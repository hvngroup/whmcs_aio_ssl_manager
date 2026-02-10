<?php
require_once('api_settings.php');

messagehelper::writeinfo('Getting Approver Email List:');

$approverreq = new order_approverlist_request();
$approverreq->ProductCode = 'rapidssl';
$approverreq->DomainName = 'test.com';
messagehelper::writevarinfo($sslapi->order_approverlist($approverreq));
?>