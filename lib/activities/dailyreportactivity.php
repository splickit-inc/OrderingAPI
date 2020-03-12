<?php

class DailyReportActivity extends SplickitActivity
{
	protected $format_array = array ('E'=>'/utility_templates/email_cob.html');

	function DailyReportActivity($activity_history_resource)
	{
		$this->data['days_back'] = '0';
		parent::SplickitActivity($activity_history_resource);
		$tz = date_default_timezone_get();
		date_default_timezone_set('America/Los_Angeles');
		$this->the_day_westcoast = date('w') + 1;
		$this->the_date_westcoast = date('Y-m-d');
		date_default_timezone_set($tz);
	} 
	
	function createAndStageReport($date_string,$merchant_id)
	{
		$hour_adapter = new HourAdapter($mimetypes);
		$local_open_close_data = $hour_adapter->getLocalOpenAndCloseDtTmForDate($merchant_id, $date_string, 'R');
		if (isProd())
			$test = "false";
		else
			$test = "true";
		$local_open_close_data['test'] = $test;
		$this->setData($local_open_close_data);
		return $this->doit();
	}

	function doit()
	{
		// first get merchant_id
		$merch_id = $this->data['merchant_id'];
		$merchant_ids = array();
		
		$sql_adapter = new MySQLAdapter($this->mimetypes);
		if ($merch_id == 0)
		{
			$sql = "SELECT merchant_id FROM Merchant WHERE active = 'Y' AND logical_delete = 'N'";
			$options[TONIC_FIND_BY_SQL] = $sql;
			$merchants = $sql_adapter->select('',$options);
			foreach ($merchants as $merchant)
				$merchant_ids[] = $merchant['merchant_id'];
		} else {
			$merchant_ids = explode(',',$merch_id);
		}
		
		foreach ($merchant_ids as $merchant_id)
		{	
			// determine if this merchant has daily reports turned on
			
			$sql = "SELECT * FROM adm_merchant_email a WHERE daily = 'Y' AND merchant_id = $merchant_id";
			$options[TONIC_FIND_BY_SQL] = $sql;
			if ($results = $sql_adapter->select('',$options))
			{
				// get email string
				$email_string = '';
				foreach ($results as $merchant_email_record)
				{
					$email_string .= $merchant_email_record['email'].';';
				}
				$email_string = substr($email_string, 0,-1);
			}
			else 
			{
				myerror_log("ERROR! merchant has not set up report emails in adm_merchant_email.  merchant_id: ".$merchant_id);
				//MailIt::sendErrorEmailSupport("Failure on Daily Report Send", "Merchant ID: $merchant_id  has NOT set up report emails in adm_merchant_email.  Daily report could NOT be sent");
				if (sizeof($merchant_ids) == 1)
				{
					$this->activity_history_resource->activity_text = " Merchant has not set up report emails in adm_merchant_email.  merchant_id: ".$merchant_id;
					$this->error_text = " Merchant has not set up report emails in adm_merchant_email.  merchant_id: ".$merchant_id;
					return false;
				}
				else
					continue;
			}

			myerror_log('the email string in daily reports is: '.$email_string);
	
			if ($days_back = $this->data['days_back'])
				;// all is good
			else
				$days_back = 0;
			//$sql = "call SMAWSP_CREATE_DAILY_REPORT_FILE($merchant_id,$days_back)";		
			//$options[TONIC_FIND_BY_SQL] = $cob_sql;
			myerror_log("days back in daily reports is: $days_back");
//CHANGE THIS  - add functionality for days back
			if ($days_back > 0)
			{
				myerror_log("ERROR!  daily reports not set up for days back yet.  BUILD IT!");
				mail('adam@dummy.com', "ERROR! daily reports not set up for days back yet", "Daily reports not set up for days back yet. build it");
				die("ERROR!  daily reports not set up for days back yet.  BUILD IT!");
			}
			
			myerror_log("the date west coast: ".$this->the_date_westcoast);
			if ($days_back < '1')
			{
				if (isset($this->data['local_open_date']))
					$for_date = $this->data['local_open_date'];
				else
					$for_date = $this->the_date_westcoast;
				
				myerror_log("we have set the for_date and it is: ".$for_date);
			}
			else
			{
				//get time stamp for days back
				$adjustment_for_west_coast = (60*60*8);
				$days_back_in_seconds = $days_back * (60 * 60 * 24);
				$new_day = time() - $adjustment_for_west_coast - $days_back_in_seconds;
				$for_date = date('Y-m-d',$new_day);	
				myerror_log("the for date is: ".$for_date);
			}
			
			// get number of orders
			//$sql = "SELECT count(*) as cnt FROM `Orders` WHERE merchant_id = ".$merch_id." AND date(pickup_dt_tm) = '".$for_date."' and status = 'E'";
			$sql = "SELECT count(*) as cnt FROM `Orders` WHERE merchant_id = ".$merchant_id." AND pickup_dt_tm > '".$this->data['local_open_dt_tm']."' AND pickup_dt_tm < '".$this->data['local_close_dt_tm']."' and status = 'E' and cash = 'N' ";
			myerror_logging(1, $sql);
			$options[TONIC_FIND_BY_SQL] = $sql;
			if ($results = $sql_adapter->select('',$options))
			{
				$row = array_pop($results);
				$count = $row['cnt'];
				if ($count > 0)
					$count_param = ";count=$count";
				else
					$count_param = ";count=0";
			}
			$test_param = ";test=true";
			$test = "true";
			
			myerror_log("******* data *******");
			foreach ($this->data as $name=>$value)
			{
				myerror_log("$name = $value");
			}
			myerror_log("******* data *******");
			
			if (isset($this->data['test']))
			{
				$debug = $this->data['test'];
				myerror_log("test is set and its value is: ".$debug);
			}	else {
				myerror_log("test is NOT set so we are defaulting to true");
			}
			if ($test = $this->data['test'])
			{
				if ($test == "false")
				{
					$test_param = ";test=false";
				}
			} else {
				$test = "true";
			}
			
			myerror_log("******** the value of test is: $test ***********");
			//$attachment_string = '';
			$file_names = '';
			if ($count != 0)
			{
				for ($i = 1;$i < 4;$i++)
				{
					if ($i != 2)
						continue;
				
					$file_name = $for_date."_".$merchant_id."_dailyreport".$i.".csv";

					// create the daily report data.
					// first get SQL from the db
					$usa = new UtilitySQLAdapter($this->mimetypes);
					$usa_options[TONIC_FIND_BY_METADATA]['sql_name'] = 'daily_report_part'.$i;
					$usa_resource = Resource::findExact($usa,null,$usa_options);
					$sql = $usa_resource->sql_text;
					//$sql = str_replace('YYYYYYYY', $file_name_w_path, $sql);
					$sql = str_replace('ZZZZ', $merchant_id, $sql);
					$sql = str_replace('XXXX', $days_back, $sql);
					$sql = str_replace('OOOO', $this->data['local_open_dt_tm'], $sql);
					$sql = str_replace('CCCC', $this->data['local_close_dt_tm'], $sql);
					
					//AND pickup_dt_tm > '".$this->data['local_open_dt_tm']."' AND pickup_dt_tm < '".$this->data['local_close_dt_tm']."' ".
					//AND pickup_dt_tm > 'OOOO' AND pickup_dt_tm < 'CCCC' 

					myerror_log("Daily Report sql: ".$sql);
					$options[TONIC_FIND_BY_SQL] = $sql;
					$whole_file = '';
					if ($results2 = $sql_adapter->select('',$options))
					{
						// all is good
						//$fp = fopen($file_name_w_path, 'w');
		
						foreach ($results2 as $row) 
						{
							$line = "";
		 					$comma = "";
		 					
		 					// ok weird.  because the firs row in the SQL is static strings, i have to uset the static strings as the field names.
		 					if ($i==2 && $row['merchant id'] == 9999999)
		 					{
		 						// this is the final row so get the totals
		 						$grand_total = $row['grand total'];
		 						$tax_total = $row['total tax'];
		 						$promo_total = $row['promo amt'];
		 						$tip_total = $row['tip'];
		 						
		 					}
		 					
							foreach ($row as $name=>$value)
							{
								$line .= $comma . '"' . str_replace('"', '""', $value) . '"';
								$comma = ",";
						    }
						    $line .= "\n";
						    $whole_file .= $line;
						   // fputs($fp, $line);						
						}
		
						//fclose($fp);
					}
					else
					{
						error_log("THERE WAS an error in DailyReportActivity: ".$sql_adapter->getLastErrorText());
						continue;
					}
					
					// now save the file to the db
					$document_adapter = new DocumentAdapter($mimetypes);
					$da_data['file_type'] = 'SpreadSheet';
					$da_data['process_type'] = 'Daily_Report';
					$da_data['file_name'] = $file_name;
					$da_data['file_size'] = strlen($whole_file);
					$da_data['file_content'] = $whole_file;
					$da_data['file_extension'] = 'csv';
					$da_data['stamp'] = getRawStamp();
					$da_data['created'] = time();
					$da_resource = Resource::factory($document_adapter,$da_data);
					if ($da_resource->save())
					{
						myerror_log("we have saved the daily report file to the db");
						$document_id = $da_resource->id;
						$file_names .= $file_name."&";
						$document_ids .= $document_id."&";
					}
					else
					{
						myerror_log("ERROR! there was an error saving the daily report file to the db: ".mysql());
						MailIt::sendErrorEmail("Error saving daily report file to the db", "ERROR! there was an error saving the daily report file to the db: ".mysql());
					}
										
					//$attachment_string .= $file_name_w_path."&";
				}		
				//$attachment_string = substr($attachment_string,0,-1);
				$file_names = substr($file_names,0,-1);
				$document_ids = substr($document_ids,0,-1);
				
			}	
	
			//$email = $merchant_resource->shop_email;
			if ($test == "false")
			{
				$email = $email_string;
				$subject = "Daily Reports $merchant_id";
			}
			else 
			{
				if (isTest())
					$email = getProperty('test_addr_email');
				else 
					$email = 'tarek@dummy.com;adam@dummy.com';
				$subject = "***TEST*** Daily Reports $merchant_id";
			}

			$mmh_adapter = new MerchantMessageHistoryAdapter($this->mimetypes);
			$message_history_data['merchant_id'] = $merchant_id;		
			$message_history_data['message_format'] = 'ED';
			$message_history_data['message_delivery_addr'] = $email;
			$message_history_data['next_message_dt_tm'] = time();
			if ($count == 0)
				$message_history_data['info'] = "subject=$subject;for_date=$for_date;from=support@dummy.com;count=0";
			else
				$message_history_data['info'] = "subject=$subject;for_date=$for_date;from=support@dummy.com;count=$count;grand_total=$grand_total;tax_total=$tax_total;promo_total=$promo_total;tip_total=$tip_total;file_names=$file_names;process=Daily_Report;document_ids=$document_ids";
			$message_resource = Resource::factory($mmh_adapter,$message_history_data);
			$message_resource->save();
			set_time_limit(30);
		}
		
		return true;
		
	}
	
	function getSQL($num)
	{
		return true;
		
	}
	
	function markActivityExecuted()
	{
		$ah_id = $this->activity_history_resource->activity_id;
		$activity_adapter = new ActivityHistoryAdapter($mimetypes); 
		
		if ($activity_adapter->delete(''.$ah_id))
			return true;
		else
			myerror_log("error!  couldn't delete successful daily report activity from Activity_History.  activity_id: ".$this->activity_history_resource->activity_id);
//		MailIt::sendErrorEmail('ERROR! could not mark activity complete', 'activity_id: '.$this->activity_history_resource->activity_id);
	}
	
	function markActivityFailed()
	{
		return $this->markActivityFailedWithoutEmail();
	}

}

?>