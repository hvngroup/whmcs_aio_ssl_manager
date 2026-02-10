<?php
require_once('api_settings.php');

messagehelper::writeinfo('Free ClaimFree Product');

//General contact to be used
$contact = new contact();
$contact->AddressLine1 = 'AddressLine1';
$contact->AddressLine2 = '';
$contact->City = 'Saint Petersburg';
$contact->Country = 'US';
$contact->Email = 'test@test.com';
$contact->Fax = '';
$contact->FirstName = 'first name';
$contact->LastName = 'last name';
$contact->OrganizationName = 'OrganizationName';
$contact->Phone = '123456789';
$contact->PostalCode = '33701';
$contact->Region = 'FL';
$contact->Title = 'Mr. ';

$order_neworder_request_freeproduct = new order_neworder_request_freeproduct();
$order_neworder_request_freeproduct->TechnicalContact= $contact;

$claimfreerequest = new free_claimfree_request();

$claimfreerequest->ProductCode ='comodoevssl';
$claimfreerequest->RelatedTheSSLStoreOrderID = '';
$claimfreerequest->NewOrderRequest = $order_neworder_request_freeproduct;
messagehelper::writevarinfo($claimfreeresponse = $sslapi->free_claimfree($claimfreerequest));



?>