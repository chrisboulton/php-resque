<?php
/**
 * Wrap Credis to add namespace support and various helper methods.
 *
 * @package		Resque/Redis
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class Resque_Redis
{
    const DEFAULT_HOST = 'localhost';

    const DEFAULT_PORT = 6379;

    const DEFAULT_DATABASE = 0;

    /**
     * Redis namespace
     * @var string
     */
    private static $defaultNamespace = 'resque:';

    /**
     * Credis driver for Redis
     * @var mixed Credis_Client or Credis_Cluster
     */
    private $driver;

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
		'setex',
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
		'blpop',
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
     * Sets up Redis driver, optionally authenticates, and selects database.
     *
	 * @param mixed $server Host/port combination separated by a colon, or
	 *                      a nested array of servers with host/port pairs.
     * @param int $database
     * @param string $password
     */
	public function __construct($server = null, $database = null, $password = null)
	{
		if (empty($server)) {
			$server = self::DEFAULT_HOST . ':' . self::DEFAULT_PORT;
		}
		if (empty($database)) {
			$database = self::DEFAULT_DATABASE;
		}

		if (is_array($server)) {
			$this->driver = new Credis_Cluster($server);
		} else {
            // This "redis://" method is deprecated and left only for
            // backward compatibility
            if (strpos($server, 'redis://') !== false) {
                // Redis format is:
                // redis://[user]:[password]@[host]:[port]
                $firstColonAfterUser =
                    strpos($server, ':', 8); // 8 is length of "redis://"
                $passwordHostPort = substr($server, $firstColonAfterUser + 1);
                $lastAt = strrpos($passwordHostPort, '@');
                $hostPort = substr($passwordHostPort, $lastAt + 1);
                $password = str_replace('@' . $hostPort, '', $passwordHostPort);
                list($host, $port) = explode(':', $hostPort);
            } elseif (strpos($server, ':') !== false) {
				list($host, $port) = explode(':', $server);
            } else {
                $host = $server;
                $port = self::DEFAULT_PORT;
            }
			
			$this->driver = new Credis_Client($host, $port);

            if ($password !== null) {
                $this->driver->auth($password);
            }
		}

        $this->driver->select($database);
	}

	/**
	 * Magic method to handle all function requests and prefix key based
	 * operations with the {self::$defaultNamespace} key prefix.
	 *
	 * @param string $name The name of the method called.
	 * @param array $args Array of supplied arguments to the method.
	 * @return mixed Return value from Resident::call() based on the command.
	 */
    public function __call($name, $args)
    {
        if(in_array($name, $this->keyCommands)) {
            if(is_array($args[0])) {
                foreach($args[0] AS $i => $v) {
                    $args[0][$i] = self::$defaultNamespace . $v;
                }
            } else {
                $args[0] = self::$defaultNamespace . $args[0];
            }
        }
        try {
            return $this->driver->__call($name, $args);
        }
        catch(CredisException $e) {
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
