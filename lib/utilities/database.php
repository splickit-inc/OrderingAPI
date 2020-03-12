<?php
class Database
{
    private $_connection;
    private static $_instance; //The single instance

    public static function getInstance() {
        if(!self::$_instance) { // If no instance then make one
            myerror_log("CONNECTION: creating a NEW connection",6);
            self::$_instance = new self();
        } else {
            myerror_log("CONNECTION: grabbing EXISTING connection",6);
        }
        return self::$_instance;
    }

    public static function getNewInstance() {
        myerror_log("CONNECTION: creating a NEW connection",6);
        self::$_instance = new self();
        return self::$_instance;
    }

    private function __construct() {
        $db_info = DatabaseInfo::getDbInfo();
        $hostname = $db_info->hostname;
        $username = isset($db_info->username) ? $db_info->username : $db_info->user_name;
        $password = $db_info->password;
        $database = $db_info->database;
        if ($db_info->port) {
            $port = $db_info->port;
        }

        myerror_log("$hostname - $username - password - $database - $port",3);

        if (isset($db_info->read_only)) {
            $this->read_only = $db_info->read_only;
        }
        myerror_logging(6, "connecting to $hostname with db ".print_r($db_info, true));

        $this->_connection = new mysqli($hostname, $username, $password, $database, $port);

        // Error handling
        if(mysqli_connect_error()) {
            trigger_error("Failed to conencto to MySQL: " . mysqli_connect_error(), E_USER_ERROR);
        }
        mysqli_query($this->_connection,"SET CHARACTER SET 'utf8'");
    }

    private function __clone() { }

    // Get mysqli connection
    public function getConnection() {
        return $this->_connection;
    }

}




?>