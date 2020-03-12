<?php 
/*
 * @desc This is actually ONLY the MessagerControllerFactory.  it was named incorrectly.
 * @author radamnyc
 */
final Class ControllerFactory {
	
	/**
	 * 
	 * Enter description here ...
	 * @param $string
	 * @param $mimetypes
	 * @param $user
	 * @param $request
	 * @param $log_level
	 * 
	 * @return MessageController
	 */
	public static function generateFromUrl($string, $mimetypes, $user, $request, $log_level) 
	{
			$string = '/'.strtolower($string).'/';
			myerror_logging(4,"trying to generate controller from url: ".$string);
		
			if (substr_count($string,'/fax/') > 0)
				$name =  'Fax';
			else if (substr_count($string,'/ivr/') > 0)
				$name = 'Ivr';
			else if (substr_count($string,'/email/') > 0)
				$name = 'Email';
			else if (substr_count($string,'/windowsservice/') > 0)
				$name = 'WindowsService';
			else if (substr_count($string,'/winapp/') > 0)
				$name = 'WindowsService';
			else if (substr_count($string,'/gprs/') > 0)
				$name = 'Gprs';
			else if (substr_count($string,'/rewardr/') > 0)
				$name =  'RewarderEvent';
			else if (substr_count($string,'/ping/') > 0)
				$name =  'Ping';
			else if (substr_count($string,'/text/') > 0)
				$name =  'Text';
			else if (substr_count($string,'/opie/') > 0)
				$name =  'Opie';
			else if (substr_count($string,'/qube/') > 0)
				$name =  'Qube';
			else if (substr_count($string,'/json/') > 0)
				$name =  'Json';
			else if (substr_count($string,'/jerseymikes/') > 0)
				$name =  'JerseyMikes';
			else if (substr_count($string,'/dblog/') > 0)
				$name =  'DbLog';
			else if (substr_count($string,'/curl/') > 0)
				$name =  'Curl';
            else if (substr_count($string,'/epsonprinter/') > 0)
                $name =  'EpsonPrinter';
            else if (substr_count($string,'/starmicros/') > 0)
                $name =  'StarmicrosPrinter';
            else if (substr_count($string,'/chinaipprinter/') > 0)
				$name =  'ChinaIPPrinter';
			else if (substr_count($string,'/pushmessage/') > 0)
				$name =  'PushMessage';
            else if (substr_count($string, '/taskretail/') > 0)
                $name = 'TaskRetail';
            else if (substr_count($string, '/micros/') > 0)
                $name = 'Micros';
			else if (substr_count($string, '/brink/') > 0)
				$name = 'Brink';
			else if (substr_count($string, '/xoikos/') > 0)
				$name = 'Xoikos';
			else if (substr_count($string, '/foundry/') > 0)
				$name = 'Foundry';
			else if (substr_count($string, '/vivonet/') > 0)
				$name = 'Vivonet';
			else if (substr_count($string, '/emagine/') > 0)
				$name = 'Emagine';
			else
			{
				myerror_logging(4, "No format recogized in ControllerFactory. Returning False.  Submitted url/string: ".$string);
				return false;
			}

			setLogLevelForObjectNameIfExists($name);
			$class_name = $name . "Controller";
			$class = new $class_name($mimetypes, $user, $request, $log_level);
			myerror_logging(3,"controller generated from url: ".$string);
			return $class;
	}
	
	/**
	 * 
	 * Enter factory method to obtain the correct controller for message sending.  will return boolean false if no controller matching type is found.
	 * 
	 * @param Resource $message_resource
	 * @param Mimetypes $mimetypes
	 * @param Array $user
	 * @param Request $request
	 * @param int $log_level
	 * 
	 * @return MessageController
	 */

	public static function generateFromMessageResource($message_resource, $mimetypes, $user, $request, $log_level) 
	{
		if ($name = ControllerFactory::getControllerNameFromMessageResource($message_resource))
		{
			$class_name = $name . "Controller";
			$class = new $class_name($mimetypes, $user, $request, $log_level);
			$class->message_resource = $message_resource;
			return $class;
		}
		myerror_log('FATAL ERROR in smaw_message_dispatch! FORMAT NOT RECOGNIZED!  No controller matching type: '.substr($message_resource->message_format, 0,1));
		return false;
	}
	
	public static function getControllerNameFromMessageResource($message_resource)
	{
		// this shoudl probably be loaded from the db	
		$mesage_formats = array('F'=>'Fax',
							'E'=>'Email',
							'I'=>'Ivr',
							'O'=>'Opie',
							'G'=>'Gprs',
							'Q'=>'Qube',
							'T'=>'Text',
							'W'=>'WindowsService',
							'R'=>'StarmicrosPrinter',
							'J'=>'Json',
							'P'=>'Ping',
							'D'=>'DbLog',
							'Y'=>'PushMessage',
							'K'=>'JerseyMikes',
							'C'=>'Curl',
							'S'=>'EpsonPrinter',
							'H'=>'ChinaIPPrinter',
              				'A'=>'TaskRetail',
                            'M'=>'Micros',
                            'B'=>'Brink',
							'X'=>'Xoikos',
							'U'=>'Foundry',
							'V'=>'Vivonet',
							'N' => 'Emagine'
		);
		
		$format = substr($message_resource->message_format, 0,1);
		myerror_logging(1, "the format in controller factory is: ".$format);
		if ($name = $mesage_formats[$format])
			return $name;
		myerror_log("No controller name registered for passed in type: ".$message_resource->message_format);
		return false;
	}
	
/*	
	private static function getController($name,$mimetypes, $user, $request, $log_level)
	{
		if ($name == null || $name == '')
		{
			myerror_log('FATAL ERROR in controller factory! no format submitted in getController');
			die ('FATAL ERROR in controller factory! no format submitted in getController');
		}	
		$class_name = $name . "Controller";
		$class = new $class_name($mimetypes, $user, $request, $log_level);
		return $class;
	}
		
	private static function getTypeFromUrl($string)
	{
		if (substr_count($string,'/fax/') > 0)
			return  'Fax';
		else if (substr_count($string,'/ivr/') > 0)
			return 'Ivr';
		else if (substr_count($string,'/email/') > 0)
			return 'Email';
		else if (substr_count($string,'/windowsservice/') > 0)
			return 'WindowsService';
		else if (substr_count($string,'/winapp/') > 0)
			return 'WindowsService';
		else if (substr_count($string,'/gprs/') > 0)
			return 'Gprs';
		else if (substr_count($string,'/rewardr/') > 0)
			return  'RewarderEvent';
		else if (substr_count($string,'/ping/') > 0)
			return  'Ping';
		else if (substr_count($string,'/text/') > 0)
			return  'Text';
		else if (substr_count($string,'/opie/') > 0)
			return  'Opie';
		else if (substr_count($string,'/qube/') > 0)
			return  'Qube';
		else if (substr_count($string,'/json/') > 0)
			return  'Json';
		else
		{
			myerror_log('FATAL ERROR in smaw_message_dispatch! FORMAT NOT RECOGNIZED!  No controller matching type: '.$url_string);
			return null;
		}
	}
*/
}
?>