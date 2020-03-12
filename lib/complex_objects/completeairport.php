<?php
class CompleteAirport extends Resource
{
	var $airport_areas;
	var $airport_merchants;
	var $id;
	var $name;
	var $code;
	var $lat;
	var $lng;
	var $display_name;
	var $airports_adapter;
	var $airport_areas_adapter;
	var $airport_areas_merchants_map_adapter;
	
	function __construct($id) {
		$this->airports_adapter = new AirportsAdapter($mimetypes);
		$this->airport_areas_adapter = new AirportAreasAdapter($mimetypes);
		$this->airport_areas_merchants_map_adapter = new AirportAreasMerchantsMapAdapter($mimetypes);
		$this->loadBaseAirportData($id);
	}
	
	/**
	 * 
	 * @desc will return a complete airport object given an airport id
	 * @param int $id
	 * @return CompleteAirport
	 */
	static function getCompleteAirport($id) {
		$complete_airport = new CompleteAirport($id);
		
		// now get areas
		$complete_airport->airport_areas = $complete_airport->getAirportAreas();
		
		// now get merchants
		$complete_airport->airport_merchants = $complete_airport->getAllAirportMerchants();
		
		return $complete_airport;
	}
	
	function cleanObjectForResponse()
	{
		unset($this->airports_adapter);
		unset($this->airport_areas_adapter);
		unset($this->airport_areas_merchants_map_adapter);
	}
	
	private function loadBaseAirportData($id)
	{
		$airport = $this->airports_adapter->getRecord(array('id'=>$id));
		foreach ($airport as $name=>$value)
			$this->$name = $value;
	}
	
	/**
	 * 
	 * @desc returns an array of the airport areas associated with this airport
	 * @return array
	 */
	function getAirportAreas()
	{
		return $this->airport_areas_adapter->getAirportAreas($this->id);
	}
	
	/**
	 * 
	 * @desc takes an airport area id and returns an array of the merchants associated with this airport area
	 * @param int $airport_area_id
	 * @return array
	 */
	function getAirportAreaMerchants($airport_area_id)
	{
		return $this->airport_areas_merchants_map_adapter->getAirportAreaMerchants($airport_area_id);
	}
	
	function getAllAirportMerchants()
	{
		return $this->airport_areas_merchants_map_adapter->getAllAirportMerchants($this->id);
	}

}