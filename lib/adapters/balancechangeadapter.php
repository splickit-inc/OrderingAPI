<?php

class BalanceChangeAdapter extends MySQLAdapter
{

	function BalanceChangeAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Balance_Change',
			'%([0-9]{1,15})%',
			'%d',
			array('id'),
			null,
			array('created','modified')
			);
        $this->setWriteDb();
	}

	function getRecordsByOrderId($order_id)
    {
        return $this->getRecordsForOrderId(['order_id'=>$order_id]);
    }

	function getRecordsForOrderId($data)
	{
		$options[TONIC_FIND_BY_METADATA] = $data;
		$results = $this->select(null,$options);
		return $results;
	}
	
	function getCurrentRecordForVoidOrRefund($data)
	{
		$options[TONIC_SORT_BY_METADATA] = 'id ASC';
		if ($records = $this->getRecords($data,$options)) {
			return array_pop($records);
		}
	}

	function addOrderRow($user_id,$balance_before,$charge_amt,$balance_after,$order_id,$notes)
	{
		return $this->addRow($user_id, $balance_before, $charge_amt, $balance_after, 'Order', $cc_processor, $order_id, $cc_transacction_id, $notes);
	}

	function addAuthorizeRow($user_id,$balance_before,$authorize_amt,$cc_processor,$order_id,$cc_transaction_id,$notes)
	{
		return $this->addRow($user_id, $balance_before, $authorize_amt, $balance_before, 'Authorize', $cc_processor, $order_id, $cc_transaction_id, $notes);
	}
	
	function addCCRow($user_id,$balance_before,$charge_amt,$balance_after,$cc_processor,$order_id,$cc_transaction_id,$notes)
	{
		return $this->addRow($user_id, $balance_before, $charge_amt, $balance_after, 'CCpayment', $cc_processor, $order_id, $cc_transaction_id, $notes);
	}
	
	function addStoredValueRow($user_id,$balance_before,$charge_amt,$balance_after,$stored_value_payment_service,$order_id,$sv_transaction_id,$notes)
	{
		return $this->addRow($user_id, $balance_before, $charge_amt, $balance_after, 'StoredValuePayment', $stored_value_payment_service, $order_id, $sv_transaction_id, $notes);
	}
	
	function addGiftRow($user_id,$balance_before,$charge_amt,$balance_after,$order_id,$notes,$double_billing_amount)
	{
		$text = 'GIFT USED';
		if ($double_billing_amount > 0.00) {
			$text = 'GIFTandCC';
			$charge_amt = $charge_amt + $double_billing_amount;
			$balance_after = $balance_after + $double_billing_amount;
		}
		return $this->addRow($user_id,$balance_before,$charge_amt,$balance_after,$text,$cc_processor,$order_id,$cc_transacction_id,$notes.$text);
	}
	
	function addRow($user_id,$balance_before,$charge_amt,$balance_after,$process,$cc_processor,$order_id,$cc_transacction_id,$notes) 
	{
		$balance_change_data['user_id'] = $user_id;
		$balance_change_data['balance_before'] = $balance_before;
		$balance_change_data['charge_amt'] = $charge_amt;
		$balance_change_data['balance_after'] = $balance_after;
		$balance_change_data['process'] = $process;
		$balance_change_data['cc_processor'] = $cc_processor;
		$balance_change_data['order_id'] = $order_id;
		$balance_change_data['cc_transaction_id'] = $cc_transacction_id;
		$balance_change_data['notes'] = $notes;
		
		return Resource::createByData($this, $balance_change_data);
	}
	
	static function staticAddRow($user_id,$balance_before,$charge_amt,$balance_after,$process,$cc_processor,$order_id,$cc_transacction_id,$notes)
	{
		$bca = new BalanceChangeAdapter(getM());
		return $bca->addRow($user_id, $balance_before, $charge_amt, $balance_after, $process, $cc_processor, $order_id, $cc_transacction_id, $notes);
	}

	function getAuthorizeRow($order_id)
    {
        $data = ['order_id'=>$order_id,"process"=>'authorize'];
        if ($bc_records = $this->getRecords($data)) {
            return array_pop($bc_records);
        }
    }
}
?>