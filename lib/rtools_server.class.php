<?php

require_once(dirname(__FILE__).'/../config.php');

class RToolsServer{

	private $db_link;

	// List of forbidden functions and classes
	private	$invalids = array(
		'Rcpp',
		'curl',
		'read_auth',
		'Sys',
		'rvest',
		'gsheet',
		'unlockBinding', # This is important because we do not want to allow users to overwrite library functions!!!!
		'call', # do.call can be used for aliasing functions, must be forbidden
		'system',
		'system2',
		'file',
		'url',
		'gzfile',
		'bzfile',
		'xzfile',
		'gzcon',
		'unz',
		'pipe',
		'fifo',
		'socketConnection',
		'odbcConnect',
		'open',
		'read',
		'readLines',
		'writeLines',
		'scan',
		'write',
		'parse',
		'eval',
		'sink',
		'install',
		'getURL',
		'getURLContent',
		'getForm',
		'postForm',
		'download.file',
		'dget',
		'dput',
		'dump',
		'genBugsScript',
		'genDataFile',
		'genInitsFile',
		'getBugsOutput',
		'rbugs',
		'runBugs',
		'source',
		'savehistory',
		'loadhistory',
		'save',
		'load',
		'readIniFile',
		'KML',
		'shapefile',
		'stackOpen',
		'stackSave',
		'writeRaster',
		'writeStart',
		'getToHost',
		'getToHost2',
		'postToHost',
		'simplePostToHost',
		'openssl'
		);
	
	# Some special regexp to invalidate the code
	private $invalid_specials = array(
		'/cat *\([^)]*file *=[^)]*\)/',    # Prevent cat to file but allow cat otherwise
		'/(<-|=) *get *\(/' # Prevent plain get because it can be used for aliasing forbidden functions
	);

    function __construct()
    {
		$this->db_link = mysqli_connect(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
		if (mysqli_connect_errno($this->db_link))
		     throw new Exception('Could not connect to database: ' . mysqli_connect_error());
		#if (!$this->db_link)
		#    throw new Exception('Could not connect to database: ' . mysqli_error());
		#$db = mysqli_select_db(DB_NAME,$this->db_link);
		#if (!$db)
		#    throw new Exception('Could not select database: ' . mysqli_error());
    }

    function __destruct()
    {
		mysqli_close($this->db_link);
	}
	
	// Sets new run and returns the token
    function new_run($username, $password, $wiki_user, $IP, $article_id, $cname, $code, $graphics = false, $store = false)
    {
    	if (! ($user_id = $this->authenticate($username, $password)))
    		throw new Exception('__authentication_failed__');

    	if ($this->count_jobs('pending') >= MAX_JOBS_PENDING)
    		throw new Exception('__pending_job_queue_full__');
    	
    	$this->validate_code($code, $username);
    	
    	if (strpos($cname, ',') !== false)
    		throw new Exception('__invalid_code_name__');
    	
    	return $this->add_job($user_id,  $wiki_user, $IP, $article_id, $cname, $code, $graphics, $store);
    }
    
	// Marks job to be deleted
    function delete_run($username, $password, $wiki_user, $IP, $token)
    {
    	if (! ($user_id = $this->authenticate($username, $password)))
    		throw new Exception('__authentication_failed__');

	# Find the job first
	$q = 'SELECT id,wiki_user FROM jobs WHERE token = "'.$token.'"';
	$result = mysqli_query($this->db_link,$q);
    	if ($result === false) {
    	    $message  = 'Invalid query: ' . mysqli_error($this->db_link) . ", query: ".$q."\n";
    	    throw new Exception($message);
	}
	while ($row = mysqli_fetch_assoc($result)) {
	    $row_id =  $row['id'];
	    $row_user =  $row['wiki_user'];
		break;
	}
	mysqli_free_result($result);
    	
    	#if ($wiki_user == '')
    	#	throw new Exception('__only_authenticated_users_can_delete_runs__');
	
    	if ($wiki_user != $row_user)
    		throw new Exception('__only_run_executor_can_delete_run__');
    	
	$query  = 'INSERT INTO deleted_jobs (job_id) VALUES ('.$row_id.')';
  	
  	$result = mysqli_query($this->db_link, $query);
    	if ($result === false) {
    		$message  = 'Invalid query: ' . mysqli_error($this->db_link) . " query: " .$query. "\n";
    		throw new Exception($message);
	}
	
    }
    
    // Cancels run
    function cancel_run($username, $password, $wiki_user, $IP, $token)
    {
    	if (! ($user_id = $this->authenticate($username, $password)))
    		throw new Exception('__authentication_failed__');

		# Find the job first
		$q = 'SELECT id,wiki_user,status FROM jobs WHERE token = "'.$token.'"';
		$result = mysqli_query($this->db_link, $q);
    	if ($result === false) {
    		$message  = 'Invalid query: ' . mysqli_error($this->db_link) . ", query: ".$q."\n";
    		throw new Exception($message);
		}
		while ($row = mysqli_fetch_assoc($result)) {
		    $row_id =  $row['id'];
		    $row_user =  $row['wiki_user'];
		    $row_status = $row['status'];
			break;
		}
		mysqli_free_result($result);
		
		if ($row_status != 'running') return;
		
    	#if ($wiki_user == '')
    	#	throw new Exception('__only_authenticated_users_can_delete_runs__');

    	if ($wiki_user != $row_user)
    		throw new Exception('__only_run_executor_can_cancel_run__');
 
		$query  = 'INSERT INTO canceled_jobs (job_id) VALUES ('.$row_id.')';
  	
  		$result = mysqli_query($this->db_link, $query);
    	if ($result === false) {
    		$message  = 'Invalid query: ' . mysqli_error($this->db_link) . " query: " .$query. "\n";
    		throw new Exception($message);
		}  
    			
    	// Set job status to cancelled and kill the process
    	//$this->cancel($row_id, $pid);
    	
    	// Make sure nothing is left on drive
    	//$this->delete_run_files($token);
    	
    }
    
    // Run pending jobs (only limited amount of them)
    function run_pending()
    {
    	$running = $this->count_jobs('running');
    	$l = MAX_JOBS_RUNNING - $running;
    	
    	if ($l < 1)
    		return;
    		
    	$pendings = $this->pendings();
    	$i = 0;
    	foreach ($pendings as $key => $token)
    		if ($i++ < $l)
    			$this->run($key, $token);
    		else
    			return;
    }
    
    function delete_purged()
    {
    	$runs = $this->deleted();
    	$i = 0;
    	foreach ($runs as $key => $token)
		{
			$this->delete($key);
			if (! $this->delete_run_files($token))
				log_error('Cannot delete run files! Token: '.$token);
		}
    }    
    
    // Check completed jobs (or dead?)
    function check_completed()
    {
    	$ids = $this->runnings();  	
    	foreach ($ids as $id => $pid)
    		if ($pid and ! $this->is_running($pid))
    			$this->complete($id);
    }
    
    // Check jobs for timeouts
    function check_timeouts()
    {
    	$ids = $this->running_timeouts();
    	foreach ($ids as $id => $pid)
    		if ($pid and $this->is_running($pid))
    			$this->timeout($id, $pid);
    }

    // Check jobs for cancellation
    function check_canceled()
    {
    	$ids = $this->canceled();
    	foreach ($ids as $id => $pid)
    		if ($pid and $this->is_running($pid))
    			$this->cancel($id, $pid);
    }
        
    // Return status of a job by given token
    function status($token, $line_count = false)
    {
	// First try the fast way, check if searched job is active (usually is, running or pending)
 	$q = 'SELECT status FROM active_jobs LEFT JOIN jobs ON (job_id = id) WHERE token = "'.$token.'"';
	$result = mysqli_query($this->db_link, $q);
    	if ($result === false) {
    		$message  = 'Invalid query: ' . mysqli_error($this->db_link) . ", query: ".$q."\n";
    		throw new Exception($message);
	}
	
	$ret = false;
	while ($row = mysqli_fetch_assoc($result)) {
	    $ret =  $row['status'];
	}
	mysqli_free_result($result);
	if ($ret)
		return $ret;
	
	// Job was not amongs active, go for the the slower way (go thru whole jobs-table)
 	$q = 'SELECT status FROM jobs WHERE token = "'.$token.'"';
	$result = mysqli_query($this->db_link, $q);
    	if ($result === false) {
    		$message  = 'Invalid query: ' . mysqli_error($this->db_link) . ", query: ".$q."\n";
    		throw new Exception($message);
	}
	
	$ret = false;
	
	while ($row = mysqli_fetch_assoc($result)) {
	    $ret =  $row['status'];
	}
	
	mysqli_free_result($result);
    	return $ret;
    }
    
    // Returns list of plot file names
    function plots($token)
    {
    	$ret = array();
    	$list = shell_exec('ls '.RUN_PATH.'/'.$token.'_plot*.png 2> /dev/null');
    	if ($list !== false)
    	{
    		$tmp =  explode("\n",$list);
    		foreach ($tmp as $p)
    			$ret[] = array_pop(explode('/',$p));
    		return $ret;
    	}
    	else
    		throw new Exception('Plot listing failed');
    }
    
    function complete_time($token)
    {
 	$q = 'SELECT TIME_FORMAT(TIMEDIFF(end_at, ran_at),"%kh %im %Ss") as complete_time FROM jobs WHERE token = "'.$token.'"';
	$result = mysqli_query($this->db_link, $q);
    	if ($result === false) {
    		$message = 'Invalid query: ' . mysqli_error($this->db_link) . ", query: ".$q."\n";
    		throw new Exception($message);
	}
	
	$ret = 0;
	
	while ($row = mysqli_fetch_assoc($result)) {
		$ret =  $row['complete_time'];
	}
	
	mysqli_free_result($result);

    	return $ret;
    }
    
    // Returns times for job
    // 0 => queued_at
    // 1 => ran_at
    // 2 => end_a
    function times($token)
    {
 	$q = 'SELECT queued_at, ran_at, end_at FROM jobs WHERE token = "'.$token.'"';
	$result = mysqli_query($this->db_link, $q);
    	if ($result === false) {
    		$message = 'Invalid query: ' . mysqli_error($this->db_link) . ", query: ".$q."\n";
    		throw new Exception($message);
	}
	
	$ret = array();
	
	while ($row = mysqli_fetch_assoc($result)) {
		$ret[0] =  $row['queued_at'];
		$ret[1] =  $row['ran_at'];
		$ret[2] =  $row['end_at'];
	}
	
	mysqli_free_result($result);
	
    	return $ret;
    }
    
    function log_error($msg)
    {
  	$fh = fopen(ERRORS_LOG, 'a') or die("can't open log file");
	$msg = date(DATE_RFC822) . ' - ' . $msg ."\n";
	fwrite($fh, $msg);
	fclose($fh);
    }
    
    function log_message($msg)
    {
  	$fh = fopen(MESSAGES_LOG, 'a') or die("can't open log file");
	$msg = date(DATE_RFC822) . ' - ' . $msg ."\n";
	fwrite($fh, $msg);
	fclose($fh);
    }
    
    /* PRIVATE FROM HERE TO BOTTOM ! */ 
    
    private  function cmd_line_args($job_id, $token)
    {
    	$args = '';
    	$username = '';
    	
 	$result = mysqli_query($this->db_link, 'SELECT username, args, wiki_page_id, code_name FROM users LEFT JOIN jobs ON users.id = jobs.user_id WHERE jobs.id = '.$job_id);
    	if ($result === false) {
    		$message  = 'Invalid query: ' . mysqli_error($this->db_link) . "\n";
    		throw new Exception($message);
	}
	
	while ($row = mysqli_fetch_assoc($result)) {
		$username = $row['username'];
		$wiki_page_id = $row['wiki_page_id'];
		$args = $row['args'];
		$code_name = $row['code_name'];
	}
	
	mysqli_free_result($result);
	
	if (! empty($args))
		$args = ',' . $args;
	
	return '--args "wiki_page_id='.$wiki_page_id.',code_name='.$code_name.',user='.$username.',token='.$token.',run_url='.RUN_URL.$args.'"';		
	#return '--args wiki_page_id="'.$wiki_page_id.'",code_name="'.$code_name.'",user="'.$username.'",token="'.$token.'",run_url="'.RUN_URL.'"'.$args;
    }
    
    private function run($id, $token)
    {
	//echo $id.":".$token;
	// Some paths needed
	$input_file = CODE_PATH . '/' . $token . '_input.R';
	$output_file = RUN_PATH . '/' . $token . '_output.txt.gz';
	$error_file = RUN_PATH . '/' . $token . '_errors.txt';
	
	$args = $this->cmd_line_args($id, $token);
	
	// Then run
	#$cmd = R_PATH." --quiet --no-restore --no-save --no-readline " . $args . " < ".$input_file." 2> ".$error_file." | grep -v '#_exclude_from_output_' | gzip > ".$output_file;
	$cmd = R_PATH." --quiet --no-restore --no-save --no-readline " . $args . " < ".$input_file." | grep -v '#_exclude_from_output_' | gzip";
	#$cmd = R_PATH." --quiet --no-restore --no-save --no-readline " . $args . " < ".$input_file." > ".$output_file;
	#$cmd = R_PATH." --quiet --no-restore --no-save --no-readline " . $input_file. " " .$args. " 2> ".$error_file." | grep -v '#_exclude_from_output_' | gzip > ".$output_file;
	#$cmd = R_PATH." --quiet --no-restore --no-save --no-readline " . $input_file. " " .$args. " | grep -v '#_exclude_from_output_' | gzip > ".$output_file;
	#$cmd = R_PATH." CMD BATCH --quiet --no-restore --no-save --no-readline '".$args."' ".$input_file." ".$output_file;
	
	chdir(RUN_PATH);
	
	$locale = 'en_US.UTF-8';
	setlocale(LC_ALL, $locale);
	putenv('LC_ALL='.$locale);
	
	$this->log_message('Running R with cmd: '.$cmd);
	
	$pid = shell_exec('nohup bash -c "'.$cmd.'"'.' > '.$output_file.' 2> '.$error_file.' & echo $!');
	#$pid = shell_exec('nohup '.$cmd.' & echo $!');
	#$pid = shell_exec($cmd.' & echo $!');
	
	# Update job status and store PID
	if ($pid > 0) {
		$query = 'UPDATE jobs SET status="running", pid='.$pid.', ran_at=NOW() WHERE id='.$id;
  		$result = mysqli_query($this->db_link, $query);
    		if ($result === false) {
    			$message  = 'Invalid query: ' . mysqli_error($this->db_link) . " query: " .$query. "\n";
    			throw new Exception($message);
		}
	} else {
		// Failed to start (TODO: create more informative handling)
		$query = 'UPDATE jobs SET status="canceled", end_at=NOW() WHERE id='.$id;
  		$result = mysqli_query($this->db_link, $query);
    		if ($result === false) {
    			$message  = 'Invalid query: ' . mysqli_error($this->db_link) . " query: " .$query. "\n";
    			throw new Exception($message);
    		}
		$this->remove_active_job($id);
    		$this->remove_canceled_job($id);
	}
    }
    
    private function complete($id)
    {
	$query = 'UPDATE jobs SET status="completed", end_at=NOW() WHERE id='.$id;
  	$result = mysqli_query($this->db_link, $query);
    	if ($result === false) {
    		$message  = 'Invalid query: ' . mysqli_error($this->db_link) . " query: " .$query. "\n";
    		throw new Exception($message);
    	}
    	$this->remove_active_job($id);
    }

    private function cancel($id, $pid)
    {
	$query = 'UPDATE jobs SET status="canceled", end_at=NOW() WHERE id='.$id;
  	$result = mysqli_query($this->db_link, $query);
    	if ($result === false) {
    		$message  = 'Invalid query: ' . mysqli_error($this->db_link) . " query: " .$query. "\n";
    		throw new Exception($message);
    	}
	
    	$this->remove_active_job($id);
    	$this->remove_canceled_job($id);
  	$this->kill($pid);
    }
    
    private function delete($id)
    {
	$query = 'UPDATE jobs SET status="deleted", deleted_at=NOW() WHERE id='.$id;
  	$result = mysqli_query($this->db_link, $query);
    	if ($result === false) {
    		$message  = 'Invalid query: ' . mysqli_error($this->db_link) . " query: " .$query. "\n";
    		throw new Exception($message);
    	}
	$query  = 'DELETE FROM deleted_jobs WHERE job_id = '.$id;
  	
  	$result = mysqli_query($this->db_link, $query);
    	if ($result === false) {
    		$message  = 'Invalid query: ' . mysqli_error($this->db_link) . " query: " .$query. "\n";
    		throw new Exception($message);
	}
    }
   
    private function timeout($id, $pid)
    {
	$query = 'UPDATE jobs SET status="timeout", end_at=NOW() WHERE id='.$id;
  	$result = mysqli_query($this->db_link, $query);
    	if ($result === false) {
    		$message  = 'Invalid query: ' . mysqli_error($this->db_link) . " query: " .$query. "\n";
    		throw new Exception($message);
	} else {
		$this->log_message('Timeouting job ' . $pid . ', max exec time is: ' . RUN_TIMEOUT);
	}
    	$this->remove_active_job($id);
	$this->kill($pid);
    }
    
    // Kill R process
    private function kill($pid)
    {
    	exec("kill ".$pid);
    }
    
    // Return ids and pids for running jobs
    private function runnings()
    {
	$q = 'SELECT id, pid FROM active_jobs LEFT JOIN jobs ON (job_id = id) WHERE status = "running"';
	$result = mysqli_query($this->db_link, $q);
    	if ($result === false) {
    		$message  = 'Invalid query: ' . mysqli_error($this->db_link) . ", query: ".$q."\n";
    		throw new Exception($message);
	}
	
	$ret = array();
	
	while ($row = mysqli_fetch_assoc($result)) {
		$ret[$row['id']] = $row['pid'];
	}
	
	mysqli_free_result($result);
    	return $ret;
    }
    
    // Return ids and pids for timeout jobs
    private function running_timeouts()
    {
	$q = 'SELECT id, pid FROM active_jobs LEFT JOIN jobs ON (job_id = id) WHERE status = "running" AND TIMESTAMPDIFF(SECOND,NOW(),ran_at) > '.RUN_TIMEOUT;
	$result = mysqli_query($this->db_link, $q);
    	if ($result === false) {
    		$message  = 'Invalid query: ' . mysqli_error($this->db_link) . ", query: ".$q."\n";
    		throw new Exception($message);
	}
	
	$ret = array();
	
	while ($row = mysqli_fetch_assoc($result)) {
		$ret[$row['id']] = $row['pid'];
	}
	
	mysqli_free_result($result);
    	return $ret;
    }
    
    // Get pending job ids to be run
    private function pendings()
    {
 	$result = mysqli_query($this->db_link, 'SELECT id, token FROM active_jobs LEFT JOIN jobs ON (job_id = id) WHERE status = "pending" ORDER BY queued_at');
    	
    	if (!$result) {
    		$message  = 'Invalid query: ' . mysqli_error($this->db_link) . "\n";
    		throw new Exception($message);
	}
	
	$ret = array();
	
	while ($row = mysqli_fetch_assoc($result)) {
	    $ret[$row['id']] = $row['token'];
	}

	mysqli_free_result($result);
    	return $ret;
    }
    
    // Get deleted job ids
    private function deleted()
    {
 	$result = mysqli_query($this->db_link, 'SELECT id, token FROM deleted_jobs LEFT JOIN jobs ON (job_id = id)');
    	
    	if (!$result) {
    		$message  = 'Invalid query: ' . mysqli_error($this->db_link) . "\n";
    		throw new Exception($message);
	}
	
	$ret = array();
	
	while ($row = mysqli_fetch_assoc($result)) {
		$ret[$row['id']] = $row['token'];
	}
	
	mysqli_free_result($result);
    	return $ret;
    }
    
    // Get canceled job ids
    private function canceled()
    {
 	$result = mysqli_query($this->db_link, 'SELECT id, pid FROM canceled_jobs LEFT JOIN jobs ON (job_id = id)');
    	
    	if (!$result) {
    		$message  = 'Invalid query: ' . mysqli_error($this->db_link) . "\n";
    		throw new Exception($message);
	}
	
	$ret = array();
	
	while ($row = mysqli_fetch_assoc($result)) {
		$ret[$row['id']] = $row['pid'];
	}
	
	mysqli_free_result($result);
    	return $ret;
    }
    
    private function add_job($id, $wiki_user, $IP, $article_id, $cname, $code, $graphics = false, $store = false)
    {
	// Generate unique token here
	$token = $this->generate_token();
	
	$request_ip = $_SERVER['REMOTE_ADDR'];
	
	$input_file = CODE_PATH . '/' . $token . '_input.R';
	
	if ($graphics)
		$code = 'bitmap(file="'.RUN_PATH.'/'.$token.'_plot%03d.png", type = "pngalpha", width=1024, height=768, units="px")'."\n".$code;
	
	$this->write_file($input_file, $code);
	
	// Make job for the code
	$query = 'INSERT INTO jobs (user_id,token,wiki_user,wiki_user_ip,wiki_page_id,code_name,request_ip,queued_at,store) VALUES ('.mysqli_real_escape_string($this->db_link, $id).',"'.$token.'","'.mysqli_real_escape_string($this->db_link, $wiki_user).'","'.mysqli_real_escape_string($this->db_link, $IP).'","'.mysqli_real_escape_string($this->db_link, $article_id).'","'.mysqli_real_escape_string($this->db_link, $cname).'","'.$request_ip.'",NOW(),'.($store ? 'TRUE' : 'FALSE').')';
	$result = mysqli_query($this->db_link, $query);
    	if ($result === false) {
    		$message  = 'Invalid query: ' . mysqli_error($this->db_link) . " query: " .$query. "\n";
    		throw new Exception($message);
	}
	
	$this->add_active_job();
	
	return $token;
    }
    
    private function delete_run_files($token)
    {
    	# Just to be sure...
    	if ($token == '*')
    		return false;
    	
	foreach(glob(RUN_PATH.'/'.$token.'_*') as $file)
		if (!unlink($file))
	    		return false;
	
	return true;
    }
    
    // Generate unique token
    private function generate_token()
    {
     	$length = 16;
	$characters = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";
    	while (true){
		$random_string = ""; 
		for ($p = 0; $p < $length; $p++)
			$random_string .= $characters[mt_rand(0, strlen($characters)-1)];
		
    		if (!$this->token($random_string))
    			return $random_string;
    	}
    }
    
    // Return true if token is already token
    private function token($token)
    {
      	$result = mysqli_query($this->db_link, 'SELECT id FROM jobs WHERE token = "'.$token.'"');
    	if ($result === false) {
    		$message  = 'Invalid query: ' . mysqli_error($this->db_link) . "\n";
    		throw new Exception($message);
	}
	
	while ($row = mysqli_fetch_assoc($result)) {
		$id = $row['id'];
		if (intval($id) > 0)
			return true;
	}
	
	mysqli_free_result($result);
    	return false;
    }
    
    // Returns number of jobs of status
    private function count_jobs($status)
    {
	// Use optimization if possible
    	if ($status == 'pending' || $status == 'running')
    		$query = 'SELECT COUNT(id) FROM active_jobs LEFT JOIN jobs ON (job_id = id) WHERE status = "'.$status.'"'; 	
    	else
    		$query = 'SELECT COUNT(id) FROM jobs WHERE status = "'.$status.'"';
    	
    	$result = mysqli_query($this->db_link, $query);
    	
    	
    	if ($result === false) {
    		$message  = 'Invalid query: ' . mysqli_error($this->db_link) . "\n";
    		throw new Exception($message);
	}
	
	while ($row = mysqli_fetch_assoc($result)) {
		$count = $row['COUNT(id)'];
	}
	
	mysqli_free_result($result);
    	return $count;
    }
    
    // Authenticate, return ID or null
    private function authenticate($username, $password)
    {
    	$id = null;
	$usr = mysqli_real_escape_string($this->db_link, $username);
    	$pwd = mysqli_real_escape_string($this->db_link, $password);
    	$result = mysqli_query($this->db_link, 'SELECT id FROM users WHERE username = "'.$usr.'" AND password = "'.$pwd.'" AND active = true LIMIT 1');
    	if ($result === false) {
    		$message  = 'Invalid query: ' . mysqli_error($this->db_link) . "\n";
    		throw new Exception($message);
	}
	
	while ($row = mysqli_fetch_assoc($result)) {
		$id = $row['id'];
	}
	
	mysqli_free_result($result);
    	return $id;
    }
    
    private function write_file($input_file, $content)
    {
	if (!$handle = fopen($input_file, 'w'))
	    	throw new Exception('__cannot_create_file__: '.$input_file);
	if (fwrite($handle, $content) === FALSE) {
		fclose($handle);
		throw new Exception('__cannot_write_file__: '.$input_file);
	}
	fclose($handle);
    }
    
    private function validate_code($input, $user = false)
    {
	$found_invalids = array();
	foreach ($this->invalids as $invalid) {
		$arr = array();
		$arr[] = '=\s*([a-zA-Z0-9_]+\.)*'.$invalid.'[^a-zA-Z0-9_]';
		$arr[] = '<-\s*([a-zA-Z0-9_]+\.)*'.$invalid.'[^a-zA-Z0-9_]';
		$arr[] = '[^a-zA-Z0-9_]'.$invalid.'(\.[a-zA-Z0-9_]+)*\s*->';
		$arr[] = '[^a-zA-Z0-9_]'.$invalid.'(\.[a-zA-Z0-9_]+)*\s*\(';
		//$arr[] = '\(\s*'.$invalid.'\s*\)'; // covered by last
		$arr[] = $invalid.'\s*\:'; // access package namespace
		$arr[] = $invalid.'(\.[a-zA-Z0-9_]+)*\s*[,\)]'; // closure prevention
		$m = "/(" . join('|',$arr) . ")/";
		
		if (preg_match_all($m, $input, $matches))
			$found_invalids[] = $invalid;
	}
	
	foreach($this->invalid_specials as $is) {
		if (preg_match($is, $input, $matches))
			$found_invalids[] = $matches[0];
	}
	
	if (! empty($found_invalids))
		throw new Exception('__invalid_code__'.': '.join(',',$found_invalids));
	else
		return true;
    }
    
    // Check if process with given id is running or not
    private function is_running($pid)
    {
   	$state = array();
       	exec("ps $pid", $state);
       	if (count($state) >= 2)
       		return true;
       	else
       		return false;
    }
    
    
    private function add_active_job($id = false)
    {
	if ($id)
		$query  = 'INSERT INTO active_jobs (job_id) VALUES ('.$id.')';
	else
		$query  = 'INSERT INTO active_jobs (job_id) VALUES (LAST_INSERT_ID())';
	
  	$result = mysqli_query($this->db_link, $query);
    	if ($result === false) {
    		$message  = 'Invalid query: ' . mysqli_error($this->db_link) . " query: " .$query. "\n";
    		throw new Exception($message);
	}
    }
    
    private function remove_active_job($id)
    {
	$query  = 'DELETE FROM active_jobs WHERE job_id = '.$id;
	
	$result = mysqli_query($this->db_link, $query);
    	if ($result === false) {
    		$message  = 'Invalid query: ' . mysqli_error($this->db_link) . " query: " .$query. "\n";
    		throw new Exception($message);
	}
    }
    
    private function remove_canceled_job($id)
    {
	$query  = 'DELETE FROM canceled_jobs WHERE job_id = '.$id;
	
	$result = mysqli_query($this->db_link, $query);
    	if ($result === false) {
    		$message  = 'Invalid query: ' . mysqli_error($this->db_link) . " query: " .$query. "\n";
    		throw new Exception($message);
	}
    }


}
?>
