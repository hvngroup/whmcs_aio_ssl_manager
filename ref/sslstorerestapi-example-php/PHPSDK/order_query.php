<?php

require_once('api_settings.php');

messagehelper::writeinfo('Query order');

$orderqueryrequest = new order_query_request();
$StartDate1 = strtotime("08/18/2014 00:00:00 GMT"); //return timestamp in second
$StartDate = $StartDate1*1000; //convert into millisecond
$orderqueryrequest->StartDate = "/Date($StartDate)/"; 
$EndDate1 = strtotime("10/27/2014 00:00:00 GMT");
$EndDate = $EndDate1*1000;
$orderqueryrequest->EndDate = "/Date($EndDate)/";

//Return certificate list of expiry date between "CertificateExpireFromDate" and "CertificateExpireToDate"
$orderqueryrequest->CertificateExpireFromDate = "/Date($StartDate)/"; //This is certificate expiry date starting from
$orderqueryrequest->CertificateExpireToDate = "/Date($EndDate)/"; //This is certificate expiry date ending to

$orderqueryrequest->DomainName = ''; //if you passed domain name then return list related to this domain name.
$orderqueryrequest->SubUserID = '';
$orderqueryrequest->ProductCode = 'rapidssl';
$orderqueryrequest->PageNumber = 1;
$orderqueryrequest->PageSize = 10;
messagehelper::writevarinfo($sslapi->order_query($orderqueryrequest));

?>