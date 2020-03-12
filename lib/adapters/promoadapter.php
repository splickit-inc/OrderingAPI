<?php

class PromoAdapter extends MySQLAdapter
{

	function PromoAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Promo', 
			'%([0-9]{1,15})%',
			'%d',
			array('promo_id'),
			null,
			array('created','modified')
			);
	}
	
	function &select($url, $options = NULL)
    {
    	$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    	return parent::select($url,$options);
    }

    function update(&$resource)
    {
        $messages = isset($resource->promo_messages) ? $resource->promo_messages : false;
        if (parent::update($resource)) {
            // now check to see if ther are messages
            if ($messages) {
                if ($promo_messages_resource = Resource::find(new PromoMessageMapAdapter(getM()),$messages['map_id'])) {
                    unset($messages['map_id']);
                    unset($messages['promo_id']);
                    $promo_messages_resource->saveResourceFromData($messages);
                }
            }
            return true;
        } else {
            return false;
        }
    }
	
}
?>