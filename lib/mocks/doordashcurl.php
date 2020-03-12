<?php
class DoordashCurl extends SplickitCurl
{
    static function curlIt($url,$data)
    {
        $service_name = 'Doordash';
        $pickup_time = $data['pickup_time'];
        $p_ts = strtotime($pickup_time);

        $tz = new DateTimeZone('GMT');
        $dt = new DateTime();
        $dt->setTimezone($tz);
        $delivery_time_stamp = $p_ts + 50*60;
        $dt->setTimestamp($delivery_time_stamp);
        $delivery_time = $dt->format('Y-m-d').'T'.$dt->format('H:i:s').'.000000Z';

        $dt->setTimestamp($p_ts);
        $pickup_time = $dt->format('Y-m-d').'T'.$dt->format('H:i:s').'.000000Z';

        if (substr_count($url,'/estimates') > 0) {
            if ($_SERVER['force_doordash_estimate_fail']) {
                $response['raw_result'] = '{"field_errors":[{"field":"pickup_address","error":"Doordash does not serve this area"}]}';
                $response['http_code'] = 400;
            } else if ($data['order_value'] == null || $data['order_value'] < 1){
                $response['raw_result'] =   '{"delivery_time":"'.$delivery_time.'","fee":888,"dropoff_address":{"city":"Boulder","state":"CO","street":"1881 9th Street","unit":"","zip_code":"80302"},"pickup_time":"'.$pickup_time.'","currency":"USD","id":1}';
                $response['http_code'] = 200;
            } else {
                $fee = .2 * ($data['order_value']) + 500;
                $response['raw_result'] = '{"delivery_time":"'.$delivery_time.'","fee":'.$fee.',"dropoff_address":{"city":"Boulder","state":"CO","street":"1881 9th Street","unit":"","zip_code":"80302"},"pickup_time":"'.$pickup_time.'","currency":"USD","id":1}';
                $response['http_code'] = 200;
            }
        } else if (substr_count($url,'/validations') > 0) {
            $response['raw_result'] = '{"valid":true}';
            $response['http_code'] = 200;
        } else if (substr_count($url,'/cancel') > 0) {
            $cancel_time = date(DATE_ATOM,time());
            if ($_SERVER['force_doordash_estimate_fail']) {
                $response['raw_result'] = '{"Delivery cannot be cancelled so close to pickup time"}';
                $response['http_code'] = 400;
            } else {
                $response['raw_result'] = '{"cancelled_at":"'.$cancel_time.'"}';
                $response['http_code'] = 200;
            }

        } else if (substr_count($url,'/deliveries') > 0) {
            if ($_SERVER['force_doordash_estimate_fail']) {
                $response['raw_result'] = '{"field_errors":[{"field":"pickup_address","error":"Doordash does not serve this area"}]}';
                $response['http_code'] = 400;
            } else {
                $fee = .2 * ($data['order_value']) + 500;
                $response['raw_result'] = '{"rating":null,"pickup_window_start_time":null,"actual_return_time":null,"driver_reference_tag":null,"contains_alcohol":false,"updated_at":"2019-02-13T22:09:22.755811Z","currency":"USD","estimated_pickup_time":"2019-02-13T22:36:04.000000Z","pickup_window_end_time":null,"order_volume":null,"id":149637531,"dasher_status":"unassigned","estimated_delivery_time":"'.$delivery_time.'","fee":'.$fee.',"quoted_pickup_time":"2019-02-13T22:36:04.000000Z","dropoff_address":{"city":"Boulder","state":"CO","street":"1045 Pine Street","unit":"","zip_code":"80302"},"allow_unattended_delivery":false,"tip":0,"team_lift_required":false,"estimated_return_time":null,"batch_id":null,"external_store_id":null,"is_return_delivery":false,"pickup_instructions":"Business: Splickit test business. Phone Number: +16505555555. ask for the splickit order for First Last","dasher":null,"status":"scheduled","quoted_delivery_time":"'.$delivery_time.'","actual_pickup_time":null,"delivery_window_start_time":null,"signature_required":false,"delivery_window_end_time":null,"pickup_address":{"city":"Boulder","state":"CO","street":"1505 Arapahoe Avenue","unit":"","zip_code":"80302"},"barcode_scanning_required":false,"submit_platform":"drive_api","delivery_tracking_url":"https://doordash.com/drive/portal/track/xkbj2vZwydkiXzb","external_delivery_id":null,"customer":{"phone_number":"+19709262121","business_name":"","first_name":"First","last_name":"Last","email":"testuser_1550095745_h4r@dummy.com"},"return_delivery_id":null,"parent_delivery_id":null,"order_value":150,"items":[],"dropoff_instructions":"","actual_delivery_time":null,"signature_image_url":null,"quantity":1}';
                $response['http_code'] = 201;
            }
        } else {
            $response['http_code'] = 500;
        }

        return $response;
    }
}
?>