<?php
class SecureNetProcessor extends SplickitProcessor
{

	function defineDataDefinition()
	{
		$this->data_definition = array("secure_net_id","secure_key");
	}


}
?>