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
                        <tr><th width="25%">Configuration Link</th><td style="word-break: break-all;"><a href="{$subItem.Pin}" target="_blank">{$subItem.Pin}</a></td></tr>
                        <tr><th>Certificate Status</th><td>{$subItem.CertificateStatus}</td></tr>
                        
                        {if $subItem.acmeOrderDetail->EABID != ''}
                            <tr><th>EAB ID</th><td>{$subItem.acmeOrderDetail->EABID}</td></tr>
                            <tr><th>EAB Key</th><td style="word-break: break-all;">{$subItem.acmeOrderDetail->EABKey}</td></tr>
                            <tr><th>Server</th><td>{$subItem.acmeOrderDetail->ServerUrL}</td></tr>
                        {/if}    
                    </table>

                    {if $subItem.acmeOrderDetail->EABID != ''}
                        <h4>ACME Account details</h4>
                        <table class="table">
                            <tr>
                                <th width="25%">ACME ID</th>
                                <th width="10%">Status</th>
                                <th width="15%">IP Address</th>
                                <th width="15%">Last Activity</th>
                                <th width="35%">User Agent</th>
                            </tr>
                            {foreach $subItem.acmeOrderDetail->AcmeAccountStatus as $index => $acmeItem}
                                <tr>
                                    <td>{$acmeItem->ACMEID}</td>
                                    <td>{$acmeItem->AccountStatus}</td>
                                    <td>{$acmeItem->IpAddress}</td>
                                    <td>{$acmeItem->LastActivity|date_format:"%d %b %Y"}</td>
                                    <td>{$acmeItem->UserAgent}</td>
                                </tr>
                            {/foreach}
                        </table>

                        <h4>Domain History</h4>
                        <table class="table">
                            <tr>
                                <th width="5%">No.</th>
                                <th width="20%">DomainName</th>
                                <th width="15%">Date</th>
                                <th width="25%">Type</th>
                            </tr>
                            {if $subItem.acmeOrderDetail->Domains|@count > 0}
                                {foreach $subItem.acmeOrderDetail->Domains as $index => $domainItem}
                                    <tr>
                                        <td>{$index + 1}</td>
                                        <td>{$domainItem->DomainName}</td>
                                        <td>{$domainItem->TransactionDate|date_format:"%d %b %Y"}</td>
                                        <td>{$domainItem->DomainAction}</td>
                                    </tr>
                                {/foreach}
                            {else}
                                <tr>
                                    <td colspan="5">No record found</td>
                                </tr>
                            {/if}
                        </table>
                    {/if}
                </div>
            </div>
        {/foreach}
    </div>
{/if}