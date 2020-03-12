<?php
/**
 * Created by PhpStorm.
 * User: radamnyc
 * Date: 10/26/16
 * Time: 9:21 AM
 */
class Order
{
    private $current_order_item_type_counts;

    /**
     * @var Resource
     */
    protected $order_resource;

    private $item_info;
    private $catering_info;
    protected $skip_hours = false;

    var $adapter;

    var $checkout_fields = array('ucid','merchant_id','order_amt','promo_code','promo_amt','promo_amt','promo_tax_amt','total_tax_amt','trans_fee_amt','delivery_amt','tip_amt','customer_donation_amt','grand_total','note','order_qty');

    function __construct($order_id)
    {
        if ($order_id == null) {
            throw new Exception("No Order Id submitted to Order object");
        }
        $this->adapter = new OrderAdapter($mimetypes);
        if (is_numeric($order_id)) {
            $options[TONIC_FIND_BY_METADATA]['order_id'] = $order_id;
        } else {
            $options[TONIC_FIND_BY_METADATA]['ucid'] = $order_id;
        }
        if ($order_resource = Resource::find($this->adapter,null,$options)) {

            $this->order_resource = $order_resource;
            $order_resource_as_json_string = json_encode($order_resource->getDataFieldsReally());
            if (stripos($order_resource_as_json_string, 'skip hours') !== false || $this->get('user_id') == 2) {
                $this->skip_hours = true;
            }
            $this->setItemInfo();
        } else {
            throw new NoMatchingOrderIdException($order_id);
        }
        if ($this->isCateringOrder()) {
            $this->setCateringOrderIfItExists();
        }
    }

    function recalculateTotals()
    {
        $order_resource = $this->getCurrentDBOrderResource();
        $order_resource = $this->recalculateItemAmountsFromOrderDetailsOnOrderResource($order_resource);

        $order_resource->set('convenience_fee', $order_resource->trans_fee_amt);

        $this->setItemInfo();
        return $this->recalculateTotalsOnOrderResource($order_resource);
    }

    function recalculateTotalsOnOrderResource($order_resource)
    {
        $order_resource->total_tax_amt = $order_resource->item_tax_amt + $order_resource->promo_tax_amt + $order_resource->delivery_tax_amt;
        $order_resource->grand_total = $order_resource->order_amt + $order_resource->promo_amt + $order_resource->total_tax_amt + $order_resource->trans_fee_amt + $order_resource->delivery_amt + $order_resource->tip_amt + $order_resource->customer_donation_amt;
        $order_resource->grand_total_to_merchant = $this->order_resource->grand_total - $order_resource->tip_amt - $order_resource->customer_donation_amt - $order_resource->merchant_donation_amt;
        $order_resource->save();
        return new Order($this->getOrderId());
    }

    function recalculateItemAmountsFromOrderDetailsOnOrderResource($order_resource)
    {
        if ($order_resource == null) {
            $order_resource = $this->order_resource;
        }
        $order_id = $order_resource->order_id;
        $order_detail_adapter = new OrderDetailAdapter();
        $sql = "SELECT IFNULL(SUM(item_total_w_mods),0) AS order_amt,IFNULL(SUM(item_tax),0) AS item_tax_amt,IFNULL(SUM(quantity),0) as quantity FROM Order_Detail WHERE `order_id` = $order_id AND `logical_delete` = 'N'";
        $options[TONIC_FIND_BY_SQL] = $sql;
        $results = $order_detail_adapter->select(null,$options);

        // get fixed tax;
        if ($fixed_tax_record = getStaticRecord(array("merchant_id"=>$order_resource->merchant_id),'FixedTaxAdapter')) {
            $fixed_tax_amount = $fixed_tax_record['amount'];
        }

        $order_resource->order_amt = $results[0]['order_amt'];
        $order_resource->item_tax_amt = $results[0]['item_tax_amt']+$fixed_tax_amount;
        $order_resource->order_qty = $results[0]['quantity'];
        if ($order_resource->save()) {
            $this->order_resource = $order_resource;
            return $order_resource;
        }
    }

    function getCheckoutBaseOrderData()
    {
        $base_order_array = $this->order_resource->getDataFieldsReally();
        $base_checkout_data = array();
        foreach ($this->checkout_fields as $field_name) {
            $base_checkout_data[$field_name] = $base_order_array[$field_name];
        }
        // for backwards compatiblity
        $base_checkout_data['cart_ucid'] = $base_checkout_data['ucid'];

        return $base_checkout_data;
    }

    function isDeliveryOrder()
    {
        return $this->order_resource->order_type == OrderAdapter::DELIVERY_ORDER;
    }

    function setItemInfo()
    {
        $order_id = $this->order_resource->order_id;
        $sql = "SELECT a.*,b.item_id,b.size_id,b.tax_group,d.menu_type_id,d.cat_id,d.start_time,d.end_time ";
        $sql .= " FROM Order_Detail a ";
        $sql .= " JOIN Item_Size_Map b ON a.item_size_id = b.item_size_id ";
        $sql .= " JOIN Item c ON b.item_id = c.item_id ";
        $sql .= " JOIN Menu_Type d ON c.menu_type_id = d.menu_type_id ";
        $sql .= " WHERE a.order_id = $order_id AND a.item_size_id > 0 AND a.logical_delete = 'N'";
        $options[TONIC_FIND_BY_SQL] = $sql;
        $results = $this->adapter->select(null,$options);
        $this->item_info = $results;
        $this->current_order_item_type_counts = array();
        foreach ($results as $item_row) {
            $this->current_order_item_type_counts[$item_row['cat_id']] = $this->current_order_item_type_counts[$item_row['cat_id']] + 1;
            if ($this->skip_hours == false) {
                if (stripos($item_row['note'], 'skip hours') !== false) {
                    $this->skip_hours = true;
                }
            }
        }
    }

//    function setItemTypeCounts()
//    {
//        $order_id = $this->order_resource->order_id;
//        $elevel_sql = "SELECT d.cat_id as type, COUNT(a.order_detail_id) AS number_of_items FROM Order_Detail a, Item_Size_Map b, Item c, Menu_Type d WHERE a.order_id = $order_id AND a.item_size_id = b.item_size_id AND b.item_id = c.item_id AND c.menu_type_id = d.menu_type_id AND a.logical_delete = 'N' AND d.menu_type_id > 0 GROUP BY d.cat_id";
//        $elevel_options[TONIC_FIND_BY_SQL] = $elevel_sql;
//        $results = $this->adapter->select('',$elevel_options);
//        foreach ($results as $menu_type_count) {
//            $this->current_order_item_type_counts[$menu_type_count['type']] = $menu_type_count['number_of_items'];
//        }
//    }

    function getNumberOfEntreLevelItems()
    {
        return $this->getNumberOfItemsOfMenuTypeInOrder('E');
    }

    function isOverSizedEntreOrder()
    {
        return $this->isPickupOrder() && $this->getNumberOfEntreLevelItems() > PlaceOrderController::BASE_NUMBER_ENTRE_LEVEL_ITEMS_FOR_NO_LEADTIME_LIMIT;
    }

    function getNumberOfExtraEntreLevelItems()
    {
        if ($this->isOverSizedEntreOrder()) {
            return $this->getNumberOfEntreLevelItems() - PlaceOrderController::BASE_NUMBER_ENTRE_LEVEL_ITEMS_FOR_NO_LEADTIME_LIMIT;
        } else {
            return 0;
        }
    }

    function isPickupOrder()
    {
        return $this->get('order_type') == OrderAdapter::PICKUP_ORDER;
    }

    function isCateringOrder()
    {
        if (sizeof($this->item_info) > 0) {
            return $this->getNumberOfCateringItems() > 0;
        } else {
            $this->setCateringOrderIfItExists();
            return $this->doesCateringOrderExist();
        }

    }

    function doesCateringOrderExist()
    {
        if ($this->catering_info) {
            return true;
        } else {
            return false;
        }
    }

    function setCateringOrderIfItExists()
    {
        $catering_orders_adapter = new CateringOrdersAdapter();
        if ($record = $catering_orders_adapter->getRecord(array("order_id"=>$this->getOrderId()))) {
            $this->catering_info = $record;
        }
    }




    function getNumberOfCateringItems()
    {
        return $this->getNumberOfItemsOfMenuTypeInOrder('C');
    }


    function getNumberOfItemsOfMenuTypeInOrder($menu_type)
    {
//        if ($this->current_order_item_type_counts == null) {
//            $this->setItemTypeCounts();
//        }
        if (isset($this->current_order_item_type_counts[$menu_type])) {
            return $this->current_order_item_type_counts[$menu_type];
        } else {
            return 0;
        }
    }

    function updateStamp()
    {
        if ($this->hasOrderBeenStampedAlready()) {
            return;
        } else {
            $stamp = getStamp().';'.$this->get('stamp');
            $order_id = $this->get('order_id');
            $sql = "UPDATE Orders SET `stamp` = '$stamp' WHERE order_id = $order_id LIMIT 1";
            $this->adapter->_query($sql);
        }

    }

    function hasOrderBeenStampedAlready()
    {
        $existing_stamp = $this->get('stamp');
        if (stripos($existing_stamp, getRawStamp()) !== false) {
            return true;
        }
    }

    function useDefaultLeadTimes()
    {
        return $this->skip_hours;
    }

    function skipHours()
    {
        return $this->skip_hours;
    }

    function get($parameter_name)
    {
        return $this->order_resource->$parameter_name;
    }

    function getOrderItemInfo()
    {
        return $this->item_info;
    }

    function getOrderItemTypeCounts()
    {
        return $this->current_order_item_type_counts;
    }

    function getCurrentDBOrderResource()
    {
        return Resource::find($this->adapter,"".$this->getOrderId());
    }

    function getOrderResource()
    {
        return $this->order_resource;
    }

    function getBaseOrderData()
    {
        return $this->order_resource->getDataFieldsReally();
    }

    function getCompleteOrder()
    {
        return CompleteOrder::staticGetCompleteOrder($this->getOrderId());
    }

    function getOrderId()
    {
        return $this->get('order_id');
    }

    function getUcid()
    {
        return $this->get('ucid');
    }

    function getUserId()
    {
        return $this->get('user_id');
    }

    function isOrderInSubmittedState()
    {
        return $this->order_resource->status == OrderAdapter::ORDER_SUBMITTED;
    }

    function isActiveStatus()
    {
        $status = strtoupper($this->get('status'));
        return ($status == OrderAdapter::ORDER_IS_A || $status == OrderAdapter::ORDER_IS_IN_PROCESS_CART || $status == OrderAdapter::ORDER_PAYMENT_FAILED );
    }

    function setSkipHours($b)
    {
        if ($b) {
            $this->skip_hours = true;
        } else {
            $this->skip_hours = false;
        }
    }

    function getCateringInfo()
    {
        return $this->catering_info;
    }

}

class NoMatchingOrderIdException extends Exception
{
    public function __construct($order_id)
    {
        parent::__construct("No matching order for order_id: $order_id", 500);
    }

}


?>