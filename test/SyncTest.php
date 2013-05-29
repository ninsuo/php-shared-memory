<?php

require(__DIR__ . "/../src/Sync.php");

//die("Please read warning inside that file and comment this die() before running tests.");

/**
 * Test Case for Sync class
 *
 * Warning:
 * This test case creates a directory in /tmp, check that
 * permissions are granted and the 'sync' directory not
 * already in use.
 *
 * Requires PHPUnit (developped using 3.6.12)
 */
class SyncTest extends PHPUnit_Framework_TestCase
{

    const DIR = "/tmp/sync";

    public function setUp()
    {
        if (!is_dir(self::DIR))
        {
            if (!mkdir(self::DIR))
            {
                throw new \Exception(sprintf("Could not create test directory: %s\n", self::DIR));
            }
        }
    }

    public function tearDown()
    {
        // Removes all files and directories inside self::DIR
        $files = new \RecursiveIteratorIterator(
           new \RecursiveDirectoryIterator(self::DIR, \RecursiveDirectoryIterator::SKIP_DOTS),
           \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileinfo)
        {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }
        rmdir(self::DIR);
    }

    public function testGetNoFile()
    {
        $file = self::DIR . "/test.sync";
        $sync = new Sync($file);

        $this->assertNull($sync->test);
        $this->assertFalse(is_file($file));
    }

    public function testGetFileExistsButNotReadable()
    {
        $file = self::DIR . "/test.sync";

        $fd = fopen($file, 'w');
        fclose($fd);
        chmod($file, 0000);

        $sync = new Sync($file);
        try
        {
            $test = $sync->test;
        }
        catch (\Exception $e)
        {
            $expected = sprintf("File '%s' is not readable.", $file);
            $this->assertEquals($expected, $e->getMessage());
            return;
        }

        $this->fail("Expected exception, but never raised.");
    }

    public function testGetPropertyNotFound()
    {
        $file = self::DIR . "/test.sync";
        $sync = new Sync($file);

        $this->assertNull($sync->not_found);
        $this->assertNull($sync->{0});
        $this->assertNull($sync->{'重庆'});
    }

    public function testGetSetSmallValue()
    {
        $file = self::DIR . "/test.sync";
        $sync = new Sync($file);

        $sync->data = "test a";
        $this->assertEquals("test a", $sync->data);

        $sync->{0} = "test b";
        $this->assertEquals("test b", $sync->{0});

        $sync->{'重庆'} = "test e";
        $this->assertEquals("test e", $sync->{'重庆'});

        $test = $sync->hello = 'world';
        $this->assertEquals('world', $test);
    }

    public function testGetSetBigValue()
    {
        $value = str_repeat("abcdefghijklmnopqrstuvwxyz", 64 * 1024 + 1);

        $file = self::DIR . "/test.sync";
        $sync = new Sync($file);

        $sync->data = $value;
        $this->assertEquals($value, $sync->data);

        $sync->{0} = $value;
        $this->assertEquals($value, $sync->{0});

        $sync->{'重庆'} = $value;
        $this->assertEquals($value, $sync->{'重庆'});

        $test = $sync->hello = $value;
        $this->assertEquals($value, $test);
    }

    public function testSetDirectoryDoesNotExists()
    {
        $dir = self::DIR . "/xxx";
        $file = $dir . "/test.sync";

        $sync = new Sync($file);
        try
        {
            $sync->test = "fail";
        }
        catch (\Exception $e)
        {
            $expected = sprintf("Directory '%s' does not exist or is not writable.", $dir);
            $this->assertEquals($expected, $e->getMessage());
            return;
        }

        $this->fail("Expected exception, but never raised.");
    }

    public function testSetNoFileDirectoryNotWritable()
    {
        $dir = self::DIR . "/xxx";
        mkdir($dir);
        chmod($dir, 000);

        $file = $dir . "/test.sync";

        $sync = new Sync($file);
        try
        {
            $sync->test = "fail";
        }
        catch (\Exception $e)
        {
            $expected = sprintf("Directory '%s' does not exist or is not writable.", $dir);
            $this->assertEquals($expected, $e->getMessage());
            chmod($dir, 770);
            return;
        }

        chmod($dir, 770);
        $this->fail("Expected exception, but never raised.");
    }

    public function testSetFileExistsButNotWritable()
    {
        $file = self::DIR . "/test.sync";

        $fd = fopen($file, 'w');
        fclose($fd);
        chmod($file, 0000);

        $sync = new Sync($file);
        try
        {
            $sync->test = "fail";
        }
        catch (\Exception $e)
        {
            $expected = sprintf("File '%s' is not writable.", $file);
            $this->assertEquals($expected, $e->getMessage());
            return;
        }

        $this->fail("Expected exception, but never raised.");
    }

    public function testSetNoFileButDirectoryWritable()
    {
        $file = self::DIR . "/test.sync";
        $sync = new Sync($file);
        $this->assertFalse(file_exists($file));
        $sync->hello = "world";
        $this->assertTrue(file_exists($file));
        $this->assertEquals("world", $sync->hello);
    }

    public function testSetFileAlreadyExisting()
    {
        $file = self::DIR . "/test.sync";
        $sync = new Sync($file);
        $this->assertFalse(file_exists($file));
        $sync->hello = "world";
        $this->assertTrue(file_exists($file));
        $this->assertEquals("world", $sync->hello);
        $sync->hello = "foo";
        $this->assertEquals("foo", $sync->hello);
    }

    public function testIssetNotSetFileDoesNotExists()
    {
        $file = self::DIR . "/test.sync";
        $sync = new Sync($file);
        $sync->hello = "world";
        unlink($file);
        $this->assertFalse(isset($sync->hello));
    }

    public function testIssetNotSetPropertyDoesNotExists()
    {
        $file = self::DIR . "/test.sync";
        $sync = new Sync($file);
        $sync->hello = "world";
        $this->assertFalse(isset($sync->foo));
    }

    public function testIssetNotSetPropertyIsNull()
    {
        $file = self::DIR . "/test.sync";
        $sync = new Sync($file);
        $sync->hello = null;
        $this->assertFalse(isset($sync->hello));
    }

    public function testIssetPropertyExists()
    {
        $file = self::DIR . "/test.sync";
        $sync = new Sync($file);
        $sync->hello = 0;
        $this->assertTrue(isset($sync->hello));
        $sync->hello = '';
        $this->assertTrue(isset($sync->hello));
        $sync->hello = false;
        $this->assertTrue(isset($sync->hello));
        $sync->hello = array();
        $this->assertTrue(isset($sync->hello));
        $sync->hello = new \stdClass();
        $this->assertTrue(isset($sync->hello));
        $sync->hello = 42;
        $this->assertTrue(isset($sync->hello));
    }

    public function testUnsetNoFile()
    {
        $file = self::DIR . "/test.sync";
        $sync = new Sync($file);
        $sync->hello = "world";
        unlink($file);
        unset($sync->hello);
    }

    public function testUnsetPropertyNeverSet()
    {
        $file = self::DIR . "/test.sync";
        $sync = new Sync($file);
        $sync->hello = "world";
        unset($sync->test);
    }

    public function testUnsetPropertyExists()
    {
        $file = self::DIR . "/test.sync";
        $sync = new Sync($file);
        $sync->hello = "world";
        $sync->foo = "bar";
        unset($sync->hello);
        $this->assertNull($sync->hello);
        $this->assertEquals("bar", $sync->foo);
    }

    public function testCRUD()
    {
        $file = self::DIR . "/test.sync";
        $sync = new Sync($file);

        $this->assertNull($sync->get('hello'));
        $this->assertNull($sync->get('foo'));

        $this->assertFalse($sync->has('hello'));
        $this->assertFalse($sync->has('foo'));

        $this->assertEquals('world', $sync->set('hello', 'world'));
        $this->assertEquals('bar', $sync->set('foo', 'bar'));

        $this->assertEquals('world', $sync->get('hello'));
        $this->assertEquals('bar', $sync->get('foo'));

        $this->assertTrue($sync->has('hello'));
        $this->assertTrue($sync->has('foo'));

        $sync->remove('foo');

        $this->assertNull($sync->get('foo'));
        $this->assertFalse($sync->has('foo'));
    }

    public function testObjectCRUD()
    {
        $file = self::DIR . "/test.sync";
        $sync = new Sync($file);

        $this->assertNull($sync->hello);
        $this->assertNull($sync->foo);

        $this->assertFalse(isset($sync->hello));
        $this->assertFalse(isset($sync->foo));

        $this->assertEquals('world', ($sync->hello = 'world'));
        $this->assertEquals('bar', ($sync->foo = 'bar'));

        $this->assertEquals('world', $sync->hello);
        $this->assertEquals('bar', $sync->foo);

        $this->assertTrue(isset($sync->hello));
        $this->assertTrue(isset($sync->foo));

        unset($sync->foo);

        $this->assertNull($sync->foo);
        $this->assertFalse(isset($sync->foo));
    }

    public function testArrayCRUD()
    {
        $file = self::DIR . "/test.sync";
        $sync = new Sync($file);

        $file = self::DIR . "/test.sync";
        $sync = new Sync($file);

        $this->assertNull($sync['hello']);
        $this->assertNull($sync['foo']);

        $this->assertFalse(isset($sync['hello']));
        $this->assertFalse(isset($sync['foo']));

        $this->assertEquals('world', ($sync['hello'] = 'world'));
        $this->assertEquals('bar', ($sync['foo'] = 'bar'));

        $this->assertEquals('world', $sync['hello']);
        $this->assertEquals('bar', $sync['foo']);

        $this->assertTrue(isset($sync['hello']));
        $this->assertTrue(isset($sync['foo']));

        unset($sync['foo']);

        $this->assertNull($sync['foo']);
        $this->assertFalse(isset($sync['foo']));
    }

    public function testLockTimeoutNotNumeric()
    {
        $file = self::DIR . "/test.sync";
        $sync = new Sync($file);

        try
        {
            $sync->lock(array());
        }
        catch (\Exception $e)
        {
            $expected = 'Lock timeout should be an integer greater or equals to 0.';
            $this->assertEquals($expected, $e->getMessage());
            return;
        }

        $this->fail("Expected exception, but never raised.");
    }

    public function testLockTimeoutLesserThanZero()
    {
        $file = self::DIR . "/test.sync";
        $sync = new Sync($file);

        try
        {
            $sync->lock(-42);
        }
        catch (\Exception $e)
        {
            $expected = 'Lock timeout should be an integer greater or equals to 0.';
            $this->assertEquals($expected, $e->getMessage());
            return;
        }

        $this->fail("Expected exception, but never raised.");
    }

    public function testLockIntervalNotNumeric()
    {
        $file = self::DIR . "/test.sync";
        $sync = new Sync($file);

        try
        {
            $sync->lock(0, array());
        }
        catch (\Exception $e)
        {
            $expected = 'Lock check interval should be an integer greater or equals to 5000.';
            $this->assertEquals($expected, $e->getMessage());
            return;
        }

        $this->fail("Expected exception, but never raised.");
    }

    public function testLockIntervalLesserThan5000()
    {
        $file = self::DIR . "/test.sync";
        $sync = new Sync($file);

        try
        {
            $sync->lock(4999, array());
        }
        catch (\Exception $e)
        {
            $expected = 'Lock check interval should be an integer greater or equals to 5000.';
            $this->assertEquals($expected, $e->getMessage());
            return;
        }

        $this->fail("Expected exception, but never raised.");
    }

    public function testLock()
    {
        $file = self::DIR . "/test.sync";
        $syncA = new Sync($file);
        $syncA->hello = 'world';
        $syncA->lock(1);

        $syncB = new Sync($file);
        try
        {
            $syncB->hello;
        }
        catch (\Exception $e)
        {
            $expected = "Can't access shared object, it is still locked after 1 second(s).";
            $this->assertEquals($expected, $e->getMessage());
            return;
        }

        $this->fail("Expected exception, but never raised.");
    }

    public function testUnlock()
    {
        $file = self::DIR . "/test.sync";
        $syncA = new Sync($file);
        $syncA->hello = 'world';
        $syncA->lock(1);
        $syncA->foo = 'bar';
        $syncA->unlock();
        $syncB = new Sync($file);
        try
        {
            $test = $syncB->hello;
        }
        catch (\Exception $e)
        {
            $this->fail(sprintf("Unexpected exception has been raised: %s.", $e->getMessage()));
            return;
        }
        $this->assertEquals('world', $test);
    }

    public function testGetSetFile()
    {
        $fileA = self::DIR . "/testA.sync";
        $fileB = self::DIR . "/testB.sync";
        $sync = new Sync($fileA);
        $this->assertEquals($fileA, $sync->getFile());
        $sync->hello = 'world';
        $this->assertEquals('world', $sync->hello);
        $this->assertEquals($fileB, $sync->setFile($fileB)->getFile());
        $sync->foo = 'bar';
        $this->assertEquals('bar', $sync->foo);
        $this->assertNull($sync->hello);
        $this->assertEquals($fileA, $sync->setFile($fileA)->getFile());
        $this->assertNull($sync->foo);
        $this->assertEquals('world', $sync->hello);
    }

    public function testGetSetData()
    {
        $file = self::DIR . "/test.sync";
        $sync = new Sync($file);

        $data = new \stdClass();
        $data->hello = 'world';
        $data->foo = 'bar';

        $sync->setData($data);

        $this->assertEquals('world', $sync->get('hello'));
        $this->assertEquals('bar', $sync->get('foo'));

        $data = $sync->getData();
        $this->assertEquals('world', $data->hello);
        $this->assertEquals('bar', $data->foo);

        // Of course, pointer's behaviour does not work in this situation
        $data->test = "ok";
        $this->assertFalse($sync->has('test'));
    }

}
