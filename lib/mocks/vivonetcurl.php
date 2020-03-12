<?php
class VivonetCurl extends SplickitCurl
{
    static function curlIt($url,$json,$headers)
    {
        if (substr_count($url,'/apiKeys/stores') > 0) {
            // Good request
            $response['raw_result'] = json_encode(array(
                    array(
                        "companyId" => 83422,
                        "companyName" => "Vivonet Certifications",
                        "storeId" => 161668,
                        "storeName" => "Vivonet Coffee Roasters"
                    ))
            );
            $response['http_code'] = 200;
        } else if (substr_count($url,'stores/1616688/orders/data') > 0) {
            $response['raw_result'] = '{"total":5.50,"charges":[{"amount":.50,"chargeId":1364464,"name":"State Tax"}],"orderLineItems":[{"remark":"","restricted":false,"productId":26669,"price":1.79,"quantityUnit":"16 oz","orderTypeId":0,"discounts":[],"ignorePrice":false,"quantity":1,"modifiers":[],"productName":"FRESH BREWED COFFEE","orderLineItemId":0,"onHold":false}],"discounts":[]}';
            $response['http_code'] = 200;
        } else if (substr_count($url,'stores/123456789/orders/data') > 0) {
            $response['raw_result'] = '{"total":2.12,"charges":[{"amount":0.16,"chargeId":1364464,"name":"State Tax"},{"amount":0.17,"chargeId":3604374,"name":"Food and Beverage"}],"orderLineItems":[{"remark":"","restricted":false,"productId":26669,"price":1.79,"quantityUnit":"16 oz","orderTypeId":0,"discounts":[],"ignorePrice":false,"quantity":1,"modifiers":[],"productName":"FRESH BREWED COFFEE","orderLineItemId":0,"onHold":false}],"discounts":[]}';
            $response['http_code'] = 200;
        } else if (substr_count($url,'orders') > 0 && !is_null($json)) { //place order
            $response['raw_result'] = json_encode(
                array(
                    "orderId" => 9873652,
                    "tabId" => "9873652",
                    "paymentTransactionConfirmations" => array(
                        "amount" => 10.26,
                        "tenderId" => 2,
                        "transactionTime" => "2016-01-23 10:15:45",
                        "transactionReferenceNumber" => "order-100",
                        "humanReadablePaymentMethod" => "",
                        "transactionTimeGmt" => 14557559,
                        "authorizationCode" => "code-123",
                        "receiptId" => "splickit-customer"
                    )
                )
            );

            $response['http_code'] = 200;
        } else if (substr_count($url,'tenders') > 0 && is_null($json)) {
            $response['raw_result'] = json_encode(
                array(
                    array( "tenderId" => 10016, "cash" => true, "tenderName" => "Cash" ),
                    array( "tenderId" => 10017, "cash" => false, "tenderName" => "Credit" ),
                    array( "tenderId" => 270045, "cash" => false, "tenderName" => "Visa" ),
                    array( "tenderId" => 270046, "cash" => false, "tenderName" => "Amex" ),
                    array( "tenderId" => 270047, "cash" => false, "tenderName" => "Mastercard" ),
                    array( "tenderId" => 1758597, "cash" => false, "tenderName" => "GC Redeemed" ),
                    array( "tenderId"=> 8368226, "cash" => false, "tenderName" => "Blackboard GC Redemd" ),
                    array( "tenderId"=> 8368227, "cash" => false, "tenderName" => "Blackboard Points" ),
                    array( "tenderId" => 8368229, "cash" => false, "tenderName" => "Blackboard CashEQ" ),
                    array( "tenderId" => 88888, "cash" => false, "tenderName" => "Outside Credit" ),
                    array( "tenderId" => 8372980, "cash" => false, "tenderName" => "Givex" ),
                    array( "tenderId" => 8372982, "cash" => false, "tenderName" => "Givex Loyalty" ),
                    array( "tenderId" => 8767339, "cash" => false, "tenderName" => "APIAccount" ),
                    array( "tenderId" => 8943529, "cash" => true, "tenderName" => "Auto Close Tender" )
                )
            );
            $response['http_code'] = 200;
        } else if (substr_count($url,'discounts') > 0 && is_null($json)) {
            $response['raw_result'] = '[{"discountType":"%","discountName":"Manager 100% Item","value":100,"discountId":1364580},{"discountType":"amount","discountName":"OCR BOGO","value":3.75,"discountId":7293245},{"discountType":"amount","discountName":"Gratuity","value":0,"discountId":89998},{"discountType":"amount","discountName":"Open $ Discount","value":0,"discountId":9649753},{"discountType":"%","discountName":"Open % Discount","value":0,"discountId":9649750},{"discountType":"%","discountName":"PC BOGO Super Bowl","value":100,"discountId":9540627},{"discountType":"amount","discountName":"PC-$1 OFF SMOOTH/FUS","value":1,"discountId":9540614},{"discountType":"%","discountName":"Q1-2","value":100,"discountId":9190357},{"discountType":"%","discountName":"Q2 Promo BOGO","value":100,"discountId":9536380}]';
            $response['http_code'] = 200;
        } else {
            $response['http_code'] = 500;
        }
        $response['curl_info']['url'] = $url;

        return $response;
    }
}
?>
