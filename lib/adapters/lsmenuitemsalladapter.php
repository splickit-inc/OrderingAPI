<?php

class LsMenuItemsAllAdapter extends MySQLAdapter
{

	function LsMenuItemsAllAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'ls_menuitems_all'
			);
	}
	
}
?>
