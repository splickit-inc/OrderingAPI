<?php
class AirportController extends SplickitController
{
	
	function AirportController($mimetypes,$user,$request,$log_level = 0)
	{
		parent::SplickitController($mimetypes, $user, $request, $log_level);
		$this->adapter = new AirportsAdapter($mimetypes);
	}
	
	function processRequest()
	{
		if ($this->request == null) { 
			throw new NullRequestException(); 
		}
		
		if (preg_match('%/airports/([0-9]{4,10})%', $this->request->url, $matches)) {
			$resource = $this->getCompleteAirportResource($matches[1]);
		} else {
			$airports = $this->getAllAirportsWithUserVerification();
			$resource = $this->setListAsDataInDispatchReturnFormat($airports);
		}
		return $resource;
	}
	
	function getAllAirportsWithUserVerification()
	{	
		if (shouldSystemProcessAsRegularUser($this->user['user_id'])) {
			$data['active'] = 'Y';
		}
		$airports = $this->getAllAirports($url, $data);
		return $airports;
	}
		
	function getAllAirports($url,$data)
	{
		return AirportsAdapter::getAllAirports($url, $data);		
	}
	
	function getCompleteAirportResource($airport_id)
	{
		$resource = CompleteAirport::getCompleteAirport($airport_id);
		$resource->cleanObjectForResponse();
		return $resource;
	}
}

