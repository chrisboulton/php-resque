<?php
if(empty($_ENV)) {
	die("\$_ENV does not seem to be available. Ensure 'E' is in your variables_order php.ini setting\n");
}

if(empty($_ENV['QUEUE'])) {
	die("Set QUEUE env var containing the list of queues to work.\n");
}

if(!empty($_ENV['APP_INCLUDE'])) {
	if(!file_exists($_ENV['APP_INCLUDE'])) {
		die('APP_INCLUDE ('.$_ENV['APP_INCLUDE'].") does not exist.\n");
	}

	require_once $_ENV['APP_INCLUDE'];
}

require 'lib/Resque.php';
require 'lib/Resque/Worker.php';

if(!empty($_ENV['REDIS_BACKEND'])) {
	Resque::setBackend($_ENV['REDIS_BACKEND']);
}

$logLevel = 0;
if(!empty($_ENV['LOGGING']) || !empty($_ENV['VERBOSE'])) {
	$logLevel = Resque_Worker::LOG_NORMAL;
}
else if(!empty($_ENV['VVERBOSE'])) {
	$logLevel = Resque_Worker::LOG_VERBOSE;
}

$interval = 5;
if(!empty($_ENV['INTERVAL'])) {
	$interval = $_ENV['INTERVAL'];
}

$count = 1;
if(!empty($_ENV['COUNT']) && $_ENV['COUNT'] > 1) {
	$count = $_ENV['COUNT'];
}

if($count > 1) {
	for($i = 0; $i < $count; ++$i) {
		$pid = pcntl_fork();
		if($pid == -1) {
			die("Could not fork worker ".$i."\n");
		}
		// Child, start the worker
		else if(!$pid) {
			$queues = explode(',', $_ENV['QUEUE']);
			$worker = new Resque_Worker($queues);
			$worker->logLevel = $logLevel;
			fwrite(STDOUT, '*** Starting worker '.$worker."\n");
			$worker->work($interval);
			break;
		}
	}
}
// Start a single worker
else {
	$queues = explode(',', $_ENV['QUEUE']);
	$worker = new Resque_Worker($queues);
	$worker->logLevel = $logLevel;
	fwrite(STDOUT, '*** Starting worker '.$worker."\n");
	$worker->work($interval);
}
?>