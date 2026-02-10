<?php
/*
 * @ https://EasyToYou.eu - IonCube v11 Decoder Online
 * @ PHP 7.1
 * @ Decoder version: 1.1.6
 * @ Release: 10/08/2022
 */

// Decoded file for php version 71.
class SSL2BuyApi
{
    private static $instance;
    private static $testUrl = "https://demo-api.ssl2buy.com/";
    private static $apiUrl = "https://api.ssl2buy.com/";
    private static $method;
    private static $apiData = "";
    private function __construct($data, $testMode)
    {
        if ($testMode) {
            self::$apiUrl = self::$testUrl;
        }
        if (!empty($data)) {
            self::$apiData = json_encode($data);
        }
    }
    public static function getInstance($data = [], $testMode = false)
    {
        self::$instance = new self($data, $testMode);
        return self::$instance;
    }
    public static function getProductPrice()
    {
        self::$method = "orderservice/order/getproductprice";
        return self::callApi();
    }
    public static function placeOrder()
    {
        self::$method = "orderservice/order/placeorder";
        return self::callApi();
    }
    private static function callApi()
    {
        $result = file_get_contents(self::$apiUrl . self::$method, NULL, stream_context_create(["http" => ["method" => "POST", "header" => "Content-Type: application/json\r\nContent-Length: " . strlen(self::$apiData) . "\r\n", "content" => self::$apiData]]));
        $result = json_decode($result);
        if (!empty($result->Errors)) {
            $error = $result->Errors;
        }
        return $result;
    }
    public static function orderDetails($brandName)
    {
        $brandNameLower = strtolower($brandName);
        self::$method = "queryservice/" . $brandNameLower . "/GetOrderDetails";
        return self::callApi();
    }
    public static function resendApprovalMail($brandName)
    {
        $brandNameLower = strtolower($brandName);
        self::$method = "queryservice/" . $brandNameLower . "/resendapprovalemail";
        return self::callApi();
    }
    public static function orderSubscriptionHistory()
    {
        self::$method = "orderservice/order/getsubscriptionordershistory";
        return self::callApi();
    }
    public static function getAcmeOrderDetail()
    {
        self::$method = "queryservice/acme/GetAcmeOrderDetail";
        return self::callApi();
    }
    public static function acmeAdditionalPurchase()
    {
        self::$method = "queryservice/acme/PurchaseAdditionalDomain";
        return self::callApi();
    }
    public static function orderSubscriptionDetail($brandName)
    {
        if (strtolower($brandName) == "globalsign") {
            self::$method = "queryservice/globalsign/globalsignsubscriptionorderdetail";
        } else if (strtolower($brandName) == "comodo") {
            self::$method = "queryservice/comodo/comodosubscriptionorderdetail";
        } else if (strtolower($brandName) == "symantec") {
            self::$method = "queryservice/symantec/digicertsubscriptionorderdetail";
        } else if (strtolower($brandName) == "prime") {
            self::$method = "queryservice/prime/primesubscriptionorderdetail";
        }
        return self::callApi();
    }
    public static function GlobalSingOrderDetails($orderSubscriptionDetail)
    {
        $returnString = "";
        if ($orderSubscriptionDetail->GlobalSignOrderNumber != "") {
            $returnString .= "<tr><th>GlobalSign Order ID</th><td>" . $orderSubscriptionDetail->GlobalSignOrderNumber . "</td></tr>";
            $returnString .= "<tr><th>Domain Name</th><td>" . $orderSubscriptionDetail->DomainName . "</td></tr>";
            $returnString .= "<tr><th>Start Date</th><td>" . date("Y-m-d", strtotime($orderSubscriptionDetail->StartDate)) . "</td></tr>";
            $returnString .= "<tr><th>End Date</th><td>" . date("Y-m-d", strtotime($orderSubscriptionDetail->EndDate)) . "</td></tr>";
            $returnString .= "<tr><th>Order Status</th><td>" . $orderSubscriptionDetail->OrderStatus . "</td></tr>";
            $returnString .= "<tr><th>Validity Period</th><td>" . $orderSubscriptionDetail->ValidityPeriod . "</td></tr>";
            $returnString .= "<tr><th>Approver Email</th><td>" . $orderSubscriptionDetail->ApprovalEmail . "</td></tr>";
            $returnString .= "<tr>";
            $returnString .= "<th>Contact Details</th>";
            $returnString .= "<td>\r\n                    First Name : " . $orderSubscriptionDetail->ContactDetails->FirstName . " <br>\r\n                    Last Name : " . $orderSubscriptionDetail->ContactDetails->LastName . " <br>\r\n                    Email : " . $orderSubscriptionDetail->ContactDetails->Email . " <br>\r\n                    Phone : " . $orderSubscriptionDetail->ContactDetails->PhoneNo . "\r\n                </td>";
            $returnString .= "</tr>";
        }
        return $returnString;
    }
    public static function ComodoOrderDetails($orderSubscriptionDetail, $productId = 0)
    {
        $returnString = "";
        if ($orderSubscriptionDetail->ComodoOrderNumber != "") {
            $returnString .= "<tr><th>Comodo Order ID</th><td>" . $orderSubscriptionDetail->ComodoOrderNumber . "</td></tr>";
            $returnString .= "<tr><th>Order Status</th><td>" . $orderSubscriptionDetail->OrderStatus . "</td></tr>";
            if ($productId == 321 || $productId == 322 || $productId == 373) {
                $returnString .= "<tr><th>Title</th><td>" . $orderSubscriptionDetail->ComodoOrderDetail->Title . "</td></tr>";
                $returnString .= "<tr><th>First Name</th><td>" . $orderSubscriptionDetail->ComodoOrderDetail->FirstName . "</td></tr>";
                $returnString .= "<tr><th>Last Name</th><td>" . $orderSubscriptionDetail->ComodoOrderDetail->LastName . "</td></tr>";
                $returnString .= "<tr><th>Email</th><td>" . $orderSubscriptionDetail->ComodoOrderDetail->Email . "</td></tr>";
                if ($productId == 322 || $productId == 373) {
                    $returnString .= "<tr>";
                    $returnString .= "<th>Organization Details</th>";
                    $returnString .= "<td>\r\n                            Name : " . $orderSubscriptionDetail->ComodoOrderDetail->OrganizationName . " <br>\r\n                            Address1 : " . $orderSubscriptionDetail->ComodoOrderDetail->Address1 . " <br>\r\n                            Address2 : " . $orderSubscriptionDetail->ComodoOrderDetail->Address2 . " <br>\r\n                            City : " . $orderSubscriptionDetail->ComodoOrderDetail->City . " <br>\r\n                            State : " . $orderSubscriptionDetail->ComodoOrderDetail->State . " <br>\r\n                            PostalCode : " . $orderSubscriptionDetail->ComodoOrderDetail->PostalCode . " <br>\r\n                            Country : " . $orderSubscriptionDetail->ComodoOrderDetail->Country . " <br>\r\n                            Phone : " . $orderSubscriptionDetail->ComodoOrderDetail->Phone . " <br>\r\n                            Email : " . $orderSubscriptionDetail->ComodoOrderDetail->OrganizationEmail . "\r\n                        </td>";
                    $returnString .= "</tr>";
                }
            } else {
                $returnString .= "<tr><th>Start Date</th><td>" . date("Y-m-d", strtotime($orderSubscriptionDetail->StartDate)) . "</td></tr>";
                $returnString .= "<tr><th>End Date</th><td>" . date("Y-m-d", strtotime($orderSubscriptionDetail->EndDate)) . "</td></tr>";
                $returnString .= "<tr><th>Validity Period</th><td>" . $orderSubscriptionDetail->ValidityPeriod . "</td></tr>";
                $returnString .= "<tr><th>Web Server</th><td>" . $orderSubscriptionDetail->ComodoOrderDetail->WebServer . "</td></tr>";
                $returnString .= "<tr><th>Organization Name</th><td>" . $orderSubscriptionDetail->ComodoOrderDetail->OrganizationName . "</td></tr>";
                $returnString .= "<tr><th>Address1</th><td>" . $orderSubscriptionDetail->ComodoOrderDetail->Address1 . "</td></tr>";
                $returnString .= "<tr><th>Address2</th><td>" . $orderSubscriptionDetail->ComodoOrderDetail->Address2 . "</td></tr>";
                $returnString .= "<tr><th>Address3</th><td>" . $orderSubscriptionDetail->ComodoOrderDetail->Address3 . "</td></tr>";
                $returnString .= "<tr><th>City</th><td>" . $orderSubscriptionDetail->ComodoOrderDetail->City . "</td></tr>";
                $returnString .= "<tr><th>State</th><td>" . $orderSubscriptionDetail->ComodoOrderDetail->State . "</td></tr>";
                $returnString .= "<tr><th>Country</th><td>" . $orderSubscriptionDetail->ComodoOrderDetail->Country . "</td></tr>";
                $returnString .= "<tr><th>Postal Code</th><td>" . $orderSubscriptionDetail->ComodoOrderDetail->PostalCode . "</td></tr>";
                $returnString .= "<tr><th>Organization Email</th><td>" . $orderSubscriptionDetail->ComodoOrderDetail->OrganizationEmail . "</td></tr>";
                $returnString .= "<tr><th>Approver Email</th><td>" . $orderSubscriptionDetail->ComodoOrderDetail->ApprovalEmail . "</td></tr>";
                $returnString .= "<tr>";
                $returnString .= "<th>CSR Details</th>";
                $returnString .= "<td>\r\n                        Domain Name : " . $orderSubscriptionDetail->CSRDetail->DomainName . " <br>\r\n                        Organisation : " . $orderSubscriptionDetail->CSRDetail->Organisation . " <br>\r\n                        Organisation Unit : " . $orderSubscriptionDetail->CSRDetail->OrganisationUnit . " <br>\r\n                        Locality : " . $orderSubscriptionDetail->CSRDetail->Locality . " <br>\r\n                        State : " . $orderSubscriptionDetail->CSRDetail->State . " <br>\r\n                        Country : " . $orderSubscriptionDetail->CSRDetail->Country . " \r\n                    </td>";
                $returnString .= "</tr>";
                $addDomainList = "";
                if (!empty($orderSubscriptionDetail->AdditionalDomainList)) {
                    foreach ($orderSubscriptionDetail->AdditionalDomainList as $domainList) {
                        $addDomainList .= $domainList->DomainName . "</br>";
                    }
                }
                $returnString .= "<tr>";
                $returnString .= "<th>Additional Domain List</th>";
                $returnString .= "<td>" . $addDomainList . "</td>";
                $returnString .= "</tr>";
            }
        }
        return $returnString;
    }
    public static function SymantecOrderDetails($orderSubscriptionDetail)
    {
        $returnString = "";
        if ($orderSubscriptionDetail->DigicertOrderNumber != "") {
            $returnString .= "<tr><th>Digicert Order ID</th><td>" . $orderSubscriptionDetail->DigicertOrderNumber . "</td></tr>";
            $returnString .= "<tr><th>Start Date</th><td>" . date("Y-m-d", strtotime($orderSubscriptionDetail->StartDate)) . "</td></tr>";
            $returnString .= "<tr><th>End Date</th><td>" . date("Y-m-d", strtotime($orderSubscriptionDetail->EndDate)) . "</td></tr>";
            $returnString .= "<tr><th>Order Status</th><td>" . $orderSubscriptionDetail->OrderStatus . "</td></tr>";
            $returnString .= "<tr><th>Validity Period</th><td>" . $orderSubscriptionDetail->ValidityPeriod . "</td></tr>";
            $returnString .= "<tr><th>Domain Name</th><td>" . $orderSubscriptionDetail->DomainName . "</td></tr>";
            $returnString .= "<tr><th>Approver Email</th><td>" . $orderSubscriptionDetail->ApprovalEmail . "</td></tr>";
            $addDomainList = "";
            if (!empty($orderSubscriptionDetail->AdditionalDomainList)) {
                foreach ($orderSubscriptionDetail->AdditionalDomainList as $domainList) {
                    $addDomainList .= $domainList->DomainName . "</br>";
                }
            }
            $returnString .= "<tr>";
            $returnString .= "<th>Additional Domain List</th>";
            $returnString .= "<td>" . $addDomainList . "</td>";
            $returnString .= "</tr>";
            $returnString .= "<tr>";
            $returnString .= "<th>Admin Contact Details</th>";
            $returnString .= "<td>\r\n                    Title : " . $orderSubscriptionDetail->AdminContact->Title . " <br>\r\n                    First Name : " . $orderSubscriptionDetail->AdminContact->FirstName . " <br>\r\n                    Last Name : " . $orderSubscriptionDetail->AdminContact->LastName . " <br>\r\n                    Email : " . $orderSubscriptionDetail->AdminContact->Email . " <br>\r\n                    Phone : " . $orderSubscriptionDetail->AdminContact->Phone . "\r\n                </td>";
            $returnString .= "</tr>";
            $returnString .= "<tr>";
            $returnString .= "<th>Technical Contact Details</th>";
            $returnString .= "<td>\r\n                    Title : " . $orderSubscriptionDetail->TechnicalContact->Title . " <br>\r\n                    First Name : " . $orderSubscriptionDetail->TechnicalContact->FirstName . " <br>\r\n                    Last Name : " . $orderSubscriptionDetail->TechnicalContact->LastName . " <br>\r\n                    Email : " . $orderSubscriptionDetail->TechnicalContact->Email . " <br>\r\n                    Phone : " . $orderSubscriptionDetail->TechnicalContact->Phone . "\r\n                </td>";
            $returnString .= "</tr>";
            $returnString .= "<tr>";
            $returnString .= "<th>Organisation Details</th>";
            $returnString .= "<td>\r\n                    Assumed Name : " . $orderSubscriptionDetail->OrganizationDetail->AssumedName . " <br>\r\n                    Legal Name : " . $orderSubscriptionDetail->OrganizationDetail->LegalName . " <br>\r\n                    Division : " . $orderSubscriptionDetail->OrganizationDetail->Division . " <br>\r\n                    DUNS : " . $orderSubscriptionDetail->OrganizationDetail->DUNS . " <br>\r\n                    Address1 : " . $orderSubscriptionDetail->OrganizationDetail->Address1 . " <br>\r\n                    Address2 : " . $orderSubscriptionDetail->OrganizationDetail->Address2 . " <br>\r\n                    City : " . $orderSubscriptionDetail->OrganizationDetail->City . " <br>\r\n                    State : " . $orderSubscriptionDetail->OrganizationDetail->State . " <br>\r\n                    Country : " . $orderSubscriptionDetail->OrganizationDetail->Country . " <br>\r\n                    Phone : " . $orderSubscriptionDetail->OrganizationDetail->Phone . " <br>\r\n                    FAX : " . $orderSubscriptionDetail->OrganizationDetail->FAX . " <br>\r\n                    Postal Code : " . $orderSubscriptionDetail->OrganizationDetail->PostalCode . "\r\n                </td>";
            $returnString .= "</tr>";
            $returnString .= "<tr>";
            $returnString .= "<th>CSR Details</th>";
            $returnString .= "<td>\r\n                    Domain Name : " . $orderSubscriptionDetail->CSRDetail->DomainName . " <br>\r\n                    Organisation : " . $orderSubscriptionDetail->CSRDetail->Organisation . " <br>\r\n                    Organisation Unit : " . $orderSubscriptionDetail->CSRDetail->OrganisationUnit . " <br>\r\n                    Locality : " . $orderSubscriptionDetail->CSRDetail->Locality . " <br>\r\n                    State : " . $orderSubscriptionDetail->CSRDetail->State . " <br>\r\n                    Country : " . $orderSubscriptionDetail->CSRDetail->Country . " \r\n                </td>";
            $returnString .= "</tr>";
        }
        return $returnString;
    }
    public static function PrimeSSLOrderDetails($orderSubscriptionDetail)
    {
        $returnString = "";
        if ($orderSubscriptionDetail->PrimeSSLOrderNumber != "") {
            $returnString .= "<tr><th>PrimeSSL Order ID</th><td>" . $orderSubscriptionDetail->PrimeSSLOrderNumber . "</td></tr>";
            $returnString .= "<tr><th>Start Date</th><td>" . date("Y-m-d", strtotime($orderSubscriptionDetail->StartDate)) . "</td></tr>";
            $returnString .= "<tr><th>End Date</th><td>" . date("Y-m-d", strtotime($orderSubscriptionDetail->EndDate)) . "</td></tr>";
            $returnString .= "<tr><th>Order Status</th><td>" . $orderSubscriptionDetail->OrderStatus . "</td></tr>";
            $returnString .= "<tr><th>Validity Period</th><td>" . $orderSubscriptionDetail->ValidityPeriod . "</td></tr>";
            $returnString .= "<tr><th>Web Server</th><td>" . $orderSubscriptionDetail->PrimeOrderDetail->WebServer . "</td></tr>";
            $returnString .= "<tr><th>Organization Name</th><td>" . $orderSubscriptionDetail->PrimeOrderDetail->OrganizationName . "</td></tr>";
            $returnString .= "<tr><th>Address1</th><td>" . $orderSubscriptionDetail->PrimeOrderDetail->Address1 . "</td></tr>";
            $returnString .= "<tr><th>Address2</th><td>" . $orderSubscriptionDetail->PrimeOrderDetail->Address2 . "</td></tr>";
            $returnString .= "<tr><th>Address3</th><td>" . $orderSubscriptionDetail->PrimeOrderDetail->Address3 . "</td></tr>";
            $returnString .= "<tr><th>City</th><td>" . $orderSubscriptionDetail->PrimeOrderDetail->City . "</td></tr>";
            $returnString .= "<tr><th>State</th><td>" . $orderSubscriptionDetail->PrimeOrderDetail->State . "</td></tr>";
            $returnString .= "<tr><th>Country</th><td>" . $orderSubscriptionDetail->PrimeOrderDetail->Country . "</td></tr>";
            $returnString .= "<tr><th>Postal Code</th><td>" . $orderSubscriptionDetail->PrimeOrderDetail->PostalCode . "</td></tr>";
            $returnString .= "<tr><th>Organization Email</th><td>" . $orderSubscriptionDetail->PrimeOrderDetail->OrganizationEmail . "</td></tr>";
            $returnString .= "<tr><th>Approver Email</th><td>" . $orderSubscriptionDetail->PrimeOrderDetail->ApprovalEmail . "</td></tr>";
            $returnString .= "<tr>";
            $returnString .= "<th>CSR Details</th>";
            $returnString .= "<td>\r\n                    Domain Name : " . $orderSubscriptionDetail->CSRDetail->DomainName . " <br>\r\n                    Organisation : " . $orderSubscriptionDetail->CSRDetail->Organisation . " <br>\r\n                    Organisation Unit : " . $orderSubscriptionDetail->CSRDetail->OrganisationUnit . " <br>\r\n                    Locality : " . $orderSubscriptionDetail->CSRDetail->Locality . " <br>\r\n                    State : " . $orderSubscriptionDetail->CSRDetail->State . " <br>\r\n                    Country : " . $orderSubscriptionDetail->CSRDetail->Country . " \r\n                </td>";
            $returnString .= "</tr>";
            $addDomainList = "";
            if (!empty($orderSubscriptionDetail->AdditionalDomainList)) {
                foreach ($orderSubscriptionDetail->AdditionalDomainList as $domainList) {
                    $addDomainList .= $domainList->DomainName . "</br>";
                }
            }
            $returnString .= "<tr>";
            $returnString .= "<th>Additional Domain List</th>";
            $returnString .= "<td>" . $addDomainList . "</td>";
            $returnString .= "</tr>";
        }
        return $returnString;
    }
    public static function SectigoACMEOrderDetails($orderSubscriptionDetail)
    {
        $returnString = "";
        if ($orderSubscriptionDetail->EABID != "") {
            $returnString .= "<tr><th>EAB ID</th><td>" . $orderSubscriptionDetail->EABID . "</td></tr>";
            $returnString .= "<tr><th>EAB Key</th><td>" . $orderSubscriptionDetail->EABKey . "</td></tr>";
            $returnString .= "<tr><th>Server</th><td>" . $orderSubscriptionDetail->ServerUrL . "</td></tr>";
            $returnString .= "<tr><th>ACME Account details</th>";
            $returnString .= "<td>";
            $returnString .= "<table class=\"datatable\" width=\"100%\" border=\"0\" cellspacing=\"1\" cellpadding=\"3\">";
            $returnString .= "<tr>";
            $returnString .= "<th width=\"23%\">ACME ID</th>";
            $returnString .= "<th width=\"9%\">Status</th>";
            $returnString .= "<th width=\"13%\">IP Address</th>";
            $returnString .= "<th width=\"13%\">Last Activity</th>";
            $returnString .= "<th>User Agent</th>";
            $returnString .= "</tr>";
            foreach ($orderSubscriptionDetail->AcmeAccountStatus as $acmeItem) {
                $returnString .= "<tr>";
                $returnString .= "<td>" . $acmeItem->ACMEID . "</td>";
                $returnString .= "<td>" . $acmeItem->AccountStatus . "</td>";
                $returnString .= "<td>" . $acmeItem->IpAddress . "</td>";
                $returnString .= "<td>" . date("d F Y", strtotime($acmeItem->LastActivity)) . "</td>";
                $returnString .= "<td>" . $acmeItem->UserAgent . "</td>";
                $returnString .= "</tr>";
            }
            $returnString .= "</table>";
            $returnString .= "</td>";
            $returnString .= "</tr>";
            $returnString .= "<tr><th>Domain History</th>";
            $returnString .= "<td>";
            $returnString .= "<table class=\"datatable\" width=\"100%\" border=\"0\" cellspacing=\"1\" cellpadding=\"3\">";
            $returnString .= "<tr>";
            $returnString .= "<th>No.</th>";
            $returnString .= "<th>DomainName</th>";
            $returnString .= "<th>Date</th>";
            $returnString .= "<th>Type</th>";
            $returnString .= "</tr>";
            if (!empty($orderSubscriptionDetail->Domains)) {
                foreach ($orderSubscriptionDetail->Domains as $key => $domainItem) {
                    $returnString .= "<tr>";
                    $returnString .= "<td>" . ($key + 1) . "</td>";
                    $returnString .= "<td>" . $domainItem->DomainName . "</td>";
                    $returnString .= "<td>" . date("d F Y", strtotime($domainItem->TransactionDate)) . "</td>";
                    $returnString .= "<td>" . $domainItem->DomainAction . "</td>";
                    $returnString .= "</tr>";
                }
            } else {
                $returnString .= "<tr><td colspan=\"5\">No record found</td></tr>";
            }
            $returnString .= "</table>";
            $returnString .= "</td>";
            $returnString .= "</tr>";
        }
        return $returnString;
    }
}

?>