<?php
require_once('api_settings.php');

messagehelper::writeinfo('Order Status:');
$orderstatusrequest = new order_status_request();
$orderstatusrequest->TheSSLStoreOrderID= ''; //Your order id

messagehelper::writevarinfo($sslapi->order_status($orderstatusrequest));
?>