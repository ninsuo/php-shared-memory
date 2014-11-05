<?php

namespace Fuz\Component\SharedMemory\Storage;

use Fuz\Component\SharedMemory\Entity\StoredEntity;

/**
 * SharedMemory
 *
 * This storage saves and restores the object to synchronize inside files.
 *
 * This class takes all its power if you use in-ram files
 * @see http://www.cyberciti.biz/faq/howto-create-linux-ram-disk-filesystem/
 *
 * @license http://opensource.org/licenses/MIT
 * @author Alain Tiemblo <alain@fuz.org>
 * @version 2.0
 */
class StorageFile implements StorageInterface
{

    const ACCESS_READ = 'r';
    const ACCESS_WRITE = 'w';

    protected $file;
    protected $fd;
    protected $access;

    public function __construct($file)
    {
        $this->file = $file;
        list($this->fd, $this->access) = null;
    }

    public function __destruct()
    {
        if (!is_null($this->fd))
        {
            $this->close();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function openReader()
    {
        if (!is_null($this->fd))
        {
            if ($this->access === self::ACCESS_READ)
            {
                return;
            }
            else
            {
                $this->close();
            }
        }

        if (!is_file($this->file))
        {
            return;
        }

        if ((is_file($this->file)) && (!is_readable($this->file)))
        {
            throw new \Exception(sprintf("File '%s' is not readable.", $this->file));
        }

        if (($fd = fopen($this->file, 'c+')) === false)
        {
            throw new \Exception(sprintf("Can't open '%s' file.", $this->file));
        }

        if (flock($fd, LOCK_SH) === false)
        {
            $this->close();
            throw new \Exception(sprintf("Can't lock '%s' file for reading.", $this->file));
        }

        $this->fd = $fd;
        $this->access = self::ACCESS_READ;
    }

    /**
     * {@inheritdoc}
     */
    public function openWriter()
    {
        if (!is_null($this->fd))
        {
            if ($this->access === self::ACCESS_WRITE)
            {
                return;
            }
            else
            {
                $this->close();
            }
        }

        if ((!is_file($this->file)) && (!is_writable(dirname($this->file))))
        {
            throw new \Exception(sprintf("Directory '%s' does not exist or is not writable.", dirname($this->file)));
        }

        if ((is_file($this->file)) && (!is_writable($this->file)))
        {
            throw new \Exception(sprintf("File '%s' is not writable.", $this->file));
        }

        if (($fd = fopen($this->file, 'c+')) === false)
        {
            throw new \Exception(sprintf("Can't open '%s' file.", $this->file));
        }

        if (flock($fd, LOCK_EX) === false)
        {
            $this->close();
            throw new \Exception(sprintf("Can't lock '%s' file for writing.", $this->file));
        }

        $this->fd = $fd;
        $this->access = self::ACCESS_WRITE;
    }

    /**
     * {@inheritdoc}
     */
    public function getObject()
    {
        $close = false;
        if (is_null($this->fd))
        {
            $this->openReader();
            $close = true;
        }
        else
        {
            rewind($this->fd);
        }

        if (is_null($this->fd))
        {
            return null;
        }

        $contents = '';
        while (($read = fread($this->fd, 32 * 1024)) !== '')
        {
            $contents .= $read;
        }

        if ($close)
        {
            $this->close();
        }

        if (empty($contents))
        {
            return null;
        }

        return unserialize($contents);
    }

    /**
     * {@inheritdoc}
     */
    public function setObject(StoredEntity $object)
    {
        $close = false;
        if (is_null($this->fd))
        {
            $this->openWriter();
            $close = true;
        }
        else
        {
            rewind($this->fd);
        }

        ftruncate($this->fd, 0);
        fwrite($this->fd, serialize($object));

        if ($close)
        {
            $this->close();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        if (!is_null($this->fd))
        {
            flock($this->fd, LOCK_UN);
            fclose($this->fd);
            list($this->fd, $this->access) = null;
        }
    }

    public function getName()
    {
        return 'file';
    }

}
