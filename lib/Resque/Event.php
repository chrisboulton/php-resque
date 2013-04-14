<?php

namespace Resque;

/**
 * Resque event/plugin system class
 *
 * @package		Resque/Event
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class Event
{
    /**
     * @var array Array containing all registered callbacks, indexed by event name.
     */
    public $events = array();

    /**
     * Raise a given event with the supplied data.
     *
     * @param  string  $event Name of event to be raised.
     * @param  mixed   $data  Optional, any data that should be passed to each callback.
     * @return boolean
     */
    public function trigger($event, $data = null)
    {
        if (!is_array($data)) {
            $data = array($data);
        }

        if (empty($this->events[$event])) {
            return true;
        }

        foreach ($this->events[$event] as $callback) {
            if (!is_callable($callback)) {
                continue;
            }
            call_user_func_array($callback, $data);
        }

        return true;
    }

    /**
     * Listen in on a given event to have a specified callback fired.
     *
     * @param  string  $event    Name of event to listen on.
     * @param  mixed   $callback Any callback callable by call_user_func_array.
     * @return boolean
     */
    public function listen($event, $callback)
    {
        if (!isset($this->events[$event])) {
            $this->events[$event] = array();
        }

        $this->events[$event][] = $callback;

        return true;
    }

    /**
     * Stop a given callback from listening on a specific event.
     *
     * @param  string  $event    Name of event.
     * @param  mixed   $callback The callback as defined when listen() was called.
     * @return boolean
     */
    public function stopListening($event, $callback)
    {
        if (!isset($this->events[$event])) {
            return true;
        }

        $key = array_search($callback, $this->events[$event]);
        if ($key !== false) {
            unset($this->events[$event][$key]);
        }

        return true;
    }

    /**
     * Call all registered listeners.
     */
    public function clearListeners()
    {
        $this->events = array();
    }
}
