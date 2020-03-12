<?php

class MerchantTaskRetailInfoMapAdapter extends MySQLAdapter {

	function MerchantTaskRetailInfoMapAdapter($mimetypes)
    {
		parent::MysqlAdapter(
			$mimetypes,
			'Merchant_TaskRetail_Info_Maps',
			'%([0-9]{4,10})%',
			'%d',
			array('id')
			);
		
		$this->allow_full_table_scan = true;
	}
	
	function &select($url, $options = NULL)
    {
		$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
        return parent::select($url,$options);
	}

	function getV2Info($merchant_id)
    {
        if ($merchant_id > 1000) {
            return $this->getMapRecordForMerchantId($merchant_id);
        } else {
            myerror_log("ERROR no merchant id submitted to MerchantTaskReatailMapsAdapter.getV2Info($merchant_id)");
            return null;
        }
    }

    function getMapRecordForMerchantId($merchant_id)
    {
        $record = $this->getRecord(['merchant_id'=>$merchant_id]);
        return $record;
    }

	function getConfigSettingsForTaskRetailService($merchant_id)
    {
		$options[TONIC_FIND_BY_METADATA]['merchant_id'] = $merchant_id;
		$data = $this->select($url, $options);
		$data = array_pop($data);
		$settings = array();

		if(isset($data['task_retail_url']) && $data['task_retail_url'] != ""){
			$settings['task_retail_url'] = $data['task_retail_url'];
		}else{
			$settings['task_retail_url'] = false;
		}

		if(isset($data['task_retail_auth_url']) && $data['task_retail_auth_url'] != ""){
			$settings['task_retail_auth_url'] = $data['task_retail_auth_url'];
		}else{
			$settings['task_retail_auth_url'] = false;
		}

		if(isset($data['task_retail_username']) && $data['task_retail_username'] != ""){
			$settings['task_retail_username'] = $data['task_retail_username'];
		}else{
			$settings['task_retail_username'] = false;
		}

		if(isset($data['task_retail_password']) && $data['task_retail_password'] != ""){
			$settings['task_retail_password'] = $data['task_retail_password'];
		}else{
			$settings['task_retail_password'] = false;
		}

		return $settings;
	}
}
?>