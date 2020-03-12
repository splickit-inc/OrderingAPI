<?php
/*
Tonic: A simple RESTful Web publishing and development system
Copyright (C) 2007 Paul James <paul@peej.co.uk>

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

// $Id: mysqladapter.php 34 2007-12-02 15:14:24Z peejeh $

require_once 'sqladapter.php';

/**
 * MySQL persistance adapter.
 * @package Tonic/Adapters
 * @version $Revision: 34 $
 * @author Paul James
 */
class MySQLAdapter extends SQLAdapter
{
	const DUPLICATE_ENTRY = 1062;
	private $force_write_db = false;

	
	/**
	 * @param str hostname
	 * @param str username
	 * @param str password
	 * @param str database
	 * @param bool persistent
	 * @return bool
	 */
	function connect($hostname, $username, $password, $database, $persistent = FALSE)
	{
		$db = DataBase::getInstance();
		$handle = $db->getConnection();

		if ($handle) {
			$this->_handle =& $handle;
			$this->_setTableSchemaData();
			return TRUE;
		} else {
            myerror_log("COULD NOT CONNECT TO DB!!!!!  ".mysqli_error()." ".mysqli_errno()." $hostname - $username - password - $database");
			die;
        }
		return FALSE;
	}
	
	/**
	 * Get the primary keys from the database table
	 */
	function _setTableSchemaData()
	{
		if (!$this->fields) {
			//if ($result = $this->_query('DESCRIBE '.$this->getTable(), $this->_handle)) {
			if ($table_schema = $this->_getTableSchemaFromDbOrCache()) {
				if (!$this->primaryKeys) {
					$findPrimaryKeys = TRUE;
				} else {
					$findPrimaryKeys = FALSE;
				}
				if (!$this->datetimeFields) {
					$findDatetimeFields = TRUE;
				} else {
					$findDatetimeFields = FALSE;
				}
				// while ($row = $this->_fetchRow($result)) {
				foreach ($table_schema as $row) {
					if ($findPrimaryKeys && $row['Key'] == 'PRI') {
						$this->primaryKeys[] = $row['Field'];
					}
					$this->fields[] = $row['Field'];
					$this->field_types[$row['Field']] = $row['Type'];
					$this->join_fields[] = $this->getTable().'.`'.$row['Field'].'`';
					$this->field_names[$row['Field']]=1;
					if ($findDatetimeFields && ($row['Type'] == 'datetime' || $row['Type'] == 'date')) {
						$this->datetimeFields[] = $row['Field'];
					}
				}
			}
		}
	}

	function _getTableSchemaFromDbOrCache()
	{
        $descibe_table_caching_string = $this->getTable()."_DESCRIBE";
        $splickit_cache = new SplickitCache();
        if ($result = $splickit_cache->getCache($descibe_table_caching_string)) {
        	if ($_SERVER['DO_NOT_CHECK_CACHE'] || getProperty('DO_NOT_CHECK_CACHE') == 'true') {
        		; // skip
			} else {
                return $result;
			}

        }

        	$result = $this->_query('DESCRIBE '.$this->getTable(), $this->_handle);
            while ($row = $this->_fetchRow($result)) {
            	$table_data[] = $row;
			}
            $expires_in_seconds = 36000; // 10 hours expiration
			$splickit_cache->setCache("$descibe_table_caching_string",$table_data,$expires_in_seconds);
            return $table_data;

	}
	
	/**
	 * Convert a database datetime string into a unix timestamp
	 * @param str date
	 * @return int
	 */
	function _dateToTimestamp($date)
	{
		return mktime(
			substr($date, 11, 2),
			substr($date, 14, 2),
			substr($date, 17, 2),
			substr($date, 5, 2),
			substr($date, 8, 2),
			substr($date, 0, 4)
		);
	}
	
	/**
	 * Convert a unix timestamp into a database datetime string
	 * @param int timestamp
	 * @return str
	 */
	function _timestampToDate($timestamp)
	{
		return date('Y-m-d H:i:s', $timestamp);
	}
    
    /**
     * Return a textual description of the last DB error
     * @return str
     */
    function _error()
    {
        return $this->getLastErrorText();
    }

    function auditTrail($sql)
	{
        $start = strtolower(substr($sql,0,6));
        if ($start == 'insert' || $start == 'update' || $start == 'delete') {
            $audit_adapter = new AuditApiPortalPostTrailAdapter(getM());
            $data['request_url'] = $_SERVER['request_url'];
			$data['stamp'] = getRawStamp();
			$data['sql_statement'] = $sql;
			$resource = Resource::factory($audit_adapter,$data);
			$resource->save();
        }
	}

	function auditTrailForInternalFunction($function)
	{
		$sql = $this->last_run_sql;
        $start = strtolower(substr($sql,0,6));
        if ($start == 'insert' || $start == 'update' || $start == 'delete') {
            $audit_adapter = new AuditApiPortalPostTrailAdapter(getM());
            $data['request_url'] = $function;
            $data['stamp'] = getRawStamp();
            $data['sql_statement'] = $sql;
            $resource = Resource::factory($audit_adapter,$data);
            $resource->save();
        }
	}
	
	/**
     * Execute the given query
     * @param str sql The SQL to execute
     * @return resultset
     */
    function _query($sql)
    {
// CHANGE THIS  probably should short circuit it farther up in the process.
    	if ($sql == 'DESCRIBE resource') {
            return false;
		}
		myerror_log("THE REQUEST METHOD IS: ".$_SERVER['REQUEST_METHOD'],6);
        if ($_SERVER['PORTAL_REQUEST']) {
            $this->auditTrail($sql);
        }
        if ($this->useWriteDb() || useForcedGlobalWriteDb()) {
    		$sql = $sql."; -- maxscale route to master";
		}
    	$time1 = microtime(true);
		$result = mysqli_query($this->_handle,$sql);
		if ($result == false) {
			$error_text =  mysqli_error($this->_handle);
			$error_number = mysqli_errno($this->_handle);
			myerror_log("The error stuff is: $error_text    $error_number");
			usleep(1);

            if ($this->isErrorADeadLock($error_text)) {
                myerror_log("DEADLOCK: lets wait a quarter second and try again");
                usleep(250000);
                $result = mysqli_query($this->_handle,$sql);
                if (!$result) {
                    myerror_log("DEADLOCK BAD RESULT: there was an error running the query a second time: ".mysqli_error($this->_handle));
                    $error_text =  mysqli_error($this->_handle);
                    $error_number = mysqli_errno($this->_handle);
                } else {
                    myerror_log("DEADLOCK good result: query was successful");
                    $error_text = null;
                    $error_number = 0;
                }
            } else {
                myerror_log("DEADLOCK NO!!!!! error was not a deadlock so skip retry");
            }

        }

		$this->rows_updated = mysqli_affected_rows($this->_handle);
		$this->last_run_sql = $sql;
		if (mysqli_insert_id($this->_handle) > 0) {
			$this->insert_id = mysqli_insert_id($this->_handle);
		} 
		if ($error_number > 0) {
			$this->error_no = $error_number;
			$this->error_text = $error_text;
			myerror_log("ERROR RUNNING QUERY: ".$this->getLastErrorText()."       sql: ".$sql);
			$body = 'SQL ERROR: '.$this->getLastErrorText();
			if ($this->table == 'Errors') {
				myerror_log("we had an error putting a row into the ERRORS table. So we skip");
				return true;
			} else {
				recordError($body,$sql);
				return false;
			}
		} else {
			unset($this->error_no);
			unset($this->error_text);
		}
		$time2 = microtime(true);
		$time_of_query = $time2 - $time1;
		if (substr_count($sql,'SELECT') > 0 ) {
			myerror_logging(2,"time:".$time_of_query."   ".$sql);
		} else {
			myerror_logging(2,"time of query: ".$time_of_query);
		}

		$_SERVER['TOTAL_DB_TIME'] = $_SERVER['TOTAL_DB_TIME'] + $time_of_query;
		$long_query_threshold = (int) getProperty("long_query_threshold");
        if ($long_query_threshold > 1 && $time_of_query > $long_query_threshold) {
            $body = "LONG QUERY ERROR";
            $m4 = "time: $time_of_query ---  $sql";
            if (isProd()) {
                myerror_log("CONNECTION: $body $m4.  the long query threshold is: $long_query_threshold");
                if ($this->table != 'Errors' && $this->table != 'Property') {
                    if (!$this->read_only) {
                        recordError($body, $m4);
                    }
                }
            } else {
                myerror_log("CONNECTION: $body $m4");
            }
        }
		return $result;
    }
    
    /**
     * Fetch a row from a result set
     * @param resultset result A MySQL result set
     * @return str[]
     */
    function _fetchRow(&$result)
    {
        return mysqli_fetch_assoc($result);
    }
    
    /**
     * The number of rows that were changed by the most recent SQL statement
     * @return int
     */
    function _affectedRows()
    { 
        return $this->rows_updated;
    }
	
	/**
     * The value of any auto-increment values from the last insert statement
     * @return int
     */
    function _insertId()
    {
    	if (isset($this->insert_id)) {
    		return $this->insert_id;
    	} else {
        	return mysqli_insert_id($this->_handle);
    	}
    }
	
	/**
     * Return the base SQL SELECT statement for this adapter
	 * @param str[] options An array of options
     * @return str
     */
	function _getBaseSelectStatement(&$options)
	{
		if (isset($options[TONIC_CALC_FOUND_RESOURCES]) && $options[TONIC_CALC_FOUND_RESOURCES]) {
			if ($this->fields) {
				return sprintf(
					'SELECT SQL_CALC_FOUND_ROWS '.join($this->fields, ', ').' FROM %s',
					$this->getTable()
				);
			} else {
				return sprintf(
					'SELECT SQL_CALC_FOUND_ROWS * FROM %s',
					$this->getTable()
				);
			}
		} else {
			 return parent::_getBaseSelectStatement($options);
		}
	}
	
	/**
     * Return the number of found rows in the previous SQL SELECT statement
     * @return int
     */
	function _getFoundRows()
	{
		$result = $this->_query('SELECT FOUND_ROWS()');
		$row = mysqli_fetch_row($result);
		return $row[0];
	}
    
    /**
     * Escape a string for the database
     * @param str string The string to escape
     * @return str
     */
    function _escape($string)
    {
        return mysqli_real_escape_string($this->_handle,$string);
    }
    
    /**
     * Escape a field name for the database
     * @param str string The string to escape
     * @return str
     */
    function _escapeFieldName($string)
    {
        return '`'.$this->_escape($string).'`';
    }
    
    /**
     * Wrap a string in delimiters for this database
     * @param str string The string to wrap in delimiters
     * @return str
     */
    function _delimitString($string)
    {
		if (is_numeric($string)) {
			return $string;
		} else if ($string == "NULL") {
			return $string;
		} else {
			return '"'.$string.'"';
		}
    }
	
    /**
     * Wrap a string in delimiters for this database
     * @param str string The string to wrap in delimiters
     * @return str
     */
    function _delimitStringBetter($string,$field_name)
    {
    	$field_type = strtolower($this->field_types[$field_name]);
    	if (substr_count($field_type, 'char') > 0)
    		return '"'.$string.'"';
    	else if (is_numeric($string)) {
			return $string;
		} else if ($string == "NULL") {
			return $string;
		} else {
			return '"'.$string.'"';
		}
    }
    
    function getRecordFromPrimaryKey($id)
    {
    	$primary_key = $this->primaryKeys[0];
    	$data[$primary_key] = $id;
    	return $this->getRecord($data);
    }
    
    /**
     * @desc will return a single row from the database for passed in hash.  returns null on no rows returned.
     * @param Hash $data
     * @return single record as an array
     * @throws Exception if more than one row is returned.
     */
    
	function getRecord($data,$options = array())
	{
		if ($results = $this->getRecords($data,$options))
		{
			if (sizeof($results, $mode) == 1)
			{
				$result = array_pop($results);
				return $result;
			}
			else {
				myerror_log("MORE THAN ONE MATCHING ROW EXCEPTION: ".$this->last_run_sql);
				throw new MoreThanOneMatchingRecordException($this->last_run_sql, $code, $previous);
			}
		} else {
			return null;
		}
	}

	/**
	 * 
	 * @desc will remove created,modified,logical_delete from the data
	 * @param hash $data
	 */
	function getCleanRecord($data)
	{
		if ($result = $this->getRecord($data)) {
			return cleanData($result);
		}
		return null;
	}
	
	static function staticGetRecord($data,$class)
	{
		$adapter = new $class($mimetypes);
		return $adapter->getRecord($data);
	}
	
	static function staticGetRecordByPrimaryKey($id,$class)
	{
		if (substr($class, -7) != 'Adapter') {
			$class = $class.'Adapter';
		}
		$adapter = new $class($mimetypes);
		return $adapter->getRecordFromPrimaryKey($id);
	}

	static function staticGetRecords($data,$class)
	{
		$adapter = new $class($mimetypes);
		return $adapter->getRecords($data);
	}

	/**
	 * 
	 * @desc will return an array of records for a passed in hash. will return false if no rows are returned
	 * @param hash $data
	 * @return Array
	 */
    
    function getRecords($data,$options = array())
    {
    	if (get_class($this) == "MySQLAdapter")
    		return false; 
    	$options[TONIC_FIND_BY_METADATA] = $data;
    	return $this->select($url,$options);
    }
    
    function getCleanRecords($data)
    {
    	$result_array = $this->getRecords($data);
    	if (count($result_array) > 0) {
    		cleanData($result_array);
    	}
    	return $result_array;
    }

    /**
     * @desc will return a single resource from the database for the passed in hash
     * @param Hash $data
     * @return Resource
     * @throws Exception if more than one resource matches the submitted data.
     */
    function getExactResourceFromData($data)
    {
    	if ($resources = $this->getResourcesFromData($data))
    	{
    		if (count($resources) == 1) {
    			return $resources[0];
    		}
			myerror_log("MORE THAN ONE MATCHING ROW EXCEPTION: ".$this->last_run_sql);
			throw new MoreThanOneMatchingRecordException($this->last_run_sql, $code, $previous);
    	}
    }
    
    function getResourcesFromData($data)
    {
    	$options[TONIC_FIND_BY_METADATA] = $data;
    	return Resource::findAll($this,'', $options);
    }

    function setWriteDb()
	{
		$this->force_write_db = true;
	}

	function unsetWriteDb()
	{
        $this->force_write_db = false;
	}

	function useWriteDb()
	{
		return $this->force_write_db;
	}
}


class MoreThanOneMatchingRecordException extends Exception
{
    public function __construct($message,$code,$previous) {
        parent::__construct("Multiple rows returned when expecting only 1: $message",$code);
    }
}
?>
