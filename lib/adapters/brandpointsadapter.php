<?php

class BrandPointsAdapter extends MySQLAdapter
{
	protected $brand_points_list;

	function BrandPointsAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Brand_Points',
			'%([0-9]{3,10})%',
			'%d',
			array('brand_points_id'),
			null,
			array('created','modified')
			);
		
		//$this->allow_full_table_scan = true;
						
	}
	
	function &select($url, $options = NULL)
    {
		$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    	return parent::select($url,$options);
	}
	
	/**
	 * 
	 * @desc given a brand id get the list of items and points associated.  returns a hash with 'size1234567'  or   'item345678'  as the index.
	 * 
	 * @param int $brand_id
	 * 
	 * @return hash
	 */
	function getBrandPointsList($brand_id)
	{
		$brand_points_list = array();
		$resources = $this->getBrandPointsResourceList($brand_id);
		foreach ($resources as $resource)
		{
			$object_type = $resource->object_type;
			$object_id = $resource->object_id;
			$data_fields = $resource->getDataFieldsReally();
			$brand_points_list[$object_type.'_'.$object_id] = $data_fields;
		}
		$this->brand_points_list = $brand_points_list;
		return $brand_points_list;
	}

	function getBrandPointsResourceList($brand_id)
	{
		$brand_point_data['brand_id'] = $brand_id;
		$options[TONIC_FIND_BY_METADATA] = $brand_point_data;
		$options[TONIC_JOIN_STATEMENT] = " JOIN Brand_Points_Object_Map ON Brand_Points.brand_points_id = Brand_Points_Object_Map.brand_points_id ";
		$options[TONIC_FIND_STATIC_FIELD] = " Brand_Points_Object_Map.object_type, Brand_Points_Object_Map.object_id ";
		$options[TONIC_FIND_BY_STATIC_METADATA] = " Brand_Points_Object_Map.logical_delete = 'N' ";
		if ($bpi_resources = Resource::findALL($this,'',$options)) {
			return $bpi_resources;	
		} else {
			return false;
		}
		
	}
	
	/**
	 * @desc given a cart item with a points_used value of greater than 0, it validates against the brands rules.
	 * 
	 * @param $submitted_cart_item
	 * 
	 * @return the brand points information array if valid, false if not valid
	 */
	
	function validateCartItem($submitted_cart_item)
	{
		if ($brand_points_list = $this->brand_points_list) {
			;//all is good
		} else {
			throw new Exception("Brand points list has not been loaded yet, cannot validate cart item.", $code);
		}

		if ($submitted_cart_item['points_used'] < 1) {
			throw new Exception("trying to validate a cart item that has no points_used field.", $code);
		}

		if ($item_size_resource = $this->getItemSizeResourceWithMenuTypeId($submitted_cart_item['sizeprice_id'])) {
			if ($brand_points_record = $this->getBrandPointsRecordForOrderedItem($item_size_resource, $brand_points_list)) {
				$price = $item_size_resource->price;
			} else {
				return false;
			}
		} else {
			throw new Exception("Cant get Item information for points validation for item_size_id: " . $submitted_cart_item['sizeprice_id'], $code);
		}

		//i'm wondering if i want to return the item with additional data, like how much or what percent off the points are buying them, etc...
		$brand_points_record['amount_off_from_points'] = $price;

		if ($submitted_cart_item['points_used'] != $brand_points_record['points']) {
			myerror_log("ERROR! BRAND POINTS MISMATCH.  submitted points: " . $submitted_cart_item['points_used'] . "    brand points record worth: " . $brand_points_record['points']);
			recordError("BRAND POINTS MISMATCH", "ERROR! BRAND POINTS MISMATCH.  submitted points: " . $submitted_cart_item['points_used'] . "    brand points record worth: " . $brand_points_record['points']);
			return false;
		/* so this was causing problems with carts since we dont save the brand_points_id in the cart. i think its actually redundant so commented out for now.
 		} else if ($submitted_cart_item['brand_points_id'] != $brand_points_record['brand_points_id']) {
			myerror_log("ERROR! BRAND POINTS ID MISMATCH.  submitted id: ".$submitted_cart_item['brand_points_id']."    brand points record id: ".$brand_points_record['brand_points_id']);
			recordError("BRAND POINTS ID MISMATCH", "ERROR! BRAND POINTS ID MISMATCH.  submitted id: ".$submitted_cart_item['brand_points_id']."    brand points record id: ".$brand_points_record['brand_points_id']);
			return false;
		*/
		} else {
			return $brand_points_record;
		}
		
	}

    function getItemSizeResourceWithMenuTypeId($item_size_id)
    {
        $sql = "SELECT a.*,b.menu_type_id FROM Item_Size_Map a JOIN Item b ON a.item_id = b.item_id WHERE a.item_size_id = $item_size_id";
        $options[TONIC_FIND_BY_SQL] = $sql;
        $item_size_adapter = new ItemSizeAdapter($mimetypes);
        return Resource::find($item_size_adapter,null,$options);
    }

    function getBrandPointsRecordForOrderedItem($ordered_item_resource_with_menu_type_id,$brand_points_list)
    {
        if ($brand_points_record = $brand_points_list['size_'.$ordered_item_resource_with_menu_type_id->size_id]) {
            myerror_logging(3,'we have a valid size_id for points');
        } else if (($brand_points_record = $brand_points_list['item_'.$ordered_item_resource_with_menu_type_id->item_id])) {
            myerror_logging(3,'we have a valid item_id for points');
        } else if (($brand_points_record = $brand_points_list['menu_type_'.$ordered_item_resource_with_menu_type_id->menu_type_id])) {
            myerror_logging(3,'we have a valid menu_type_id for points');
        } else {
            $json_string = json_encode($ordered_item_resource_with_menu_type_id->getDataFieldsReally());
            myerror_logging(3,"no valid match for points redemption for item_size_record: $json_string");
            return false;
        }
        return $brand_points_record;
    }
	
}
?>