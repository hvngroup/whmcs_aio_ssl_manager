<?php
require_once('api_settings.php');

messagehelper::writeinfo('Add New User');

$newuser = new user_newuser_request();
$newuser->Email = 'test@test.com';
$newuser->Password = 'test1234';
$newuser->FirstName = 'first name';
$newuser->LastName = 'last name';
$newuser->AlternateEmail = 'test1@test.com';
$newuser->CompanyName = 'CompanyName';
$newuser->Street = '123 Any Street';
$newuser->CountryName = 'US';
$newuser->State = 'FL';
$newuser->City = 'Saint Petersburg';
$newuser->Zip = '33701';
$newuser->Phone = '123456789';
$newuser->Fax = '23456789';
$newuser->Mobile = '3214567890';
$newuser->UserType = 0;
$newuser->HearedBy = 'Someone';

messagehelper::writevarinfo($sslapi->user_newuser($newuser));






?>

