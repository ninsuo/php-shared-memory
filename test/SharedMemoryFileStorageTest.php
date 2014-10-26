<?php

use Fuz\Component\SharedMemory\SharedMemory;
use Fuz\Component\SharedMemory\Storage\StorageFile;

require(__DIR__ . "/../src/SharedMemory.php");
require(__DIR__ . "/../src/Entity/StoredEntity.php");
require(__DIR__ . "/../src/Storage/StorageInterface.php");
require(__DIR__ . "/../src/Storage/StorageFile.php");

die("Please read warning inside that file and comment this die() before running tests.\n");

/**
 * Test Case for SharedMemory used with StorageFile driver
 *
 * Warning:
 * This test case creates a directory in /tmp, check that
 * permissions are granted and the 'sync' directory not
 * already in use.
 *
 * Requires PHPUnit (developped using 3.6.12)
 */
class SharedMemoryStorageFileTest extends \PHPUnit_Framework_TestCase
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
        $storage = new StorageFile($file);
        $shared = new SharedMemory($storage);
        $this->assertNull($shared->test);
        $this->assertFalse(is_file($file));
    }

    public function testGetFileExistsButNotReadable()
    {
        $file = self::DIR . "/test.sync";

        $fd = fopen($file, 'w');
        fclose($fd);
        chmod($file, 0000);

        $storage = new StorageFile($file);
        $shared = new SharedMemory($storage);
        try
        {
            $test = $shared->test;
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
        $storage = new StorageFile($file);
        $shared = new SharedMemory($storage);
        $this->assertNull($shared->not_found);
        $this->assertNull($shared->{0});
        $this->assertNull($shared->{'重庆'});
    }

    public function testGetSetSmallValue()
    {
        $file = self::DIR . "/test.sync";
        $storage = new StorageFile($file);
        $shared = new SharedMemory($storage);
        $shared->data = "test a";
        $this->assertEquals("test a", $shared->data);

        $shared->{0} = "test b";
        $this->assertEquals("test b", $shared->{0});

        $shared->{'重庆'} = "test e";
        $this->assertEquals("test e", $shared->{'重庆'});

        $test = $shared->hello = 'world';
        $this->assertEquals('world', $test);
    }

    public function testGetSetBigValue()
    {
        $value = str_repeat("abcdefghijklmnopqrstuvwxyz", 64 * 1024 + 1);

        $file = self::DIR . "/test.sync";
        $storage = new StorageFile($file);
        $shared = new SharedMemory($storage);
        $shared->data = $value;
        $this->assertEquals($value, $shared->data);

        $shared->{0} = $value;
        $this->assertEquals($value, $shared->{0});

        $shared->{'重庆'} = $value;
        $this->assertEquals($value, $shared->{'重庆'});

        $test = $shared->hello = $value;
        $this->assertEquals($value, $test);
    }

    public function testSetDirectoryDoesNotExists()
    {
        $dir = self::DIR . "/xxx";
        $file = $dir . "/test.sync";

        $storage = new StorageFile($file);
        $shared = new SharedMemory($storage);
        try
        {
            $shared->test = "fail";
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

        $storage = new StorageFile($file);
        $shared = new SharedMemory($storage);
        try
        {
            $shared->test = "fail";
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

        $storage = new StorageFile($file);
        $shared = new SharedMemory($storage);
        try
        {
            $shared->test = "fail";
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
        $storage = new StorageFile($file);
        $shared = new SharedMemory($storage);
        $this->assertFalse(file_exists($file));
        $shared->hello = "world";
        $this->assertTrue(file_exists($file));
        $this->assertEquals("world", $shared->hello);
    }

    public function testSetFileAlreadyExisting()
    {
        $file = self::DIR . "/test.sync";
        $storage = new StorageFile($file);
        $shared = new SharedMemory($storage);
        $this->assertFalse(file_exists($file));
        $shared->hello = "world";
        $this->assertTrue(file_exists($file));
        $this->assertEquals("world", $shared->hello);
        $shared->hello = "foo";
        $this->assertEquals("foo", $shared->hello);
    }

    public function testIssetNotSetFileDoesNotExists()
    {
        $file = self::DIR . "/test.sync";
        $storage = new StorageFile($file);
        $shared = new SharedMemory($storage);
        $shared->hello = "world";
        unlink($file);
        $this->assertFalse(isset($shared->hello));
    }

    public function testIssetNotSetPropertyDoesNotExists()
    {
        $file = self::DIR . "/test.sync";
        $storage = new StorageFile($file);
        $shared = new SharedMemory($storage);
        $shared->hello = "world";
        $this->assertFalse(isset($shared->foo));
    }

    public function testIssetNotSetPropertyIsNull()
    {
        $file = self::DIR . "/test.sync";
        $storage = new StorageFile($file);
        $shared = new SharedMemory($storage);
        $shared->hello = null;
        $this->assertFalse(isset($shared->hello));
    }

    public function testIssetPropertyExists()
    {
        $file = self::DIR . "/test.sync";
        $storage = new StorageFile($file);
        $shared = new SharedMemory($storage);
        $shared->hello = 0;
        $this->assertTrue(isset($shared->hello));
        $shared->hello = '';
        $this->assertTrue(isset($shared->hello));
        $shared->hello = false;
        $this->assertTrue(isset($shared->hello));
        $shared->hello = array ();
        $this->assertTrue(isset($shared->hello));
        $shared->hello = new \stdClass();
        $this->assertTrue(isset($shared->hello));
        $shared->hello = 42;
        $this->assertTrue(isset($shared->hello));
    }

    public function testUnsetNoFile()
    {
        $file = self::DIR . "/test.sync";
        $storage = new StorageFile($file);
        $shared = new SharedMemory($storage);
        $shared->hello = "world";
        unlink($file);
        unset($shared->hello);
    }

    public function testUnsetPropertyNeverSet()
    {
        $file = self::DIR . "/test.sync";
        $storage = new StorageFile($file);
        $shared = new SharedMemory($storage);
        $shared->hello = "world";
        unset($shared->test);
    }

    public function testUnsetPropertyExists()
    {
        $file = self::DIR . "/test.sync";
        $storage = new StorageFile($file);
        $shared = new SharedMemory($storage);
        $shared->hello = "world";
        $shared->foo = "bar";
        unset($shared->hello);
        $this->assertNull($shared->hello);
        $this->assertEquals("bar", $shared->foo);
    }

    public function testCRUD()
    {
        $file = self::DIR . "/test.sync";
        $storage = new StorageFile($file);
        $shared = new SharedMemory($storage);
        $this->assertNull($shared->get('hello'));
        $this->assertNull($shared->get('foo'));

        $this->assertFalse($shared->has('hello'));
        $this->assertFalse($shared->has('foo'));

        $this->assertEquals('world', $shared->set('hello', 'world'));
        $this->assertEquals('bar', $shared->set('foo', 'bar'));

        $this->assertEquals('world', $shared->get('hello'));
        $this->assertEquals('bar', $shared->get('foo'));

        $this->assertTrue($shared->has('hello'));
        $this->assertTrue($shared->has('foo'));

        $shared->remove('foo');

        $this->assertNull($shared->get('foo'));
        $this->assertFalse($shared->has('foo'));
    }

    public function testObjectCRUD()
    {
        $file = self::DIR . "/test.sync";
        $storage = new StorageFile($file);
        $shared = new SharedMemory($storage);
        $this->assertNull($shared->hello);
        $this->assertNull($shared->foo);

        $this->assertFalse(isset($shared->hello));
        $this->assertFalse(isset($shared->foo));

        $this->assertEquals('world', ($shared->hello = 'world'));
        $this->assertEquals('bar', ($shared->foo = 'bar'));

        $this->assertEquals('world', $shared->hello);
        $this->assertEquals('bar', $shared->foo);

        $this->assertTrue(isset($shared->hello));
        $this->assertTrue(isset($shared->foo));

        unset($shared->foo);

        $this->assertNull($shared->foo);
        $this->assertFalse(isset($shared->foo));
    }

    public function testArrayCRUD()
    {
        $file = self::DIR . "/test.sync";
        $storage = new StorageFile($file);
        $shared = new SharedMemory($storage);
        $file = self::DIR . "/test.sync";
        $storage = new StorageFile($file);
        $shared = new SharedMemory($storage);
        $this->assertNull($shared['hello']);
        $this->assertNull($shared['foo']);

        $this->assertFalse(isset($shared['hello']));
        $this->assertFalse(isset($shared['foo']));

        $this->assertEquals('world', ($shared['hello'] = 'world'));
        $this->assertEquals('bar', ($shared['foo'] = 'bar'));

        $this->assertEquals('world', $shared['hello']);
        $this->assertEquals('bar', $shared['foo']);

        $this->assertTrue(isset($shared['hello']));
        $this->assertTrue(isset($shared['foo']));

        unset($shared['foo']);

        $this->assertNull($shared['foo']);
        $this->assertFalse(isset($shared['foo']));
    }

    public function testLockTimeoutNotNumeric()
    {
        $file = self::DIR . "/test.sync";
        $storage = new StorageFile($file);
        $shared = new SharedMemory($storage);
        try
        {
            $shared->lock(array ());
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
        $storage = new StorageFile($file);
        $shared = new SharedMemory($storage);
        try
        {
            $shared->lock(-42);
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
        $storage = new StorageFile($file);
        $shared = new SharedMemory($storage);
        try
        {
            $shared->lock(0, array ());
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
        $storage = new StorageFile($file);
        $shared = new SharedMemory($storage);
        try
        {
            $shared->lock(4999, array ());
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
        $storage = new StorageFile($file);

        $sharedA = new SharedMemory($storage);
        $sharedA->hello = 'world';
        $sharedA->lock(1);

        $sharedB = new SharedMemory($storage);
        try
        {
            $sharedB->hello;
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
        $storage = new StorageFile($file);

        $sharedA = new SharedMemory($storage);
        $sharedA->hello = 'world';
        $sharedA->lock(1);
        $sharedA->foo = 'bar';
        $sharedA->unlock();
        $sharedB = new SharedMemory($storage);
        try
        {
            $test = $sharedB->hello;
        }
        catch (\Exception $e)
        {
            $this->fail(sprintf("Unexpected exception has been raised: %s.", $e->getMessage()));
            return;
        }
        $this->assertEquals('world', $test);
    }

    public function testGetSetStorage()
    {
        $fileA = self::DIR . "/testA.sync";
        $fileB = self::DIR . "/testB.sync";
        $storageA = new StorageFile($fileA);
        $storageB = new StorageFile($fileB);
        $shared = new SharedMemory($storageA);
        $this->assertEquals($storageA, $shared->getStorage());
        $shared->hello = 'world';
        $this->assertEquals('world', $shared->hello);
        $this->assertEquals($storageB, $shared->setStorage($storageB)->getStorage());
        $shared->foo = 'bar';
        $this->assertEquals('bar', $shared->foo);
        $this->assertNull($shared->hello);
        $this->assertEquals($storageA, $shared->setStorage($storageA)->getStorage());
        $this->assertNull($shared->foo);
        $this->assertEquals('world', $shared->hello);
    }

    public function testGetSetData()
    {
        $file = self::DIR . "/test.sync";
        $storage = new StorageFile($file);
        $shared = new SharedMemory($storage);
        $data = new \stdClass();
        $data->hello = 'world';
        $data->foo = 'bar';

        $shared->setData($data);

        $this->assertEquals('world', $shared->get('hello'));
        $this->assertEquals('bar', $shared->get('foo'));

        $data = $shared->getData();
        $this->assertEquals('world', $data->hello);
        $this->assertEquals('bar', $data->foo);

        $data->test = "ok";
        $this->assertFalse($shared->has('test'));
    }

}
