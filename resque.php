<?php
	$QUEUE = getenv('QUEUE');
	if (empty($QUEUE))
	{
		die("Set QUEUE env var containing the list of queues to work.\n");
	}

	if (!defined('DS'))
	{
		define('DS', DIRECTORY_SEPARATOR);
	}

	require_once 'vendor'.DS.'autoload.php';

	$logger = null;
	if (getenv('LOGHANDLERTARGET') !== false && getenv('LOGHANDLER') !== false)
	{
		$logHandlerTarget = getenv('LOGHANDLERTARGET');
		$logHandlerClassName = 'Monolog\Handler\\' . getenv('LOGHANDLER') . 'Handler';

		if (class_exists('Monolog\Logger') && class_exists($logHandlerClassName))
		{
			$logger = new Monolog\Logger('main');
			$logger->pushHandler(new $logHandlerClassName($logHandlerTarget));
		}
	}

	$REDIS_BACKEND = getenv('REDIS_BACKEND');
	if (!empty($REDIS_BACKEND))
	{
		Resque::setBackend($REDIS_BACKEND);
	}

	$logLevel = 0;
	$LOGGING = getenv('LOGGING');
	$VERBOSE = getenv('VERBOSE');
	$VVERBOSE = getenv('VVERBOSE');
	if (!empty($LOGGING) || !empty($VERBOSE))
	{
		$logLevel = Resque_Worker::LOG_NORMAL;
	}
	else if (!empty($VVERBOSE))
	{
		$logLevel = Resque_Worker::LOG_VERBOSE;
	}

	$APP_INCLUDE = getenv('APP_INCLUDE');
	if ($APP_INCLUDE)
	{
		if (!file_exists($APP_INCLUDE))
		{
			die('APP_INCLUDE (' . $APP_INCLUDE . ") does not exist.\n");
		}

		require_once $APP_INCLUDE;
	}

	$interval = 5;
	$INTERVAL = getenv('INTERVAL');
	if (!empty($INTERVAL))
	{
		$interval = $INTERVAL;
	}

	$count = 1;
	$COUNT = getenv('COUNT');
	if (!empty($COUNT) && $COUNT > 1)
	{
		$count = $COUNT;
	}

	if ($count > 1)
	{
		for ($i = 0; $i < $count; ++$i)
		{
			$pid = pcntl_fork();
			if ($pid == -1)
			{
				die("Could not fork worker " . $i . "\n");
			}
			// Child, start the worker
			else if (!$pid)
			{
				$queues = explode(',', $QUEUE);
				$worker = new Resque_Worker($queues);
				$worker->registerLogger($logger);
				$worker->logLevel = $logLevel;
				logStart($logger, array('message' => '*** Starting worker ' . $worker, 'data' => array('type' => 'start', 'worker' => (string) $worker)));
				$worker->work($interval);
				break;
			}
		}
	}
	// Start a single worker
	else
	{
		$queues = explode(',', $QUEUE);
		$worker = new Resque_Worker($queues);
		$worker->registerLogger($logger);
		$worker->logLevel = $logLevel;

		$PIDFILE = getenv('PIDFILE');
		if ($PIDFILE)
		{
			file_put_contents($PIDFILE, getmypid()) or die('Could not write PID information to ' . $PIDFILE);
		}

		logStart($logger, array('message' => '*** Starting worker ' . $worker, 'data' => array('type' => 'start', 'worker' => (string) $worker)));
		$worker->work($interval);
	}

	function logStart($logger, $message)
	{
		if($logger === null)
		{
			fwrite(STDOUT, (($logLevel == self::LOG_NORMAL) ? "" : "[" . strftime('%T %Y-%m-%d') . "] ") . $message['message'] . "\n");
		}
		else
		{
			$logger->addInfo($message['message'], $message['data']);
		}
	}