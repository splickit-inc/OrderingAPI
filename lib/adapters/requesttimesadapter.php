<?php

class RequestTimesAdapter extends MySQLAdapter
{

	function RequestTimesAdapter($mimetypes)
	{
		parent::MysqlAdapter(
			$mimetypes,
			'request_times',
			'%([0-9]{4,10})%',
			'%d',
			array('id'),
			null,
			array('created')
			);
        $this->log_level = 0;
	}

	static function createTimesRecord($stamp,$url,$method,$request_time,$db_time,$db_connection_time,$request_body = null,$response_payload = null)
	{
        $dftz = $_SERVER['GLOBAL_PROPERTIES']['default_server_timezone'];
        $tz = date_default_timezone_get();
        $reset_time_zone = false;
        if ($dftz != $tz) {
            $reset_time_zone = true;
            date_default_timezone_set($dftz);
        }
        $rta = new RequestTimesAdapter($mimetypes);
		$data['stamp'] = $stamp;
		$data['request_url'] = $url;
		$data['method'] = $method;
		$data['total_request_time'] = $request_time;
		$data['total_query_times'] = $db_time;
		$data['total_db_connection_time'] = $db_connection_time;
        $data['request_body'] = $request_body;
        if (strpos($request_body,'password') > 0) {
            $request_body_array = json_decode($request_body,true);
            $request_body_array['password'] = 'XXXXXXXX';
            $data['request_body'] = json_encode($request_body_array);
        }
        $data['response_payload'] = $response_payload;
		$resource = Resource::factory($rta,$data);
		$resource->save();
        if ($reset_time_zone) {
            date_default_timezone_set($tz);
        }
		return $rta->_insertId();
	}

    function auditTrail($sql)
    {
        return true;
    }

}
?>