<?php

class UserBrandLoyaltyHistoryAdapter extends MySQLAdapter
{

	function __construct($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'User_Brand_Loyalty_History',
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
	
	/**
	 * 
	 * @desc used to record a loyalty transaction. positive points goes in the added, negative points goes in the redeemed.
	 * @param $user_id
	 * @param $brand_id
	 * @param $order_id
	 * @param $process
	 * @param $points
	 */
	function recordLoyaltyTransaction($user_id,$brand_id,$order_id,$process,$points,$current_points = 0,$current_balance = 0.00,$action_date = 0)
	{
        if ($action_date == 0) {
            $action_date = time();
        }
		$data['user_id'] = $user_id;
		$data['brand_id'] = $brand_id;
		$data['order_id'] = $order_id;
		$data['process'] = $process;
		$data['current_points'] = $current_points;
        $data['action_date'] = date('Y-m-d',$action_date);
		$data['current_dollar_balance'] = $current_balance;
        if ($process == LoyaltyController::ORDER_LABEL_FOR_HISTORY) {
            if ($base_order_data = CompleteOrder::getBaseOrderData($order_id, $mimetypes)) {
                $data['action_date'] = date('Y-m-d',$base_order_data['order_dt_tm']);
            }
        }
		if ( $points >= 0 ) {
			$data['points_added'] = $points;
		} else if ( $points < 0 ) {
			$data['points_redeemed'] = -$points;
		}
		$resource = Resource::factory($this, $data);
		if ($resource->save()) {
			return $resource;
		} else {
			$error = $this->getLastErrorText();
			myerror_log("we had an error inserting the loyalty history record: ".$error);
			recordError($error, "we had an error inserting the loyalty history record: ");
		}
	}
	
	function getLoyaltyHistoryForUserBrand($user_id,$brand_id)
	{
		$history = array();
		$options[TONIC_SORT_BY_METADATA] = ' action_date DESC, id DESC ';
		if ($records = $this->getRecords(array("user_id"=>$user_id,"brand_id"=>$brand_id),$options)) {
			$history = cleanData($records);
		}
		return $history;
	}

	function getLoyaltyHistoryByOrderId($order_id)
    {
        $options[TONIC_FIND_BY_METADATA]['order_id'] = $order_id;
        return Resource::findAll($this,null,$options);
    }

}
?>