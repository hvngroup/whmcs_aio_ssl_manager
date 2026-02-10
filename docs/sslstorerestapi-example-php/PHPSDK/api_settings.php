<?php
//For more information see readme.txt
require_once('api/sslstore.php');

$partnerCode = 'YOUR PARTNER CODE';
$authToken = 'YOUR AUTH TOKEN';
$token = 'YOUR TOKEN';
$tokenID = 'YOUR TOKEN ID';
$tokenCode = 'YOUR TOKEN CODE';
$IsUsedForTokenSystem = false; //Pass 'true' when you want to use token or tokenCode & tokenID
$mode = 'API MODE'; //'TEST' or 'LIVE'

$sslapi = new sslstore($partnerCode,$authToken,$token,$tokenID,$tokenCode,$IsUsedForTokenSystem,$mode);
?>