<?php

use Aws\S3\S3Client;

class AWSService
{
	protected $client = null;
	
	function __construct() {
		$c = S3Client::factory(array(
			'key' => 'AKIAI5Z25ZEXUNJ3OXPA',
			'secret' => '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'
		));
		$this->client = $c;
	}
	
	function hasKey($bucket, $key) {
		return $this->doesObjectExist($bucket, $key); 
	}		
	
	function getKey($bucket, $key) {

		if (getProperty('do_not_call_out_to_aws') == 'true') {
			return null;
		} else if (fopen("http://www.google.com:80/","r")) {
			return $this->getOrElse($bucket, $key, null);
		} else {
			myerror_log("NO INTERNET CONNECTION!!!! CANT GET REMOTE TEMPLATES!!");
			return null;
		}

	}
	
	function getOrElse($bucket, $key, $default) {		
		if($this->hasKey($bucket, $key)) {
			return $this->getObject($bucket, $key);
		} else {
			return $default;
		}
	}

	function getObjectUrl($bucket, $key, $expires = null, array $args = array()){
			return $this->client->getObjectUrl($bucket,$key, $expires, $args);
	}
	
	protected function doesObjectExist($bucket, $key) {
		return $this->client->doesObjectExist($bucket, $key);
	}
	
	protected function getObject($bucket, $key) {
		$object = $this->client->getObject(array(
				'Bucket' => $bucket,
				'Key' => $key
		));
		
		return $object->get("Body");
	}
}

?>