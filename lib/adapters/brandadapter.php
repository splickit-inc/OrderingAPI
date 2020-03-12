<?php

class BrandAdapter extends MySQLAdapter
{

	function BrandAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Brand2',
			'%([0-9]{3,10})%',
			'%d',
			array('brand_id') /*,
			array('brand_id','brand_name','active','allows_tipping','allows_in_store_payments','brand_external_identifier','cc_processor_username','cc_processor_password','created','modified',
				'logical_delete','loyalty','use_loyalty_lite', 'last_orders_displayed', 'nutrition_data_link', 'nutrition_flag') */
			);
		
		$this->allow_full_table_scan = true;
						
	}
	
	function &select($url, $options = array())
    {
    	$splickit_cache = new SplickitCache();
    	if (isset($options[TONIC_FIND_BY_METADATA]['brand_id']) && $options[TONIC_FIND_BY_METADATA]['brand_id']  > 0) {
            $brand_caching_string = "brand-".$options[TONIC_FIND_BY_METADATA]['brand_id'];
            if ($brand = $splickit_cache->getCache($brand_caching_string)) {
                return [$brand];
            }
		}
        $options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    	if ($brand = parent::select($url,$options)) {
            $expires_in_seconds = 36000; // 10 hours expiration
            $splickit_cache->setCache("brand-".$brand[0]['brand_id'],$brand[0],$expires_in_seconds);
            return $brand;
		}
	}

	function update(&$resource)
    {
        if (parent::update($resource)) {
        	SplickitCache::deleteCacheFromKey("brand-".$resource->brand_id);
        	return true;
		} else {
        	return false;
		}
    }
}
?>
