<?php
require_once('api_settings.php');

messagehelper::writeinfo('Download CSR');

$csr_download_req = new csr_download_request();
$csr_download_req->TheSSLStoreOrderID = '';

$csr_download_resp = $sslapi->csr_download($csr_download_req);
messagehelper::writevarinfo($csr_download_resp);

?>