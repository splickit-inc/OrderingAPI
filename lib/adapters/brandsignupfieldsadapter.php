<?php

class BrandSignupFieldsAdapter extends MySQLAdapter
{
  function BrandSignupFieldsAdapter($mimetypes)
  {
    parent::MysqlAdapter(
        $mimetypes,
        'Brand_Signup_Fields',
        '%([0-9]{1,15})%',
        '%d',
        array('field_id'),
        NULL,
        array('created','modified')
    );
    $this->allow_full_table_scan = true;
  }
  
  static function getFieldsForBrand($brand_id) {
    $opts[TONIC_FIND_BY_METADATA]['brand_id'] = $brand_id;
    $opts[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    return Resource::findAll(new BrandSignupFieldsAdapter(), '', $opts);     
  }
}
?>