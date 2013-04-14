<?php

namespace Resque\Backend;

use Credis_Client;
use Credis_Cluster;

class RedisBackend implements BackendInterface
{
    /**
     * Redis namespace
     * @var string
     */
    public $defaultNamespace = 'resque:';

    /**
     * @var array List of all commands in Redis that supply a key as their
     *  first argument. Used to prefix keys with the Resque namespace.
     */
    public $keyCommands = array(
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

    /**
     * Set Redis namespace (prefix) default: resque
     * @param string $namespace
     */
    public function setPrefix($namespace)
    {
        if (strpos($namespace, ':') === false) {
            $namespace .= ':';
        }
        $this->defaultNamespace = $namespace;
    }

    public function __construct(array $config)
    {
        $server = (! empty($config['server'])) ? $config['server'] : 'localhost:6379';

        if (is_array($server)) {
            $this->driver = new Credis_Cluster($server);
        } else {
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
            } elseif (strpos($server, 'redis://') !== false) {
                // Redis format is:
                // redis://[user]:[password]@[host]:[port]
                list($userpwd,$hostport) = explode('@', $server);
                $userpwd = substr($userpwd, strpos($userpwd, 'redis://')+8);
                list($host, $port) = explode(':', $hostport);
                list(,$password) = explode(':', $userpwd);
            }

            $this->driver = new Credis_Client($host, $port);
            if (isset($password)) {
                $this->driver->auth($password);
            }
        }

        if (! empty($config['database'])) {
            $this->driver->select($config['database']);
        }
    }

    /**
     * Magic method to handle all function requests and prefix key based
     * operations with the {$this->defaultNamespace} key prefix.
     *
     * @param  string $name The name of the method called.
     * @param  array  $args Array of supplied arguments to the method.
     * @return mixed  Return value from Credis_Client::call() based on the command.
     */
    public function __call($name, $args)
    {
        if (in_array($name, $this->keyCommands)) {
            $args[0] = $this->defaultNamespace . $args[0];
        }
        try {
            return $this->driver->__call($name, $args);
        } catch (\CredisException $e) {
            return false;
        }
    }

    public function getPrefix()
    {
        return $this->defaultNamespace;
    }

    public function removePrefix($string)
    {
        $prefix = $this->getPrefix();

        if (substr($string, 0, strlen($prefix)) == $prefix) {
            $string = substr($string, strlen($prefix), strlen($string) );
        }

        return $string;
    }
}
