<?php
class PayeezyProcessor extends SplickitProcessor
{

    function defineDataDefinition()
    {
        $this->data_definition = array("terminal_gateway_id","terminal_password");
    }

}
?>