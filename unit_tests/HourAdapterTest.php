<?php
ini_set('max_execution_time', 300);

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class HourAdapterTest extends PHPUnit_Framework_TestCase
{
    var $hour_adapter;
    var $user;
    var $merchant;
    var $merchant_id;

    function setUp() {
        setContext('com.splickit.houradapterskin');
        $this->hour_adapter = new HourAdapter();
		$user_id = $_SERVER['unit_test_ids']['user_id'];
		$user_resource = SplickitController::getResourceFromId($user_id, 'User');
		$this->user = $user_resource->getDataFieldsReally();
		
    	$this->merchant_id = $_SERVER['unit_test_ids']['merchant_id'];
    	$this->merchant = SplickitController::getResourceFromId($this->merchant_id, 'Merchant');	
        
    }

    function tearDown() {
        // delete your instance
        unset($this->hour_adapter);
    }
    
    function setSomeInvertedHours($merchant_id)
    {
    	//set the hours for the date
		$hours_data['merchant_id'] = $merchant_id;
		$hours_data['hour_type'] = 'R';
    	$hours_options[TONIC_FIND_BY_METADATA] = $hours_data;
    	$hours_options[TONIC_SORT_BY_METADATA] = " day_of_week ASC ";
    	$hour_adapter = new HourAdapter($mimetypes);
    	$hours_resources = Resource::findAll($hour_adapter,'',$hours_options);
    	
    	//set weds hours
    	$hours_resources[3]->open = "09:00";
    	$hours_resources[3]->close = "00:00";
    	$hours_resources[3]->save();
    	//set thurs hours
    	$hours_resources[4]->open = "08:45";
    	$hours_resources[4]->close = "03:05";
    	$hours_resources[4]->save();
    	//set fris hours
    	$hours_resources[5]->open = "09:00";
    	$hours_resources[5]->close = "03:15";
    	$hours_resources[5]->second_close = "17:03";
    	$hours_resources[5]->save();
		
    }
    
	function testGetCurrentOpenAndCloseTimeStamps()
	{
    	$tz = date_default_timezone_get(); 
    	date_default_timezone_set('America/Denver');
		$hour_adapter = $this->hour_adapter;
    	$time_stamp_with_no_close = getTimeStampForDateTimeAndTimeZone(017, 0, 0, 10, 16, 2013, 'America/Denver');
    	$time_stamp_after_midnight = getTimeStampForDateTimeAndTimeZone(01, 0, 0, 10, 17, 2013, 'America/Denver');
    	$time_stamp_after_late_close_before_open = getTimeStampForDateTimeAndTimeZone(06, 0, 0, 10, 17, 2013, 'America/Denver');
    	$time_stamp_after_open_with_second_close = getTimeStampForDateTimeAndTimeZone(10, 0, 0, 10, 18, 2013, 'America/Denver');
    	$time_stamp_after_second_close = getTimeStampForDateTimeAndTimeZone(19, 0, 0, 10, 18, 2013, 'America/Denver');
		
    	$merchant_resource = createNewTestMerchant($this->ids['menu_id']);
		$merchant_id = $merchant_resource->merchant_id;
    	$this->setSomeInvertedHours($merchant_id);
		
    	// no close
    	$time_stamp_string = date('Y-m-d H:i:s',$time_stamp_with_no_close);
    	myerror_log("time stamp is for: ".$time_stamp_string);
    	$today_open_close = $hour_adapter->getTSForOpenAndCloseForHourType($merchant_id, 'R',$time_stamp_with_no_close);
		$actual_today_closed = date('Y-m-d H:i:s',$today_open_close['close_ts']);
		$expected_today_close = '2013-10-17 03:05:00';
		$this->assertEquals($expected_today_close, $actual_today_closed); 

		//after midnight
		$time_stamp_string = date('Y-m-d H:i:s',$time_stamp_after_midnight);
    	myerror_log("time stamp is for: ".$time_stamp_string);
    	$today_open_close = $hour_adapter->getTSForOpenAndCloseForHourType($merchant_id, 'R',$time_stamp_after_midnight);
		$actual_today_closed = date('Y-m-d H:i:s',$today_open_close['close_ts']);
		$expected_today_close = '2013-10-17 03:05:00';
		$this->assertEquals($expected_today_close, $actual_today_closed); 

		//after inverted close before open
		$time_stamp_string = date('Y-m-d H:i:s',$time_stamp_after_late_close_before_open);
    	myerror_log("time stamp is for: ".$time_stamp_string);
    	$today_open_close = $hour_adapter->getTSForOpenAndCloseForHourType($merchant_id, 'R',$time_stamp_after_late_close_before_open);
		$actual_today_open = date('Y-m-d H:i:s',$today_open_close['open_ts']);
		$expected_today_open = '2013-10-17 08:45:00';
		$this->assertEquals($expected_today_open, $actual_today_open); 
		$actual_today_closed = date('Y-m-d H:i:s',$today_open_close['close_ts']);
		$expected_today_close = '2013-10-18 03:15:00';
		$this->assertEquals($expected_today_close, $actual_today_closed); 

		//after open with seconds close
		$time_stamp_string = date('Y-m-d H:i:s',$time_stamp_after_open_with_second_close);
    	myerror_log("time stamp is for: ".$time_stamp_string);
    	$today_open_close = $hour_adapter->getTSForOpenAndCloseForHourType($merchant_id, 'R',$time_stamp_after_open_with_second_close);
		$actual_today_closed = date('Y-m-d H:i:s',$today_open_close['close_ts']);
		$expected_today_close = '2013-10-18 17:03:00';
		$this->assertEquals($expected_today_close, $actual_today_closed); 

		//after second close
		$time_stamp_string = date('Y-m-d H:i:s',$time_stamp_after_second_close);
    	myerror_log("time stamp is for: ".$time_stamp_string);
    	$today_open_close = $hour_adapter->getTSForOpenAndCloseForHourType($merchant_id, 'R',$time_stamp_after_second_close);
		$actual_today_open = date('Y-m-d H:i:s',$today_open_close['open_ts']);
		$expected_today_open = '2013-10-18 09:00:00';
		$this->assertEquals($expected_today_open, $actual_today_open); 
		$actual_today_closed = date('Y-m-d H:i:s',$today_open_close['close_ts']);
		$expected_today_close = '2013-10-18 17:03:00';
		$this->assertEquals($expected_today_close, $actual_today_closed); 

		date_default_timezone_set($tz);
		
	}
    
    //test human readable hours function
    function testHumanReadableMerchantHours()
    {
    	$merchant_id = $this->merchant_id;
    	
		$hours_data['merchant_id'] = $merchant_id;
		$hours_data['hour_type'] = 'R';
    	$hours_options[TONIC_FIND_BY_METADATA] = $hours_data;
    	$hours_options[TONIC_SORT_BY_METADATA] = " day_of_week ASC ";
    	
    	$hour_adapter = new HourAdapter($mimetypes);
    	$hours_resources = Resource::findAll($hour_adapter,'',$hours_options);
    	$hours_resources[0]->open = "11:00";
    	$hours_resources[0]->close = "02:45";
    	$hours_resources[0]->second_close = "17:00";    	
    	$hours_resources[0]->save();
    	
    	$hours_resources[2]->day_open = 'N';
    	$hours_resources[2]->save();
    	
    	$hours_resources[3]->open = "07:30";
    	$hours_resources[3]->close = "21:45";
    	$hours_resources[3]->save();
    	$hours_resources[4]->open = "07:30";
    	$hours_resources[4]->close = "21:45";
    	$hours_resources[4]->save();
    	
    	$hours_resources[5]->open = "10:30";
    	$hours_resources[5]->close = "00:00";
    	$hours_resources[5]->save();
    	$hours_resources[6]->open = "11:00";
    	$hours_resources[6]->close = "02:38";
    	$hours_resources[6]->save();
    	    	
    	$human_readable_hours = $hour_adapter->getAllMerchantHoursHumanReadable($merchant_id);
    	$this->assertNotNull($human_readable_hours);
    	$this->assertEquals(7, sizeof($human_readable_hours, $mode));
    	$this->assertEquals("closed",$human_readable_hours[1]['Tuesday']);
    	$this->assertEquals("7:30am-9:45pm",$human_readable_hours[2]['Wednesday']);
    	$this->assertEquals("10:30am-2:38am",$human_readable_hours[4]['Friday']);
    	$this->assertEquals("11:00am-2:45am",$human_readable_hours[5]['Saturday']);
    	$this->assertEquals("11:00am-5:00pm",$human_readable_hours[6]['Sunday']);
    	
    }

    //test the new cod timestamp function.  will generate a ts for when the cob shoudl be run based on the later closing hours of pickup and delivery
    function testGetCOBtimestamp() {
    	// merchant 1095
    	$merchant_id = $this->merchant_id;
    	$hour_adapter = new HourAdapter($mimetypes);
	   	// now lets create a loop that should give us the values for the entire week
    	//lets start on sunday, january 13th
    	for ($i = 1;$i < 8;$i++)
    	{
    		// need to start with 12 so data lines up correctly for me
    		$week_of_ts[$i] = mktime(3, '0', '0', 1 , 12+$i, 2013); 
    		myerror_log("the date is: ".date('Y-m-d H:i:s',$week_of_ts[$i]));
    	}
    	foreach ($week_of_ts as $day=>$time_stamp)
    	{
	    	$hours_data = $hour_adapter->getOpenAndCloseForCOBInTimeStampFormForMerchantId($merchant_id,$time_stamp);
   		 	$cob_date_string = date('Y-m-d H:i:s',$hours_data['close_ts']);
   		 	$cob_dates[$day] = $cob_date_string;   		 	
       	}
       	foreach ($cob_dates as $day=>$cob_date)
       		myerror_log("close $day:    $cob_date");

      	$this->assertEquals("2013-01-13 20:00:00", $cob_dates[1]);  //since delivery hours are later, the COB will click at the delivery hours time.
       	$this->assertEquals("2013-01-14 20:00:00", $cob_dates[2]);	
       	$this->assertEquals("2013-01-15 20:00:00", $cob_dates[3]);	
       	$this->assertEquals("2013-01-16 21:45:00", $cob_dates[4]);	
       	$this->assertEquals("2013-01-17 21:45:00", $cob_dates[5]);	
       	$this->assertEquals("2013-01-19 02:38:00", $cob_dates[6]);	
       	$this->assertEquals("2013-01-20 02:45:00", $cob_dates[7]);	
//       	myerror_log("and we've reached the end");
    	
    }
    
    // test the getNowAsInt function
    function testGetNowAsInt() {
    	error_log("starting get now as int test");
        $actual = $this->hour_adapter->getNowAsInt();
        $expected = time();
        $this->assertEquals($expected, $actual);
    }
    
    function testGetHoursForWeek()
    {
    	//get record out of db and compare with functions
    	$merchant_id = $this->merchant_id;
    	$day = date('w') + 1;
    	$hour_data['day_of_week'] = $day;
    	$hour_data['merchant_id'] = $merchant_id;
    	$hour_options[TONIC_FIND_BY_METADATA] = $hour_data;
    	$resources = Resource::findAll($this->hour_adapter,null,$hour_options);
    	foreach ($resources AS $resource)
    	{
    		$open[$resource->hour_type] = $resource->open;
    		$close[$resource->hour_type] = $resource->close;
    	}
    	
    	$pickup_weeks_hours = $this->hour_adapter->getMerchantHoursForWeek(time(), $merchant_id, 'R');
    	$delivery_weeks_hours = $this->hour_adapter->getMerchantHoursForWeek(time(), $merchant_id, 'D');
    	
    	$this->assertEquals($open['D'], $delivery_weeks_hours[0]['open']);
    	$this->assertEquals($open['R'], $pickup_weeks_hours[0]['open']);
    }
    
    function testGetLocalOpenCLoseFromDateForMerchant()
    {
        $the_starting_date = "2012-10-15"; //monday
    	$merchant_id = $this->merchant_id;
    	// first get local opening and closing times
    	
    	$hour_adapter = new HourAdapter($mimetypes);
		$local_open_close_data = $hour_adapter->getLocalOpenAndCloseDtTmForDate($merchant_id, $the_starting_date, 'R');
		$this->assertEquals("2012-10-15 07:00:00", $local_open_close_data['local_open_dt_tm']);
		$this->assertEquals("2012-10-15 20:00:00", $local_open_close_data['local_close_dt_tm']);
		$this->assertEquals("2012-10-15",$local_open_close_data['local_open_date']);

		//test with inverted hours
		$the_starting_date_inverted = "2012-10-19"; //friday
		$local_open_close_data_inverted = $hour_adapter->getLocalOpenAndCloseDtTmForDate($merchant_id, $the_starting_date_inverted, 'R');
		$this->assertEquals("2012-10-19 10:30:00", $local_open_close_data_inverted['local_open_dt_tm']);
		$this->assertEquals("2012-10-20 02:38:00", $local_open_close_data_inverted['local_close_dt_tm']);
		$this->assertEquals("2012-10-19",$local_open_close_data_inverted['local_open_date']);
    }
    
    function testGetDeliveryLeadTimesArray()
    {
    	$merchant_delivery_info_adapter = new MerchantDeliveryInfoAdapter($mimetypes);
    	$merchant_id = $this->merchant_id;
    	$mdia_data['merchant_id'] = $merchant_id;
    	$options[TONIC_FIND_BY_METADATA] = $mdia_data;
    	$mid_resource = Resource::findExact($merchant_delivery_info_adapter,'',$options);
    	$mid_resource->minimum_delivery_time = 1440;
    	$mid_resource->max_days_out = 2;
    	$mid_resource->save();
    	
    	$hours_data['merchant_id'] = $merchant_id;
		$hours_data['hour_type'] = 'D';
    	$hours_options[TONIC_FIND_BY_METADATA] = $hours_data;
    	$hours_options[TONIC_SORT_BY_METADATA] = " day_of_week ASC ";
    	$hour_adapter = new HourAdapter($mimetypes);
    	$hours_resources = Resource::findAll($hour_adapter,'',$hours_options);
    	
    	$hours_resources[4]->close = "20:25";
    	$hours_resources[4]->save();
    	$our_timestamp = mktime(12,22,15,8,27,2012);
    	myerror_log("our timestamp is: ".date("Y-m-d H:i:s",$our_timestamp));
    	$advanced_times_array = $this->hour_adapter->buildAdvancedDeliveryTimesArray($merchant_id, -7, 'D',$our_timestamp);
    	$first_available_time = date('Y-m-d H:i:s',$advanced_times_array[0]);
    	myerror_log("first available time is: ".$first_available_time);
    	
    	$this->assertEquals(66, sizeof($advanced_times_array, $mode));
    	$this->assertEquals(1346178600, $advanced_times_array[0], "first time available should have been.....Tuesday August 28, 2012, 12:30 pm America/Denver   but its: ".date("l F j, Y, g:i a e",$advanced_times_array[0]));
    	$last_time_stamp = array_pop($advanced_times_array);
    	$this->assertEquals(1346378400, $last_time_stamp,"last time available should have been.....Thursday August 30, 2012, 8:00 pm America/Denver   but its:  ".date("l F j, Y, g:i a e",$last_time_stamp));
    	
    }
    
    function testGetDeliveryHoursAfterMidnight()
    {
		$merchant_resource = createNewTestMerchant($this->menu_id);
    	$merchant_id = $merchant_resource->merchant_id;

    	$merchant_delivery_info_adapter = new MerchantDeliveryInfoAdapter($mimetypes);
    	$mdia_data['merchant_id'] = $merchant_id;
    	$options[TONIC_FIND_BY_METADATA] = $mdia_data;
    	$mid_resource = Resource::findExact($merchant_delivery_info_adapter,'',$options);
    	$mid_resource->minimum_delivery_time = 45;
    	$mid_resource->delivery_increment = 15;
    	$mid_resource->max_days_out = 0;
    	$mid_resource->save();
 
    	// set delivery hours after midnight
    	$hours_data['merchant_id'] = $merchant_id;
		$hours_data['hour_type'] = 'D';
    	$hours_options[TONIC_FIND_BY_METADATA] = $hours_data;
    	$hours_options[TONIC_SORT_BY_METADATA] = " day_of_week ASC ";
    	$hour_adapter = new HourAdapter($mimetypes);
    	$hours_resources = Resource::findAll($hour_adapter,'',$hours_options);
    	
    	//set friday's hours
    	$hours_resources[5]->open = "09:00";
    	$hours_resources[5]->close = "00:00";
    	$hours_resources[5]->save();
    	//set saturday's hours
    	$hours_resources[6]->open = "08:45";
    	$hours_resources[6]->close = "02:00";
    	$hours_resources[6]->save();
    	//set sunday's hours
    	$hours_resources[0]->open = "09:00";
    	$hours_resources[0]->close = "02:00";
    	$hours_resources[0]->second_close = "17:00";
    	$hours_resources[0]->save();
    	
    	// store 1093 has now has delivery hours after midnight on the weekend
    	// first lets see what  happens if i get the delivery array back and its 1am, they close at 2am
    	$our_timestamp = mktime(1,0,0,9,1,2012);
    	myerror_log("our timestamp is: ".date("Y-m-d H:i:s",$our_timestamp));
    	
    	$advanced_times_array = $this->hour_adapter->buildAdvancedDeliveryTimesArray($merchant_id, -8, 'D',$our_timestamp);
    	$first_available_time = date('Y-m-d H:i:s',$advanced_times_array[0]);
    	myerror_log("first available time is: ".$first_available_time);
    	
    	$this->assertEquals(1346512500, $advanced_times_array[0], "first time available should have been.....Saturday September 1, 2012, 9:15 am America/Denver   but its: ".date("l F j, Y, g:i a e",$advanced_times_array[0]));
    	$last_time_stamp = array_pop($advanced_times_array);
    	$this->assertEquals(1346571900, $last_time_stamp,"last time available should have been.....Sunday September 2, 2012, 1:45 am America/Denver   but its:  ".date("l F j, Y, g:i a e",$last_time_stamp));
    }
    
    function testGetDays()
    {
    	$hour_adapter = new HourAdapter($mimetypes);
    	$tz = date_default_timezone_get();
    	date_default_timezone_set("America/Denver");
    	$starting_time_stamp = mktime(12,22,15,12,22,2012);
    	date_default_timezone_set($tz);
    	$days = $hour_adapter->getDaysArrayFromStartingTimeStamp($starting_time_stamp);
    	$this->assertEquals('2012-12-22', $days[0],'Should have started on december 22nd');
    }

    function testGetHolidaysThatFallOnTheseDays()
    {
    	$holiday_hour_adapter = new HolidayHourAdapter($mimetypes);
    	Resource::createByData($holiday_hour_adapter, array("merchant_id"=>$this->merchant_id,"the_date"=>strtotime('2012-12-24'),'day_open'=>'Y','open'=>'10:00','close'=>'14:30'));
    	Resource::createByData($holiday_hour_adapter, array("merchant_id"=>$this->merchant_id,"the_date"=>strtotime('2012-12-25'),'day_open'=>'N','open'=>'10:00','close'=>'14:30'));
    	$tz = date_default_timezone_get();
    	date_default_timezone_set("America/Denver");
    	$starting_time_stamp = mktime(12,22,15,12,22,2012);
    	date_default_timezone_set($tz);
    	$hour_adapter = new HourAdapter($mimetypes);
    	$days = $hour_adapter->getDaysArrayFromStartingTimeStamp($starting_time_stamp);
    	$this->assertEquals(7, count($days),"days should be an array of 7");
    	$holidays = $hour_adapter->getHolidaysThatFallOnTheseDays($this->merchant_id, $days);
    	$this->assertNotNull($holidays);
    	$this->assertEquals(2,count($holidays),"should have found 2 holidays");
    	$this->assertEquals('Y', $holidays['2012-12-24']['day_open']);
    	$this->assertEquals('10:00:00', $holidays['2012-12-24']['open']);
    	$this->assertEquals('14:30:00', $holidays['2012-12-24']['close']);
    	$this->assertEquals('N', $holidays['2012-12-25']['day_open']);
    	return true;
    }
    
    /**
     * @depends testGetHolidaysThatFallOnTheseDays
     */
    function testLoadMerchantHoursForWeekPickup()
    {
    	$hour_adapter = new HourAdapter($mimetypes);
		$day1_hours = $hour_adapter->getRecord(array("merchant_id"=>$this->merchant_id,"day_of_week"=>7,"hour_type"=>'R'));
    	$day2_hours = $hour_adapter->getRecord(array("merchant_id"=>$this->merchant_id,"day_of_week"=>1,"hour_type"=>'R'));
    	$day5_hours = $hour_adapter->getRecord(array("merchant_id"=>$this->merchant_id,"day_of_week"=>4,"hour_type"=>'R'));
    	$day6_hours = $hour_adapter->getRecord(array("merchant_id"=>$this->merchant_id,"day_of_week"=>5,"hour_type"=>'R'));
    	$tz = date_default_timezone_get();
    	date_default_timezone_set("America/Denver");
    	$starting_time_stamp = mktime(12,22,15,12,22,2012);
    	date_default_timezone_set($tz);
		$hours = $hour_adapter->loadMerchantHoursForWeek($starting_time_stamp, $this->merchant_id, 'R');
    	$this->assertEquals(7, count($hours));
    	$this->assertEquals($day1_hours['open'], $hours[0]['open']);
    	$this->assertEquals($day1_hours['close'], $hours[0]['close']);  
    	$this->assertEquals($day1_hours['day_open'], $hours[0]['day_open']);
    	$this->assertEquals("N", $hours[0]['holiday']);  
    	$this->assertEquals("10:00:00", $hours[2]['open']);
    	$this->assertEquals("14:30:00", $hours[2]['close']);  
    	$this->assertEquals("Y", $hours[2]['day_open']);
    	$this->assertEquals("Y", $hours[2]['holiday']);  
    	$this->assertEquals("N", $hours[3]['day_open'],"should be closed for christmass");
    	$this->assertEquals("Y", $hours[3]['holiday']);  
    	$this->assertEquals($day5_hours['open'], $hours[4]['open']);
    	$this->assertEquals($day5_hours['close'], $hours[4]['close']);  
    	$this->assertEquals($day5_hours['day_open'], $hours[4]['day_open']);
    	$this->assertEquals("N", $hours[4]['holiday']);  
    }

    function testGetAdvancedTimesArray()
    {
    	$hour_adapter = new HourAdapter($mimetypes);

	   	$merchant_resource = createNewTestMerchant($this->menu_id);
	   	$merchant_resource->delivery = 'Y';
	   	$merchant_resource->save();
    	$merchant_id = $merchant_resource->merchant_id;
    	
    	$hour_data['merchant_id'] = $merchant_id;
    	$hour_data['day_of_week'] = 2;
    	$hour_data['hour_type'] = 'D';
    	$hour_options[TONIC_FIND_BY_METADATA] = $hour_data;
    	$monday_hour_resource = Resource::findExact($hour_adapter,'',$hour_options);
    	$monday_hour_resource->close = '22:00:00';
    	$monday_hour_resource->open = '11:00:00';
    	$monday_hour_resource->day_open = 'Y';
    	$monday_hour_resource->save();
    	
    	$hour_data['day_of_week'] = 3;
    	$hour_options[TONIC_FIND_BY_METADATA] = $hour_data;
    	$tuesday_hour_resource = Resource::findExact($hour_adapter,'',$hour_options);
    	$tuesday_hour_resource->close = '22:00:00';
     	$tuesday_hour_resource->open = '11:00:00';
     	$tuesday_hour_resource->day_open = 'Y';
    	$tuesday_hour_resource->save();

    	$hour_data['day_of_week'] = 5;
    	$hour_options[TONIC_FIND_BY_METADATA] = $hour_data;
    	$wednesday_hour_resource = Resource::findExact($hour_adapter,'',$hour_options);
    	$wednesday_hour_resource->close = '22:00:00';
     	$wednesday_hour_resource->open = '11:00:00';
     	$wednesday_hour_resource->day_open = 'Y';
    	$wednesday_hour_resource->save();
    	
    	$merchant_delivery_info_adapter = new MerchantDeliveryInfoAdapter($mimetypes);
    	$mdia_data['merchant_id'] = $merchant_id;
    	$options[TONIC_FIND_BY_METADATA] = $mdia_data;
    	$mid_resource = Resource::findExact($merchant_delivery_info_adapter,'',$options);
    	$mid_resource->minimum_delivery_time = 45;
    	$mid_resource->delivery_increment = 15;
    	$mid_resource->max_days_out = 1;
    	$mid_resource->save();

	   	$tz = date_default_timezone_get();
    	date_default_timezone_set("America/Denver");
    	$the_time = mktime(12,22,15,8,27,2012);
    	date_default_timezone_set($tz);

    	$advanced_times_array = $hour_adapter->buildAdvancedDeliveryTimesArray($merchant_id, -7, 'D', $the_time);
    	
    	$this->assertEquals(1346094000, $advanced_times_array[0],'first time should be  Monday August 27, 2012, 1:00 pm America/Denver  but its: '.date("l F j, Y, g:i a e",$advanced_times_array[0]));
    	$last_time_stamp = array_pop($advanced_times_array);
    	$this->assertEquals(1346211900, $last_time_stamp,'last time should be  Tuesday August 28, 2012, 9:45 pm America/Denver  but its: '.date("l F j, Y, g:i a e",$last_time_stamp));
    	    	
    	// now shut down the first day which is monday
   		$monday_hour_resource->day_open = 'N';
    	$monday_hour_resource->save();
    	
    	$hour_adapter = new HourAdapter($mimetypes);
    	$advanced_times_array = $hour_adapter->buildAdvancedDeliveryTimesArray($merchant_id, -7, 'D', $the_time);
    	
    	$this->assertEquals(1346175000, $advanced_times_array[0],'first time should be  Tuesday August 28, 2012, 11:30 am America/Denver  but its: '.date("l F j, Y, g:i a e",$advanced_times_array[0]));
    	$last_time_stamp = array_pop($advanced_times_array);
    	$this->assertEquals(1346211900, $last_time_stamp,'last time should be  Tuesday August 28, 2012, 9:45 pm America/Denver  but its: '.date("l F j, Y, g:i a e",$last_time_stamp));

   		$monday_hour_resource->day_open = 'Y';
    	$monday_hour_resource->save();

    	// now shut down tuesday
    	$tuesday_hour_resource->day_open = 'N';
    	$tuesday_hour_resource->save();
    	
    	$hour_adapter = new HourAdapter($mimetypes);
    	$advanced_times_array =$hour_adapter->buildAdvancedDeliveryTimesArray($merchant_id, -7, 'D', $the_time);
    	
    	$this->assertEquals(1346094000, $advanced_times_array[0],'first time should be  Monday August 27, 2012, 1:00 pm America/Denver  but its: '.date("l F j, Y, g:i a e",$advanced_times_array[0]));
    	$last_time_stamp = array_pop($advanced_times_array);
    	$this->assertEquals(1346125500, $last_time_stamp,'last time should be  Monday August 27, 2012, 9:45 pm America/Denver  but its: '.date("l F j, Y, g:i a e",$last_time_stamp));
    	
    	$tuesday_hour_resource->day_open = 'Y';
    	$tuesday_hour_resource->save();
    	
    	// turn on the holiday on wednesday and bump up the days to 2 which would be wednesday
		$holiday_hour_adapter = new HolidayHourAdapter($mimetypes);
		Resource::createByData($holiday_hour_adapter, array("merchant_id"=>$merchant_id,"the_date"=>strtotime('2012-08-29'),'day_open'=>'N','open'=>'10:00','close'=>'14:30'));
    	
		$mid_resource->max_days_out = 2;
    	$mid_resource->save();

    	$hour_adapter = new HourAdapter($mimetypes);
    	$advanced_times_array = $hour_adapter->buildAdvancedDeliveryTimesArray($merchant_id, -7, 'D', $the_time);
    	
    	// last time should still be tuesday night
    	$this->assertEquals(1346094000, $advanced_times_array[0],'first time should be  Monday August 27, 2012, 1:00 pm America/Denver  but its: '.date("l F j, Y, g:i a e",$advanced_times_array[0]));
    	$last_time_stamp = array_pop($advanced_times_array);
    	$this->assertEquals(1346211900, $last_time_stamp,'last time should be  Tuesday August 28, 2012, 9:45 pm America/Denver  but its: '.date("l F j, Y, g:i a e",$last_time_stamp));
    	
 		// now bump up the max to thursday
    	$mid_resource->max_days_out = 3;
    	$mid_resource->save();
    	
    	$hour_adapter = new HourAdapter($mimetypes);
    	$advanced_times_array = $hour_adapter->buildAdvancedDeliveryTimesArray($merchant_id, -7, 'D', $the_time);
    	
    	// last time should now be thursday night
    	$this->assertEquals(1346094000, $advanced_times_array[0],'first time should be  Monday August 27, 2012, 1:00 pm America/Denver  but its: '.date("l F j, Y, g:i a e",$advanced_times_array[0]));
    	$last_time_stamp = array_pop($advanced_times_array);
    	$this->assertEquals(1346384700, $last_time_stamp,'last time should be  Thursday August 30, 2012, 9:45 pm America/Denver  but its: '.date("l F j, Y, g:i a e",$last_time_stamp));
    	
    	// now check to see if ther are any wednesday timestamps.  there shouldn't be any since wednesday is now a holiday
    	foreach ($advanced_times_array as $timestamp)
    	{
    		$day = date("l",$timestamp);
    		$day = strtolower($day);
    		$days[$day] = $day;
    		//myerror_log("testing for day: ".$day);
    	}
    	$this->assertEquals('monday', $days['monday']);
    	$this->assertEquals('tuesday', $days['tuesday']);
    	$this->assertEquals('thursday', $days['thursday']);
    	$this->assertNull($days['wednesday']);    	 
    }

	function testCreateHoleHoursAdapter()
	{
		$hole_hours_adapter = new HoleHoursAdapter($mimetypes);
		$this->assertNotNull($hole_hours_adapter);
		date_default_timezone_set("America/Denver");
		date_default_timezone_set($tz);

		$start_time = strtotime("13:30");
		$start_time_bd = date("H:i:s", $start_time);

		$end_time = strtotime('15:30');
		$end_time_bd = date("H:i:s", $end_time);
		Resource::createByData($hole_hours_adapter,
			array(
				"merchant_id" => $this->merchant_id,
				"day_of_week" => 1,
				'order_type' => 'Delivery',
				'start_time' => $start_time_bd,
				'end_time' => $end_time_bd));

		Resource::createByData($hole_hours_adapter,
			array(
				"merchant_id" => $this->merchant_id,
				"day_of_week" => 2,
				'order_type' => 'Pickup',
				'start_time' => $start_time_bd,
				'end_time' => $end_time_bd));

		//test for the row
		$hole_hours_by_merchant = $hole_hours_adapter->getByMerchantId($this->merchant_id);
		$hole_hours_by_merchant_by_day_of_week = $hole_hours_adapter->getByMerchantIdAndDayOfWeek(
			$this->merchant_id,
			1
		);
		$hole_hours_by_merchant_by_order_type = $hole_hours_adapter->getByMerchantIdAndOrderType(
			$this->merchant_id,
			'R'
		);
		$this->assertNotNull($hole_hours_by_merchant_by_order_type);
		$this->assertEquals('Pickup', $hole_hours_by_merchant_by_order_type[0]['order_type']);
		$this->assertNotNull($hole_hours_by_merchant);
		$this->assertNotNull($hole_hours_by_merchant_by_day_of_week);
		$this->assertEquals($this->merchant_id, $hole_hours_by_merchant_by_day_of_week[0]['merchant_id']);
		$this->assertEquals(1, $hole_hours_by_merchant_by_day_of_week[0]['day_of_week']);
		$this->assertEquals($this->merchant_id, $hole_hours_by_merchant[0]['merchant_id']);

		//test the rows info
		$this->assertEquals(2, count($hole_hours_by_merchant), "should have found 2 rows in the table");
		$this->assertEquals(1, $hole_hours_by_merchant[0]['day_of_week']);
		$this->assertEquals('Delivery', $hole_hours_by_merchant[0]['order_type']);
		$this->assertEquals('13:30:00', $hole_hours_by_merchant[0]['start_time']);
		$this->assertEquals('15:30:00', $hole_hours_by_merchant[0]['end_time']);
		$this->assertEquals(2, $hole_hours_by_merchant[1]['day_of_week']);
		$this->assertEquals('Pickup', $hole_hours_by_merchant[1]['order_type']);
		$this->assertEquals('13:30:00', $hole_hours_by_merchant[1]['start_time']);
		$this->assertEquals('15:30:00', $hole_hours_by_merchant[1]['end_time']);
	}

    static function setUpBeforeClass()
    {
    	ini_set('max_execution_time',300);
        SplickitCache::flushAll();
        $db = DataBase::getInstance();
        $mysqli = $db->getConnection();
        $mysqli->begin_transaction(); ;
        $skin_resource = getOrCreateSkinAndBrandIfNecessaryWithLoyalty('houradapterskin', 'houradapterbrand');
        setContext('com.splickit.houradapterskin');
    	$_SERVER['request_time1'] = microtime(true);    	
		$menu_id = createTestMenuWithOneItem("Test Item 1");
    	$ids['menu_id'] = $menu_id;
    	
    	$merchant_resource = createNewTestMerchant($menu_id);
    	$merchant_id = $merchant_resource->merchant_id;
    	$ids['merchant_id'] = $merchant_id;
    	
    	$user_resource = createNewUser(array('flags'=>'1C20000001'));
    	$ids['user_id'] = $user_resource->user_id;    	
    	
    	$_SERVER['log_level'] = 5; 
		$_SERVER['unit_test_ids'] = $ids;
		$_SERVER['users'] = $users;
    	
    }
    
	static function tearDownAfterClass()
    {
    	SplickitCache::flushAll();         $db = DataBase::getInstance(); $mysqli = $db->getConnection();       $mysqli->rollback();
    }
    
	static function main() {
		$suite = new PHPUnit_Framework_TestSuite( __CLASS__);
  		PHPUnit_TextUI_TestRunner::run( $suite);
 	}

 }
 
if (false && !defined('PHPUnit_MAIN_METHOD')) {
    HourAdapterTest::main();
}

?>