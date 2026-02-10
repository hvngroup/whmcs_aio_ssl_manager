<?php
require_once('api_settings.php');

messagehelper::writeinfo('Download Certificate');

$downloadreq = new order_download_request();
$downloadreq->TheSSLStoreOrderID = '';
$downloadreq->ReturnPKCS7Cert = false;
$downloadresp = $sslapi->order_download($downloadreq);
messagehelper::writevarinfo($downloadresp);

?>