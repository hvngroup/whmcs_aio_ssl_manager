<?php
require_once('api_settings.php');

messagehelper::writeinfo('Getting Order Agreement:');

$orderagreementreq = new order_agreement_request();
$orderagreementreq->ProductCode = 'rapidssl';
$orderagreementreq->ServerCount = 1;
$orderagreementreq->ValidityPeriod = 12;
messagehelper::writeinfo($sslapi->order_agreement($orderagreementreq));

?>