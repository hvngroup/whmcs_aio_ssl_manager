<?php
require_once('api_settings.php');

messagehelper::writeinfo('Email Setting Template:');

$settingTemplate = new setting_settemplate_request();
$settingTemplate->EmailSubject = "Test Subject";
$settingTemplate->EmailMessage = "This is test message";
$settingTemplate->isDisabled=true;
$settingTemplate->EmailTemplateTypes = 0;

messagehelper::writevarinfo($sslapi->setting_settemplate($settingTemplate));


?>

