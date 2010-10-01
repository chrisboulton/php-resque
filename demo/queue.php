<?php
if(empty($argv[1])) {
	die('Specify the name of a job to add. e.g, php queue.php PHP_Job');
}

require '../lib/Resque.php';
date_default_timezone_set('GMT');
Resque::setBackend('127.0.0.1:6379');

$args = array(
	'time' => time(),
	'array' => array(
		'test' => 'test',
	),
);

$jobId = Resque::enqueue('default', $argv[1], $args, true);
echo "Queued job ".$jobId."\n\n";
?>