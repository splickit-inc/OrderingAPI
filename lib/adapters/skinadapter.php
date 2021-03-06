<?php

class SkinAdapter extends MySQLAdapter
{

	function SkinAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Skin',
			'%([0-9]{1,15})%',
			'%d',
			array('skin_id'),
			null,
			array('created','modified')
			);
			
		$this->allow_full_table_scan = true;
	}

    function insert(&$resource)
    {
        $resource->public_client_id = generateUUID();
        return parent::insert($resource); // TODO: Change the autogenerated stub
    }

    function update(&$resource)
    {
        if (parent::update($resource)) {
            $this->deleteSkinCacheFromResource($resource);
            return true;
        } else {
            return false;
        }
    }

    function deleteSkinCacheFromSkinId($skin_id)
    {
        if ($skin_resource = Resource::find($this,$skin_id)) {
            $this->deleteSkinCacheFromResource($skin_resource);
            return $skin_resource->external_identifier;
        }
        return createErrorResourceWithHttpCode("No Skin matching id $skin_id",422,422);
    }

    function deleteSkinCacheFromResource($resource)
    {
        myerror_log("deleting skin cache for: ".$resource->external_identifier);
        SplickitCache::deleteCacheFromKey("skin-".$resource->external_identifier);
        SplickitCache::deleteCacheFromKey("skin-".$resource->skin_id);
        SplickitCache::deleteCacheFromKey("skin-".$resource->public_client_id);

        if ($brand_id = $resource->brand_id) {
            $brand_cache_key = "brand-$brand_id";
            myerror_log("we have a brand with the skin so bust that cache too: $brand_cache_key");
            SplickitCache::deleteCacheFromKey($brand_cache_key);
        }

    }



    function setLoyaltyFeatures(&$skin) {
	  $supports_history = ($skin['supports_history'] == '1');
	  $supports_join = ($skin['supports_join'] == '1');
	  $supports_link_card = ($skin['supports_link_card'] == '1');
        $supports_pin = ($skin['supports_pin'] == '1');
        $loyalty_lite = false;
        if ($skin['brand_id']) {
            $brand_record = BrandAdapter::staticGetRecordByPrimaryKey($skin['brand_id'],'BrandAdapter');
            $loyalty_lite = ($brand_record['use_loyalty_lite'] == 1);

            try{
                if($loyalty_controller  = LoyaltyControllerFactory::getLoyaltyControllerForBrandAndSkin(null,$brand_record,$skin)){
                    $loyalty_type = $loyalty_controller->brand_loyalty_rules_record['loyalty_type'];
                    $loyalty_type_labels = $loyalty_controller->getLoyaltyLabels();
                }
            }catch (Exception $e){
                myerror_log("there was an error:  ".$e->getMessage());
            }
        }

	  $skin['loyalty_features'] = array(
          "supports_history" => $supports_history,
          "supports_join" => $supports_join,
          "supports_link_card" => $supports_link_card,
          "supports_pin"=>$supports_pin,
          "loyalty_lite"=>$loyalty_lite,
          "loyalty_type" => $loyalty_type,
          'loyalty_labels' => is_null($loyalty_type)? null :$loyalty_type_labels
      );
	}
	
	function setMerchantDelivers(&$skin) {
        $skin['merchants_with_delivery'] = $this->doesSkinHaveDeliveryMerchants($skin['skin_id']);
	}

	function setExtraUserFields(&$skin) {
	  $loyalty_fields = BrandSignupFieldsAdapter::getFieldsForBrand($skin['brand_id']);	  
	  $skin_fields = array();
	  foreach($loyalty_fields as $field) {
	    $field_arr = array("field_name" => $field->field_name, "field_type" => $field->field_type, "field_label"=> $field->field_label, "field_placeholder" =>$field->field_placeholder);
	    $skin_fields[] = $field_arr;
	  }
	  $skin['brand_fields'] = $skin_fields;
	}

    function getRecords($data,$options = array())
    {
        $options[TONIC_FIND_BY_METADATA] = $data;
        return $this->selectLite(null,$options);
    }

	function selectLite($url, $options) {
        $splickit_cache = new SplickitCache();
        $skins = array();
	    if ($this->cache_enabled) {
            $options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
            if (isset($options[TONIC_FIND_BY_METADATA]['skin_id']) && $options[TONIC_FIND_BY_METADATA]['skin_id']  > 0) {
                $skin_caching_string = "skinlight-".$options[TONIC_FIND_BY_METADATA]['skin_id'];
            } else if (isset($options[TONIC_FIND_BY_METADATA]['public_client_id']) ) {
                $skin_caching_string = "skinlight-".$options[TONIC_FIND_BY_METADATA]['public_client_id'];
            } else if (isset($options[TONIC_FIND_BY_METADATA]['external_identifier']) ) {
                $skin_caching_string = "skinlight-".$options[TONIC_FIND_BY_METADATA]['external_identifier'];
            } else {
                $skin_caching_string = "skinlight-".getStamp();
                myerror_log("we have a request for the skin with no valid field to test the cache. setting cache string to: $skin_caching_string");
            }
            if ($skin = $splickit_cache->getCache($skin_caching_string)) {
                return [$skin];
            }
        }
        $skin_results = parent::select($url,$options);
        foreach($skin_results as $skin) {
            $skin['show_notes_fields'] = ($skin['show_notes_fields'] == '1');

            $expires_in_seconds = 36000; // 10 hours expiration
            // set all keys for skin
            $splickit_cache->setCache("skinlight-".$skin['external_identifier'],$skin,$expires_in_seconds);
            $splickit_cache->setCache("skinlight-".$skin['skin_id'],$skin,$expires_in_seconds);
            $splickit_cache->setCache("skinlight-".$skin['public_client_id'],$skin,$expires_in_seconds);
            array_push($skins, $skin);
        }
        return $skins;
    }

	function &select($url, $options = NULL) {
        $options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
        $splickit_cache = new SplickitCache();
        $skins = array();
        if ($this->cache_enabled) {
            if (isset($options[TONIC_FIND_BY_METADATA]['skin_id']) && $options[TONIC_FIND_BY_METADATA]['skin_id'] > 0) {
                $skin_caching_string = "skin-" . $options[TONIC_FIND_BY_METADATA]['skin_id'];
            } else if (isset($options[TONIC_FIND_BY_METADATA]['public_client_id'])) {
                $skin_caching_string = "skin-" . $options[TONIC_FIND_BY_METADATA]['public_client_id'];
            } else if (isset($options[TONIC_FIND_BY_METADATA]['external_identifier'])) {
                $skin_caching_string = "skin-" . $options[TONIC_FIND_BY_METADATA]['external_identifier'];
            } else {
                $skin_caching_string = "skin-" . getStamp();
                myerror_log("we have a request for the skin with no valid field to test the cache. setting cache string to: $skin_caching_string");
            }

            if ($skin = $splickit_cache->getCache($skin_caching_string)) {
                return [$skin];
            }
        }
        $skin_results = parent::select($url,$options);
        foreach($skin_results as $skin) {
            $this->setLoyaltyFeatures($skin);
            $this->setMerchantDelivers($skin);
            $this->setExtraUserFields($skin);
            $skin['show_notes_fields'] = ($skin['show_notes_fields'] == '1');

            $expires_in_seconds = 36000; // 10 hours expiration
            // set all keys for skin
            $splickit_cache->setCache("skin-".$skin['external_identifier'],$skin,$expires_in_seconds);
            $splickit_cache->setCache("skin-".$skin['skin_id'],$skin,$expires_in_seconds);
            $splickit_cache->setCache("skin-".$skin['public_client_id'],$skin,$expires_in_seconds);
            array_push($skins, $skin);
        }
        return $skins;
      }

  function findForBrand($external_id)
  {
  	$options[TONIC_FIND_BY_METADATA]['external_identifier'] = $external_id;
  	$skins = $this->select("", $options);
  	return $skins;
  }
  
  function findMerchantsForSkin($skin_id)
  {
      $options[TONIC_FIND_BY_SQL] = "SELECT a.* FROM Merchant a JOIN Skin_Merchant_Map b ON a.merchant_id = b.merchant_id WHERE  b.skin_id = $skin_id";
      $merchants = Resource::findAll(new MerchantAdapter($m), '', $options);
      return $merchants;
  }

    function doesSkinHaveDeliveryMerchants($skin_id)
    {
        $options[TONIC_FIND_BY_SQL] = "SELECT count(a.merchant_id) AS number_of_merchants FROM Merchant a JOIN Skin_Merchant_Map b ON a.merchant_id = b.merchant_id WHERE  b.skin_id = $skin_id AND a.delivery = 'Y'";
        $merchant_adapter = new MerchantAdapter($m);
        $result = $merchant_adapter->select(null,$options);
        return $result[0]['number_of_merchants'] > 0;
    }

    /**
     * @desc will return the skin and any extra fields by the external_identifier (which is really our internal code. eg: com.splickit.moes
     * @param $external_id_string
     * @return mixed
     */
    static function getSkin($external_id_string,$cache_enabled = true)
    {
        if ($cache_enabled) {
            $skin_caching_string = "skin-$external_id_string";
            $splickit_cache = new SplickitCache();
            if ($skin = $splickit_cache->getCache($skin_caching_string)) {
                myerror_log("we have retrieved the skin from the cache: ".$skin_caching_string,3);
                return $skin;
            }
        }

        $skin_adapter = new SkinAdapter(getM());
        $skin_adapter->cache_enabled = $cache_enabled;

        $options[TONIC_FIND_BY_SQL] = "SELECT * FROM Skin WHERE (external_identifier = '$external_id_string' OR public_client_id = '$external_id_string') AND logical_delete = 'N'";
        if ($skin_resource = Resource::find($skin_adapter,null,$options)) {
            myerror_log("we have the skin by sql");
            $skin = $skin_resource->getDataFieldsReally();
            logData($skin,"SKIN");
            return $skin;
        }
    }

    function bustCacheFromSkinResource($skin_resource)
    {
        SplickitCache::deleteCacheFromKey("skin-".$skin_resource->external_identifier);
        SplickitCache::deleteCacheFromKey("skin-".$skin_resource->skin_id);
        SplickitCache::deleteCacheFromKey("skin-".$skin_resource->public_client_id);
        SplickitCache::deleteCacheFromKey("skinlight-".$skin_resource->external_identifier);
        SplickitCache::deleteCacheFromKey("skinlight-".$skin_resource->skin_id);
        SplickitCache::deleteCacheFromKey("skinlight-".$skin_resource->public_client_id);
    }
    function bustCacheFromSkinId($skin_id)
    {
        if ($skin_resource = Resource::find($this,$skin_id)) {
            $this->bustCacheFromSkinResource($skin_resource);
        }
    }
}
?>
