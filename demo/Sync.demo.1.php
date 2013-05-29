<?php

/*
 * This demo demonstrates that the same instance of the Sync class
 * is used, even if two php programs use it.
 *
 * Should be run using PHP Cli
 */

require("../src/Sync.php");

$sync = new Sync("/tmp/demo.sync");

if (isset($argv[1]) === false)
{
    // master process (the one you launched)
    $sync->hello = "foo, bar!\n";

    $command = sprintf("/usr/bin/php %s demo", escapeshellarg($argv[0]));
    exec($command);

    echo $sync->hello;
}
else
{
    // child process
    $sync->hello = "Hello, world!\n";
}


