<?php

class ItemAdapter extends MySQLAdapter
{

	function ItemAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Item',
			'%([0-9]{1,15})%',
			'%d',
			array('item_id'),
			null,
			array('created','modified')
			);
	}
	
	function &select($url, $options = NULL)
    {
    	$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    	return parent::select($url,$options);
    }
    
    /**
     * @codeCoverageIgnore
     */
    function bulkInsert($resource)
    {
        $id_array = array();
    	if ($name_array = isStringList($resource->item_name,';')) {
    		$priority = 200;
    		foreach ($name_array as $item_name) {
    		    unset($prices);
    		    $item_name = trim($item_name);
    			if (substr_count($item_name, "=") > 0) {
    				// we have a description as part of the name so separate it out
    				$n = explode("=", $item_name);
    				$name = $n[0];
    				$description = $n[1];
    				$prices = $n[2];
    			} else {
    				$name = $item_name;
    				$description = $item_name;
    			}
    			$item_data['menu_type_id'] = $resource->menu_type_id;
    			$item_data['tax_group'] = 1;
    			$item_data['item_name'] = $name;
    			$item_data['item_print_name'] = $name;
    			$item_data['description'] = $description;
    			$item_data['active'] = 'Y';
    			$item_data['priority'] = $priority;
    			$item_resource = Resource::factory($this,$item_data);
                if ($item_resource->save()) {
                    $insert_id = $this->_insertId();
                    if ($prices) {
                        $insert_id = $insert_id.'#'.$prices;
                    }
                    $id_array[] = $insert_id;
                }
    			$priority = $priority - 10;
    		}
    	} else {
            if (parent::insert($resource)) {
                $id_array[] = $this->_insertId();
            }
        }
        if (sizeof($id_array) == 0) {
            return false;
        } else {
            return $id_array;
        }

    }

}
?>