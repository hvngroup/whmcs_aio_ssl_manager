<?php
require_once('api_settings.php');

messagehelper::writeinfo('Checking health_validate:');
$healthvalidatereq = new health_validate_request();

messagehelper::writevarinfo($sslapi->health_validate($healthvalidatereq));
?>