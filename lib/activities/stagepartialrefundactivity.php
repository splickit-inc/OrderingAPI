<?php

/**
 * @codeCoverageIgnore
 */
class StagePartialRefundActivity extends SplickitActivity
{
	
	function __construct($activity_history_resource)
	{
		$this->activity_history_resource = $activity_history_resource;
		parent::SplickitActivity($activity_history_resource);
	}

	function doit() {
		$order_id = $this->data['order_id'];
		$amount_to_refund = $this->data['amount_to_refund'];

		$order_controller = new OrderController($mt, $u, $r);
		return $order_controller->executeScheduledPartialOrderRefund($order_id,$amount_to_refund);
	}
}
?>