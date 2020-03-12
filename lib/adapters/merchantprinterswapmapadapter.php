<?php

class MerchantPrinterSwapMapAdapter extends MySQLAdapter
{

	function MerchantPrinterSwapMapAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Merchant_Printer_Swap_Map',
			'%([0-9]{2,10})%',
			'%d',
			array('merchant_id'),
			array('id','merchant_id','new_sms_no','live','created','modified'),
			array('created','modified')
			);
	}
	
}
?>