<?php

use EBernhardson\FastCGI\Client;
use EBernhardson\FastCGI\CommunicationException;

/**
 * @package Resque/JobStrategy
 * @author  Erik Bernhardson <bernhardsonerik@gmail.com>
 * @license http://www.opensource.org/licenses/mit-license.php
 */
class Resque_JobStrategy_Fastcgi implements Resque_JobStrategy_Interface
{
    /**
     * @var bool True when waiting for a response from fcgi server
     */
    private $waiting = false;

    /**
     * @var array Default enironment for FCGI requests
     */
    protected $requestData = array(
        'GATEWAY_INTERFACE' => 'FastCGI/1.0',
        'REQUEST_METHOD' => 'GET',
        'SERVER_SOFTWARE' => 'php-resque-fastcgi/1.3-dev',
        'REMOTE_ADDR' => '127.0.0.1',
        'REMOTE_PORT' => 8888,
        'SERVER_ADDR' => '127.0.0.1',
        'SERVER_PORT' => 8888,
        'SERVER_PROTOCOL' => 'HTTP/1.1'
    );

    /**
     * @param string $location When the location contains a `:` it will be considered a host/port pair
     *							otherwise a unix socket path
     * @param string $script      Absolute path to the script that will load resque and perform the job
     * @param array  $environment Additional environment variables available in $_SERVER to the fcgi script
     */
    public function __construct($location, $script, $environment = array())
    {
        $this->location = $location;

        $port = false;
        if (false !== strpos($location, ':')) {
            list($location, $port) = explode(':', $location, 2);
        }

        $this->fcgi = new Client($location, $port);
        $this->fcgi->setKeepAlive(true);

        $this->requestData = $environment + $this->requestData + array(
            'SCRIPT_FILENAME' => $script,
            'SERVER_NAME' => php_uname('n'),
            'RESQUE_DIR' => __DIR__.'/../../../',
        );
    }

    /**
     * @param Resque_Worker $worker
     */
    public function setWorker(Resque_Worker $worker)
    {
        $this->worker = $worker;
    }

    /**
     * Executes the provided job over a fastcgi connection
     *
     * @param Resque_Job $job
     */
    public function perform(Resque_Job $job)
    {
        $status = 'Requested fcgi job execution from ' . $this->location . ' at ' . strftime('%F %T');
        $this->worker->updateProcLine($status);
        $this->worker->log($status);

        $this->waiting = true;

        try {
            $this->fcgi->request(array(
                'RESQUE_JOB' => urlencode(serialize($job)),
            ) + $this->requestData, '');

            $response = $this->fcgi->response();
            $this->waiting = false;
        } catch (CommunicationException $e) {
            $this->waiting = false;
            $job->fail($e);

            return;
        }

        if ($response['statusCode'] !== 200) {
            $job->fail(new Exception(sprintf(
                'FastCGI job returned non-200 status code: %s Stdout: %s Stderr: %s',
                $response['headers']['status'],
                $response['body'],
                $response['stderr']
            )));
        }
    }

    /**
     * Shutdown the worker process.
     */
    public function shutdown()
    {
        if ($this->waiting === false) {
            $this->worker->log('No child to kill.');
        } else {
            $this->worker->log('Closing fcgi connection with job in progress.');
        }
        $this->fcgi->close();
    }
}
