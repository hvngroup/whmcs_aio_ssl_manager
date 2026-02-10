<?php
require_once('api_settings.php');

messagehelper::writeinfo('Order Refund Status:');

$refundStatus = new order_refundstatus_request();
$refundStatus->TheSSLStoreOrderID = '';

messagehelper::writevarinfo($sslapi->order_refundstatus($refundStatus));
