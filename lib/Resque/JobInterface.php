<?php

interface Resque_JobInterface
{
	/**
	 * @return bool
	 */
	public function perform();
}
