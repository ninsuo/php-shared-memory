php-shared-memory
=================


Share variables across multiple PHP apps

This class works like `\stdClass`, but one instance of SharedMemory can be used simultaneously in several PHP applications.

Because this class stores and restores its data every time a property is requested or set, data are always fresh between
your applications. And because PHP has a great built-in advisory lock feature, there could be as many applications as
you want, there is no concurrent access to the synchronization file.

Use cases
---------

Long-running tasks : when there is a long-running task run in background from a web application,
this is diffcult to display progression information. With SharedMemory, just set $shared->progress = x in your
task, and echo $shared->progress in your web app.

Multi task : there is no built-in threads functions in PHP, so if we need to simulate threads, we execute
several PHP tasks (forks, execs, ...), and keep control on resources and results. But from here, there is
no way for all children processes to communicate each other. Sync gives you a centralized data pool, where
every processes can put about anything.

Installation
---------

If you want a standalone file to manage your shared memory, you can look at the `v1.5.0` release:
https://github.com/ninsuo/php-shared-memory/releases/tag/v1.5.0

If you're building a real-life project, you'd better use Composer:

### install Composer

If you have curl, you can use:

`curl -sS https://getcomposer.org/installer | php`

Else, you can use the PHP method instead:

`php -r "readfile('https://getcomposer.org/installer');" | php`

#### Add the following to your `composer.json`:

```json
{
    "require": {
        "ninsuo/php-shared-memory": "dev-master"
    }
}
```

#### Update

`php composer.phar update`

Usage
---------

This class works the same way as `stdClass`, but you should give a storage in its constructor.
This storage will be used to store and retrieve your data: use the same storage on several apps to get the same instance of a variable.

```php

require("vendor/autoload.php");

use Fuz\Component\SharedMemory\Storage\StorageFile;
use Fuz\Component\SharedMemory\SharedMemory;

// On both apps
$storage = new StorageFile('/tmp/demo.sync');

// First app
$sharedA = new SharedMemory($storage);
$sharedA->foo = 'bar';

// Second app
$sharedB = new SharedMemory($storage);
echo $sharedB->foo; // bar

```

Or a complete working example:

```php
<?php

require("vendor/autoload.php");

use Fuz\Component\SharedMemory\SharedMemory;
use Fuz\Component\SharedMemory\Storage\StorageFile;

$storage = new StorageFile('/tmp/demo.sync');
$shared = new SharedMemory($storage);

if (isset($argv[1]) === false)
{
    // master process (the one you launched)
    $shared->hello = "foo, bar!\n";

    $command = sprintf("/usr/bin/php %s demo", escapeshellarg($argv[0]));
    exec($command);

    echo $shared->hello;
}
else
{
    // child process
    $shared->hello = "Hello, world!\n";
}

// $ php demo/Sync.demo.1.php
// Hello, world!
// $

```

How does it work ?
---------

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

So, SharedMemory and StorageFile are working this way:

- When constructing a new `SharedMemory`, a file (wrapped in a Storage object) is required to store a `\stdClass` instance that will be serialized / unserialized.
- When requiring a property of a `SharedMemory` object, `__get` method restores the variable from that file and returns associated value.
- When assigning a new property of a `SharedMemory` object, `__set` method restores the variable too, and sets a new property/value pair to it.

Optimizations
---------

Of course, if you have 150 processes working on the same file at the same time, your hard drive will slow down your processes.
To handle this issue, if you're on a Linux system, you can create a filesystem partition on RAM.
Writing into a file stored in RAM will be about as quick as writing in memory.

As root, type the following commands:

```
mkfs -q /dev/ram1 65536
mkdir -p /ram
mount /dev/ram1 /ram
```
