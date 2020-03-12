<?php

$filepathParts = pathinfo(__FILE__);
$path = $filepathParts['dirname'];
chdir($path . '/../');

require_once 'lib/utilities/unit_test_functions.inc';
require_once 'lib/utilities/functions.inc';

class ImporterTest extends PHPUnit_Framework_TestCase
{
    var $stamp;
    var $ids;

    function setUp()
    {
        $_SERVER['HTTP_NO_CC_CALL'] = 'true';
        $this->stamp = $_SERVER['STAMP'];
        $_SERVER['STAMP'] = __CLASS__.'-'.$_SERVER['STAMP'];
        $this->ids = $_SERVER['unit_test_ids'];

    }

    function tearDown()
    {
        //delete your instance
        $_SERVER['STAMP'] = $this->stamp;
        unset($this->ids);
    }

    function testImporterFactory()
    {
//        setContext("com.splickit.pitapit");
//        $url = "/apiv2/pos/import/brink/pitapit-88888";
//        $importer = ImporterFactory::getImporterFromUrl($url);
//        $this->assertTrue(is_a($importer,'BrinkImporter'),"should have created a brink importer");
//        $merchant_resource = $importer->getLoadedMerchantResource();
//        $this->assertEquals($this->ids['merchant_id'],$merchant_resource->merchant_id,"should have loaded the merchant as part of the factory");

    }

    /**
     * @expectedException NoMatchingImporterException
     */
    function testImporterFactoryNoMatching()
    {
        $url = "/apiv2/pos/import/Tyhgdfh/34567ytrfgu765";
        $importer = ImporterFactory::getImporterFromUrl($url);
    }

    /**
     * @expectedException NoMatchingMerchantException
     */
    function testImporterNoMatchingMerchantExternalId()
    {
        $importer = new XoikosImporter('888889999');
    }

    function testImportAllRecuringActivity()
    {
        $skin_resource = getOrCreateSkinAndBrandIfNecessary('goodcentssubs','Goodcents Subs',140,430);
        $menu_id = createTestMenuWithNnumberOfItems(1);
        $merchant_resource1 = createNewTestMerchant($menu_id);
        $merchant_resource1->merchant_external_id = generateCode(10);
        $merchant_resource1->brand_id = 430;
        $merchant_resource1->save();
        $merchant_resource2 = createNewTestMerchant($menu_id);
        $merchant_resource2->merchant_external_id = generateCode(10);
        $merchant_resource2->brand_id = 430;
        $merchant_resource2->save();
        $merchant_resource3 = createNewTestMerchant($menu_id);
        $merchant_resource3->merchant_external_id = generateCode(10);
        $merchant_resource3->brand_id = 430;
        $merchant_resource3->save();

        $activity_history_adapter = new ActivityHistoryAdapter(getM());
        $info = 'object=XoikosImporter;method=staticStageImportForEntireBrandList;thefunctiondatastring=/import/xoikos/all';
        $original_activity_history_resource = $activity_history_adapter->createActivityReturnActivityResource('ExecuteObjectFunction', time() - 5, $info, null,86400);
        $id = $original_activity_history_resource->activity_id;

        $activity = $activity_history_adapter->getNextActivityToDo(null);
        $this->assertEquals($id, $activity->getActivityHistoryId());
        $activity->executeThisActivity();
        $activity_history_resource = SplickitController::getResourceFromId($id, "ActivityHistory");
        $this->assertEquals('E', $activity_history_resource->locked);
        $sql = "UPDATE Activity_History SET locked = 'E'";
        $activity_history_adapter->_query($sql);
    }

    function testStageImportAll()
    {
        $skin_resource = getOrCreateSkinAndBrandIfNecessary('goodcentssubs','Goodcents Subs',140,430);
        setContext("com.splickit.goodcentssubs");
        $menu_id = createTestMenuWithNnumberOfItems(1);
        $merchant_resource1 = createNewTestMerchant($menu_id);
        $merchant_resource1->merchant_external_id = generateCode(10);
        $merchant_resource1->save();
        $merchant_resource2 = createNewTestMerchant($menu_id);
        $merchant_resource2->merchant_external_id = generateCode(10);
        $merchant_resource2->save();
        $merchant_resource3 = createNewTestMerchant($menu_id);
        $merchant_resource3->merchant_external_id = generateCode(10);
        $merchant_resource3->save();
        $request = new Request();
        $request->url = "/apiv2/pos/import/xoikos/all";
        $request->method = "POST";
        $pos_controller = new PosController(getM(),null,$request,5);
        $resource = $pos_controller->processV2request();
        //$this->assertEquals("The imports have been staged. there were 6 merchants.",$resource->message);

//        $id = $pos_controller->getId();
//        $activity_resource = Resource::find(new ActivityHistoryAdapter($m),"$id",$options);
//        $this->assertNotNull($activity_resource);
//        $this->assertEquals("object=XoikosImporter;method=import;thefunctiondatastring=all",$activity_resource->info);

        $aha = new ActivityHistoryAdapter(getM());
        $doit = time() - 10;
        $sql = "UPDATE Activity_History SET doit_dt_tm = $doit";
        $aha->_query($sql);
        $activity = $aha->getNextActivityToDo($aha_options);

        $results = $activity->executeThisActivity();

        $import_audit_adapter = new ImportAuditAdapter(getM());



    }

//    function testStageImport()
//    {
//        setContext("com.splickit.pitapit");
//        $request = new Request();
//        $request->url = "/apiv2/pos/import/brink/pitapit-88888";
//        $request->method = "POST";
//        $pos_controller = new PosController($mt,$u,$request,5);
//        $resource = $pos_controller->processV2request();
//        $this->assertEquals("The import has been staged",$resource->message);
//
//        $id = $pos_controller->getId();
//        $activity_resource = Resource::find(new ActivityHistoryAdapter($m),"$id",$options);
//        $this->assertNotNull($activity_resource);
//        $this->assertEquals("object=BrinkImporter;method=import;thefunctiondatastring=pitapit-88888",$activity_resource->info);
//    }

    static function setUpBeforeClass()
    {
        $_SERVER['request_time1'] = microtime(true);
        $tz = date_default_timezone_get();
        $_SERVER['starting_tz'] = $tz;
        date_default_timezone_set(getProperty("default_server_timezone"));
        ini_set('max_execution_time',300);
        SplickitCache::flushAll();
        $db = DataBase::getInstance();
        $mysqli = $db->getConnection();
        $mysqli->begin_transaction(); ;

        $skin_resource = createWorldHqSkin();
        $ids['skin_id'] = $skin_resource->skin_id;

        $merchant_resource = getOrCreateNewTestMerchantBasedOnExternalId('pitapit-88888');
        $merchant_resource->brand_id = 282;
        $merchant_resource->save();
        $ids['merchant_id'] = $merchant_resource->merchant_id;

//        //map it to a menu
//        $menu_id = createTestMenuWithNnumberOfItems(5);
//        $ids['menu_id'] = $menu_id;
//
//        $modifier_group_resource = createModifierGroupWithNnumberOfItems($menu_id, 10);
//        $modifier_group_id = $modifier_group_resource->modifier_group_id;
//        $item_records = CompleteMenu::getAllMenuItemsAsArray($menu_id, 'Y', $mimetypes);
//        assignModifierGroupToItemWithFirstNAsComesWith($item_records[0]['item_id'], $modifier_group_id, 2);
//        assignModifierGroupToItemWithFirstNAsComesWith($item_records[1]['item_id'], $modifier_group_id, 4);
//        assignModifierGroupToItemWithFirstNAsComesWith($item_records[2]['item_id'], $modifier_group_id, 1);
//
//        $merchant_resource = createNewTestMerchant($menu_id);
//        attachMerchantToSkin($merchant_resource->merchant_id, $ids['skin_id']);
//        $ids['merchant_id'] = $merchant_resource->merchant_id;
//
//        $user_resource = createNewUser(array("flags"=>"1C20000001"));
//        $ids['user_id'] = $user_resource->user_id;

        $_SERVER['log_level'] = 5;
        $_SERVER['unit_test_ids'] = $ids;
    }

    static function tearDownAfterClass()
    {
        SplickitCache::flushAll();
        $db = DataBase::getInstance();
        $mysqli = $db->getConnection();
        $mysqli->rollback();
        date_default_timezone_set($_SERVER['starting_tz']);
    }

    /* mail method for testing */
    static function main() {
        $suite = new PHPUnit_Framework_TestSuite( __CLASS__);
        PHPUnit_TextUI_TestRunner::run( $suite);
    }

}

if (isset($_SERVER['XDEBUG_CONFIG']) && !defined('PHPUnit_MAIN_METHOD')) {
    ImporterTest::main();
}

?>