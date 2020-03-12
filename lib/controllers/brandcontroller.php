<?php

class BrandController extends SplickitController {

    /**
     * @var Resource
     */
    private $brand_resource;

    const NO_HOMEGROWN_LOYALTY_ERROR = "Sorry, this loyalty program is not user controlled, please contact customer service.";
    const PUBLIC_KEY_ALREADY_EXISTS_ERROR = "This skin already has a public key associated with it";

    function BrandController($mt,$u,$r,$l = 0)
    {
        parent::SplickitController($mt,$u,$r,$l);
        $this->adapter = new BrandAdapter(getM());
    }

    function processV2Request()
    {
        $this->processRequest();
    }

    function processRequest()
    {
        if (preg_match("%/brands/([0-9]{3,15})%", $this->request->url, $matches)) {
            $brand_id = $matches[1];
            if ($brand_resource = Resource::find($this->adapter,$brand_id)) {
                $this->brand_resource = $brand_resource;
                if ($this->hasRequestForDestination('loyalty') || substr_count($this->request->url,'loyalty') > 0 ) {
                    if ($this->isThisRequestMethodAPost()) {
                        if ($this->hasRequestForDestination('enable_loyalty')) {
                            $this->enableLoyaltyOnBrandResource();
                        } else if ($this->hasRequestForDestination('disable_loyalty')) {
                            $this->brand_resource->loyalty = 'N';
                            if ($this->brand_resource->save()) {
                                $resource = Resource::dummyfactory(['result' => 'success']);
                            } else {
                                $resource = Resource::dummyfactory(['result' => 'failure', "message" => "Loyalty could not be disabled"]);
                            }
                            return $resource;
                        } else if ($this->hasRequestForDestination('adjustloyaltypoints') || $this->hasRequestForDestination('setasprimaryaccount')) {
                            if ($user_id = $this->request->data['user_id']) {
                                if ($user_resource = Resource::find(new UserAdapter(getM()), $user_id)) {
                                    if ($skin_resource = Resource::find(new SkinAdapter(getM()), null, [TONIC_FIND_BY_METADATA => ['brand_id' => $brand_resource->brand_id]])) {
                                        if ($loyalty_controller = LoyaltyControllerFactory::getLoyaltyControllerForBrandAndSkin($user_resource->getDataFieldsReally(), $brand_resource->getDataFieldsReally(), $skin_resource->getDataFieldsReally())) {
                                            if ($this->hasRequestForDestination('adjustloyaltypoints')) {
                                                if ($user_brand_points_map = $loyalty_controller->processManualLoyaltyAdjustmentReturnUserBrandPointsMap($user_id, $this->request->data['points'], $this->request->data['note'])) {
                                                    $resource = Resource::dummyfactory(['result' => 'sucess', 'user_brand_loyalty_info' => $user_brand_points_map]);
                                                } else {
                                                    $resource = Resource::dummyfactory(['result' => 'failure', "message" => "Loyalty could not be adjusted"]);
                                                }
                                            } else if ($this->hasRequestForDestination('setasprimaryaccount')) {
                                                if ($loyalty_controller->setCurrentUserAsPrimaryAccount()) {
                                                    $resource = Resource::dummyfactory(['result' => 'sucess']);
                                                } else {
                                                    $resource = Resource::dummyfactory(['result' => 'failure', "message" => "Loyalty account could not be adjusted"]);
                                                }
                                            }
                                        } else {
                                            return createErrorResourceWithHttpCode("Unable to get loyalty program for skin: " . $skin_resource->name, 422, 422);
                                        }
                                    } else {
                                        return createErrorResourceWithHttpCode("Unable to get Skin associated with brand. Possibly ambiguous.", 422, 422);
                                    }
                                } else {
                                    return createErrorResourceWithHttpCode("This user does not exist", 422, 422);
                                }
                            } else {
                                return createErrorResourceWithHttpCode("This user does not exist", 422, 422);
                            }
                            return $resource;
                        }

                        // ok if we get here then its a post to loyalty so we assume there is data that needs to be updated
                        if (isset($this->data['loyalty_type']) && $this->isLoyaltyNOTHomeGrown($this->data['loyalty_type'])) {
                            return createErrorResourceWithHttpCode(self::NO_HOMEGROWN_LOYALTY_ERROR, 422, 422);
                        }
                        $blr_data['brand_id'] = $brand_id;
                        $blra = new BrandLoyaltyRulesAdapter(getM());
                        if ($brand_loyalty_rules_resource = Resource::findOrCreateIfNotExistsByData($blra, $blr_data)) {
                            if (isset($brand_loyalty_rules_resource->insert_id)) {
                                if (isset($this->data['loyalty_type'])) {
                                    $brand_loyalty_rules_resource->saveResourceFromData($this->data);
                                } else {
                                    // do default loyalty
                                    $brand_loyalty_rules_resource->loyalty_type = 'splickit_cliff';
                                    $brand_loyalty_rules_resource->earn_value_amount_multiplier = 10;
                                    $brand_loyalty_rules_resource->cliff_value = 500;
                                    $brand_loyalty_rules_resource->cliff_award_dollar_value = 5.00;
                                    $brand_loyalty_rules_resource->save();
                                }
                                // now make sure that skin has history turned on
                                if ($skin_resource = Resource::find(new SkinAdapter(getM()), null, [TONIC_FIND_BY_METADATA => ["brand_id" => $brand_id]])) {
                                    myerror_log("We have the skin so now set Support History to true");
                                    $skin_resource->supports_history = 1;
                                    $skin_resource->save();
                                } else {
                                    myerror_log("ERROR!!!! attemped brand loyalty but no SKIN associated with brand");
                                    $resource = Resource::dummyfactory(['result' => 'failure', "message" => "Loyalty could not be enabled on brand because brand is not assicated as primary brand to any skin."]);
                                    return $resource;
                                }
                            } else {
                                $brand_loyalty_rules_resource->saveResourceFromData($this->data);
                            }
                            if ($this->data['enabled'] == 'Y' && $this->brand_resource->loyalty == 'N') {
                                $this->enableLoyaltyOnBrandResource();
                            } else if ($this->data['enabled'] == 'N' && $this->brand_resource->loyalty == 'Y') {
                                $this->disableLoyaltyOnBrandResource();

                            }
                            //$resource = Resource::dummyfactory(['result' => 'sucess']);
                            return $this->getBrandLoyaltyRulesForEdit($brand_id);
                        } else {
                            $resource = Resource::dummyfactory(['result' => 'failure', "message" => "Loyalty could not be enabled on brand because of an unknown error."]);
                            return $resource;
                        }
                    } else {
                        // ok its a GET so just return loyalty info
                        return $this->getBrandLoyaltyRulesForEdit($brand_id);
                    }
                } else if ($this->hasRequestForDestination('createpublickey')){
                    if ($skin_id = $this->data['skin_id']) {
                        return $this->createPublicKeyForSkin($skin_id,$brand_id);
                    } else {
                        return createErrorResourceWithHttpCode("No skin id submitted with request.", 422, 422);
                    }
                } else {
                    return $this->brand_resource;
                }
            } else {
                return createErrorResourceWithHttpCode('Brand does not exist with that id: '.$matches[1],422,422);
            }
        } else {
            return createErrorResourceWithHttpCode("This endpoint does not exist",422,422);
        }

    }

    function createPublicKeyForSkin($skin_id,$brand_id)
    {
        if ($skin_resource = Resource::find(new SkinAdapter(getM()), null, [TONIC_FIND_BY_METADATA => ["brand_id" => $brand_id,"skin_id"=>$skin_id]])) {
            if ($skin_resource->public_client_id == '') {
                $skin_resource->public_client_id = generateUUID();
                $skin_resource->save();
                return $skin_resource;
            } else {
                return createErrorResourceWithHttpCode(self::PUBLIC_KEY_ALREADY_EXISTS_ERROR, 422, 422);
            }
        } else {
            return createErrorResourceWithHttpCode("Unable to get Skin associated with brand.", 422, 422);
        }
    }

    function getBrandLoyaltyRulesForEdit($brand_id)
    {
        $blra = new BrandLoyaltyRulesAdapter(getM());
        if ($brand_loyalty_rules_resource = Resource::find($blra, null, [3=>['brand_id'=>$brand_id]])) {
            if ($this->isLoyaltyHomeGrown($brand_loyalty_rules_resource->loyalty_type)) {
                $brand_loyalty_rules_resource->enabled = $this->brand_resource->loyalty;
                return $brand_loyalty_rules_resource;
            } else {
                return createErrorResourceWithHttpCode(self::NO_HOMEGROWN_LOYALTY_ERROR, 422, 422);
            }

        } else {
            return createErrorResourceWithHttpCode("This Brand has no loyalty associated with it.", 422, 422);
        }
    }

    function isLoyaltyNOTHomeGrown($loyalty_type)
    {
        return ! $this->isLoyaltyHomeGrown($loyalty_type);
    }

    function isLoyaltyHomeGrown($loyalty_type)
    {
        $loyalty_type = strtolower($loyalty_type);
        if ($loyalty_type == 'splickit_earn') {
            return true;
        } else if ($loyalty_type == 'splickit_cliff') {
            return true;
        } else {
            return false;
        }
    }

    function enableLoyaltyOnBrandResource()
    {
        $this->brand_resource->loyalty = 'Y';
        return $this->brand_resource->save();
    }

    function disableLoyaltyOnBrandResource()
    {
        $this->brand_resource->loyalty = 'N';
        return $this->brand_resource->save();
    }



}

?>