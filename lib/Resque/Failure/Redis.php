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
    /**
     * Initialize a failed job class and save it (where appropriate).
     *
     * @param object $payload   Object containing details of the failed job.
     * @param object $exception Instance of the exception that was thrown by the failed job.
     * @param object $worker    Instance of \Resque\Worker that received the job.
     * @param string $queue     The name of the queue the job was fetched from.
     */
    public function __construct($payload, $exception, $worker, $queue)
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
        Resque::getBackend()->rpush('failed', $data);
    }
}
