<?php
require_once('api_settings.php');

messagehelper::writeinfo('Order PMR Status:');

$pmr_req = new order_pmr_request();
$pmr_req->TheSSLStoreOrderID = '';
messagehelper::writevarinfo($sslapi->order_pmr($pmr_req));

?>