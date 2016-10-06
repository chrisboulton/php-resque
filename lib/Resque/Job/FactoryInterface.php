<?php

interface Resque_Job_FactoryInterface
{
    /**
     * @return Resque_JobInterface
     */
    public function create();
}
