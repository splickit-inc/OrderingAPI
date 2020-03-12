<?php

class FoundryBrandCardTenderIdsAdapter extends MySQLAdapter
{

	function __construct($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Foundry_Brand_Card_Tender_Ids',
			'%([0-9]{4,10})%',
			'%d',
			array('id'),
			null,
			array('created','modified')
			);
		
		$this->allow_full_table_scan = true;
						
	}
	
	function &select($url, $options = NULL)
    {
		$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    	return parent::select($url,$options);
	}

	function getTenderIdFromBrandAndCardType($brand_id,$card_type)
    {
        if ($brand_id < 1) {
            myerror_log("NO BRAND ID SUBMITTED FOR FoundryBrandCardTenderIdsAdapter.getTenderIdFromBrandAndCardType");
            return false;
        }
        if ($record = $this->getRecord(['brand_id'=>$brand_id])) {
            if ($tender_id = $record[$card_type]) {
                return $tender_id;
            } else {
                myerror_log("NO VALID TENDER_ID retrieved FoundryBrandCardTenderIdsAdapter.getTenderIdFromBrandAndCardType");
            }
        } else {
            myerror_log("NO VALID RECORD FOR BRAND_ID: $brand_id, in FoundryBrandCardTenderIdsAdapter.getTenderIdFromBrandAndCardType");
        }
        return false;
    }

}
?>