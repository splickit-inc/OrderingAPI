<?php

class UserSkinStoredValueMapsAdapter extends MySQLAdapter
{
    var $stored_value_record;

    function __construct($mimetypes)
    {
        parent::MysqlAdapter(
            $mimetypes,
            'User_Skin_Stored_Value_Maps',
            '%([0-9]{4,15})%',
            '%d',
            array('id'),
            null,
            array('created','modified')
        );

        $this->allow_full_table_scan = true;

    }

    function &select($url, $options = NULL)
    {
        $options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
        return parent::select($url,$options);
    }


    function getCardNumberForUserSkinPaymentTypeCombination($user_id,$skin_id,$splickit_accepted_payment_type_id)
    {
        if ($record = $this->getRecord(["user_id"=>$user_id,"skin_id"=>$skin_id,"splickit_accepted_payment_type_id"=>$splickit_accepted_payment_type_id])) {
            return $record['card_number'];
        } else {
            return null;
        }
    }

    function saveCardNumber($card_number,$user_id,$skin_id,$splickit_accepted_payment_type_id)
    {
        $data = ['user_id'=>$user_id,'skin_id'=>$skin_id,'splickit_accepted_payment_type_id'=>$splickit_accepted_payment_type_id];
        if ($resource = Resource::findOrCreateIfNotExistsByData($this,$data)) {
            $resource->card_number = $card_number;
            $resource->save();
            return $resource;
        }
    }

    function insert(&$resource)
    {
        // get payment service
        $splickit_accepted_payment_type_id = $resource->splickit_accepted_payment_type_id;
        $stored_value_payment_service = PaymentGod::getPaymentServiceBySplickitAcceptedPaymentTypeId($splickit_accepted_payment_type_id);

        // if card number exists  check balance

        // if card number doesn't exist create it on the remote system.

        return parent::insert($resource);
    }

    function getCardNumber($user_id,$skin_id,$splickit_accepted_payment_type_id)
    {
        $data = [];
        $data['user_id'] = $user_id;
        $data['skin_id'] = $skin_id;
        $data['splickit_accepted_payment_type_id'] = $splickit_accepted_payment_type_id;
        if ($record = $this->getRecord($data)) {
            $this->stored_value_record = $record;
            return $record['card_number'];
        }
    }

    function getStoredValueRecord()
    {
        return $this->stored_value_record;
    }
}
?>