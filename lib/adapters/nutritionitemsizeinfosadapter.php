<?php

class NutritionItemSizeInfosAdapter extends MySQLAdapter
{
    const TYPE_ID_FIELD = 'nutritional_label';
    var $nutrition_units;

    function __construct($m)
    {
        parent::MysqlAdapter(
            $m,
            'Nutrition_Item_Size_Infos',
            '%([0-9]{4,10})%',
            '%d',
            array('id'),
            null,
            array('created', 'modified')
        );
        $this->nutrition_units = $this->getValuesAndNamesFromLookupTable();
    }

    function &select($url, $options = NULL)
    {
        return parent::select($url, $options);
    }

    function retrieveNutritionSizesInfo($item_id, $size_id)
    {
        myerror_log("About to retrieve the nutrtion information for item_id: $item_id,  size_id: $size_id");
        $results = array();
        if (($nutrition_sizes_info_data_record = $this->getNutritionalSizesInfo($item_id, $size_id)) && $this->nutrition_units) {
            $results[] = ['label'=>'Serving Size','value'=>$nutrition_sizes_info_data_record['serving_size']];
            foreach ($this->nutrition_units as $key => $unit) {
                $label = capitalizeWordAndAddSpaceBetweenWords($key);
                $raw_value = $nutrition_sizes_info_data_record[$key];
                if ($raw_value == null) {
                    $value = 'Not Available';
                } else {
                    $value = convertDecimalToIntegerOrRoundUp($raw_value);
                    $value = $value.$unit;
                }
                $array_labels_values = array('label' => $label, 'value' => $value);
                $results[] = $array_labels_values;
            }
        }
        return $results;
    }

    private function getValuesAndNamesFromLookupTable()
    {
        $results = array();
        if ($records = LookupAdapter::staticGetRecords(array("type_id_field" => self::TYPE_ID_FIELD), 'LookupAdapter')) {
            foreach ($records as $record) {
                $key = $record['type_id_value'];
                $value = $record['type_id_name'];
                $results[$key] = $value;
            }
        }
        return $results;
    }

    private function getNutritionalSizesInfo($item_id, $size_id)
    {
        if($record = $this->getRecord(array("item_id" => $item_id, "size_id" => $size_id))) {
            return $record;
        }
    }
    
}
