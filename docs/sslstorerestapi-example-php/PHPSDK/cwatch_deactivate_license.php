<?php
require_once('api_settings.php');

messagehelper::writeinfo('cWatch Deactivate License:');

$refund_request = new order_refundrequest_request();
$refund_request->RefundReason = '';
$refund_request->TheSSLStoreOrderID = 0;

messagehelper::writevarinfo($sslapi->cwatch_deactivate_license($refund_request));
?>