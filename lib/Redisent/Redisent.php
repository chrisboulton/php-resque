<?php
/**
 * Redisent, a Redis interface for the modest
 * @author Justin Poliey <jdp34@njit.edu>
 * @copyright 2009 Justin Poliey <jdp34@njit.edu>
 * @license http://www.opensource.org/licenses/mit-license.php The MIT License
 * @package Redisent
 */

define('CRLF', sprintf('%s%s', chr(13), chr(10)));

/**
 * Wraps native Redis errors in friendlier PHP exceptions
 */
class RedisException extends Exception {
}

/**
 * Redisent, a Redis interface for the modest among us
 */
class Redisent {

	/**
	 * Socket connection to the Redis server
	 * @var resource
	 * @access private
	 */
	private $__sock;

	/**
	 * Redis bulk commands, they are sent in a slightly different format to the server
	 * @var array
	 * @access private
	 */
	private $bulk_cmds = array(
		'SET',   'GETSET', 'SETNX', 'ECHO',
		'RPUSH', 'LPUSH',  'LSET',  'LREM',
		'SADD',  'SREM',   'SMOVE', 'SISMEMBER'
	);

	/**
	 * Creates a Redisent connection to the Redis server on host {@link $host} and port {@link $port}.
	 * @param string $host The hostname of the Redis server
	 * @param integer $port The port number of the Redis server
	 */
	function __construct($host, $port = 6379) {
		$this->__sock = fsockopen($host, $port, $errno, $errstr);
		if (!$this->__sock) {
			throw new Exception("{$errno} - {$errstr}");
		}
	}

	function __destruct() {
		fclose($this->__sock);
	}

	function __call($name, $args) {

		/* Build the Redis protocol command */
		$name = strtoupper($name);
		if (in_array($name, $this->bulk_cmds)) {
			$value = array_pop($args);
			$command = sprintf("%s %s %d%s%s%s", $name, trim(implode(' ', $args)), strlen($value), CRLF, $value, CRLF);
		}
		else {
			$command = sprintf("%s %s%s", $name, trim(implode(' ', $args)), CRLF);
		}

		/* Open a Redis connection and execute the command */
		fwrite($this->__sock, $command);

		/* Parse the response based on the reply identifier */
		$reply = trim(fgets($this->__sock, 512));
		switch (substr($reply, 0, 1)) {
			/* Error reply */
			case '-':
				echo $command."\n";
				throw new RedisException(substr(trim($reply), 4));
				break;
			/* Inline reply */
			case '+':
				$response = substr(trim($reply), 1);
				break;
			/* Bulk reply */
			case '$':
				$response = null;
				if ($reply == '$-1') {
					break;
				}
				$read = 0;
				$size = substr($reply, 1);
				do {
					$block_size = ($size - $read) > 1024 ? 1024 : ($size - $read);
					$response .= fread($this->__sock, $block_size);
					$read += $block_size;
				} while ($read < $size);
				fread($this->__sock, 2); /* discard crlf */
				break;
			/* Multi-bulk reply */
			case '*':
				$count = substr($reply, 1);
				if ($count == '-1') {
					return null;
				}
				$response = array();
				for ($i = 0; $i < $count; $i++) {
					$bulk_head = trim(fgets($this->__sock, 512));
					$size = substr($bulk_head, 1);
					if ($size == '-1') {
						$response[] = null;
					}
					else {
						$read = 0;
						$block = "";
						do {
							$block_size = ($size - $read) > 1024 ? 1024 : ($size - $read);
							$block .= fread($this->__sock, $block_size);
							$read += $block_size;
						} while ($read < $size);
						fread($this->__sock, 2); /* discard crlf */
						$response[] = $block;
					}
				}
				break;
			/* Integer reply */
			case ':':
				$response = substr(trim($reply), 1);
				break;
			default:
				throw new RedisException("invalid server response: {$reply}");
				break;
		}
		/* Party on */
		return $response;
	}

}