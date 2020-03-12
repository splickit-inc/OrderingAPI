<?php

class ItemsController extends SplickitController
{
    function __construct($mt, $user, $request, $log_level = 0)
    {
        parent::SplickitController($mt, $user, $request, $log_level);
        $this->adapter = new NutritionItemSizeInfosAdapter($this->mimetypes);
    }

    function processV2Request()
    {
        $resource = new Resource($this->adapter);
        if ($this->request == null) {
            throw new NullRequestException();
        } 

        if (preg_match('%/items/([0-9]{4,10})%', $this->request->url, $matches)) {
            $item_id = $matches[1];
            $size_id = $this->request->data['size_id'];

            if ($nutrition_info = $this->adapter->retrieveNutritionSizesInfo($item_id, $size_id)) {
                $item = $this->getItemInfo($item_id);
                $size_info = $this->geSizesInfo($size_id);
                $resource->set('item_name', $item['item_name']);
                $resource->set('item_size', $size_info['size_name']);
                $resource->set('nutrition_info', $nutrition_info);
            } else {
                myerror_log("ERROR!!!! entering nutrition info process v2 request main block ");
                $error_message = $this->getErrorMessage();
                $resource->set('message', $error_message);
            }
        } else {
            myerror_logging("ItemsController : processV2Request bad parameters" . $this->request->url);
            return returnErrorResource("bad request to API: ". $this->request->url);
        }
        return $resource;
    }

    private function getItemInfo($item_id)
    {
        if($record = getStaticRecord(array("item_id" => $item_id),"ItemAdapter"))
        {
            return $record;
        }
    }

    private function geSizesInfo($size_id)
    {
        if($record = getStaticRecord(array("size_id" => $size_id),"SizeAdapter"))
        {
            return $record;
        }
    }

    function getErrorMessage()
    {
        $message = "There is no nutrition information for this item";
        $brand_id = getBrandIdFromCurrentContext();

        if ($brand_record = getStaticRecord(array("brand_id" => $brand_id), "BrandAdapter")) {
            if (!empty($brand_record['nutrition_data_link'])) {
                $message = "For additional nutrition information go to " . $brand_record['nutrition_data_link'];
            }
        }
        return $message;
    }
}

