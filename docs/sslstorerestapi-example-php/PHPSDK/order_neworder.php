<?php
require_once('api_settings.php');

messagehelper::writeinfo('Full New Order');

$testcsr ='-----BEGIN CERTIFICATE REQUEST-----
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



//General contact to be used
$contact = new contact();
$contact->AddressLine1 = 'AddressLine1';
$contact->AddressLine2 = '';
$contact->City = 'Saint Petersburg';
$contact->Country = 'US';
$contact->Email = 'test@test.com';
$contact->Fax = '';
$contact->FirstName = 'Test First Name';
$contact->LastName = 'Test Last Name';
$contact->OrganizationName = 'XYZ Pvt. Ltd';
$contact->Phone = '123456789';
$contact->PostalCode = '33701';
$contact->Region = 'FL';
$contact->Title = 'Mr. ';

$neworder = new order_neworder_request();
$neworder->AddInstallationSupport = false;
$neworder->AdminContact = $contact;

// For multi-domain products, pass respective approver methods by comma seprated single string value. (first method is for main domain).
// Permitted values:
// COMMODO/SECTIGO - HTTP, HTTPS, DNS, specific admin email ex: admin@example.com
// DIGICERT - EMAIL, HTTP, DNS, dns_cname_token
$neworder->ApproverEmail = 'admin@domain.com';
$neworder->CSR = $testcsr;
$neworder->CustomOrderID = uniqid('FullOrder-');
$neworder->DNSNames = array ('2.domain.com'); // For multi-domain products, add comma seprated SAN values.
$neworder->EmailLanguageCode = 'EN';
$neworder->ExtraProductCodes = '';
$neworder->OrganizationInfo->DUNS='';
$neworder->OrganizationInfo->Division='';
$neworder->OrganizationInfo->IncorporatingAgency='';
$neworder->OrganizationInfo->JurisdictionCity = $contact->City;
$neworder->OrganizationInfo->JurisdictionCountry = $contact->Country;
$neworder->OrganizationInfo->JurisdictionRegion = $contact->Region;
$neworder->OrganizationInfo->OrganizationName = $contact->OrganizationName;
$neworder->OrganizationInfo->RegistrationNumber = '';
$neworder->OrganizationInfo->OrganizationAddress->AddressLine1 = $contact->AddressLine1;
$neworder->OrganizationInfo->OrganizationAddress->AddressLine2 = $contact->AddressLine2;
$neworder->OrganizationInfo->OrganizationAddress->AddressLine3 = '';
$neworder->OrganizationInfo->OrganizationAddress->City = $contact->City;
$neworder->OrganizationInfo->OrganizationAddress->Country = $contact->Country;
$neworder->OrganizationInfo->OrganizationAddress->Fax = $contact->Fax;
$neworder->OrganizationInfo->OrganizationAddress->LocalityName = '';
$neworder->OrganizationInfo->OrganizationAddress->Phone=$contact->Phone;
$neworder->OrganizationInfo->OrganizationAddress->PostalCode=$contact->PostalCode;
$neworder->OrganizationInfo->OrganizationAddress->Region=$contact->Region;
$neworder->ProductCode = 'positivessl';
$neworder->ReserveSANCount = 4;
$neworder->ServerCount = 1;
$neworder->SpecialInstructions = '';
$neworder->TechnicalContact = $contact;
$neworder->ValidityPeriod = 12; //number of months
$neworder->WebServerType = 'Other';
$neworder->isCUOrder = false;
$neworder->isRenewalOrder = true;
$neworder->isTrialOrder = false;
$neworder->RelatedTheSSLStoreOrderID = '';
$neworder->FileAuthDVIndicator = true; //USED For DV File Authentication. Only for Symantec/Comodo Domain Vetted Products. You need to pass value "true".
$neworder->CNAMEAuthDVIndicator = false;
$neworder->HTTPSFileAuthDVIndicator = false;
$neworder->CSRUniqueValue = '';

// Possible value for Symantec Products 1) SHA2-256 and 2) SHA1 (Preferable is SHA2-256)
// Possible value for Comodo Products 1) NO_PREFERENCE 2) INFER_FROM_CSR 3) PREFER_SHA2 4) PREFER_SHA1 5) REQUIRE_SHA2 (Preferable is PREFER_SHA2)
$neworder->SignatureHashAlgorithm = 'SHA2-256';
$neworder->CertTransparencyIndicator = true;
$neworder->RenewalDays = 0;
$neworder->TSSOrganizationId = 0; // Used for Digicert products, If Organization prevously registered then just pass TheSSLStore’s organization id.
$neworder->DateTimeCulture = 'en-US'; // returns all dates in DateTime Culture.
$neworder->CSRUniqueValue = ''; // Only for Comodo certificates. 20 characters long Alphanumeric value.
$neworder->WildcardReserveSANCount = 0; // for flex products only, wildcard SANs count
messagehelper::writevarinfo($neworderresponse = $sslapi->order_neworder($neworder));



?>