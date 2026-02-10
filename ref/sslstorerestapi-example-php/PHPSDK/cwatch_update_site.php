<?php
require_once('api_settings.php');

messagehelper::writeinfo('cWatch Update Site:');
$update_site_request = new cwatch_update_site_request();
$update_site_request->TheSSLStoreOrderID = 0; //Your order id
$update_site_request->DomainName = '';


messagehelper::writevarinfo($sslapi->cwatch_update_site($update_site_request));