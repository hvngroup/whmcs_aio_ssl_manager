<?php
require_once('api_settings.php');

messagehelper::writeinfo('Set Order Callback:');

$orderCallback = new setting_setordercallback_request();
$orderCallback->url = "www.test.com";

messagehelper::writevarinfo($sslapi->setting_setordercallback($orderCallback));


?>

