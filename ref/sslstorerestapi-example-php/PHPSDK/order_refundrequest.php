<?php
require_once('api_settings.php');

messagehelper::writeinfo('Refund Request:');

$refundreq = new order_refundrequest_request();
$refundreq->RefundReason = 'Order For testing';
$refundreq->TheSSLStoreOrderID = '';
messagehelper::writevarinfo($sslapi->order_refundrequest($refundreq));
?>