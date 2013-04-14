<?php

namespace Resque\Failure;

use Resque\Resque;

/**
 * Redis backend for storing failed Resque jobs.
 *
 * @package		Resque/Failure
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */

class Redis implements FailureInterface
{
    public function __construct($resque, $payload, $exception, $worker, $queue)
    {
        $data = new \stdClass;
        $data->failed_at = strftime('%a %b %d %H:%M:%S %Z %Y');
        $data->payload = $payload;
        $data->exception = get_class($exception);
        $data->error = $exception->getMessage();
        $data->backtrace = explode("\n", $exception->getTraceAsString());
        $data->worker = (string) $worker;
        $data->queue = $queue;
        $data = json_encode($data);
        $resque->getBackend()->rpush('failed', $data);
    }
}
