<?php

class LookupAdapter extends MySQLAdapter
{

	function LookupAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Lookup',
			'%([0-9]{4,10})%',
			'%d',
			array('lookup_id'),
			null,
			array('created','modified')
			);
		
		$this->allow_full_table_scan = true;
						
	}
	
	function &select($url, $options = NULL)
    {
		$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    	return parent::select($url,$options);
	}
	
	static function staticGetValueFromTypeAndName($type_id_field,$type_id_name)
	{
		$lua = new LookupAdapter($mimetypes);
		return $lua->getValueFromTypeAndName($type_id_field, $type_id_name);
	}
	
	function getValueFromTypeAndName($type_id_field,$type_id_name)
	{
		if ($record = $this->getRecord(array("type_id_field"=>$type_id_field,"type_id_name"=>$type_id_name))) {
			return $record['type_id_value'];
		}
	}
	
	static function staticGetNameFromTypeAndValue($type_id_field,$type_id_value)
	{
		$lua = new LookupAdapter($mimetypes);
		return $lua->getNameFromTypeAndValue($type_id_field, $type_id_value);
	}
	
	function getNameFromTypeAndValue($type_id_field,$type_id_value)
	{
		if ($record = $this->getRecord(array("type_id_field"=>$type_id_field,"type_id_value"=>$type_id_value))) {
			return $record['type_id_name'];
		}
	}

}
?>