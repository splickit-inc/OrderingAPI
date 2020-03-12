<?php
/**
 * 
 * processors are the different ways credit cards can be used through VIO.
 *
	currently supported
	
	- Sage
	- Mercury
	- FPN
	- Jersey Mikes
		- for sending the order payload to JM with teh CC info
	- Heartland
		- for sending CC info for auto reload of stored value cards
		- running cards (future)Enter description here ...
 * @author radamnyc
 *
 */

abstract class SplickitProcessor
{
	var $data_definition;
	
	function __construct()
	{
		$this->defineDataDefinition();
	}
	
	function getVIOPayload($data)
	{
		foreach ($this->data_definition as $field) {
			if (isset($data[$field])) {
				$payload[$field] = $data[$field];
			} else {
				throw new BadCredentialDataPassedInException($field);
			}
		}
		return $payload;
	}

	function getFields()
	{
		return $this->data_definition;
	}
	
	abstract function defineDataDefinition();
	
}

class BadCredentialDataPassedInException extends Exception
{
	function __construct($field_name)
	{
		parent::__construct("A value for $field_name was NOT contained in the setup data", 999);
	}
	
}
?>