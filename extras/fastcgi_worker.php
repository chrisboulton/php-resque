<?php

if (!isset($_SERVER['RESQUE_JOB'])) {
    header('Status: 500 No Job');
    return;
}

require_once dirname(__FILE__).'/../lib/Resque.php';
require_once dirname(__FILE__).'/../lib/Resque/Worker.php';

if (isset($_SERVER['REDIS_BACKEND'])) {
    Resque::setBackend($_SERVER['REDIS_BACKEND']);
}

try {
    if (isset($_SERVER['APP_INCLUDE'])) {
        require_once $_SERVER['APP_INCLUDE'];
    }

    $job = unserialize(urldecode($_SERVER['RESQUE_JOB']));
    $job->worker->perform($job);
} catch (\Exception $e) {
    if (isset($job)) {
        $job->fail($e);
    } else {
        header('Status: 500');
    }
}

?>
