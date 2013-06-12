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
    /**
     * Redis namespace
     * @var string
     */
    private static $defaultNamespace = 'resque:';

    private $server;
    private $database;

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

	public function __construct($server, $database = null)
	{
		$this->server = $server;
		$this->database = $database;

		if (is_array($this->server)) {
			$this->driver = new Credis_Cluster($server);
		}
		else {
			$port = null;
			$password = null;
			$host = $server;

			// If not a UNIX socket path or tcp:// formatted connections string
			// assume host:port combination.
			if (strpos($server, '/') === false) {
				$parts = explode(':', $server);
				if (isset($parts[1])) {
					$port = $parts[1];
				}
				$host = $parts[0];
			}else if (strpos($server, 'redis://') !== false){
				// Redis format is:
				// redis://[user]:[password]@[host]:[port]
				list($userpwd,$hostport) = explode('@', $server);
				$userpwd = substr($userpwd, strpos($userpwd, 'redis://')+8);
				list($host, $port) = explode(':', $hostport);
				list($user, $password) = explode(':', $userpwd);
			}
			
			$this->driver = new Credis_Client($host, $port);
			if (isset($password)){
				$this->driver->auth($password);
			}
		}

		if ($this->database !== null) {
			$this->driver->select($database);
		}
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