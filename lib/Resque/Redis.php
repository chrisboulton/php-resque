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
		'exists' => 'exists',
		'del' => 'del',
		'type' => 'type',
		'keys' => 'keys',
		'expire' => 'expire',
		'ttl' => 'ttl',
		'move' => 'move',
		'set' => 'set',
		'get' => 'get',
		'getset' => 'getset',
		'setnx' => 'setnx',
		'incr' => 'incr',
		'incrby' => 'incrby',
		'decr' => 'decr',
		'decrby' => 'decrby',
		'rpush' => 'rpush',
		'lpush' => 'lpush',
		'llen' => 'llen',
		'lrange' => 'lrange',
		'ltrim' => 'ltrim',
		'lindex' => 'lindex',
		'lset' => 'lset',
		'lrem' => 'lrem',
		'lpop' => 'lpop',
		'rpop' => 'rpop',
		'sadd' => 'sadd',
		'srem' => 'srem',
		'spop' => 'spop',
		'scard' => 'scard',
		'sismember' => 'sismember',
		'smembers' => 'smembers',
		'srandmember' => 'srandmember',
		'zadd' => 'zadd',
		'zrem' => 'zrem',
		'zrange' => 'zrange',
		'zrevrange' => 'zrevrange',
		'zrangebyscore' => 'zrangebyscore',
		'zcard' => 'zcard',
		'zscore' => 'zscore',
		'zremrangebyscore' => 'zremrangebyscore',
		'sort' => 'sort'
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
		if(isset($this->keyCommands[$name])) {
		    $args[1][0] = self::$defaultNamespace . $args[1][0];
		}
		try {
			return parent::__call($name, $args[1]);
		}
		catch(RedisException $e) {
			return false;
		}
	}

    public static function getPrefix()
    {
        return self::$defaultNamespace;
    }

    public static function removePrefix($string)
    {
        $prefix=self::getPrefix();

        if (substr($string, 0, strlen($prefix)) == $prefix) {
            $string = substr($string, strlen($prefix), strlen($string) );
        }
        return $string;
    }
}
?>