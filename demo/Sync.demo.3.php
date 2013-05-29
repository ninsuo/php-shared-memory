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
require("../src/Sync.php");

$sync = new Sync("/tmp/demo.sync");

// Master process (the one you launched)
if (isset($argv[1]) === false)
{
    $sync->withoutMutexAlreadyCalculated = false;
    $sync->withoutMutex = 1;
    $sync->withMutexAlreadyCalculated = false;
    $sync->withMutex = 1;

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
    echo sprintf("Without mutex, 1 + 1 = %d\n", $sync->withoutMutex); // random between 2 and 6
    echo sprintf("With a mutex, 1 + 1 = %d\n", $sync->withMutex); // 2
}

// Without mutex...
if (isset($argv[1]) === true)
{
    // for all processes, this condition is true
    if ($sync->withoutMutexAlreadyCalculated === false)
    {
        // a "long" calculation
        sleep(1);

        $sync->withoutMutex = $sync->withoutMutex + 1;
        $sync->withoutMutexAlreadyCalculated = true;
    }
}

// With a mutex...
if (isset($argv[1]) === true)
{
    $sync->lock();
    // Only one process at once can pass between lock and unlock.
    // So this condition is true only once.
    if ($sync->withMutexAlreadyCalculated === false)
    {
        // a "long" calculation
        sleep(1);

        $sync->withMutex = $sync->withMutex + 1;
        $sync->withMutexAlreadyCalculated = true;
    }
    $sync->unlock();
}

/*
 * Explaination : access to $sync->withoutMutexAlreadyCalculated and
 * $sync->withoutMutex is concurrent : it means, before those variables has
 * been set by a process, they have been read with the same value several times.
 * Result of our calculation becomes unexepected.
 */