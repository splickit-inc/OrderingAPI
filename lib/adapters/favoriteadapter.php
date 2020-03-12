<?php

class FavoriteAdapter extends MySQLAdapter
{

	function FavoriteAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Favorite',
			'%([0-9]{3,15})%',
			'%d',
			array('favorite_id'),
			NULL,
			array('created','modified')
		);
	}
	
	function &select($url, $options = NULL)
	{
		$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
		return parent::select($url,$options);
	}

	function delete($url)
	{
		if (preg_match('%/([0-9]{2,10})%', $url, $matches)) {
			$favorite_id = $matches[1];
			$sql = "DELETE FROM Favorite WHERE favorite_id = $favorite_id";
			try {
				$this->_query($sql);
				return true;
			} catch (Exception $exception) {
				myerror_log("Error deleting Favorite record for favorite_id=$favorite_id: " . $this->getLastErrorText());
			}
		} else {
			myerror_log("Error deleting Favorite record to this $url");
		}
		return false;
	}
}
?>
