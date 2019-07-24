# Opasnet-RTools

R-tools Server installation instructions

0. Briefing

R-tools Server is a program that handles and runs code written in R. It implements interface which enables
multiple clients to use its services and can be installed on Linux operating system (or likes, CentOS-tested).
Server acts at two different levels; interaction between clients is handled by web server but the code itself
is planned to be run by restricted shell user (for improved security). The web service puts code run requests
into the database, from where the script running in the restricted shell fetches them and runs R processes.

1. Installing R

R-tools Server uses local R installation to run the code. Install the R and desired libraries.
In CentOS this can be done by first installing the Epel-repository and then "yum install R".
This installs R-code and R-devel package.

2. Installing web service

R-tools Server interacts with MediaWiki extensions via XML-RPC protocol. Program is written in PHP thus
web server with PHP-interpreter is needed (e.g. Apache with PHP-support). R-tools Server also needs access
to MySQL database via PHP. Follow next steps to install (with default configuration):

- Copy R-tools Server files to folder under your web servers document root (e.g. /var/www/html/rtools_server)

- All new pending runs will be saved as files into "codes" folder. Chown this to be owned by your web server
  to allow PHP to write the code files (e.g. apache:apache).

- Run jobs are recommended to be ran by other system user than the web server's. Run outputs will be written
  into "runs" folder. Chown this to be owned by the restricted shell user (e.g. rtools:rtools).
  
- Check that the "offline" folder is NOT VISIBLE TO THE INTERNET. There is .htaccess file for the Apache
  to make it invisible. Check the server's configuration if this is not the case. This is for improved
  security!
  
- Change the ownership of the "logs" folder under the "offline" folder to the restricted shell user
  (e.g. rtools:rtools). This folder contains log files and user that runs the code needs to have write access.
  
- Create MySql database and user for the R-tools Server.
  
- Copy "config.dist.php" to "config.php" and open it with your favorite text editor. Fill in database
  host, base, user and password. Check other configuration to correspond to your system as well.
  
- XML-RPC server functionality can be tested with web agent. Point your browser to http://<server url>/index.php
  and you should see some XML-output (faultCode 105 etc.).
  
3. Running the code in restricted shell

It is advisable to run R code jobs by a restricted shell user. At least on CentOS this can be easily done by
first creating symbolic link "ln -s /bin/bash /opt/rbash" and then by creating a new system user "e.g. rtools"
which uses this "/opt/rbash" as its default shell. Restricted shell doesn't allow any programs to be run
outside the shell and also doesn't allow the user to leave its home folder. But we need to allow some
programs to be run and this can be done by creating "bin" folder and some symbolic links under the user
home (e.g. /home/rtools/bin). Example symbolic link setup on CentOS 6:

nohup -> /usr/bin/nohup
ps -> /bin/ps
R -> /usr/bin/R
rm -> /bin/rm
uname -> /bin/uname
grep -> /bin/grep
gs -> /usr/bin/gs
gzip -> /bin/gzip
zip -> /bin/zip
kill -> /bin/kill
ls -> /bin/ls
env -> /bin/env

After linking required programs we still need to link the actual script that runs the code (goes thru job queue). E.g.

/home/rtools/run_jobs.php -> /var/www/html/rtools_server/offline/run_jobs.php

Now you should be able to login as "rtools" and run "php run_jobs.php". If not then check the permissions once more.
If the script starts normally, then it begins to fetch jobs from the work queue (database) and runs them. This script
runs infinitely until any exception occurs, when it shuts itself down. Next chapter describes one way how to keep
"run_jobs.php" running.

4. Setting up a cron script to observe and run "run_jobs.php"

R-tools Server job executor "run_jobs.php" must be running all the time in order to run jobs from the database queue.
One way to assure this is to set up an observing parent process that restarts the "run_jobs.php" if it's not running
(has died). This can be done by following the next steps: 

- Make new symbolic link as alias to php executable (e.g. /home/rtools/bin/rtools-worker -> /usr/bin/php)
- Create a new script that works as an observer and keeps "run_jobs.php" running (e.g. /home/rtools/bin/start_rtools).

	#!/opt/rbash
	line=$(ps -A | grep 'rtools-worker')
	if [ -z "${line}" ]; then
	    echo "Starting Rtools Worker"
	    rtools-worker run_jobs.php &
	fi

- Set the above script to be run by "rtools" user cron. Use crontab and insert following lines to run it once per minute:

	SHELL=/opt/rbash
	PATH=/home/rtools/bin
	*       *       *       *       *       start_rtools

- We are done! Try "killall rtools-worker" and within a minute the script should be back up and running!

5. Additional configuration

OpansetUtils(Ext) package needs some configuration files to work properly. These files need to be in "offline" folder and are:

- opasnet.json
-- Passwords for Opasnet databases in JSON-format: {"opasnet_en":"some_secret", ...}

-html_safe_key
-- Just one line of text; secret key for authenticating HTML safe output (must match with Opasnet RTools-extension config)










  