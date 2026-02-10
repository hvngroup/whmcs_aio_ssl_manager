<?php
require_once('api_settings.php');

messagehelper::writeinfo('Digicert Get Domain Info');

$digicertDomainInfoReq = new digicert_get_domain_info_request();
$digicertDomainInfoReq->TheSSLStoreOrderID = '123456';
$digicertDomainInfoReq->DomainName = 'www.example.com';

$digicertDomainInfoResp = $sslapi->digicert_get_domain_info($digicertDomainInfoReq);
messagehelper::writevarinfo($digicertDomainInfoResp);
?>