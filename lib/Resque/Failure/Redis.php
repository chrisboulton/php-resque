<?php
/**
 * Redis backend for storing failed Resque jobs.
 *
 * @package		Resque/Failure
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */

class Resque_Failure_Redis implements Resque_Failure_Interface
{
	/**
	 * Initialize a failed job class and save it (where appropriate).
	 *
	 * @param object $payload Object containing details of the failed job.
	 * @param object $exception Instance of the exception that was thrown by the failed job.
	 * @param object $worker Instance of Resque_Worker that received the job.
	 * @param string $queue The name of the queue the job was fetched from.
	 */
	public function __construct($payload, $exception, $worker, $queue)
	{
		$data = new stdClass;
		$data->failed_at = strftime('%a %b %d %H:%M:%S %Z %Y');
		$data->payload = $payload;
		$data->exception = get_class($exception);
		$data->error = $this->formatExceptionMessage($exception);
		$data->backtrace = $this->formatExceptionTrace($exception);
		$data->worker = (string)$worker;
		$data->queue = $queue;
		$data = json_encode($data);
		Resque::redis()->rpush('failed', $data);
	}

	/**
	 * Get the exception message with code, if available.
	 *
	 * @param object $exception
	 *
	 * @return string
	 */
	private function formatExceptionMessage($exception)
	{
		$message = $exception->getMessage();

		$code = $exception->getCode();
		if ($code) {
			// getCode(..) returns the exception code as integer in Exception,
			// but possibly as other type in descendants, assume string.
			$message = sprintf('[%s] %s', $code, $message);
		}

		return $message;
	}

	/**
	 * Get a detailed exception trace including and previous exceptions.
	 *
	 * @param object $exception
	 *
	 * @return string[]
	 */
	private function formatExceptionTrace($exception)
	{
		$trace = explode("\n", $exception->getTraceAsString());

		$previous = $exception->getPrevious();
		if ($previous instanceof \Exception) {
			$trace[] = null;
			$trace[] = sprintf('Caused by: %s', $this->formatExceptionMessage($previous));
			$trace = array_merge($trace, $this->formatExceptionTrace($previous));
		}

		return $trace;
	}
}
