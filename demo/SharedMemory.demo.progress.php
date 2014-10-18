<?php

/*
 * This demo show you how you can synchronize long-running tasks with your web application.
 *
 * Roadmap :
 * - When you come to that page, you are invited to click on a "Start" button to launch a "long-running task".
 * - By clicking "Start", you exec() a simulation of long-running task that only sets progress percentages every 1sec
 * - When refreshing, you can see progression of your task using a simple echo $shared->percentage
 *
 * Should be run using a web browser
 */

require("../src/SharedMemory.php");
require("../src/Entity/StoredEntity.php");
require("../src/Storage/StorageInterface.php");
require("../src/Storage/StorageFile.php");

use Fuz\Component\SharedMemory\SharedMemory;
use Fuz\Component\SharedMemory\Storage\StorageFile;

$storage = new StorageFile('/tmp/demo.sync');
$shared = new SharedMemory($storage);

// Child process
if (php_sapi_name() === 'cli')
{
    // Simulates a long-running task
    for ($i = 0; ($i <=  100); $i++)
    {
        $shared->percentage = $i;
        sleep(1);
    }
    $shared->percentage = null;
    die();
}

// Button was clicked
if (isset($_POST['button']))
{
    // Execute this file with PHP Cli as a daemon (to launch long-running task)
    // See http://stackoverflow.com/a/12341511/731138
    exec(sprintf("/usr/bin/php %s > /dev/null 2>&1 &", escapeshellarg($_SERVER['SCRIPT_FILENAME'])));

    // Gives time for the demo to change percentage in our synchronized variable
    sleep(1);

    // Avoid that refresh button asks for form repost
    header(sprintf("Location: %s", filter_var($_SERVER['PHP_SELF'], FILTER_SANITIZE_STRING)));
    die();
}

if (is_null($shared->percentage))
{
    // Long-running task not executed, displaying start button

    $form = <<< 'HTML_END'

<form action="%s" method="post">

    To start long-running task, click on the following button:

    <input name="button" type="submit" value="Start" />

</form>

HTML_END;
    echo sprintf($form, filter_var($_SERVER['PHP_SELF'], FILTER_SANITIZE_STRING));
}
else
{
    // Long-running task executed, displaying progression

    echo sprintf("Program still in progress: %d%% <br/>", $shared->percentage);
    echo '<a href="#" onclick="window.location.reload(); return false;">Refresh page</a>';
}
