<?php

 // Report simple running errors
 error_reporting(E_ALL);

 require_once(dirname(__FILE__).'/../lib/rtools_server.class.php');

 $rtools_server = new RToolsServer();
 
 $rtools_server->log_message('R-tools server started');
 
 while(true)
 {
	 try {
 		$rtools_server->check_completed();
 		$rtools_server->check_timeouts();
 		$rtools_server->check_canceled();
 		$rtools_server->run_pending();
 		$rtools_server->delete_purged();
	 }
	 catch (Exception $e)
	 {
		$rtools_server->log_error($e->getMessage());	 	
		$rtools_server->log_message('R-tools server shutting down');
 	 	exit();
 	 }
	 usleep(100000);
 }

?>
