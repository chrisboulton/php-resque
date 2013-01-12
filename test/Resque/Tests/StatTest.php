<?php
/**
 * Resque_Stat tests.
 *
 * @package		Resque/Tests
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class Resque_Tests_StatTest extends Resque_Tests_TestCase
{
	public function testStatCanBeIncremented()
	{
		Resque_Stat::incr('test_incr');
		Resque_Stat::incr('test_incr');
		$this->assertEquals(2, $this->redis->get('resque:stat:test_incr'));
	}

	public function testStatCanBeIncrementedByX()
	{
		Resque_Stat::incr('test_incrX', 10);
		Resque_Stat::incr('test_incrX', 11);
		$this->assertEquals(21, $this->redis->get('resque:stat:test_incrX'));
	}

	public function testStatCanBeDecremented()
	{
		Resque_Stat::incr('test_decr', 22);
		Resque_Stat::decr('test_decr');
		$this->assertEquals(21, $this->redis->get('resque:stat:test_decr'));
	}

	public function testStatCanBeDecrementedByX()
	{
		Resque_Stat::incr('test_decrX', 22);
		Resque_Stat::decr('test_decrX', 11);
		$this->assertEquals(11, $this->redis->get('resque:stat:test_decrX'));
	}

	public function testGetStatByName()
	{
		Resque_Stat::incr('test_get', 100);
		$this->assertEquals(100, Resque_Stat::get('test_get'));
	}

	public function testGetUnknownStatReturns0()
	{
		$this->assertEquals(0, Resque_Stat::get('test_get_unknown'));
	}
}