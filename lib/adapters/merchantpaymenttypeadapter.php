<?php

class MerchantPaymentTypeAdapter extends MySQLAdapter
{

	function MerchantPaymentTypeAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Merchant_Payment_Type',
			'%([0-9]{1,15})%',
			'%d',
			array('id'),
			null,
			array('created')
			);
	}
	
	static function createMerchantPaymentTypeRecord($merchant_id,$payment_type)
	{
		$mpt_adapter = new MerchantPaymentTypeAdapter($mimetypes);
		$mpt_data['merchant_id'] = $merchant_id;
		$mpt_data['payment_type'] = $payment_type;
		$mpt_resource = Resource::factory($mpt_adapter,$mpt_data);
		if ($mpt_resource->save())
			return $mpt_resource;//all is good
		else
			return returnErrorResource("could not create merchant payment type map: ".$mpt_adapter->getLastErrorText());
	}
	
	function setCashForMerchant($merchant_id)
	{
		$mpt_data['merchant_id'] = $merchant_id;
		$mpt_data['payment_type'] = 'cash';
		$resource = Resource::factory($this,$mpt_data);
		$resource->save();
	}
	
	function deleteCashForMerchant($merchant_id)
	{
		if ($mpt_resource = $this->getMerchantCashMapResource($merchant_id))
		{
			$this->delete(''.$mpt_resource->id)     ;// do delete code
		}
		return true;
		
	}
	
	function isMerchantCash($merchant_id)
	{
		if ($mpt_resource = $this->getMerchantCashMapResource($merchant_id))
			return true;
		else
			return false;				
	}
	
	private function getMerchantCashMapResource($merchant_id)
	{
		$mpt_data['merchant_id'] = $merchant_id;
		$mpt_data['payment_type'] = 'cash';
		$mpt_options[TONIC_FIND_BY_METADATA] = $mpt_data;
		$mpt_adapter = new MerchantPaymentTypeAdapter($this->mimetypes);
		if ($mpt_resource = Resource::findExact($mpt_adapter,'',$mpt_options))
			return $mpt_resource;
		else
			return null;
		
	}
	
	function getMerchantPaymentTypes($merchant_id)
	{
		return $this->getRecords(array("merchant_id"=>$merchant_id), $options);
	}
	
}
?>