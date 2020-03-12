<?php

class TaxAdapter extends MySQLAdapter
{

	function TaxAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'Tax',
			'%([0-9]{1,15})%',
			'%d',
			array('tax_id'),
			null,
			array('created','modified')
			);
	}
	
    static function createTaxRecord($merchant_id,$local,$tax_rate,$tax_group)
    {
		$tax_adapter = new TaxAdapter($mimetypes);
		$tax_data['merchant_id'] = $merchant_id;
		$tax_data['tax_group'] = $tax_group;
		$tax_data['locale'] = $local;
		$tax_data['locale_description'] = 'Default';
		$tax_data['rate'] = $tax_rate;
		$tax_resource = Resource::factory($tax_adapter,$tax_data);
		if ($tax_resource->save())
			return $tax_resource;
		else
			return returnErrorResource("couldn't create dummy tax records. ".$tax_adapter->getLastErrorText());
    }
	
    function &select($url, $options = NULL)
    {
    	$options[TONIC_FIND_BY_METADATA]['logical_delete'] = 'N';
    	return parent::select($url,$options);
    }
    
    function getTotalTaxRates($merchant_id)
	{
		$options[TONIC_FIND_BY_METADATA]['merchant_id'] = $merchant_id;		
		$taxs = $this->select('',$options);
		$tax_rates = array();
		foreach ($taxs as $tax)
			$tax_rates[$tax['tax_group']] = $tax_rates[$tax['tax_group']] + $tax['rate'];
		foreach ($tax_rates AS $group=>&$tax_rate)
			$tax_rate = $tax_rate/100;
		return $tax_rates;
	}

	static function staticGetTotalTax($merchant_id)
	{
		$tax_adapter = new TaxAdapter($m);
		return $tax_adapter->getTotalTax($merchant_id);
	}

	function getTotalTax($merchant_id)
	{
		$options[TONIC_FIND_BY_METADATA]['merchant_id'] = $merchant_id;		
		$options[TONIC_FIND_BY_METADATA]['tax_group'] = 1;		
		$taxs = $this->select('',$options);
		$total_tax = 0.00;
		foreach ($taxs as &$tax)
		{
			$total_tax = $total_tax + $tax['rate'];
		}
		$total_tax = $total_tax/100;
		return $total_tax;
	}
	
	/**
	 * 
	 * @desc will return the total group 1 tax as a percentage (10 vs .1)
	 * @param int $merchant_id
	 */
	function getTotalBaseTaxRate($merchant_id)
	{
		return $this->getTotalTax($merchant_id) * 100;
	}

	/**
	 * 
	 * @desc will return the total group 1 tax as a percentage (10 vs .1)
	 * @param int $merchant_id
	 */
	static function staticGetTotalBaseTaxRate($merchant_id)
	{
		$tax_adapter = new TaxAdapter($mimetypes);
		return $tax_adapter->getTotalBaseTaxRate($merchant_id);	
	}
}
?>