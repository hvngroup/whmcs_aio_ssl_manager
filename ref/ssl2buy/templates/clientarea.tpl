<div id="ssl2buyContainer" class="text-left">
    {if $orderData}
        {$LANG.configurationlink}:
        <div class="alert alert-info">
            <a href="{$orderData->link}" target="_blank">{$orderData->link}</a>
        </div>

        <div class="certDetails">
            <table class="table">
                <tr><td>{$LANG.orderstatus}</td><td>{$apiOrderDetails->OrderStatus}</td></tr>

                {if $apiOrderDetails->OrderStatus != 'LINKPENDING'}

                    <tr><td>{$LANG.certstartdate}</td><td>{$apiOrderDetails->CertificateStartDate}</td></tr>
                    <tr><td>{$LANG.certenddate}</td><td>{$apiOrderDetails->CertificateEndDate}</td></tr>
                    
                    <tr><td>{$LANG.ValidityPeriod}</td><td>{$apiOrderDetails->ValidityPeriod}</td></tr>
                    <tr><td>{$LANG.csrdetails}</td><td>{$LANG.domainname} : {$apiOrderDetails->CSRDetail->DomainName} <br> {$LANG.locality} : {$apiOrderDetails->CSRDetail->Locality} <br> {$LANG.state} : {$apiOrderDetails->CSRDetail->State} <br> {$LANG.country} : {$apiOrderDetails->CSRDetail->Country} </td></tr>
                    
                    <tr><td>{$LANG.admincontact}</td><td>{$LANG.title} : {$apiOrderDetails->AdminContact->Title} <br> {$LANG.firstname} : {$apiOrderDetails->AdminContact->FirstName} <br> {$LANG.lastname} : {$apiOrderDetails->AdminContact->LastName} <br> {$LANG.email} : {$apiOrderDetails->AdminContact->Email} <br> {$LANG.phone} : {$apiOrderDetails->AdminContact->Phone}</td></tr>
                    <tr><td>{$LANG.technicalcontact}</td><td>{$LANG.title} : {$apiOrderDetails->TechnicalContact->Title} <br> {$LANG.firstname} : {$apiOrderDetails->TechnicalContact->FirstName} <br> {$LANG.lastname} : {$apiOrderDetails->TechnicalContact->LastName} <br> {$LANG.email} : {$apiOrderDetails->TechnicalContact->Email} <br> {$LANG.phone} : {$apiOrderDetails->TechnicalContact->Phone}</td></tr>

                    {if isset($BaseOrderDetails) }
                        <tr><td>{$LANG.symantecorderid}</td><td>{$SymantecOrderID}</td></tr>
                    {/if}

                    {if isset($BaseOrderDetails) }
                        <tr><td>{$LANG.approveremail}</td><td>{$ApproverEmail}</td></tr>
                    {/if}

                    {if isset($BaseOrderDetails) }

                        <tr><td>{$LANG.invoiceid}</td><td>{$BaseOrderDetails->InvoiceId}</td></tr>
                        <tr><td>{$LANG.productamount}</td><td>{$BaseOrderDetails->ProductAmount}</td></tr>
                        <tr><td>{$LANG.san}</td><td>{$BaseOrderDetails->SAN}</td></tr>
                        <tr><td>{$LANG.sanprice}</td><td>{$BaseOrderDetails->SANPrice}</td></tr>
                        <tr><td>{$LANG.paiddate}</td><td>{$BaseOrderDetails->PaidDate}</td></tr>
                        <tr><td>{$LANG.totalamount}</td><td>{$BaseOrderDetails->TotalAmount}</td></tr>
                        <tr><td>{$LANG.productname}</td><td>{$BaseOrderDetails->ProductName}</td></tr>
                        <tr><td>{$LANG.year}</td><td>{$BaseOrderDetails->Year}</td></tr>

                    {/if}

                {/if}            
            </table>
        </div>
    {/if}
</div>
