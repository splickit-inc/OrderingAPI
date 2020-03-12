<?php
class DatabaseInfo
{
    static function getDbInfo() {
        myerror_log("STARTING getDbInfo",5);
            if ($_SERVER['FORCE_PROD'] != 'true' && $_SERVER['FORCE_TEST'] != 'true') {
                if (isset($_SERVER['DB_INFO']->database)) {
                    return $_SERVER['DB_INFO'];
                }
            }


        $filename = isset($_SERVER['DB_INFO_FILE_PATH']) ? $_SERVER['DB_INFO_FILE_PATH'] : "/usr/local/splickit/etc/smaw_database.conf";
        if ($txt = file_get_contents($filename)) {
            $dbs = json_decode($txt);
            // correct for environment names
            //$dbs->development = $dbs->staging;
            $dbs->prod = $dbs->production;
            if ($dbs->unit_test) {
                ;//bypass
            } else {
                $dbs->unit_test = $dbs->laptop;
                $dbs->unit_test->database = "smaw_unittest";
            }
        } else {
            myerror_log("ERROR!!!!! COULDN'T LOAD THE '$filename' file. Killing processes!!");
            die("couldn't load database.conf file");
        }
        if ($environment_name = getEnvironment()) {
            myerror_log("the environment name is: $environment_name",5);
            if ($db_info = $dbs->$environment_name) {
                myerror_logging(6,"we have loaded the db_info: ".print_r($db_info,true));
            } else {
                myerror_log("ERROR!!!! couldn't load db properties for environment: ".$environment_name);
                die ("couldnt load db properties. unrecognized environment name: ".$environment_name);
            }
        } else {
            die ("could not get environment");
        }
        if ($_SERVER['FORCE_PROD'] == 'true') {
            myerror_log("We are forcing PROD");
            $db_info = $dbs->production;
            $db_info->read_only = true;
            if (!isUnitTest()) {
                die("can't force prod on anything but laptop");
            }
            return $db_info;
        } else if ($_SERVER['FORCE_TEST'] == 'true') {
            myerror_log("We are forcing TEST");
            $db_info = $dbs->staging;
            if (!isUnitTest()) {
                die("can't force prod on anything but laptop");
            }
            return $db_info;
        }
        $_SERVER['DB_INFO'] = $db_info;
        return $db_info;
    }
}
?>