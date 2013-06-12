<?php
class Bad_PHP_Job
{
	public function perform()
	{
		throw new Exception('Unable to run this job!');
	}
}