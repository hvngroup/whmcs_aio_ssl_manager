<?php
require_once('api_settings.php');

messagehelper::writeinfo('Validate Token:');
$healthvalidatetokenreq = new health_validate_token_request();
$healthvalidatetokenreq->Token = '';
$healthvalidatetokenreq->TokenCode = '';
$healthvalidatetokenreq->TokenID = '';
messagehelper::writevarinfo($sslapi->health_validate_token($healthvalidatetokenreq));
?>