###### PHPSDK 2.25 ######
* Added the following parameter to the Product Query request:
    + IsForceNewSKUs

* Added the following parameter to the Product Query response:
    + isFlexProduct
    + PricingInfo -> PricePerWildcardSAN
    + PricingInfo -> WildcardPrice

* Added the following parameter to the Invite Order request:
    + IsWildcardCSRDomain
    + ExtraWildcardSAN
    + CSRUniqueValue
    + OrganizationIds

* Added the following parameter to the New Order and Order Midterm Upgrade request:
    + WildcardReserveSANCount
    + DateTimeCulture
    + CSRUniqueValue
-----------------------------------------------------------
###### PHPSDK 2.24 ######
* New Methods Added
    + Added Digicert List Organization method
    + Added Digicert Organization Info method
    + Added Digicert Get Domain Info method
    + Added Digicert Set Approver Method method

* Added the following field to the Invite Order Service request:
    + OrganizationIds

* Added the following field to the New Order Service request:
    + TSSOrganizationId

* Added the following field to the Order Status and Query Order response:
    + TSSOrganizationId

* Added the following fields to the Download Certificate and Download Certificate As Zip request:
    + PlatFormId
    + FormatType
-----------------------------------------------------------
###### PHPSDK 2.11 ######
* New Methods Added
    + Added cWatch Place Order method
    + Added cWatch Order Status method
    + Added cWatch Update Site method
    + Added cWatch Deactivate License method
    + Added cWatch Upgrade License method
    + Added cWatch Invite Order method
    + Added cWatch Product List method
-----------------------------------------------------------
###### PHPSDK 2.10 ######
* Added the following parameter to the New Order and Re-Issue request:
    + CSRUniqueValue

* Added the following parameter to the Validate CSR Response:
    + sha256
-----------------------------------------------------------
###### PHPSDK 2.9 ######
* New Methods Added
    + Added Get Modified Order Summary method
    + Added Order Mid Term Upgrade method

* Added the following parameters to the Query – Order request:
    + PageNumber
    + PageSize
-----------------------------------------------------------
###### PHPSDK 2.8 ######
* New Methods Added
    + Added Certificate PMR Request method
-----------------------------------------------------------
###### PHPSDK 2.7 ######
* New Methods Added
    + Added User Account Detail method
-----------------------------------------------------------
###### PHPSDK 2.6 ######
* Updated "Get Approver Email List" and "Change Approver Email List" Methods
-----------------------------------------------------------
* New Features Added
	+ Added Token system.
-----------------------------------------------------------	
* Use of SDK
Now, You can access API using three ways.
1. With PartnerCode & AuthToken.
	Pass Your PartnerCode, AuthToken and IsUsedForTokenSystem = false
	
2. With Token only.
	Pass Token and IsUsedForTokenSystem = true
	
3. With TokenCode & TokenID.
	Pass TokenCode,TokenID and IsUsedForTokenSystem = true
------------------------------------------------------------	
* Note: 
1. Token is different thing then TokenCode and TokenID.
2. If you pass IsUsedForTokenSystem=true then you must need to pass Token or TokenCode and TokenID, otherwise it throws error.
3. In Some, api calls Token system will not work (For e.g. Invite Order etc.), For that you need to pass PartnerCode and AuthToken.
