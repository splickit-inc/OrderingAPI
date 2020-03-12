<?php

/**
 * Created by PhpStorm.
 * User: Elvis
 * Date: 8/15/16
 * Time: 8:03 AM
 */
class MailChimpService extends SplickitService
{
    var $base_url;
    var $mail_list;
    var $api_key;
    var $response = array();
    var $errors = array();

    const BIRTHDAY_ERROR_MESSAGE =  "Sorry, birthday must be in the form of mm/dd/YYYY. please try again";

    function __construct()
    {
        $this->base_url = getProperty('mailchimp_url');
        $this->mail_list = getProperty('mailchimp_mail_list');
        $this->api_key = getProperty('mailchimp_api_key');
        if ($this->base_url == "" || $this->api_key == "") {
            myerror_log("ERROR!!!!  trying to use default mail chimp setup for something other than goodcentssubs");
            MailIt::sendErrorEmail("Serious Production Error With Mailchimp", "Trying to use default mailchimp setup for something other than goodcentssubs.  skin: " . getSkinNameForContext());
        }
    }

    function validateBirthday($date_of_birth)
    {
        $error_message = MailChimpService::BIRTHDAY_ERROR_MESSAGE;
        myerror_log("about to validate birthda in mail chimp: $date_of_birth");
        $valid = validateDate($date_of_birth);

        if ($valid === false) {
            $this->errors['birthday'] = $error_message;
        }
        return $valid;
    }

    function send($data, $resource)
    {
        $parameters["email_address"] = $resource->email;
        $parameters["status"] = "subscribed";
        $parameters["merge_fields"] = array(
            "FNAME" => $resource->first_name,
            "LNAME" => $resource->last_name,
            "MMERGE5" => $data["birthday"]
        );

        $url = $this->base_url . "/lists/" . $this->mail_list . "/members";
        $this->headers[] = "Authorization: apikey " . $this->api_key;
        $response = MailChimpCurl::curlIt($url, $parameters, $this->headers);
        $this->response = $response;
        return $response;
    }
    

    function getError()
    {
        return $this->errors;
    }
}