<?php

class MerchantVivonetInfoMapsAdapter extends MySQLAdapter
{
    private $record;

	function MerchantVivonetInfoMapsAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Merchant_Vivonet_Info_Maps',
			'%([0-9]{4,10})%',
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

	function getMerchantVivonetInfo($merchant_id)
	{
		if ($this->record) {
			return $this->record;
		} else if ($record = $this->getRecord(array("merchant_id"=>$merchant_id))) {
			$this->record = $record;
			return $record;
		}
	}

	function getInfoRecord()
    {
        return $this->record;
    }

    function getAlternateUrl($merchant_id)
    {
        if ($record = $this->getMerchantVivonetInfo($merchant_id)) {
            return $record['alternate_url'];
        }
    }

    function getMerchantKey($merchant_id)
    {
        if ($record = $this->getMerchantVivonetInfo($merchant_id)) {
            return $record['merchant_key'];
        }
    }

	function getStoreId($merchant_id)
    {
        if ($record = $this->getMerchantVivonetInfo($merchant_id)) {
            return $record['store_id'];
        }
    }

	function getTenderIdForMerchant($merchant_id)
	{
		if ($record = $this->getMerchantVivonetInfo($merchant_id)) {
			return $record['tender_id'];
		}
	}

	function getServiceTipIdForMerchant($merchant_id)
	{
		if ($record = $this->getMerchantVivonetInfo($merchant_id)) {
			return $record['service_tip_id'];
		}
	}

	function getPromoChargeIdForMerchant($merchant_id)
    {
        if ($record = $this->getMerchantVivonetInfo($merchant_id)) {
            return intval($record['promo_charge_id']);
        }
    }

    static function isMechantAVivonetMerchant($merchant_id)
    {
        $mvima = new MerchantVivonetInfoMapsAdapter(getM());
        if ($record = $mvima->getRecord(array("merchant_id"=>$merchant_id))) {
            return true;
        } else {
            return false;
        }
    }

}
?>