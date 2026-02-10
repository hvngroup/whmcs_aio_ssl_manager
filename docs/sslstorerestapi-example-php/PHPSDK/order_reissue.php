<?php

require_once('api_settings.php');

messagehelper::writeinfo('Order Reissue');


$testcsr = '-----BEGIN CERTIFICATE REQUEST-----
MIIC/jCCAeYCAQAwgY8xCzAJBgNVBAYTAlZJMQswCQYDVQQIEwJGTDEZMBcGA1UE
BxMQU2FpbnQgUGV0ZXJzYnVyZzEVMBMGA1UEChMMWFlaIFB2dC4gTHRkMQswCQYD
VQQLEwJpdDETMBEGA1UEAxMKZG9tYWluLmNvbTEfMB0GCSqGSIb3DQEJARYQYWRt
aW5AZG9tYWluLmNvbTCCASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEBAMx4
D55XQmtaALWQtxUo44VZ5CmgSVzUVAk6sORWba5m71Q43ia5XPcURgZbmfnbVhrZ
vRRJ49xK9yYf+dwz0hmwLMU0xuyUsY/Qcwbh48tF3v1kJplfAfIqRGHZC3vHik2F
syDHolOc5HZLufLSMkR4ObYz4Y5I2GYF9fKI/DYeDZTXt4PPblSEkAR4/5/GVZYs
CrpltolybqtCDmpODncrE+c4fXSo/PL06YlGfY7IU2gOGIAnOdIwuty8uxUGcYxy
qcvege3YWnV6i22O80okThKK5S6z6UqMlTIQiby6OBKstosGRgYIwxL3fr5eNcyO
PDP4JRw0qqj8+RHAzCsCAwEAAaApMCcGCSqGSIb3DQEJDjEaMBgwCQYDVR0TBAIw
ADALBgNVHQ8EBAMCBeAwDQYJKoZIhvcNAQELBQADggEBACIkt4vYGHkHF8bNrnVD
lRyrIGpW0YNT82pNVJZJl5a1H72Y/qqf31Ht7JVIBbH/pLDt9sdubiIHME0A97hJ
F2XqFjMRUubGtQEQGJzKOsEVT3+3DNI4eRbiCchUnzBEL9rJxl9QVw0soQad/Ygi
GJ6mTx/zPMslZgPYg+Ec9zAyqzh+fD6fUgREF/tja9rdlW8/jadqHq1Eu/4OMpjE
svto1Rm5blyb8gcanEPxOiKbOMF/DjoNnhp/DLevi40s0mEtT/3oXQIKDpVTUeYM
/aqWYewC2rZyAvuGX706wi3l/ozCEhYtuEnIlM8lfWsa5TJZJPZ1pv0cDwpnqKpO
Orw=
-----END CERTIFICATE REQUEST-----';

$AddSANoldNewPair = new oldNewPair();
$AddSANoldNewPair->OldValue = '';
$AddSANoldNewPair->NewValue = '3.domain.com';

$AddSANoldNewPair1 = new oldNewPair();
$AddSANoldNewPair1->OldValue = '';
$AddSANoldNewPair1->NewValue = '4.domain.com';

$EditSANoldNewPair = new oldNewPair();
$EditSANoldNewPair->OldValue = '';
$EditSANoldNewPair->NewValue = '';

$DeleteSANoldNewPair = new oldNewPair();
$DeleteSANoldNewPair->OldValue = '';
$DeleteSANoldNewPair->NewValue = '';

$orderreissuereq = new order_reissue_request();


$orderreissuereq->CSR = $testcsr;
$orderreissuereq->TheSSLStoreOrderID = '';
$orderreissuereq->WebServerType = 'Other';
$orderreissuereq->PreferEnrollmentLink = false;
$orderreissuereq->SpecialInstructions = '';
$orderreissuereq->AddSAN = array ($AddSANoldNewPair,$AddSANoldNewPair1) ;
$orderreissuereq->EditSAN = array ($EditSANoldNewPair) ;
$orderreissuereq->DeleteSAN = array ($DeleteSANoldNewPair) ;
$orderreissuereq->isWildCard = true;
$orderreissuereq->ReissueEmail = 'admin@test.com'; //This email must be same as Admin contact email which is passed in new order.
$orderreissuereq->ApproverEmails = 'admin@domain.com';
$orderreissuereq->FileAuthDVIndicator = true;  //USED For DV File Authentication. Only for Symantec/Comodo Domain Vetted Products. You need to pass value "true".
$orderreissuereq->HTTPSFileAuthDVIndicator = false;
$orderreissuereq->CNAMEAuthDVIndicator = false;
$orderreissuereq->CSRUniqueValue = '';

// Possible value for Symantec Products 1) SHA2-256 and 2) SHA1 (Preferable is SHA2-256)
// Possible value for Comodo Products 1) NO_PREFERENCE 2) INFER_FROM_CSR 3) PREFER_SHA2 4) PREFER_SHA1 5) REQUIRE_SHA2 (Preferable is PREFER_SHA2)
$orderreissuereq->SignatureHashAlgorithm = 'SHA2-256';
messagehelper::writevarinfo($sslapi->order_reissue($orderreissuereq));

?>