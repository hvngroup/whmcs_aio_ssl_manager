<?php
require_once('api_settings.php');

messagehelper::writeinfo('Digicert Organization Info');

$digicertOrgInfoReq = new digicert_organization_info_request();
$digicertOrgInfoReq->TSSOrganizationId = '12345'; // TheSSLStore’s organization id.

$digicertOrgInfoResp = $sslapi->digicert_organization_info($digicertOrgInfoReq);
messagehelper::writevarinfo($digicertOrgInfoResp);
?>