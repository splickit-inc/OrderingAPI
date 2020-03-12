<?php

class UserSessionAdapter extends MySQLAdapter
{

	function UserSessionAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'User',
			'%^/([0-9]+)%',
			'%d',
			array('user_id'),
			array('user_id', 'uuid', 'first_name', 'last_name', 'email','balance','points_lifetime','points_current','orders','trans_fee_override','flags')
			);
	}
	
	function insert($resource)
	{
		return false;
	}
	
	function update($resource)
	{
		return false;
	}
	
	function &select($url, $options = NULL)
    {
    	$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    	$data = parent::select($url,$options);
    	foreach ($data as $user_id=>$user)
    	{
				$faveAdapter = new FavoriteAdapter($mimetypes);
				$options2[TONIC_FIND_BY_METADATA] = array('user_id'=>$user_id);
				$faves = $faveAdapter->select('/faves/',$options2);
				if (sizeof($faves)>0)
					;//$data[$user_id]['faves']=$faves;
				
				$order_adapter = new OrderAdapter($mimetypes);
				$options3[TONIC_FIND_BY_SQL] = 'SELECT a.merchant_id, b.merchant_type FROM `Orders` a, Merchant b WHERE a.merchant_id = b.merchant_id AND user_id = '.$user_id.' ORDER BY a.created desc LIMIT 1';
				$merch = $order_adapter->select('merch',$options3);
				if (sizeof($merch) > 0)
				{
					$merch = array_pop($merch);
					$merchant_id = $merch['merchant_id'];	
					$merchant_type = $merch['merchant_type'];				
					$merchant_adapter = new MerchantAdapter($mimetypes);
					$merchantInfo = $merchant_adapter->select('/merchants/'.$merchant_id);
					$merchantInfo = array_pop($merchantInfo);
					
					// now get lead time, tax, and trans fee override if it exists.
					
					$tax = $merchant_adapter->getTax($merchant_id);
					$merchantInfo['tax_rate'] = $tax;
					
					$lead_time = $merchant_adapter->getLeadTime($merchant_id);
					$merchantInfo['lead_time'] = $lead_time;

					if ($override_value = $user['trans_fee_override'])
						$merchantInfo['customer_trans_fee'] = $override_value;
					unset($data[$user_id]['trans_fee_override']);
						
					$merchantInfo['menu']=$merchant_adapter->getMenuItemPrices($merchant_id);
					$merchantInfo['modifiers']=$merchant_adapter->getModifierPrices($merchant_id, $merchant_type);
					$data[$user_id]['homeMerchInfo']=$merchantInfo;		
						
				}
    	}
    	return $data;
    }
}
?>
