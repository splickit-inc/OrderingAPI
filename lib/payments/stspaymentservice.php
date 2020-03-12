<?php
class StsPaymentService extends SplickitPaymentService
{
    var $balance_change_resource_for_stored_value_payment;
    var $skin_sts_info_maps_adapter;
    var $skin_sts_info_map;
    var $api_key;
    var $url;

    const USER_DOES_NOT_HAVE_A_CARD_ON_RECORD_ERROR = "User does not have a stored value card on record";
    const CARD_NUMBER_IS_INVALID_ERROR_MESSAGE = "Error! This card number does not appear to be valid, please check your entry.";
    const SPLICKIT_ACCEPTED_PAYMENT_TYPE_ID_FOR_STS = 12000;

    function __construct($data)
    {
        parent::__construct($data);
        $this->url = getProperty('sts_url');
        $this->skin_sts_info_maps_adapter = new SkinStsInfoMapsAdapter(getM());
        if ($record = $this->skin_sts_info_maps_adapter->getStsInfo(getSkinIdForContext())) {
            $this->skin_sts_info_map = $record;
        } else {
            throw new NonStsMerchantException($this->merchant_id);
        }
    }

    function processPayment($amount)
    {
        if ($account_number = $this->getBillingUsersAccountNumber()) {
            if ($this->skin_sts_info_map) {
                $xml = $this->getChargeCardXML($account_number,$amount);
                $payment_results = $this->curl($xml);
            } else {
                myerror_log("ERROR!!!!!!   Merchant does NOT have an STS info record ");
                $payment_results['Respnose_Code'] = '01';
                $payment_results['Response_Text'] = "Could not get Merchant_STS_Info_Record";
            }
        } else {
            myerror_log("Could not get local stored value record to process stored value fcard or user_id: ".$this->billing_user_id);
            $payment_results['Respnose_Code'] = '01';
            $payment_results['Response_Text'] = self::USER_DOES_NOT_HAVE_A_CARD_ON_RECORD_ERROR;
        }
        $payment_results = $this->parseXMlResponse($payment_results);
        return $this->processStsResults($payment_results);
    }

    function parseXMlResponse($payment_results)
    {
        //$this->curl_response = $response;
        $raw_result_as_array = array();
        if ($raw_result = $payment_results['raw_result']) {
            $raw_result_as_array = parseXMLintoHashmap($raw_result);
        }
        return array_merge($payment_results,$raw_result_as_array);
    }


    function processStsResults($payment_results)
    {
        // if successfull set the transaction_id,processor_used,authcode

        if ($payment_results['Response_Code'] == '00') {
            $payment_results['status'] = 'success';
            $payment_results['response_code'] = 100;
            $payment_results['transactionid'] = $payment_results['Transaction_ID'];
            $payment_results['authcode'] = $payment_results['Auth_Reference'];
            return $payment_results;
        } else {
            return $this->processStsError($payment_results);
        }
    }

    function processStsError($payment_results)
    {
        $payment_results['status'] = 'failure';
        if ($message = $payment_results['Response_Text']) {
            $payment_results['response_text'] = $message;
        } else {
            myerror_log("some unknown error processing STS stored value card");
            MailIt::sendErrorEmailAdam("uncaught stored value processing error message", "check logs to find out what to create in StsPaymentService->processStsError: ".logData($payment_results, 'sts return'));
        }
        return $payment_results;
    }

    function getBillingUsersAccountNumber()
    {
        $billing_user_id = $this->billing_user_id;
        $user_skin_stored_value_maps_adapter = new UserSkinStoredValueMapsAdapter(getM());
        return $user_skin_stored_value_maps_adapter->getCardNumberForUserSkinPaymentTypeCombination($billing_user_id,getSkinIdForContext(),12000);
    }

    function recordOrderTransactionsInBalanceChangeTable($order_resource,$payment_results)
    {
        $balance_change_adapter = new BalanceChangeAdapter(getM());
        $balance_change_resource_for_order = parent::recordOrderTransactionsInBalanceChangeTable($order_resource);
        $balance_after_order = $balance_change_resource_for_order->balance_after;
        $ending_balance_after_stored_value_charge = $balance_after_order + $this->amount;
        $auth_reference_transaction_id = $payment_results['authcode'].':'.$payment_results['transactionid'];
        if ($bc_resource = $balance_change_adapter->addStoredValueRow($this->billing_user_id, $balance_after_order, $this->amount, $ending_balance_after_stored_value_charge, $this->name, $order_resource->order_id, $auth_reference_transaction_id ,'payment with stored value Card')) {
            $this->balance_change_resource_for_stored_value_payment = $bc_resource;
            $this->setUsersBalance($ending_balance_after_stored_value_charge);
            myerror_log("successful insert of primary order record balance change row");
        } else {
            myerror_log("ERROR!  FAILED TO ADD ROW TO BALANCE CHANGE TABLE");
            MailIt::sendErrorEMail('Error thrown in PlaceOrderController','ERROR*****************  FAILED TO ADD ROW TO BALANCE CHANGE TABLE, user_id = '.$order_resource->user_id.', AFTER RUNNING HEARTLAND STORED VALUE AND UPDATING BALANCE: '.$balance_change_adapter->getLastErrorText());
        }
    }

    function addCardToUserAccount($card_number,$user_id,$skin_id)
    {
        // check that card number is valid
        $response = $this->getCardBalance($card_number);
        if ($response['status'] == 'success') {
            $user_skin_stored_value_maps_adapter = new UserSkinStoredValueMapsAdapter(getM());
            if ($card_resource = $user_skin_stored_value_maps_adapter->saveCardNumber($card_number,$user_id,$skin_id,self::SPLICKIT_ACCEPTED_PAYMENT_TYPE_ID_FOR_STS)) {
                $card_resource->balance = $response['Amount_Balance'];
                $card_resource->save();
                return $card_resource;
            } else {
                $error_resource = createErrorResourceWithHttpCode("There was an internal error and we were not able to add your card. Engineering has been alerted",500);
                return $error_resource;
            }
        } else {
            $error_resource = createErrorResourceWithHttpCode(self::CARD_NUMBER_IS_INVALID_ERROR_MESSAGE,422);
            return $error_resource;
        }
    }

    function addCashValueToCard($card_number,$amount)
    {
        $xml = $this->getAddCashValueToCardXML($card_number,$amount);
        $payment_results = $this->parseXMlResponse($this->curl($xml));
        return $this->processStsResults($payment_results);
    }

    function voidTransaction($auth_reference,$transaction_id)
    {
        $xml = $this->getVoidTransactionXML($auth_reference,$transaction_id);
        $payment_results = $this->parseXMlResponse($this->curl($xml));
        return $this->processStsResults($payment_results);

    }


    function getCardBalance($account_number)
    {
        $xml = $this->getBalanceXML($account_number);
        $payment_results = $this->parseXMlResponse($this->curl($xml));
        return $this->processStsResults($payment_results);
    }

    function curl($xml)
    {
        $payment_results = StsCurl::curlIt($this->url,$xml);
        return $payment_results;
    }

    function getAddCashValueToCardXML($user_account_number,$amount)
    {
        $xml = $this->getRootXMl().'<Action_Code>02</Action_Code><Trans_Type>N</Trans_Type><POS_Entry_Mode></POS_Entry_Mode><Card_Number>'.$user_account_number.'</Card_Number><Transaction_Amount>'.$amount.'</Transaction_Amount></Request>';
        return $xml;
    }

    function getActivateNewCardXML($amount)
    {
        return $this->getAddCashValueToCardXML('NewAccountRQ',$amount);
    }


    function getBalanceXML($user_account_number)
    {
        $xml = $this->getRootXMl().'<Action_Code>05</Action_Code><Trans_Type>N</Trans_Type><POS_Entry_Mode>S</POS_Entry_Mode><Card_Number>'.$user_account_number.'</Card_Number></Request>';
        return $xml;
    }

    function getVoidTransactionXML($auth_reference,$transaction_id)
    {
        $xml = $this->getRootXMl().'<Action_Code>11</Action_Code><Trans_Type>L</Trans_Type><POS_Entry_Mode>M</POS_Entry_Mode><Auth_Reference>'.$auth_reference.'</Auth_Reference><Transaction_ID>'.$transaction_id.'</Transaction_ID></Request>';
        return $xml;
    }

    function getChargeCardXML($user_account_number,$amount)
    {
        $xml = $this->getRootXMl().'<Action_Code>19</Action_Code><Trans_Type>N</Trans_Type><POS_Entry_Mode>S</POS_Entry_Mode><Card_Number>'.$user_account_number.'</Card_Number><Transaction_Amount>'.$amount.'</Transaction_Amount></Request>';
        return $xml;
    }

    function getRootXMl()
    {
        $root_xml = '<Request><Merchant_Number>'.$this->getMerchantNumber().'</Merchant_Number><Terminal_ID>'.$this->getTerminalId().'</Terminal_ID><API_Key>'.$this->getApiKey().'</API_Key>';
        return $root_xml;
    }

    function getMerchantNumber()
    {
        return $this->skin_sts_info_map['merchant_number'];
    }

    function getTerminalId()
    {
        return $this->skin_sts_info_map['terminal_id'];
    }

    function getApiKey()
    {
        return $this->skin_sts_info_map['api_key'];;
    }


}

class NonStsMerchantException extends Exception
{
    public function __construct($merchant_id)
    {
        parent::__construct("Non STS merchant.  merchant_id = $merchant_id", 999);
    }
}

class STSCardException extends Exception
{
    public function __construct($message)
    {
        parent::__construct($message, 999);
    }

}

?>