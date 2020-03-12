<?php

class TokenAuthenticationsAdapter extends MySQLAdapter
{

	function TokenAuthenticationsAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Token_Authentications',
			'%([0-9]{4,10})%',
			'%d',
			array('id'),
			null,
			array('created','modified')
			);
		
		$this->allow_full_table_scan = false;
        $this->log_level = 0;
	}
	
	function &select($url, $options = NULL)
    {
		$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    	return parent::select($url,$options);
	}

	static function clearExpiredTokensForActivity()
	{
		$taa = new TokenAuthenticationsAdapter($m);
		$records = $taa->clearExpiredTokens();
		// activity needs a true returned
		return true;
	}

	function clearExpiredTokens()
	{
		$time = time();
		$sql = "DELETE FROM Token_Authentications WHERE expires_at < $time";
		$result = $this->_query($sql);
		$records = $this->rows_updated;
		myerror_log("There were $records records deleted from the Token_Authentications table");
		return $records;
	}
}
?>