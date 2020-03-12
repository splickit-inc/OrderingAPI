<?php
class ElavonProcessor extends SplickitProcessor
{

    function defineDataDefinition()
    {
        $this->data_definition = array("my_virtual_merchant_id","my_virtual_merchant_user_id","my_virtual_merchant_pin");
    }

}
?>