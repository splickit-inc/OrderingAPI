<?php

class ExecuteObjectFunctionActivity extends SplickitActivity
{
	
	function ExecuteObjectFunctionActivity($activity_history_resource)
	{
		$this->activity_history_resource = $activity_history_resource;
		parent::SplickitActivity($activity_history_resource);
	}

	function doit() {
        ini_set('max_execution_time',300);
		$class_name = $this->data['object'];
		$method_to_execute = $this->data['method'];
		$function_data_string = $this->data['thefunctiondatastring'];
		if ($log_level = $this->data['log_level']) {
			$this->log_level = $log_level;
			$_SERVER['log_level'] = $log_level;
		}
		myerror_log("In ExecuteObjectFunctionActivity, we are about to call ".$class_name."->".$method_to_execute."(".$function_data_string.")");
		
		$class = new $class_name();
		try {
			if (method_exists($class, $method_to_execute)) {
				return $class->$method_to_execute($function_data_string);
			} else {
				throw new UndefinedActivityMethodException($class, $method);
			}
		} catch (UndefinedActivityMethodException $e) {
			myerror_log("We have an exception thrown in ExecuteObjectFunctionActivity: ".$e->getMessage()."!  ".$class_name."->".$method_to_execute);
			MailIt::sendErrorEmail("Error In ExecuteObjectFunctionActivity", $e->getMessage());
			return false;
		} catch (Exception $e) {
			myerror_log("We have an exception thrown in ExecuteObjectFunctionActivity: ".$e->getMessage()."!  ".$class_name."->".$method_to_execute);
			MailIt::sendErrorEmail("Error In ExecuteObjectFunctionActivity", $e->getMessage()."    executing: ".$class_name."->".$method_to_execute);
			throw $e;
		}
	}
}

class UndefinedActivityMethodException extends Exception
{
    public function __construct($class,$method) { 
        parent::__construct("ExecuteObjectFunctionActivity called but no method exists. class: $class  ->   method: $method ");   
    }
}

?>