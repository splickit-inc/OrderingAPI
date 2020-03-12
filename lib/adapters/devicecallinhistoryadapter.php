<?php

class DeviceCallInHistoryAdapter extends MySQLAdapter
{
	function DeviceCallInHistoryAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'device_call_in_history',
			'%([0-9]{4,15})%',
			'%d',
			array('merchant_id'),
			NULL,
			array('last_call_in')
			);
        $this->log_level = 0;
	}

    function getLastCallInByMerchantId($merchant_id)
    {
        $record = $this->getRecord(array('merchant_id'=>$merchant_id));
        $last_call_in = $record['last_call_in'];
        return $last_call_in;
    }

    function getLastCallInAsIntegerByMerchantId($merchant_id)
    {
        $record = $this->getRecord(array('merchant_id'=>$merchant_id));
        $last_call_in_as_integer = $record['last_call_in_as_integer'];
        return $last_call_in_as_integer;
    }

    function createRecord($merchant_id)
	{
		$merchant_id = intval($merchant_id);
		myerror_logging(3,"about to retrieve or create record for merchant_id: ".$merchant_id);
		// had to do this because of 2 digit demo merchants
		$merchant_data['merchant_id'] = $merchant_id;
		$m_options[TONIC_FIND_BY_METADATA] = $merchant_data;
		$cih_resource = Resource::findOrCreateIfNotExists($this, null, $m_options);
		$this->saveLastCallIn($cih_resource);
	}
	
	private function saveLastCallIn($cih_resource,$device_base_format = 'Z')
	{
        $cih_resource->last_call_in = time();
        $cih_resource->last_call_in_as_integer = time();
        $cih_resource->device_base_format = $device_base_format;
        if ($cih_resource->auto_turned_off == 1) {
            $merchant_id = $cih_resource->merchant_id;
            myerror_log("about to turn merchant_id: $merchant_id back to Orderin On from call in history adapter");
            $sql = "UPDATE Merchant SET ordering_on = 'Y' WHERE merchant_id = $merchant_id LIMIT 1";
            $this->_query($sql);
            if ($this->rows_updated == 1) {
                myerror_log("Merchant is AUTO turned back on since printer has now called in");
                $text = "Merchant device is auto turned back on for merchant_id: $merchant_id, device_format: $device_base_format";
                MailIt::sendErrorEmailSupport("Merchant Device Back On Line, Turning Ordering Back On",$text);
                $cih_resource->auto_turned_off = 0;
            } else {
                $merchant_resource = Resource::find(new MerchantAdapter(getM()),"$merchant_id");
                if ($merchant_resource->ordering_on == 'Y') {
                    $cih_resource->auto_turned_off = 0;
                }
                myerror_log("COULD NOT UPDATE MERCHANT TO TURN ORDERING BACK ON");
            }
        }
        $this->setLogLevel(0);
		return $cih_resource->save();		
		
	}
	
	/**
	 * @desc will record the last call in for the device
	 */
	static function recordPullCallIn($merchant_id,$device_base_format = 'Z')
	{
		$dciha = new DeviceCallInHistoryAdapter(getM());
		$merchant_data['merchant_id'] = $merchant_id;
		$m_options[TONIC_FIND_BY_METADATA] = $merchant_data;
		$cih_resource = Resource::findOrCreateIfNotExists($dciha, null, $m_options);
        $dciha->saveLastCallIn($cih_resource,$device_base_format);
	}

    function noActiveMerchantMessageMapRecord($merchant_id,$base_format)
    {
        return false == $this->checkForValidMerchantMessageMapRecord($merchant_id,$base_format);
    }

    function checkForValidMerchantMessageMapRecord($merchant_id,$base_format)
    {
        $sql = "SELECT * FROM Merchant_Message_Map WHERE merchant_id = $merchant_id AND message_format LIKE '$base_format%' AND logical_delete = 'N'";
        $options[TONIC_FIND_BY_SQL] = $sql;
        if ($message_map_resource = Resource::find(new MerchantMessageMapAdapter(getM()),null, $options)) {
            return true;
        } else {
            return false;
        }
    }

	function getNonRecentlyCalledInDevicesOtherThanGPRS($minutes)
    {
        $now = time();
        $threshold_time = $now - ($minutes*60);
        $sql = "SELECT a.* FROM device_call_in_history a JOIN Merchant b ON a.merchant_id = b.merchant_id WHERE a.last_call_in_as_integer < $threshold_time AND a.auto_turned_off = 0 AND b.active = 'Y' AND b.ordering_on = 'Y' AND b.logical_delete = 'N'";
        $options[TONIC_FIND_BY_SQL] = $sql;
        $late_call_in_resources = Resource::findAll($this,null,$options);
        $results = [];
        foreach ($late_call_in_resources as $late_call_in_resource) {
            // validate that there is an active record in merchant_message_map table
            $merchant_id = $late_call_in_resource->merchant_id;
            $base_format = $late_call_in_resource->device_base_format;
            if ($this->noActiveMerchantMessageMapRecord($merchant_id,$base_format)) {
                // first delete call-in record
                $delete_sql = "DELETE FROM device_call_in_history WHERE merchant_id = $merchant_id AND device_base_format = '$base_format'";
                $this->_query($delete_sql);
                //$this->delete("".$late_call_in_resource->merchant_id);

                // now skip to the next record
                continue;
            }
            switch ($late_call_in_resource->device_base_format) {
                case 'P' : {
                    // Admin Portal
                    $results['portal'][] = $late_call_in_resource;
                } break;
                case 'H' : {
                    // china IP
                    $results['china_ip'][] = $late_call_in_resource;
                } break;
                case 'W' : {
                    // win app
                    $results['win_app'][] = $late_call_in_resource;
                } break;
                case 'S' : {
                    // epson ip
                    $results['epson_ip'][] = $late_call_in_resource;
                } break;
                case 'U' : {
                    // foundry
                    $results['foundry'][] = $late_call_in_resource;
                } break;
                case 'R' : {
                    // start ip printer
                    $results['star_ip'][] = $late_call_in_resource;
                }
            }
        }
        return $results;
    }
}
?>