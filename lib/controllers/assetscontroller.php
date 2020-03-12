<?php
	class AssetsController {		
		function getAsset($request) {
			$bucket = null;
			$key = null;
			
			if($request->data) {
				$bucket = $request->data['bucket'];
				$key = $request->data['key'];	
			}			
			
			if($bucket == null || $key == null) {
				return null;
			} else {				
				$aws = new AWSService();
				$r = $aws->getKey($bucket, $key);
				$response = new Response(200, $r['Body']);	
			 	$response->headers['Content-Type'] = $this->getContentTypeFor($key);
				return $response;
			}	
		}
		
		private function getContentTypeFor($key) {		
			if (strpos($key,'html') !== false) {
				return 'text/html';
			} else if (strpos($key,'png') !== false) {
				return 'image/png';
			} else if (strpos($key,'jpg') !== false) {
				return 'image/jpeg';
			} else {
				return 'text/plain';
			}
		}
	}
?>