<?php

class CheckDeviceCallInActivity extends SplickitActivity
{
    function doIt()
    {
        $call_in_gap_threshold_for_late_in_minutes = getProperty("call_in_gap_threshold_for_late");
        if ($call_in_gap_threshold_for_late_in_minutes == null || $call_in_gap_threshold_for_late_in_minutes < 4) {
            $call_in_gap_threshold_for_late_in_minutes = 4;
        }

        $dciha = new DeviceCallInHistoryAdapter(getM());
        if ($non_recently_called_in_devices_by_type = $dciha->getNonRecentlyCalledInDevicesOtherThanGPRS($call_in_gap_threshold_for_late_in_minutes)) {
            $merchant_adapter = new MerchantAdapter(getM());
            $auto_turned_off_infos = [];
            foreach ($non_recently_called_in_devices_by_type as $key=>$late_devices) {
                myerror_log("starting auto turn off deivces for $key");
                foreach ($late_devices as $late_device) {
                    $merchant_id = $late_device->merchant_id;
                    if ($merchant_resource = Resource::find($merchant_adapter,"$merchant_id")) {
                        $merchant_resource->ordering_on = 'N';
                        $merchant_resource->inactive_reason = 'F';
                        $merchant_resource->save();
                        $merchant_adapter->auditTrailForInternalFunction("INTERNAL: Auto Shut OFf /merchants/".$merchant_resource->merchant_id);
                        $late_device->auto_turned_off = 1;
                        $late_device->save();
                        $auto_turned_off_infos[] = "merchant_id: $merchant_id, device_format: $key";
                    }
                }
            }
            if (sizeof($auto_turned_off_infos) > 0) {
                $email_body = "These merchant devices have not called in for at least $call_in_gap_threshold_for_late_in_minutes minutes. They have been auto turned off: \r\n \r\n";
                foreach ($auto_turned_off_infos as $auto_turned_off_info) {
                    $email_body .= "$auto_turned_off_info \r\n";
                }
                myerror_log("Auto Turnoff Email:    $email_body");
                MailIt::sendErrorEmailSupport("Merchant Device Off Line Report. Merchants Auto Turned Off.",$email_body);
            }
        }
    }
}
