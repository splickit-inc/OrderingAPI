<?php

class SkinSplickitAcceptedPaymentTypeMapsAdapter extends MySQLAdapter
{

	function SkinSplickitAcceptedPaymentTypeMapsAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Skin_Splickit_Accepted_Payment_Type_Maps',
			'%([0-9]{4,15})%',
			'%d',
			array('id'),
			null,
			array('created','modified')
			);
	}
	
	function &select($url, $options = NULL)
    {
    	$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    	return parent::select($url,$options);
    }

    function addRecord($skin_id,$splickit_accepted_payment_type_id)
    {
        $data = ['skin_id'=>$skin_id,'splickit_accepted_payment_type_id'=>$splickit_accepted_payment_type_id];
        return Resource::createByData($this,$data);
    }

    static function staticAddRecord($skin_id,$splickit_accepted_payment_type_id)
    {
        $ssaptma = new SkinSplickitAcceptedPaymentTypeMapsAdapter(getM());
        return $ssaptma->addRecord($skin_id,$splickit_accepted_payment_type_id);

    }
}
?>