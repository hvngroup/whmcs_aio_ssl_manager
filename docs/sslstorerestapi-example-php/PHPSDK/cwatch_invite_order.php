<?php

require_once('api_settings.php');

messagehelper::writeinfo('cWatch Invite Order:');

$inviteorderreq = new cwatch_invite_order_request();
$inviteorderreq->CustomOrderID = uniqid('TinyOrder-');
$inviteorderreq->EmailLanguageCode = 'EN';
$inviteorderreq->PreferSendOrderEmails = false;
$inviteorderreq->ProductCode = 'cwatchstarter';
$inviteorderreq->RequestorEmail = '';
$inviteorderreq->ValidityPeriod = 12; //Months
messagehelper::writevarinfo($sslapi->cwatch_invite_order($inviteorderreq));

?>