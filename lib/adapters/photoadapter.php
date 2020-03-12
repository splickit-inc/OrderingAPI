<?php

class PhotoAdapter extends MySQLAdapter {

	function PhotoAdapter($mimetypes) {		
		parent::MysqlAdapter(
			$mimetypes,
			'Photo',
			'%([0-9]{3,10})%',
			'%d',
			array('id'),
			array('id','item_id','url','width','height')
			);

		$this->allow_full_table_scan = true;
        $this->log_level = 0;
	}
	
	static function findForItem($item_id) {
        $options[TONIC_FIND_BY_METADATA]['item_id'] = $item_id;
        $pa = new PhotoAdapter($m);
        return $pa->select('', $options);
	}
}
?>