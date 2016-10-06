<?php

interface Resque_Job_FactoryInterface
{
    /**
     * @param $className
     * @return Resque_JobInterface
     */
    public function create($className);
}
