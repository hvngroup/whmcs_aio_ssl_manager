<?php
require_once('api_settings.php');

messagehelper::writeinfo('cWatch Order Status:');
$order_status_request = new order_status_request();
$order_status_request->TheSSLStoreOrderID = 0; //Your order id

messagehelper::writevarinfo($sslapi->cwatch_order_status($order_status_request));


?>