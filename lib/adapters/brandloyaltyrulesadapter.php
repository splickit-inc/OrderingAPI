<?php

class BrandLoyaltyRulesAdapter extends MySQLAdapter
{

	function BrandLoyaltyRulesAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Brand_Loyalty_Rules',
			'%([0-9]{3,10})%',
			'%d',
			array('brand_loyalty_rules_id'),
			null,
			array('created','modified')
			);
		
		//$this->allow_full_table_scan = true;
						
	}
	
	function &select($url, $options = NULL)
    {
		$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    	return parent::select($url,$options);
	}
	
	static function getBrandLoyaltyRulesForContext()
	{
		if (isBrandLoyaltyOn()) {
			$brand_loyalty_rules_adapter = new BrandLoyaltyRulesAdapter($m);
			return $brand_loyalty_rules_adapter->getRecord(array("brand_id"=>getBrandIdFromCurrentContext()));
		}
	}

}
?>