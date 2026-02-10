<?php
require_once('api_settings.php');

messagehelper::writeinfo('Set Cancel Notification:');

$cancelNotification = new setting_cancelnotification_request();
$cancelNotification->url = "www.test.com";

messagehelper::writevarinfo($sslapi->setting_setcancelnotification($cancelNotification));

?>
