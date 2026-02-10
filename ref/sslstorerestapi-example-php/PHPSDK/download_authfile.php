<?php
require_once('api_settings.php');

$orderstatusrequest = new order_status_request();
$orderstatusrequest->TheSSLStoreOrderID= '';

$orderstatusresponse=$sslapi->order_status($orderstatusrequest);

$filename=$orderstatusresponse->AuthFileName;
$content=$orderstatusresponse->AuthFileContent;

if(!$filename && !$content)
{
	echo "Download Failed";
}
else
{
$handle = fopen($filename, 'w');
fwrite($handle, $content);
fclose($handle);

header("Cache-Control: public");
header("Content-Description: File Transfer");
header("Content-Length: ". filesize("$filename").";");
header("Content-Disposition: attachment; filename=$filename");
header("Content-Type: application/octet-stream; "); 
header("Content-Transfer-Encoding: binary");

readfile($filename);
}
?>