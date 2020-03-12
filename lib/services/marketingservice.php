<?php

/**
 * Created by PhpStorm.
 * User: Elvis
 * Date: 8/12/16
 * Time: 11:27 AM
 */
class MarketingService
{
    var $service_name = "splickit";
    var $service;
    var $join_fields;
    var $join_data;
    var $enable_join_service;

    function __construct($brand_id)
    {
        $this->join_data = array();

        $sign_up_fields = BrandSignupFieldsAdapter::getFieldsForBrand($brand_id);
        if (count($sign_up_fields) > 0) {
            $this->service_name = $sign_up_fields[0]->group_name;
            $class_name = $this->service_name . "Service";
            $this->service = new $class_name();
            foreach ($sign_up_fields as $sign_up_field) {
                $this->join_fields[] = $sign_up_field->field_name;
            }
        }
    }

    function validateJoinFields($request_data)
    {
        //If fishbowl should remove the year and pass the fishbowl birthday validation and stored in User_Extra_Data info
        if (get_class($this->service) == "FishBowlService") {
            if (!empty($request_data['birthday'])) {
                $date_format_regex = '/(0[1-9]|1[012])[- \/.](0[1-9]|[12][0-9]|3[01])[- \/.](19|20)\d\d/'; //check MM/DD/YYYY
                if (preg_match($date_format_regex, $request_data['birthday'])) {
                    $request_data['birthday'] = substr($request_data['birthday'], 0, -5); //remove the year to keep the format mm/dd to valid for fishbowl
                }
            }
        }
        $valid = true;
        if ($request_data['marketing_email_opt_in'] == 'Y' || $request_data['marketing_email_opt_in'] == '1') {
            $this->enable_join_service = true;
            foreach ($this->join_fields as $field) {
                $validator = "validate" . $this->sanitizeField($field);
                if (method_exists($this->service, $validator)) {
                    if ($this->service->$validator($request_data[$field])) {
                        $this->join_data[$field] = $request_data[$field];
                    } else {
                        $valid = false;
                    }
                }
            }
        } else {
            $this->enable_join_service = false;
        }
        return $valid;
    }

    function getValidationError()
    {
        return join(", ", $this->service->getError());
    }

    function join($resource)
    {

        myerror_log("starting to send the $this->service_name ");
        $results = $this->service->send($this->join_data, $resource);

        if ($this->service->isSuccessfulResponse($results)) {
            $user_extra_data_adapter = new UserExtraDataAdapter($mimetypes);
            Resource::createByData($user_extra_data_adapter, array(
                "user_id" => $resource->user_id,
                "birthdate" => $this->join_data['birthday'],
                "zip" => '',
                "process" => strtolower($this->service_name),
                "results" => $this->service->response_array['http_code']
            ));
        } else {
            myerror_log("ERROR TRYING TO JOIN " . strtoupper($this->service_name));
            logError("error", $this->service_name . " Error. they don't really tell us", "");
        }
        

    }

    private function sanitizeField($field)
    {
        $fieldName = ucwords(str_replace("_", " ", $field));
        return str_replace(" ", "", $fieldName);
    }
}