<?php

class FishBowlService extends SplickitService
{
    var $good_codes = array("302"=>1);
    var $url;
    var $fishbowl_data = array();
    var $response_array = array();
    var $error = array();
    const BIRTHDAY_ERROR_MESSAGE = "Sorry, birthday must be in the form of mm/dd/YYYY. please try again";
    const ZIPCODE_ERROR_MESSAGE = "Sorry, your zip appears invalid, please try again";


    function __construct()
    {
        $this->url = getProperty('fishbowl_url');
        if (isContext('moes')) {
            $this->fishbowl_data['SiteGUID'] = getProperty('fishbowl_site_guid');
            $this->fishbowl_data['ListID'] = getProperty('fishbowl_list_id');
        } else {
            myerror_log("ERROR!!!!  trying to use default fishbowl setup for something other than MOES");
            MailIt::sendErrorEmail("Serious Production Error With Fishbowl", "Trying to use default fishbowl setup for something other than MOES.  skin: " . getSkinNameForContext());
        }

    }
    
    /*
     * Custom validator for FishBowl 
     */
    function validateZipcode($zip)
    {
        if (preg_match('/^[0-9]{5}$/', $zip)) {
            return true;
        }
        $this->error['Zip'] = FishBowlService::ZIPCODE_ERROR_MESSAGE;
        return false;
    }

    function validateBirthday($date_of_birth)
    {
        myerror_log("date of birth in fishbowl service is: ".$date_of_birth);
        $valid = validateDate($date_of_birth);
        $result = $valid ? "TRUE" : "FALSE";
        myerror_log("the date of birth: $date_of_birth  validity is $result ");
        if ($valid === false) {
            $this->error['birthday'] = FishBowlService::BIRTHDAY_ERROR_MESSAGE;
        }
        return $valid;
    }

    function getError()
    {
        return $this->error;
    }

    function send($data, $resource = null)
    {
        $parameters  = array(
            'SiteGUID'=> $data['SiteGUID'],
            'ListID' => $data['ListID']
        );
        if($resource){
            $parameters['FirstName'] = $resource->first_name;
            $parameters['LastName'] = $resource->last_name;
            $parameters['EmailAddress'] = $resource->email;
            $parameters['Phone'] = $resource->phone;
            $parameters['Birthdate'] = $data['birthday'];
            $parameters['Zip'] = $data['zipcode'];
            $parameters['VibesMobileOptin'] = "false";
            $parameters["_InputSource_"] = "mobileapp";
        }else{
            $parameters['Action'] = $data['Action'];
            $parameters['EmailAddress'] = $data['EmailAddress'];
        }

        $response = FishbowlCurl::curlIt($this->url, $data);
        $this->response_array = $response;
        return $response;
    }    

    function checkExistence($email)
    {
        $data = $this->getFishBowlData();
        $data['Action'] = 'Update';
        $data['EmailAddress'] = $email;
        return $this->send($data);
    }
    

    function getFishBowlData()
    {
        return $this->fishbowl_data;
    }

}

?>
