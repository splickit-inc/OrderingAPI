<?php

class FavoriteController extends SplickitController
{

	function FavoriteController($mt,$u,$r,$l = 0)
	{
		parent::SplickitController($mt,$u,$r,$l);
		$this->adapter = new FavoriteAdapter($this->mimetypes);
	}

  function processV2Request()
  {
    if (isRequestMethodAPost($this->request)) {
      if ($order_id = $this->request->data['order_id']) {
        return $this->saveFavoriteFromOrderId($order_id,$this->request->data['favorite_name']);
      } else {
        return createErrorResourceWithHttpCode("no order id passed for favorite save",422,422);
      }
    } else if (preg_match('%/favorites/([0-9]{2,10})%', $this->request->url, $matches)) {
      $favorite_id = $matches[1];
      if (isRequestMethodADelete($this->request)) {
        return $this->deleteFavorite();
      } else {
        // we have a get favorite
        return Resource::find(new FavoriteAdapter($m),$favorite_id,null);
      }
    } else {
      return createErrorResourceWithHttpCode("no endpoint",488,488);
    }
  }

  function saveFavoriteFromOrderId($order_id,$favorite_name)
  {
    $complete_order = CompleteOrder::staticGetCompleteOrder($order_id,$m);
    $new_favorite_record['favorite_json'] = $this->getFavoriteJsonFromCompleteOrder($complete_order);
    $new_favorite_record['favorite_name'] = $favorite_name;
    $new_favorite_record['user_id'] = $complete_order['user_id'];
    $new_favorite_record['merchant_id'] = $complete_order['merchant_id'];

    if ($item_id = $complete_order['order_details'][0]["item_id"]) {
      $menu_id = MenuAdapter::getMenuIdFromAnItemId($item_id);
      $new_favorite_record['menu_id'] = $menu_id;
      $favorite_resource = $this->saveFavoriteReally($new_favorite_record);

      return $this->cleanFavoriteSavedResource($favorite_resource);
    } else {
      myerror_log("FAVORITE SAVE ERROR: cant get item id from old order");
      return createErrorResourceWithHttpCode("We're sorry, the menu has changed and this order is no longer valid to be saved as a favorite.",422,422);
    }
  }

  function deleteFavorite()
  {
    $message = $this->adapter->delete($this->request->url) ? "Your favorite was successfully deleted" : "Your favorite could not be deleted";
    $resource = Resource::dummyfactory(array("user_message"=>$message));
    return $resource;
  }

  /*
   * Function for V1 of API
   */

  function process()
  {
    if (strtolower($this->request->method) == 'post')
      return $this->saveFavorite();
    else if ($this->request->method == 'delete')
      return $this->deleteFavorite();
    else
      return $this->getFavorite();
  }

  function getFavorite()
  {
    myerror_log("starting the getFavorites");
    if ($favorites = $this->adapter->getRecords(array("user_id"=>$this->user['user_id'],"merchant_id"=>$this->request->data['merchant_id'])))
    {
      // get the json in array format so dave is happy
      foreach ($favorites as &$favorite)
      {
        // this should be a Hash, not an object  need the 'true' parameter on the json_decode method
        $favorite['favorite_order'] = objectToArray(json_decode($favorite['favorite_json']));

        if(isset($favorite['favorite_order']['items']))
          $this->clearPriceIdsFromItemsOfFavorite($favorite['favorite_order']['items']);

        unset($favorite['favorite_json']);
      }
    } else {
      $favorites = array();
    }

    $resource = Resource::dummyfactory(array("favorites"=>$favorites));
    return $resource;
  }

  function saveFavorite()
  {
    $submitted_data = $this->request->data;
    if (! is_numeric($submitted_data['user_id'])) {
      if ($user_record = UserAdapter::staticGetRecord(array("uuid"=>$submitted_data['user_id']),'UserAdapter')) {
        $submitted_data['user_id'] = $user_record['user_id'];
      }
    }

    $fave_data['user_id'] = $submitted_data['user_id'];
    $fave_data['merchant_id'] = $submitted_data['merchant_id'];
    $fave_data['favorite_name'] = $submitted_data['favorite_name'];
    $fave_data['favorite_json'] = $this->convertV1FavoriteJsonToV2Json(json_encode($submitted_data));
    $item_id = $submitted_data['items'][0]["item_id"];
    $menu_id = MenuAdapter::getMenuIdFromAnItemId($item_id);
    $fave_data['menu_id'] = $menu_id;

    return $this->saveFavoriteReally($fave_data);
  }

  function saveFavoriteReally($fave_data)
  {
    $fave_resource = new Resource($this->adapter, $fave_data);
    if ($fave_resource->save()) {
      $fave_id = $this->adapter->_insertId();
      myerror_log("the favorite was saved: ".$fave_id);
      $fave_resource->set('user_message','Your favorite was successfully stored.');
      $fave_resource->favorite_json = '';
      return $fave_resource;
    } else {
      return returnErrorResource("We're sorry, somethign went wrong and your favorite could not be saved, please try again");
    }
  }

  function cleanFavoriteSavedResource($resource)
  {
    return Resource::dummyfactory(array("favorite_id"=>$resource->favorite_id,"favorite_name"=>$resource->favorite_name,"user_message"=>$resource->user_message));
  }

    function getFavoriteJsonFromCompleteOrder($complete_order)
    {
        $new_favorite = $this->getBaseFavoriteData($complete_order);
        foreach ($complete_order['order_details'] as $item) {
            myerror_log("checking item for favorite. items_size_id: ".$item['item_size_id'],5);
            if ($item['item_size_id'] > 0) {
                $new_favorite['items'][] = $this->convertOrderDetailToFavoriteItem($item);
            } else {
                myerror_log("this is a non item row on the order so we'll skip for favorite save");
            }
        }
        return json_encode($new_favorite);

    }

  function convertV1FavoriteJsonToV2Json($favorite_json)
  {
    $favorite_array = json_decode($favorite_json,true);
    return json_encode($this->convertV1FavoriteArrayToV2($favorite_array));
  }

  function getBaseFavoriteData($info)
  {
    $new_favorite = array();
    $new_favorite['note'] = $info['note'];
    $new_favorite['merchant_id'] = $info['merchant_id'];
    $new_favorite['user_id'] = $info['user_id'];
    return $new_favorite;
  }

  function convertV1FavoriteArrayToV2($favorite_array)
  {
    $new_favorite = $this->getBaseFavoriteData($favorite_array);
    $new_favorite['favorite_name'] = $favorite_array['favorite_name'];
    foreach ($favorite_array['items'] as $item) {
      $new_favorite['items'][] = $this->convertV1FavoriteItemArrayToV2($item);
    }

    $this->clearPriceIdsFromItemsOfFavorite($new_favorite["items"]);

    return $new_favorite;
  }

  function getBaseFavoriteItemArray($item)
  {
    $new_item = array();
    $new_item['quantity'] = $item['quantity'];
    $new_item['note'] = $item['note'];
    $new_item['size_id'] = $item['size_id'];
    $new_item['item_id'] = $item['item_id'];
    $new_item['sizeprice_id'] = isset($item['sizeprice_id']) ? $item['sizeprice_id'] : $item['item_size_id'];
    return $new_item;
  }

  function convertOrderDetailToFavoriteItem($order_detail)
  {
    $new_item = $this->getBaseFavoriteItemArray($order_detail);
    foreach ($order_detail['order_detail_complete_modifier_list_no_holds'] as $modifier) {
      $new_item['mods'][] = $this->convertOrderedModifierToSupeSetModifier($modifier);
    }
    return $new_item;
  }

  function convertOrderedModifierToSupeSetModifier($modifier)
  {
    $new_modifier['modifier_item_id'] = $modifier['modifier_item_id'];
    $new_modifier['mod_item_id'] = $modifier['modifier_item_id'];
    $new_modifier['mod_sizeprice_id'] = $modifier['modifier_size_id'];
    $new_modifier['quantity'] = $modifier['mod_quantity'];
    $new_modifier['mod_quantity'] = $modifier['mod_quantity'];
    return $new_modifier;
  }

  function convertV1FavoriteItemArrayToV2($item)
  {
    $new_item = $this->getBaseFavoriteItemArray($item);
    foreach ($item['mods'] as $modifier)
    {
      $new_item['mods'][] = $this->convertV1FavoriteModifierArrayToV2($modifier);
    }
    return $new_item;
  }

  function convertV1FavoriteModifierArrayToV2($modifier)
  {
    $modifier['quantity'] = $modifier['mod_quantity'];
    if (!isset($modifier['mod_item_id'])) {
      if ($mod_size_id = $modifier['mod_sizeprice_id']) {
        if ($modifier_record = ModifierSizeMapAdapter::staticGetRecordByPrimaryKey($mod_size_id,'ModifierSizeMapAdapter')) {
          $modifier['modifier_item_id'] = $modifier_record['modifier_item_id'];
          $modifier['mod_item_id'] = $modifier_record['modifier_item_id'];
        }
      }
    } else {
      $modifier['modifier_item_id'] = $modifier['mod_item_id'];
    }
    return $modifier;
  }

  /**
   * @param $favorite_resource Resource
   *
   * @return false if not have items in other case return favorite resource
   */
  function convertExistingFavoriteToSuperSet(&$favorite_resource)
  {
    myerror_log("FAVORITE MIGRATION migrating favorite_id: ".$favorite_resource->favorite_id);
    $all_favorite_details = json_decode($favorite_resource->favorite_json,true);
    $favorite_items = $all_favorite_details['items'];
    myerror_log("the count of favorite items is: ".count($favorite_items));
    if(count($favorite_items) > 0) {
      $item = $favorite_items[0];
      if(isset($item['item_id'])) {
        $new_favorite_json = $this->convertV1FavoriteJsonToV2Json($favorite_resource->favorite_json);
        $new_favorite_json = str_replace("'","",$new_favorite_json);
        myerror_log("FAVORITE MIGRATION we have the new favorite json: $new_favorite_json");

        $item_id = $item['item_id'];
        myerror_log("FAVORITE MIGRATION about to get the menu_id from the item id: $item_id");
        $menu_sql = "SELECT Menu.* FROM Menu join Menu_Type on (Menu.menu_id = Menu_Type.menu_id) join Item on (Item.menu_type_id = Menu_Type.menu_type_id) WHERE item_id = $item_id";
        $menu_adapter = new MenuAdapter($m);
        $options[TONIC_FIND_BY_SQL] = $menu_sql;
        if ($menu_resource = Resource::find($menu_adapter,'',$options)){
          $menu_id = $menu_resource->menu_id;
          myerror_log("FAVORITE MIGRATION we have the menu_id: $menu_id");
          $favorite_resource->menu_id = $menu_id;
          $favorite_resource->favorite_json = $new_favorite_json;
          if ($favorite_resource->save()) {
            return $favorite_resource;
          } else {
            myerror_log("FAVORITE MIGRATION couldnt save new favorite.");
          }
        } else {
          myerror_log("FAVORITE MIGRATION couldnt get menu from favorite. no longer valid. set logical delete to 'N'");
          $favorite_resource->logical_delete = 'N';
          $favorite_resource->save();
        }
      } else {
        myerror_log("FAVORITE MIGRATION no item_id's as part of favorite. not valid so set logical delete to 'N'");
        $favorite_resource->logical_delete = 'N';
        $favorite_resource->save();
      }
    } else {
      myerror_log("FAVORITE MIGRATION No items as part of favorite, delete record");
      $favorite_resource->_adapter->delete($favorite_resource->favorite_id);
    }
    return false;
  }

  function getFavorites($merchant = null, $api_version = 2)
  {
    $favorites = array();
    myerror_log("getting favorites with api version: $api_version",3);
    if (isset($this->user) && $this->user['user_id'] > 1) {
      if ($api_version == 2) {
        $favorites = $this->getFavoritesForUserMenuCombination( $this->user['user_id'], $merchant);
      } else {
        if ($favorite_list = FavoriteAdapter::staticGetRecords(array("user_id"=>$this->user['user_id'],"menu_id"=>$merchant->menu_id),"FavoriteAdapter")) {
          myerror_log("There are favorites. total count: ".sizeof($favorite_list),3);
          foreach ($favorite_list as $favorite) {
            myerror_log("adding favorite: ".$favorite['favorite_name'],3);
            $favorites[] = $favorite['favorite_json'];
          }
        } else {
          myerror_log("no favorites for user menu combination",3);
        }
      }
    }
    logData($favorites,"FAVORITES",5);
    return $favorites;
  }


  function getFavoritesForUserMenuCombination($user_id, $merchant)
  {
    $favorites = array();
    if (isUserIdARegularUserId($user_id)) {

      if ($unfiltered_favorite_list = Resource::findAll($this->adapter, null, array(TONIC_FIND_BY_METADATA => array("user_id" => $user_id)))) {
        myerror_log("we have an unfiltered favorite list",3);
        foreach ($unfiltered_favorite_list as $fav_resource) {
          logData($fav_resource->getDataFieldsReally(),"FAVORITE",3);
        }
        if(isset($merchant)){
          $menu = $merchant->menu;
          foreach ($menu['menu_types'] as $menu_type) {
            foreach ($menu_type['menu_items'] as $item) {
              foreach ($item['size_prices'] as $item_size) {
                $active_item_size_list[$item_size['item_id'] . '-' . $item_size['size_id']] = true;
              }

              if (isset($item['modifier_groups'])) {
                foreach ($item['modifier_groups'] as $modifier_group) {
                  foreach ($modifier_group['modifier_items'] as $modifier_item) {
                    if (isset($modifier_item['nested_items'])) {
                      foreach ($modifier_item['nested_items'] as $nested_modifier_item) {
                        $active_modifiers_list[$item['item_id'] . '-' . $nested_modifier_item['modifier_item_id']] = true;
                      }
                    } else {
                      $active_modifiers_list[$item['item_id'] . '-' . $modifier_item['modifier_item_id']] = true;
                    }

                  }
                }
              }
            }
          }
        }
        
        foreach ($unfiltered_favorite_list as $favorite_resource) {
          if ($favorite_resource->menu_id == 0) {
            // we have an old style favorite that needs to be converted to the super set
            if (isset($merchant) && $merchant->merchant_id != $favorite_resource->merchant_id) {
              // skip favorites that don't match this merchant_id to save time on merchant retrieval
              continue;
            }

            if (!$this->convertExistingFavoriteToSuperSet($favorite_resource)) {
              // if favorite doesn't get converted, then skip it.
              continue;
            }
          }

          if ($menu && $favorite_resource->menu_id != $menu['menu_id']) {
            myerror_log("Favorite:favorite doesn't match this menu. " . $favorite_resource->menu_id . " != $menu->menu_id");
            continue;
          }

          $favorite_order = $this->cleanFavoriteOrder(json_decode($favorite_resource->favorite_json, true));
          if (isset($merchant) ) {
            myerror_log("Favorite: merchant and menu present");
            if ($this->validateFavoriteAgainstActiveItemsSizesAndModifiers($favorite_order, $active_item_size_list, $active_modifiers_list)) {
              myerror_log("Favorite: valid on merchant and menu");
              $favorites[] = array("favorite_id" => $favorite_resource->favorite_id, "favorite_name" => $favorite_resource->favorite_name, "favorite_order" => $favorite_order);
            }
          }else{
            myerror_log("Favorite: favorite without validation");
            $favorites[] = array("favorite_id" => $favorite_resource->favorite_id, "favorite_name" => $favorite_resource->favorite_name, "favorite_order" => $favorite_order);
          }
        }
      }
    }
    return $favorites;
  }

  function validateFavoriteAgainstActiveItemsSizesAndModifiers($favorite_order, $active_item_size_list, $active_modifiers_list)
  {
    foreach ($favorite_order['items'] as $item) {
      $item_size_string = $item['item_id'] . '-' . $item['size_id'];
      myerror_logging(3, "Favorite:checking order $item_size_string against merchant menu");
      if ($active_item_size_list[$item_size_string] && $this->ensureOrderAgainstActiveModifiers($item['item_id'], $item['mods'], $active_modifiers_list)) {
        continue;
      } else {
        return false;
      }
    }
    return true;
  }

  function ensureOrderAgainstActiveModifiers($item_id, $item_mods, $active_modifiers_list)
  {
    foreach ($item_mods as $mod) {
      $item_mod_string = $item_id . '-' . $mod['modifier_item_id'];
      myerror_logging(3, "Favorite:checking order modifier $item_mod_string against merchant menu");
      if ($active_modifiers_list[$item_mod_string]) {
        continue;
      } else {
        return false;
      }
    }
    return true;
  }
  
  function cleanFavoriteOrder($favorite_order)
  {    
    $favorite_items = $this->clearPriceIdsFromItemsOfFavorite($favorite_order['items']);
    
    return array("note" => $favorite_order['note'], "items" => $favorite_items);
  }

  function clearPriceIdsFromItemsOfFavorite($favorite_items){
    return array_map(function($item){
      unset($item["sizeprice_id"]);
      if(isset($item["mods"])){
        $item["mods"] = array_map(function($mod){
          unset($mod["mod_sizeprice_id"]);
          return $mod;
        }, $item["mods"]);
      }
      return $item;
    }, $favorite_items);
    
  }
  
}

?>
