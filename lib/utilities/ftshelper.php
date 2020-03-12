<?php
class FTSHelper
{

    public function getHeaders($responseBody, $responseInfo)
    {
        $header_text = substr($responseBody, 0, $responseInfo['header_size']);
        
        $headers = array();
        foreach(explode("\n",$header_text) as $line)
        {
            $parts = explode(": ",$line);
            if(count($parts) == 2)
            {
                if (isset($headers[$parts[0]]))
                {
                    if (is_array($headers[$parts[0]])) $headers[$parts[0]][] = chop($parts[1]);
                    else $headers[$parts[0]] = array($headers[$parts[0]], chop($parts[1]));
                } else
                {
                    $headers[$parts[0]] = chop($parts[1]);
                }
            }
        }
        return $headers;        
    }

    public function getResponseDataAsArray($responseBody, $responseInfo)
    {
    	$response = $this->getResponseData($responseBody, $responseInfo);
    	$response_data_array = json_decode($response,true);
    	return $response_data_array;
    }
    
    public function getResponseData($responseBody, $responseInfo)
	{
        $body = "" . substr($responseBody, $responseInfo['header_size']);
        myerror_log("SendFaxResponse: " . $body);
        return $body;
    }

}