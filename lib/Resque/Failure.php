<?php

namespace Resque;

/**
 * Failed Resque job.
 *
 * @package		Resque/Failure
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class Failure
{
    /**
     * @var string Class name representing the backend to pass failed jobs off to.
     */
    public $backend;

    /**
     * Create a new failed job on the backend.
     */
    public function __construct($resque, $payload, \Exception $exception, Worker $worker, $queue)
    {
        $backend = $this->getBackend();
        new $backend($resque, $payload, $exception, $worker, $queue);
    }

    /**
     * Return an instance of the backend for saving job failures.
     *
     * @return object Instance of backend object.
     */
    public function getBackend()
    {
        if ($this->backend === null) {
            $this->backend = 'Resque\\Failure\\Redis';
        }

        return $this->backend;
    }

    /**
     * Set the backend to use for raised job failures. The supplied backend
     * should be the name of a class to be instantiated when a job fails.
     * It is your responsibility to have the backend class loaded (or autoloaded)
     *
     * @param string $backend The class name of the backend to pipe failures to.
     */
    public function setBackend($backend)
    {
        $this->backend = $backend;
    }
}
