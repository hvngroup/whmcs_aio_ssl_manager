<?php
require_once('api_settings.php');

messagehelper::writeinfo('Checking SSL');
$sslRequest = new ssl_validation_request();
$sslRequest->Domainname = "www.yourdomain.com";

$sslResponse =  $sslapi->ssl_validation($sslRequest);
if($sslResponse->AuthResponse->isError)
    messagehelper::writeerror(($sslResponse->AuthResponse->Message));
else
    messagehelper::writevarinfo($sslResponse);

?>

