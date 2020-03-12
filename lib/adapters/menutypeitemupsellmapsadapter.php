<?php

class MenuTypeItemUpsellMapsAdapter extends MySQLAdapter
{

    function MenuTypeItemUpsellMapsAdapter($mimetypes)
    {
        parent::MysqlAdapter(
            $mimetypes,
            'Menu_Type_Item_Upsell_Maps',
            '%([0-9]{4,15})%',
            '%d',
            array('id'),
            null,
            array('created','modified')
        );

        $this->allow_full_table_scan = false;

    }

    function &select($url, $options = NULL)
    {
        $options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
        return parent::select($url,$options);
    }

    function getUpsellItemsForMenuType($menu_type_id,$active) {
        $data['menu_type_id'] = $menu_type_id;
        if ($active == 'Y') {
            $data['active'] = 'Y';
        }
        return $this->getRecords($data);
    }

    function getUpsellItemsForMenuByMenuType($menu_id)
    {
        $sql = "SELECT a.* From Menu_Type_Item_Upsell_Maps a JOIN Menu_Type b ON a.menu_type_id = b.menu_type_id WHERE b.menu_id = $menu_id AND a.logical_delete = 'N' AND b.logical_delete = 'N'";
        $options[TONIC_FIND_BY_SQL] = $sql;
        $upsellsByMenuTypeId = [];
        foreach ( $this->select(null,$options) as $upsell_record) {
            $upsellsByMenuTypeId[$upsell_record['menu_type_id']][] = $upsell_record['item_id'];
        }
        return $upsellsByMenuTypeId;
    }

}
?>