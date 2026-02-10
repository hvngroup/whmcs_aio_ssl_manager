<?php
require_once('api_settings.php');

messagehelper::writeinfo('Whois Checker');
$whoisRequest = new whois_request();
$whoisRequest->Domainname = "www.yourdomain.com";

$whoisResponse =  $sslapi->whois($whoisRequest);
if($whoisResponse->AuthResponse->isError)
    messagehelper::writeerror(($whoisResponse->AuthResponse->Message));
else
    messagehelper::writevarinfo($whoisResponse);
?>