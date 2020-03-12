<?php

class AdmMerchantEmailAdapter extends MySQLAdapter
{

	function AdmMerchantEmailAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'adm_merchant_email',
			'%([0-9]{3,10})%',
			'%d',
			array('id')
		);
	}
}
?>