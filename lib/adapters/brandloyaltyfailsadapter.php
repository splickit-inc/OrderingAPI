<?php
class BrandLoyaltyFailsAdapter extends MySQLAdapter
{
	function BrandLoyaltyFailsAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Brand_Loyalty_Fails',
			'%([0-9]{1,10})%',
			'%d',
			array('id'),
			NULL,
			array('created','modified')
			);
	}
	
	function unlockLoyaltyFails($the_current_time = 0)
	{
		if ($the_current_time == 0)
			$the_current_time = time();
		$options[TONIC_FIND_BY_METADATA]['unlock_at_ts'] = array('<'=>$the_current_time);
		$number_updated = 0;
		$number_failed = 0;
		$error_string = "";
		if ($resources = Resource::findAll($this,'',$options))
		{
			foreach ($resources as $blf_resource)
			{
				$blf_resource->failed_attempts = 0;
				$blf_resource->unlock_at_ts = 'nullit';
				if ($blf_resource->save())
					$number_updated++;
				else
				{
					myerror_log("We had a failed trying to update the Brand Loyalty Fails Record");
					$number_failed++;
					$error_string = $error_string."  error: ".$this->getLastErrorText();
				}
			}
		}
		if ($number_failed > 0)
			MailIt::sendErrorEmailAdam("we had some failures in unlock loyalty fails", $error_string);
		myerror_log("number of records unlocked is: ".$number_updated);
		return $number_updated;
	}

	static function doesUserHaveMoreThanSubmittedNumberOfFails($device_id,$brand_id,$more_then_this_many_fails)
	{
		$brand_loyalty_fails_adapter = new BrandLoyaltyFailsAdapter($mimetypes);
   		$blf_options[TONIC_FIND_BY_METADATA] = array("brand_id"=>$brand_id,"device_id"=>$device_id);
   		if ($brand_loyalty_fails_resource = Resource::find($brand_loyalty_fails_adapter,'',$blf_options)) {
   			if ($brand_loyalty_fails_resource->failed_attempts > $more_then_this_many_fails) {
				return true; 
   			}			   		
		}
		return false;		
	}
}
?>