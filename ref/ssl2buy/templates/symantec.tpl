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

                        {if $subItem.SymantecOrderID != ''}
                            <tr><th>Digicert Order ID</th><td>{$subItem.SymantecOrderID}</td></tr>
                            <tr><th>Start Date</th><td>{$subItem.StartDate}</td></tr>
                            <tr><th>End Date</th><td>{$subItem.EndDate}</td></tr>
                            <tr><th>Order Status</th><td>{$subItem.OrderStatus}</td></tr>
                            <tr><th>Validity Period</th><td>{$subItem.ValidityPeriod}</td></tr>
                            <tr><th>Domain Name</th><td>{$subItem.DomainName}</td></tr>
                            <tr><th>Approver Email</th><td>{$subItem.ApproverEmail}</td></tr>
                            <tr>
                                <th>Additional Domain List</th>
                                <td>
                                    {$subItem.addDomainList}
                                </td>
                            </tr>
                            <tr>
								<th>Admin Contact Details</th>
								<td>
									Title : {$subItem.AdminContact->Title} <br>
									First Name : {$subItem.AdminContact->FirstName} <br>
									Last Name : {$subItem.AdminContact->LastName} <br>
									Email : {$subItem.AdminContact->Email} <br>
									Phone : {$subItem.AdminContact->Phone}
								</td>
							</tr>
							<tr>
								<th>Technical Contact Details</th>
								<td>
									Title : {$subItem.TechnicalContact->Title} <br>
									First Name : {$subItem.TechnicalContact->FirstName} <br>
									Last Name : {$subItem.TechnicalContact->LastName} <br>
									Email : {$subItem.TechnicalContact->Email} <br>
									Phone : {$subItem.TechnicalContact->Phone}
								</td>
							</tr>
							<tr>
								<th>Organisation Details</th>
								<td>
									Assumed Name : {$subItem.OrganisationDetail->AssumedName} <br>
									Legal Name : {$subItem.OrganisationDetail->LegalName} <br>
									Division : {$subItem.OrganisationDetail->Division} <br>
									DUNS : {$subItem.OrganisationDetail->DUNS} <br>
									Address1 : {$subItem.OrganisationDetail->Address1} <br>
									Address2 : {$subItem.OrganisationDetail->Address2} <br>
									City : {$subItem.OrganisationDetail->City} <br>
									State : {$subItem.OrganisationDetail->State} <br>
									Country : {$subItem.OrganisationDetail->Country} <br>
									Phone : {$subItem.OrganisationDetail->Phone} <br>
									FAX : {$subItem.OrganisationDetail->FAX} <br>
									Postal Code : {$subItem.OrganisationDetail->PostalCode}
								</td>
							</tr>
                            <tr>
                                <th>CSR Details</th>
                                <td>
                                    Domain Name : {$subItem.CSRDetail->DomainName} <br>
                                    Organisation : {$subItem.CSRDetail->Organisation} <br>
                                    Organisation Unit : {$subItem.CSRDetail->OrganisationUnit} <br>
                                    Locality : {$subItem.CSRDetail->Locality} <br>
                                    State : {$subItem.CSRDetail->State} <br>
                                    Country : {$subItem.CSRDetail->Country} 
                                </td>
                            </tr>
                        {/if}            
                    </table>
                </div>
            </div>
        {/foreach}
    </div>
{/if}