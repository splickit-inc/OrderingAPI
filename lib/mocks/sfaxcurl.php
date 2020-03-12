<?php
class SFaxCurl extends SplickitCurl
{
	
	static function curlIt($url,$postData)
	{
		//HTTP/1.1 100 Continue\r\n\r\nHTTP/1.1 200 OK\r\nCache-Control: no-cache\r\nPragma: no-cache\r\nContent-Type: application/json; charset=utf-8\r\nExpires: -1\r\nServer: Microsoft-IIS/8.0\r\nX-AspNet-Version: 4.0.30319\r\nSet-Cookie: XDEBUG=; path=/\r\nP3P: CP="NOI OUR PSA DEV PSD STA COM CUR"\r\nDate: Thu, 26 Sep 2013 00:23:01 GMT\r\nContent-Length: 118\r\n\r\n{"SendFaxQueueId":"E5F20808CC99452193121E0F74774E78","isSuccess":true,"message":"Fax is received and being processed"}
		
		$u = explode('&', $url);
		$fn = explode('=',$u[2]);
		$fax_number = $fn[1];
		
		if (substr_count($u[0], 'SendFaxStatus') > 0)
		{
			if ($fn[1] == 'goodsend')
			{
				$response['raw_result'] = '{"RecipientFaxStatusItems":[{"SendFaxQueueId":"goodsend","IsSuccess":true,"ResultCode":0,"ErrorCode":0,"ResultMessage":"OK","RecipientName":"Splickit Order","RecipientFax":"1-7204384799","TrackingCode":"","FaxDateUtc":"2013-09-25T22:16:21Z","FaxId":2130925221621997110,"Pages":1,"Attempts":1}],"isSuccess":true,"message":"Fax request is complete"}';
			}
			else if ($fn[1] == 'failedsend')
			{
				$response['raw_result'] = '{"RecipientFaxStatusItems":[{"SendFaxQueueId":"failedsend","IsSuccess":false,"ResultCode":6300,"ErrorCode":28025, "ResultMessage": "VoiceLine","RecipientName":"GeneFry","RecipientFax":"15125551212","TrackingCode":"GFry1234","FaxDateUtc":"2013-05-08T18:10:20Z","FaxId":2130508180940997005, "Pages":2, "Attempts":1}],"isSuccess":true,"message":"Fax request is complete"}';
			}
			else 
			{
				//HTTP/1.1 200 OK\r\nCache-Control: no-cache\r\nPragma: no-cache\r\nContent-Type: application/json; charset=utf-8\r\nExpires: -1\r\nServer: Microsoft-IIS/8.0\r\nX-AspNet-Version: 4.0.30319\r\nSet-Cookie: XDEBUG=; path=/\r\nP3P: CP="NOI OUR PSA DEV PSD STA COM CUR"\r\nDate: Thu, 26 Sep 2013 01:03:39 GMT\r\nContent-Length: 82\r\n\r\n{"RecipientFaxStatusItems":[],"isSuccess":true,"message":"Processing fax request"}
				$response['raw_result'] = '{"RecipientFaxStatusItems":[],"isSuccess":true,"message":"Processing fax request"}';
			}
			$response['http_code'] = 200;
			$response['curl_info']['http_code'] = 200;
		}
		else if (substr_count($u[0], 'badurl') > 0)
		{
			//HTTP/1.1 100 Continue\r\n\r\nHTTP/1.1 404 Not Found\r\nContent-Type: text/html\r\nServer: Microsoft-IIS/8.0\r\nP3P: CP="NOI OUR PSA DEV PSD STA COM CUR"\r\nDate: Thu, 26 Sep 2013 00:29:42 GMT\r\nContent-Length: 1245\r\n\r\nDocument Here\r\n
			$response['raw_result'] = 'HTTP/1.1 100 Continue\r\n\r\nHTTP/1.1 404 Not Found\r\nContent-Type: text/html\r\nServer: Microsoft-IIS/8.0\r\nP3P: CP="NOI OUR PSA DEV PSD STA COM CUR"\r\nDate: Thu, 26 Sep 2013 00:29:42 GMT\r\nContent-Length: 1245\r\n\r\nDocument Here\r\n';
			$response['http_code'] = 404;
			$response['curl_info']['http_code'] = 404;
		}
		else if (substr_count($u[0], 'httxps://api.sfaxme.com/api/') > 0)
		{
			$response['error'] = 'Protocol httxps not supported or disabled in libcurl';
		}
		else if ($fax_number == '17204x84799')
		{
			//HTTP/1.1 100 Continue\r\n\r\nHTTP/1.1 200 OK\r\nCache-Control: no-cache\r\nPragma: no-cache\r\nContent-Type: application/json; charset=utf-8\r\nExpires: -1\r\nServer: Microsoft-IIS/8.0\r\nX-AspNet-Version: 4.0.30319\r\nSet-Cookie: XDEBUG=; path=/\r\nP3P: CP="NOI OUR PSA DEV PSD STA COM CUR"\r\nDate: Thu, 26 Sep 2013 00:23:01 GMT\r\nContent-Length: 118\r\n\r\n{"SendFaxQueueId":"-1","isSuccess":false,"message":"Invalid fax number(s): 17204x84799"}
			$response['raw_result'] = '{"SendFaxQueueId":"-1","isSuccess":false,"message":"Invalid fax number(s): 17204x84799"}';
			$response['http_code'] = 200;
			$response['curl_info']['http_code'] = 200;
		}
		else
		{
			//HTTP/1.1 100 Continue\r\n\r\nHTTP/1.1 200 OK\r\nCache-Control: no-cache\r\nPragma: no-cache\r\nContent-Type: application/json; charset=utf-8\r\nExpires: -1\r\nServer: Microsoft-IIS/8.0\r\nX-AspNet-Version: 4.0.30319\r\nSet-Cookie: XDEBUG=; path=/\r\nP3P: CP="NOI OUR PSA DEV PSD STA COM CUR"\r\nDate: Thu, 26 Sep 2013 00:23:01 GMT\r\nContent-Length: 118\r\n\r\n{"SendFaxQueueId":"E5F20808CC99452193121E0F74774E78","isSuccess":true,"message":"Fax is received and being processed"}
			$queued_id = generateCode(10);
			$response['raw_result'] = '{"SendFaxQueueId":"'.$queued_id.'","isSuccess":true,"message":"Fax is received and being processed"}';
			$response['http_code'] = 200;
			$response['curl_info']['http_code'] = 200;
		}
		
		return $response;
	}
}
?>