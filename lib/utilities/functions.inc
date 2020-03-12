<?php
// create unique session for logging
$code = generateCode(7);
$_SERVER['STAMP'] = $code;
$_SERVER['RAW_STAMP'] = $code;

$path = $_SERVER['DOCUMENT_ROOT'] . '/app2/';
set_include_path(get_include_path() . PATH_SEPARATOR . $path);
$_SERVER['TOTAL_DB_TIME'] = 0.00;
//myerror_log("here we are in functions inc");
//set mime types
$mimetypes = array(
  'html' => 'text/html',
  'xml' => 'text/xml'
);

spl_autoload_register('myautoload');
require 'vendor/autoload.php';



$_SERVER['MIMETYPES'] = $mimetypes;

$high_alert_sms_nos = array('3038844083', '7208411460');

// set globals
$global_properties = loadProperties();
try {
  validateSystemPropertiesConfiguration();
} catch (Exception $e) {
  myerror_log("ERROR!!!!!! " . $e->getMessage());
  $db_info = DatabaseInfo::getDbInfo();
  myerror_log($db_info);
  $array = get_object_vars($db_info);
  logData($array, "dbinfo");
  SmsSender2::sendEngineeringAlert($e->getMessage());
  die($e->getMessage());
}

if ($_SERVER['HTTP_HOST'] == 'test.splickit.com' && $global_properties['server'] == 'prod') {
  myerror_log('server: ' . $global_properties['server']);
  myerror_log('host: ' . $_SERVER['HTTP_HOST']);
  myerror_log("ERRROR!  production properties file on test server");
  SmsSender2::sendAlertListSMS('PRODUCTION PROPERITES FILE ON TEST!');
  MailIt::sendErrorEmail('PRODUCTION PROPERITES FILE ON TEST!', 'PRODUCTION PROPERITES FILE ON TEST!');
  die("PRODUCTION PROPERITES FILE ON TEST!");
}

$log_level = getProperty('log_level');
if (substr_count($_SERVER['REQUEST_URI'], '/messagemanager/') > 0) {
  $log_level = getProperty('log_level_message_manager');
}

$_SERVER['log_level'] = $log_level;
myerror_logging(2, "we have just set the server log level to: " . $log_level);
if (isset($_SERVER['PHP_AUTH_USER']) && $_SERVER['PHP_AUTH_USER'] != null && $_SERVER['PHP_AUTH_USER'] == $global_properties['high_logging_user']) {
  myerror_log("we have a high_logging_user so setting log level to 6");
  $log_level = 6;
  $_SERVER['log_level'] = 6;
} else if ($_SERVER['HTTP_X_SPLICKIT_CLIENT_DEVICE'] == 'copperegg_testing') {
  $log_level = 0;
  $_SERVER['log_level'] = 0;
}

$file_adapter = new FileAdapter($mimetypes, 'resources');
$shell_output = shell_exec('uname -a');
myerror_logging(3, "about to do the prepend on the session stamp");

//create the full stamp
$prepend = getServerPrepend($shell_output);
$code = $prepend . '-' . $code;
$_SERVER['STAMP'] = $code;
$_SERVER['RAW_STAMP'] = $code;

//set tz to that of the server
myerror_logging(3, "about to get and set time zone stuff");
if (isset($global_properties['default_server_timezone'])) {
  //determine if we're in daylight savings
  date_default_timezone_set('America/Denver');
  $daylight_savings = date("I");
  $_SERVER['GLOBAL_PROPERTIES']['daylight_savings'] = $daylight_savings;
  $_SERVER['MOUNTAIN_TIME_HOUR'] = date('G');

  $tz = $global_properties['default_server_timezone'];
  $default_timezone = $global_properties['default_server_timezone'];

  myerror_logging(3, "about to set default TZ to: " . $tz);
  date_default_timezone_set($default_timezone);
}

// *******************  define global functions  *********************
function isSystemShutdown()
{
  return (getProperty('system_shutdown') == 'true');
}

function isOrderingShutdown()
{
  return (getProperty('ordering_shutdown') == 'true');
}


function getEnvironmentConfigurationProperties()
{
  // defaults first
  $txt = file_get_contents("./config/default.conf");
  $default_env_properties = json_decode($txt, true);
  if (count($default_env_properties) < 10) {
    myerror_log("ERROR! THERE IS S PROBLEM LOADEING THE default PROPERTIES. the size of the default properties is: " . count($default_env_properties));
  }

  // environment specific values
  $filename = "./config/" . getEnvironmentConfigFileName() . ".conf";
  $txt = file_get_contents($filename);
  $env_properties = json_decode($txt, true);
  if (count($env_properties) < 1) {
    myerror_log("ERROR! the filename for environment is: " . $filename);
    myerror_log("ERROR! THERE IS A PROBLEM LOADING THE environment PROPERTIES. the size of the environment properties is: " . count($env_properties));
  }
  return array_merge($default_env_properties, $env_properties);
}

function setGlobalProperties($db_properties, $env_properties)
{
  $db_properties = ($db_properties == null) ? array() : $db_properties;
  $temp_properties = array();
  $properties = array_merge($db_properties, $env_properties);
  if (isLaptop()) {
    $filename = "./config/local.conf";
    if ($txt = file_get_contents($filename)) {
      $local_properties = json_decode($txt, true);
      $properties = array_merge($properties, $local_properties);
    }
  }
  foreach ($properties as $property) {
    $property_name = $property['name'];
    $property_value = $property['value'];
    $temp_properties[$property_name] = $property_value;
  }
  if (isProd()) {
    $production_credentials = array();
    foreach (ThirdPartyProductionCredentialsAdapter::staticGetRecords(array(), 'ThirdPartyProductionCredentialsAdapter') as $credential_record) {
      $production_credentials[$credential_record['name']] = $credential_record['value'];
    }
    $temp_properties = array_merge($temp_properties, $production_credentials);
  }
  $_SERVER['GLOBAL_PROPERTIES'] = (isset($_SERVER['GLOBAL_PROPERTIES'])) ? $_SERVER['GLOBAL_PROPERTIES'] : array();
  $global_properties = array_merge($_SERVER['GLOBAL_PROPERTIES'], $temp_properties);
  $_SERVER['log_level'] = $global_properties['log_level'];
  $_SERVER['GLOBAL_PROPERTIES'] = $global_properties;
  logData($global_properties, "Global Properties", 6);
  return $global_properties;
}

function loadProperties()
{
  $property_adapter = new PropertyAdapter();
  $db_properties = $property_adapter->select('',array());
  if (count($db_properties) < 10) {
    myerror_log("ERROR! there was a problem loading the properties table. size of properties table is: " . count($db_properties));
  }
  $env_properties = getEnvironmentConfigurationProperties();
  return setGlobalProperties($db_properties, $env_properties);
}

function validateSystemPropertiesConfiguration()
{
  validateLoadedPropertiesTable($_SERVER['GLOBAL_PROPERTIES']);
  validateLoadedEnvironmentConfigurationFile($_SERVER['GLOBAL_PROPERTIES']);
  validateLoadedDefaultEnvironmentConfigurationFile($_SERVER['GLOBAL_PROPERTIES']);
  validateMatchedEnvironmentFileToServer();
  return true;
}

function toCurrency($amount)
{
  return number_format($amount, 2);
}

function validateMatchedEnvironmentFileToServer()
{
  if (getEnvironment() == $_SERVER['GLOBAL_PROPERTIES']['environment_file']) {
    return true;
  } else if (isUnitTest() && $_SERVER['GLOBAL_PROPERTIES']['environment_file'] == 'unit_test') {
      return true;
  }
  $server = getEnvironment();
  $file = $_SERVER['GLOBAL_PROPERTIES']['environment_file'];
  throw new SystemPropertiesConfigurationException($file, $server);
}

function validateLoadedPropertiesTable($global_properties)
{
  if (isset($global_properties["property_table_loaded"])) {
    return true;
  }
  throw new PropertiesTableNotLoadedException();
}

function validateLoadedDefaultEnvironmentConfigurationFile($global_properties)
{
  if (isset($global_properties["default_environment_file"]) && getPropertyLowerCase("default_environment_file") == "loaded") {
    return true;
  }
  throw new DefaultEnvironmentFileNotLoadedException();
}

function validateLoadedEnvironmentConfigurationFile($global_properties)
{
  if (isset($global_properties["environment_file"])) {
    return true;
  }
  throw new EnvironmentFileNotLoadedException();
}

function getServerPrepend($shell_output = '')
{
  if ($shell_output == '') {
    $shell_output = shell_exec('uname -a');
  }
  preg_match("/([a-zA-Z]{4}\d.*)\.splickit\.com/", $shell_output, $matches);
  if ($matches[0])
    return $matches[1];
  else if ($_SERVER['GLOBAL_PROPERTIES'] == 'laptop')
    return "AAAA09";
  myerror_log("we have an unrecognized server!  " . $shell_output);
  recordError("UNRECOGNIZED SERVER", $shell_output);
  return "UUUU09";
}

function getRequestingDevicesAppVersion()
{
  return $_SERVER['APP_VERSION'];
}

function getRequestingDevicesAppType()
{
  return $_SERVER['HTTP_X_SPLICKIT_CLIENT_DEVICE'];
}

/**
 *
 * @desc returns the 6 character server code (eg: pweb03,padm01,etc....)
 */
function getServerCode()
{
  $server_prepend = getServerPrepend();
  return substr($server_prepend, 0, 6);
}

/**
 *
 * @desc will get brand from current skin if its not already set and determin if loyalty is on for this context
 * @return boolean
 */
function isBrandLoyaltyOn()
{
  if (!isset($_SERVER['BRAND'])) {
    setBrandFromCurrentSkin();
  }
  return (isset($_SERVER['BRAND']) && isBrandLoyaltyOnByBrandRecord($_SERVER['BRAND']));
}

function isBrandLoyaltyOnByBrandRecord($brand_record)
{
  return $brand_record['loyalty'] == 'Y';
}

function setBrandFromBrandRecord($brand_record)
{
    $_SERVER['BRAND'] = $brand_record;
}

function setBrandFromCurrentSkin()
{
  if ($brand_id = $_SERVER['SKIN']['brand_id']) {
    $brand_adapter = new BrandAdapter();
    $brand_record = $brand_adapter->getRecord(array("brand_id" => $brand_id));
    setBrandFromBrandRecord($brand_record);
  } else {
    myerror_log("COULD NOT SET BRAND FROM CURRENT SKIN");
  }
}

function setSkinFromBrandId($brand_id)
{
  $skin_adapter = new SkinAdapter();
  if ($skin_record = $skin_adapter->getRecord(array("brand_id" => $brand_id))) {
    $_SERVER['SKIN'] = $skin_record;
  }
}

/**
 * @desc do not use any spaces in the context name
 */
function isContext($name)
{
  return getIdentifierNameFromContext() == strtolower($name);
}

function getBrandIdFromCurrentContext()
{
  if ($brand_id = $_SERVER['SKIN']['brand_id']) {
    return $brand_id;
  } else {
    myerror_log("brand is not set yet");
    return null;
  }
}

function getBrandForCurrentProcess()
{
  if (isset($_SERVER['BRAND'])) {
    return $_SERVER['BRAND'];
  } else {
    setBrandFromCurrentSkin();
    return $_SERVER['BRAND'];
  }
}

function getNameFromSkinExternalIdentifier($external_identifier)
{
    $s = explode(".", $external_identifier);
    $name = $s[2];
    return $name;
}

function getIdentifierNameFromContext()
{
  if ($external_identifier = getContext()) {
      return getNameFromSkinExternalIdentifier($external_identifier);
  } else {
    myerror_log("COULD NOT GET external_identifier FROM CURRENT CONTEXT");
    return null;
  }
}

function getContext()
{
  return $_SERVER['SKIN']['external_identifier'];
}

function getPublicClientIdForContext()
{
  return $_SERVER['SKIN']['public_client_id'];
}

function getSkinForContext()
{
  return $_SERVER['SKIN'];
}

function getSkinIdForContext()
{
  return $_SERVER['SKIN']['skin_id'];
}

function getSkinNameForContext()
{
  return $_SERVER['SKIN']['skin_name'];
}

function isLevelUpBroadcastContext()
{
  $skin_name = getSkinNameForContext();
  return substr($skin_name,-4) == '-lub';
}

function getBaseUrlForContext()
{
  $skin = getSkinForContext();
  $id_name = getIdentifierNameFromContext();
  if (isTest()) {
    $base_url = "https://$id_name.splickit-test.com";
  } else if (isUat()) {
    $base_url = "https://$id_name.splickit-uat.com";
  } else if (isDevelopment()) {
    $base_url = "https://$id_name.splickit-dev.com";
  } else if (validateThatStringFieldIsSetAndIsNotNullAndIsNotEmpty($skin, 'base_url')) {
    $base_url = $skin['base_url'];
  } else {
    $base_url = "https://$id_name.splickit.com";
  }
  return $base_url;
}

function isThisForRealAnUpdateQuestionMark()
{
  return ($_SERVER['REQUEST_METHOD'] == 'POST' || $_SERVER['REQUEST_METHOD'] == 'PUT');
}

function getEnvironmentFromEnvironmentVariable()
{
  if ($environment = getenv('SMAW_ENV')) {
    myerror_log("SMAW_ENV: we got the environment from the environment variable: $environment",5);
    $_SERVER['ENVIRONMENT'] = $environment;
    return $environment;
  } else {
      myerror_log("couldn't get env varibale, variable doesnt exist. here are whats loaded");
      foreach ($_ENV as $n=>$v) {
        myerror_log("$n = $v");
      }
      die("Could not get environment type from environment variable. variable doesnt exist");
  }
}

function getEnvironmentFromEnvironmentConfig()
{
  return getEnvironmentFromEnvironmentVariable();
}

function getEnvironment()
{
  if (isset($_SERVER['ENVIRONMENT'])) {
    return $_SERVER['ENVIRONMENT'];
  }
  return getEnvironmentFromEnvironmentVariable();
}

function getEnvironmentConfigFileName()
{
    $environment_name = getEnvironment();
    return $environment_name;
   // return ($environment_name == 'unit_test_ide') ? 'unit_test' : $environment_name;
}

/**
 * returns the current session stamp
 */
function getStamp()
{
  return $_SERVER['STAMP'];
}

function getRawStamp()
{
  if ($_SERVER['RAW_STAMP'])
    return $_SERVER['RAW_STAMP'];
  else
    return $_SERVER['STAMP'];
}

function generateAlphaCode($length)
{
  $alphabet_string = "abcdefghijklmnopqrstuvwxyz";
  return generateGenericCode($alphabet_string, $length);
}

function generateCode($length)
{
  $characters = 'abc1234def567890ghijk1234lm567nop890qrstuvwxyz';
  $characters = strtoupper($characters);
  return generateGenericCode($characters, $length);
}

function generateGenericCode($characters, $desired_length_of_generated_code)
{
  $top_end = strlen($characters) - 1;
  $code = '';
  for ($i = 0; $i < $desired_length_of_generated_code; $i++) {
    $new = mt_rand(0, $top_end);
    $code = $code . substr($characters, $new, 1);
  }
  return $code;

}

function generateUUID()
{
  $characters = 'abc1234def567890ghijk1234lm567nop890qrstuvwxyz';
  $code = '';
  for ($i = 0; $i < 15; $i++) {
    $new = mt_rand(0, 45);
    $code = $code . substr($characters, $new, 1);
    if ($i == 4 || $i == 9)
      $code = $code . '-';
  }
  $pref = mt_rand(1111, 9999);
  $code = $pref . '-' . $code;
  return $code;
}

function clearAuthenticatedUserParametersForSession()
{
	unset($_SERVER['AUTHENTICATED_USER']);
	unset($_SERVER['AUTHENTICATED_USER_ID']);
	unset($_SERVER['USER_IS_STORE_TESTER_OR_BETTER']);
    unset($_SERVER['USER_IS_PROD_TESTER']);
}

function setAuthenticatedUserParametersForSession($user)
{
  $_SERVER['HTTP_X_SPLICKIT_CLIENT_ID'] = getContext();
  $_SERVER['AUTHENTICATED_USER'] = $user;
  $_SERVER['AUTHENTICATED_USER_ID'] = $user['user_id'];
  if ($user['user_id'] != 1) {
    if (substr_count($_SERVER['STAMP'],$user['user_id'] == 0)) {
      $_SERVER['STAMP'] = $user['user_id'] . '-' . $_SERVER['STAMP'];
    }
  }

  if ($user['user_id'] == $_SERVER['GLOBAL_PROPERTIES']['high_logging_user']) {
    myerror_log("we have a high_logging_user so setting log level to 6");
    $log_level = 6;
    $_SERVER['log_level'] = 6;
  }

}


function isBrandedApp()
{
  return ($_SERVER['SKIN_TYPE'] == 'B');
}

function isAggregate()
{
  $skin = getSkinForContext();
  return ($_SERVER['SKIN_TYPE'] == 'A' || $skin['mobile_app_type'] == 'A');
}

function isNotProd()
{
  return !isProd();
}

function isProd()
{
  $env = getEnvironment();
  return ($env == 'production' || $env == 'prod');
}

function isUat()
{
  return (getEnvironment() == 'uat');
}

function isStaging()
{
  return (getEnvironment() == 'staging');
}

function isStagingTypeServer()
{
  return ((getEnvironment() == 'staging') || (getEnvironment() == 'development'));
}

function isTest()
{
  return (getEnvironment() == 'staging');
}

function isLaptop()
{
  $environment = getEnvironment();
  return ($environment == 'laptop' || $environment == 'unit_test' || $environment == 'unit_test_ide');
}

function isUnitTest()
{
    return (getEnvironment() == 'unit_test' || getEnvironment() == 'unit_test_ide');
}

function isLocalDevelopment()
{
  return isLaptop();
}

function isDevelopment()
{
  return (getEnvironment() == 'development');
}

function getEnvironmentShortNameForUrl()
{
  $string = '';
  if (isTest()) {
    $string = "-test";
  } else if (isDevelopment()) {
    $string = "-dev";
  } else if (isLaptop()) {
    $string = "-laptop";
  }
  return $string;
}

function getProperty($property_name)
{
  return $_SERVER['GLOBAL_PROPERTIES'][$property_name];
}

function getPropertyLowerCase($property_name)
{
  return strtolower(getProperty($property_name));
}

function setSessionProperty($property_name, $property_value)
{
  $_SERVER['GLOBAL_PROPERTIES'][$property_name] = $property_value;
}

function getBaseLogLevel()
{
  $log_level = (isset($_SERVER["log_level"])) ? $_SERVER["log_level"] : 0;
  return $log_level;
}

function setLogLevelForObjectNameIfExists($name)
{
  if ($custom_log_level = getProperty(strtolower($name) . "_log_level")) {
    myerror_log("We have a custom object log level of $custom_log_level");
    $log_level = getBaseLogLevel();
    if ($custom_log_level > $log_level) {
      myerror_log("we are setting to a custom log level of $custom_log_level");
      $log_level = $custom_log_level;
      $_SERVER['log_level'] = $log_level;
    } else {
      myerror_log("NOT settting to a custom log level because   $log_level  !<  $custom_log_level");
    }
  }
}

function setProperty($property_name, $value, $create = false)
{
  $_SERVER['GLOBAL_PROPERTIES'][$property_name] = $value;
  $property_adapter = new PropertyAdapter();
  $property_data['name'] = $property_name;
  $options[TONIC_FIND_BY_METADATA] = $property_data;
  if ($property_resource = Resource::findExact($property_adapter, null, $options)) {
    $property_resource->value = $value;
    $property_resource->modified = time();
    if ($property_resource->save())
      return true;
    else {
      myerror_log("ERROR updating property $property_name  to  $value.  mysql_error: " . $property_adapter->getLastErrorText());
      return false;
    }
  } else if ($create) {
    return is_a('Resource', Resource::createByData($property_adapter, array("name" => $property_name, "value" => $value)));
  } else {
    myerror_log("ERROR updating property $property_name  to  $value.  Could not find property");
    return false;
  }
}

function getElapsedTimeFormatted($time1)
{
  $now = microtime(true);
  $elapsed_time = $now - $time1;
  $formatted_elapsed_time = number_format($elapsed_time, 3);
  return $formatted_elapsed_time;
}

function getRequestElapsedTimeFormatted()
{
  if (isset($_SERVER['request_time1'])) {
    return getElapsedTimeFormatted($_SERVER['request_time1']);
  }
  return 0;
}

function cleanPasswordFromBody($body)
{
  $pattern = '/"password":"(.{2,20})","/';
  if (substr_count($body, '%22password')) {
    $pattern = '/%22password%22%3A%22(.{2,20})%22%2C%22/';
  }
  $matches = array();
  if (preg_match($pattern, $body, $matches)) {
    $password = $matches[1];
    $clean_body = preg_replace("/$password/", 'xxxxxxxxxx', $body);
    return $clean_body;
  } else {
    return $body;
  }
}

function cleanCCNumberIfExists($full_message)
{
  $pattern = '/([0-9]{1})([0-9]{11})([0-9]{4})/';
  $matches = array();
  if (preg_match($pattern, $full_message, $matches)) {
    $full_message = preg_replace('/([0-9]){16}/', $matches[1] . 'xxxxxxxxxxx' . $matches[3], $full_message);
    $full_message = preg_replace('/(cvv":"[0-9]{3})/', 'cvv":"xxx', $full_message);
  }
  return $full_message;
}

/*
 *
 */
function getEndpointVersion($request)
{
  $version = 1;

  if (substr_count($request->url, '/v2/') > 0 || substr_count($request->url, 'apiv2/') > 0) {
    $version = 2;
  }

  return $version;
}

function myerror_log($message, $level = 0)
{
  $server_log_level = isset($_SERVER['log_level']) ? $_SERVER['log_level'] : 0;
  if ($server_log_level >= $level) {
    $full_message = $_SERVER['STAMP'] . ':' . $message . ' ---  request elapsed time: ' . getRequestElapsedTimeFormatted();
    $dftz = $_SERVER['GLOBAL_PROPERTIES']['default_server_timezone'];
    $tz = date_default_timezone_get();
    if ($dftz != $tz) {
      date_default_timezone_set($dftz);
      error_log($full_message);
      date_default_timezone_set($tz);
    } else {
      error_log($full_message);
    }
  }
}

function myerror_logging($level, $message)
{
  if (isset($_SERVER['log_level'])) {
    if ($_SERVER['log_level'] >= $level) {
      myerror_log($message);
    }
  }
}

/**
 *
 * @desc takes an array and write to the log file the name = value
 * @param $data
 */
function logData($data, $title, $messsage_log_level = 0)
{
  if ((isset($_SERVER['log_level']) && $_SERVER['log_level'] < $messsage_log_level) || !isset($_SERVER['log_level'])) {
    return;
  }
  if ($title) {
    myerror_log("******** start $title data ********");
  }
  if ($data == null) {
    myerror_log("data is null");
    myerror_log("******** end $title data ********");
    return;
  }
  if (is_array($data)) {
    foreach ($data as $name => $value) {
      if (is_array($value)) {
        myerror_log("$name = ");
        logData($value, $name, $messsage_log_level);
      } else if (is_string($value)) {
        myerror_log("$name = $value");
      } else {
        $to_string = print_r($value, true);
        myerror_log("$name = " . $to_string);
      }
    }
  } else {
    myerror_log("Submitted object to logData is not an array.");
    return;
  }

  if ($title) {
    myerror_log("******** end $title data ********");
  }
}

/**
 *
 * @desc will log the fields of a resource (will not recurse)
 * @param Resource $resource
 * @param string $title
 * @param int $log_level
 */
function logResource($resource, $title, $log_level = 0)
{
  $data = $resource->getDataFieldsReally();
  logData($data, $title, $log_level);
}

function getSplickitDateTimeObjectFromMerchant($merchant)
{
    return new DateTime('now',new DateTimeZone(getTheTimeZoneStringFromOffset($merchant['time_zone'],$merchant['state'])));
}

function getTheTimeZoneStringFromOffset($time_zone, $state = 'XX')
{
  if ($state == 'AZ')
    $time_zone_string = 'America/Phoenix';
  else if ($time_zone == -5)
    $time_zone_string = 'America/New_York';
  else if ($time_zone == -6)
    $time_zone_string = 'America/Chicago';
  else if ($time_zone == -7)
    $time_zone_string = 'America/Denver';
  else if ($time_zone == -8)
    $time_zone_string = 'America/Los_Angeles';
  else if ($time_zone == -9)
    $time_zone_string = 'America/Anchorage';
  else if ($time_zone == -10)
    $time_zone_string = 'America/Adak';
  return $time_zone_string;
}

function getCurrentOffsetForTimeZone($time_zone_string)
{
  $tz = new DateTimeZone($time_zone_string);
  $date = new DateTime('now', $tz);

  $offset_seconds = $tz->getOffset($date);
  $offset_hours = $offset_seconds / 3600;
  return $offset_hours;
}

function setTheDefaultTimeZone($time_zone, $state = 'XX')
{
  $time_zone_string = getTheTimeZoneStringFromOffset($time_zone, $state);
  setDefaultTimeZoneFromString($time_zone_string);
}

function setDefaultTimeZoneFromString($time_zone_string)
{
  $current_time_zone = date_default_timezone_get();
  if ($time_zone_string != $current_time_zone) {
    myerror_logging(3, "setting time zone to: $time_zone_string,  from: " . $current_time_zone);
    date_default_timezone_set($time_zone_string);
  } else {
    myerror_logging(3, "no need to set timezone, its already set correctly");
  }
}

function myautoload($class_name)
{
  $class_name_lower = strtolower($class_name);

  myerror_log("getting class: " . $class_name_lower,3);

  if ($class_name_lower == 'request' || $class_name_lower == 'resource' || $class_name_lower == 'response' || $class_name_lower == 'smartyresource')
    require_once 'tonic' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . $class_name_lower . '.php';
  else if ($class_name_lower == 'fileadapter' || $class_name_lower == 'sqladapter' || $class_name_lower == 'mysqladapter' || $class_name_lower == 'adapter')
    require_once 'tonic' . DIRECTORY_SEPARATOR . 'adapters' . DIRECTORY_SEPARATOR . $class_name_lower . '.php';
  else if (substr_count($class_name_lower, "adapter"))
    require_once "lib" . DIRECTORY_SEPARATOR . "adapters" . DIRECTORY_SEPARATOR . $class_name_lower . ".php";
  else if (substr_count($class_name_lower, "controller"))
    require_once "lib" . DIRECTORY_SEPARATOR . "controllers" . DIRECTORY_SEPARATOR . $class_name_lower . ".php";
  else if (substr_count($class_name_lower, "activity"))
    require_once "lib" . DIRECTORY_SEPARATOR . "activities" . DIRECTORY_SEPARATOR . $class_name_lower . ".php";
  else if (substr_count($class_name_lower, "curl"))
    require_once "lib" . DIRECTORY_SEPARATOR . "curl_objects" . DIRECTORY_SEPARATOR . $class_name_lower . ".php";
  else if (substr_count($class_name_lower, "payment"))
    include_once "lib" . DIRECTORY_SEPARATOR . "payments" . DIRECTORY_SEPARATOR . $class_name_lower . ".php";
  else if (substr_count($class_name_lower, "processor"))
    require_once "lib" . DIRECTORY_SEPARATOR . "payments" . DIRECTORY_SEPARATOR . "processors" . DIRECTORY_SEPARATOR . $class_name_lower . ".php";
  else if (substr_count($class_name_lower, "service"))
    require_once "lib" . DIRECTORY_SEPARATOR . "services" . DIRECTORY_SEPARATOR . $class_name_lower . ".php";
  else if (substr_count($class_name_lower, "manager"))
    require_once "lib" . DIRECTORY_SEPARATOR . "managers" . DIRECTORY_SEPARATOR . $class_name_lower . ".php";
  else if (substr_count($class_name_lower, "complete"))
    require_once "lib" . DIRECTORY_SEPARATOR . "complex_objects" . DIRECTORY_SEPARATOR . $class_name_lower . ".php";
  else if (substr_count($class_name_lower, "importer"))
    require_once "lib" . DIRECTORY_SEPARATOR . "importers" . DIRECTORY_SEPARATOR . $class_name_lower . ".php";
  else if (substr_count($class_name_lower, "smarty")) {
    return;
    //require_once "smarty" . DIRECTORY_SEPARATOR . "libs" . DIRECTORY_SEPARATOR . $class_name . ".class.php";
  } else {
    $path = "lib" . DIRECTORY_SEPARATOR . "utilities" . DIRECTORY_SEPARATOR . $class_name_lower . ".php";
    myerror_logging(5, "getting class from: " . $path);
    if (file_exists($path)) {
        include_once $path;
    }
  }
}

/**
 * @desc  to return a json resource from data for reply
 */

function returnJsonResource($data)
{
  $jsonString = json_encode($data);
  $resource = Resource::dummyfactory(array('json' => $jsonString));
  $resource->_representation = '/json.xml';
  return $resource;
}

//function createAPIV2ErrorResourceWithHttpCode($error_message, $http_code, $error_code, $error_type,$data = array())
//{
//  $return_error_array = array("http_code"=>$http_code,"stamp"=>getStamp(),"error"=>array("error"=>$error_message,"error_code"=>$error_code,"error_type"=>$error_type),"data"=>$data);
//  $resource = Resource::dummyFactory($return_error_array);
//  return $resource;
//}

function createErrorResourceWithHttpCode($error_message, $http_code, $error_code, $error_data = array())
{
  if ($error_data == null || (!is_array($error_data))) {
    $error_data = array();
  }
  myerror_log("creating an error resource with message: ".$error_message);
  return returnErrorResource($error_message, $error_code, array_merge($error_data, array("http_code" => $http_code)));
}

function returnCleanErrorResource($resource)
{
  $error_message = isset($resource->error) ? $resource->error : 'Unknown Error';
  $error_code = isset($resource->error_code) ? $resource->error_code : '999';
  if (isset($resource->http_code)) {
    $data['http_code'] = $resource->http_code;
  }
  if (isset($resource->error_type)) {
    $data['error_type'] = $resource->error_type;
  }
  myerror_log("creating an error response with message: ".$error_message);
  return returnErrorResource($error_message, $error_code, $data);
}

/**
 *
 * @desc to create an error resource to return to the requesting entity
 * @param string $error_message
 * @param int $error_code
 * @param array $error_data
 * @return Resource
 */
function returnErrorResource($error_message, $error_code = 999, $error_data = null)
{
  myerror_log("we are about to build the error resource with a message of: " . $error_message);
  if (is_a($error_data, "Resource")) {
    $resource = clone $error_data;
    $resource->set("error_code", $error_code);
    $resource->set('error', $error_message);
  } else {
    $error_data['error'] = $error_message;
    $error_data['error_code'] = $error_code;
    $resource = Resource::dummyFactory($error_data);
  }
  $resource->_representation = '/json.xml';
  return $resource;
}

function setHttpCodeOnResource(&$resource, $http_code)
{
  $resource->set('http_code', $http_code);
  return $resource;
}

/**
 *
 * @desc will set the error fields on an existing resource and return boolean false
 * @param Resource $resource
 * @param string $error_message
 * @param int $error_code
 */
function setErrorOnResourceReturnFalse(&$resource, $error_message, $error_code)
{
  setErrorOnResourceReturnResource($resource, $error_message, $error_code);
  return false;
}

function setErrorOnResourceReturnResource(&$resource, $error_message, $error_code)
{
  myerror_log("Setting error on resource: " . $error_message . "    code: " . $error_code);
  $resource->set('error_code', $error_code);
  $resource->set('error', $error_message);
  if ($error_code < 100 && $error_code > 0) {
    $resource->set('http_code', 422);
  }
  return $resource;
}

function createXmlFromHashMap($array)
{
  $xml_string = '';
  if (is_array($array)) {
    foreach ($array as $key => &$arrayElement) {
      $key = htmlspecialchars($key);
      if (is_array($arrayElement)) {
        if (is_numeric($key)) {
          $key = 'index_' . $key;
        }
        $xml_string .= "<$key>" . createXmlFromHashMap($arrayElement) . "</$key>";
      } else {
        $val = htmlspecialchars($arrayElement);
        if (is_numeric($key)) {
          $key = 'index_' . $key;
        }
        $xml_string .= "<$key>$val</$key>";
      }
    }
  }
  if ($xml_string == '') {
      return null;
  }
  return $xml_string;
}

function recursiveRemoval(&$array, $val)
{
  if (is_array($array)) {
    foreach ($array as $key => &$arrayElement) {
      if (is_array($arrayElement)) {
        recursiveRemoval($arrayElement, $val);
      } else {
        if ($key === $val) {
          unset($array[$key]);
        }
      }
    }
  }
}

/**
 * @desc will put the error in the db to be emailed out.  if the error in m3 is on the ignore list, or m3==record error, an email wont be sent.
 * @param MySQLErrorString $m3 The actual error text
 * @param $m4  any other text to be recorded
 */

function recordError($m3, $m4)
{
  if (isErrorOnIgnoreList($m3)) {
    return logError('', $m3, $m4);
  }
  return ErrorsAdapter::createNewErrorRecord("EMAIL ERROR", null, $m3, $m4);
}

function saveErrorForEndOfDayReport($m3, $m4)
{
  return ErrorsAdapter::createNewErrorRecord("REPORT ERROR", null, $m3, $m4);
}

function logError($m2, $m3, $m4)
{
  return ErrorsAdapter::createNewErrorRecord("LOG ERROR", $m2, $m3, $m4);
}

function isErrorOnIgnoreList($error)
{
  if (substr_count(strtolower($error), 'duplicate entry') > 0 && substr_count(strtolower($error), "for key") > 0) {
    myerror_logging(3, "duplicate key error, so DO NOT send alert");
    return true;
  }
  return false;
}

/*
 * tareks function
 */
function insert_header($params)
{
  // this function expects $content argument
  if (empty($params['content'])) {
    return;
  }
  header($params['content']);
  return;
}

function getFileAdapter()
{
  return new FileAdapter(getMimetypes(), 'resources');
}

function getMimetypes()
{
  $mimetypes = array(
    'html' => 'text/html',
    'xml' => 'text/xml'
  );
  return $mimetypes;
}

function getResourceBody($resource)
{
  if ($representation =& $resource->loadRepresentation(getFileAdapter())) {
    $body = $representation->_getContent();
  } else {
    MailIt::sendErrorEmail("ERROR creating body of resource. COULD NOT CREATE REPRESENTATION", "COULD NOT CREATE REPRESENTATION: " . debug_print_backtrace());
  }
  return $body;
}

function cleanAllNonNumericCharactersFromString($string)
{
	return preg_replace("/[^0-9]/", '', $string);
}

/**
 *
 * @desc takes an array of characters to remove from a string and removes them, returning the string
 * @param Array $array_of_characters_to_remove
 * @param String $string
 * @return String
 */
function cleanString($array_of_characters_to_remove, $string)
{
  foreach ($array_of_characters_to_remove as $char) {
    $string = str_replace($char, '', $string);
  }
  return $string;
}

/**
 *
 * @desc takes a string that is formatted with tabs, carriage returns, and line feeds and removes them
 * @param String $xml
 * @return String (xml as a single line)
 */
function cleanUpXML($xml)
{
  myerror_logging(6, "$xml");
  $snip = str_replace("\t", '', $xml); // remove tabs
  $snip = str_replace("\n", '', $snip); // remove new lines
  $snip = str_replace("\r", '', $snip); // remove carriage returns
  while (substr_count($snip, ' "') > 0) {
    $snip = str_replace(' "', '"', $snip);
  }
  myerror_logging(6, "$snip");
  return $snip;
}

/**
 *
 * @desc takes a string that is formatted with tabs, carriage returns, and line feeds and removes them
 * @param String $xml
 * @return String (xml as a single line)
 */
function cleanUpCRLFTFromString($string)
{
  return cleanUpXML($string);
}

function cleanUpDoubleSpacesCRLFTFromString($string)
{
  $clean_string = cleanUpCRLFTFromString($string);
  return str_replace("  ", '', $clean_string); // remove spaces
}

/**
 *
 * @desc takes a string and determines if it a string list.  If the delimeter is not passed in, it tests for these delimeters in this order (semicolon,colon,comma)
 * @desc returns an array of the list items if it is a string list, returns false if it is not a string list
 * @param unknown_type $string
 */
function isStringList($string, $delimiter = 'X')
{
  if ($delimiter != 'X')
    return explode($delimiter, $string);
  else {
    if (substr_count($string, ';') > 0)
      return explode(';', $string);
    else if (substr_count($string, ':') > 0)
      return explode(':', $string);
    else if (substr_count($string, ',') > 0)
      return explode(',', $string);
    else
      return false;
  }
}

//  SOME LOGGING STUFF
logData($_REQUEST, 'Request', 6);
logData($_SERVER, 'Server', 6);
logData($_POST, 'Post', 6);
logData($_GET, 'Get', 6);

function cmpCartSavingsHighestToLowest($a, $b)
{
  return $b['savings'] - $a['savings'];
}

function cmpCartPointsHighestToLowest($a, $b)
{
  return $b['points'] - $a['points'];
}

function ifLogLevelIsGreaterThan($level)
{
  return ($_SERVER['log_level'] > $level);
}

function getTimeStringForUnixTimeStampInMerchantLocal($format_string, $time_stamp, $merchant)
{
  $date = getDateObjectWithMerchantTimezoneSet($time_stamp, $merchant);
  return $date->format($format_string);
}

function getDateObjectWithMerchantTimezoneSet($time_stamp, $merchant)
{
  $date = new DateTime();
  $date->setTimezone(new DateTimeZone(getTheTimeZoneStringFromOffset($merchant['time_zone'], $merchant['state'])));
  $date->setTimestamp($time_stamp);
  return $date;
  $order_resource->requested_delivery_time = $date->format("D g:i A");
}

function getTimeStampForDateTimeAndTimeZone($hour, $min, $second, $month, $day, $year, $time_zone)
{
  $tz = date_default_timezone_get();
  date_default_timezone_set($time_zone);
  $time_stamp = mktime($hour, $min, $second, $month, $day, $year);
  date_default_timezone_set($tz);
  return $time_stamp;
}

/**
 * @desc will return 'Y-m-d H:i:s' for timestamp and time zone
 * @param unknown_type $time_stamp
 * @param unknown_type $time_zone_string
 * @return string
 */
function getMySqlFormattedDateTimeFromTimeStampAndTimeZone($time_stamp, $time_zone_string)
{
  $tz = date_default_timezone_get();
  date_default_timezone_set($time_zone_string);
  $mysql_formatted_date_time_string = date("Y-m-d H:i:s", $time_stamp);
  date_default_timezone_set($tz);
  return $mysql_formatted_date_time_string;
}

/**
 *
 * @desc will return a string formatted like this "Tuesday February 12, 2013, 10:40 am"
 * @param int $time_stamp
 */
function getFullyFormattedDateAndTimeStringForTimeStamp($time_stamp)
{
  return date("l F j, Y, g:i a", $time_stamp);
}

function formatGoodTenDigitPhoneNumber($phone_number)
{
  return substr($phone_number,0,3).'-'.substr($phone_number,3,3).'-'.substr($phone_number,6,4);
}

function parseXMLintoLowercaseHashmap($xml_as_string)
{
  $array = parseXMLintoHashmap($xml_as_string);
  return createLowercaseHashmapFromMixedHashmap($array);

}

function parseXMLintoHashmap($xml_as_string)
{
  $xml = new SimpleXMLElement($xml_as_string);
  return myToArray($xml);
}

function createLowercaseHashmapFromMixedHashmap($hashmap)
{
  $array = array();
  foreach ($hashmap as $key => $value) {
    if (is_array($value)) {
      $array[strtolower($key)] = createLowercaseHashmapFromMixedHashmap($value);
    } else {
      $array[strtolower($key)] = $value;
    }
  }
  return $array;
}

/**
 *
 * @desc parses XML into an array
 * @param SimpleXMLElement $xml
 * @return Hashmap
 */

function myToArray($xml)
{
  $array = (array)$xml;

  foreach (array_slice($array, 0) as $key => $value) {
    if ($value instanceof SimpleXMLElement) {
      $array[$key] = empty($value) ? NULL : myToArray($value);
    } else if (is_array($value)) {
      foreach ($value as $sub_key => $sub_value) {
        $array[$key][$sub_key] = myToArray($sub_value);
      }
    }
  }
  return $array;
}

function getTimeNowDenver()
{
  $tz = date_default_timezone_get();
  date_default_timezone_set("America/Denver");
  $time_string = date("H:i");
  date_default_timezone_set($tz);
  return $time_string;
}

function getTimeStamp24HoursFromNow()
{
  return getTimeStampDaysFromNow(1);
}

function getTimeStampDaysFromNow($days)
{
  return getTimeStampHoursFromNow($days * 24);
}

function getTimeStampHoursFromNow($hours)
{
  return getTimeStampMinutesFromNow($hours * 60);
}

function getTimeStampMinutesFromNow($minutes)
{
  return getTimeStampSecondsFromNow($minutes * 60);
}

function getTimeStampSecondsFromNow($seconds)
{
  return time() + $seconds;
}

function getProtocolDomain()
{
  return getProperty('protocol_domain');
}

function getNowSubThisManyMinutes($min)
{
   $the_time = time() - (60 * $min);
  return $the_time;
}

function getLoggedInUser()
{
  return $_SERVER['AUTHENTICATED_USER'];
}

function getLoggedInUserUUId()
{
  if ($user = getLoggedInUser()) {
    return $user['uuid'];
  }
  return null;
}

function getLoggedInUserId()
{
  if ($user = getLoggedInUser()) {
    return $user['user_id'];
  }
  return null;
}

function isARegularUser($user_id)
{
  return ($user_id > 19999);
}

/**
 *
 * @desc currently returns true for user_id > 9998  and the qa_tester (user_id == 6)
 * @param int $user_id
 * @return boolean
 */
function shouldSystemProcessAsRegularUser($user_id)
{
  return ($user_id > 19999 || $user_id == 9999 || $user_id == 6);
}

function isLoggedInUserARegularUser()
{
  return isUserIdARegularUserId($_SERVER['AUTHENTICATED_USER']['user_id']);
}

function isUserIdARegularUserId($user_id)
{
  return ($user_id > 19999);
}

function checkUserIdAndSetAdminFields($user_id)
{
  if (isUserProdTesterLevel($user_id)) {
    $_SERVER['USER_IS_PROD_TESTER'] = true;
    $_SERVER['USER_IS_STORE_TESTER_OR_BETTER'] = true;
  } else if (isUserStoreTesterLevelOrBetter($user_id)) {
    $_SERVER['USER_IS_STORE_TESTER_OR_BETTER'] = true;
  } else {
    unset($_SERVER['USER_IS_PROD_TESTER']);
    unset($_SERVER['USER_IS_STORE_TESTER_OR_BETTER']);
  }
}

function isUsernameABypassUsername($user_name)
{
  return ($user_name == 'prod_tester@dummy.com' || $user_name == 'store_tester@dummy.com');
}

function isUsernameNotABypassUsername($user_name)
{
  return !isUsernameABypassUsername($user_name);
}

function isLoggedInUserStoreTesterLevelOrBetter()
{
  return (isset($_SERVER['USER_IS_STORE_TESTER_OR_BETTER']) && $_SERVER['USER_IS_STORE_TESTER_OR_BETTER']);
}

function isLoggedInUserProdTester()
{
  return (isset($_SERVER['USER_IS_PROD_TESTER']) && $_SERVER['USER_IS_PROD_TESTER']);
}

function isUserStoreTesterLevelOrBetter($user_id)
{
  return ($user_id > 0 && $user_id < 200);
}

function isUserProdTesterLevel($user_id)
{
  return ($user_id > 0 && $user_id < 100);
}

function isLoggedInUserATempUser()
{
  return isUserATempUser($_SERVER['AUTHENTICATED_USER']);
}

function isUserResourceATempUser($user_resource)
{
  return isEmailASplickitTempUserEmail($user_resource->email);
}

function isUserAGuest($user)
{
    return doFlagsIndicateGuestUser($user['flags']);
}

function isUserResourceAGuest($user_resource)
{
    return doFlagsIndicateGuestUser($user_resource->flags);
}

function doFlagsIndicateGuestUser($flags)
{
    return doesFlagPositionNEqualX($flags,9,'2');
}

function isUserATempUserByUserId($user_id)
{
  $user_adapter = new UserAdapter();
  if ($user = $user_adapter->getRecordFromPrimaryKey($user_id)) {
    return isUserATempUser($user);
  } else {
    throw new Exception("User Id does not exist");
  }
}

function isLoggedInUserTheAdmin()
{
  return (getLoggedInUserId() == 1);
}

function isUserATempUser($user)
{
  return isEmailASplickitTempUserEmail($user['email']);
}

function isEmailASplickitTempUserEmail($email)
{
  return (substr($email, -13) == '@splickit.dum');
}

function areStringsEqualCaseInsentive($s1, $s2)
{
  return strtolower($s1) == strtolower($s2);
}

function validateThatStringFieldIsSetAndIsNotNullAndIsNotEmpty($data, $field_name)
{
  if (is_a($data, 'Resource')) {
    return validateThatStringFieldIsSetAndIsNotNullAndIsNotEmptyOnResource($data, $field_name);
  }
  return (isset($data["$field_name"]) && $data["$field_name"] != null && trim($data["$field_name"]) != '');
}

function validateThatStringFieldIsSetAndIsNotNullAndIsNotEmptyByIndex($data, $index)
{
  return (isset($data[$index]) && $data[$index] != null && trim($data[$index]) != '');
}

function validateThatStringFieldIsSetAndIsNotNullAndIsNotEmptyOnResource($data, $field_name)
{
    return (isset($data->$field_name) && $data->$field_name != null && trim($data->$field_name) != '');
}

function validateThatStringFieldIsSetAndIsNotNullAndIsNotEmptyOnArray($data, $field_name)
{
    return (isset($data["$field_name"]) && $data["$field_name"] != null && trim($data["$field_name"]) != '');
}

function isStringFieldNullOrEmptyOnArray($data, $field_name)
{
    return  validateThatStringFieldIsSetAndIsNotNullAndIsNotEmptyOnArray($data, $field_name) == false;
}

function removeCarriageReturnsTabsLineFeedsFromString($xml)
{
  myerror_log("$xml",5);
  $snip = str_replace("\t", '', $xml); // remove tabs
  $snip = str_replace("\n", '', $snip); // remove new lines
  $xml = str_replace("\r", '', $snip); // remove carriage returns
    myerror_log("$xml",5);
  return $xml;

}

function createResourceReturnCleanArray($adapter,$data)
{
    $resource = Resource::createByData($adapter,$data);
    $array = $resource->getDataFieldsReally();
    return cleanDataForResponse($array);
}

/**
 *
 * @desc removes created,modified,logical delete fields from a data set.
 * @param hash $data
 */
function cleanData(&$data)
{
  recursiveRemoval($data, 'created');
  recursiveRemoval($data, 'modified');
  recursiveRemoval($data, 'logical_delete');
  return $data;
}

function cleanDataForResponse(&$data)
{
  $data = cleanData($data);
  recursiveRemoval($data, '_exists');
  recursiveRemoval($data, '_adapter');
  recursiveRemoval($data, '_representation');
  recursiveRemoval($data, 'class');
  recursiveRemoval($data, 'mimetype');
  return $data;
}

/**
 *
 * @desc given order data will determin if the price records are by merchant id or by default mercahnt id of 0
 * @param hash map $order_data
 * @return int $merchant_id or '0'
 */
function getPriceRecordMerchantIdFromOrderData($order_data)
{
  $menu_type = (substr(strtoupper($order_data['delivery']), 0, 1) == 'Y') ? 'delivery' : 'pickup';
  $menu_id = MerchantMenuMapAdapter::getMenuIdFromMerchantIdAndType($order_data['merchant_id'], $menu_type);
  $menu_version = MenuAdapter::getMenuVersion($menu_id);
  if ($menu_version == 2.0) {
    $price_record_merchant_id = "0";
  } else {
    $price_record_merchant_id = $order_data['merchant_id'];
  }
  return $price_record_merchant_id;
}

function isOrderDataForDelivery($order_data)
{
  return (strtolower($order_data['delivery']) == 'yes' || strtolower($order_data['delivery']) == 'y' || (isset($order_data['user_addr_id']) && $order_data['user_addr_id'] > 1000));
}

function generateUniqueID($prefix)
{
  $uid = $prefix . str_shuffle(uniqid('', true));
  return str_replace(".", "", $uid);
}

function getRecordFromArrayOfArraysFromFieldAndValueOfSubArray($array, $field, $value)
{
  foreach ($array as $record) {
    if ($record[$field] == $value) {
      return $record;
    }
  }
}

/**
 *
 * @desc will take a hashmap and return it as a name value pair string.  Default separator is '|'
 * @param Hashmap $hashmap
 * @param char $separator
 * @return string;
 */
function createNameValuePairStringFromHashMap($hashmap, $separator = '|')
{
  if (is_array($hashmap)) {
      $hash_as_name_value_pair_string = '';
    foreach ($hashmap as $name => $value) {
      $hash_as_name_value_pair_string .= "$name=$value" . $separator;
    }
    return substr($hash_as_name_value_pair_string, 0, -1);
  }
}

/**
 *
 * @desc will take an array of hashmaps and convert the top level into hash map using the field name as the 'name'
 * @param unknown_type $input_array
 * @param unknown_type $field_name
 */
function createHashmapFromArrayOfArraysByFieldName($input_array, $field_name)
{
  $hash_map = array();
  foreach ($input_array as $record) {
    $hash_map[$record[$field_name]] = $record;
  }
  return $hash_map;
}

function createHashmapFromArrayOfResourcesByFieldName($input_array, $field_name)
{
  $hash_map = array();
  foreach ($input_array as $resource) {
    $hash_map[$resource->$field_name] = $resource;
  }
  return $hash_map;
}

/**
 * @desc search in array all elements and remove if one element on field value is duplicated
 * @param $array initial values
 * @param $field field name on array
 * @return mixed array with unique values acording field value
 */
function getUniqueElementsFromArrayByFieldValue($array, $field){

  $tmp = array();
  foreach($array as $k => $v)
    $tmp[$k] = is_array($v)? $v[$field] : $v->$field;

  $tmp = array_unique($tmp);


  foreach($array as $k => $v)
  {
    if (!array_key_exists($k, $tmp))
      unset($array[$k]);
  }

  return $array;
}

/**
 * @desc takes a Resource and converts it into json string. if an error is present it will convert it to the correct fields
 * @param Resource $resource
 * @return string
 */
function convertResourceToJsonResponseBody($resource)
{
  if (isset($resource->error)) {
    $data_fields = convertErrorResourceToErrorHashWithOnlyErrorFieldsContained($resource);
  } else {
    $data_fields = Resource::encodeResourceIntoJsonPrepArray($resource);
  }
  $json_string = json_encode($data_fields);
  return $json_string;
}

/**
 *
 * @desc converts an error resource into the standard jason format for errors
 * @param Resource $resource
 * @return hashmap
 */
function convertErrorResourceToErrorHashWithOnlyErrorFieldsContained($resource)
{
  $data_fields = array('ERROR' => $resource->error,
    'ERROR_CODE' => $resource->error_code,
    'TEXT_TITLE' => $resource->text_title,
    'TEXT_FOR_BUTTON' => $resource->text_for_button,
    'FATAL' => $resource->fatal,
    'URL' => $resource->url,
    'stamp' => $resource->stamp
  );
  return $data_fields;
}

function getAndRespondWithAPIDocs()
{
  if (isTest()) {
    header("location: http://www.splickit.com/developers/api-documentation");
    die();
  } else {
    if ($output = getAPIDocs()) {
      respondWithPlainTextBody($output, 200);
    }
  }
}

function getAPIDocs()
{
  $ch = curl_init("http://splickit.com/developers/api-documentation");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
  curl_setopt($ch, CURLOPT_TIMEOUT, 2);
  $output = curl_exec($ch);
  curl_close($ch);
  return $output;
  /*	
    
    $aws_service = new AWSService();
    $body = $aws_service->getKey("splickit-templates/api-doc", "index.txt");
    if ($body) {
      $body->rewind();			
      $output = $body->read($body->getContentLength());
      return $output;
    }
  */
}

function logCurl($url, $method, $user_password, $headers, $payload)
{
  $curl_string = "curl ";
  if (strtolower($method) == 'post') {
    $curl_string .= "-X POST ";
  } else if (strtolower($method) == 'put') {
    $curl_string .= "-X PUT ";
  } else if (strtolower($method) == 'delete') {
    $curl_string .= "-X DELETE ";
  }
  if ($user_password) {
    $curl_string .= "-u $user_password ";
  }
  $curl_string .= "-v ";
  foreach ($headers as $header) {
    $curl_string .= "-H '$header' ";
  }
  if ($payload) {
    $curl_string .= "-d '$payload' ";
  }
  $curl_string .= "$url";
  myerror_log("CURL COMMAND: ".$curl_string);
}

function createUserAuthenticationToken($user_id,$duration_in_seconds = 43200)
{
  $auth_token = generateCode(20);
  $taa = new TokenAuthenticationsAdapter();
  //expires 12 hours from now
  $expires_at = time() + $duration_in_seconds;
  if ($token_authentication_resource = Resource::createByData($taa, array("user_id" => $user_id, "token" => $auth_token, "expires_at" => $expires_at))) {
    myerror_log("New Auth Token created: " . $token_authentication_resource->token . "  for user_id: $user_id");
    logData($token_authentication_resource->getDataFieldsReally(), "Created Token Auth Record", 3);
    return $token_authentication_resource;
  }
}

function hard_wrap($string, $line_length, $line_sep)
{
  $string_cpy = $string;
  $string_chunks = explode($line_sep, $string_cpy);
  $newstr = "";

  for ($i = 0; $i < count($string_chunks); $i++) {
    $chunk = $string_chunks[$i];
    if ($i < count($string_chunks) - 1) {
      $chunk .= $line_sep;
    }
    if (strlen($chunk) > (strlen($line_sep) + $line_length)) {
      $small_enough = str_split($chunk, $line_length);
      for ($j = 0; $j < count($small_enough); $j++) {
        $small_chunk = $small_enough[$j];
        $newstr .= $small_chunk;

        if ($j < count($string_chunks) - 1) {
          $newstr .= $line_sep;
        }
      }
    } else {
      $newstr .= $chunk;
    }
  }
  return $newstr;
}

function isStringValidJson($string)
{
  $result = json_decode($string, true);
  if ($result == null) {
    return false;
  } else {
    return is_array($result);
  }
}

function getSOAPCleanSectionFromEnvelopeBodyAsHashMap($body, $tag)
{
  $xml = getSOAPCleanSectionFromEnvelopeBody($body, $tag);
  return parseXMLintoHashmap($xml);
}

function getSOAPCleanSectionFromEnvelopeBody($body, $tag)
{
  $start = strpos($body, "<$tag");
  $end = strpos($body, "</$tag>") + strlen($tag) + 3;
  $xml = getCleanPayloadFromXML($body, $start, $end);
  return $xml;
}

function getCleanPayloadFromXML($xml_string, $start, $end)
{
  $length = $end - $start;
  $data_xml = substr($xml_string, $start, $length);
  $clean_xml = cleanUpXML($data_xml);
  return $clean_xml;

}

function objectToArray($d)
{
  if (is_object($d)) {
    // Gets the properties of the given object with get_object_vars function
    $d = get_object_vars($d);
  }

  if (is_array($d)) {
    /*
        * Return array converted to object
        * Using __FUNCTION__ (Magic constant) for recursive call
        */
    return array_map(__FUNCTION__, $d);
  } else {
    // Return array
    return $d;
  }
}

function arrayToObject($d)
{
  if (is_array($d)) {
    /*
        * Return array converted to object
        * Using __FUNCTION__ (Magic constant) for recursive call
        */
    return (object)array_map(__FUNCTION__, $d);
  } else {
    // Return object
    return $d;
  }
}


function getUserFromId($user_id)
{
  if ($user_resource = getUserResourceFromId($user_id)) {
    return $user_resource->getDataFieldsReally();
  }
}

/**
 * @param $user_id
 * @return Resource
 */
function getUserResourceFromId($user_id)
{
  return UserAdapter::getUserResourceFromId($user_id);
}

function getStaticRecord($data, $class_name)
{
  return $class_name::staticGetRecord($data, "$class_name");
}

function getStaticRecords($data, $class_name)
{
  return $class_name::staticGetRecords($data, "$class_name");
}

function isMobileAppRequest()
{
  return (strtolower($_SERVER['HTTP_X_SPLICKIT_CLIENT_DEVICE']) == 'iphone' || strtolower($_SERVER['HTTP_X_SPLICKIT_CLIENT_DEVICE']) == 'android');
}


function validateDate($date, $format = 'mm/dd/yyyy'){
  if (strlen($date) <= 5) {
    // force a year if a user didn't want to give us the year
    $date = $date.'/2000';
  }

  if(strlen($date) >= 6 && strlen($format) == 10){

    // find separator. Remove all other characters from $format
    $separator_only = str_replace(array('m','d','y'),'', $format);
    $separator = $separator_only[0]; // separator is first character

    if($separator && strlen($separator_only) == 2){
      // make regex
      $regexp = str_replace('mm', '(0?[1-9]|1[0-2])', $format);
      $regexp = str_replace('dd', '(0?[1-9]|[1-2][0-9]|3[0-1])', $regexp);
      $regexp = str_replace('yyyy', '(19|20)?[0-9][0-9]', $regexp);
      $regexp = str_replace($separator, "\\" . $separator, $regexp);
      if($regexp != $date && preg_match('/'.$regexp.'\z/', $date)){

        // check date
        $arr=explode($separator,$date);
        $arr_format = explode($separator, $format);
        $day=$arr[array_search('dd', $arr_format)]; //dd
        $month=$arr[array_search('mm', $arr_format)]; //mm
        $year=$arr[array_search('yyyy', $arr_format)]; //yyyy
        if(@checkdate($month, $day, $year))
          return true;
      }
    }
  }
  return false;
}


function getM()
{
  return array('html' => 'text/html','xml' => 'text/xml');
}

function useForcedGlobalWriteDb()
{
  if (isset($_SERVER['USE_WRITE_DB'])) {
      return $_SERVER['USE_WRITE_DB'];
  } else {
    return ($_SERVER['REQUEST_METHOD'] == 'POST' || $_SERVER['REQUEST_METHOD'] == 'DELETE');
  }
}

function isRequestBrowserBased()
{
  $device = strtolower($_SERVER['device_type']);
  return (substr($device,0,3)=='web');
}


function fatalHandler()
{
  $error = error_get_last();
  if ($error !== NULL) {
    $err_type = $error["type"];
    if ($err_type != 8) {
      $message = (empty($error["message"])) ? 'No message listed' : $error["message"];
      if ($err_type == 1) {
        $err_file = (empty($error["file"])) ? 'No file Listed' : $error["file"];
        $err_line = (empty($error["line"])) ? 'No line number Listed' : $error["line"];
        $body = " error_type = $err_type,\r\n file = $err_file,\r\n line = $err_line,\r\n messages = $message";
        myerror_log("******** FATAL ERROR! IN: " . getRawStamp() . " ************: ".$body);
        recordError("******** FATAL ERROR! IN: " . getRawStamp() . " ************",$body);
      }
    }
  }
}

/**
 * It capitalizes the first Word of a sentence and separates by blank space between them. Trace an log error and returns
 * null if there is a wrong.
 * @param $words
 * @return null|string if is not an string or is empty or words is null
 */
function capitalizeWordAndAddSpaceBetweenWords($words)
{
  if (empty($words) || !is_string($words)) {
    myerror_log("Class functions function->capitalizeWordAndAddSpaceBetweenWords: ERROR! words is empty or null or not string instance");
  } else {
    $result = trim(ucwords(preg_replace('/\s\s+/', ' ', str_replace("_", " ", $words))));
    return $result;
  }
  return null;
}

/**
 * Converts rounds to up decimals samples 2.46 to 2.5, 3.62 to 3.6, 2.03 to 2, 2.0 to 2
 * @param $number floating number
 * @return int|float converted
 */
function convertDecimalToIntegerOrRoundUp($number)
{
  $result = 0;
  if (!is_numeric($number)) {
    myerror_log("class functions convertDecimalToInteger: ERROR! number has not valid format");
  } else {
    if ($number == 0 || $number == 0.0 || $number == 0.00) {
      $result = 0;
    }else {
      $result = round($number,PHP_ROUND_HALF_UP);
      return $result;
    }
  }
  return $result;
}

function getFieldFromObjectArray($data, $index)
{
  return array_map(function ($item, $i) {
    return is_array($item) ? $item[$i] : $item->$i;
  }, $data, $index);
}

function doesFlagPositionNEqualX($flags,$n,$x)
{
  $char = substr($flags, $n-1,1);
  return $x == $char;
}

register_shutdown_function("fatalHandler");

class SystemPropertiesConfigurationException extends Exception
{
  public function __construct($file, $server)
  {
    parent::__construct("SYSTEM CONFIGURATION EXCEPTION: $file vs $server");
  }
}

class PropertiesTableNotLoadedException extends Exception
{
  public function __construct()
  {
    parent::__construct("ERROR!!!! PROPERTIES TABLE NOT LOADED!!!!!");
  }
}

class EnvironmentFileNotLoadedException extends Exception
{
  public function __construct()
  {
    parent::__construct("ERROR!!!! ENVIRONMENT FILE NOT LOADED!!!!");
  }
}

class DefaultEnvironmentFileNotLoadedException extends Exception
{
  public function __construct()
  {
    parent::__construct("ERROR!!!! DEFAULT ENVIRONMENT FILE NOT LOADED!!!!");
  }
}


?>
