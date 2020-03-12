<?php

class SkinLightAdapter extends SkinAdapter
{

    function &select($url, $options = NULL)
    {
        $options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
        return MySQLAdapter::select($url,$options);
    }
}
?>
