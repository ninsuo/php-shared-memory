<?php

namespace Fuz\Component\SharedMemory;

use Fuz\Component\SharedMemory\Entity\StoredEntity;
use Fuz\Component\SharedMemory\Storage\StorageInterface;

/**
 * Works like stdClass object or an array, but always safely
 * sync its content on/from a storage, making it sharable
 * between concurrent processes.
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
 * @license http://opensource.org/licenses/MIT
 * @author Alain Tiemblo <alain@fuz.org>
 * @version 2.0
 */
class SharedMemory implements \ArrayAccess
{

    protected $storage;
    protected $lock;

    /**
     * Constructor
     *
     * @access public
     * @param StorageInterface $storage
     */
    public function __construct(StorageInterface $storage)
    {
        $this->storage = $storage;
        $this->lock = false;
    }

    /**
     * Magic method that implements property existance check.
     *
     * Makes you able to use:
     *   $shared = new SharedMemory($storage);
     *   isset($shared->data);
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
     * Makes you able to use:
     *   $shared = new SharedMemory($storage);
     *   isset($shared['data']);
     *
     * @access public
     * @param mixed $offset
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
     * Makes you able to use:
     *   $shared = new SharedMemory($storage);
     *   $var = $shared['data'];
     *
     * @access public
     * @param mixed $offset
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
        $this->storage->openReader();
        $object = $this->getObjectSafely('openReader');
        $this->storage->close();

        $data = $object->getData();
        if (!property_exists($data, $property))
        {
            return $default;
        }

        return $data->{$property};
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
     * Makes you able to use:
     *   $shared = new SharedMemory($storage);
     *   $shared['data'] = $var;
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
        $this->storage->openWriter();
        $object = $this->getObjectSafely('openWriter');
        $data = $object->getData();
        $data->{$property} = $value;
        $object->setData($data);
        $this->storage->setObject($object);
        $this->storage->close();
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
     * Makes you able to use:
     *   $shared = new SharedMemory($storage);
     *   unset($shared['data']);
     *
     * @access public
     * @param mixed $offset
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
        $this->storage->openWriter();
        $object = $this->getObjectSafely('openWriter');
        $data = $object->getData();
        if (property_exists($data, $property))
        {
            unset($data->{$property});
        }
        $object->setData($data);
        $this->storage->setObject($object);
        $this->storage->close();
        return $this;
    }

    /**
     * Locks synchronized variable to the current process.
     *
     * This is useful to avoid concurrent accesses, such as :
     *
     * if (is_null($shared->check)) {
     *   $shared->data = "something";
     * }
     *
     * In the example above, the condition can be true for several processes
     * if $shared->check is accessed simultaneously.
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

        $this->storage->openWriter();

        $object = $this->getObjectSafely('openWriter');
        $object->setLocked(true);
        $object->setTimeout($timeout);
        $object->setInterval($interval);

        $this->lock = true;
        $this->storage->setObject($object);
        $this->storage->close();
    }

    /**
     * Unlocks synchronized variable, making it available for
     * all processes that uses it. Note that any app using a
     * shared object can unlock it.
     *
     * @access public
     * @return self
     */
    public function unlock()
    {
        $this->storage->openWriter();
        $object = $this->getObjectSafely('openWriter');
        $object->setLocked(false);
        $this->lock = false;
        $this->storage->setObject($object);
        $this->storage->close();
        return $this;
    }

    /**
     * Get current storage
     *
     * @access public
     * @return string
     */
    public function getStorage()
    {
        return $this->storage;
    }

    /**
     * Set new storage
     *
     * @access public
     * @param StorageInterface $storage
     * @return self
     */
    public function setStorage(StorageInterface $storage)
    {
        $this->storage = $storage;
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
        $this->storage->openReader();
        $object = $this->getObjectSafely('openReader');
        $this->storage->close();
        return $object->getData();
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
        $this->storage->openWriter();
        $object = $this->getObjectSafely('openWriter');
        $object->setData($data);
        $this->storage->setObject($object);
        $this->storage->close();
        return $this;
    }

    /**
     * Creates or validates an object coming from storage.
     *
     * @access protected
     * @return StoredEntity
     */
    protected function getObject()
    {
        $object = $this->storage->getObject();
        if (!($object instanceof StoredEntity))
        {
            $object = new StoredEntity();
        }
        return $object;
    }

    /**
     * Recovers the object if the mutex-style lock is released.
     *
     * @access protected
     * @param callable $openCallback
     * @return StoredEntity
     * @throws \Exception
     */
    protected function getObjectSafely($openCallback)
    {
        $elapsed = 0;
        $object = $this->getObject();
        if ($this->lock === false)
        {
            while ($object->isLocked())
            {
                $this->storage->close();
                usleep($object->getInterval());
                $this->storage->{$openCallback}();
                $object = $this->getObject();
                if ($object->getTimeout() > 0)
                {
                    $elapsed += $object->getInterval();
                    if (floor($elapsed / 1000000) >= $object->getTimeout())
                    {
                        throw new \Exception(sprintf("Can't access shared object, it is still locked after %d second(s).",
                           $object->getTimeout()));
                    }
                }
            }
        }
        return $object;
    }

}
