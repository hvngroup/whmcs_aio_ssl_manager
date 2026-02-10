<?php
require_once('api_settings.php');

messagehelper::writeinfo('cWatch Upgrade License:');
$cwatch_upgrade_license_request = new cwatch_upgrade_license_request();
$cwatch_upgrade_license_request->TheSSLStoreOrderID = 0; //Your order id
$cwatch_upgrade_license_request->ProductCode = '';
$cwatch_upgrade_license_request->ValidityPeriod = 12;

messagehelper::writevarinfo($sslapi->cwatch_upgrade_license($cwatch_upgrade_license_request));