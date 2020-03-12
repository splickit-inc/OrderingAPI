<?php
class ProcessorFactory
{
	/**
	 * 
	 * @desc given a type, will return the correct processor class
	 * @param string $type
	 * @return SplickitProcessor
	 */
	static function getProcessor($type)
	{
		$name = str_replace('-','',$type);
		$processor_name = $name."Processor";
		$processor_name_lower = strtolower($processor_name);

		include_once "lib".DIRECTORY_SEPARATOR."payments".DIRECTORY_SEPARATOR."processors".DIRECTORY_SEPARATOR.$processor_name_lower.".php";
    	// Check to see whether the include declared the class
    	if (class_exists($processor_name, false)) {
        	$class = new $processor_name();
        	return $class;
		} 					
	}
	
}
?>
