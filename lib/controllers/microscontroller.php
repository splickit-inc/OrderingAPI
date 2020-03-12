<?php

Class MicrosController extends MessageController
{
    const DESTINATION_SYMPHONY = "http://simphony16mr5hf4tssv-microsimphony-w0uncrt9.srv.ravcloud.com:8080/TSWebService/TSWebService_1_0.asmx";
    //const DESTINATION_3200 = "http://ec2-54-189-188-51.us-west-2.compute.amazonaws.com";
    //const DESTINATION_3200 = "http://54.189.188.51:81/LocalService";
    const DESTINATION_3200 = "http://micros.splickit.com:81";

    function MicrosController($mt,$u,&$r,$l = 0)
    {
        parent::MessageController($mt,$u,$r,$l);
    }

    function send($body)
    {
        myerror_log("about to curl with: ".cleanUpDoubleSpacesCRLFTFromString($body));
        if ($this->message_resource->message_format == 'MS') {
            $url = MicrosController::DESTINATION_SYMPHONY;
        } else if ($this->message_resource->message_format == 'MT') {
            $url = MicrosController::DESTINATION_3200;
        }

        if ($ch = curl_init($url))
        {
            curl_setopt($ch, CURLOPT_HEADER, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_POST, 1);
            $headers = array(
                "Accept: */*",
                "Content-Type: text/xml",
                "Content-Length: " . strlen($body));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            logCurl($url,'POST',$user_password,$headers,cleanUpDoubleSpacesCRLFTFromString($body));
            $response = SplickitCurl::curlIt($ch);
            curl_close($ch);
            $this->message_resource->message_text = $body;
            $this->message_resource->response = $response['raw_result'];
            if ($response['http_code'] == 200) {
                return true;
            } else {
                return false;
            }
        } else {
            $response['error'] = "FAILURE. Could not connect to Micros";
            myerror_log("ERROR!  could not connect to Micros");
            return false;
        }
    }

    public function populateMessageData($message_resource)
    {
        $resource = parent::populateMessageData($message_resource);
        // now set the micros pick and order times for pacific for ALL task orders
        return $this->setMicrosPickupAndOrderTimeStringsOnResource($resource);
    }

    public function setMicrosPickupAndOrderTimeStringsOnResource($resource)
    {
        $tz = date_default_timezone_get();
        date_default_timezone_set('America/Baltimore');
        $micros_pickup_time_string = date("Y-m-d\TH:i:00",$resource->pickup_dt_tm);
        $micros_order_time_string = date("Y-m-d\TH:i:00",$resource->order_dt_tm);
        $resource->set("micros_pickup_time_string",$micros_pickup_time_string);
        $resource->set("micros_order_time_string",$micros_order_time_string);
        date_default_timezone_set($tz);
        return $resource;
    }

}
?>