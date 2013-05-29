<?php

/**
 * Works like stdClass object or an array, but always safely
 * sync its content on/from a file, making it sharable between
 * concurrent processes.
 *
 * This class helps to create an unique instance of a class
 * usable by several processes. This is useful when you
 * need to synchronize long-running tasks with a web
 * application, or if you deal with threads simulation
 * issues.
 *
 * This class cannot share resources (db connections, file descriptors,
 * socked handles and so on), as serialize() can't.
 *
 * This class was created to answer the below stackoverflow question.
 * @see http://stackoverflow.com/questions/16415206
 *
 * This class takes all its power if you use in-ram files
 * @see http://www.cyberciti.biz/faq/howto-create-linux-ram-disk-filesystem/
 *
 * @license http://opensource.org/licenses/bsd-license.html
 * @author Alain Tiemblo <alain@fuz.org>
 * @version 1.5
 */
class Sync implements ArrayAccess
{

    private $_file;
    private $_lock;

    public function __construct($file)
    {
        $this->_file = $file;
        $this->_lock = false;
    }

    /**
     * Magic method that implements property existance check.
     *
     * Make you able to use:
     *   $sync = new Sync();
     *   isset($sync->data);
     *
     * @access public
     * @param mixed $property
     * @return bool
     * @throws \Exception
     */
    public function __isset($property)
    {
        return $this->has($property);
    }

    /**
     * Implement isset when accessing a Sync object as an array
     *
     * Make you able to use:
     *   $sync = new Sync();
     *   isset($sync['data']);
     *
     * @access public
     * @param mixed $property
     * @return bool
     * @see \ArrayAccess
     */
    public function offsetExists($offset)
    {
        return $this->has($offset);
    }

    /**
     * Checks that a property exists and is not null in the shared object.
     *
     * @access public
     * @param mixed $property
     * @return bool
     * @throws \Exception
     */
    public function has($property)
    {
        $test = $this->{$property};
        return isset($test);
    }

    /**
     * Magic method that implements property access.
     *
     * @access public
     * @param mixed $property
     * @return mixed
     * @throws \Exception
     */
    public function __get($property)
    {
        return $this->get($property);
    }

    /**
     * Implement data access when reading a Sync object like an array
     *
     * Make you able to use:
     *   $sync = new Sync();
     *   $var = $sync['data'];
     *
     * @access public
     * @param mixed $property
     * @return mixed
     * @throws \Exception
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * Returns a value from the shared object
     *
     * @access public
     * @param mixed $property
     * @param mixed $default
     * @return mixed
     * @throws \Exception
     */
    public function get($property, $default = null)
    {
        $fd = $this->_openReader();

        if (is_null($fd))
        {
            return $default;
        }

        $object = $this->_getObjectSafely($fd, '_openReader');
        $this->_closeUnlock($fd);

        if (!property_exists($object->data, $property))
        {
            return $default;
        }

        return $object->data->{$property};
    }

    /**
     * Magic method that implements property assignation.
     *
     * @access public
     * @param mixed $property
     * @param mixed $value
     * @return $value
     * @throws \Exception
     */
    public function __set($property, $value)
    {
        $this->set($property, $value);
        return $value;
    }

    /**
     * Implement data access when writting a Sync object like an array
     *
     * Make you able to use:
     *   $sync = new Sync();
     *   $sync['data'] = $var;
     *
     * @access public
     * @param mixed $property
     * @param mixed $value
     * @return $value
     * @throws \Exception
     */
    public function offsetSet($offset, $value)
    {
        $this->set($offset, $value);
        return $value;
    }

    /**
     * Add a new property to the shared object.
     *
     * @access public
     * @param mixed $property
     * @param mixed $value
     * @return self
     * @throws \Exception
     */
    public function set($property, $value)
    {
        $fd = $this->_openWriter();
        $object = $this->_getObjectSafely($fd, '_openWriter');
        $object->data->{$property} = $value;
        $this->_setObject($fd, $object);
        $this->_closeUnlock($fd);
        return $value;
    }

    /**
     * Magic method that implements property deletion.
     *
     * @access public
     * @param mixed $property
     * @throws \Exception
     */
    public function __unset($property)
    {
        $this->remove($property);
    }

    /**
     * Implement data removal when unsetting a Sync property
     * accessed like an array
     *
     * Make you able to use:
     *   $sync = new Sync();
     *   unset($sync['data']);
     *
     * @access public
     * @param mixed $property
     * @throws \Exception
     */
    public function offsetUnset($offset)
    {
        $this->remove($offset);
    }

    /**
     * Removes a value from the shared object.
     *
     * @access public
     * @param mixed $property
     * @return self
     * @throws \Exception
     */
    public function remove($property)
    {
        $fd = $this->_openWriter();
        $object = $this->_getObjectSafely($fd, '_openWriter');
        if (property_exists($object->data, $property))
        {
            unset($object->data->{$property});
        }
        $this->_setObject($fd, $object);
        $this->_closeUnlock($fd);
        return $this;
    }

    /**
     * Locks synchronized variable to the current process.
     *
     * This is useful to avoid concurrent accesses, such as :
     *
     * if (is_null($sync->check)) {
     *   $sync->data = "something";
     * }
     *
     * In the example above, the condition can be true for several processes
     * if $sync->check is accessed simultaneously.
     *
     * @access public
     * @param int $timeout Define how many seconds the shared variable should be locked (0 = unlimited)
     * @param int $interval Microseconds between each lock check when a process awaits unlock
     * @return self
     * @throws \Exception
     */
    public function lock($timeout = 0, $interval = 50000)
    {
        if ((!is_numeric($timeout)) || ($timeout < 0))
        {
            throw new \Exception("Lock timeout should be an integer greater or equals to 0.");
        }

        if ((!is_numeric($interval)) || ($interval < 5000))
        {
            throw new \Exception("Lock check interval should be an integer greater or equals to 5000.");
        }

        $fd = $this->_openWriter();

        $object = $this->_getObjectSafely($fd, '_openWriter');
        $object->lock = true;
        $object->timeout = $timeout;
        $object->interval = $interval;

        $this->_lock = true;
        $this->_setObject($fd, $object);
        $this->_closeUnlock($fd);
    }

    /**
     * Unlocks synchronized variable, making it available for
     * all processes that use it.
     *
     * @access public
     * @return self
     */
    public function unlock()
    {
// It may be useful to be able to unlock the shared variable from any process
//        if ($this->_lock)
//        {
            $fd = $this->_openWriter();
            $object = $this->_getObjectSafely($fd, '_openWriter');
            $object->lock = false;
            $this->_lock = false;
            $this->_setObject($fd, $object);
            $this->_closeUnlock($fd);
//        }
        return $this;
    }

    /**
     * Get current synchronization file
     *
     * @access public
     * @return string
     */
    public function getFile()
    {
        return $this->_file;
    }

    /**
     * Set new synchronization file
     *
     * @access public
     * @param string $file
     * @return self
     */
    public function setFile($file)
    {
        $this->_file = $file;
        return $this;
    }

    /**
     * Get the whole object, raw, for quicker access to its properties.
     *
     * This class delivers objects designed to be always safely synchronized
     * with a file, to allow safe concurrent accesses. To avoid getting in
     * trouble, you should use lock() method before using getData and
     * unlock() method after using setData.
     *
     * @access public
     * @return \stdClass
     * @throws \Exception
     */
    public function getData()
    {
        $fd = $this->_openReader();
        if (is_null($fd))
        {
            return new \stdClass();
        }
        $object = $this->_getObjectSafely($fd, '_openWriter');
        $this->_closeUnlock($fd);
        return $object->data;
    }

    /**
     * Set the whole object, replacing all its properties and values by the new ones.
     *
     * This class delivers objects designed to be always safely synchronized
     * with a file, to allow safe concurrent accesses. To avoid getting in
     * trouble, you should use lock() method before using getData and
     * unlock() method after using setData.
     *
     * @access public
     * @param \stdClass $data
     * @return self
     * @throws \Exception
     */
    public function setData(\stdClass $data)
    {
        $fd = $this->_openWriter();
        $object = $this->_getObjectSafely($fd, '_openWriter');
        $object->data = $data;
        $this->_setObject($fd, $object);
        $this->_closeUnlock($fd);
        return $this;
    }

    protected function _openReader()
    {
        // File does not exist
        if (!is_file($this->_file))
        {
            return null;
        }

        // Check if file is readable
        if ((is_file($this->_file)) && (!is_readable($this->_file)))
        {
            throw new \Exception(sprintf("File '%s' is not readable.", $this->_file));
        }

        // Open file with advisory lock option enabled for reading and writting
        if (($fd = fopen($this->_file, 'c+')) === false)
        {
            throw new \Exception(sprintf("Can't open '%s' file.", $this->_file));
        }

        // Request a lock for reading (hangs until lock is granted successfully)
        if (flock($fd, LOCK_SH) === false)
        {
            throw new \Exception(sprintf("Can't lock '%s' file for reading.", $this->_file));
        }

        return $fd;
    }

    protected function _openWriter()
    {
        // Check if directory is writable if file does not exist
        if ((!is_file($this->_file)) && (!is_writable(dirname($this->_file))))
        {
            throw new \Exception(sprintf("Directory '%s' does not exist or is not writable.", dirname($this->_file)));
        }

        // Check if file is writable if it exists
        if ((is_file($this->_file)) && (!is_writable($this->_file)))
        {
            throw new \Exception(sprintf("File '%s' is not writable.", $this->_file));
        }

        // Open file with advisory lock option enabled for reading and writting
        if (($fd = fopen($this->_file, 'c+')) === false)
        {
            throw new \Exception(sprintf("Can't open '%s' file.", $this->_file));
        }

        // Request a lock for writting (hangs until lock is granted successfully)
        if (flock($fd, LOCK_EX) === false)
        {
            throw new \Exception(sprintf("Can't lock '%s' file for writing.", $this->_file));
        }

        return $fd;
    }

    protected function _closeUnlock($fd)
    {
        // Release lock and close file
        flock($fd, LOCK_UN);
        fclose($fd);
    }

    protected function _getObject($fd)
    {
        // A hand-made file_get_contents
        $contents = '';
        while (($read = fread($fd, 32 * 1024)) !== '')
        {
            $contents .= $read;
        }

        // Restore shared data object
        if (empty($contents))
        {
            $object = $this->_createObject();
        }
        else
        {
            $object = $this->_validateObject($contents);
        }

        return $object;
    }

    protected function _setObject($fd, \stdClass $object)
    {
        // Go back at the beginning of file
        rewind($fd);

        // Truncate file
        ftruncate($fd, 0);

        // Save shared data object to the file
        fwrite($fd, serialize($object));
    }

    protected function _createObject()
    {
        $object = new stdClass();
        $object->data = new stdClass();
        $object->lock = false;
        $object->timeout = 0;
        $object->interval = 50000;
        return $object;
    }

    protected function _validateObject($contents)
    {
        $object = @unserialize($contents);
        if (($object === false)
           || (!is_object($object))
           || (!($object instanceof \stdClass))
           || (!property_exists($object, 'data'))
           || (!is_object($object->data))
           || (!($object->data instanceof \stdClass))
           || (!property_exists($object, 'lock'))
           || (!is_bool($object->lock))
           || (!property_exists($object, 'timeout'))
           || (!is_numeric($object->timeout))
           || ($object->timeout < 0)
           || (!property_exists($object, 'interval'))
           || (!is_numeric($object->interval))
           || ($object->interval < 5000))
        {
            $object = $this->_createObject();
        }
        return $object;
    }

    protected function _getObjectSafely(&$fd, $openMethod)
    {
        $elapsed = 0;
        $object = $this->_getObject($fd);
        if ($this->_lock === false)
        {
            while ($object->lock)
            {
                $this->_closeUnlock($fd);
                usleep($object->interval);
                $fd = $this->{$openMethod}();
                $object = $this->_getObject($fd);
                if ($object->timeout > 0)
                {
                    $elapsed += $object->interval;
                    if (floor($elapsed / 1000000) >= $object->timeout)
                    {
                        throw new \Exception(sprintf("Can't access shared object, it is still locked after %d second(s).", $object->timeout));
                    }
                }
            }
        }
        return $object;
    }

}