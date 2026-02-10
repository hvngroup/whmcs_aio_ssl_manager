<?php
require_once('api_settings.php');

messagehelper::writeinfo('User Account Detail:');

$account_detail_req = new user_account_detail_request();

messagehelper::writevarinfo($sslapi->user_account_detail($account_detail_req));
?>