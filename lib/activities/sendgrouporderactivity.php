<?php

class SendGroupOrderActivity extends SplickitActivity
{
	protected $server;

    /**
     * @var GroupOrderController
     */
	protected $group_order_controller;
	
	function SendGroupOrderActivity($activity_history_resource)
	{
		myerror_log("Creating SendGroupOrderActivity");
		parent::SplickitActivity($activity_history_resource);
	}

	function doit()
	{
		$group_order_id = $this->data['group_order_id'];
		if ($group_order_resource = Resource::find(new GroupOrderAdapter($mimetypes),''.$group_order_id)) {
			if ($group_order_resource->group_order_type == 1) {
				$god_data['group_order_id'] = $group_order_id;
				$god_options[TONIC_FIND_BY_METADATA] = $god_data;
				if ($group_order_details = Resource::findAll(new GroupOrderDetailAdapter($mimetypes),null,$god_options)) {
					$order_data['user_id'] = $group_order_resource->admin_user_id;
					$user_resource = Resource::find(new UserAdapter($mimetypes),''.$group_order_resource->admin_user_id);
					$user = $user_resource->getDataFieldsReally();

					$order_data['merchant_id'] = $group_order_resource->merchant_id;
					$order_data['note'] = $group_order_resource->notes;
					$order_data['tip'] = $group_order_resource->tip;
					$place_order_controller = new PlaceOrderController($mt, $user, $r,$_SERVER['log_level']);
                    $place_order_controller->setOrderAndMerchantByUcid($group_order_resource->group_order_token);
					$place_order_controller->setGroupOrderRecord($group_order_resource->getDataFieldsReally());
                    $order_resource = $place_order_controller->newPlaceOrderFromLoadedOrder($order_data);
					myerror_log("about to show the return from the internal place group order activity");
					Resource::encodeResourceIntoTonicFormat($order_resource);
					if ($order_resource->order_id > 1000) {
						return $order_resource->order_id;
					} else {
						return false;
					}
				}
			} else if ($group_order_resource->group_order_type == 2) {
                $user = getStaticRecord(array("user_id"=>$group_order_resource->admin_user_id),"UserAdapter");
				$this->group_order_controller = new GroupOrderController($m,$user,$r,5);
				$this->group_order_controller->submit_from_activity = true;
        		return $this->group_order_controller->sendGroupOrderByGroupOrderResource($group_order_resource);
			} else {
				myerror_log("no matching group order type for: ".$group_order_resource->group_order_type);
				return false;
			}
		}
	}

    function markActivityFailed()
    {
        if (strtolower($this->group_order_controller->group_order_resource->status) == 'submitted') {
            return $this->cancelActivity();
        } else if (strtolower($this->group_order_controller->group_order_resource->status) == 'cancelled') {
            return parent::markActivityFailedWithoutEmail();
        } else {
            return parent::markActivityFailed();
        }

    }
}