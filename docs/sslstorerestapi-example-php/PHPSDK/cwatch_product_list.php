<?php
require_once('api_settings.php');

messagehelper::writeinfo('cWatch Product List');
$cwatch_product_list_request = new cwatch_product_list_request();
$cwatch_product_list_request->ProductCode = '';
messagehelper::writevarinfo($sslapi->cwatch_product_list($cwatch_product_list_request));
?>