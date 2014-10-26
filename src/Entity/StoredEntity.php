<?php

namespace Fuz\Component\SharedMemory\Entity;

/**
 * Contains the object that needs to be stored on a shared resource.
 *
 * @license http://opensource.org/licenses/MIT
 * @author Alain Tiemblo <alain@fuz.org>
 * @version 2.0
 */
class StoredEntity
{

    protected $data;
    protected $locked;
    protected $timeout;
    protected $interval;

    public function __construct()
    {
        $this->reset();
    }

    public function setData(\stdClass $data)
    {
        $this->data = $data;
        return $this;
    }

    public function getData()
    {
        return $this->data;
    }

    public function setLocked($locked)
    {
        if (is_bool($locked))
        {
            $this->locked = $locked;
        }
        return $this;
    }

    public function isLocked()
    {
        return $this->locked;
    }

    public function setTimeout($timeout)
    {
        if ((is_numeric($timeout)) && ($timeout >= 0))
        {
            $this->timeout = $timeout;
        }
        return $this;
    }

    public function getTimeout()
    {
        return $this->timeout;
    }

    public function setInterval($interval)
    {
        if ((is_numeric($interval)) && ($interval >= 5000))
        {
            $this->interval = $interval;
        }
        return $this;
    }

    public function getInterval()
    {
        return $this->interval;
    }

    public function reset()
    {
        $this->data = new \stdClass();
        $this->locked = false;
        $this->timeout = 0;
        $this->interval = 50000;
    }

    public function __wakeup()
    {
        if (!($this->data instanceof \stdClass))
        {
            $this->data = new \stdClass();
        }
        if (!is_bool($this->locked))
        {
            $this->locked = false;
        }
        if (!((is_numeric($this->timeout)) && ($this->timeout >= 0)))
        {
            $this->timeout = 0;
        }
        if (!((is_numeric($this->interval)) && ($this->interval >= 5000)))
        {
            $this->interval = 5000;
        }
    }

}
