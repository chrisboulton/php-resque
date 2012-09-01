<?php
include_once dirname(__FILE__) . '/Resque/Event.php';
include_once dirname(__FILE__) . '/Resque/Job/Status.php';
include_once dirname(__FILE__) . '/Resque/Exception.php';

/**
 * Base Resque_Queue class.
 *
 * @package		Resque
 * @author		Salimane Adjao Moustapha <me@salimane.com>
 * @copyright	(c) 2012 Salimane Adjao Moustapha
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class Resque_Queue
{
	const VERSION = '1.0';

	/**
	 * @var Resque_Redis Instance of Resque_Redis that talks to redis.
	 */
	public static $redis = null;

	/**
	 * @var mixed Host/port conbination separated by a colon, or a nested
	 * array of server swith host/port pairs
	 */
	protected static $redisServer = null;

	/**
	 * @var int ID of Redis database to select.
	 */
	protected static $redisDatabase = 0;

	/**
	 * @var bool use phpredis extension or fsockopen to connect to the redis server
	 */
	public static $phpredis = null;

	/**
	 * Given a host/port combination separated by a colon, set it as
	 * the redis server that Resque will talk to.
	 *
	 * @param mixed $server Host/port combination separated by a colon, or
	 * a nested array of servers with host/port pairs.
	 * @param integer $database the db to be selected
	 * @param bool $phpredis use phpredis extension or fsockopen to connect to the server
	 */
  public static function setBackend($server, $database = 0, $phpredis = true)
  {
    self::$redisServer   = $server;
    self::$redisDatabase = $database;
    self::$phpredis      = $phpredis;
    self::$redis         = null;
    return self::redis();
  }


	/**
	 * Return an instance of the Resque_Redis class instantiated for Resque.
	 *
	 * @return Resque_Redis Instance of Resque_Redis.
	 */
	public static function redis()
	{
	  if(!is_null(self::$redis)) {
	    return self::$redis;
	  }

	  $server = self::$redisServer;
	  if (empty($server)) {
	    $server = 'localhost:6379';
	  }

	  if(is_array($server)) {
	    include_once dirname(__FILE__) . '/Resque/RedisCluster.php';
	    self::$redis = new Resque_RedisCluster($server, self::$redisDatabase, self::phpredis);
	  }
	  else {
      if (strpos($server, 'unix:') === false) {
        list($host, $port) = explode(':', $server);
      }
      else {
        $host = $server;
        $port = null;
      }
	    include_once dirname(__FILE__) . '/Resque/Redis.php';
	    self::$redis = new Resque_Redis($host, $port, self::$redisDatabase, self::$phpredis);
	  }

		return self::$redis;
	}

	/**
	 * Push a job to the end of a specific queue. If the queue does not
	 * exist, then create it as well.
	 *
	 * @param string $queue The name of the queue to add the job to.
	 * @param array $item Job description as an array to be JSON encoded.
	 */
	public static function push($queue, $item)
	{
		self::redis()->sadd('queues', $queue);
		return (int)self::redis()->rpush('queue:' . $queue, json_encode($item));
	}

	/**
	 * Create a new job and save it to the specified queue.
	 *
	 * @param string $queue The name of the queue to place the job in.
	 * @param string $class The name of the class that contains the code to execute the job.
	 * @param array $args Any optional arguments that should be passed when the job is executed.
	 * @param boolean $trackStatus Set to true to be able to monitor the status of a job.
	 *
	 * @return string
	 */
	public static function enqueue($queue, $class, $args = null, $trackStatus = false)
	{
		if($args !== null && !is_array($args)) {
			throw new InvalidArgumentException(
					'Supplied $args must be an array.'
			);
		}

		$id = md5(uniqid('', true));
		self::redis();
		self::$redis->sadd('queues', $queue);
		$result = (int)self::$redis->rpush('queue:' . $queue, json_encode(array(
				'class'	=> $class,
				'args'	=> array($args),
				'id'	=> $id,
		)));

		if ($result) {
			if($trackStatus) {
				Resque_Job_Status::create($id);
			}

			Resque_Event::trigger('afterEnqueue', array(
					'class' => $class,
					'args'  => $args,
					'queue' => $queue,
			));

			return $id;
		}

		return false;
	}
}
