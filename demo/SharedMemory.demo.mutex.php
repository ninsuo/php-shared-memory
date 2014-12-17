<?php

/**
 * Mutexes are used to avoid concurrent access to your shared
 * variables.
 *
 * This demo shows you:
 * - why you should use mutexes
 * - how to use mutex-style locks with Sync
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

// Master process (the one you launched)
if (isset($argv[1]) === false)
{
    $shared->withoutMutexAlreadyCalculated = false;
    $shared->withoutMutex = 1;
    $shared->withMutexAlreadyCalculated = false;
    $shared->withMutex = 1;

    // Execute 5 daemons
    // see http://stackoverflow.com/questions/12341421
    $command = sprintf("/usr/bin/php %s demo > /dev/null 2>&1 &", escapeshellarg($argv[0]));
    exec($command);
    exec($command);
    exec($command);
    exec($command);
    exec($command);

    // Wait for all daemons to end
    sleep(3);

    // Display results
    echo sprintf("Without mutex, 1 + 1 = %d\n", $shared->withoutMutex); // random between 2 and 6
    echo sprintf("With a mutex, 1 + 1 = %d\n", $shared->withMutex); // 2
}

// Without mutex...
if (isset($argv[1]) === true)
{
    // for all processes, this condition is true
    if ($shared->withoutMutexAlreadyCalculated === false)
    {
        // a "long" calculation
        sleep(1);

        $shared->withoutMutex = $shared->withoutMutex + 1;
        $shared->withoutMutexAlreadyCalculated = true;
    }
}

// With a mutex...
if (isset($argv[1]) === true)
{
    $shared->lock();
    // Only one process at once can pass between lock and unlock.
    // So this condition is true only once.
    if ($shared->withMutexAlreadyCalculated === false)
    {
        // a "long" calculation
        sleep(1);

        $shared->withMutex = $shared->withMutex + 1;
        $shared->withMutexAlreadyCalculated = true;
    }
    $shared->unlock();
}

/*
 * Explaination : access to $shared->withoutMutexAlreadyCalculated and
 * $shared->withoutMutex is concurrent : it means, before those variables has
 * been set by a process, they have been read with the same value several times.
 * Result of our calculation becomes unexepected.
 */
