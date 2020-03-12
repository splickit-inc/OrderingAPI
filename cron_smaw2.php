<?php
error_reporting(E_ERROR|E_COMPILE_ERROR|E_COMPILE_WARNING);
//error_reporting(E_ALL);
ini_set('display_errors','0');
ini_set('default_socket_timeout', 42);
$_SERVER['request_time1'] = microtime(true);
$_SERVER['CRON'] = 'true';
$file_path = '/usr/local/splickit/httpd/htdocs/prod/app2';
set_include_path(get_include_path() . PATH_SEPARATOR . $file_path);

// time zone will next be set for standard application functions in the functions.inc file below
include 'lib'.DIRECTORY_SEPARATOR.'utilities'.DIRECTORY_SEPARATOR.'functions.inc';

myerror_log("********** cron_smaw2.php ******* ".getTimeNowDenver()." ***********");
myerror_log("*******  check for any activity that needs to execute ***********");

$log_level = $global_properties['log_level_activity'];
$_SERVER['log_level'] = $log_level;

$activity_history_adapter = new ActivityHistoryAdapter($mimetypes);
$activity_history_adapter->doAllActivitiesReadyToBeExecuted();	

?>