<?php

Class PushIphone
{

    private $apnsHost = 'gateway.push.apple.com';
    private $apnsPort = 2195;

    private $apnsHost_test = 'gateway.sandbox.push.apple.com';

    var $response = array();

    function PushIphone($skin_external_identifier, $mimetypes)
    {
        $this->mimetypes = $mimetypes;
        $cert_file_name = $skin_external_identifier . '.pem';
        if (isTest() || isLaptop()) {
            $cert_file_name = 'com.splickit.ordersandbox.pem'; // load testing
            //$cert_file_name = 'com.splickit.splickitbeta.pem'; // push testing
        }
        myerror_log("in push_iphone certificate file name is: " . $cert_file_name);
        $this->cert_file = $cert_file_name;
    }

    function push($user_messaging_records, $message)
    {

        if($message == 'failthismessage'){
            $this->response = array('error_no' => 500, 'error' => "Error on push message to apns", 'http_code' => 500);
            return false;
        }

        if (sizeof($user_messaging_records) < 1) {
            myerror_log("ERROR!  NO USERS SELECTED FOR PUSH!");
            $this->response = array('error_no' => 500, 'error' => "ERROR!  NO USERS SELECTED FOR PUSH!", 'http_code' => 500);
            return false;
        }
        myerror_log("the size of hte records in push_iphone is: " . sizeof($user_messaging_records));
        $num_users_sent_to = 0;
        $fails = 0;
        $errors = array();
        $successes = array();

        foreach ($user_messaging_records as $record) {
            myerror_log("iphone message pushed to user_id: " . $record['user_id']);
            $successes[] = "iphone message pushed to user_id: " . $record['user_id'];
            $num_users_sent_to++;
        }
        myerror_log("RESULTS OF PUSH MESSAGE IS: successes=$num_users_sent_to     fails=$fails");

        $this->response = array(
            'http_code' => 200,
            'raw_result' => json_encode(
                array(
                    'success' => true,
                    'message' => "RESULTS OF PUSH MESSAGE IS: successes=$num_users_sent_to     fails=$fails",
                    'fails' => implode('; ', $errors),
                    'successes' => implode(';', $successes)
                )
            )
        );

        return true;
    }

} // class PushIphone
?>