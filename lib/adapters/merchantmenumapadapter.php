<?php

class MerchantMenuMapAdapter extends MySQLAdapter
{

	function MerchantMenuMapAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Merchant_Menu_Map',
			'%^/merchantmenumaps/([0-9]+)%',
			'%d',
			array('map_id'),
			NULL,
			array('created')
			);
	}
	
	static function createMerchantMenuMap($merchant_id,$menu_id,$menu_type)
	{
		$merchant_menu_map_adapter = new MerchantMenuMapAdapter($mimetypes);
		$mmm_data = array();
		$mmm_data['merchant_id'] = $merchant_id;
		$mmm_data['menu_id'] = $menu_id;
		$mmm_data['merchant_menu_type'] = $menu_type;
		$options_mmm[TONIC_FIND_BY_METADATA] = $mmm_data;
		$mmm_resource = Resource::factory($merchant_menu_map_adapter,$mmm_data);
		if ($mmm_resource->save())
			return $mmm_resource;
		else
			return returnErrorResource("Error! Merchant Menu Map was not created: ".$merchant_menu_map_adapter->getLastErrorText());
	}
	
	static function getMenuIdFromMerchantIdAndType($merchant_id,$menu_type)
	{
		$mmma = new MerchantMenuMapAdapter(getM());
		if ($record = $mmma->getRecord(array("merchant_id"=>$merchant_id,"merchant_menu_type"=>$menu_type))) {
			return $record['menu_id'];
		}
	}

	static function getMerchantMenuMapsByOrderType($merchant_id)
    {
        $merchant_menu_map_adapter = new MerchantMenuMapAdapter(getM());
        $merchant_menu_maps = $merchant_menu_map_adapter->getRecords(['merchant_id'=>$merchant_id]);
        $merchant_menu_maps_hash = [];
        foreach ($merchant_menu_maps as $merchant_menu_map) {
            if (strtolower($merchant_menu_map['merchant_menu_type']) == 'pickup') {
                $merchant_menu_maps_hash['R'] = $merchant_menu_map;
            } else if (strtolower($merchant_menu_map['merchant_menu_type']) == 'delivery') {
                $merchant_menu_maps_hash['D'] = $merchant_menu_map;
            }
        }
        return $merchant_menu_maps_hash;
    }
	
	function &select($url, $options = NULL)
    {
    	$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    	return parent::select($url,$options);
    }
	
}
?>