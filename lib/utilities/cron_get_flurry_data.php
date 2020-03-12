<?php
error_reporting(E_ERROR);
ini_set('display_errors','0');
//error_reporting(E_ALL);

require_once '..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'tonic'.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'request.php';
require_once '..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'tonic'.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'resource.php';
require_once '..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'tonic'.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'response.php';
require_once '..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'tonic'.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'smartyresource.php';
require_once '..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'tonic'.DIRECTORY_SEPARATOR.'adapters'.DIRECTORY_SEPARATOR.'mysqladapter.php';

require_once '..'.DIRECTORY_SEPARATOR.'adapters'.DIRECTORY_SEPARATOR.'admflurrykeyadapter.php';
require_once '..'.DIRECTORY_SEPARATOR.'adapters'.DIRECTORY_SEPARATOR.'admflurrydataadapter.php';

  $startDate = strtotime('2011-01-11');
  $endDate   = strtotime('2011-01-12');

 // New Variables
  $currDate  = $startDate;
  $dayArray  = array();

 // Loop until we have the Array
  do{
    $dayArray[] = date( 'Y-m-d' , $currDate );
    $currDate = strtotime( '+1 day' , $currDate );
  } while( $currDate<=$endDate );

$api_access_code = 'IV3N51NDTSDQ9FJL9TMY';

//get yesterday's date
$uts['yesterday'] = strtotime( '-1 days' );
$the_date = date("Y-m-d", $uts['yesterday'] );

if ($argv[1])
{	
	$date_test = split('-',$argv[1]);
	if (checkdate($date_test[1],$date_test[2],$date_test[0]))	
		$the_date = $argv[1];
	else
		die ('bad date format, must be in yyyy-mm-dd');		
}

$metrics = array('ActiveUsers' => 'active_users','NewUsers' => 'new_users');

$adm_flurry_key_adapter = new AdmFlurryKeyAdapter($mimetypes);
$flurry_keys = $adm_flurry_key_adapter->select('',null);
$header_array = array('Accept' => 'application/json');
$options = array('headers' => $header_array);

//foreach ($dayArray AS $the_date)
//{
	// now loop through the existing keys
	foreach ($flurry_keys as $flurry_key)
	{
		$data = array();
		$data['flurry_id'] = $flurry_key['flurry_id'];
		foreach ($metrics AS $metric => $db_column)
		{
			//myerror_log("about to do data for ".$flurry_key['skin_id']." on ".$flurry_key['app_platform']);
			$url = 'http://api.flurry.com/appMetrics/'.$metric.'?apiAccessCode='.$api_access_code.'&apiKey='.$flurry_key['flurry_key'].'&startDate='.$the_date.'&endDate='.$the_date.'';
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_HEADER, $header_array);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER,1); //dont display to screen
			//myerror_log("flurry url is: ".$url);
			$response = curl_exec($ch);	
			//myerror_log("response: ".$response);
			if (substr_count(strtolower($response), 'error'))
			{
				$error_pos1 = strpos($response, '<message>');
				$error_pos2 = strpos($response, '</message>');
				$estring = substr($response, $error_pos1+9,$error_pos2-$error_pos1-9);
				myerror_log($estring);
				die ('ERROR! '.$estring);
			}
			$loc = stripos($response, "{\"");
			$newstr = substr($response, $loc);
			myerror_log("response: ".$newstr);
			$decoded = json_decode($newstr,true);
			$data[$db_column] = $value = $decoded['day']['@value'];		
			$data['info_date'] = ''.$decoded['day']['@date'];
			sleep(2);
		}
		$adm_flurry_data_adapter = new AdmFlurryDataAdapter($mimetypes);
		$resource = new Resource($adm_flurry_data_adapter,$data);
		$adm_flurry_data_adapter->insert($resource);
		
	}
//}
?>

