<?php

namespace Resque;

/**
 * Resque statistic management (jobs processed, failed, etc)
 *
 * @package		Resque/Stat
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class Stat
{
    public $backend;

    public function __construct($backend)
    {
        $this->backend = $backend;
    }

    /**
     * Get the value of the supplied statistic counter for the specified statistic.
     *
     * @param  string $stat The name of the statistic to get the stats for.
     * @return mixed  Value of the statistic.
     */
    public function get($stat)
    {
        return (int) $this->backend->get('stat:' . $stat);
    }

    /**
     * Increment the value of the specified statistic by a certain amount (default is 1)
     *
     * @param  string  $stat The name of the statistic to increment.
     * @param  int     $by   The amount to increment the statistic by.
     * @return boolean True if successful, false if not.
     */
    public function incr($stat, $by = 1)
    {
        return (bool) $this->backend->incrby('stat:' . $stat, $by);
    }

    /**
     * Decrement the value of the specified statistic by a certain amount (default is 1)
     *
     * @param  string  $stat The name of the statistic to decrement.
     * @param  int     $by   The amount to decrement the statistic by.
     * @return boolean True if successful, false if not.
     */
    public function decr($stat, $by = 1)
    {
        return (bool) $this->backend->decrby('stat:' . $stat, $by);
    }

    /**
     * Delete a statistic with the given name.
     *
     * @param  string  $stat The name of the statistic to delete.
     * @return boolean True if successful, false if not.
     */
    public function clear($stat)
    {
        return (bool) $this->backend->del('stat:' . $stat);
    }
}
