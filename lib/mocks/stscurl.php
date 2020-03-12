<?php
class StsCurl extends SplickitCurl
{
	static function curlIt($url,$xml)
	{
		/*	$loyalty_return ='<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><AddPointsResponse xmlns="http://tempuri.org/"><AddPointsResult>{"xml":{"Reward":"REWARD:0.00","status":"success","Approved":"29916540","Clerk":"27","Check":"9780653","PointsAdded":"10.50","TotalPoints":["369.50","369.50"],"TotalSaved":"0.00","TotalVisits":"37","GiftCardBalance":"0.00","RewardCashBalance":"0.00","CustomReceiptMessages":"Register your Rewards Number and receive\na R5.00 instant reward\n"}}</AddPointsResult></AddPointsResponse></soap:Body></soap:Envelope>'; */
        $xml_as_array = parseXMLintoHashmap($xml);

        $service_name = 'Smart Transaction Systems API';
        //$url = "https://smarttransactions.net/gateway_no_lrc.php";

        $action_code = $xml_as_array['Action_Code'];
        $card_number = $xml_as_array['Card_Number'];
        $transaction_id = rand(1111111,9999999);
        $api_key = $xml_as_array['API_Key'];
        if ($api_key != 'h*B7833Mp1tZ') {
            $response = '<Response><Response_Code>01</Response_Code><Response_Text>INVALID API KEY</Response_Text><Amount_Balance>0.00</Amount_Balance><Trans_Date_Time>112219083829</Trans_Date_Time><Transaction_ID>1830</Transaction_ID></Response>';
        } else if ( $action_code == '01') {
            //charge card   <Action_Code>01</Action_Code>
            $response = '<Response><Response_Code>00</Response_Code><Response_Text>941215</Response_Text><Auth_Reference>0001</Auth_Reference> <Amount_Balance>000</Amount_Balance><Expiration_Date>121627</Expiration_Date><Trans_Date_Time>032108122102</Trans_Date_Time></Response>';
        } else if ( $action_code == '02') {
            $transaction_amount = $xml_as_array['Transaction_Amount'];
            $response = '<Response><Response_Code>00</Response_Code><Response_Text>331148</Response_Text><Auth_Reference>0001</Auth_Reference><Amount_Balance>'.$transaction_amount.'</Amount_Balance><Expiration_Date>060130</Expiration_Date><Trans_Date_Time>060710012010</Trans_Date_Time><Card_Number>'.$card_number.'</Card_Number><Transaction_ID>'.$transaction_id.'</Transaction_ID></Response>';
        } else if ( $action_code == '05') {
            //balance inquiry  <Action_Code>05</Action_Code>
            if ($card_number == '1001001') {
                $response = '<Response><Response_Code>01</Response_Code><Response_Text>INVALID CARD12</Response_Text><Amount_Balance>0.00</Amount_Balance><Trans_Date_Time>110719064158</Trans_Date_Time><Transaction_ID>0001</Transaction_ID></Response>';
                return ['raw_result'=>$response];
            }
            if ($card_number == '88888888') {
                $balance = 88.88;
            } else {
                $balance = 77.77;
            }
            $response = '<Response><Response_Code>00</Response_Code><Response_Text>311421</Response_Text><Auth_Reference>0001</Auth_Reference><Amount_Balance>'.$balance.'</Amount_Balance><Expiration_Date>092429</Expiration_Date><Trans_Date_Time>'.time().'</Trans_Date_Time><Card_Number>'.$xml_as_array['card_number'].'</Card_Number><Transaction_ID>56</Transaction_ID></Response>';
        } else if ( $action_code == '11') {
            $response = '<Response><Response_Code>00</Response_Code><Response_Text>000888</Response_Text><Auth_Reference>0005</Auth_Reference><Amount_Balance>100.00</Amount_Balance><Trans_Date_Time>120419085845</Trans_Date_Time><Transaction_ID>1210853218</Transaction_ID></Response>';
        } else if ( $action_code == '19') {
            //force sale (partial) <Action_Code>19</Action_Code>
            $transaction_amount = $xml_as_array['Transaction_Amount'];
            $response = '<Response><Response_Code>00</Response_Code><Response_Text>472000</Response_Text><Auth_Reference>0006</Auth_Reference><Amount_Balance>12.23</Amount_Balance><Approved_Amount>'.$transaction_amount.'</Approved_Amount><Expiration_Date>121329</Expiration_Date> <Trans_Date_Time>061010164530</Trans_Date_Time><Card_Number>'.$card_number.'</Card_Number> <Transaction_ID>'.$transaction_id.'</Transaction_ID></Response>';

        } else {
            //
        }
		return ['raw_result'=>$response,'http_code'=>200];
	}
}
?>