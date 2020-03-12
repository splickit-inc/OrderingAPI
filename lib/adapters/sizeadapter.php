<?php

class SizeAdapter extends MySQLAdapter
{

	function SizeAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Sizes',
			'%([0-9]{1,15})%',
			'%d',
			array('size_id'),
			null,
			array('created','modified')
			);
	}
	
	function &select($url, $options = NULL)
    {
    	$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    	$options[TONIC_SORT_BY_METADATA] = 'priority DESC';
    	return parent::select($url,$options);
    }

	/**
     * @codeCoverageIgnore
     */
    function bulkInsert($resource)
    {
        $sizes_updated = false;
        $id_array = array();
    	if ($name_array = isStringList($resource->size_name)) {
            $priority = 200;
            foreach ($name_array as $name) {
                $name = trim($name);
                $new_size_data['menu_type_id'] = $resource->menu_type_id;
                $new_size_data['size_name'] = $name;
                $new_size_data['size_print_name'] = $name;
                $new_size_data['description'] = $name;
                $new_size_data['priority'] = $priority;
                $new_size_data['active'] = 'Y';
                $new_size_resource = Resource::factory($this, $new_size_data);
                if ($new_size_resource->save()) {
                    $id_array[] = $this->_insertId();
                }
                $priority = $priority - 10;

            }
        } else if (is_array($resource->size_name)) {
    		foreach ($resource->size_name as $size_record) {
    			if (isset($size_record['size_id'])) {
    				// do update
					$size_resource = Resource::find($this,"".$size_record['size_id']);
					unset($size_record['created']);
                    unset($size_record['modified']);
					if ($size_resource->saveResourceFromData($size_record)) {
                        $sizes_updated = true;
					}
				} else {
    				// do insert
					$size_record['menu_type_id'] = $resource->menu_type_id;
                    $new_size_resource = Resource::factory($this, $size_record);
                    $new_size_resource->save();
                    $id_array[] = $new_size_resource->_adapter->_insertId();
                }
			}
    	} else {
            if (parent::insert($resource)) {
                $id_array[] = $this->_insertId();
			}
		}
		if (sizeof($id_array) == 0 && $sizes_updated == false) {
    		return false;
		} else {
    		return $id_array;
		}

    }
}
?>
