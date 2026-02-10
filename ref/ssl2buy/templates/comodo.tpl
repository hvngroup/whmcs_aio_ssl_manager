{if $subscription}
    <!-- Tabs Navigation -->
    <ul class="nav nav-tabs" id="customTabs" role="tablist">
        {foreach $subscription as $index => $subItem}
            <li class="nav-item {if $index == 0} active {/if}" role="presentation">
                <a class="nav-link" id="tab1-tab" data-toggle="tab" href="#tab{$index}" role="tab">{$subItem.label}</a>
            </li>
        {/foreach}
    </ul>

    <!-- Tabs Content -->
    <div class="tab-content mt-3" id="customTabsContent">
        {foreach $subscription as $index => $subItem}
            <div class="tab-pane fade {if $index == 0} active in {/if}" id="tab{$index}" role="tabpanel">
                <div class="certDetails">
                    <h4>Subscription Details</h4>
                    <table class="table">

                        <tr><th>Configuration Link</th><td><a href="{$subItem.Pin}" target="_blank">{$subItem.Pin}</a></td></tr>
                        <tr><th>Certificate Status</th><td>{$subItem.CertificateStatus}</td></tr>

                        {if $subItem.ComodoOrderID != ''}
                            <tr><th>Comodo Order ID</th><td>{$subItem.ComodoOrderID}</td></tr>
                            <tr><th>Order Status</th><td>{$subItem.OrderStatus}</td></tr>
                            
                            {if $subItem.productId == 321 OR $subItem.productId == 322 OR $subItem.productId == 373}
                                <tr><th>Title</th><td>{$subItem.Title}</td></tr>
                                <tr><th>First Name</th><td>{$subItem.FirstName}</td></tr>
                                <tr><th>Last Name</th><td>{$subItem.LastName}</td></tr>
                                <tr><th>Email</th><td>{$subItem.Email}</td></tr>
                                {if $subItem.productId == 322 OR $subItem.productId == 373}
                                    <tr>
                                        <th>Organization Details</th>
                                        <td>
                                            Name : {$subItem.OrganizationName} <br>
                                            Address1 : {$subItem.Address1} <br>
                                            Address2 : {$subItem.Address2} <br>
                                            City : {$subItem.City} <br>
                                            State : {$subItem.State} <br>
                                            PostalCode : {$subItem.PostalCode} <br>
                                            Country : {$subItem.Country} <br> 
                                            Phone : {$subItem.Phone} <br> 
                                            Email : {$subItem.OrganizationEmail} <br> 
                                        </td>
                                    </tr>
                                {/if}
                            {else}
                                <tr><th>Start Date</th><td>{$subItem.StartDate}</td></tr>
                                <tr><th>End Date</th><td>{$subItem.EndDate}</td></tr>
                                <tr><th>Validity Period</th><td>{$subItem.ValidityPeriod}</td></tr>
                                <tr><th>Web Server</th><td>{$subItem.WebServer}</td></tr>
                                <tr><th>Organization Name</th><td>{$subItem.OrganizationName}</td></tr>
                                <tr><th>Address1</th><td>{$subItem.Address1}</td></tr>
                                <tr><th>Address2</th><td>{$subItem.Address2}</td></tr>
                                <tr><th>Address3</th><td>{$subItem.Address3}</td></tr>
                                <tr><th>City</th><td>{$subItem.City}</td></tr>
                                <tr><th>State</th><td>{$subItem.State}</td></tr>
                                <tr><th>Country</th><td>{$subItem.Country}</td></tr>
                                <tr><th>Postal Code</th><td>{$subItem.PostalCode}</td></tr>
                                <tr><th>Organization Email</th><td>{$subItem.OrganizationEmail}</td></tr>
                                <tr><th>Approver Email</th><td>{$subItem.ApprovalEmail}</td></tr>
                                <tr>
                                    <th>CSR Details</th>
                                    <td>
                                        Domain Name : {$subItem.CSRDetail.DomainName} <br>
                                        Organisation : {$subItem.CSRDetail.Organisation} <br>
                                        Organisation Unit : {$subItem.CSRDetail.OrganisationUnit} <br>
                                        Locality : {$subItem.CSRDetail.Locality} <br>
                                        State : {$subItem.CSRDetail.State} <br>
                                        Country : {$subItem.CSRDetail.Country} 
                                    </td>
                                </tr>
                                <tr>
                                    <th>Additional Domain List</th>
                                    <td>
                                        {$subItem.addDomainList}
                                    </td>
                                </tr>
                            {/if}
                        {/if}            
                    </table>
                </div>
            </div>
        {/foreach}
    </div>
{/if}