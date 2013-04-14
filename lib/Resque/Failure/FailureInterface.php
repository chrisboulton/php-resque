<?php

namespace Resque\Failure;

/**
 * Interface that all failure backends should implement.
 *
 * @package		Resque/Failure
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
interface FailureInterface
{
    /**
     * Initialize a failed job class and save it (where appropriate).
     */
    public function __construct($resque, $payload, $exception, $worker, $queue);
}
