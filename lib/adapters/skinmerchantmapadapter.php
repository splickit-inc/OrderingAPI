<?php

class SkinMerchantMapAdapter extends MySQLAdapter
{

	function SkinMerchantMapAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Skin_Merchant_Map',
			'%([0-9]{3,10})%',
			'%d',
			array('map_id')
			);
	}
	
	static function createSkinMerchantMapRecord($merchant_id,$skin_id)
	{
		$smm_adapter = new SkinMerchantMapAdapter($mimetypes);
		$smm_data['skin_id'] = $skin_id;
		$smm_data['merchant_id'] = $merchant_id;
		$smm_resource = Resource::factory($smm_adapter,$smm_data);
		if ($smm_resource->save()) {
			return $smm_resource;
		} else {
			return returnErrorResource("couldn't create merchant skin map. " . $smm_adapter->getLastErrorText());
		}
	}

	function getRestrictedSkinThatMerchantIsMemberOf($merchant_id)
    {
        foreach ($this->getAllRestrictiveSkins() as $restrictive_skin) {
            if ($this->isMerchantInSkin($restrictive_skin['skin_id'],$merchant_id)) {
                myerror_log("we have a match for restrictive skin: ".$restrictive_skin['skin_name'],1);
                return $restrictive_skin;
            }
        }
        return false;
    }

	function getAllRestrictiveSkins()
    {
        $skin_adapter = new SkinAdapter(getM());
        $data = ['mobile_app_type'=>'R'];
        return $skin_adapter->getRecords($data);
    }
	
	function &select($url, $options = NULL)
    {
    	$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    	return parent::select($url,$options);
    }

	function getMerchantIdListForSkin($skin_id)
	{
        $options[TONIC_JOIN_STATEMENT] = " JOIN Merchant ON Merchant.merchant_id = Skin_Merchant_Map.merchant_id ";
        if (isset($options[TONIC_FIND_BY_STATIC_METADATA])) {
            $options[TONIC_FIND_BY_STATIC_METADATA] .= " AND Merchant.logical_delete = 'N' ";
        } else {
            $options[TONIC_FIND_BY_STATIC_METADATA] = " Merchant.logical_delete = 'N' ";
        }

        $data['merchant_id'] = array('>' => 1000);
        $data['skin_id'] = $skin_id;
		$records = $this->getRecords($data,$options);
		$merchants = [];
		foreach ($records as $record) {
            $merchants[] = $record['merchant_id'];
		}
		return $merchants;
	}

	static function isMerchantInSkin($skin_id,$merchant_id)
    {
        $smma = new SkinMerchantMapAdapter(getM());
        if ($record = $smma->getRecord(array("skin_id"=>$skin_id,"merchant_id"=>$merchant_id))) {
            return true;
        } else {
            return false;
        }
    }
	
}
?>