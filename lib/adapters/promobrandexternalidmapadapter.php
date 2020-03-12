<?php

class PromoBrandExternalIdMapAdapter extends MySQLAdapter
{

	function PromoBrandExternalIdMapAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Promo_Brand_External_Id_Map',
			'%([0-9]{4,10})%',
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
	
	static function staticGetExternalIdForPromoBrandMaping($promo_id,$brand_id)
	{
		$pbeima = new PromoBrandExternalIdMapAdapter($mimetypes);
		return $pbeima->getExternalIdForPromoBrandMaping($promo_id, $brand_id);
	}
	
	function getExternalIdForPromoBrandMaping($promo_id,$brand_id)
	{
		if ($record = $this->getRecord(array("promo_id"=>$promo_id,"brand_id"=>$brand_id))) {
			return $record['external_id'];
		}
	}
	
}
?>