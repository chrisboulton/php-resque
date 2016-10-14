<?php

class Resque_Job_Factory implements Resque_Job_FactoryInterface
{

    /**
     * @param $className
     * @param array $args
     * @param $queue
     * @return Resque_JobInterface
     * @throws \Resque_Exception
     */
    public function create($className, $args, $queue)
    {
        if (!class_exists($className)) {
            throw new Resque_Exception(
                'Could not find job class ' . $className . '.'
            );
        }

        if (!method_exists($className, 'perform')) {
            throw new Resque_Exception(
                'Job class ' . $className . ' does not contain a perform method.'
            );
        }

        $instance = new $className;
        $instance->args = $args;
        $instance->queue = $queue;
        return $instance;
    }
}
