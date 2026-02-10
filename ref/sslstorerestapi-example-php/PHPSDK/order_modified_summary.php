<?php
require_once('api_settings.php');

messagehelper::writeinfo('Get Modified Orders Summary');

$order_modified_summary_request = new order_modified_summary_request();

$StartDate1 = strtotime("2016-08-01 00:00:00 GMT"); //return timestamp in second mm-dd-yyyy
$StartDate = $StartDate1*1000; //convert into millisecond
$order_modified_summary_request->StartDate = "/Date($StartDate)/";

$EndDate1 = strtotime("2016-09-02 00:00:00 GMT");
$EndDate = $EndDate1*1000;
$order_modified_summary_request->EndDate = "/Date($EndDate)/";

$order_modified_summary_request->SubUserID = '';
$order_modified_summary_request->PageNumber = 1;
$order_modified_summary_request->PageSize = 100;

messagehelper::writevarinfo($sslapi->order_modified_summary($order_modified_summary_request));