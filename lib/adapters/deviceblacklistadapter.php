<?php

class DeviceBlacklistAdapter extends MySQLAdapter
{

    function DeviceBlacklistAdapter($mimetypes)
    {
        parent::MysqlAdapter(
            $mimetypes,
            'Device_Blacklist',
            '%([0-9]{4,10})%',
            '%d',
            array('id'),
            null,
            array('created','modified')
        );

        $this->allow_full_table_scan = false;

    }

    static function addUserResourceToBlackList($user_resource)
    {
        $dbla = new DeviceBlacklistAdapter($m);
        if (is_numeric($user_resource)) {
            $user_resource = Resource::find(new UserAdapter($m),"$user_resource",$options);
        }
        if (is_a($user_resource,"Resource")) {
            $device_id = $dbla->getDeviceIdFromUserResourceForBlackList($user_resource);
            Resource::createByData($dbla, array("device_id" => $device_id));
            //$user_resource->logical_delete = 'Y';
            //$user_resource->email = 'blacklisted_'.$user_resource->email;
            $user_resource->flags = 'X000000001';
            $user_resource->save();
        } else {
            throw new Exception("No user submitted");
        }
    }

    static function isUserResourceOnBlackList($user_resource)
    {
        $dbla = new DeviceBlacklistAdapter($m);
        return $dbla->getBlackListRecordByUserResource($user_resource) != null;
    }

    static function isDeviceIdOnBlackList($device_id)
    {
        $dbla = new DeviceBlacklistAdapter($m);
        return $dbla->getBlackListRecordByDeviceId($device_id) != null;
    }



    function getBlackListRecordByUserResource($user_resource)
    {
        $device_id = $this->getDeviceIdFromUserResourceForBlackList($user_resource);
        return $this->getBlackListRecordByDeviceId($device_id);
    }

    function getBlackListRecordByDeviceId($device_id)
    {
        return $this->getRecord(array('device_id'=>$device_id));
    }

    function getDeviceIdFromUserResourceForBlackList($user_resource)
    {
        return $user_resource->device_id == null || trim($user_resource->device_id) == '' ? 'userid-'.$user_resource->user_id : $user_resource->device_id;
    }

}
?>