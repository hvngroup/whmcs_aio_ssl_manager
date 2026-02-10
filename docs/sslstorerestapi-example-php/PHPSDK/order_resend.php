<?php
require_once('api_settings.php');

messagehelper::writeinfo('Resend Approver Email');

$orderesendreq = new order_resend_request();
$orderesendreq->TheSSLStoreOrderID = '';
$orderesendreq->ResendEmailType = 'ApproverEmail';
messagehelper::writevarinfo($sslapi->order_resend($orderesendreq));


?>