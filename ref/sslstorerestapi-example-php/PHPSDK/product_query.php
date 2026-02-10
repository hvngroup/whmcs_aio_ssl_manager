<?php
require_once('api_settings.php');

messagehelper::writeinfo('Query Products');

$productqueryreq = new product_query_request();
//$productqueryreq->ProductCode = 'rapidssl';
$productqueryreq->ProductType = '0';
$productqueryreq->NeedSortedList = true;
$productqueryreq->IsForceNewSKUs = true;
messagehelper::writevarinfo($sslapi->product_query($productqueryreq));
?>