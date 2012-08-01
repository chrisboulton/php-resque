<?php
$QUEUE = getenv('QUEUE');
if(empty($QUEUE)) {
	die("Set QUEUE env var containing the list of queues to work.\n");
}

require_once 'lib/Resque.php';
require_once 'lib/Resque/Worker.php';

$REDIS_BACKEND = getenv('REDIS_BACKEND');
if(!empty($REDIS_BACKEND)) {
	Resque::setBackend($REDIS_BACKEND);
}

$logLevel = 0;
$LOGGING = getenv('LOGGING');
$VERBOSE = getenv('VERBOSE');
$VVERBOSE = getenv('VVERBOSE');
if(!empty($LOGGING) || !empty($VERBOSE)) {
	$logLevel = Resque_Worker::LOG_NORMAL;
}
else if(!empty($VVERBOSE)) {
	$logLevel = Resque_Worker::LOG_VERBOSE;
}

$APP_INCLUDE = getenv('APP_INCLUDE');
if($APP_INCLUDE) {
	if(!file_exists($APP_INCLUDE)) {
		die('APP_INCLUDE ('.$APP_INCLUDE.") does not exist.\n");
	}

	require_once $APP_INCLUDE;
}

$interval = 5;
$INTERVAL = getenv('INTERVAL');
if(!empty($INTERVAL)) {
	$interval = $INTERVAL;
}

$count = 1;
$COUNT = getenv('COUNT');
if(!empty($COUNT) && $COUNT > 1) {
	$count = $COUNT;
}

if($count > 1) {
	$childrens = array();

	$pid = 0;
	for($i = 0; $i < $count; ++$i) {
		$pid = pcntl_fork();
		if($pid == -1) {
			echo "Could not fork worker ".$i."\n";
		}
		// Child, start the worker
		else if(!$pid) {
			create_worker($QUEUE, $logLevel, $interval);
			exit;
		} else {
			$childrens[] = $pid;
		}
	}

	echo "*** I'm your father! ". implode(',', $childrens) ."\n";

	declare(ticks = 1);
	pcntl_signal(SIGTERM, 'sig_handler');
	pcntl_signal(SIGINT, 'sig_handler');
	pcntl_signal(SIGQUIT, 'sig_handler');

	//watch the children
	while(1) {
		sleep(10);
		$status = 0;
		$chldPid = pcntl_wait($status, WNOHANG);
		if($chldPid > 0) {
			echo "*** child $chldPid died unexpectedly, restarting...\n";
			unset($childrens[$chldPid]);
			$pid = pcntl_fork();
			if($pid == -1) {
				echo "Could not fork worker ".$i."\n";
			}
			// Child, start the worker
			else if(!$pid) {
				create_worker($QUEUE, $logLevel, $interval);
				exit;
			} else {
				$childrens[] = $pid;
				echo "*** I'm your father! ". implode(',', $childrens) ."\n";
			}
		}
	}
}
// Start a single worker
else {
	create_worker($QUEUE, $logLevel, $interval);
}

function create_worker($QUEUE, $logLevel, $interval) {
	$queues = explode(',', $QUEUE);
	$worker = new Resque_Worker($queues);
	$worker->logLevel = $logLevel;

	$PIDFILE = getenv('PIDFILE');
	if ($PIDFILE) {
		file_put_contents($PIDFILE, getmypid()) or
			die('Could not write PID information to ' . $PIDFILE);
	}

	fwrite(STDOUT, '*** Starting worker '.$worker."\n");
	$worker->work($interval);
}

// signal handler function
function sig_handler($signo)
{
	global $childrens;
	$status = 0;

	foreach($childrens as $child) {
		echo "*** sending signal $signo to child $child\n";
		posix_kill($child, $signo);
	}

	switch ($signo) {
		case SIGTERM:
		case SIGINT:
		case SIGQUIT:
			$pid = pcntl_wait($status);
			while($pid > 0 && sizeof($childrens) > 0) {
				$exitCode = pcntl_wexitstatus($status);
				echo "*** $pid exited with status ".$exitCode."\n";
				unset($childrens[$pid]);
				$pid = pcntl_wait($status);
			}
			echo "*** finished\n";
			exit;
		break;
	}

}

