<?php
/*
 * @ https://EasyToYou.eu - IonCube v11 Decoder Online
 * @ PHP 7.1
 * @ Decoder version: 1.1.6
 * @ Release: 10/08/2022
 */

// Decoded file for php version 71.
$GLOBALS["moduleName"] = "ssl2buy";
if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
require_once __DIR__ . "/LibLoader.php";
add_hook("ClientAreaHeadOutput", 1, function ($vars) {
    return "<style>\n        .certDetails table { text-align:left; }\n        .certDetails h4{text-align:left; margin-top:20px; margin-bottom:20px; font-weight:600; color:#058;}\n    </style>";
});
function ssl2buy_MetaData()
{
    return ["DisplayName" => "SSL2Buy", "APIVersion" => "1.1", "RequiresServer" => true];
}
function ssl2buy_ConfigOptions()
{
    $products = SSL2BuyProducts::getProductsForSelect();
    $module = Illuminate\Database\Capsule\Manager::table("tblproducts")->where("id", (int) $_REQUEST["id"])->first();
    $configurableOptionsDefaultName = "SSL2Buy - " . $module->name . " - " . $module->id;
    $groupConfig = Illuminate\Database\Capsule\Manager::table("tblproductconfiggroups")->where("name", $configurableOptionsDefaultName)->first();
    if (!empty($groupConfig)) {
        $groupId = $groupConfig->id;
        $productConfigOptions = Illuminate\Database\Capsule\Manager::table("tblproductconfigoptions")->where("gid", $groupId)->first();
        if (!empty($productConfigOptions)) {
            $configId = $productConfigOptions->id;
            $configOptionsSub = Illuminate\Database\Capsule\Manager::table("tblproductconfigoptionssub")->where("configid", $configId)->where("optionname", "1|1")->first();
            if (!empty($configOptionsSub)) {
                $prices = Illuminate\Database\Capsule\Manager::table("tblpricing")->where("relid", $configOptionsSub->id)->first();
            }
        }
    }
    if (isset($prices->annually) && 0 < $prices->annually) {
        $oneYear = !empty($prices->annually) ? $prices->annually : 0;
    } else {
        $oneYear = !empty($prices->asetupfee) ? $prices->asetupfee : 0;
    }
    if (isset($prices->biennially) && 0 < $prices->biennially) {
        $twoYear = !empty($prices->biennially) ? $prices->biennially : 0;
    } else {
        $twoYear = !empty($prices->bsetupfee) ? $prices->bsetupfee : 0;
    }
    if (isset($prices->triennially) && 0 < $prices->triennially) {
        $threeYear = !empty($prices->triennially) ? $prices->triennially : 0;
    } else {
        $threeYear = !empty($prices->tsetupfee) ? $prices->tsetupfee : 0;
    }
    $configurableWildcardOptionsDefaultName = "SSL2Buy - Wildcard - " . $module->name . " - " . $module->id;
    $groupWildcardConfig = Illuminate\Database\Capsule\Manager::table("tblproductconfiggroups")->where("name", $configurableWildcardOptionsDefaultName)->first();
    if (!empty($groupWildcardConfig)) {
        $groupWildcardId = $groupWildcardConfig->id;
        $productWildcardConfigOptions = Illuminate\Database\Capsule\Manager::table("tblproductconfigoptions")->where("gid", $groupWildcardId)->first();
        if (!empty($productWildcardConfigOptions)) {
            $configWildcardId = $productWildcardConfigOptions->id;
            $configWildcardOptionsSub = Illuminate\Database\Capsule\Manager::table("tblproductconfigoptionssub")->where("configid", $configWildcardId)->where("optionname", "1|1")->first();
            if (!empty($configWildcardOptionsSub)) {
                $wildcardPrices = Illuminate\Database\Capsule\Manager::table("tblpricing")->where("relid", $configWildcardOptionsSub->id)->first();
            }
        }
    }
    if (isset($wildcardPrices->annually) && 0 < $wildcardPrices->annually) {
        $oneYearWildcard = !empty($wildcardPrices->annually) ? $wildcardPrices->annually : 0;
    } else {
        $oneYearWildcard = !empty($wildcardPrices->asetupfee) ? $wildcardPrices->asetupfee : 0;
    }
    if (isset($wildcardPrices->biennially) && 0 < $wildcardPrices->biennially) {
        $twoYearWildcard = !empty($wildcardPrices->biennially) ? $wildcardPrices->biennially : 0;
    } else {
        $twoYearWildcard = !empty($wildcardPrices->bsetupfee) ? $wildcardPrices->bsetupfee : 0;
    }
    if (isset($wildcardPrices->triennially) && 0 < $wildcardPrices->triennially) {
        $threeYearWildcard = !empty($wildcardPrices->triennially) ? $wildcardPrices->triennially : 0;
    } else {
        $threeYearWildcard = !empty($wildcardPrices->tsetupfee) ? $wildcardPrices->tsetupfee : 0;
    }
    $deliveryMethods = SSL2BuyProducts::getDeliveryMethods();
    if (!empty($deliveryMethods)) {
        foreach ($deliveryMethods as $deliveryItem) {
            $inputname = $deliveryItem["inputname"];
            if ($inputname != "installOnExistHSM") {
                ${$inputname} = 0;
            }
        }
    }
    $configurableDeliveryModeDefaultName = "SSL2Buy - CodeSign - DeliveryMode - " . $module->name . " - " . $module->id;
    $groupDeliveryModeConfig = Illuminate\Database\Capsule\Manager::table("tblproductconfiggroups")->where("name", $configurableDeliveryModeDefaultName)->first();
    if (!empty($groupDeliveryModeConfig)) {
        $groupDeliveryMethodId = $groupDeliveryModeConfig->id;
        $productDeliveryModeOptions = Illuminate\Database\Capsule\Manager::table("tblproductconfigoptions")->where("gid", $groupDeliveryMethodId)->first();
        if (!empty($productDeliveryModeOptions)) {
            $configDMethodId = $productDeliveryModeOptions->id;
            foreach ($deliveryMethods as $deliveryItem) {
                $inputname = $deliveryItem["inputname"];
                if ($inputname != "installOnExistHSM") {
                    $configDeliveryOptionsSub = Illuminate\Database\Capsule\Manager::table("tblproductconfigoptionssub")->where("configid", $configDMethodId)->where("optionname", $deliveryItem["code"] . "|" . $deliveryItem["name"])->first();
                    if (!empty($configDeliveryOptionsSub)) {
                        $deliveryMethodPrices = Illuminate\Database\Capsule\Manager::table("tblpricing")->where("relid", $configDeliveryOptionsSub->id)->first();
                        if (isset($deliveryMethodPrices->annually) && 0 < $deliveryMethodPrices->annually) {
                            ${$inputname} = !empty($deliveryMethodPrices->annually) ? $deliveryMethodPrices->annually : 0;
                        } else {
                            ${$inputname} = !empty($deliveryMethodPrices->asetupfee) ? $deliveryMethodPrices->asetupfee : 0;
                        }
                    }
                }
            }
        }
    }
    $deliveryString = "";
    if (!empty($deliveryMethods)) {
        foreach ($deliveryMethods as $deliveryItem) {
            $inputname = $deliveryItem["inputname"];
            if ($inputname != "installOnExistHSM") {
                $deliveryString .= "<div class=\"form-group\"><label for=\"ssl2buyPrice1Year\">" . $deliveryItem["name"] . ":</label><br/><input value=\"" . ${$inputname} . "\" type=\"text\" name=\"" . $deliveryItem["inputname"] . "\"/></div>";
            }
        }
    }
    $digicertdeliveryMethods = SSL2BuyProducts::getDigicertDeliveryMethods();
    if (!empty($digicertdeliveryMethods)) {
        foreach ($digicertdeliveryMethods as $digicertdeliveryItem) {
            $digicertinputname = $digicertdeliveryItem["inputname"];
            ${$digicertinputname} = 0;
        }
    }
    $configurableDigicertDeliveryModeDefaultName = "SSL2Buy - Digicert CodeSign - DeliveryMode - " . $module->name . " - " . $module->id;
    $groupDigicertDeliveryModeConfig = Illuminate\Database\Capsule\Manager::table("tblproductconfiggroups")->where("name", $configurableDigicertDeliveryModeDefaultName)->first();
    if (!empty($groupDigicertDeliveryModeConfig)) {
        $groupDigicertDeliveryMethodId = $groupDigicertDeliveryModeConfig->id;
        $productDigicertDeliveryModeOptions = Illuminate\Database\Capsule\Manager::table("tblproductconfigoptions")->where("gid", $groupDigicertDeliveryMethodId)->first();
        if (!empty($productDigicertDeliveryModeOptions)) {
            $configDigicertDMethodId = $productDigicertDeliveryModeOptions->id;
            foreach ($digicertdeliveryMethods as $digicertdeliveryItem) {
                $inputname = $digicertdeliveryItem["inputname"];
                $configDigicertDeliveryOptionsSub = Illuminate\Database\Capsule\Manager::table("tblproductconfigoptionssub")->where("configid", $configDigicertDMethodId)->where("optionname", $digicertdeliveryItem["code"] . "|" . $digicertdeliveryItem["name"])->first();
                if (!empty($configDigicertDeliveryOptionsSub)) {
                    $deliveryDigicertMethodPrices = Illuminate\Database\Capsule\Manager::table("tblpricing")->where("relid", $configDigicertDeliveryOptionsSub->id)->first();
                    if (isset($deliveryDigicertMethodPrices->annually) && 0 < $deliveryDigicertMethodPrices->annually) {
                        ${$inputname} = !empty($deliveryDigicertMethodPrices->annually) ? $deliveryDigicertMethodPrices->annually : 0;
                    } else {
                        ${$inputname} = !empty($deliveryDigicertMethodPrices->asetupfee) ? $deliveryDigicertMethodPrices->asetupfee : 0;
                    }
                }
            }
        }
    }
    $digicertdeliveryString = "";
    if (!empty($digicertdeliveryMethods)) {
        foreach ($digicertdeliveryMethods as $digicertdeliveryItem) {
            $inputname = $digicertdeliveryItem["inputname"];
            $digicertdeliveryString .= "<div class=\"form-group\"><label for=\"ssl2buyPrice1Year\">" . $digicertdeliveryItem["name"] . ":</label><br/><input value=\"" . ${$inputname} . "\" type=\"text\" name=\"" . $digicertdeliveryItem["inputname"] . "\"/></div>";
        }
    }
    $installScript = "<style>#ssl2buy_custom_dialog, #ssl2buy_custom_wildcard_dialog, #ssl2buy_custom_delivery_mode, #ssl2buy_custom_digicert_delivery_mode{width: 500px; position: fixed; background: #ffffff; z-index: 100; left: 50%; top: 50%; padding: 30px; box-shadow: 0 0 5px 4px rgba(0,0,0,0.1); transform: translate(-50%,-50%); display:block;}</style><script type=\"text/javascript\">\n                        jQuery(document).ready(function(){\n                            jQuery(document).click(function(event) {\n                              if (!jQuery(event.target).closest(\"#ssl2buy_custom_dialog, #install_default_options\").length) {\n                                    jQuery(\"#ssl2buy_custom_dialog\").addClass(\"hidden\");\n                              }\n                              if (!jQuery(event.target).closest(\"#ssl2buy_custom_wildcard_dialog, #install_default_wildcard_options\").length) {\n                                    jQuery(\"#ssl2buy_custom_wildcard_dialog\").addClass(\"hidden\");\n                              }\n                              if (!jQuery(event.target).closest(\"#ssl2buy_custom_delivery_mode, #install_delivery_mode\").length) {\n                                    jQuery(\"#ssl2buy_custom_delivery_mode\").addClass(\"hidden\");\n                              }\n                              if (!jQuery(event.target).closest(\"#ssl2buy_custom_digicert_delivery_mode, #install_digicert_delivery_mode\").length) {\n                                    jQuery(\"#ssl2buy_custom_digicert_delivery_mode\").addClass(\"hidden\");\n                              }\n                            });\n                            jQuery('select[name=\"packageconfigoption[3]\"]').parents().eq(3).after('<div id=\"ssl2buy_custom_dialog\" class=\"hidden\" style=\"width: 500px\" title=\"SAN Prices\"><h3 style=\"font-size: 20px; font-weight: bold;\">Additional Domain Price</h3>'\n                                +'<form method=\"post\"><input type=\"hidden\" name=\"packageconfigoption\"><input type=\"hidden\" name=\"servertype\" value=\"ssl2buy\"><input type=\"hidden\" name=\"actionSaveConfig\" value=\"SSL2Buy_setup_configurable_options\"><input type=\"hidden\" name=\"numberOfSans\" value=\"0\">'\n                                +'<div class=\"form-group\"><label for=\"ssl2buyPrice1Year\">Price for Additional Domain for 1 year:</lebel> <input value=\"" . $oneYear . "\" type=\"text\" name=\"oneYear\"/></div>'\n                                +'<div class=\"form-group\"><label for=\"ssl2buyPrice1Year\">Price for Additional Domain for 2 year:</lebel> <input value=\"" . $twoYear . "\" type=\"text\" name=\"twoYear\"/></div>'\n                                +'<div class=\"form-group\"><label for=\"ssl2buyPrice1Year\">Price for Additional Domain for 3 year:</lebel> <input value=\"" . $threeYear . "\" type=\"text\" name=\"threeYear\"/></div>'\n                                +'<div class=\"form-group\">'\n                                +'<input type=\"submit\" class=\"btn btn-danger pull-right\" name=\"deleteConfigOption\" value=\"Delete\" style=\"margin-left:10px;\"><input type=\"submit\" class=\"btn btn-success pull-right\" name=\"saveConfigOption\" value=\"Save\"></form>'\n                            +'</div>');\n                            jQuery(\"#install_default_options\").click(function(event){\n                                event.preventDefault();\n                                jQuery(\"#ssl2buy_custom_dialog\").removeClass(\"hidden\");\n                                var numberOfSans = jQuery('input[name=\"packageconfigoption[4]\"]').val();\n                                jQuery('input[name=\"numberOfSans\"]').val(numberOfSans);\n                            });\n                            jQuery('select[name=\"packageconfigoption[3]\"]').parents().eq(3).after('<div id=\"ssl2buy_custom_wildcard_dialog\" class=\"hidden\" style=\"width: 500px\" title=\"Wildcard SAN Prices\"><h3 style=\"font-size: 20px; font-weight: bold;\">Additional Wildcard Domain Price</h3>'\n                                +'<form method=\"post\"><input type=\"hidden\" name=\"packageconfigoption\"><input type=\"hidden\" name=\"servertype\" value=\"ssl2buy\"><input type=\"hidden\" name=\"actionSaveWildcardConfig\" value=\"SSL2Buy_setup_configurable_wildcard_options\"><input type=\"hidden\" name=\"numberOfWildcardSans\" value=\"0\">'\n                                +'<div class=\"form-group\"><label for=\"ssl2buyPrice1Year\">Price for Additional Wildcard Domain for 1 year:</lebel> <input value=\"" . $oneYearWildcard . "\" type=\"text\" name=\"oneYearWildcard\"/></div>'\n                                +'<div class=\"form-group\"><label for=\"ssl2buyPrice1Year\">Price for Additional Wildcard Domain for 2 year:</lebel> <input value=\"" . $twoYearWildcard . "\" type=\"text\" name=\"twoYearWildcard\"/></div>'\n                                +'<div class=\"form-group\"><label for=\"ssl2buyPrice1Year\">Price for Additional Wildcard Domain for 3 year:</lebel> <input value=\"" . $threeYearWildcard . "\" type=\"text\" name=\"threeYearWildcard\"/></div>'\n                                +'<div class=\"form-group\">'\n                                +'<input type=\"submit\" class=\"btn btn-danger pull-right\" name=\"deleteWildcardConfigOption\" value=\"Delete\" style=\"margin-left:10px;\"><input type=\"submit\" class=\"btn btn-success pull-right\" name=\"saveWildcardConfigOption\" value=\"Save\"></form>'\n                                +'</div>');\n                            jQuery(\"#install_default_wildcard_options\").click(function(event){\n                                event.preventDefault();\n                                jQuery(\"#ssl2buy_custom_wildcard_dialog\").removeClass(\"hidden\");\n                                var numberOfWildcardSans = jQuery('input[name=\"packageconfigoption[10]\"]').val();\n                                jQuery('input[name=\"numberOfWildcardSans\"]').val(numberOfWildcardSans);\n                            });\n\n\n                            jQuery('select[name=\"packageconfigoption[3]\"]').parents().eq(3).after('<div id=\"ssl2buy_custom_delivery_mode\" class=\"hidden\" style=\"width: 500px\" title=\"Certificate Delivery Method Prices\"><h3 style=\"font-size: 20px; font-weight: bold;\">Certificate Delivery Method Price</h3>'\n                                +'<form method=\"post\"><input type=\"hidden\" name=\"packageconfigoption\"><input type=\"hidden\" name=\"servertype\" value=\"ssl2buy\"><input type=\"hidden\" name=\"actionSaveDeliveryMode\" value=\"SSL2Buy_setup_configurable_delivery_mode\">" . $deliveryString . "'\n                                +'<div class=\"form-group\">'\n                                +'<input type=\"submit\" class=\"btn btn-danger pull-right\" name=\"deleteDeliveryModeConfigOption\" value=\"Delete\" style=\"margin-left:10px;\"><input type=\"submit\" class=\"btn btn-success pull-right\" name=\"saveDeliveryModeConfigOption\" value=\"Save\"></form>'\n                                +'</div>');\n\n                            jQuery(\"#install_delivery_mode\").click(function(event){\n                                event.preventDefault();\n                                jQuery(\"#ssl2buy_custom_delivery_mode\").removeClass(\"hidden\");\n                            });   \n\n                            jQuery('select[name=\"packageconfigoption[3]\"]').parents().eq(3).after('<div id=\"ssl2buy_custom_digicert_delivery_mode\" class=\"hidden\" style=\"width: 500px\" title=\"Digicert Certificate Delivery Method Prices\"><h3 style=\"font-size: 20px; font-weight: bold;\">Digicert Certificate Delivery Method Price</h3>'\n                                +'<form method=\"post\"><input type=\"hidden\" name=\"packageconfigoption\"><input type=\"hidden\" name=\"servertype\" value=\"ssl2buy\"><input type=\"hidden\" name=\"actionSaveDigicertDeliveryMode\" value=\"SSL2Buy_setup_configurable_digicert_delivery_mode\">" . $digicertdeliveryString . "'\n                                +'<div class=\"form-group\">'\n                                +'<input type=\"submit\" class=\"btn btn-danger pull-right\" name=\"deleteDigicertDeliveryModeConfigOption\" value=\"Delete\" style=\"margin-left:10px;\"><input type=\"submit\" class=\"btn btn-success pull-right\" name=\"saveDigicertDeliveryModeConfigOption\" value=\"Save\"></form>'\n                                +'</div>');\n\n                            jQuery(\"#install_digicert_delivery_mode\").click(function(event){\n                                event.preventDefault();\n                                jQuery(\"#ssl2buy_custom_digicert_delivery_mode\").removeClass(\"hidden\");\n                            });    \n                        });\n                    </script>";
    $template = Illuminate\Database\Capsule\Manager::table("tblemailtemplates")->where("name", SSL2BuyEmailHandler::$configurationDetailsTemplateName)->first();
    if (empty($template)) {
        Illuminate\Database\Capsule\Manager::table("tblemailtemplates")->insertGetId(["type" => "product", "name" => SSL2BuyEmailHandler::$configurationDetailsTemplateName, "subject" => "SSL2Buy Configuration Details", "custom" => 1, "message" => "Client Name: {\$clientname} <br> Order Number: {\$ordernumber} <br> Product Name: {\$productname} <br> SSL configuration Link: <a href=\"{\$configurationLink}\">Click</a> <br> You can change content of this mail in Email Templates section."]);
    }
    $customTemplates = Illuminate\Database\Capsule\Manager::table("tblemailtemplates")->where("custom", 1)->get();
    $templatesArray = [];
    foreach ($customTemplates as $key => $value) {
        $templatesArray[$value->name] = $value->name;
    }
    return ["Partner Email" => ["Type" => "text", "Size" => "30"], "Api Key" => ["Type" => "text", "Size" => "30", "Description" => $installScript], "Product" => ["Type" => "dropdown", "Options" => $products], "Default number of additional domains" => ["Type" => "text", "Size" => "30", "Default" => 0], "Default Period" => ["Type" => "text", "Size" => "30", "Default" => 1], "Configurable Options" => ["type" => "text", "Description" => "<a href=\"#install\" id=\"install_default_options\">Install/Update</a>"], "Is Demo" => ["Type" => "yesno"], "Send SSL Configuration Link Email" => ["Type" => "yesno"], "Configuration Link Email Template" => ["Type" => "dropdown", "Options" => $templatesArray], "Default number of additional wildcard domains" => ["Type" => "text", "Size" => "15", "Default" => 0, "Description" => "<a href=\"#install\" id=\"install_default_wildcard_options\">Install/Update</a>"], "Certificate Delivery Method ( comodo / sectigo )" => ["type" => "text", "Description" => "<a href=\"#install\" id=\"install_delivery_mode\">Install/Update</a>"], "Certificate Delivery Method ( digicert )" => ["type" => "text", "Description" => "<a href=\"#install\" id=\"install_digicert_delivery_mode\">Install/Update</a>"]];
}
function ssl2buy_CreateAccount(array $params)
{
    try {
        if (!empty($params["configoptions"]["sannumber"])) {
            $additionalSans = filter_var($params["configoptions"]["sannumber"], FILTER_SANITIZE_NUMBER_INT);
        } else {
            $additionalSans = 0;
        }
        if (!empty($params["configoptions"]["wildcardsannumber"])) {
            $additionalWildcardSans = filter_var($params["configoptions"]["wildcardsannumber"], FILTER_SANITIZE_NUMBER_INT);
        } else {
            $additionalWildcardSans = 0;
        }
        if (isset($params["configoptions"]["deliverymode"])) {
            $deliveryMode = $params["configoptions"]["deliverymode"];
        } else {
            $deliveryMode = "";
        }
        $billingCycle = 1;
        $hosting = Illuminate\Database\Capsule\Manager::table("tblhosting")->where("id", $params["serviceid"])->first();
        switch ($hosting->billingcycle) {
            case "Biennially":
                $billingCycle = 2;
                break;
            case "Triennially":
                $billingCycle = 3;
                break;
            default:
                $isRenew = isset($params["customfields"]["is_renew"]) && $params["customfields"]["is_renew"] == "on" ? true : false;
                $rand = rand(1, 1000);
                $clientDetails = $params["clientsdetails"];
                $orderDetailsForApi = ["PartnerEmail" => $params["configoption1"], "ApiKey" => $params["configoption2"], "ProductCode" => $params["configoption3"], "Year" => $billingCycle, "IsRenew" => $isRenew, "AddDomains" => $additionalSans, "WildcardSAN" => $additionalWildcardSans, "ProvisioningOption" => $deliveryMode, "PartnerOrderID" => "WHMCS-" . $params["serviceid"] . $rand, "CustomerName" => $clientDetails["fullname"], "Address1" => $clientDetails["address1"], "Address2" => $clientDetails["address2"], "City" => $clientDetails["city"], "State" => $clientDetails["state"], "Country" => $clientDetails["country"], "PostalCode" => $clientDetails["postcode"], "Phone" => $clientDetails["phonenumber"], "BillingEmail" => $clientDetails["email"], "CompanyName" => $clientDetails["companyname"], "VatTax" => $clientDetails["taxexempt"]];
                $testMode = $params["configoption7"] == "on" ? true : false;
                $order = Illuminate\Database\Capsule\Manager::table("tblsslorders")->where("userid", $clientDetails["userid"])->where("serviceid", $params["serviceid"])->where("module", $GLOBALS["moduleName"])->get();
                $ConfigLink = "";
                $ConfigPin = "";
                if (!empty($order[0])) {
                    $orderNew = $order[0];
                    $dataOrderParsedNew = json_decode($orderNew->configdata);
                    $ConfigLink = $dataOrderParsedNew->link;
                    $ConfigPin = $dataOrderParsedNew->pin;
                }
                if ($ConfigLink == "" || $ConfigLink == NULL) {
                    $placeOrder = SSL2BuyApi::getInstance($orderDetailsForApi, $testMode)->placeOrder();
                    if ($placeOrder->Errors->ErrorNumber !== 0) {
                        throw new Exception($placeOrder->Errors->ErrorMessage);
                    }
                    $respondFromApi = new stdClass();
                    $respondFromApi->pin = $placeOrder->Pin;
                    $respondFromApi->link = $placeOrder->ConfigurationLink;
                    $respondFromApi->orderId = $placeOrder->OrderNumber;
                    $jsonRespondFromApi = json_encode($respondFromApi);
                    if ($params["configoption8"] == "on") {
                        $productId = $params["pid"];
                        $productName = Illuminate\Database\Capsule\Manager::table("tblproducts")->where("id", $productId)->first();
                        $productName = $productName->name;
                        $customvars = base64_encode(serialize(["clientname" => $clientDetails["fullname"], "ordernumber" => $respondFromApi->orderId, "productname" => $productName, "pin" => $respondFromApi->pin, "configurationLink" => $respondFromApi->link]));
                        $templateName = $params["configoption9"];
                        SSL2BuyEmailHandler::getInstance(["id" => $params["serviceid"], "customvars" => $customvars])->createSendMailCustom($templateName);
                    }
                    $order = Illuminate\Database\Capsule\Manager::table("tblsslorders")->where("userid", $clientDetails["userid"])->where("serviceid", $params["serviceid"])->where("module", $GLOBALS["moduleName"])->get();
                    if (empty($order[0])) {
                        Illuminate\Database\Capsule\Manager::connection()->transaction(function ($connectionManager) {
                            static $clientDetails = NULL;
                            static $params = NULL;
                            static $jsonRespondFromApi = NULL;
                            $connectionManager->table("tblsslorders")->insert(["userid" => $clientDetails["userid"], "serviceid" => $params["serviceid"], "remoteid" => "", "module" => $GLOBALS["moduleName"], "certtype" => "", "configdata" => $jsonRespondFromApi, "completiondate" => "", "status" => ""]);
                        });
                    } else {
                        Illuminate\Database\Capsule\Manager::table("tblsslorders")->where("userid", $clientDetails["userid"])->where("serviceid", $params["serviceid"])->where("module", $GLOBALS["moduleName"])->update(["configdata" => $jsonRespondFromApi]);
                    }
                }
        }
    } catch (Exception $e) {
        logModuleCall("ssl2buy", "ssl2buy_CreateAccount", $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
    return "success";
}
function ssl2buy_SuspendAccount(array $params)
{
    try {
    } catch (Exception $e) {
        logModuleCall("ssl2buy", "ssl2buy_SuspendAccount", $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
    return "success";
}
function ssl2buy_UnsuspendAccount(array $params)
{
    try {
    } catch (Exception $e) {
        logModuleCall("ssl2buy", "ssl2buy_UnsuspendAccount", $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
    return "success";
}
function ssl2buy_RenewAccount(array $params)
{
    try {
    } catch (Exception $e) {
        logModuleCall("ssl2buy", "ssl2buy_RenewAccount", $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
    return "success";
}
function ssl2buy_TerminateAccount(array $params)
{
    try {
    } catch (Exception $e) {
        logModuleCall("ssl2buy", "ssl2buy_TerminateAccount", $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
    return "success";
}
function ssl2buy_AdminCustomButtonArray()
{
    return ["Resend Configuration Link" => "resendConfigurationLink", "Resend Approval Email" => "resendApprovalEmail"];
}
function ssl2buy_resendApprovalEmail(array $params)
{
    try {
        $order = Illuminate\Database\Capsule\Manager::table("tblsslorders")->where("userid", $params["userid"])->where("serviceid", $params["serviceid"])->where("module", $GLOBALS["moduleName"])->get();
        if (!empty($order)) {
            $order = $order[0];
            $dataOrderParsed = json_decode($order->configdata);
            $orderDetailsForApi = ["PartnerEmail" => $params["configoption1"], "ApiKey" => $params["configoption2"], "OrderNumber" => $dataOrderParsed->orderId];
            $selectedBrandCode = $params["configoption3"];
            $selectedBrandData = SSL2BuyProducts::getBrandByCode($selectedBrandCode);
            $testMode = $params["configoption7"] == "on" ? true : false;
            $resend = SSL2BuyApi::getInstance($orderDetailsForApi, $testMode)->resendApprovalMail($selectedBrandData["brand_name"]);
            if ($resend == NULL) {
                throw new Exception("Empty Response");
            }
        }
    } catch (Exception $e) {
        logModuleCall("ssl2buy", "ssl2buy_resendApprovalEmail", $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
    return "success";
}
function ssl2buy_resendConfigurationLink(array $params)
{
    try {
        $order = Illuminate\Database\Capsule\Manager::table("tblsslorders")->where("userid", $params["userid"])->where("serviceid", $params["serviceid"])->where("module", $GLOBALS["moduleName"])->get();
        if (!empty($order)) {
            $order = $order[0];
            $dataOrderParsed = json_decode($order->configdata);
            if ($params["configoption8"] == "on") {
                $clientDetails = $params["clientsdetails"];
                $productId = $params["pid"];
                $productName = Illuminate\Database\Capsule\Manager::table("tblproducts")->where("id", $productId)->first();
                $productName = $productName->name;
                $customvars = base64_encode(serialize(["clientname" => $clientDetails["fullname"], "ordernumber" => $dataOrderParsed->orderId, "productname" => $productName, "pin" => $dataOrderParsed->pin, "configurationLink" => $dataOrderParsed->link]));
                $templateName = $params["configoption9"];
                SSL2BuyEmailHandler::getInstance(["id" => $params["serviceid"], "customvars" => $customvars])->createSendMailCustom($templateName);
            }
        }
    } catch (Exception $e) {
        logModuleCall("ssl2buy", "ssl2buy_resendConfigurationLink", $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
    return "success";
}
function ssl2buy_AdminServicesTabFields(array $params)
{
    try {
        $order = Illuminate\Database\Capsule\Manager::table("tblsslorders")->where("userid", $params["userid"])->where("serviceid", $params["serviceid"])->where("module", $GLOBALS["moduleName"])->get();
        $extraFields = [];
        if (!empty($order[0])) {
            $order = $order[0];
            $dataOrderParsed = json_decode($order->configdata);
            $selectedBrandCode = $params["configoption3"];
            $selectedBrandData = SSL2BuyProducts::getBrandByCode($selectedBrandCode);
            $responseString = "";
            if ($selectedBrandData) {
                $orderDetailsForApi = ["PartnerEmail" => $params["configoption1"], "ApiKey" => $params["configoption2"], "OrderNumber" => $dataOrderParsed->orderId];
                $testMode = $params["configoption7"] == "on" ? true : false;
                $orderSubscriptionHistory = SSL2BuyApi::getInstance($orderDetailsForApi, $testMode)->orderSubscriptionHistory();
                if ($orderSubscriptionHistory->Errors->ErrorNumber !== 0) {
                    throw new Exception($orderSubscriptionHistory->Errors->ErrorMessage);
                }
                if ($orderSubscriptionHistory->StatusCode == 0) {
                    $subscriptionHistory = $orderSubscriptionHistory->SubscriptionHistory;
                    $responseString .= "<ul class=\"nav nav-tabs\" id=\"customTabs\" role=\"tablist\">";
                    foreach ($subscriptionHistory as $index => $subHItem) {
                        if (count($orderSubscriptionHistory->SubscriptionHistory) - $index == 1) {
                            $subLabel = "1<sup>st</sup>  Year Subscription";
                        } else if (count($orderSubscriptionHistory->SubscriptionHistory) - $index == 2) {
                            $subLabel = "2<sup>nd</sup>  Year Subscription";
                        } else if (count($orderSubscriptionHistory->SubscriptionHistory) - $index == 3) {
                            $subLabel = "3<sup>rd</sup>  Year Subscription";
                        }
                        if ($index == 0) {
                            $responseString .= "<li class=\"nav-item active\" role=\"presentation\">";
                        } else {
                            $responseString .= "<li class=\"nav-item\" role=\"presentation\">";
                        }
                        $responseString .= "<a class=\"nav-link\" id=\"tab1-tab\" data-toggle=\"tab\" href=\"#tab" . $index . "\" role=\"tab\">" . $subLabel . "</a>";
                        $responseString .= "</li>";
                    }
                    $responseString .= "</ul>";
                    $responseString .= "<div class=\"tab-content mt-3\" id=\"customTabsContent\">";
                    foreach ($subscriptionHistory as $index => $subHItem) {
                        if ($index == 0) {
                            $responseString .= "<div class=\"tab-pane fade active in\" id=\"tab" . $index . "\" role=\"tabpanel\">";
                        } else {
                            $responseString .= "<div class=\"tab-pane fade\" id=\"tab" . $index . "\" role=\"tabpanel\">";
                        }
                        $configPin = str_replace($dataOrderParsed->pin, $subHItem->Pin, $dataOrderParsed->link);
                        $responseString .= "<div class=\"certDetails\">";
                        $responseString .= "<table class=\"table\">";
                        $responseString .= "<tr><th>Configuration Link</th><td><a href=\"" . $configPin . "\" target=\"_blank\">" . $configPin . "</a></td></tr>";
                        $responseString .= "<tr><th>Certificate Status</th><td>" . $subHItem->CertificateStatus . "</td></tr>";
                        if ($selectedBrandData["brand_name"] == "sectigo_acme") {
                            $orderSubDetailsForApi = ["PartnerEmail" => $params["configoption1"], "ApiKey" => $params["configoption2"], "OrderNumber" => $dataOrderParsed->orderId];
                            $testMode = $params["configoption7"] == "on" ? true : false;
                            $orderSubscriptionDetail = SSL2BuyApi::getInstance($orderSubDetailsForApi, $testMode)->getAcmeOrderDetail();
                            if ($orderSubscriptionDetail->StatusCode == 0) {
                                $responseString .= SSL2BuyApi::SectigoACMEOrderDetails($orderSubscriptionDetail);
                            }
                        } else {
                            $orderSubDetailsForApi = ["PartnerEmail" => $params["configoption1"], "ApiKey" => $params["configoption2"], "Pin" => $subHItem->Pin];
                            $testMode = $params["configoption7"] == "on" ? true : false;
                            $orderSubscriptionDetail = SSL2BuyApi::getInstance($orderSubDetailsForApi, $testMode)->orderSubscriptionDetail($selectedBrandData["brand_name"]);
                            if ($orderSubscriptionDetail->StatusCode == 0) {
                                if (strtolower($selectedBrandData["brand_name"]) == "globalsign") {
                                    $responseString .= SSL2BuyApi::GlobalSingOrderDetails($orderSubscriptionDetail);
                                } else if (strtolower($selectedBrandData["brand_name"]) == "comodo") {
                                    $responseString .= SSL2BuyApi::ComodoOrderDetails($orderSubscriptionDetail, $params["configoption3"]);
                                } else if (strtolower($selectedBrandData["brand_name"]) == "symantec") {
                                    $responseString .= SSL2BuyApi::SymantecOrderDetails($orderSubscriptionDetail);
                                } else if (strtolower($selectedBrandData["brand_name"]) == "prime") {
                                    $responseString .= SSL2BuyApi::PrimeSSLOrderDetails($orderSubscriptionDetail);
                                }
                            }
                        }
                        $responseString .= "</table>";
                        $responseString .= "</div>";
                        $responseString .= "</div>";
                    }
                    $responseString .= "</div>";
                }
            }
        }
        $extraFields["Subscription Details"] = $responseString;
        return $extraFields;
    } catch (Exception $e) {
        logModuleCall("ssl2buy", "ssl2buy_AdminServicesTabFields", $params, $e->getMessage(), $e->getTraceAsString());
    }
    return [];
}
function ssl2buy_ClientArea(array $params)
{
    if ($params["status"] != "Active") {
        return [];
    }
    try {
        $response = [];
        $serviceAction = "get_stats";
        $order = Illuminate\Database\Capsule\Manager::table("tblsslorders")->where("userid", $params["userid"])->where("serviceid", $params["serviceid"])->where("module", $GLOBALS["moduleName"])->get();
        if (!empty($order[0])) {
            $order = $order[0];
            $dataOrderParsed = json_decode($order->configdata);
            $response["orderData"] = $dataOrderParsed;
            $selectedBrandCode = $params["configoption3"];
            $selectedBrandData = SSL2BuyProducts::getBrandByCode($selectedBrandCode);
            if ($selectedBrandData) {
                $orderDetailsForApi = ["PartnerEmail" => $params["configoption1"], "ApiKey" => $params["configoption2"], "OrderNumber" => $dataOrderParsed->orderId];
                $testMode = $params["configoption7"] == "on" ? true : false;
                $orderSubscriptionHistory = SSL2BuyApi::getInstance($orderDetailsForApi, $testMode)->orderSubscriptionHistory();
                if ($orderSubscriptionHistory->Errors->ErrorNumber !== 0) {
                    throw new Exception($orderSubscriptionHistory->Errors->ErrorMessage);
                }
                if ($orderSubscriptionHistory->StatusCode == 0) {
                    $subscriptionHistory = $orderSubscriptionHistory->SubscriptionHistory;
                    foreach ($subscriptionHistory as $index => $subHItem) {
                        if (count($orderSubscriptionHistory->SubscriptionHistory) - $index == 1) {
                            $subLabel = "1<sup>st</sup>  Year Subscription";
                        } else if (count($orderSubscriptionHistory->SubscriptionHistory) - $index == 2) {
                            $subLabel = "2<sup>nd</sup>  Year Subscription";
                        } else if (count($orderSubscriptionHistory->SubscriptionHistory) - $index == 3) {
                            $subLabel = "3<sup>rd</sup>  Year Subscription";
                        }
                        $configPin = str_replace($dataOrderParsed->pin, $subHItem->Pin, $dataOrderParsed->link);
                        $response["subscription"][$index]["label"] = $subLabel;
                        $response["subscription"][$index]["Pin"] = $configPin;
                        $response["subscription"][$index]["CertificateStatus"] = $subHItem->CertificateStatus;
                        if ($selectedBrandData["brand_name"] == "sectigo_acme") {
                            $orderSubDetailsForApi = ["PartnerEmail" => $params["configoption1"], "ApiKey" => $params["configoption2"], "OrderNumber" => $dataOrderParsed->orderId];
                            $testMode = $params["configoption7"] == "on" ? true : false;
                            $orderSubscriptionDetail = SSL2BuyApi::getInstance($orderSubDetailsForApi, $testMode)->getAcmeOrderDetail();
                            if ($orderSubscriptionDetail->StatusCode == 0) {
                                $response["subscription"][$index]["acmeOrderDetail"] = $orderSubscriptionDetail;
                            }
                        } else {
                            $orderSubDetailsForApi = ["PartnerEmail" => $params["configoption1"], "ApiKey" => $params["configoption2"], "Pin" => $subHItem->Pin];
                            $testMode = $params["configoption7"] == "on" ? true : false;
                            $orderSubscriptionDetail = SSL2BuyApi::getInstance($orderSubDetailsForApi, $testMode)->orderSubscriptionDetail($selectedBrandData["brand_name"]);
                            if ($orderSubscriptionDetail->StatusCode == 0) {
                                if (strtolower($selectedBrandData["brand_name"]) == "globalsign") {
                                    $response["subscription"][$index]["GlobalSignOrderID"] = $orderSubscriptionDetail->GlobalSignOrderNumber;
                                    $response["subscription"][$index]["DomainName"] = $orderSubscriptionDetail->DomainName;
                                    $response["subscription"][$index]["StartDate"] = date("Y-m-d", strtotime($orderSubscriptionDetail->StartDate));
                                    $response["subscription"][$index]["EndDate"] = date("Y-m-d", strtotime($orderSubscriptionDetail->EndDate));
                                    $response["subscription"][$index]["ValidityPeriod"] = $orderSubscriptionDetail->ValidityPeriod;
                                    $response["subscription"][$index]["OrderStatus"] = $orderSubscriptionDetail->OrderStatus;
                                    $response["subscription"][$index]["ApproverEmail"] = $orderSubscriptionDetail->ApprovalEmail;
                                    $response["subscription"][$index]["ContactDetail"] = $orderSubscriptionDetail->ContactDetails;
                                } else if (strtolower($selectedBrandData["brand_name"]) == "comodo") {
                                    $response["subscription"][$index]["ComodoOrderID"] = $orderSubscriptionDetail->ComodoOrderNumber;
                                    $response["subscription"][$index]["StartDate"] = date("Y-m-d", strtotime($orderSubscriptionDetail->StartDate));
                                    $response["subscription"][$index]["EndDate"] = date("Y-m-d", strtotime($orderSubscriptionDetail->EndDate));
                                    $response["subscription"][$index]["ValidityPeriod"] = $orderSubscriptionDetail->ValidityPeriod;
                                    $response["subscription"][$index]["OrderStatus"] = $orderSubscriptionDetail->OrderStatus;
                                    $response["subscription"][$index]["WebServer"] = isset($orderSubscriptionDetail->ComodoOrderDetail->WebServer) ? $orderSubscriptionDetail->ComodoOrderDetail->WebServer : "";
                                    $response["subscription"][$index]["OrganizationName"] = isset($orderSubscriptionDetail->ComodoOrderDetail->OrganizationName) ? $orderSubscriptionDetail->ComodoOrderDetail->OrganizationName : "";
                                    $response["subscription"][$index]["Address1"] = isset($orderSubscriptionDetail->ComodoOrderDetail->Address1) ? $orderSubscriptionDetail->ComodoOrderDetail->Address1 : "";
                                    $response["subscription"][$index]["Address2"] = isset($orderSubscriptionDetail->ComodoOrderDetail->Address2) ? $orderSubscriptionDetail->ComodoOrderDetail->Address2 : "";
                                    $response["subscription"][$index]["Address3"] = isset($orderSubscriptionDetail->ComodoOrderDetail->Address3) ? $orderSubscriptionDetail->ComodoOrderDetail->Address3 : "";
                                    $response["subscription"][$index]["City"] = isset($orderSubscriptionDetail->ComodoOrderDetail->City) ? $orderSubscriptionDetail->ComodoOrderDetail->City : "";
                                    $response["subscription"][$index]["State"] = isset($orderSubscriptionDetail->ComodoOrderDetail->State) ? $orderSubscriptionDetail->ComodoOrderDetail->State : "";
                                    $response["subscription"][$index]["Country"] = isset($orderSubscriptionDetail->ComodoOrderDetail->Country) ? $orderSubscriptionDetail->ComodoOrderDetail->Country : "";
                                    $response["subscription"][$index]["PostalCode"] = isset($orderSubscriptionDetail->ComodoOrderDetail->PostalCode) ? $orderSubscriptionDetail->ComodoOrderDetail->PostalCode : "";
                                    $response["subscription"][$index]["OrganizationEmail"] = isset($orderSubscriptionDetail->ComodoOrderDetail->OrganizationEmail) ? $orderSubscriptionDetail->ComodoOrderDetail->OrganizationEmail : "";
                                    $response["subscription"][$index]["ApprovalEmail"] = isset($orderSubscriptionDetail->ComodoOrderDetail->ApprovalEmail) ? $orderSubscriptionDetail->ComodoOrderDetail->ApprovalEmail : "";
                                    $response["subscription"][$index]["productId"] = $params["configoption3"];
                                    $response["subscription"][$index]["Title"] = isset($orderSubscriptionDetail->ComodoOrderDetail->Title) ? $orderSubscriptionDetail->ComodoOrderDetail->Title : "";
                                    $response["subscription"][$index]["FirstName"] = isset($orderSubscriptionDetail->ComodoOrderDetail->FirstName) ? $orderSubscriptionDetail->ComodoOrderDetail->FirstName : "";
                                    $response["subscription"][$index]["LastName"] = isset($orderSubscriptionDetail->ComodoOrderDetail->LastName) ? $orderSubscriptionDetail->ComodoOrderDetail->LastName : "";
                                    $response["subscription"][$index]["Email"] = isset($orderSubscriptionDetail->ComodoOrderDetail->Email) ? $orderSubscriptionDetail->ComodoOrderDetail->Email : "";
                                    $response["subscription"][$index]["Phone"] = isset($orderSubscriptionDetail->ComodoOrderDetail->Phone) ? $orderSubscriptionDetail->ComodoOrderDetail->Phone : "";
                                    $response["subscription"][$index]["Fax"] = isset($orderSubscriptionDetail->ComodoOrderDetail->Fax) ? $orderSubscriptionDetail->ComodoOrderDetail->Fax : "";
                                    $response["subscription"][$index]["CSRDetail"]["DomainName"] = isset($orderSubscriptionDetail->CSRDetail->DomainName) ? $orderSubscriptionDetail->CSRDetail->DomainName : "";
                                    $response["subscription"][$index]["CSRDetail"]["Organisation"] = isset($orderSubscriptionDetail->CSRDetail->Organisation) ? $orderSubscriptionDetail->CSRDetail->Organisation : "";
                                    $response["subscription"][$index]["CSRDetail"]["OrganisationUnit"] = isset($orderSubscriptionDetail->CSRDetail->OrganisationUnit) ? $orderSubscriptionDetail->CSRDetail->OrganisationUnit : "";
                                    $response["subscription"][$index]["CSRDetail"]["Locality"] = isset($orderSubscriptionDetail->CSRDetail->Locality) ? $orderSubscriptionDetail->CSRDetail->Locality : "";
                                    $response["subscription"][$index]["CSRDetail"]["State"] = isset($orderSubscriptionDetail->CSRDetail->State) ? $orderSubscriptionDetail->CSRDetail->State : "";
                                    $response["subscription"][$index]["CSRDetail"]["Country"] = isset($orderSubscriptionDetail->CSRDetail->Country) ? $orderSubscriptionDetail->CSRDetail->Country : "";
                                    $addDomainList = "";
                                    if (!empty($orderSubscriptionDetail->AdditionalDomainList)) {
                                        foreach ($orderSubscriptionDetail->AdditionalDomainList as $domainList) {
                                            $addDomainList .= $domainList->DomainName . "</br>";
                                        }
                                    }
                                    $response["subscription"][$index]["addDomainList"] = $addDomainList;
                                } else if (strtolower($selectedBrandData["brand_name"]) == "symantec") {
                                    $response["subscription"][$index]["SymantecOrderID"] = $orderSubscriptionDetail->DigicertOrderNumber;
                                    $response["subscription"][$index]["StartDate"] = date("Y-m-d", strtotime($orderSubscriptionDetail->StartDate));
                                    $response["subscription"][$index]["EndDate"] = date("Y-m-d", strtotime($orderSubscriptionDetail->EndDate));
                                    $response["subscription"][$index]["ValidityPeriod"] = $orderSubscriptionDetail->ValidityPeriod;
                                    $response["subscription"][$index]["OrderStatus"] = $orderSubscriptionDetail->OrderStatus;
                                    $response["subscription"][$index]["DomainName"] = $orderSubscriptionDetail->DomainName;
                                    $response["subscription"][$index]["ApproverEmail"] = $orderSubscriptionDetail->ApprovalEmail;
                                    $addDomainList = "";
                                    if (!empty($orderSubscriptionDetail->AdditionalDomainList)) {
                                        foreach ($orderSubscriptionDetail->AdditionalDomainList as $domainList) {
                                            $addDomainList .= $domainList->DomainName . "</br>";
                                        }
                                    }
                                    $response["subscription"][$index]["addDomainList"] = $addDomainList;
                                    $response["subscription"][$index]["AdminContact"] = $orderSubscriptionDetail->AdminContact;
                                    $response["subscription"][$index]["TechnicalContact"] = $orderSubscriptionDetail->TechnicalContact;
                                    $response["subscription"][$index]["OrganisationDetail"] = $orderSubscriptionDetail->OrganizationDetail;
                                    $response["subscription"][$index]["CSRDetail"] = $orderSubscriptionDetail->CSRDetail;
                                } else if (strtolower($selectedBrandData["brand_name"]) == "prime") {
                                    $response["subscription"][$index]["PrimeSSLOrderID"] = $orderSubscriptionDetail->PrimeSSLOrderNumber;
                                    $response["subscription"][$index]["StartDate"] = date("Y-m-d", strtotime($orderSubscriptionDetail->StartDate));
                                    $response["subscription"][$index]["EndDate"] = date("Y-m-d", strtotime($orderSubscriptionDetail->EndDate));
                                    $response["subscription"][$index]["ValidityPeriod"] = $orderSubscriptionDetail->ValidityPeriod;
                                    $response["subscription"][$index]["OrderStatus"] = $orderSubscriptionDetail->OrderStatus;
                                    $response["subscription"][$index]["WebServer"] = isset($orderSubscriptionDetail->PrimeOrderDetail->WebServer) ? $orderSubscriptionDetail->PrimeOrderDetail->WebServer : "";
                                    $response["subscription"][$index]["OrganizationName"] = isset($orderSubscriptionDetail->PrimeOrderDetail->OrganizationName) ? $orderSubscriptionDetail->PrimeOrderDetail->OrganizationName : "";
                                    $response["subscription"][$index]["Address1"] = isset($orderSubscriptionDetail->PrimeOrderDetail->Address1) ? $orderSubscriptionDetail->PrimeOrderDetail->Address1 : "";
                                    $response["subscription"][$index]["Address2"] = isset($orderSubscriptionDetail->PrimeOrderDetail->Address2) ? $orderSubscriptionDetail->PrimeOrderDetail->Address2 : "";
                                    $response["subscription"][$index]["Address3"] = isset($orderSubscriptionDetail->PrimeOrderDetail->Address3) ? $orderSubscriptionDetail->PrimeOrderDetail->Address3 : "";
                                    $response["subscription"][$index]["City"] = isset($orderSubscriptionDetail->PrimeOrderDetail->City) ? $orderSubscriptionDetail->PrimeOrderDetail->City : "";
                                    $response["subscription"][$index]["State"] = isset($orderSubscriptionDetail->PrimeOrderDetail->State) ? $orderSubscriptionDetail->PrimeOrderDetail->State : "";
                                    $response["subscription"][$index]["Country"] = isset($orderSubscriptionDetail->PrimeOrderDetail->Country) ? $orderSubscriptionDetail->PrimeOrderDetail->Country : "";
                                    $response["subscription"][$index]["PostalCode"] = isset($orderSubscriptionDetail->PrimeOrderDetail->PostalCode) ? $orderSubscriptionDetail->PrimeOrderDetail->PostalCode : "";
                                    $response["subscription"][$index]["OrganizationEmail"] = isset($orderSubscriptionDetail->PrimeOrderDetail->OrganizationEmail) ? $orderSubscriptionDetail->PrimeOrderDetail->OrganizationEmail : "";
                                    $response["subscription"][$index]["ApprovalEmail"] = isset($orderSubscriptionDetail->PrimeOrderDetail->ApprovalEmail) ? $orderSubscriptionDetail->PrimeOrderDetail->ApprovalEmail : "";
                                    $response["subscription"][$index]["CSRDetail"]["DomainName"] = isset($orderSubscriptionDetail->CSRDetail->DomainName) ? $orderSubscriptionDetail->CSRDetail->DomainName : "";
                                    $response["subscription"][$index]["CSRDetail"]["Organisation"] = isset($orderSubscriptionDetail->CSRDetail->Organisation) ? $orderSubscriptionDetail->CSRDetail->Organisation : "";
                                    $response["subscription"][$index]["CSRDetail"]["OrganisationUnit"] = isset($orderSubscriptionDetail->CSRDetail->OrganisationUnit) ? $orderSubscriptionDetail->CSRDetail->OrganisationUnit : "";
                                    $response["subscription"][$index]["CSRDetail"]["Locality"] = isset($orderSubscriptionDetail->CSRDetail->Locality) ? $orderSubscriptionDetail->CSRDetail->Locality : "";
                                    $response["subscription"][$index]["CSRDetail"]["State"] = isset($orderSubscriptionDetail->CSRDetail->State) ? $orderSubscriptionDetail->CSRDetail->State : "";
                                    $response["subscription"][$index]["CSRDetail"]["Country"] = isset($orderSubscriptionDetail->CSRDetail->Country) ? $orderSubscriptionDetail->CSRDetail->Country : "";
                                    $addDomainList = "";
                                    if (!empty($orderSubscriptionDetail->AdditionalDomainList)) {
                                        foreach ($orderSubscriptionDetail->AdditionalDomainList as $domainList) {
                                            $addDomainList .= $domainList->DomainName . "</br>";
                                        }
                                    }
                                    $response["subscription"][$index]["addDomainList"] = $addDomainList;
                                }
                            }
                        }
                    }
                }
            }
        }
        return ["templatefile" => strtolower($selectedBrandData["brand_name"]), "vars" => $response];
    } catch (Exception $e) {
        logModuleCall("ssl2buy", "ssl2buy_ClientArea", $params, $e->getMessage(), $e->getTraceAsString());
    }
}
function ssl2buy_ChangePackage($params)
{
    return "success";
}

?>