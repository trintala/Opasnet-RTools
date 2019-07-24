<?php

	define('DB_HOST',''); // RTools metadata SQL host
	define('DB_NAME',''); // RTools metadata database name
	define('DB_USERNAME',''); // RTools metadata user
	define('DB_PASSWORD',''); // RTools metadata password
 
 	define('MAX_JOBS_PENDING',10); // max run queue length
 	define('MAX_JOBS_RUNNING',5); // max simultaneous runs
  	define('RUN_TIMEOUT',60*60*24); // seconds to timeout = maximum execution time
 	
 	define('R_PATH','/usr/bin/R');
 	define('RUN_PATH','/var/www/html/rtools_server/runs');
 	define('CODE_PATH','/var/www/html/rtools_server/codes');
 	
 	define('ERRORS_LOG','/var/www/html/rtools_server/logs/errors.log');
 	define('MESSAGES_LOG','/var/www/html/rtools_server/logs/messages.log');
 	
 	define('RUN_URL','http://'.'localhost'.'/rtools_server/runs'); 	// URL returned to MediaWiki RTools extension when submitting runs (may be local IP)
 	
?>
