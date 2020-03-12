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

// $Id: sqladapter.php 36 2008-01-15 22:39:21Z peejeh $

require_once 'adapter.php';

/**
 * SQL persistance adapter.
 * @package Tonic/Adapters
 * @version $Revision: 36 $
 * @author Paul James
 * @abstract
 */
class SQLAdapter extends Adapter
{
	
	/**
	 * The database connection handle
	 * @var resource
	 */
	var $_handle;
	
	/**
	 * The MySQL table to use
	 * @var str
	 */
	var $table = NULL;
	
	/**
	 * Regular expression to pull the primary keys values from the given URL
	 * @var str
	 */
	var $keyValues = NULL;
	
	/**
	 * How to turn primary keys back into a URL
	 * @var str
	 */
	var $template;
	
	/**
	 * Name of primary keys as found in URL
	 * @var str[]
	 */
	var $primaryKeys;
	
	/**
	 * Name of fields in the database table
	 * @var str[]
	 */
	var $fields;
	
	/**
	 * Names of fields in the database table so we can test for existance
	 * @var str[]
	 */
	var $field_names;
	
	/**
	 * Field types for database table so we can save as correct type when varchar
	 * @var str[]
	 */
	var $field_types;
	
	/**
	 * Name of fields which are datetime fields and which we want to automatically convert to and from unix timestamps
	 * @var str[]
	 */
	var $datetimeFields;
	
	/**
	 * should we log
	 */
	var $log_level = 1;
	
	/**
	 * to prevent full table scan
	 */
	var $allow_full_table_scan = false;
	
	/**
	 * to prevent updates from this connection
	 */
	var $read_only = false;
	
	/**
	 * 
	 * number of retry attepts when a dead lock is encountered on an update
	 */
	var $update_dead_lock_retry_attempts = 2;

	/**
	 * holds the last mysql_error text
	 */
	protected $error_text;

	/**
	 * holds the last mysql_error number
	 */
	protected $error_no;
	
	/**
	 * holds the last used SQL
	 */
	protected $last_run_sql;
	
	/**
	 * holds the number of rows updated by the last user SQL
	 */
	protected $rows_updated;
	
	/**
	 * holds the last insert id so it doesn't get overritten by an errors update. each should be stored on its own adapter.
	 */
	protected $insert_id;

    /**
     * ability to shut down cache by setting to false
	 * @var boolean
     */
    var $cache_enabled = true;


	/**
	 * @param str[] mimetypes The mimetypes to use for the adapter
	 * @param str table The name of the database table to use
	 * @param str keyValues Regular expression to extract primary keys from URL
	 * @param str template How to turn primary keys back into a URL
	 * @param str[] primaryKeys Name of primary keys as found in URL
	 * @param str[] fields The fields of the resource to insert into the database
	 * @param str[] datetimeFields Fields which are datetime fields and which we want to automatically convert to and from unix timestamps
	 */
    function SQLAdapter(&$mimetypes, $table = 'resource', $keyValues = '%^/([0-9]+)%', $template = '/%d', $primaryKeys = array(), $fields = array() ,$datetimeFields = array())
    {
    	if ($mimetypes == null)
    		$mimetypes = array('html' => 'text/html','xml' => 'text/xml');
		parent::adapter($mimetypes);
		
		$this->table = $table;
		$this->keyValues = $keyValues;
		$this->template = $template;
		
		$this->primaryKeys = $primaryKeys;
		$this->fields = $fields;
		if (sizeof($fields) > 0)
			foreach ($fields as $name=>$value)
				$this->field_names[$value] = 1;
		$this->datetimeFields = $datetimeFields;
		$this->connect();
		
		if (isset($_SERVER['log_level']))
			$this->log_level = $_SERVER['log_level'];
    }
	
	/**
	 * Get the database table name
	 * @return str
	 */
	function getTable()
	{
		return $this->table;
	}
	
	/**
	 * Get the database key values
	 * @param str url The URL that the table name is embedded in if we're extracting it with a regular expression
	 * @return str
	 */
	function getKeyValues($url = NULL)
	{
		if ($url == null)
			return NULL;
		preg_match_all($this->keyValues, $url, $matches);
		if (isset($matches[1])) {
			return $matches[1];
		}
		return NULL;
	}
	
	/**
	 * @abstract
	 * @param str hostname
	 * @param str username
	 * @param str password
	 * @param str database
	 * @param bool persistent
	 * @return bool
	 */
	function connect($hostname = 'localhost', $username = 'root', $password = '', $database = 'tonic', $persistent = FALSE)
	{
		$this->_setTableSchemaData();
		return FALSE;
	}
	
	/**
	 * Get the primary keys from the database table
	 * @return str
	 */
	function _setTableSchemaData()
	{
		if (!$this->fields) {
			$this->fields = array();
			$this->primaryKeys = array();
			$this->datetimeFields = array();
		}
	}
	
    /**
     * Return a textual description of the last DB error
	 * @abstract
     * @return str
     */
    function _error()
    {
        return NULL;
    }
	
	/**
	 * Get the database connection
	 * @return resource
	 */
	function &getConnection()
	{
		return $this->_handle;
	}
	
	/**
     * Execute the given query
	 * @abstract
     * @param str sql The SQL to execute
     * @return resultset
     */
    function _query($sql)
    {
		return NULL;
    }
    
    /**
     * Fetch a row from a result set
	 * @abstract
     * @param resultset result A MySQL result set
     * @return str[]
     */
    function _fetchRow(&$result)
    {
        return array();
    }
    
    /**
     * The number of rows that were changed by the most recent SQL statement
	 * @abstract
     * @return int
     */
    function _affectedRows()
    {
        return NULL;
    }
	
    /**
     * The value of any auto-increment values from the last insert statement
	 * @abstract
     * @return int
     */
    function _insertId()
    {
        return NULL;
    }
	
	/**
	 * Build an SQL SELECT statement
	 * @param str[] options An array of options
	 * @param str[] where
	 * @param str[] order
	 * @param int limit
	 * @param int offset
     * @return str
	 */
	function _buildSelectStatement(&$options, $where, $order, $limit, $offset)
	{
		$sql = $this->_getBaseSelectStatement($options);
		if (isset($options[TONIC_FIND_BY_STATIC_METADATA]))
			$where[] = $options[TONIC_FIND_BY_STATIC_METADATA];
		$sql .= $this->_getWhereClause($where);
//			$sql .= ' AND '.$options[TONIC_FIND_BY_STATIC_METADATA].' ';
		$sql .= $this->_getOrderClause($order);
		$sql .= $this->_getLimitClause($limit, $offset);
		return $sql;
	}
	
	/**
     * Return the base SQL SELECT statement for this adapter
	 * @param str[] options An array of options
     * @return str
     */
	function _getBaseSelectStatement(&$options)
	{
		$more_fields = '';
		$join_statement = '';
		$join = false;
		if (isset($options[TONIC_FIND_STATIC_FIELD]))
			$more_fields = ', '.$options[TONIC_FIND_STATIC_FIELD];
		
		if (isset($options[TONIC_JOIN_STATEMENT]))
		{
			$join_statement = $options[TONIC_JOIN_STATEMENT];
			$join = true;
			myerror_logging(3,"we've loaded the join");
			if (!$this->join_fields)
			{
				foreach ($this->fields as $field)
					$this->join_fields[] = $this->getTable().'.`'.$field.'`';
			}
		}
			
		if ($this->fields && $join) {
			return sprintf('SELECT '.join($this->join_fields, ', ').''.$more_fields.' FROM %s '.$join_statement, $this->getTable() );
		} else if ($this->fields) {
			return sprintf('SELECT '.join($this->fields, ', ').''.$more_fields.' FROM %s '.$join_statement, $this->getTable() );
		} else {
			return sprintf('SELECT * '.$more_fields.' FROM %s '.$join_statment,$this->getTable());
		}
		
	}
	
	function _getWhereClause($where)
	{
		if (count($where)) {
			if ( (!$this->allow_full_table_scan) && sizeof($where) == 1 && substr_count($where[0], 'logical_delete') > 0 && substr_count($where[0], ' AND ') < 1)
			{
				myerror_log("ERROR! complete table scan due to ONLY logical_delete WHERE CLAUSE.  SETTING 1 = 0 so query will not run");
				//MailIt::sendErrorEmail("ERROR!  ONLY LOGICAL DELETE CLAUSE IN SQL ADAPTER for table: ".$this->table, "ERROR! complete table scan due to ONLY logical_delete WHERE CLAUSE.  SETTING 1 = 0 so query will not run");
				recordError("ERROR!  ONLY LOGICAL DELETE CLAUSE IN SQL ADAPTER for table: ".$this->table, "ERROR! complete table scan due to ONLY logical_delete WHERE CLAUSE.  SETTING 1 = 0 so query will not run");
				return ' WHERE 1 = 0 ';
			}
			return ' WHERE ('.join(') AND (', $where).')';
		} else {
			if (!$this->allow_full_table_scan)
			{
				myerror_log("ERROR! complete table scan due to no WHERE CLAUSE.  SETTING 1 = 0 so query will not run");
				//MailIt::sendErrorEmail("ERROR!  NO WHERE CLAUSE IN SQL ADAPTER for table: ".$this->table, $this->table);
				recordError("ERROR!  NO WHERE CLAUSE IN SQL ADAPTER for table: ".$this->table, $this->table);
				return ' WHERE 1 = 0 ';
			}
		}
	}

	function _getOrderClause($order)
	{
		if (count($order)) {
			return ' ORDER BY '.join(', ', $order);
		}
	}
	
	function _getLimitClause($limit, $offset)
	{
		if ($limit) {
			$sql = ' LIMIT ';
			if ($offset) {
				$sql .= $offset.', ';
			}
			return $sql.$limit;
		} elseif ($offset) {
			return ' LIMIT '.$offset.', 99999999999999999';
		}
	}
	
	function _getGroupByClause($group_by)
	{
		if ($group_by) {
			return ' GROUP BY '.$group_by.' ';
		}
	}
	
	/**
     * Return the number of found rows in the previous SQL SELECT statement
	 * @abstract
     * @return int
     */
	function _getFoundRows()
	{
		return NULL;
	}
    
    /**
     * Escape a string for the database
	 * @abstract
     * @param str string The string to escape
     * @return str
     */
    function _escape($string)
    {
        return NULL;
    }
    
    /**
     * Escape a field name for the database
	 * @abstract
     * @param str string The string to escape
     * @return str
     */
    function _escapeFieldName($string)
    {
        return NULL;
    }
    
    /**
     * Wrap a string in delimiters for this database
	 * @abstract
     * @param str string The string to wrap in delimiters
     * @return str
     */
    function _delimitString($string)
    {
        return NULL;
    }
	
	/**
	 * Turn the URL into the primary keys using the URL regex
	 * @param str url
	 * @return str[]
	 */
	function _makeKeys($url)
	{
		$keyValues = $this->getKeyValues($url);
		$keys = array();
		if ($this->primaryKeys == NULL)
			return $keys;
		foreach ($this->primaryKeys as $id => $key) {
			if (isset($keyValues[$id])) {
				$keys[$key] = $keyValues[$id];
			}
		}
		return $keys;
	}
	
	/**
	 * Turn the primary keys into a URL using the URL template
	 * @param str[] data
	 * @return str
	 */
	function _makeUrl($data)
	{
		$idValues = array();
		foreach ($this->primaryKeys as $id) {
			$idValues[] = $data[$id];
		}
		return vsprintf($this->template, $idValues);
	}
	
	/**
	 * Convert a database datetime string into a unix timestamp
	 * @param str date
	 * @return int
	 */
	function _dateToTimestamp($date)
	{
		return $date;
	}
	
	/**
	 * Convert a unix timestamp into a database datetime string
	 * @param int timestamp
	 * @return str
	 */
	function _timestampToDate($timestamp)
	{
		return $timestamp;
	}
	
	/**
     * Select data from the data source
     * @param str url The URL of the resource to select
     * @param str[] options An array of options
     * @return str[]
     */
    function &select($url, $options = NULL)
    {
        $data = array();
		if (isset($options[TONIC_FIND_BY_SQL])) { // we've been given SQL, just go with it
			if (is_array($options[TONIC_FIND_BY_SQL])) {
				$sql = vsprintf(array_shift($options[TONIC_FIND_BY_SQL]), $options[TONIC_FIND_BY_SQL]);
			} else {
				$sql = $options[TONIC_FIND_BY_SQL];
			}	
		} else { // process options and build SQL
			$where = array();
			$order = array();
			$limit = NULL;
			$offset = NULL;
			
			$keys = $this->_makeKeys($url);
			// find one
			if ($keys) {
				foreach ($keys as $id => $key) {
					$where[] = sprintf('%s.%s = %s', $this->getTable(), $this->_escapeFieldName($id), $this->_delimitString($this->_escape($key)));
				}
			}
			$or = false;
			// find by metadata
			if (isset($options[TONIC_FIND_BY_METADATA]) && is_array($options[TONIC_FIND_BY_METADATA])) {
				if (is_array($options[TONIC_FIND_BY_METADATA])) {
					foreach ($options[TONIC_FIND_BY_METADATA] as $field => $value) {
						
						if ($field == 'OR') {
							$or = true;							
							$or_string = '';
							foreach ($value as $field_name => $or_value)
							{
								if (gettype($or_value) == "string")
									$or_string .= sprintf("%s.%s = '%s' OR ", $this->getTable(), $this->_escapeFieldName($field_name), $this->_delimitString($this->_escape($or_value)));
								else
									$or_string .= sprintf('%s.%s = %s OR ', $this->getTable(), $this->_escapeFieldName($field_name), $this->_delimitString($this->_escape($or_value)));
							}
							$or_string = substr($or_string, 0,-3);
							$where[] = '('.$or_string.')';
						} else if (is_array($value)) {
							foreach ($value as $operator => $v) {
								if (is_scalar($v)) {
									$where[] = sprintf('%s.%s %s %s', $this->getTable(), $this->_escapeFieldName($field), $operator, $this->_delimitString($this->_escape($v)));
								} else if (is_array($v)) {
									$list = "";
									foreach ($v as $index=>$value)
									{
										$escvalue = $this->_escape($value); 
										if (is_numeric($escvalue))
											$list .= $escvalue.",";
										else
											$list .= "'".$escvalue."',";
									}
									$list = substr($list,0,-1);
									$where[] = sprintf('%s.%s %s (%s)', $this->getTable(), $this->_escapeFieldName($field), $operator, $list);
								} else {
									// guess we cant get here anymore
									myerror_logging(1,'array type for meta data on sql query so we will ignore.');
								}
							}
						} elseif (is_scalar($value)) {
							//shouldn't i do a test here for valid values and ignore the ones that aren't applicable?
							if ($this->field_names[$field]) {
								//$where[] = sprintf('%s.%s = %s', $this->getTable(), $this->_escapeFieldName($field), $this->_delimitString($this->_escape($value)));
								$where[] = sprintf('%s.%s = %s', $this->getTable(), $this->_escapeFieldName($field), $this->_delimitStringBetter($this->_escape($value),$field));
							} else {
								myerror_logging(3,"this field name,".$field." is not in the table so we will skip");
							}
						} else {
							myerror_logging(1,'meta data error on sql query so we will ignore. '.$field.'='.strval($value).' as TONIC_FIND_BY_METADATA option');
							//trigger_error('Can not use value "'.strval($value).'" as TONIC_FIND_BY_METADATA option', E_USER_ERROR);
						}
					}
				} else {
					trigger_error('TONIC_FIND_BY_METADATA option is required to be an array', E_USER_ERROR);
				}
			}
			
			// find order
			if (isset($options[TONIC_SORT_BY_METADATA])) {
				if (is_array($options[TONIC_SORT_BY_METADATA])) {
					$order = array_merge($order, $options[TONIC_SORT_BY_METADATA]);
				} else {
					$order[] = $options[TONIC_SORT_BY_METADATA];
				}
			}
			// find start and end
			if (isset($options[TONIC_FIND_FROM]) && is_numeric($options[TONIC_FIND_FROM])) {
				$offset = $options[TONIC_FIND_FROM] - 1;
				if (isset($options[TONIC_FIND_TO]) && is_numeric($options[TONIC_FIND_TO])) {
					$limit = $options[TONIC_FIND_TO] - $options[TONIC_FIND_FROM] + 1;
				}
			} elseif (isset($options[TONIC_FIND_TO]) && is_numeric($options[TONIC_FIND_TO])) {
				$offset = 0;
				$limit = $options[TONIC_FIND_TO];
			}
			
			// build SQL
			$sql = $this->_buildSelectStatement($options, $where, $order, $limit, $offset);
			if (isset($options[TONIC_GROUP_BY])) {
				$sql .= $this->_getGroupByClause($options[TONIC_GROUP_BY]);	
			}
			if (isset($options[TONIC_SELECT_FOR_UPDATE])) {
				$sql .= ' FOR UPDATE';
			}
		}
		//var_dump($sql);// die;
	
		//myerror_logging(1,"log leve in sql adapter is: ".$this->log_level);
		//myerror_logging(1,"server log level is: ".$_SERVER['log_level']);
		/*if ($this->log_level > 2)
			myerror_logging(1,"sql: ".$sql);
		else if ($or)
			myerror_logging(1,"sql with OR: ".$sql); */
		// execute SQL
		
		// check for SQL injections
		$sql_lower = str_replace('_delete', '', strtolower($sql));
		$sql_lower = str_replace('_update', '', strtolower($sql_lower));
		$sql_lower = str_replace('for update', '', strtolower($sql_lower));
		
		if (substr_count($sql_lower, 'delete ') > 0)
			die("DANGER!  SQL INJECTION with delete: ".$sql);
		else if (substr_count($sql_lower, 'update ') > 0)
			die("DANGER!  SQL INJECTION with update: ".$sql);
// CHANGE_THIS
		if (false && $_SERVER['GLOBAL_PROPERTIES']['server'] = 'test')
		{
			$num = rand(1,5);
			if ($num == 2 && substr_count($sql, "FROM Errors WHERE") < 1)
				$sql = "BLOWN SQL";
		}
		//myerror_logging(1,"the time zone now is: ".date_default_timezone_get());
		//myerror_log("the log level in this adapter is: ".$this->log_level);
		if ($this->log_level > 5) {
			myerror_log($sql);
		}
		$result = $this->_query($sql);
		if ($result) {
			$i = 0;
			while($row = $this->_fetchRow($result)) {
				if ($this->log_level > 5)
					foreach ($row as $name=>$value)
						myerror_logging(6,''.$name.':'.$value);
				//$row['url'] = $this->_makeUrl($row);
				foreach ($row as $field => $value) { // assign metatdata values
					if (in_array($field, $this->datetimeFields)) {
						$data[$i][$field] = $this->_dateToTimestamp($value);
					} elseif (substr($value, 0, 5) == 'srlz!') {
						$data[$i][$field] = unserialize(substr($value, 6));
					} else {
						$data[$i][$field] = $value;
					}
				}
				$i++;
			}
		}
		// grab found rows if asked to
		if (isset($options[TONIC_CALC_FOUND_RESOURCES]) && $options[TONIC_CALC_FOUND_RESOURCES]) {
			$this->foundResources = $this->_getFoundRows();
		}
        return $data;
    }
    
    /**
     * Insert a resource
     * @param Resource resource
     * @return bool
    */
    function insert(&$resource)
    {
        if ($this->log_level > 0) {
            myerror_logging(1,"starting insert");
        }
    	if ($this->read_only)
    	{
    		myerror_log("ERROR! connection is in read only mode");
    		throw new Exception("CONNECTION IS IN READ ONLY MODE", $code);
    	}
    	$resource->created = time();
		$names = '';
		$values = '';
		foreach ($this->fields as $field) {
			if (isset($resource->$field)) {
				$value = $resource->$field;
				$names .= $this->_escapeFieldName($field).', ';
				if (in_array($field, $this->datetimeFields)) { // datetime magic
					$values .= $this->_delimitString($this->_escape($this->_timestampToDate($value))).', ';
				} elseif (is_object($value) && is_a($value, 'Resource')) { // resource
					$values .= $this->_delimitString($this->_escape($value->url)).', ';
				} elseif (is_object($value) || is_array($value)) { // serialize
					$values .= $this->_delimitString($this->_escape('srlz!'.serialize($value))).', ';
				} else {
					$values .= $this->_delimitStringBetter($this->_escape($value),$field).', ';
				}
			}
		}
		$sql = sprintf(
			'INSERT INTO %s (%s) VALUES (%s)',
			$this->getTable(),
			substr($names, 0, -2),
			substr($values, 0, -2)
		);
		$val = 1;
		if (substr_count($sql, "DOCTYPE html PUBLIC") > 0 || $this->log_level == 0) {
            $val = 6;
        }

		myerror_logging($val,"the sql is: ".$sql);
		$result = $this->_query($sql);
		if (!$result) {
			$error_text = $this->getLastErrorText();
			myerror_log("there was an error inserting the record in sqladapter: $error_text");
			return false;
		}
		if ($this->_affectedRows()) {
			if ($this->primaryKeys != NULL)
				foreach ($this->primaryKeys as $id) {
					if (!isset($resource->$id)) {
						$resource->$id = $this->_insertId();
						break;
					}
				}
			return TRUE;
		}
	    return FALSE;
    }

    function isErrorADeadLock($error_text)
	{
		return substr_count($error_text,"Deadlock found") > 0;
	}
	
    /**
     * Update a resource
     * @param Resource resource
     * @return bool
     */
    function update(&$resource)
    {
        if ($this->log_level > 0) {
            myerror_logging(1,"starting update");
        }
    	if ($this->read_only)
    	{
    		myerror_log("ERROR! connection is in read only mode");
    		throw new Exception("CONNECTION IS IN READ ONLY MODE", $code);
    	}
    	$resource->modified = time();
    	$values = '';
		foreach ($this->fields as $field) {
			// Remove the created field for updates ***arosenthal*****
			if ($field != 'created') {
				if (isset($resource->$field)) {
					$value = $resource->$field;
					$values .= $this->_escapeFieldName($field).' = ';
					if (in_array($field, $this->datetimeFields)) {
						$values .= $this->_delimitString($this->_escape($this->_timestampToDate($value))).', ';
					} elseif (is_object($value) && is_a($value, 'Resource')) {
						$values .= $this->_delimitString($this->_escape($value->url)).', ';
					} elseif (is_object($value) || is_array($value)) { // serialize
                        $values .= $this->_delimitString('srlz!' . serialize($value)) . ', ';
                    } else if ($value === 0) {
                        $values .= '0, ';
					} elseif ($value === 'nullit') { // set to null
						$values .= 'NULL, ';
					} else {
						$values .= $this->_delimitStringBetter($this->_escape($value),$field).', ';
					}

				}
			}
		}
		$sql = sprintf(
			'UPDATE %s SET %s WHERE ',
			$this->getTable(),
			substr($values, 0, -2)
		);
		
		foreach ($this->primaryKeys as $key) {
			$sql .= sprintf(
				'%s.%s = %s AND ',
				$this->getTable(),
				$this->_escapeFieldName($key),
				$this->_delimitString($this->_escape($resource->$key))
			);
		}
		$sql = substr($sql, 0, -5);
		$val = 1;
        if (substr_count($sql, "DOCTYPE html PUBLIC") > 0 || $this->log_level == 0) {
            $val = 6;
        }
        myerror_logging($val,$sql);
		//$result = $this->doUpdateWithDeadlockRetry($sql);
		$result = $this->_query($sql);
		if (!$result) {
			//trigger_error($this->_error().' for URL "'.$resource->url.'"', E_USER_ERROR);
			myerror_log("there was an error updating the record in sqladapter: ".$this->getLastErrorText());
			$resource->set('error',$this->getLastErrorText());
		}
		$affected_rows = $this->_affectedRows(); 
		if ($affected_rows > 0) {
			return TRUE;
		}
		myerror_logging(2,"SQL adapter is about to return false because NO ROWS were updated: ".$this->getLastErrorText());
		if ($this->getLastErrorNo() == 0 && $resource->_exists)
		{
			myerror_logging(2,"CHANGE! SQL adapter is about to return TRUE because NO ROWS were updated but thing exists!  meaning values were the same.");
			return true;
		}
	    return FALSE;
    }

    /**
     * 
     * @desc Will retry the update N number of times.  N being the update_dead_lock_retry_attempts. CURRENTLY NOT BEING USED.
     * @param unknown_type $sql
     */
    private function doUpdateWithDeadlockRetry($sql)
    {
    	for ($i=0;$i<=$this->update_dead_lock_retry_attempts;$i++)
    	{
    		if ($result = $this->_query($sql)) {
    			return $result;
    		}	
    		else if  (substr_count(strtolower($this->_error()), 'deadlock found') > 0) {
    			// lets sleep for some number of mico second and retry
    			$int = rand(1000,10000);
    			usleep($int);
    		} else {
    			return false;
    		}
    	}
    	return false;
    }
    
    /**
     * Delete a resource
     * @param str url
     * @return bool
     */
    function delete($url)
    {
    	myerror_logging(1,"starting delete");
    	$keys = $this->_makeKeys($url);
		$sql = sprintf(
			'DELETE FROM %s WHERE ',
			$this->getTable()
		);
		foreach ($this->primaryKeys as $key) {
			$sql .= sprintf(
				'%s.%s = %s AND ',
				$this->getTable(),
				$this->_escapeFieldName($key),
				$this->_delimitString($this->_escape($keys[$key]))
			);
		}
		$sql = substr($sql, 0, -5);
		myerror_logging(1,"the sql is: ".$sql);
		$result = $this->_query($sql);
		if (!$result) {
			myerror_log("there was an error DELETING the record in sqladapter: ".$this->getLastErrorText());
			//trigger_error($this->_error().'" for URL "'.$url.'"', E_USER_ERROR);
		}
		if ($this->_affectedRows()) {
			return TRUE;
		}
    }
    
    function setFindByMetaData($data,$options)
    {
    	$options[TONIC_FIND_BY_METADATA] = $data;
    	return $options;
    }
    
    function setLogLevel($log_level)
    {
    	$this->log_level = $log_level;
    }
    
    function getLastErrorNo()
    {
    	return $this->error_no;
    }
    
    function getLastErrorText()
    {
    	return $this->error_text;
    }
    
    function getLastRunSQL()
    {
    	return $this->last_run_sql;
    }

}

?>
