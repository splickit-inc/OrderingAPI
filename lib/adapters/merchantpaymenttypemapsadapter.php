<?php

class MerchantPaymentTypeMapsAdapter extends MySQLAdapter
{
	var $billing_entity_record;
	var $merchant_payment_type_map_record;

	function __construct($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Merchant_Payment_Type_Maps',
			'%([0-9]{4,10})%',
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

	/**
	 * 
	 * @desc will create a record in the MerchantPaymentTypeMaps table
	 * @param int $merchant_id
	 * @param int $splickit_accepted_payment_type_id
	 * @param int $billing_entity_id
	 * @return Resource
	 */
	static function createMerchantPaymentTypeMap($merchant_id,$splickit_accepted_payment_type_id,$billing_entity_id)
	{
		$mpt_adapter = new MerchantPaymentTypeMapsAdapter($mimetypes);
		$mpt_data['merchant_id'] = $merchant_id;
		$mpt_data['splickit_accepted_payment_type_id'] = $splickit_accepted_payment_type_id;
		$mpt_data['billing_entity_id'] = $billing_entity_id;
		if ($mpt_resource = Resource::createByData($mpt_adapter, $mpt_data)) {
			return $mpt_resource;
		} else {
			return returnErrorResource("could not create merchant payment type map: ".$mpt_adapter->getLastErrorText());
		}
	}
	
	static function validateCashForMerchantId($merchant_id)
	{
		$mptma = new MerchantPaymentTypeMapsAdapter($mimetypes);
		if ($record = $mptma->getRecord(array('merchant_id'=>$merchant_id,'splickit_accepted_payment_type_id'=>1000))) {
			return true;
		} else {
			return false;
		}
	}
	
	static function getMerchantPaymentTypes($merchant_id)
	{
		$mpta = new MerchantPaymentTypeMapsAdapter($mimetypes);
		return $mpta->getRecords(array("merchant_id"=>$merchant_id), $options);
	}
	
	function getMerchantPaymentTypeMapFromIdWithBillingEntityIfItExists($merchant_payment_type_map_id)
	{
		if ($merchant_payment_type_map_record = $this->getRecordFromPrimaryKey($merchant_payment_type_map_id)) {
			$this->merchant_payment_type_map_record = $merchant_payment_type_map_record;
			if ($billing_entity_id = $merchant_payment_type_map_record['billing_entity_id']) {
				$this->billing_entity_record = $this->getBillingEntityFromBillingEntityId($billing_entity_id);
				$merchant_payment_type_map_record['billing_entity_record'] = $this->billing_entity_record;
			}
			return $merchant_payment_type_map_record;
		}
	}
	
	function getBillingEntityFromBillingEntityId($billing_entitiy_id)
	{
		return BillingEntitiesAdapter::staticGetRecordByPrimaryKey($billing_entitiy_id, "BillingEntitiesAdapter");
	}
}
?>