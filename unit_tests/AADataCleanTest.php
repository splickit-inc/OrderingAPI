<?php
ini_set("error_log","./logs/php_errors.log");
ini_set('max_execution_time', 300);
$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require 'vendor/autoload.php';
//require_once 'tonic'.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'resource.php';
//require_once "lib".DIRECTORY_SEPARATOR."adapters".DIRECTORY_SEPARATOR."useradapter.php";


class AADataCleanTest extends PHPUnit_Framework_TestCase
{
	var $stamp;
  var $handle;
  var $hostname;
  var $username;
  var $password;
  var $database;

	function testCreateTheDb()
	{
        $_SERVER['DO_NOT_CHECK_CACHE'] = true;
        $_SERVER['DO_NOT_SAVE_TO_CACHE'] = true;
		error_log("here i am");
		set_time_limit(300);
		$environment = (isset($_SERVER['XDEBUG_CONFIG'])) ? "unit_test_ide" : "unit_test";
		$_SERVER['ENVIRONMENT'] = $environment;
		//$_SERVER['ENVIRONMENT_NAME'] = "laptop";
		$filename = "./config/local.conf";
		if ($txt = file_get_contents($filename)) {
			$local_properties_as_array = json_decode($txt,true);
			$local_properties = array();
			foreach ($local_properties_as_array as $local_property) {
				$local_properties[$local_property['name']] = $local_property['value'];
			}
			if ($log_file_full_path = $local_properties['log_file_path']) {
				$output = shell_exec("cat /dev/null > $log_file_full_path");
			}
			if ($appache_log_file_full_path = $local_properties['appache_log_file_path']) {
				$output = shell_exec("cat /dev/null > $appache_log_file_full_path");
			}
            if ($appache_access_log_file_full_path = $local_properties['appache_access_log_file_path']) {
                $output = shell_exec("cat /dev/null > $appache_access_log_file_full_path");
            }
		}


		$version = phpversion();
		$file_path = php_ini_loaded_file();
		$ipath = get_include_path();
		error_log("version = ".$version);
		error_log("ipath = " . $ipath);
		error_log("ini_file = ".$file_path);

		$filename = "./config/smaw_database_unittest.conf";
		$txt = file_get_contents($filename);
		if ($dbs = json_decode($txt)) {
			error_log("yes we can");
		} else {
			error_log("NO NO NO");
		}
		$dbInfo = $dbs->$environment;
		$hostname = $dbInfo->hostname;
		$username = $dbInfo->username;
		$password = $dbInfo->password;
		$database = $dbInfo->database;
		if ($database != 'smaw_unittest') {
			die("something other than unit test being used for AADataCleanTest: $database");
		}
		error_log("about to connect to $database  with user:  $username");
		$handle = mysqli_connect($hostname, $username, $password);
		error_log("after connect");
		$this->assertNotNull($handle);

		if (mysqli_select_db($handle,$database)) {
			$sql = "DROP DATABASE $database";
			error_log("about to drop the db");
			if (mysqli_query($handle,$sql))
				error_log("the db has been dropped");
			else
				die('couldnt drop db. killing process');
		} else {
			error_log("no db to drop maybe?");
		}
		//create the db
		$sql="CREATE DATABASE $database";
		error_log($sql);
		if (mysqli_query($handle,$sql)) {
			$output = shell_exec("mysql -h db_container --user=$username --password='$password' $database < ./unit_tests/smaw_unittest_schema.sql");
			$output2 = shell_exec("mysql -h db_container --user=$username --password='$password' $database < ./unit_tests/UnitTest_Merchant_Data.sql");
			$output3 = shell_exec("mysql -h db_container --user=$username --password='$password' $database < ./unit_tests/create_order_sp.sql");
			require_once 'lib/utilities/unit_test_functions.inc';
			require_once 'lib/utilities/functions.inc';

			$this->migrations($database, $handle);
			$output6 = shell_exec("mysql -h db_container --user=$username --password='$password' $database < ./sql/adm_dma_codes.sql");
			$output5 = shell_exec("mysql -h db_container --user=$username --password='$password' $database < ./sql/adm_dma.sql");
			$this->createTheAdamUser();
            $sqls[] = "INSERT INTO Splickit_Accepted_Payment_Types VALUES (7000,'Dummy Card','Dummy Card','dummycardstoredvaluepaymentservice',NOW(),NOW(),'N')";
			$sqls[] = "INSERT INTO Splickit_Accepted_Payment_Types VALUES (8000,'Loyalty Balance','Loyalty Balance Payment','loyaltybalancepaymentservice',NOW(),NOW(),'N')";
			$sqls[] = "INSERT INTO Splickit_Accepted_Payment_Types VALUES (9000,'Loyalty Balance + Cash','Loyalty Balance Payment','loyaltybalancepaymentservice',NOW(),NOW(),'N')";
			//$sqls[] = "INSERT INTO Splickit_Accepted_Payment_Types VALUES (8001,'Punch Balance','Loyalty Balance Payment','punchloyaltybalancepaymentservice',NOW(),NOW(),'N')";
            $sqls[] = "INSERT INTO Vio_Credit_Card_Processors VALUES (2004,'Payeezy','Payeezy',2000,null,null,NOW(),NOW(),'N')";
			$sqls[] = "INSERT INTO Vio_Credit_Card_Processors VALUES (2005,'Heartland','Heartland',2000,null,null,NOW(),NOW(),'N')";
			$sqls[] = "INSERT INTO Vio_Credit_Card_Processors VALUES (2008,'Secure-Net','Secure-Net',2000,null,null,NOW(),NOW(),'N')";
			$sqls[] = "INSERT INTO Vio_Credit_Card_Processors VALUES (2009,'Vio-Instant','Vio-Instant',2000,null,null,NOW(),NOW(),'N')";
			$sqls[] = "CREATE TABLE `adm_moes_aloha_info` (
						  `id` int(11) NOT NULL AUTO_INCREMENT,
						  `item_id` int(11) DEFAULT NULL,
						  `modifier_id` int(11) DEFAULT NULL,
						  `size_id` int(11) DEFAULT NULL,
						  `name` varchar(255) NOT NULL,
						  `aloha_id` int(11) NOT NULL,
						  `rule` enum('item-mod','item-size','item','mod') DEFAULT NULL,
						  `major_group` varchar(254) NOT NULL DEFAULT 'X',
						  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
						  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
						  `logical_delete` char(1) NOT NULL DEFAULT 'N',
						  PRIMARY KEY (`id`)
						) ENGINE=InnoDB AUTO_INCREMENT=1000 DEFAULT CHARSET=utf8;";
            $sqls[] = "ALTER TABLE `Brand2` ADD `loyalty_title` VARCHAR(255) AFTER `use_loyalty_lite`";
            $sqls[] = "ALTER TABLE `Brand2` ADD `support_email` VARCHAR(255) AFTER `loyalty_title`";
			$sqls[] = "ALTER TABLE `Brand2` ADD `support_email_categories` VARCHAR(255) AFTER `support_email`";
            $sqls[] = "ALTER TABLE `Brand2` ADD `production` VARCHAR(1) AFTER `support_email_categories`";
			$sqls[] = "INSERT INTO Foundry_Brand_Card_Tender_Ids (`brand_id`,`visa`,`master`,`american_express`,`discover`) VALUES (482,215,216,217,218),(485,3,3,2,null),(484,44,44,44,44),(480,100,100,100,100),(486,56,57,58,59); ";

			$this->loadMoreDataNotPartOfMigrations($database,$handle,$sqls);
		} else {
			die("couldn't create the db.  killing process.".mysqli_error($handle));
		}
		createWorldHqSkin();
		$this->createPublicClientIds();
		$this->loadMoreDataNotPartOfMigrations($database,$handle,array("UPDATE `Skin` SET `show_notes_fields` = 1 WHERE 1 = 1 AND `logical_delete` = 'N'"));

        $_SERVER['DO_NOT_CHECK_CACHE'] = false;
        $_SERVER['DO_NOT_SAVE_TO_CACHE'] = false;
        SplickitCache::flushAll();
	}

	function createPublicClientIds()
	{
		$sql = "SELECT * FROM Skin WHERE `public_client_id` = '' OR `public_client_id` IS NULL";
		$options[TONIC_FIND_BY_SQL] = $sql;
		foreach (Resource::findAll(new SkinAdapter($m),null,$options) as $skin_resource) {
			$skin_resource->public_client_id = createUUID();

			$skin_resource->save();
		}
	}

    function loadMoreDataNotPartOfMigrations($database,$handle,$sqls)
    {
        mysqli_select_db($handle,$database);
        foreach ($sqls as $sql) {
            mysqli_query($handle,$sql);
			$error = mysqli_error();
			error_log($error);
        }
    }
	
	function createTheAdamUser()
	{

		$user_resource = createNewUserWithCC(array("email"=>'radamnyc@gmail.com'));
		$this->assertTrue(is_a($user_resource, 'Resource'));
		$this->assertTrue($user_resource->user_id > 19999);
	}
    
	function migrations($database, $handle)
	{
		mysqli_select_db($handle,$database);
		
		$sqls[] = "ALTER TABLE `Merchant` ADD `tip_minimum_percentage` INT(11) NOT NULL DEFAULT '0' AFTER `show_tip`, ADD `tip_minimum_trigger_amount` DECIMAL(10,2) NOT NULL DEFAULT '0.00' AFTER `tip_minimum_percentage`;";
		$sqls[] = "UPDATE Merchant SET `tip_minimum_percentage` = 10, `tip_minimum_trigger_amount` = 50.00 WHERE brand_id = 150;";
		$sqls[] = "INSERT INTO `Brand2` VALUES(430, 'Goodcents Subs', 'Y', 'N', 'N', NULL, 'splickitapiuser', 'Spl2ck2ty', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N', 'Y');";

		foreach ($sqls as $sql) {
			mysqli_query($handle,$sql);
		}
		
		$user_options[TONIC_FIND_BY_SQL] = "SELECT * FROM User WHERE uuid IS NULL";
		$user_resources = Resource::findAll(new UserAdapter(), null, $user_options);
		foreach ($user_resources as $user_resource) {
			$user_resource->uuid = generateUUID();
			$user_resource->save();
		}
		
		$output = shell_exec('vendor/bin/phinx migrate -e unittest');
		error_log("calling phinx migration output: ".$output);
		error_log("finishing migrations");
		
		//shell_exec('vendor/bin/phinx migrate');

		$sql = "UPDATE `Property` SET value = 'true' WHERE name = 'use_new_pita_pit_loyalty' LIMIT 1";
		mysqli_query($handle,$sql);

		$sql2[] = "INSERT INTO `Lookup` VALUES(null, 'message_template', 'FPC', '/order_templates/fax/execute_order_fax_PP4.htm', 'Y', now(), now(), 'N');";
        $sql2[] = "INSERT INTO `Lookup` VALUES(null, 'message_template', 'FAM', '/order_templates/email/execute_order_email_exceptions_amicis_SF.html', 'Y', now(), now(), 'N');";
		$sql2[] = "INSERT INTO `Lookup` VALUES(null, 'message_template', 'BO', '/order_templates/brink/execute_order_brink.xml', 'Y', now(), now(), 'N');";
        $sql2[] = "INSERT INTO `Splickit_Accepted_Pos_Types` (`id`,`name`) VALUES (1000,'Brink'),(2000,'Vivonet'),(3000,'Emagine'),(4000,'Xoikos'),(5000,'Task Retail')";

        foreach ($sql2 as $sql) {
            mysqli_query($handle,$sql);
        }
	}
    
    /* mail method for testing */
    static function main() {
		$suite = new PHPUnit_Framework_TestSuite( __CLASS__);
  		PHPUnit_TextUI_TestRunner::run( $suite);
 	}
    
}

if (isset($_SERVER['XDEBUG_CONFIG']) && !defined('PHPUnit_MAIN_METHOD')) {
    AADataCleanTest::main();
}

?>
