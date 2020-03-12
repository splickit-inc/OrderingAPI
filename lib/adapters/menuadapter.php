<?php

class MenuAdapter extends MySQLAdapter
{
  function MenuAdapter($mimetypes)
  {
    parent::MysqlAdapter(
      $mimetypes,
      'Menu',
      '%([0-9]{4,11})%',
      '%d',
      array('menu_id'),
      null,
      array('created', 'modified')
    );
  }

  function &select($url, $options = NULL)
  {
    $options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    return parent::select($url, $options);
  }

  function insert(&$resource)
  {
      $merchant_ids = isset($resource->merchant_ids) ? $resource->merchant_ids : null ;
      if (parent::insert($resource)) {
          if ($merchant_ids) {
              $s = explode(',',$merchant_ids);
              $mmma = new MerchantMenuMapAdapter(getM());
              foreach ($s as $merchant_id) {
                  $merchant_menu_map_data['menu_id'] = $resource->menu_id;
                  $merchant_menu_map_data['merchant_id'] = $merchant_id;
                  $merchant_menu_map_data['merchant_menu_type'] = 'pickup';
                  Resource::createByData($mmma,$merchant_menu_map_data);
                  $merchant_menu_map_data['merchant_menu_type'] = 'delivery';
                  Resource::createByData($mmma,$merchant_menu_map_data);
              }
          }
          return true;
      }
      return false;
  }

    /**
   *
   * @desc will return the time stamp of the last 'official' menu change
   * @param int $menu_id
   * @return int
   */
  static function getMenuStatus($menu_id)
  {
    $menu_adapter = new MenuAdapter(getM());
    $menu_record = $menu_adapter->getRecord(array("menu_id" => $menu_id));
    $menu_status = $menu_record['last_menu_change'];
    return $menu_status;
  }

  static function getMenuVersion($menu_id)
  {
    $menu_adapter = new MenuAdapter(getM());
    $menu_record = $menu_adapter->getRecord(array("menu_id" => $menu_id));
    return $menu_record['version'];
  }

  static function incrementAllMenus()
  {
    $menu_adapter = new MenuAdapter(getM());
    $sql = "UPDATE Menu SET last_menu_change = NOW()";
    if ($menu_adapter->_query($sql))
      return true;
    else {
      MailIt::sendErrorEmail("Error thrown incrementing all the menu status", "error: " . $menu_adapter->getLastErrorText());
      return false;
    }

  }

  static function touchMenu($menu_id, $time_stamp = 0)
  {
    if ($menu_id) {
      myerror_logging(3, "about to update the menu status key to now");
      $menu_resource = Resource::find(new MenuAdapter(getM()), '' . $menu_id);
      if ($time_stamp == 0)
        $time_stamp = time();
      $menu_resource->last_menu_change = $time_stamp;
      $menu_resource->save();
    } else {
      throw new Exception("no menu id submitted for touchMenu", 999);
    }
  }

  static function validateAllMenus($merchant_id = 0)
  {
    // validate all menus
    if ($merchant_id == 0)
      $data['merchant_id'] = array(">" => "1000");
    else
      $data['merchant_id'] = $merchant_id;
    // this is for testing so it doesn't take forever to run unit tests
    if (isLaptop())
      $data['merchant_id'] = 1004;

    $options[TONIC_FIND_BY_METADATA] = $data;
    $options[TONIC_JOIN_STATEMENT] = " JOIN Merchant ON Merchant_Menu_Map.merchant_id = Merchant.merchant_id ";
    $options[TONIC_FIND_BY_STATIC_METADATA] = " Merchant.active = 'Y' ";

    $merchant_menu_maps = Resource::findAll(new MerchantMenuMapAdapter(getM()), null, $options);
    foreach ($merchant_menu_maps as $merchant_menu_map) {
      myerror_log("getting complete menu for merchant id: " . $merchant_menu_map->merchant_id . "   menu_id: " . $merchant_menu_map->menu_id);
      CompleteMenu::getCompleteMenu($merchant_menu_map->menu_id, 'Y', $merchant_menu_map->merchant_id);
      set_time_limit(30);
    }
    MenuAdapter::sqlMenuChecks();
    myerror_log("Validate All Menus has completed");
    return true;
  }

  static function sqlMenuChecks()
  {
    // DO SQL CHECKS
    // 1.  JM MENU - check for ingredients without ingredient ID's
    $sql = "SELECT b.modifier_item_name, c.modifier_type, a.* FROM Modifier_Size_Map a JOIN Modifier_Item b ON a.modifier_item_id = b.modifier_item_id JOIN Modifier_Group c ON b.modifier_group_id = c.modifier_group_id WHERE c.menu_id = 102433 AND a.merchant_id = 0 AND (external_id IS NULL OR external_id = '') AND a.active = 'Y' AND c.active = 'Y' AND c.logical_delete = 'N' AND b.logical_delete = 'N' AND c.modifier_type != 'Q'";
    $options[TONIC_FIND_BY_SQL] = $sql;
    $mspa = new ModifierSizeMapAdapter(getM());
    $results = $mspa->select(null, $options);
    if (count($results) > 0) {
      $alert = "We have Jersey Mikes Modifiers with no ingredient id's";
      foreach ($results as $row) {
        $string = $string . $row['modifier_item_name'] . '-' . $row['modifier_size_id'] . ';';
      }
      $alert = "We have Jersey Mikes Modifiers with no ingredient id's: $string";
      MailIt::sendErrorEmail("Jersey Mikes Menu Problem!", $alert);
    }
    return $string;

  }

  /**
   * @codeCoverageIgnore
   */
  function importMenu($menu_id, $from_db, $to_db, $brand_id = 0)
  {
    if (isProd())
      die("HEY MORON, YOU'RE FIRED!");

    if ($from_db == 'prod')
      $_SERVER['FORCE_PROD'] = 'true';
    elseif ($from_db == 'test')
      $_SERVER['FORCE_TEST'] = 'true';
    else
      myerror_log("FROM DB set to local");

    $db = DataBase::getNewInstance();
    $menu_adapter = new MenuAdapter(getM());
    $menu_type_adapter = new MenuTypeAdapter(getM());
    $item_adapter = new ItemAdapter(getM());
    $size_adapter = new SizeAdapter(getM());
    $item_size_adapter = new ItemSizeAdapter(getM());
    $item_modifier_group_map_adapter = new ItemModifierGroupMapAdapter(getM());
    $item_modifier_item_map_adapter = new ItemModifierItemMapAdapter(getM());
    $modifier_group_adapter = new ModifierGroupAdapter(getM());
    $modifier_item_adapter = new ModifierItemAdapter(getM());
    $modifier_size_map_adapter = new ModifierSizeMapAdapter(getM());
    $photo_adapter = new PhotoAdapter(getM());

    $nutrition_adapter = new NutritionItemSizeInfosAdapter(getM());

    $menu_combo_adapter = new MenuComboAdapter(getM());
    $menu_combo_association_adapter = new MenuComboAssociationAdapter(getM());
    $menu_combo_price_adapter = new MenuComboPriceAdapter(getM());

    myerror_log("we have connected to the db");

    //get the menu
    if ($menu_resource = Resource::find($menu_adapter, '' . $menu_id))
      myerror_log("all is good, we have the menu record");
    else {
      $error = $menu_adapter->getLastErrorText();
      myerror_log("ERROR!  we couldn't get the menu: error: " . $error);
      die('couldnt get menu: ' . $error);
    }

    //first delete all NON merchant_id=0 price records
    /*    	if ($menu_resource->version > 2.0)
          {
          $delete_sql = "call SMAWSP_ADMIN_DEL_MERCHANT_PRICE_RECORDS(102433,0,'Y')";
          }
    */
    //get Menu Types
    $mt_options[TONIC_FIND_BY_METADATA]['menu_id'] = $menu_id;
    $menu_type_resources = Resource::findAll($menu_type_adapter, '', $mt_options);

    //item resources
    $item_data = array();
    $item_options[TONIC_FIND_BY_METADATA] = $item_data;
    $item_options[TONIC_JOIN_STATEMENT] = " JOIN Menu_Type ON Item.menu_type_id = Menu_Type.menu_type_id ";
    $item_options[TONIC_FIND_BY_STATIC_METADATA] = " Menu_Type.menu_id = $menu_id AND Menu_Type.logical_delete = 'N' ";
    $item_resources = Resource::findAll($item_adapter, '', $item_options);

    //photo resources
    $photo_data = array();
    $photo_options[TONIC_FIND_BY_METADATA] = $photo_data;
    $photo_options[TONIC_JOIN_STATEMENT] = " JOIN Item ON Item.item_id = Photo.item_id JOIN Menu_Type ON Item.menu_type_id = Menu_Type.menu_type_id ";
    $photo_options[TONIC_FIND_BY_STATIC_METADATA] = " Menu_Type.menu_id = $menu_id AND Menu_Type.logical_delete = 'N' ";
    $photo_resources = Resource::findAll($photo_adapter, '', $photo_options);

    //size resources
    $size_data = array();
    $size_options[TONIC_FIND_BY_METADATA] = $size_data;
    $size_options[TONIC_JOIN_STATEMENT] = " JOIN Menu_Type ON Sizes.menu_type_id = Menu_Type.menu_type_id ";
    $size_options[TONIC_FIND_BY_STATIC_METADATA] = " Menu_Type.menu_id = $menu_id AND Menu_Type.logical_delete = 'N' ";
    $size_resources = Resource::findAll($size_adapter, '', $size_options);

    //item_size_map_resource
    $item_size_data = array();
    $item_size_options[TONIC_FIND_BY_METADATA] = $item_size_data;
    $item_size_options[TONIC_JOIN_STATEMENT] = " JOIN Item ON Item.item_id = Item_Size_Map.item_id JOIN Menu_Type ON Item.menu_type_id = Menu_Type.menu_type_id ";
    $item_size_options[TONIC_FIND_BY_STATIC_METADATA] = " Menu_Type.menu_id = $menu_id AND Menu_Type.logical_delete = 'N' AND Item_Size_Map.merchant_id = 0";
    //$item_size_options[TONIC_SORT_BY_METADATA] = ' Item_Size_Map.priority DESC ';
    $item_size_resources = Resource::findAll($item_size_adapter, '', $item_size_options);

    //item_modifier_group_maps
    $item_modifier_group_data = array();
    $item_modifier_group_map_options[TONIC_FIND_BY_METADATA] = $item_modifier_group_data;
    $item_modifier_group_map_options[TONIC_JOIN_STATEMENT] = " JOIN Item ON Item.item_id = Item_Modifier_Group_Map.item_id JOIN Menu_Type ON Item.menu_type_id = Menu_Type.menu_type_id ";
    $item_modifier_group_map_options[TONIC_FIND_BY_STATIC_METADATA] = " Menu_Type.menu_id = $menu_id AND Menu_Type.logical_delete = 'N'  AND Item_Modifier_Group_Map.merchant_id = 0 ";
    //$item_modifier_group_map_options[TONIC_SORT_BY_METADATA] = array('Item_Modifier_Group_Map.priority DESC','display_name');
    $item_modifier_group_map_resources = Resource::findAll($item_modifier_group_map_adapter, '', $item_modifier_group_map_options);

    //item_modifier_item_maps
    $item_modifier_item_data = array();
    $item_modifier_item_map_options[TONIC_FIND_BY_METADATA] = $item_modifier_item_data;
    $item_modifier_item_map_options[TONIC_JOIN_STATEMENT] = " JOIN Item ON Item.item_id = Item_Modifier_Item_Map.item_id JOIN Menu_Type ON Item.menu_type_id = Menu_Type.menu_type_id ";
    $item_modifier_item_map_options[TONIC_FIND_BY_STATIC_METADATA] = " Menu_Type.menu_id = $menu_id AND Menu_Type.logical_delete = 'N' ";
    $item_modifier_item_map_resources = Resource::findAll($item_modifier_item_map_adapter, '', $item_modifier_item_map_options);

    //modifier_group resources
    $mg_options[TONIC_FIND_BY_METADATA]['menu_id'] = $menu_id;
    $modifier_group_resources = Resource::findAll($modifier_group_adapter, '', $mg_options);

    //modifier item resource
    $modifier_item_data = array();
    $modifier_item_options[TONIC_FIND_BY_METADATA] = $modifier_item_data;
    $modifier_item_options[TONIC_JOIN_STATEMENT] = " JOIN Modifier_Group ON Modifier_Item.modifier_group_id = Modifier_Group.modifier_group_id ";
    $modifier_item_options[TONIC_FIND_BY_STATIC_METADATA] = " Modifier_Group.menu_id = $menu_id AND Modifier_Group.logical_delete = 'N' ";
    //	$modifier_item_options[TONIC_SORT_BY_METADATA] = ' Modifier_Item.priority DESC, Modifier_Item.modifier_item_name ';
    $modifier_item_resources = Resource::findAll($modifier_item_adapter, '', $modifier_item_options);

    //modifier item price
    $modifier_item_size_options[TONIC_FIND_BY_METADATA] = $modifier_item_size_data;
    $modifier_item_size_options[TONIC_JOIN_STATEMENT] = " JOIN Modifier_Item ON Modifier_Item.modifier_item_id = Modifier_Size_Map.modifier_item_id JOIN Modifier_Group ON Modifier_Item.modifier_group_id = Modifier_Group.modifier_group_id ";
    $modifier_item_size_options[TONIC_FIND_BY_STATIC_METADATA] = " Modifier_Group.menu_id = $menu_id AND Modifier_Group.logical_delete = 'N' AND Modifier_Size_Map.merchant_id = 0";
    //	$modifier_item_size_options[TONIC_SORT_BY_METADATA] = ' Modifier_Size_Map.priority DESC ';
    $modifier_item_size_resources = Resource::findAll($modifier_size_map_adapter, '', $modifier_item_size_options);


    //combos
    $menu_combo_data['menu_id'] = $menu_id;
    $menu_combo_options[TONIC_FIND_BY_METADATA] = $menu_combo_data;
    $menu_combo_resources = Resource::findAll($menu_combo_adapter, '', $menu_combo_options);

    //combo associations
    $mca_options[TONIC_FIND_BY_METADATA] = $mca_data;
    $mca_options[TONIC_JOIN_STATEMENT] = " JOIN Menu_Combo ON Menu_Combo.combo_id = Menu_Combo_Association.combo_id ";
    $mca_options[TONIC_FIND_BY_STATIC_METADATA] = " Menu_Combo.menu_id = $menu_id AND Menu_Combo.logical_delete = 'N' ";
    $mca_resources = Resource::findAll($menu_combo_association_adapter, '', $mca_options);

    //combo prices
    $mcp_options[TONIC_FIND_BY_METADATA] = $mcp_data;
    $mcp_options[TONIC_JOIN_STATEMENT] = " JOIN Menu_Combo ON Menu_Combo.combo_id = Menu_Combo_Price.combo_id ";
    $mcp_options[TONIC_FIND_BY_STATIC_METADATA] = " Menu_Combo.menu_id = $menu_id AND Menu_Combo.logical_delete = 'N' AND Menu_Combo_Price.merchant_id = 0 ";
    $mcp_resources = Resource::findAll($menu_combo_price_adapter, '', $mcp_options);

    $nutrition_options[TONIC_FIND_BY_METADATA] = $nutrition_data;
      $nutrition_options[TONIC_JOIN_STATEMENT] = " JOIN Item ON Nutrition_Item_Size_Infos.item_id = Item.item_id JOIN Menu_Type ON Item.menu_type_id = Menu_Type.menu_type_id JOIN Menu ON Menu_Type.menu_id = Menu.menu_id ";
      $nutrition_options[TONIC_FIND_BY_STATIC_METADATA] = " Menu.menu_id = $menu_id ";
      $nutrition_resources = Resource::findAll($nutrition_adapter, '', $nutrition_options);


    if ($brand_id != 0) {
      $brand_loyalty_rules_adapter = new BrandLoyaltyRulesAdapter(getM());
      $brand_points_adapter = new BrandPointsAdapter(getM());
      $brand_points_object_map_adapter = new BrandPointsObjectMapAdapter(getM());
      $brand_earned_points_object_map_adapter = new BrandEarnedPointsObjectMapsAdapter($m);

      //brand loyalty rules
      $blr_options[TONIC_FIND_BY_METADATA] = array("brand_id" => $brand_id);
      $blr_resources = Resource::findAll($brand_loyalty_rules_adapter, '', $blr_options);

      //brand point values
      $bp_options[TONIC_FIND_BY_METADATA] = array("brand_id" => $brand_id);
      $bp_resources = Resource::findAll($brand_points_adapter, '', $bp_options);

      //brand points object map
      $bpom_options[TONIC_FIND_BY_METADATA] = $bpom_data;
      $bpom_options[TONIC_JOIN_STATEMENT] = " JOIN Brand_Points ON Brand_Points.brand_points_id = Brand_Points_Object_Map.brand_points_id ";
      $bpom_options[TONIC_FIND_BY_STATIC_METADATA] = " Brand_Points.brand_id = $brand_id ";
      $bpom_resources = Resource::findAll(new BrandPointsObjectMapAdapter(getM()), '', $bpom_options);

      //brand earned points object map
      $bepom_options[TONIC_FIND_BY_METADATA] = array("brand_id" => $brand_id);
      $bepom_resources = Resource::findAll($brand_earned_points_object_map_adapter, '', $bepom_options);

      unset($brand_loyalty_rules_adapter);
      unset($brand_points_adapter);
      unset($brand_points_object_map_adapter);
      unset($brand_earned_points_object_map_adapter);

    }

    unset($menu_type_adapter);
    unset($item_adapter);
    unset($photo_adapter);
    unset($size_adapter);
    unset($item_size_adapter);
    unset($menu_adapter);
    unset($item_modifier_group_map_adapter);
    unset($item_modifier_item_map_adapter);
    unset($modifier_group_adapter);
    unset($modifier_item_adapter);
    unset($modifier_size_map_adapter);
    unset($menu_combo_adapter);
    unset($menu_combo_association_adapter);
    unset($menu_combo_price_adapter);
    unset($nutrition_adapter);

    $_SERVER['FORCE_PROD'] = 'false';
    $_SERVER['FORCE_TEST'] = 'false';
    unset($_SERVER['DB_INFO']);

    if ($to_db == 'test')
      $_SERVER['FORCE_TEST'] = 'true';
    else if ($to_db == 'prod')
      die("YOU'RE FIRED MORON");
    else if ($to_db == 'local')
      ; // do nothing
    else
      die("NO to_db listed import has been killed");

    DataBase::getNewInstance();
    $menu_adapter = new MenuAdapter(getM());
    $menu_type_adapter = new MenuTypeAdapter(getM());
    $item_adapter = new ItemAdapter(getM());
    $photo_adapter = new PhotoAdapter($m);
    $size_adapter = new SizeAdapter(getM());
    $item_size_adapter = new ItemSizeAdapter(getM());
    $item_modifier_group_map_adapter = new ItemModifierGroupMapAdapter(getM());
    $item_modifier_item_map_adapter = new ItemModifierItemMapAdapter(getM());
    $modifier_group_adapter = new ModifierGroupAdapter(getM());
    $modifier_item_adapter = new ModifierItemAdapter(getM());
    $modifier_size_map_adapter = new ModifierSizeMapAdapter(getM());
    $nutrition_adapter = new NutritionItemSizeInfosAdapter(getM());

    $menu_combo_adapter = new MenuComboAdapter(getM());
    $menu_combo_association_adapter = new MenuComboAssociationAdapter(getM());
    $menu_combo_price_adapter = new MenuComboPriceAdapter(getM());

    $menu_resource->_adapter = $menu_adapter;
    $menu_resource->_exists = false;
    $menu_resource->advancedSave();

    myerror_log("we have connected to the db");

    // must delete the Item_Modifier_Group_Map records and the Item_Modifier_Item_Map records
    $sql = "DELETE Item_Modifier_Item_Map  FROM  Item_Modifier_Item_Map  INNER JOIN Item b ON Item_Modifier_Item_Map.item_id = b.item_id INNER JOIN Menu_Type c ON c.menu_type_id = b.menu_type_id WHERE c.menu_id = $menu_id;";
    $menu_adapter->_query($sql);
    $sql = "DELETE Item_Modifier_Group_Map  FROM  Item_Modifier_Group_Map  INNER JOIN Item b ON Item_Modifier_Group_Map.item_id = b.item_id INNER JOIN Menu_Type c ON c.menu_type_id = b.menu_type_id WHERE c.menu_id = $menu_id";
    $menu_adapter->_query($sql);

    $this->importTheResources($menu_type_resources, $menu_type_adapter);
    $this->importTheResources($item_resources, $item_adapter);
    $this->importTheResources($photo_resources, $photo_adapter);
    $this->importTheResources($size_resources, $size_adapter);
    $this->importTheResources($item_size_resources, $item_size_adapter);
    $this->importTheResources($modifier_group_resources, $modifier_group_adapter);
    $this->importTheResources($modifier_item_resources, $modifier_item_adapter);
    $this->importTheResources($modifier_item_size_resources, $modifier_size_map_adapter);
    $this->importTheResources($item_modifier_group_map_resources, $item_modifier_group_map_adapter);
    $this->importTheResources($item_modifier_item_map_resources, $item_modifier_item_map_adapter);
    $this->importTheResources($menu_combo_resources, $menu_combo_adapter);
    $this->importTheResources($mca_resources, $menu_combo_association_adapter);
    $this->importTheResources($mcp_resources, $menu_combo_price_adapter);
    $this->importTheResources($nutrition_resources, $nutrition_adapter);

    if ($brand_id != 0) {
      $brand_loyalty_rules_adapter = new BrandLoyaltyRulesAdapter(getM());
      $brand_points_adapter = new BrandPointsAdapter(getM());
      $brand_points_object_map_adapter = new BrandPointsObjectMapAdapter(getM());
      $brand_earned_points_object_map_adapter = new BrandEarnedPointsObjectMapsAdapter($m);


      $this->importTheResources($blr_resources, $brand_loyalty_rules_adapter);
      $this->importTheResources($bp_resources, $brand_points_adapter);
      $this->importTheResources($bpom_resources, $brand_points_object_map_adapter);
      $this->importTheResources($bepom_resources, $brand_earned_points_object_map_adapter);
    }

  }

  static function getMenuIdFromAnItemId($item_id)
  {
    $menu_sql = "SELECT Menu.menu_id FROM Menu join Menu_Type on (Menu.menu_id = Menu_Type.menu_id) join Item on (Item.menu_type_id = Menu_Type.menu_type_id) WHERE item_id = $item_id";
    $menu_adapter = new MenuAdapter(getM());
    $options[TONIC_FIND_BY_SQL] = $menu_sql;
    $result = $menu_adapter->select(null,$options);
    $menu_id = $result[0]['menu_id'];
    return $menu_id;
  }

  private function importTheResources($resources, $adapter)
  {
    foreach ($resources as $resource) {
      $resource->_exists = false;
      $resource->_adapter = $adapter;
      $resource->modified = time();
      $resource->advancedSave();
    }
  }
}

?>