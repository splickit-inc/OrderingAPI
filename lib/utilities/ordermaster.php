<?php
/**
 * Created by PhpStorm.
 * User: radamnyc
 * Date: 02/16/17
 * Time: 9:21 AM
 */
class OrderMaster extends Order
{
    function __construct($order_id)
    {
        if ($order_id == null) {
            throw new Exception("No Order Id submitted to Order object");
        }
        $this->adapter = new OrderAdapter($mimetypes);
        $this->adapter->setWriteDb();
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
        $this->adapter->unsetWriteDb();
    }

}
?>