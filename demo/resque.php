<?php
date_default_timezone_set('GMT');
require 'bad_job.php';
require 'job.php';
require 'php_error_job.php';

require __DIR__ . '../bin/resque.php';
