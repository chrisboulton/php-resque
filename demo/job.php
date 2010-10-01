<?php
class PHP_Job
{
	public function perform()
	{
		sleep(120);
		fwrite(STDOUT, 'Hello!');
	}
}
?>