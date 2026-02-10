<?php
require_once('api_settings.php');

messagehelper::writeinfo('Change Approver Email');

$orderchangeapproveremailreq = new order_changeapproveremail_request();
$orderchangeapproveremailreq->TheSSLStoreOrderID = '1234';
$orderchangeapproveremailreq->ResendEmail = 'admin@test.com';
$orderchangeapproveremailreq->DomainNames = 'test.com';
messagehelper::writevarinfo($sslapi->order_changeapproveremail($orderchangeapproveremailreq));

?>