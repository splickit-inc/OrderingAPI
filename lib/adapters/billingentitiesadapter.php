<?php

class BillingEntitiesAdapter extends MySQLAdapter
{

	function BillingEntitiesAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Billing_Entities',
			'%([0-9]{4,10})%',
			'%d',
			array('id'),
			null,
			array('created','modified')
			);
		
		$this->allow_full_table_scan = false;
						
	}
	
	function &select($url, $options = NULL)
    {
		$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    	return parent::select($url,$options);
	}
	
	static function createBillingEntity($vio_credit_card_processor_id,$name_of_billing_entity,$description,$brand_id,$external_identifier,$credentials,$process_type = 'purchase')
	{
		$billing_entity_adapter = new BillingEntitiesAdapter($mimetypes);
		$data['vio_credit_card_processor_id'] = $vio_credit_card_processor_id;
		$data['name'] = $name_of_billing_entity;
		$data['description'] = $description;
		$data['brand_id'] = $brand_id;
		$data['external_id'] = $external_identifier;
		$data['process_type'] = $process_type;
		if (is_array($credentials)) {
			$credentials = createNameValuePairStringFromHashMap($credentials);
		}
		$data['credentials'] = $credentials;
		if ($billing_entity_resource = Resource::createByData($billing_entity_adapter, $data)) {
			return $billing_entity_resource;
		} else {
			return returnErrorResource("could not create billing entity: ".$billing_entity_adapter->getLastErrorText());
		}
	}
	
	static function getBillingEntityByExternalId($external_identifier)
	{
		$billing_entity_adapter = new BillingEntitiesAdapter($mimetypes);
		$data['external_id'] = $external_identifier;
        if (isNotProd()) {
            $records = $billing_entity_adapter->getRecords($data);
            return $records[0];
        } else {
            return $billing_entity_adapter->getRecord($data);
        }

	}
}
?>