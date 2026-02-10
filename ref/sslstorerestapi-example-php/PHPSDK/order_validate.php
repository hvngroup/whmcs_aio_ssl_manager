<?php
require_once('api_settings.php');

messagehelper::writeinfo('Validate Order Parameters:');

$testcsr ='-----BEGIN CERTIFICATE REQUEST-----
MIIDFTCCAf0CAQAwgaYxCzAJBgNVBAYTAklOMRAwDgYDVQQIEwdndWphcmF0MRIw
EAYDVQQHEwlhaG1lZGFiYWQxDzANBgNVBAoTBmpjdHdlYjEWMBQGA1UECxMNaXQg
ZGVwYXJ0bWVudDEbMBkGA1UEAxMSd3d3LmZhc3Rlc3Rzc2wuY29tMSswKQYJKoZI
hvcNAQkBFhx5b2dlc2gucGF0ZWxAdGhlc3Nsc3RvcmUuY29tMIIBIjANBgkqhkiG
9w0BAQEFAAOCAQ8AMIIBCgKCAQEArqGNVOX9FqJFs0dXi6ObEY5tR1xzZ26aQC0T
FARgSE40uH2zHDGic3NkYW95PuWgQt0YEf1t9n2bJHKrvFPCHt9gLmeF9KG5pYcN
1ElbsAUfa6/6HE780AYvW4Yh6t+sZ74T9IDoTGUKqxB4EkfeaZVjJuJbPAED2jAp
tt2A0EhtiqRD6Y4oH7N7Ip3zaWyMcFtREyWofS5cJ+ftwIyfenNxot2m8yndzJDy
wHPBV0SGVVshnzPwOn1E7wCr9yu7Qn6n3i69a0BfEJxenadAXdxfFUKWxUwzEDll
vpSTJUkgb93VKzISFYPu+j1cLquARKV43p5RZoV3Wc/Z9Hca4wIDAQABoCkwJwYJ
KoZIhvcNAQkOMRowGDAJBgNVHRMEAjAAMAsGA1UdDwQEAwIF4DANBgkqhkiG9w0B
AQUFAAOCAQEAVjosyolcWF0DFTKOcA8vL3qNWBYDRUE9/m3Nt8NKpy6cNnfnK8ka
mmvGlXIuFH9QRiNQantgJpP59rAA9LBbRdlOqPkIcjj4e/PFq6d72qUXlC6o0m3z
Cf2sj3zUJgMNkaSJY02u61owU2xkZIT7FfOzr2cKthbqHcuLYCVSeiCXNJe//iBD
Incjl4XxnQDayJDkmA0i46LkITJwQ+JyqcYRx5Fg1Hk01z8nwhZv5jknpkKsDthK
ls08HPFr52lZZ/pacMnF6n4QxAnDFM+WIZaPSOGqFUPdcwQiCbGXWbWyOu1vMYBU
ZpDDcLDR8Lm/XQJMVW4/nyf2u9UJ5OduUw==
-----END CERTIFICATE REQUEST-----';

$validateOrder = new order_validate_request();
$validateOrder->CSR = $testcsr;
$validateOrder->ProductCode = "rapidssl";
$validateOrder->ServerCount = 1;
$validateOrder->ValidityPeriod = 12;
$validateOrder->WebServerType = 'Other';

messagehelper::writevarinfo($sslapi->order_validate($validateOrder));

?>