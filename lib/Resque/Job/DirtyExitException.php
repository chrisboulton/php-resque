<?php
/**
 * Runtime exception class for a job that does not exit cleanly.
 *
 * @package		Resque/Job
 * @author		Chris Boulton <chris.boulton@interspire.com>
 * @copyright	(c) 2010 Chris Boulton
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class Resque_Job_DirtyExitException extends RuntimeException
{

}