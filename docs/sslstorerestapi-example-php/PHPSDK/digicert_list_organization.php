<?php
require_once('api_settings.php');

messagehelper::writeinfo('Digicert List Organization');

$digicertListOrgReq = new digicert_list_organization_request();

$digicertListOrgResp = $sslapi->digicert_list_organization($digicertListOrgReq);
messagehelper::writevarinfo($digicertListOrgResp);
?>