<?php

class SkinImagesMapAdapter extends MySQLAdapter
{

	function SkinImagesMapAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Skin_Images_Map',
			'%([0-9]{1,15})%',
			'%d',
			array('map_id')
			);
	}
	
	static function getSkinImagesRecordForCurrentContext()
	{
		$skin_images_map_adapter = new SkinImagesMapAdapter($this->mimetypes);
		if ($_SERVER['SKIN_ID']) {
			$skin_id = $_SERVER['SKIN_ID'];
		} else {
			$skin_id = 1;
		}
		$skin_images_record = $skin_images_map_adapter->getRecord(array("skin_id"=>$skin_id));
		return $skin_images_record;
	}
	
}
?>