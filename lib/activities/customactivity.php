<?php

class CustomActivity extends SplickitActivity
{
    /************
     *      custom activity for a one time procedure
     *      just rewrite the doit() function for what you need.
                                                            ************/
    function __construct($activity_history_resource)
    {
        parent::SplickitActivity($activity_history_resource);
    }

//    function doit()
//    {
//        $nuorder_importer = new NuorderImporter($this->data);
//        if ($menu_id = $nuorder_importer->importMenuData()) {
//            return true;
//        } else {
//            return false;
//        }


//    }

    function xxxdoit() {
        $sql = "XXXX select id, user_id, order_id, charge_amt, cc_processor, cc_transaction_id from (select *, count(*) as charges from Balance_Change where id > 18458159 and id < 18458995 and process = 'CCpayment' group by order_id, charge_amt ) z where charges > 1";
//        if (isLaptop()) {
//            $sql = "select * From Balance_Change where process = 'CCpayment' order by order_id desc";
//        }
//        $sql .= '  limit 2';
        $bcr_adapter = new BalanceChangeAdapter(getM());
        $bcr_adapter->log_level = 6;
        $options[TONIC_FIND_BY_SQL] = $sql;
        $resources = Resource::findAll($bcr_adapter,null,$options);
        myerror_log("BULK:: there were ".sizeof($resources)." records that needed to be refunded this way");
        $successes = 0;
        $failures = 0;
        foreach ($resources as $resource) {
            $balance_change_record_id = $resource->id;
            $order_id = $resource->order_id;
            myerror_log("BULK: record: ".json_encode($resource->getDataFieldsReally()));
            myerror_log("BULK:  we are trying to select the balance change row with an ID of: $balance_change_record_id");
            if ($balance_change_record = $bcr_adapter->getRecordFromPrimaryKey($balance_change_record_id)) {
                $base_order_data = CompleteOrder::getBaseOrderData($order_id);
                if ($merchant_resource = Resource::find(new MerchantAdapter(getM()),''.$base_order_data['merchant_id'],null)) {
                    $cc_functions = CreditCardFunctions::creditCardFunctionsFactory($balance_change_record['cc_processor']);
                    $return_fields = $cc_functions->creditVoidTransaction($balance_change_record, $balance_change_record['charge_amt'], $merchant_resource);
                    myerror_log("BULK:: refund results from refund activity: ".json_encode($return_fields));
                    if ($return_fields['status'] == 'success') {
                        $update_sql = "UPDATE Balance_Change set `process` = 'CCpaymentREFUNDED' where id = $balance_change_record_id LIMIT 1";
                        $bcr_adapter->_query($update_sql);
                        $successes++;
                        continue;
                    }
                } else {
                    myerror_log("BULK:: couldnt get merchant resource for order_id: $order_id");
                }
            } else {
                myerror_log("BULK:: couldn't get balance change record for order_id: $order_id");
            }
            $failures++;
        }
        myerror_log("BULK:: THERE were $successes successful refunds  and    $failures  failures");
        }
}
?>