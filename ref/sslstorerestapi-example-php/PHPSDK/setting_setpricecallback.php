<?php
require_once('api_settings.php');

messagehelper::writeinfo('Set Price Callback:');

$priceCallback = new setting_setpricecallback_request();
$priceCallback->url = "www.test.com";

messagehelper::writevarinfo($sslapi->setting_setpricecallback($priceCallback));

?>

