<?php
require_once('api_settings.php');

messagehelper::writeinfo('Query Sub User');

$subUser = new user_query_request();
$subUser->SubUserID = "";
$subUser->StartDate = '07/25/2014'; //MM/DD/YYYY
$subUser->EndDate = '07/26/2014';   //MM/DD/YYYY

messagehelper::writevarinfo($sslapi->user_query($subUser));



?>