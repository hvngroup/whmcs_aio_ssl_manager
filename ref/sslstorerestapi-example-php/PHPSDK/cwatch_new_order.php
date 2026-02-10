<?php
require_once('api_settings.php');

messagehelper::writeinfo('cWatch Full New Order');

$new_order = new cwatch_new_order_request();

$new_order->AdminContact->FirstName = '';
$new_order->AdminContact->LastName = '';
$new_order->AdminContact->Phone = '';
$new_order->AdminContact->Email = '';
$new_order->AdminContact->Country = '';

$new_order->autoLicenseUpgrade = false;
$new_order->automaticRenewal = false;
$new_order->CustomOrderID = '';
$new_order->ProductCode = '';
$new_order->ValidityPeriod = '';
$new_order->DomainName = '';
$new_order->SpecialInstructions = '';
$new_order->ApproverEmail = '';
$new_order->EmailLanguageCode = '';

messagehelper::writevarinfo($neworderresponse = $sslapi->cwatch_new_order($new_order));
