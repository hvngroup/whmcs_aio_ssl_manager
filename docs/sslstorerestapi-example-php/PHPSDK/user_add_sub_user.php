<?php
require_once('api_settings.php');

messagehelper::writeinfo('Add Sub User');

$subUser = new user_add_request();
$subUser->PartnerCode = "";
$subUser->CustomPartnerCode = "";
$subUser->AuthenticationToken = "";
$subUser->PartnerEmail = "test@test.com";
$subUser->isEnabled = "";

messagehelper::writevarinfo($sslapi->user_add($subUser));

?>
