<?php

class MenuUpsellItemMapsAdapter extends MySQLAdapter
{

	function MenuUpsellItemMapsAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Menu_Upsell_Item_Maps',
			'%([0-9]{1,10})%',
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
	
	function getUpsellItemsForMenu($menu_id,$active) {
		$data['menu_id'] = $menu_id;
		if ($active == 'Y') {
			$data['active'] = 'Y';
		}
		return $this->getRecords($data);
	}

}
?>