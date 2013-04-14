<?php

namespace Resque;

/**
 * Stat tests.
 *
 * @package		Resque/Tests
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class StatTest extends TestCase
{
    public function testStatCanBeIncremented()
    {
        $this->resque->getStat()->incr('test_incr');
        $this->resque->getStat()->incr('test_incr');
        $this->assertEquals(2, $this->redis->get('resque:stat:test_incr'));
    }

    public function testStatCanBeIncrementedByX()
    {
        $this->resque->getStat()->incr('test_incrX', 10);
        $this->resque->getStat()->incr('test_incrX', 11);
        $this->assertEquals(21, $this->redis->get('resque:stat:test_incrX'));
    }

    public function testStatCanBeDecremented()
    {
        $this->resque->getStat()->incr('test_decr', 22);
        $this->resque->getStat()->decr('test_decr');
        $this->assertEquals(21, $this->redis->get('resque:stat:test_decr'));
    }

    public function testStatCanBeDecrementedByX()
    {
        $this->resque->getStat()->incr('test_decrX', 22);
        $this->resque->getStat()->decr('test_decrX', 11);
        $this->assertEquals(11, $this->redis->get('resque:stat:test_decrX'));
    }

    public function testGetStatByName()
    {
        $this->resque->getStat()->incr('test_get', 100);
        $this->assertEquals(100, $this->resque->getStat()->get('test_get'));
    }

    public function testGetUnknownStatReturns0()
    {
        $this->assertEquals(0, $this->resque->getStat()->get('test_get_unknown'));
    }
}
