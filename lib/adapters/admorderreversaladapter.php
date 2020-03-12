<?php

class AdmOrderReversalAdapter extends MySQLAdapter
{

	function AdmOrderReversalAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'adm_order_reversal',
			'%([0-9]{3,10})%',
			'%d',
			array('id')
		);
	}
	
	static function checkOrderForPreviousReversal($order_id)
	{
		// see if there is a row in the adm_order_reversal table
		$aor_adapter = new AdmOrderReversalAdapter($mimetypes);
		$aor_options[TONIC_FIND_BY_METADATA]['order_id'] = $order_id;
		if ($reversal_record = Resource::find($aor_adapter,null,$aor_options))
			return true;
		else
			return false;
		
	}

	function checkForAlreadyInProcessRefundAndInitiateIfNot($order_id,$refund_amt,$note)
	{
		// see if there is a row in the adm_order_reversal table
		if ($reversal_record = $this->getRecord(array("order_id"=>$order_id))) {
			return true;
		} else {
			$this->addRow($order_id,$refund_amt,'X',$note,null);
			return false;
		}
	}
	
	function addRow($order_id,$amount,$credit_type,$note,$invoice)
	{
		$order_reversal_data['order_id'] = $order_id;
		$order_reversal_data['amount'] = $amount;
		$order_reversal_data['credit_type'] = $credit_type;
		$order_reversal_data['note'] = $note;
		return Resource::createByData($this, $order_reversal_data);
	}

	function completeRefund($order_id,$amount,$credit_type,$note,$invoice)
	{
		$options[TONIC_FIND_BY_METADATA]['order_id'] = $order_id;
		$options[TONIC_FIND_BY_METADATA]['credit_type'] = 'X';
		if ($resource = Resource::find($this,null,$options)) {
			$resource->amount = $amount;
			$resource->credit_type = $credit_type;
			$resource->note = $note;
			$resource->invoice = $invoice;
			if ($resource->save()) {
				return $resource;
			}
		}
		throw new Exception("ERROR updating admin reversal table with refund info");
	}
	
	static function staticAddRow($order_id,$amount,$credit_type,$note,$invoice)
	{
		$adm_order_reversal_adapter = new AdmOrderReversalAdapter($mimetypes);
		return $adm_order_reversal_adapter->addRow($order_id,$amount,$credit_type,$note,$invoice);	
	}
	
}
?>