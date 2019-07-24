<?php

  require_once(dirname(__FILE__).'/lib/xmlrpc.inc');
  require_once(dirname(__FILE__).'/lib/xmlrpcs.inc');

  require_once(dirname(__FILE__).'/lib/rtools_server.class.php');

  // Set new run job
  function new_run ($xmlrpcmsg) {
    global $xmlrpcerruser; // import user errcode base value

    $meth = $xmlrpcmsg->method(); // retrieve method name
    $username = $xmlrpcmsg->getParam(0)->scalarVal();
    $password = $xmlrpcmsg->getParam(1)->scalarVal();
	$wiki_user = $xmlrpcmsg->getParam(2)->scalarVal();
	$IP = $xmlrpcmsg->getParam(3)->scalarVal();
	$article_id = $xmlrpcmsg->getParam(4)->scalarVal();
	$cname = $xmlrpcmsg->getParam(5)->scalarVal();
    $code = $xmlrpcmsg->getParam(6)->scalarVal();
    $graphics = $xmlrpcmsg->getParam(7)->scalarVal();
    $store = $xmlrpcmsg->getParam(8)->scalarVal();

	$err = false;
	$ret = null;
 	
 	try {
	    $rtools_server = new RToolsServer();
 		$ret = $rtools_server->new_run($username, $password, $wiki_user, $IP, $article_id, $cname, $code, $graphics, $store);	
 	}
 	catch (Exception $e)
 	{
		$err = $e->getMessage();
 	}
 	
    if ($err) {
      // this is an error condition
      return new xmlrpcresp(0, $xmlrpcerruser+1, $err);
    } else {
      // this is a successful value being returned
      return new xmlrpcresp(new xmlrpcval($ret));
    }
  }
  
    // Delete run
  function delete_run($xmlrpcmsg) {
    global $xmlrpcerruser; // import user errcode base value

    $meth = $xmlrpcmsg->method(); // retrieve method name
    $username = $xmlrpcmsg->getParam(0)->scalarVal();
    $password = $xmlrpcmsg->getParam(1)->scalarVal();
	$wiki_user = $xmlrpcmsg->getParam(2)->scalarVal();
	$IP = $xmlrpcmsg->getParam(3)->scalarVal();
	$id = $xmlrpcmsg->getParam(4)->scalarVal();

	$err = false;
	$ret = null;
 	
 	try {
	    $rtools_server = new RToolsServer();
 		$ret = $rtools_server->delete_run($username, $password, $wiki_user, $IP, $id);	
 	}
 	catch (Exception $e)
 	{
		$err = $e->getMessage();
 	}
 	
    if ($err) {
      // this is an error condition
      return new xmlrpcresp(0, $xmlrpcerruser+1, $err);
    } else {
      // this is a successful value being returned
      return new xmlrpcresp(new xmlrpcval($ret));
    }
  }

    // Cancel run
  function cancel_run($xmlrpcmsg) {
    global $xmlrpcerruser; // import user errcode base value

    $meth = $xmlrpcmsg->method(); // retrieve method name
    $username = $xmlrpcmsg->getParam(0)->scalarVal();
    $password = $xmlrpcmsg->getParam(1)->scalarVal();
	$wiki_user = $xmlrpcmsg->getParam(2)->scalarVal();
	$IP = $xmlrpcmsg->getParam(3)->scalarVal();
	$id = $xmlrpcmsg->getParam(4)->scalarVal();

	$err = false;
	$ret = null;
 	
 	try {
	    $rtools_server = new RToolsServer();
 		$ret = $rtools_server->cancel_run($username, $password, $wiki_user, $IP, $id);	
 	}
 	catch (Exception $e)
 	{
		$err = $e->getMessage();
 	}
 	
    if ($err) {
      // this is an error condition
      return new xmlrpcresp(0, $xmlrpcerruser+1, $err);
    } else {
      // this is a successful value being returned
      return new xmlrpcresp(new xmlrpcval($ret));
    }
  }
  
  // Return status of job (or run)
  function status($xmlrpcmsg) {
    global $xmlrpcerruser; // import user errcode base value

    $meth = $xmlrpcmsg->method(); // retrieve method name
    $token = $xmlrpcmsg->getParam(0)->scalarVal();
    $line_count = $xmlrpcmsg->getParam(1)->scalarVal();

	$err = false;
	$ret = null;
 	
 	try {
	    $rtools_server = new RToolsServer();
 		$ret = $rtools_server->status($token, $line_count);	
 	}
 	catch (Exception $e)
 	{
		$err = $e->getMessage();
 	}
 	
    if ($err) {
      // this is an error condition
      return new xmlrpcresp(0, $xmlrpcerruser+1, $err);
    } else {
      // this is a successful value being returned
      return new xmlrpcresp(new xmlrpcval($ret));
    }
  }
  
  // Return array of plot file names
  function plots($xmlrpcmsg) {
    global $xmlrpcerruser; // import user errcode base value

    $meth = $xmlrpcmsg->method(); // retrieve method name
    $token = $xmlrpcmsg->getParam(0)->scalarVal();
 
	$err = false;
	$ret = null;
 	
 	try {
	    $rtools_server = new RToolsServer();
 		$ret = $rtools_server->plots($token);	
 	}
 	catch (Exception $e)
 	{
		$err = $e->getMessage();
 	}
 	
    if ($err) {
      // this is an error condition
      return new xmlrpcresp(0, $xmlrpcerruser+1, $err);
    } else {
      $tmp = array();
      foreach ($ret as $r)
      	$tmp[] = new xmlrpcval($r,"string");
      // this is a successful value being returned
      return new xmlrpcresp(new xmlrpcval($tmp,"array"));
    }
  }
  
  // Return the run completion time
  function complete_time($xmlrpcmsg) {
    global $xmlrpcerruser; // import user errcode base value

    $meth = $xmlrpcmsg->method(); // retrieve method name
    $token = $xmlrpcmsg->getParam(0)->scalarVal();
 
	$err = false;
	$ret = null;
 	
 	try {
	    $rtools_server = new RToolsServer();
 		$ret = $rtools_server->complete_time($token);	
 	}
 	catch (Exception $e)
 	{
		$err = $e->getMessage();
 	}
 	
    if ($err) {
      // this is an error condition
      return new xmlrpcresp(0, $xmlrpcerruser+1, $err);
    } else {
      // this is a successful value being returned
      return new xmlrpcresp(new xmlrpcval($ret));
    }
  }
  
  // Return array of times
  function times($xmlrpcmsg) {
    global $xmlrpcerruser; // import user errcode base value

    $meth = $xmlrpcmsg->method(); // retrieve method name
    $token = $xmlrpcmsg->getParam(0)->scalarVal();
 
	$err = false;
	$ret = null;
 	
 	try {
	    $rtools_server = new RToolsServer();
 		$ret = $rtools_server->times($token);	
 	}
 	catch (Exception $e)
 	{
		$err = $e->getMessage();
 	}
 	
    if ($err) {
      // this is an error condition
      return new xmlrpcresp(0, $xmlrpcerruser+1, $err);
    } else {
      $tmp = array();
      foreach ($ret as $k => $v)
      	$tmp[] = new xmlrpcval($v,"string");
      // this is a successful value being returned
      return new xmlrpcresp(new xmlrpcval($tmp,"array"));
    }
  }
  
  $s = new xmlrpc_server(
    array(
      "rtools.new_run" => array("function" => "new_run"),
      "rtools.delete_run" => array("function" => "delete_run"),
      "rtools.cancel_run" => array("function" => "cancel_run"),
      "rtools.status" => array("function" => "status"),
      "rtools.plots" => array("function" => "plots"),
      "rtools.complete_time" => array("function" => "complete_time"),
      "rtools.times" => array("function" => "times")
    ));

?>