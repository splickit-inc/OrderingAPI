<?php

class DocumentAdapter extends MySQLAdapter
{

	function DocumentAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Document',
			'%([0-9]{4,10})%',
			'%d',
			array('id'),
			null,
			array('created','modified')
			);
	}

	static function createRecordOnFileSystem($id)
	{
		$da = new DocumentAdapter($mimetypes);
		if ($document_resource = Resource::find($da,''.$id))
		{
			$file_name = $document_resource->file_name;
			$file_content = $document_resource->file_content;
			$file_name_w_path = "./reportfiles/$file_name";
			$result = file_put_contents($file_name_w_path, $file_content);
			if ($result !== false) {
				return $file_name_w_path;	
			}
		} 
		return false;
	}
}
?>