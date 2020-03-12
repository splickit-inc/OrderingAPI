<?php
require_once 'lib'.DIRECTORY_SEPARATOR.'controllers'.DIRECTORY_SEPARATOR.'splickitcontroller.php';

Class MenuImportController extends SplickitController
{
	protected $no_errors;	
	
	function MenuImportController($mt,$u,$r,$l = 0)
	{
		parent::SplickitController($mt,$u,$r,$l);
		$this->adapter = new MerchantAdapter($this->mimetypes);
	}
	
	function insertIt($adapter,$data)
	{
		$resource = new Resource($adapter,$data);
		$adapter->insert($resource);
		if ($insert_id = $adapter->_insertId())
			return $insert_id;
		else {
			$this->no_errors = false;
			$this->rollback();
		}
	}
	
	function importMenuItems($merchant_id = 0,$file_name = null)
	{
		$this->no_errors = true;
		$this->request->_parseRequestBody();
		if ($merchant_id == 0)
		{
			$merchant_id = $this->request->data['merchant_id'];
			$file_name = $this->request->data['file_name'];
			if ($merchant_id == 0)
				die ("NO MERCHANT ID");
		}
		
		$in_modifiers = false;
		$in_menu_types = false;
		//$file_name = 'snarfs.txt';
		$sizes = array();
		$all_sizes[5] = 0;
		$menu_type_id = null;
		$modifier_group_id = null;
		//$merchant_id = 1020;
		$server_name = 'prod';
		if (substr_count(strtolower($_SERVER['SERVER_NAME']),'test') > 0 )
				$server_name = 'test';
		
		$file_path = '/usr/local/splickit/httpd/htdocs/'.$server_name.'/app2';

		$menu = file($file_path.'/menus/'.$file_name);
		myerror_log("menu retrieved.  number of lines is: ".sizeof($menu));
		myerror_log("we have gotten the file");
		myerror_log("$menu");
		//$this->begin();
		$this->adapter->_query('START TRANSACTION');
		$this->adapter->_query('BEGIN');
		foreach ($menu as $line)
		{
			// first get rid  of \n
			$line = str_replace("\n",'',$line);
			$line = str_replace("\r",'',$line);
			myerror_log("next line is: ".$line);
			$line_array = explode(',',$line);
			$size_of_line = sizeof($line_array);
			
			// now put the comma's back in 
			foreach ($line_array as &$column)
				$column = str_replace("*",",",$column);

			// if there is a value in the first column this is a menu type row
			if ($line_array[0] != null && substr_count($line_array[0],'xxxx') > 0)
				die ("file has already been run");
			else if ($line_array[0] != null && $line_array[0] == 'menu types')
				$in_menu_types = true;//do nothing, top of file
			else if ($line_array[0] != null  && strtolower($line_array[0]) == 'modifiers')
			{
				$in_menu_types = false;
				$in_modifiers = true;
			}
			else if ($line_array[0] != null && $in_menu_types)
			{
				$menu_type_data = array();
				$menu_type_data['menu_type_name']=$line_array[0];
				$menu_type_data['menu_type_description']=$line_array[2];
				$menu_type_data['priority']=$line_array[4];
				$menu_type_data['merchant_id'] = $merchant_id;
				// create menu type
				$menu_type_adapter = new MenuTypeAdapter($this->mimetypes);
				$menu_type_id = $this->insertIt($menu_type_adapter,$menu_type_data);

				myerror_log("new menu Type id: ".$menu_type_id);
				// now do sizes
				$sizes = null;
				$size_data = array();
				$size_adapter = new SizeAdapter($this->mimetypes);
				for ($i=5;$i<$size_of_line;$i++)
				{
					if ($line_array[$i] != null && trim($line_array[$i]) != '')
					{						
						$size_data['size_name'] = $line_array[$i];
						$size_data['size_print_name'] = $line_array[$i];
						$size_data['description'] = $line_array[$i];
						$size_data['priority'] = $i;
						$size_data['menu_type_id'] = $menu_type_id;
						$sizes[$i] = $this->insertIt($size_adapter,$size_data);
						$all_sizes[] = $sizes[$i];
					}
				}
				if (sizeof($sizes) == 1)
				{
					//we have a single sized thing so no need to ahve a short name
					myerror_log("we have a single sized size, so set short name to ' ' ");
					$resource =& Resource::find($size_adapter,$sizes[5],$options);
					$resource->size_print_name = 'one size';
					$size_adapter->update($resource);
				}
				myerror_log("done adding new menu type and associated sizes");
			} else if ($line_array[1] != null && $in_menu_types) {
				// we have a menu item and prices so load it up
				$item_data['item_name'] = $line_array[1];
				$item_data['description'] = $line_array[2];
				$item_data['item_print_name'] = $line_array[3];
				$item_data['priority'] = $line_array[4];
				$item_data['menu_type_id'] = $menu_type_id;
				$item_adapter = new ItemAdapter($this->mimetypes);
				$item_id = $this->insertIt($item_adapter,$item_data);
				
				// now do prices for each size
				$item_size_adapter = new ItemSizeAdapter($this->mimetypes);
				for ($i=5;$i<$size_of_line;$i++)
				{
					if ($line_array[$i] != null && trim($line_array[$i]) != '')
					{						
						$item_size_data['price'] = str_replace('$','', $line_array[$i]);
						$item_size_data['item_id'] = $item_id;
						$item_size_data['size_id'] = $sizes[$i];
						$item_size_data['priority'] = $i;
						$this->insertIt($item_size_adapter,$item_size_data);
					}
				}
			} else if ($line_array[0] != null && $in_modifiers) {
				//create new modifier group
				$modifier_group_id = null;
				$modifier_group_data['modifier_group_name'] = $line_array[0];
				$modifier_group_data['modifier_description'] = $line_array[2];
				$modifier_group_data['modifier_type'] = $line_array[3];
				$modifier_group_data['priority'] = $line_array[4];
				$modifier_group_data['merchant_id'] = $merchant_id;
				$modifier_group_adapter = new ModifierGroupAdapter($this->mimetypes);
				$modifier_group_id = $this->insertIt($modifier_group_adapter,$modifier_group_data);
			} else if ($line_array[1] != null && $in_modifiers) {
				// we have a modifier item so add it to the current group
				$modifier_item_data['modifier_group_id'] = $modifier_group_id;
				$modifier_item_data['modifier_item_name'] = $line_array[1];
				$modifier_item_data['modifier_item_print_name'] = $line_array[3];
				$modifier_item_data['priority'] = $line_array[4];
				$modifier_item_adapter = new ModifierItemAdapter($this->mimetypes);
				$modifier_item_id = $this->insertIt($modifier_item_adapter,$modifier_item_data);
				
				// now do prices for each size
				$modifier_size_map_adapter = new ModifierSizeMapAdapter($this->mimetypes);
				for ($i=5;$i<$size_of_line;$i++)
				{
					if ($line_array[$i] != null && trim($line_array[$i]) != '')
					{						
						myerror_log("about to do insert with size all_sizes[$i]: ".$all_sizes[$i]);
						$modifier_size_data['modifier_price'] = str_replace('$','', $line_array[$i]);
						$modifier_size_data['modifier_item_id'] = $modifier_item_id;
						$modifier_size_data['size_id'] = $all_sizes[$i];
						$this->insertIt($modifier_size_map_adapter,$modifier_size_data);
					}
				}
			} else {
				;//do nothing
			}
			
		} // foreach line
		
		if ($this->no_errors) {
			$this->adapter->_query('COMMIT');
			
			// now set the flag so the file cant be run again accidentally
			$file_data = "xxxxxxxx\n";
			$file_data .= file_get_contents($file_name);
			file_put_contents($file_name, $file_data);
			
		} else
			$this->adapter->_query('ROLLBACK');
				
	} // function import menu items
	  
   function rollback(){
		myerror_log("starting rollback");
   	    $this->adapter->_query('ROLLBACK');
   	    die ("there was an error in the data");
   }
	
}