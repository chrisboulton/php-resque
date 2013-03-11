<?php
/**
 * Resque test case class. Contains setup and teardown methods.
 *
 * @package		Resque/Tests
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class Resque_Tests_TestCase extends PHPUnit_Framework_TestCase
{
	protected $resque;
	protected $redis;

	public function setUp()
	{
		$config = file_get_contents(REDIS_CONF);
		preg_match('#^\s*port\s+([0-9]+)#m', $config, $matches);
		$this->redis = new Credis_Client('localhost', $matches[1]);

		// Flush redis
		$this->redis->flushAll();
	}
}