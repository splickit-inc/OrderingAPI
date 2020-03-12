<?php

class MenuIntegrityErrorsAdapter extends MySQLAdapter
{

	function MenuIntegrityErrorsAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Menu_Integrity_Errors',
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
	
	static function recordMenuError($menu_id,$merchant_id,$error_text)
	{
		$miea = new MenuIntegrityErrorsAdapter($m);
		$data = array();
		$data['menu_id'] = $menu_id;
		$data['merchant_id'] = $merchant_id;
		$data['error'] = $error_text;
		$menu_error_resource = Resource::findOrCreateIfNotExistsByData($miea,$data);
		$menu_error_resource->running_count = $menu_error_resource->running_count + 1;
		$menu_error_resource->modified = time();
		$menu_error_resource->save();
		return $menu_error_resource;
	}

}
?>