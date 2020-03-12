<?php

class LoyaltyBrandBehaviorAwardAmountMapsAdapter extends MySQLAdapter
{

	function LoyaltyBrandBehaviorAwardAmountMapsAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Loyalty_Brand_Behavior_Award_Amount_Maps',
			'%([0-9]{4,15})%',
			'%d',
			array('id'),
			null,
			array('created','modified')
			);
		
		$this->allow_full_table_scan = false;
						
	}
	
	function &select($url, $options = NULL)
    {
		$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    	return parent::select($url,$options);
	}

	static function getBrandBehaviorAwardRecords($brand_id,$get_active_only = 'Y')
    {
        $blbams = new LoyaltyBrandBehaviorAwardAmountMapsAdapter($m);
        $options[TONIC_JOIN_STATEMENT] = " JOIN Loyalty_Award_Brand_Trigger_Amounts ON Loyalty_Award_Brand_Trigger_Amounts.id = Loyalty_Brand_Behavior_Award_Amount_Maps.loyalty_award_brand_trigger_amounts_id JOIN Loyalty_Award_Trigger_Types ON Loyalty_Award_Trigger_Types.id = Loyalty_Award_Brand_Trigger_Amounts.loyalty_award_trigger_type_id ";
        $options[TONIC_FIND_STATIC_FIELD] = " Loyalty_Award_Brand_Trigger_Amounts.trigger_value, Loyalty_Award_Trigger_Types.trigger_name ";
        $data = array("brand_id"=>$brand_id);
        if ($get_active_only == 'Y') {
            $data['active'] = true;
        }
        return $blbams->getRecords($data,$options);
    }

}
?>