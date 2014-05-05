<?php
/**
 * Resque_Redis DSN tests.
 *
 * @package		Resque/Tests
 * @author		Iskandar Najmuddin <github@iskandar.co.uk>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class Resque_Tests_DsnTest extends Resque_Tests_TestCase
{

	/**
	 * These DNS strings are considered valid.
	 *
	 * @return array
	 */
	public function validDsnStringProvider()
	{
		return array(
			// Input , Expected output
			array('', array(
				'localhost',
				Resque_Redis::DEFAULT_PORT,
				Resque_Redis::DEFAULT_DATABASE,
				false, false,
				array(),
			)),
			array('localhost', array(
				'localhost',
				Resque_Redis::DEFAULT_PORT,
				Resque_Redis::DEFAULT_DATABASE,
				false, false,
				array(),
			)),
			array('localhost:1234', array(
				'localhost',
				1234,
				Resque_Redis::DEFAULT_DATABASE,
				false, false,
				array(),
			)),
			array('localhost:1234/2', array(
				'localhost',
				1234,
				2,
				false, false,
				array(),
			)),
			array('redis://foobar', array(
				'foobar',
				Resque_Redis::DEFAULT_PORT,
				Resque_Redis::DEFAULT_DATABASE,
				false, false,
				array(),
			)),
			array('redis://foobar:1234', array(
				'foobar',
				1234,
				Resque_Redis::DEFAULT_DATABASE,
				false, false,
				array(),
			)),
			array('redis://foobar:1234/15', array(
				'foobar',
				1234,
				15,
				false, false,
				array(),
			)),
			array('redis://user@foobar:1234', array(
				'foobar',
				1234,
				Resque_Redis::DEFAULT_DATABASE,
				'user', false,
				array(),
			)),
			array('redis://user@foobar:1234/15', array(
				'foobar',
				1234,
				15,
				'user', false,
				array(),
			)),
			array('redis://user:pass@foobar:1234', array(
				'foobar',
				1234,
				Resque_Redis::DEFAULT_DATABASE,
				'user', 'pass',
				array(),
			)),
			array('redis://user:pass@foobar:1234?x=y&a=b', array(
				'foobar',
				1234,
				Resque_Redis::DEFAULT_DATABASE,
				'user', 'pass',
				array('x' => 'y', 'a' => 'b'),
			)),
			array('redis://:pass@foobar:1234?x=y&a=b', array(
				'foobar',
				1234,
				Resque_Redis::DEFAULT_DATABASE,
				false, 'pass',
				array('x' => 'y', 'a' => 'b'),
			)),
			array('redis://user@foobar:1234?x=y&a=b', array(
				'foobar',
				1234,
				Resque_Redis::DEFAULT_DATABASE,
				'user', false,
				array('x' => 'y', 'a' => 'b'),
			)),
			array('redis://foobar:1234?x=y&a=b', array(
				'foobar',
				1234,
				Resque_Redis::DEFAULT_DATABASE,
				false, false,
				array('x' => 'y', 'a' => 'b'),
			)),
			array('redis://user@foobar:1234/12?x=y&a=b', array(
				'foobar',
				1234,
				12,
				'user', false,
				array('x' => 'y', 'a' => 'b'),
			)),
			array('tcp://user@foobar:1234/12?x=y&a=b', array(
				'foobar',
				1234,
				12,
				'user', false,
				array('x' => 'y', 'a' => 'b'),
			)),
		);
	}

	/**
	 * These DSN values should throw exceptions
	 * @return array
	 */
	public function bogusDsnStringProvider()
	{
		return array(
			'http://foo.bar/',
			'://foo.bar/',
			'user:@foobar:1234?x=y&a=b',
			'foobar:1234?x=y&a=b',
		);
	}

	/**
	 * @dataProvider validDsnStringProvider
	 */
	public function testParsingValidDsnString($dsn, $expected)
	{
		$resqueRedis = new Resque_Redis('localhost');
		$result = $resqueRedis->parseDsn($dsn);
		$this->assertEquals($expected, $result);
	}

	/**
	 * @dataProvider bogusDsnStringProvider
	 * @expectedException InvalidArgumentException
	 */
	public function testParsingBogusDsnStringThrowsException($dsn)
	{
		$resqueRedis = new Resque_Redis('localhost');
		// The next line should throw an InvalidArgumentException
		$result = $resqueRedis->parseDsn($dsn);
	}

}
