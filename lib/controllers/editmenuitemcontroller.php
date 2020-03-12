<?php
require_once 'lib'.DIRECTORY_SEPARATOR.'adapters'.DIRECTORY_SEPARATOR.'itemadapter.php';
require_once 'lib'.DIRECTORY_SEPARATOR.'adapters'.DIRECTORY_SEPARATOR.'itemsizeadapter.php';
require_once 'lib'.DIRECTORY_SEPARATOR.'adapters'.DIRECTORY_SEPARATOR.'sizeadapter.php';

class EditMenuItemController extends SplickitController
{
	
	function EditMenuItemController($mt,$u,$r,$l = 0)
	{
		parent::SplickitController($mt,$u,$r,$l);
		$this->adapter = new ItemAdapter($this->mimetypes);
	}
	
	function getItem()
	{
	}
}

?>