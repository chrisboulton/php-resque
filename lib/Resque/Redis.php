<?php
// Third- party apps may have already loaded Resident from elsewhere
// so lets be careful.
if(!class_exists('Redisent', false)) {
	require_once dirname(__FILE__) . '/../Redisent/Redisent.php';
}

/**
 * Extended Redisent class used by Resque for all communication with
 * redis. Essentially adds namespace support to Redisent.
 *
 * @package		Resque/Redis
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class Resque_Redis extends Redisent
{
    /**
     * Redis namespace
     * @var string
     */
    private static $defaultNamespace = 'resque:';
	/**
	 * @var array List of all commands in Redis that supply a key as their
	 *	first argument. Used to prefix keys with the Resque namespace.
	 */
	private $keyCommands = array(
		'exists',
		'del',
		'type',
		'keys',
		'expire',
		'ttl',
		'move',
		'set',
		'get',
		'getset',
		'setnx',
		'incr',
		'incrby',
		'decr',
		'decrby',
		'rpush',
		'lpush',
		'llen',
		'lrange',
		'ltrim',
		'lindex',
		'lset',
		'lrem',
		'lpop',
		'rpop',
		'sadd',
		'srem',
		'spop',
		'scard',
		'sismember',
		'smembers',
		'srandmember',
		'zadd',
		'zrem',
		'zrange',
		'zrevrange',
		'zrangebyscore',
		'zcard',
		'zscore',
		'zremrangebyscore',
		'sort'
	);
	// sinterstore
	// sunion
	// sunionstore
	// sdiff
	// sdiffstore
	// sinter
	// smove
	// rename
	// rpoplpush
	// mget
	// msetnx
	// mset
	// renamenx
	
	/**
	 * Set Redis namespace (prefix) default: resque
	 * @param string $namespace
	 */
	public static function prefix($namespace)
	{
	    if (strpos($namespace, ':') === false) {
	        $namespace .= ':';
	    }
	    self::$defaultNamespace = $namespace;
	}
	
	/**
	 * Magic method to handle all function requests and prefix key based
	 * operations with the {self::$defaultNamespace} key prefix.
	 *
	 * @param string $name The name of the method called.
	 * @param array $args Array of supplied arguments to the method.
	 * @return mixed Return value from Resident::call() based on the command.
	 */
	public function __call($name, $args) {
		$args = func_get_args();
		if(in_array($name, $this->keyCommands)) {
		    $args[1][0] = self::$defaultNamespace . $args[1][0];
		}
		try {
			return parent::__call($name, $args[1]);
		}
		catch(RedisException $e) {
			return false;
		}
	}
}
?>