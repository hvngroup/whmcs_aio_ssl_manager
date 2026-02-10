<?php
require_once('api_settings.php');


messagehelper::writeinfo('Checking Service Status:');
messagehelper::writevarinfo($sslapi->health_status());


?>