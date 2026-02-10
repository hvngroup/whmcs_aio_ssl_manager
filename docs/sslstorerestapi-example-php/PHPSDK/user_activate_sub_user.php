<?php
require_once('api_settings.php');

messagehelper::writeinfo('Activate Sub User');

$subUser = new user_activate_request();
$subUser->PartnerCode = "";
$subUser->CustomPartnerCode = "";
$subUser->AuthenticationToken = "";
$subUser->PartnerEmail = "test@test.com";
$subUser->isEnabled = true;

messagehelper::writevarinfo($sslapi->user_activate($subUser));

?>