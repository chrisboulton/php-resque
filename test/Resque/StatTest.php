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
        Stat::incr('test_incr');
        Stat::incr('test_incr');
        $this->assertEquals(2, $this->redis->get('resque:stat:test_incr'));
    }

    public function testStatCanBeIncrementedByX()
    {
        Stat::incr('test_incrX', 10);
        Stat::incr('test_incrX', 11);
        $this->assertEquals(21, $this->redis->get('resque:stat:test_incrX'));
    }

    public function testStatCanBeDecremented()
    {
        Stat::incr('test_decr', 22);
        Stat::decr('test_decr');
        $this->assertEquals(21, $this->redis->get('resque:stat:test_decr'));
    }

    public function testStatCanBeDecrementedByX()
    {
        Stat::incr('test_decrX', 22);
        Stat::decr('test_decrX', 11);
        $this->assertEquals(11, $this->redis->get('resque:stat:test_decrX'));
    }

    public function testGetStatByName()
    {
        Stat::incr('test_get', 100);
        $this->assertEquals(100, Stat::get('test_get'));
    }

    public function testGetUnknownStatReturns0()
    {
        $this->assertEquals(0, Stat::get('test_get_unknown'));
    }
}
