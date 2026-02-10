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

                        {if $subItem.GlobalSignOrderID != ''}
                            <tr><th>GlobalSign Order ID</th><td>{$subItem.GlobalSignOrderID}</td></tr>
                            <tr><th>Domain Name</th><td>{$subItem.DomainName}</td></tr>
                            <tr><th>Start Date</th><td>{$subItem.StartDate}</td></tr>
                            <tr><th>End Date</th><td>{$subItem.EndDate}</td></tr>
                            <tr><th>Order Status</th><td>{$subItem.OrderStatus}</td></tr>
                            <tr><th>Validity Period</th><td>{$subItem.ValidityPeriod}</td></tr>
                            <tr><th>Approver Email</th><td>{$subItem.ApproverEmail}</td></tr>
                            <tr>
                                <th>Contact Details</th>
                                <td>
                                    First Name : {$subItem.ContactDetail->FirstName} <br>
                                    Last Name : {$subItem.ContactDetail->LastName} <br>
                                    Email : {$subItem.ContactDetail->Email} <br>
                                    Phone : {$subItem.ContactDetail->PhoneNo}
                                </td>
                            </tr>
                        {/if}            
                    </table>
                </div>
            </div>
        {/foreach}
    </div>
{/if}