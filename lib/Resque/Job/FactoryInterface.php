<?php

interface Resque_Job_FactoryInterface
{
	/**
	 * @param $className
	 * @param array $args
	 * @param $queue
	 * @return Resque_JobInterface
	 */
	public function create($className, $args, $queue);
}
