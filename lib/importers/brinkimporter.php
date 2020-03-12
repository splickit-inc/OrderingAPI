<?php
abstract class BrinkImporter extends SplickitImporter
{

    protected $brand_name;

    function __construct($merchant_mixed)
    {
        parent::__construct($merchant_mixed);
    }

    function setBrandRegExForMerchantExternalIds()
    {
        $this->brand_regex_for_merchant_external_ids = '[a-zA-Z0-9_.-]+';
    }

    function getRemoteData($merchant_resource)
    {
        return $this->importBrinkRemoteData($merchant_resource);
    }

    function importBrinkRemoteData($merchant_resource)
    {
        if ($this->callServiceForRemoteData($merchant_resource)) {
            $plu_list = array();
            foreach ($this->item_price_records_imported as $remote_item_record) {
                $plu_list['item-'.$remote_item_record['Id']] = $remote_item_record;
            }
            foreach ($this->modifier_price_records_imported as $key=>$remote_modifier_record) {
                $plu_list['modifier-'.$key] = $remote_modifier_record;
            }
        } else {
            throw new Exception("MAJOR PROBLEM WITH BRINK ".$this->getBrandName()." IMPORT");
        }
        ksort($plu_list);
        $this->imported_prices = $plu_list;

        $merchant_external = $merchant_resource->merchant_external_id;
        $file_name = "./import_logs/brink".$this->getBrandName()."_$merchant_external.txt";
        // MailIt::sendErrorEmailAdam($file_name,$request->body);
        if ($myfile = fopen($file_name, "w")) {
            foreach ($plu_list as $plu_record) {
                fwrite($myfile, $plu_record['productName'].' '.$plu_record['productId'].' $'.$plu_record['pricePerUnit']."\r\n");
            }

            fclose($myfile);
        }
        myerror_log("done");
    }

    function processPriceResource(&$splickit_price_resource)
    {
        $merchant_id = $this->merchant_resource->merchant_id;
        $imported_price_hash_by_plu = $this->imported_prices;
        $raw_plu_on_splickt_price_record = $splickit_price_resource->external_id;
        myerror_log("splickit price record external id: ".$raw_plu_on_splickt_price_record);
        $p = explode("-",$raw_plu_on_splickt_price_record);
        $plu = isset($p[1]) ? $raw_plu_on_splickt_price_record : $p[0];

        myerror_log("about to get price for: $plu",5);
        if ($p[0] == 'PLU') {
            myerror_log("doing price for modifier item price thing",5);
            $plu = 'item-'.$p[1];
        } else if (isset($splickit_price_resource->modifier_price)) {
            $plu = 'modifier-'.$plu;
        } else {
            $plu = 'item-'.$plu;
        }
        if (isset($imported_price_hash_by_plu[$plu])) {
            $imported_price = $imported_price_hash_by_plu[$plu];
            myerror_log("WE DO HAVE A PRICE: " . $imported_price_hash_by_plu[$plu]['Price']);
            if ($imported_price_hash_by_plu[$plu]['Active'] != 'true') {
                $record = $imported_price_hash_by_plu[$plu];
                myerror_log(get_class().": we have an innactive price for ".$record['Name'],5);
                $this->addToRunningImport("We have an innactive price from the POS for ".$record['Name']."  plu: $plu");
            } else {
                $this->addToRunningImport("WE DO HAVE AN ACTIVE PRICE: ".json_encode($imported_price_hash_by_plu[$plu]));
            }
            $splickit_price_resource->active = strtolower($imported_price_hash_by_plu[$plu]['Active']) == 'true' ? 'Y' : 'N';
            if (isset($splickit_price_resource->modifier_price)) {
                $splickit_price_resource->modifier_price = $this->getModifierPriceFromItemData($imported_price_hash_by_plu[$plu]);
            } else {
                $splickit_price_resource->price = $this->getObjectPriceFromItemData($imported_price_hash_by_plu[$plu]);
            }

            $this->doCustomPriceManipulationForPriceResource($splickit_price_resource);
            $splickit_price_resource->tax_group = $this->isImportedItemTaxable($imported_price_hash_by_plu[$plu]) ? 1 : 0;



            $price_recorded = true;
        } else if ($plu == 'item-use_modifier') {
            myerror_log("we have an item where the price is set by the modifier",5);
            $this->addToRunningImport("we have an item where the price is set by the modifier: ".json_encode($splickit_price_resource->getDataFieldsReally()));
            $splickit_price_resource->active = 'Y';
            $price_recorded = false;
        } else {
            // there was no price returned so create an innactive price record
            myerror_log("WE DO NOT HAVE A PRICE FOR PLU: $plu",5);
            $id = isset($splickit_price_resource->item_id) ? $splickit_price_resource->item_id : $splickit_price_resource->modifier_item_id;
            $this->addToRunningImport("WE DID NOT IMPORT A PRICE RECORD FOR ITEM_ID: $id   SIZE_ID: ".$splickit_price_resource->size_id."   PLU: $plu SET record to INACTIVE!");
            $splickit_price_resource->active = 'N';
            $price_recorded = false;
        }
        $splickit_price_resource->merchant_id = $merchant_id; // in case this is a default record
        if ($p[1] == 'zero_price') {
            $splickit_price_resource->modifier_price = 0.00;
            $splickit_price_resource->price = 0.00;
        }
        if ($splickit_price_resource->save()) {
            return $price_recorded;
        }
    }

    function getObjectPriceFromItemData($item_data)
    {
        return $item_data['Price'];
    }

    function getModifierPriceFromItemData($item_data)
    {
        if ($item_data['PriceMethod'] == '0') {
            $plu = $item_data['ItemId'];
            myerror_log("BRINK IMPORT: We are getting a parent price for this plu: $plu");
            if ($new_item_data = $this->item_price_records_imported[$plu]) {
                myerror_log("BRINK IMPORT: Parent price aquired: ".$new_item_data['Price']);
                return $new_item_data['Price'];
            } else {
                myerror_log("BRINK IMPORT: ERROR No parent price aquired, using submitted item data: ".$item_data['Price']);
            }

        }
        return $item_data['Price'];
    }

    function callServiceForRemoteData($merchant_resource)
    {
        if (isset($merchant_resource->merchant_id)) {
            $merchant_id  = $merchant_resource->merchant_id;
        } else {
            throw new Exception("Cannot complete request. No merchant loaded.");
        }

        $method = "GetDestinations";
        $response0 = $this->curlIt($method,$merchant_resource);
        myerror_log($response0);

        $method = "GetDayParts";
        $response0 = $this->curlIt($method,$merchant_resource);
        myerror_log($response0);

        $method = "GetModifierGroups";
        //setProperty('write_brink_file','true');
        $response = $this->curlIt($method,$merchant_resource);
        if (getProperty("write_brink_file") == 'true') { $this->writeFile('brink_modifier_groups.xml',$response); }
        $modifier_group_list = $this->parseXMLResponseIntoArray($response,$method);
        //$modifier_group_list_hash = createHashmapFromArrayOfArraysByFieldName($modifier_group_list['ModifierGroup'],'Id');
        //$modifier_group_list_hash_by_name = createHashmapFromArrayOfArraysByFieldName($modifier_group_list['ModifierGroup'],'Name');
//        $this->modifier_price_records_imported = array();
//        foreach ($modifier_group_list['ModifierGroup'] as $modifier_group_info) {
//            foreach ($modifier_group_info['Items']['ModifierGroupItem'] as $remote_modifier_item) {
//                $this->modifier_price_records_imported[$remote_modifier_item['ItemId']] = $remote_modifier_item;
//            }
//        }
//        if (sizeof($this->modifier_price_records_imported) <1) {
//            return false;
//        }

        $method = "GetItems";
        $response2 = $this->curlIt($method,$merchant_resource);
        if (getProperty("write_brink_file") == 'true') { $this->writeFile('brink_items.xml',$response2); }
        $item_list = $this->parseXMLResponseIntoArray($response2,$method);
        $this->item_price_records_imported = array();
        foreach ($item_list['Item'] as $brink_remote_item_info) {
            $this->item_price_records_imported[$brink_remote_item_info['Id']] = $brink_remote_item_info;
        }
        if (sizeof($this->item_price_records_imported) < 1) {
            return false;
        }

        $this->modifier_price_records_imported = array();
        foreach ($modifier_group_list['ModifierGroup'] as $modifier_group_info) {
            foreach ($modifier_group_info['Items']['ModifierGroupItem'] as $remote_modifier_item) {
                $imported_item_record = $this->item_price_records_imported[$remote_modifier_item['ItemId']];
                $remote_modifier_item['Active'] = $imported_item_record['Active'];
                $this->modifier_price_records_imported[$modifier_group_info['Id'].'-'.$remote_modifier_item['ItemId']] = $remote_modifier_item;
            }
        }
        if (sizeof($this->modifier_price_records_imported) <1) {
            return false;
        }


//        $method = "GetItemGroups";
//        $response3 = $this->curlIt($method,$merchant_resource);
//        if (getProperty("write_brink_file") == 'true') { $this->writeFile('brink_item_groups.xml',$response3); }
//        $item_group_list = $this->parseXMLResponseIntoArray($response3,$method);
        return true;
    }

    function stageImportFromMerchantResource($merchant_resource,$delay)
    {
        //check it merchant is a brink merchant
        if (MerchantBrinkInfoMapsAdapter::isMechantBrinkMerchant($merchant_resource->merchant_id)) {
            return parent::stageImportFromMerchantResource($merchant_resource,$delay);
        } else {
            throw new Exception("merchant is not a Brink Merchant");
        }

    }

    function myBrinkToArray($xml) {
        $array = (array)$xml;

        foreach ( array_slice($array, 0) as $key => $value )
        {
            if ( $value instanceof SimpleXMLElement )
            {
                $ns = $value->getNamespaces(true);
                if ($ns['b']) {
                    $value = $array[$key]->children($ns['b']);
                }
                $array[$key] = empty($value) ? NULL : $this->myBrinkToArray($value);
            } else if (is_array($value)) {
                foreach ($value as $sub_key => $sub_value) {
                    $array[$key][$sub_key] = $this->myBrinkToArray($sub_value);
                }
            }
        }
        return $array;
    }

    function parseXMLResponseIntoArray($response,$method)
    {
        $start = strpos($response,"<".$method."Result");
        $end_string = "</".$method."Result>";
        $end = strpos($response,$end_string) + strlen($end_string);
        $payload = substr($response,$start,$end-$start);
        $xml=simplexml_load_string($payload, NULL, NULL, "http://schemas.datacontract.org/2004/07/Pos.Web.Service.Data") or die("Error: Cannot create object");
        $ns = $xml->getNamespaces(true);
        $res = $xml->children($ns['a']);
        $hashmap = $this->myBrinkToArray($res);
        return $hashmap;
    }

    function curlIt($method,$merchant_resource)
    {
        $soap_action = "http://tempuri.org/ISettingsWebService/$method";
        $url = getProperty("brink_api_url_settings");
        if ($this->brand_id == 282) {
            //use new url
            $url = str_replace('api.brinkpos.net','api4.brinkpos.net',$url);
        } else if ($this->brand_id == 150) {
            //use new url
            $url = str_replace('api.brinkpos.net','api8.brinkpos.net',$url);
        }
        $access_token = getProperty("brink_access_token"); //"ssYesckeWU+3jzRwuW1IyQ==";
        $location_token = $this->getLocationToken($merchant_resource);

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curl, CURLOPT_VERBOSE, 0);

        $body = '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body><'.$method.' xmlns="http://tempuri.org/"><accessToken>'.$access_token.'</accessToken><locationToken>'.$location_token.'</locationToken></'.$method.'></s:Body></s:Envelope>';
        error_log("body: $body");
        error_log(" ");
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        $headers = array('Content-Type: text/xml; charset=utf-8', 'Content-Length: ' . strlen($body), "SOAPAction: $soap_action");
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        logCurl($url,'POST',$up,$headers,$body);
        $result = curl_exec($curl);
        //error_log($result);
        return $result;
    }

    function getLocationToken($merchant_resource)
    {
        if ($this->merchant_location_token) {
            return $this->merchant_location_token;
        }
        $merchant_resource = $merchant_resource == null ? $this->merchant_resource : $merchant_resource;
        $mbimr = MerchantBrinkInfoMapsAdapter::staticGetRecord(array("merchant_id"=>$merchant_resource->merchant_id),'MerchantBrinkInfoMapsAdapter');
        $location_token = $mbimr['brink_location_token'];
        myerror_logging(1,"we have the brink location token: $location_token");
        if (isNotProd() && !isLaptop()) {
            $location_token = getProperty('brink_test_location_token');
        }
        $this->merchant_location_token = $location_token;
        return $location_token;
    }

    function writeFile($file_name,$content)
    {
        if ($myfile = fopen($file_name, "w")) {
            fwrite($myfile, $content);
            fclose($myfile);
        } else {
            myerror_log("******************** COULDNT WRITE XML DUMP TO FILE $file_name ***********************");
        }
    }

    function getBrandName()
    {
        return $this->brand_name;
    }

    /* ABSTRACT METHTODS NOT USED WITH BRINK SINCE WE'RE JUST DOING PRICES */


    function getItemDescriptionFromItemData($item_data){}

    function getModifierGroupMinForThisIntegration($modifier_group_data){}

    function getModifierGroupMaxForThisIntegration($modifier_group_data){}

    function getActiveFlagSetting($data){}

    function getMenuTypeCatId($menu_type_data){}

}
?>