<?php

require_once('api_settings.php');

messagehelper::writeinfo('Invite Order:');

$inviteorderreq = new order_inviteorder_request();
$inviteorderreq->AddInstallationSupport = false;
$inviteorderreq->CustomOrderID = uniqid('TinyOrder-');
$inviteorderreq->EmailLanguageCode = 'EN';
$inviteorderreq->PreferVendorLink = false;
$inviteorderreq->ProductCode = 'positivessl';
$inviteorderreq->RequestorEmail = '';
$inviteorderreq->ServerCount = 1;
$inviteorderreq->ValidityPeriod = 12; //Months
$inviteorderreq->ExtraProductCode = '';
$inviteorderreq->ExtraSAN = 4;
$inviteorderreq->IsWildcardCSRDomain = true; // Check for, is main domain is wildcard in flex?
$inviteorderreq->ExtraWildcardSAN = 0; // Extra wildcard SANs in flex product
$inviteorderreq->OrganizationIds = array();
messagehelper::writevarinfo($sslapi->order_inviteorder($inviteorderreq));

?>