<?php

/*
 * This demo demonstrates that the same instance of the Sync class
 * is used, even if two php programs use it.
 *
 * Should be run using PHP Cli
 */


require("../src/Fuz/Component/SharedMemory/SharedMemory.php");
require("../src/Fuz/Component/SharedMemory/Entity/StoredEntity.php");
require("../src/Fuz/Component/SharedMemory/Storage/StorageInterface.php");
require("../src/Fuz/Component/SharedMemory/Storage/StorageFile.php");

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


