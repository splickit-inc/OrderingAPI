<?php

class HolidayHourAdapter extends MySQLAdapter
{

	function HolidayHourAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Holiday_Hour',
			'%([0-9]{1,15})%',
			'%d',
			array('holiday_id')
			);
	}
	
	function &select($url, $options = NULL)
    {
    	if (isset($options[TONIC_FIND_BY_METADATA]['logical_delete']))
			; //bypass the logical delete.  this si for testing 
		else
    		$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    	return parent::select($url,$options);
    }

    static function doTareksHolidayThing()
    {
    	$holiday_hour_adapter = new HolidayHourAdapter($mimetypes);
    	$sql = "CALL SMAWSP_HOLIDAY_HOUR";
		if ($holiday_hour_adapter->_query($sql))
		{
			myerror_log("HOLIDAY CRON executed normally");
			return true;
		} else {
			myerror_log("there was an error executing the holiday cron: ".$holiday_hour_adapter->getLastErrorText());
			MailIt::sendErrorEmail("There was an error executing tareks holiday hour SP", "error: ".$holiday_hour_adapter->getLastErrorText());
			return false;
		}    	
    }
}
?>