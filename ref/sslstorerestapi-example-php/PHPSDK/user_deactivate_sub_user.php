<?php
require_once('api_settings.php');

messagehelper::writeinfo('Deactivate Sub User');

$subUser = new user_deactivate_request();
$subUser->PartnerCode = "";
$subUser->CustomPartnerCode = "";
$subUser->AuthenticationToken = "";
$subUser->PartnerEmail = "test@test.com";
$subUser->isEnabled = true;

messagehelper::writevarinfo($sslapi->user_deactivate($subUser));

?>
