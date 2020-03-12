<?php

class LsItemsAllAdapter extends MySQLAdapter
{

	function LsItemsAllAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'ls_items_all'
			);
	}
	
}
?>
