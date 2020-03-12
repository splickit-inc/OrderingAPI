<?php

class SkinController extends SplickitController {

	function SkinController($mt,$u,$r,$l = 0) {		
		parent::SplickitController($mt,$u,$r,$l);								
	}
	
	function processRequest() {
        $skin_adapter = new SkinAdapter();
        if ($this->isThisRequestMethodAPost()) {
            //if ($skin_resource = Resource::find($skin_adapter,null,array(TONIC_FIND_BY_METADATA=>array("public_client_id"=>$client_id)))) {
            if ($skin_resource = Resource::find($skin_adapter,getSkinIdForContext())) {
                if (isset($this->request->data['password'])) {
                    if ($skin_resource->password == null) {
                        $skin_resource->password = Encrypter::Encrypt($this->request->data['password']);

                        if ($skin_resource->save()) {
                            $skin_adapter->bustCacheFromSkinResource($skin_resource);
                            return Resource::dummyfactory(array("result"=>'success'));
                        } else {
                            return createErrorResourceWithHttpCode("There was an internal error and the password could not be saved. Please contact customer service",500,null,null);
                        }
                    } else {
                        return createErrorResourceWithHttpCode("Passowrd cannot be reset at this time, please contact customer service",500,null,null);
                    }
                } else {
                    return createErrorResourceWithHttpCode("Illegal Function Attempted",422,null,null);
                }
            } else {
                return createErrorResourceWithHttpCode("Authentication Error",401,null,null);
            }
        } else if ($this->isThisRequestMethodADelete()) {
            //if (isNotProd())
            if ($this->hasRequestForDestination('delete_password')) {
                if ($skin_id = getSkinIdForContext() ) {
                    if ($skin_id > 0) {
                        $sql = "UPDATE Skin SET password = NULL WHERE skin_id = $skin_id LIMIT 1";
                        myerror_log("ABOUT TO DELETE PASSSWORD: $sql");
                        $skin_adapter->_query($sql);
                        $skin_adapter->bustCacheFromSkinId($skin_id);
                        if ($skin_adapter->_affectedRows() == 1) {
                            return Resource::dummyfactory(array("result"=>'success'));
                        } else if ($skin_adapter->_affectedRows() == 0) {
                            return createErrorResourceWithHttpCode("No Rows Were Updated. Possibly already null.",422,null,null);
                        }
                    }
                }
            }
            return createErrorResourceWithHttpCode("Method Error",404,null,null);
        }
	    $url = isset($this->request->fullUrl) ? $this->request->fullUrl : $this->request->url;
		if (preg_match('%/skins/([^?/]+)%', $url, $matches)) {
			$client_id = $matches[1];
			$skin_adapter = new SkinAdapter();
			$skins = $skin_adapter->findForBrand($client_id);
			if(count($skins) > 0) {
				$resource = Resource::dummyfactory($skins[0]);				
				
				if(preg_match('/apiv2/', $this->request->url)) {
					$resource->iphone_app_link = $resource->facebook_thumbnail_link;
					unset($resource->facebook_thumbnail_link);

					if($resource->feedback_url == null || trim($resource->feedback_url) == '') {
					  unset($resource->feedback_url);
					}
				}

                if($resource->skin_id == 149){
                  $url= "https://d38o1hjtj2mzwt.cloudfront.net/com.splickit.hollywoodbowl/merchant-location-images/large/HollywoodBowlMap.png";
                  $resource->set("map_url", $url);
                }

				$resource->set("http_code", 200);				
			} else {
				$resource = createErrorResourceWithHttpCode("Found no skins for brand: ".$client_id, 404);
			}
		} else {
			$resource = createErrorResourceWithHttpCode("Missing parameter in request. Url: ".$this->request->url, 400);
		}		
		return $resource;	
	}

}	

?>