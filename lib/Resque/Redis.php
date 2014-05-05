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

	/**
	 * A default host to connect to
	 */
	const DEFAULT_HOST = 'localhost';

	/**
	 * The default Redis port
	 */
	const DEFAULT_PORT = 6379;

	/**
	 * The default Redis Database number
	 */
	const DEFAULT_DATABASE = 0;

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

	/**
	 * @param string|array $server A DSN or array
	 * @param int $database A database number to select
	 */
    public function __construct($server, $database = null)
	{
		$this->server = $server;
		$this->database = $database;

		if (is_array($this->server)) {
			$this->driver = new Credis_Cluster($server);

		} else {

			list($host, $port, $dsnDatabase, $user, $password, $options) = $this->parseDsn($server);
			// $user is are unused here

			// Look for known Credis_Client options
			$timeout = isset($options['timeout']) ? intval($options['timeout']) : null;
			$persistent = isset($options['persistent']) ? $options['persistent'] : '';

			$this->driver = new Credis_Client($host, $port, $timeout, $persistent);
			if ($password){
				$this->driver->auth($password);
			}

			// If the `$database` constructor argument is not set, use the value from the DSN.
			if (is_null($database)) {
				$database = $dsnDatabase;
			}
		}

		if ($this->database !== null) {
			$this->driver->select($database);
		}
	}

	/**
	 * Parse a DSN string
	 * @param string $dsn
	 * @return array [host, port, db, user, pass, options]
	 */
	public function parseDsn($dsn)
	{
		$validSchemes = array('redis', 'tcp');
		if ($dsn == '') {
			// Use a sensible default for an empty DNS string
			$dsn = 'redis://' . self::DEFAULT_HOST;
		}
		$parts = parse_url($dsn);
		if (isset($parts['scheme']) && ! in_array($parts['scheme'], $validSchemes)) {
			throw new \InvalidArgumentException("Invalid DSN. Supported schemes are " . implode(', ', $validSchemes));
		}

		// Allow simple 'hostname' format, which parse_url treats as a path, not host.
		if ( ! isset($parts['host'])) {
			$parts = array('host' => $parts['path']);
		}

		$port = isset($parts['port']) ? intval($parts['port']) : self::DEFAULT_PORT;

		$database = self::DEFAULT_DATABASE;
		if (isset($parts['path'])) {
			// Strip non-digit chars from path
			$database = intval(preg_replace('/[^0-9]/', '', $parts['path']));
		}

		$options = array();
		if (isset($parts['query'])) {
			// Parse the query string into an array
			parse_str($parts['query'], $options);
		}

		return array(
			$parts['host'],
			$port,
			$database,
			isset($parts['user']) ? $parts['user'] : false,
			isset($parts['pass']) ? $parts['pass'] : false,
			$options,
		);
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