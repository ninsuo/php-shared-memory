php-shared-memory
=================

Share variables across multiple PHP apps

Sync
Share variables across multiple PHP apps

This class works like \stdClass, but one instance of Sync can be used simultaneously in several PHP applications.

Because this class stores and restores its data every time a property is requested or set, data are always fresh between
your applications. And because PHP has a great built-in advisory lock feature, there could be as many applications as
you want, there is no concurrent access to the synchronization file.

--------- Use cases

Long-running tasks : when there is a long-running task run in background from a web application,
this is diffcult to display progression information. With Sync, just set $sync->progress = x in your
task, and echo $sync->progress in your web app.

Multi task : there is no built-in threads functions in PHP, so if we need to simulate threads, we execute
several PHP tasks (forks, execs, ...), and keep control on resources and results. But from here, there is
no way for all children processes to communicate each other. Sync gives you a centralized data pool, where
every processes can put about anything.

--------- How does it work ?

PHP has magic methods:

    `__get($property)` let us implement the access of a $property on an object
    `__set($property, $value)` let us implement the assignation of a $property on an object

PHP can serialize variables:

    `serialize($variable)` returns a string representation of the variable
    `unserialize($string)` returns back a variable from a string

PHP can handle files, with concurrent-access management:

    `fopen($file, 'c+')` opens a file with advisory lock options enabled (allow you to use flock)
    `flock($descriptor, LOCK_SH)` takes a shared lock (for reading)
    `flock($descriptor, LOCK_EX)` takes an exclusive lock (for writting)

So, Sync is working this way:

    When constructing a new Sync, a file is required to store a \stdClass instance that will be serialized / unserialized.
    When requiring a property of a Sync object, __get method restores the variable from that file and returns associated value.
    When assigning a new property of a Sync object, __set method restores the variable too, and sets a new property/value pair to it.

--------- Optimizations

Of course, if you have 150 processes working on the same file at the same time, your hard drive will slow down your processes.
To handle this issue, if you're on a Linux system, you can create a filesystem partition on RAM.
Writing into a file stored in RAM will be about as quick as writing in memory.

As root, type the following commands:

```
mkfs -q /dev/ram1 65536
mkdir -p /ram
mount /dev/ram1 /ram
```