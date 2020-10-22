<?php

/**
 * @see       https://github.com/laminas/laminas-session for the canonical source repository
 * @copyright https://github.com/laminas/laminas-session/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-session/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\Session;

use Laminas\Session\Container;
use Laminas\Session\SessionManager;
use Laminas\Session\Storage\SessionArrayStorage;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Laminas\Session\Storage\SessionArrayStorage
 */
class SessionArrayStorageTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION      = [];
        $this->storage = new SessionArrayStorage();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testStorageWritesToSessionSuperglobal()
    {
        $this->storage['foo'] = 'bar';
        self::assertSame($_SESSION['foo'], $this->storage->foo);
        unset($this->storage['foo']);
        self::assertArrayNotHasKey('foo', $_SESSION);
    }

    public function testPassingArrayToConstructorOverwritesSessionSuperglobal()
    {
        $_SESSION['foo'] = 'bar';
        $array           = ['foo' => 'FOO'];
        $storage         = new SessionArrayStorage($array);
        $expected        = [
            'foo'       => 'FOO',
            '__Laminas' => [
                '_REQUEST_ACCESS_TIME' => $storage->getRequestAccessTime(),
            ],
        ];
        self::assertSame($expected, $_SESSION);
    }

    public function testModifyingSessionSuperglobalDirectlyUpdatesStorage()
    {
        $_SESSION['foo'] = 'bar';
        self::assertTrue(isset($this->storage['foo']));
    }

    public function testDestructorSetsSessionToArray()
    {
        $this->storage->foo = 'bar';
        $expected = [
            '__Laminas' => [
                '_REQUEST_ACCESS_TIME' => $this->storage->getRequestAccessTime(),
            ],
            'foo' => 'bar',
        ];
        $this->storage->__destruct();
        self::assertSame($expected, $_SESSION);
    }

    public function testModifyingOneSessionObjectModifiesTheOther()
    {
        $this->storage->foo = 'bar';
        $storage            = new SessionArrayStorage();
        $storage->bar       = 'foo';
        self::assertEquals('foo', $this->storage->bar);
    }

    public function testMarkingOneSessionObjectImmutableShouldMarkOtherInstancesImmutable()
    {
        $this->storage->foo = 'bar';
        $storage            = new SessionArrayStorage();
        self::assertEquals('bar', $storage['foo']);
        $this->storage->markImmutable();
        self::assertTrue($storage->isImmutable(), var_export($_SESSION, 1));
    }

    public function testAssignment()
    {
        $_SESSION['foo'] = 'bar';
        self::assertEquals('bar', $this->storage['foo']);
    }

    public function testMultiDimensionalAssignment()
    {
        $_SESSION['foo']['bar'] = 'baz';
        self::assertEquals('baz', $this->storage['foo']['bar']);
    }

    public function testUnset()
    {
        $_SESSION['foo'] = 'bar';
        unset($_SESSION['foo']);
        self::assertFalse(isset($this->storage['foo']));
    }

    public function testMultiDimensionalUnset()
    {
        $this->storage['foo'] = ['bar' => ['baz' => 'boo']];
        unset($this->storage['foo']['bar']['baz']);
        self::assertFalse(isset($this->storage['foo']['bar']['baz']));
        unset($this->storage['foo']['bar']);
        self::assertFalse(isset($this->storage['foo']['bar']));
    }

    public function testSessionWorksWithContainer()
    {
        // Run without any validators; session ID is often invalid in CLI
        $container = new Container(
            'test',
            new SessionManager(null, null, null, [], ['attach_default_validators' => false])
        );
        $container->foo = 'bar';

        self::assertSame($container->foo, $_SESSION['test']['foo']);
    }

    public function testToArrayWithMetaData()
    {
        $this->storage->foo = 'bar';
        $this->storage->bar = 'baz';
        $this->storage->setMetadata('foo', 'bar');
        $expected = [
            '__Laminas' => [
                '_REQUEST_ACCESS_TIME' => $this->storage->getRequestAccessTime(),
                'foo'                  => 'bar',
            ],
            'foo'       => 'bar',
            'bar'       => 'baz',
        ];
        self::assertSame($expected, $this->storage->toArray(true));
    }

    public function testUndefinedSessionManipulation()
    {
        $this->storage['foo'] = 'bar';
        $this->storage['bar'][] = 'bar';
        $this->storage['baz']['foo'] = 'bar';

        $expected = [
            '__Laminas' => [
                '_REQUEST_ACCESS_TIME' => $this->storage->getRequestAccessTime(),
            ],
            'foo'       => 'bar',
            'bar'       => ['bar'],
            'baz'       => ['foo' => 'bar'],
        ];
        self::assertSame($expected, $this->storage->toArray(true));
    }

    /**
     * @runInSeparateProcess
     */
    public function testExpirationHops()
    {
        // since we cannot explicitly test reinitializing the session
        // we will act in how session manager would in this case.
        $storage = new SessionArrayStorage();
        $manager = new SessionManager(null, $storage);
        $manager->start();

        $container = new Container('test');
        $container->foo = 'bar';
        $container->setExpirationHops(1);

        $copy = $_SESSION;
        $_SESSION = null;
        $storage->init($copy);
        self::assertEquals('bar', $container->foo);

        $copy = $_SESSION;
        $_SESSION = null;
        $storage->init($copy);
        self::assertNull($container->foo);
    }

    /**
     * @runInSeparateProcess
     */
    public function testPreserveRequestAccessTimeAfterStart()
    {
        $manager = new SessionManager(null, $this->storage);
        self::assertGreaterThan(0, $this->storage->getRequestAccessTime());
        $manager->start();
        self::assertGreaterThan(0, $this->storage->getRequestAccessTime());
    }

    public function testGetArrayCopyFromContainer()
    {
        $container      = new Container('test');
        $container->foo = 'bar';
        $container->baz = 'qux';
        self::assertSame(['foo' => 'bar', 'baz' => 'qux'], $container->getArrayCopy());
    }
}
