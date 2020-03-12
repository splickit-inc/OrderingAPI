<?php

class PaymentMerchantApplicationsAdapter extends MySQLAdapter
{

	function PaymentMerchantApplicationsAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'payment_merchant_applications',
			'%([0-9]{4,15})%',
			'%d',
			array('id'),
			null,
            array('created','modified')
			);

        $this->allow_full_table_scan = true;
	}
		
}
?>