<?php
/*
 * @ https://EasyToYou.eu - IonCube v11 Decoder Online
 * @ PHP 7.1
 * @ Decoder version: 1.1.6
 * @ Release: 10/08/2022
 */

// Decoded file for php version 71.
if (isset($_POST["servertype"]) && $_POST["servertype"] == "ssl2buy" && isset($_POST["actionSaveConfig"]) && $_POST["actionSaveConfig"] == "SSL2Buy_setup_configurable_options") {
    $id = (int) $_GET["id"];
    $module = Illuminate\Database\Capsule\Manager::table("tblproducts")->where("id", $id)->first();
    if (empty($module)) {
        echo "<script>alert(\"Product don't exist or problem with configuration.\"); window.location.href = \"configproducts.php?action=edit&id=" . $id . "&tab=3\";</script>";
        exit;
    }
    if (empty($_POST["numberOfSans"]) || $_POST["numberOfSans"] < 1) {
        echo "<script>alert(\"Please set valid number of additional domains\"); window.location.href = \"configproducts.php?action=edit&id=" . $id . "&tab=3\";</script>";
        exit;
    }
    $productName = $module->name;
    $configurableOptionsDefaultName = "SSL2Buy - " . $productName . " - " . $module->id;
    $san_min = 1;
    $san_max = (int) $_POST["numberOfSans"];
    if (isset($_POST["deleteConfigOption"])) {
        $groupConfig = Illuminate\Database\Capsule\Manager::table("tblproductconfiggroups")->where("name", $configurableOptionsDefaultName)->first();
        $groupId = $groupConfig->id;
        $productConfigOptions = Illuminate\Database\Capsule\Manager::table("tblproductconfigoptions")->where("gid", $groupId)->first();
        $configId = $productConfigOptions->id;
        $configOptionsSub = Illuminate\Database\Capsule\Manager::table("tblproductconfigoptionssub")->where("configid", $configId)->get();
        foreach ($configOptionsSub as $key => $value) {
            Illuminate\Database\Capsule\Manager::table("tblpricing")->where("relid", $value->id)->delete();
        }
        Illuminate\Database\Capsule\Manager::table("tblproductconfigoptionssub")->where("configid", $configId)->delete();
        Illuminate\Database\Capsule\Manager::table("tblproductconfigoptions")->where("gid", $groupId)->delete();
        Illuminate\Database\Capsule\Manager::table("tblproductconfiglinks")->where("gid", $groupId)->where("pid", $id)->delete();
        Illuminate\Database\Capsule\Manager::table("tblproductconfiggroups")->where("id", $groupId)->delete();
        Illuminate\Database\Capsule\Manager::table("tblproductconfiglinks")->where("gid", $groupId)->where("pid", $id)->delete();
        echo "<script>alert(\"Configurable option has been deleted.\"); window.location.href = \"configproducts.php?action=edit&id=" . (int) $_REQUEST["id"] . "&tab=3\";</script>";
        exit;
    } else {
        $price = [$_POST["oneYear"], $_POST["twoYear"], $_POST["threeYear"]];
        $currencyRes = Illuminate\Database\Capsule\Manager::table("tblcurrencies")->get();
        $currencyArray = [];
        foreach ($currencyRes as $c) {
            $currencyArray[] = ["id" => $c->id, "rate" => $c->rate];
        }
        $groupConfig = Illuminate\Database\Capsule\Manager::table("tblproductconfiggroups")->where("name", $configurableOptionsDefaultName)->first();
        if ($groupConfig) {
            $groupId = $groupConfig->id;
            $productConfigOptions = Illuminate\Database\Capsule\Manager::table("tblproductconfigoptions")->where("gid", $groupId)->first();
            $configId = $productConfigOptions->id;
            $configOptionsSub = Illuminate\Database\Capsule\Manager::table("tblproductconfigoptionssub")->where("configid", $configId)->get();
            $existingCount = count($configOptionsSub) - 1;
            foreach ($configOptionsSub as $sub) {
                $relid = $sub->id;
                $i = (int) explode("|", $sub->optionname)[0];
                if ($i === 0) {
                    foreach ($currencyArray as $currency) {
                        Illuminate\Database\Capsule\Manager::table("tblpricing")->where("relid", $relid)->where("currency", $currency["id"])->update(["annually" => 0, "biennially" => 0, "triennially" => 0]);
                    }
                } else {
                    foreach ($currencyArray as $currency) {
                        $options = [];
                        foreach ($price as $p) {
                            $options[] = (double) ($i * $p) * $currency["rate"];
                        }
                        Illuminate\Database\Capsule\Manager::table("tblpricing")->where("relid", $relid)->where("currency", $currency["id"])->update(["annually" => $options[0], "biennially" => $options[1], "triennially" => $options[2], "asetupfee" => 0, "bsetupfee" => 0, "tsetupfee" => 0]);
                    }
                }
            }
            if ($existingCount < $san_max) {
                for ($i = $existingCount + 1; $i <= $san_max; $i++) {
                    $subId = Illuminate\Database\Capsule\Manager::table("tblproductconfigoptionssub")->insertGetId(["configid" => $configId, "optionname" => $i . "|" . $i, "sortorder" => $i]);
                    foreach ($currencyArray as $currency) {
                        $options = [];
                        foreach ($price as $p) {
                            $options[] = (double) ($i * $p) * $currency["rate"];
                        }
                        Illuminate\Database\Capsule\Manager::table("tblpricing")->insert(["type" => "configoptions", "currency" => $currency["id"], "relid" => $subId, "annually" => $options[0], "biennially" => $options[1], "triennially" => $options[2]]);
                    }
                }
            }
            if ($san_max < $existingCount) {
                for ($i = $existingCount; $san_max < $i; $i--) {
                    $sub = Illuminate\Database\Capsule\Manager::table("tblproductconfigoptionssub")->where("configid", $configId)->where("optionname", $i . "|" . $i)->first();
                    if ($sub) {
                        Illuminate\Database\Capsule\Manager::table("tblpricing")->where("relid", $sub->id)->delete();
                        Illuminate\Database\Capsule\Manager::table("tblproductconfigoptionssub")->where("id", $sub->id)->delete();
                    }
                }
            }
            echo "<script>alert(\"Configurable options updated successfully.\"); window.location.href = \"configproducts.php?action=edit&id=" . $id . "&tab=3\";</script>";
            exit;
        } else {
            $group_id = Illuminate\Database\Capsule\Manager::table("tblproductconfiggroups")->insertGetId(["name" => $configurableOptionsDefaultName, "description" => "Auto generated by module"]);
            Illuminate\Database\Capsule\Manager::table("tblproductconfiglinks")->insert(["gid" => $group_id, "pid" => $id]);
            $sanLabelName = "Number of SAN&#039;s";
            if ($module->configoption3 == 401) {
                $sanLabelName = "Standard Domain";
            }
            $optionId = Illuminate\Database\Capsule\Manager::table("tblproductconfigoptions")->insertGetId(["gid" => $group_id, "optionname" => "sannumber|" . $sanLabelName, "optiontype" => 1]);
            $zeroId = Illuminate\Database\Capsule\Manager::table("tblproductconfigoptionssub")->insertGetId(["configid" => $optionId, "optionname" => "0|0", "sortorder" => 1]);
            foreach ($currencyArray as $currency) {
                Illuminate\Database\Capsule\Manager::table("tblpricing")->insert(["type" => "configoptions", "currency" => $currency["id"], "relid" => $zeroId, "annually" => 0, "biennially" => 0, "triennially" => 0]);
            }
            for ($i = $san_min; $i <= $san_max; $i++) {
                $sortorder = $i;
                if ($module->configoption3 == 401 && $i == 1) {
                    $sortorder = 0;
                }
                $subId = Illuminate\Database\Capsule\Manager::table("tblproductconfigoptionssub")->insertGetId(["configid" => $optionId, "optionname" => $i . "|" . $i, "sortorder" => $sortorder]);
                foreach ($currencyArray as $currency) {
                    $options = [];
                    foreach ($price as $p) {
                        $options[] = (double) ($i * $p) * $currency["rate"];
                    }
                    Illuminate\Database\Capsule\Manager::table("tblpricing")->insert(["type" => "configoptions", "currency" => $currency["id"], "relid" => $subId, "annually" => $options[0], "biennially" => $options[1], "triennially" => $options[2]]);
                }
            }
            echo "<script>alert(\"Configurable options created successfully.\"); window.location.href = \"configproducts.php?action=edit&id=" . $id . "&tab=3\";</script>";
            exit;
        }
    }
} else if (isset($_POST["servertype"]) && $_POST["servertype"] == "ssl2buy" && isset($_POST["actionSaveWildcardConfig"]) && $_POST["actionSaveWildcardConfig"] == "SSL2Buy_setup_configurable_wildcard_options") {
    $id = (int) $_GET["id"];
    $module = Illuminate\Database\Capsule\Manager::table("tblproducts")->where("id", $id)->first();
    if (empty($module)) {
        echo "<script>alert(\"Product don't exist or problem with configuration.\"); window.location.href = \"configproducts.php?action=edit&id=" . $id . "&tab=3\";</script>";
        exit;
    }
    if (empty($_POST["numberOfWildcardSans"]) || $_POST["numberOfWildcardSans"] < 1) {
        echo "<script>alert(\"Please set valid number of additional wildcard domains\"); window.location.href = \"configproducts.php?action=edit&id=" . $id . "&tab=3\";</script>";
        exit;
    }
    $productName = $module->name;
    $configurableOptionsDefaultName = "SSL2Buy - Wildcard - " . $productName . " - " . $module->id;
    $wildcardsan_min = 1;
    $wildcardsan_max = (int) $_POST["numberOfWildcardSans"];
    if (isset($_POST["deleteWildcardConfigOption"])) {
        $groupConfig = Illuminate\Database\Capsule\Manager::table("tblproductconfiggroups")->where("name", $configurableOptionsDefaultName)->first();
        $groupId = $groupConfig->id;
        $productConfigOptions = Illuminate\Database\Capsule\Manager::table("tblproductconfigoptions")->where("gid", $groupId)->first();
        $configId = $productConfigOptions->id;
        $configOptionsSub = Illuminate\Database\Capsule\Manager::table("tblproductconfigoptionssub")->where("configid", $configId)->get();
        foreach ($configOptionsSub as $key => $value) {
            Illuminate\Database\Capsule\Manager::table("tblpricing")->where("relid", $value->id)->delete();
        }
        Illuminate\Database\Capsule\Manager::table("tblproductconfigoptionssub")->where("configid", $configId)->delete();
        Illuminate\Database\Capsule\Manager::table("tblproductconfigoptions")->where("gid", $groupId)->delete();
        Illuminate\Database\Capsule\Manager::table("tblproductconfiglinks")->where("gid", $groupId)->where("pid", $id)->delete();
        Illuminate\Database\Capsule\Manager::table("tblproductconfiggroups")->where("id", $groupId)->delete();
        Illuminate\Database\Capsule\Manager::table("tblproductconfiglinks")->where("gid", $groupId)->where("pid", $id)->delete();
        echo "<script>alert(\"Configurable option has been deleted.\"); window.location.href = \"configproducts.php?action=edit&id=" . (int) $_REQUEST["id"] . "&tab=3\";</script>";
        exit;
    } else {
        $price = [$_POST["oneYearWildcard"], $_POST["twoYearWildcard"], $_POST["threeYearWildcard"]];
        $currencyRes = Illuminate\Database\Capsule\Manager::table("tblcurrencies")->get();
        $currencyArray = [];
        foreach ($currencyRes as $c) {
            $currencyArray[] = ["id" => $c->id, "rate" => $c->rate];
        }
        $groupConfig = Illuminate\Database\Capsule\Manager::table("tblproductconfiggroups")->where("name", $configurableOptionsDefaultName)->first();
        if ($groupConfig) {
            $groupId = $groupConfig->id;
            $productConfigOptions = Illuminate\Database\Capsule\Manager::table("tblproductconfigoptions")->where("gid", $groupId)->first();
            $configId = $productConfigOptions->id;
            $configOptionsSub = Illuminate\Database\Capsule\Manager::table("tblproductconfigoptionssub")->where("configid", $configId)->get();
            $existingCount = count($configOptionsSub) - 1;
            foreach ($configOptionsSub as $sub) {
                $relid = $sub->id;
                $i = (int) explode("|", $sub->optionname)[0];
                if ($i === 0) {
                    foreach ($currencyArray as $currency) {
                        Illuminate\Database\Capsule\Manager::table("tblpricing")->where("relid", $relid)->where("currency", $currency["id"])->update(["annually" => 0, "biennially" => 0, "triennially" => 0]);
                    }
                } else {
                    foreach ($currencyArray as $currency) {
                        $options = [];
                        foreach ($price as $p) {
                            $options[] = (double) ($i * $p) * $currency["rate"];
                        }
                        Illuminate\Database\Capsule\Manager::table("tblpricing")->where("relid", $relid)->where("currency", $currency["id"])->update(["annually" => $options[0], "biennially" => $options[1], "triennially" => $options[2], "asetupfee" => 0, "bsetupfee" => 0, "tsetupfee" => 0]);
                    }
                }
            }
            if ($existingCount < $wildcardsan_max) {
                for ($i = $existingCount + 1; $i <= $wildcardsan_max; $i++) {
                    $subId = Illuminate\Database\Capsule\Manager::table("tblproductconfigoptionssub")->insertGetId(["configid" => $configId, "optionname" => $i . "|" . $i, "sortorder" => $i]);
                    foreach ($currencyArray as $currency) {
                        $options = [];
                        foreach ($price as $p) {
                            $options[] = (double) ($i * $p) * $currency["rate"];
                        }
                        Illuminate\Database\Capsule\Manager::table("tblpricing")->insert(["type" => "configoptions", "currency" => $currency["id"], "relid" => $subId, "annually" => $options[0], "biennially" => $options[1], "triennially" => $options[2]]);
                    }
                }
            }
            if ($wildcardsan_max < $existingCount) {
                for ($i = $existingCount; $wildcardsan_max < $i; $i--) {
                    $sub = Illuminate\Database\Capsule\Manager::table("tblproductconfigoptionssub")->where("configid", $configId)->where("optionname", $i . "|" . $i)->first();
                    if ($sub) {
                        Illuminate\Database\Capsule\Manager::table("tblpricing")->where("relid", $sub->id)->delete();
                        Illuminate\Database\Capsule\Manager::table("tblproductconfigoptionssub")->where("id", $sub->id)->delete();
                    }
                }
            }
            echo "<script>alert(\"Configurable options updated successfully.\"); window.location.href = \"configproducts.php?action=edit&id=" . $id . "&tab=3\";</script>";
            exit;
        } else {
            $group_id = Illuminate\Database\Capsule\Manager::table("tblproductconfiggroups")->insertGetId(["name" => $configurableOptionsDefaultName, "description" => "Auto generated by module"]);
            Illuminate\Database\Capsule\Manager::table("tblproductconfiglinks")->insert(["gid" => $group_id, "pid" => $id]);
            $wsanLabelName = "Number of Wildcard SAN&#039;s";
            if ($module->configoption3 == 401) {
                $wsanLabelName = "Wildcard Domain";
            }
            $optionId = Illuminate\Database\Capsule\Manager::table("tblproductconfigoptions")->insertGetId(["gid" => $group_id, "optionname" => "wildcardsannumber|" . $wsanLabelName, "optiontype" => 1]);
            $zeroId = Illuminate\Database\Capsule\Manager::table("tblproductconfigoptionssub")->insertGetId(["configid" => $optionId, "optionname" => "0|0", "sortorder" => 1]);
            foreach ($currencyArray as $currency) {
                Illuminate\Database\Capsule\Manager::table("tblpricing")->insert(["type" => "configoptions", "currency" => $currency["id"], "relid" => $zeroId, "annually" => 0, "biennially" => 0, "triennially" => 0]);
            }
            for ($i = $wildcardsan_min; $i <= $wildcardsan_max; $i++) {
                $subId = Illuminate\Database\Capsule\Manager::table("tblproductconfigoptionssub")->insertGetId(["configid" => $optionId, "optionname" => $i . "|" . $i, "sortorder" => $i]);
                foreach ($currencyArray as $currency) {
                    $options = [];
                    foreach ($price as $p) {
                        $options[] = (double) ($i * $p) * $currency["rate"];
                    }
                    Illuminate\Database\Capsule\Manager::table("tblpricing")->insert(["type" => "configoptions", "currency" => $currency["id"], "relid" => $subId, "annually" => $options[0], "biennially" => $options[1], "triennially" => $options[2]]);
                }
            }
            echo "<script>alert(\"Configurable options created successfully.\"); window.location.href = \"configproducts.php?action=edit&id=" . $id . "&tab=3\";</script>";
            exit;
        }
    }
} else if (isset($_POST["servertype"]) && $_POST["servertype"] == "ssl2buy" && isset($_POST["actionSaveDeliveryMode"]) && $_POST["actionSaveDeliveryMode"] == "SSL2Buy_setup_configurable_delivery_mode") {
    $id = (int) $_GET["id"];
    $module = Illuminate\Database\Capsule\Manager::table("tblproducts")->where("id", $id)->first();
    if (empty($module)) {
        echo "<script>alert(\"Product doesn't exist or problem with configuration.\"); \n              window.location.href = \"configproducts.php?action=edit&id=" . $id . "&tab=3\";</script>";
        exit;
    }
    $productName = $module->name;
    $configurableOptionsDefaultName = "SSL2Buy - CodeSign - DeliveryMode - " . $productName . " - " . $module->id;
    if (isset($_POST["deleteDeliveryModeConfigOption"])) {
        $groupConfig = Illuminate\Database\Capsule\Manager::table("tblproductconfiggroups")->where("name", $configurableOptionsDefaultName)->first();
        $groupId = $groupConfig->id;
        $productConfigOptions = Illuminate\Database\Capsule\Manager::table("tblproductconfigoptions")->where("gid", $groupId)->first();
        $configId = $productConfigOptions->id;
        $configOptionsSub = Illuminate\Database\Capsule\Manager::table("tblproductconfigoptionssub")->where("configid", $configId)->get();
        foreach ($configOptionsSub as $key => $value) {
            Illuminate\Database\Capsule\Manager::table("tblpricing")->where("relid", $value->id)->delete();
        }
        Illuminate\Database\Capsule\Manager::table("tblproductconfigoptionssub")->where("configid", $configId)->delete();
        Illuminate\Database\Capsule\Manager::table("tblproductconfigoptions")->where("gid", $groupId)->delete();
        Illuminate\Database\Capsule\Manager::table("tblproductconfiglinks")->where("gid", $groupId)->where("pid", $id)->delete();
        Illuminate\Database\Capsule\Manager::table("tblproductconfiggroups")->where("id", $groupId)->delete();
        Illuminate\Database\Capsule\Manager::table("tblproductconfiglinks")->where("gid", $groupId)->where("pid", $id)->delete();
        echo "<script>alert(\"Configurable option has been deleted.\"); window.location.href = \"configproducts.php?action=edit&id=" . (int) $_REQUEST["id"] . "&tab=3\";</script>";
        exit;
    } else {
        $shippingMethodArray = [];
        require_once __DIR__ . "/LibLoader.php";
        $deliveryMethods = SSL2BuyProducts::getDeliveryMethods();
        if (!empty($deliveryMethods)) {
            foreach ($deliveryMethods as $deliveryItem) {
                $inputname = $deliveryItem["inputname"];
                $shippingMethodArray[] = ["code" => $deliveryItem["code"], "name" => $deliveryItem["name"], "price" => isset($_POST[$inputname]) ? (int) $_POST[$inputname] : 0];
            }
        }
        $currencyRes = Illuminate\Database\Capsule\Manager::table("tblcurrencies")->get();
        $currencyArray = [];
        foreach ($currencyRes as $value) {
            $currencyArray[] = ["id" => $value->id, "rate" => $value->rate];
        }
        $group = Illuminate\Database\Capsule\Manager::table("tblproductconfiggroups as g")->join("tblproductconfiglinks as l", "g.id", "=", "l.gid")->where("l.pid", $id)->where("g.name", $configurableOptionsDefaultName)->select("g.id")->first();
        if ($group) {
            $group_id = $group->id;
        } else {
            $group_id = Illuminate\Database\Capsule\Manager::table("tblproductconfiggroups")->insertGetId(["name" => $configurableOptionsDefaultName, "description" => "Auto generated by module"]);
            Illuminate\Database\Capsule\Manager::table("tblproductconfiglinks")->insert(["gid" => $group_id, "pid" => $id]);
        }
        $option = Illuminate\Database\Capsule\Manager::table("tblproductconfigoptions")->where("gid", $group_id)->where("optionname", "like", "deliverymode|%")->first();
        if ($option) {
            $option_id = $option->id;
        } else {
            $option_id = Illuminate\Database\Capsule\Manager::table("tblproductconfigoptions")->insertGetId(["gid" => $group_id, "optionname" => "deliverymode|Certificate Delivery Method", "optiontype" => 1, "qtyminimum" => 0, "qtymaximum" => 0, "order" => 0, "hidden" => 0]);
        }
        foreach ($shippingMethodArray as $shippingMethod) {
            $sub = Illuminate\Database\Capsule\Manager::table("tblproductconfigoptionssub")->where("configid", $option_id)->where("optionname", $shippingMethod["code"] . "|" . $shippingMethod["name"])->first();
            if ($sub) {
                $sub_id = $sub->id;
            } else {
                $sub_id = Illuminate\Database\Capsule\Manager::table("tblproductconfigoptionssub")->insertGetId(["configid" => $option_id, "optionname" => $shippingMethod["code"] . "|" . $shippingMethod["name"], "sortorder" => 1, "hidden" => 0]);
            }
            foreach ($currencyArray as $currency) {
                $priceRow = Illuminate\Database\Capsule\Manager::table("tblpricing")->where("type", "configoptions")->where("currency", $currency["id"])->where("relid", $sub_id)->first();
                $data = ["annually" => $shippingMethod["price"], "biennially" => $shippingMethod["price"], "triennially" => $shippingMethod["price"], "asetupfee" => 0, "bsetupfee" => 0, "tsetupfee" => 0];
                if ($priceRow) {
                    Illuminate\Database\Capsule\Manager::table("tblpricing")->where("id", $priceRow->id)->update($data);
                } else {
                    $data = array_merge($data, ["type" => "configoptions", "currency" => $currency["id"], "relid" => $sub_id]);
                    Illuminate\Database\Capsule\Manager::table("tblpricing")->insert($data);
                }
            }
        }
        echo "<script>alert(\"Default Configurable options have been created.\"); \n          window.location.href = \"configproducts.php?action=edit&id=" . $id . "&tab=3\";</script>";
        exit;
    }
} else if (isset($_POST["servertype"]) && $_POST["servertype"] == "ssl2buy" && isset($_POST["actionSaveDigicertDeliveryMode"]) && $_POST["actionSaveDigicertDeliveryMode"] == "SSL2Buy_setup_configurable_digicert_delivery_mode") {
    $id = (int) $_GET["id"];
    $module = Illuminate\Database\Capsule\Manager::table("tblproducts")->where("id", $id)->first();
    if (empty($module)) {
        echo "<script>alert(\"Product doesn't exist or problem with configuration.\"); \n              window.location.href = \"configproducts.php?action=edit&id=" . $id . "&tab=3\";</script>";
        exit;
    }
    $productName = $module->name;
    $configurableOptionsDefaultName = "SSL2Buy - Digicert CodeSign - DeliveryMode - " . $productName . " - " . $module->id;
    if (isset($_POST["deleteDigicertDeliveryModeConfigOption"])) {
        $groupConfig = Illuminate\Database\Capsule\Manager::table("tblproductconfiggroups")->where("name", $configurableOptionsDefaultName)->first();
        $groupId = $groupConfig->id;
        $productConfigOptions = Illuminate\Database\Capsule\Manager::table("tblproductconfigoptions")->where("gid", $groupId)->first();
        $configId = $productConfigOptions->id;
        $configOptionsSub = Illuminate\Database\Capsule\Manager::table("tblproductconfigoptionssub")->where("configid", $configId)->get();
        foreach ($configOptionsSub as $key => $value) {
            Illuminate\Database\Capsule\Manager::table("tblpricing")->where("relid", $value->id)->delete();
        }
        Illuminate\Database\Capsule\Manager::table("tblproductconfigoptionssub")->where("configid", $configId)->delete();
        Illuminate\Database\Capsule\Manager::table("tblproductconfigoptions")->where("gid", $groupId)->delete();
        Illuminate\Database\Capsule\Manager::table("tblproductconfiglinks")->where("gid", $groupId)->where("pid", $id)->delete();
        Illuminate\Database\Capsule\Manager::table("tblproductconfiggroups")->where("id", $groupId)->delete();
        Illuminate\Database\Capsule\Manager::table("tblproductconfiglinks")->where("gid", $groupId)->where("pid", $id)->delete();
        echo "<script>alert(\"Configurable option has been deleted.\"); window.location.href = \"configproducts.php?action=edit&id=" . (int) $_REQUEST["id"] . "&tab=3\";</script>";
        exit;
    } else {
        $shippingMethodArray = [];
        require_once __DIR__ . "/LibLoader.php";
        $deliveryMethods = SSL2BuyProducts::getDigicertDeliveryMethods();
        if (!empty($deliveryMethods)) {
            foreach ($deliveryMethods as $deliveryItem) {
                $inputname = $deliveryItem["inputname"];
                $shippingMethodArray[] = ["code" => $deliveryItem["code"], "name" => $deliveryItem["name"], "price" => isset($_POST[$inputname]) ? (int) $_POST[$inputname] : 0];
            }
        }
        $currencyRes = Illuminate\Database\Capsule\Manager::table("tblcurrencies")->get();
        $currencyArray = [];
        foreach ($currencyRes as $value) {
            $currencyArray[] = ["id" => $value->id, "rate" => $value->rate];
        }
        $group = Illuminate\Database\Capsule\Manager::table("tblproductconfiggroups as g")->join("tblproductconfiglinks as l", "g.id", "=", "l.gid")->where("l.pid", $id)->where("g.name", $configurableOptionsDefaultName)->select("g.id")->first();
        if ($group) {
            $group_id = $group->id;
        } else {
            $group_id = Illuminate\Database\Capsule\Manager::table("tblproductconfiggroups")->insertGetId(["name" => $configurableOptionsDefaultName, "description" => "Auto generated by module"]);
            Illuminate\Database\Capsule\Manager::table("tblproductconfiglinks")->insert(["gid" => $group_id, "pid" => $id]);
        }
        $option = Illuminate\Database\Capsule\Manager::table("tblproductconfigoptions")->where("gid", $group_id)->where("optionname", "like", "deliverymode|%")->first();
        if ($option) {
            $option_id = $option->id;
        } else {
            $option_id = Illuminate\Database\Capsule\Manager::table("tblproductconfigoptions")->insertGetId(["gid" => $group_id, "optionname" => "deliverymode|Certificate Delivery Method", "optiontype" => 1, "qtyminimum" => 0, "qtymaximum" => 0, "order" => 0, "hidden" => 0]);
        }
        foreach ($shippingMethodArray as $shippingMethod) {
            $sub = Illuminate\Database\Capsule\Manager::table("tblproductconfigoptionssub")->where("configid", $option_id)->where("optionname", $shippingMethod["code"] . "|" . $shippingMethod["name"])->first();
            if ($sub) {
                $sub_id = $sub->id;
            } else {
                $sub_id = Illuminate\Database\Capsule\Manager::table("tblproductconfigoptionssub")->insertGetId(["configid" => $option_id, "optionname" => $shippingMethod["code"] . "|" . $shippingMethod["name"], "sortorder" => 1, "hidden" => 0]);
            }
            foreach ($currencyArray as $currency) {
                $priceRow = Illuminate\Database\Capsule\Manager::table("tblpricing")->where("type", "configoptions")->where("currency", $currency["id"])->where("relid", $sub_id)->first();
                $data = ["annually" => $shippingMethod["price"], "biennially" => $shippingMethod["price"], "triennially" => $shippingMethod["price"], "asetupfee" => 0, "bsetupfee" => 0, "tsetupfee" => 0];
                if ($priceRow) {
                    Illuminate\Database\Capsule\Manager::table("tblpricing")->where("id", $priceRow->id)->update($data);
                } else {
                    $data = array_merge($data, ["type" => "configoptions", "currency" => $currency["id"], "relid" => $sub_id]);
                    Illuminate\Database\Capsule\Manager::table("tblpricing")->insert($data);
                }
            }
        }
        echo "<script>alert(\"Default Configurable options have been created.\"); \n          window.location.href = \"configproducts.php?action=edit&id=" . $id . "&tab=3\";</script>";
        exit;
    }
} else {
    add_hook("ClientAreaHeadOutput", 2, function ($params) {
        if (isset($_REQUEST["a"]) && $_REQUEST["a"] == "confproduct") {
            $head_return = "<script type=\"text/javascript\">\n            \$(document).ready(function(){\n                \$(\"#productConfigurableOptions select option\").each(function(){\n                    var text = \$(this).text();\n                    if (text.includes(\"Setup Fee\")) {\n                        var textFixed = text.replace(\"Setup Fee\", \"\"); \n                        textFixed = textFixed.replace(\"+\", \"=\"); \n                        \$(this).text(textFixed);\n                    }\n                });\n                \$( document ).ajaxComplete(function( event, xhr, settings ) {\n                    if(settings.data.indexOf(\"cyclechange\") != -1){\n                        \$(\"#productConfigurableOptions select option\").each(function(){\n                            var text = \$(this).text();\n                            if (text.includes(\"Setup Fee\")) {\n                                var textFixed = text.replace(\"Setup Fee\", \"\"); \n                                textFixed = textFixed.replace(\"+\", \"=\"); \n                                \$(this).text(textFixed);\n                            }\n                        });\n                    }\n                });\n            });\n        </script>";
            return $head_return;
        }
    });
    add_hook("ShoppingCartValidateProductUpdate", 1, function ($vars) {
        $cartSession = $_SESSION["cart"]["products"];
        $productIndex = $vars["i"];
        if (isset($cartSession[$productIndex])) {
            $productId = $cartSession[$productIndex]["pid"];
            $productData = Illuminate\Database\Capsule\Manager::table("tblproducts")->where("id", $productId)->first();
            if ($productData->servertype === "ssl2buy" && $productData->configoption3 == 401) {
                $configoptions = $cartSession[$productIndex]["configoptions"];
                if (empty($configoptions)) {
                    return ["Please select at least 1 Standard or Wildcard Domain."];
                }
                foreach ($configoptions as $optionId => $selectedValueId) {
                    $option = Illuminate\Database\Capsule\Manager::table("tblproductconfigoptions")->where("id", $optionId)->first();
                    $selectedValue = Illuminate\Database\Capsule\Manager::table("tblproductconfigoptionssub")->where("id", $selectedValueId)->where("configid", $optionId)->first();
                    if ($selectedValue->optionname !== "0|0") {
                        return NULL;
                    }
                }
                return ["Please select at least 1 Standard or Wildcard Domain."];
            }
        }
    });
    add_hook("PreShoppingCartCheckout", 1, function ($vars) {
        if (!empty($vars["products"])) {
            foreach ($vars["products"] as $pItem) {
                $productId = $pItem["pid"];
                $productData = Illuminate\Database\Capsule\Manager::table("tblproducts")->where("id", $productId)->first();
                if ($productData->servertype === "ssl2buy" && $productData->configoption3 == 401) {
                    $isValid = 0;
                    $configoptions = $pItem["configoptions"];
                    if (!empty($configoptions)) {
                        foreach ($configoptions as $optionId => $selectedValueId) {
                            $option = Illuminate\Database\Capsule\Manager::table("tblproductconfigoptions")->where("id", $optionId)->first();
                            $selectedValue = Illuminate\Database\Capsule\Manager::table("tblproductconfigoptionssub")->where("id", $selectedValueId)->where("configid", $optionId)->first();
                            if ($selectedValue->optionname !== "0|0" && $isValid == 0) {
                                $isValid = 1;
                            }
                        }
                    }
                    if (!$isValid) {
                        $_SESSION["checkout_error"] = "Please select at least 1 Standard or Wildcard Domain.";
                        $systemUrl = WHMCS\Config\Setting::getValue("SystemURL");
                        header("Location: " . $systemUrl . "/cart.php?a=view");
                        exit;
                    }
                }
            }
        }
    });
    add_hook("ClientAreaPageCart", 1, function ($vars) {
        if (isset($_SESSION["checkout_error"])) {
            $errorMessage = $_SESSION["checkout_error"];
            unset($_SESSION["checkout_error"]);
            return ["errormessage" => $errorMessage];
        }
    });
    add_hook("ClientAreaPageUpgrade", 1, function ($vars) {
        if (isset($_GET["type"]) && $_GET["type"] == "configoptions") {
            $serviceId = $vars["id"];
            $hosting = Illuminate\Database\Capsule\Manager::table("tblhosting")->where("id", $serviceId)->first(["id", "packageid"]);
            if (!$hosting) {
                return NULL;
            }
            $productData = Illuminate\Database\Capsule\Manager::table("tblproducts")->where("id", $hosting->packageid)->first();
            if ($productData->servertype === "ssl2buy" && $productData->configoption3 == 401) {
                $currentOptions = Illuminate\Database\Capsule\Manager::table("tblhostingconfigoptions")->where("relid", $serviceId)->pluck("optionid", "configid");
                foreach ($vars["configoptions"] as &$option) {
                    $currentValueId = $currentOptions[$option["id"]] ?? NULL;
                    if (!$currentValueId) {
                    } else {
                        foreach ($option["options"] as $i => $opt) {
                            if ($opt["id"] < $currentValueId) {
                                unset($option["options"][$i]);
                            }
                        }
                    }
                }
                return ["configoptions" => $vars["configoptions"]];
            }
        }
    });
    add_hook("AfterConfigOptionsUpgrade", 1, function ($vars) {
        static $processedOrders = [];
        try {
            $upgradeId = $vars["upgradeid"];
            $upgradedata = Illuminate\Database\Capsule\Manager::table("tblupgrades")->where("id", $upgradeId)->first();
            $orderId = $upgradedata->orderid;
            if (in_array($orderId, $processedOrders)) {
                return NULL;
            }
            $processedOrders[] = $orderId;
            $serviceId = $upgradedata->relid ? $upgradedata->relid : NULL;
            $hosting = Illuminate\Database\Capsule\Manager::table("tblhosting")->where("id", $serviceId)->first(["id", "packageid"]);
            $productData = Illuminate\Database\Capsule\Manager::table("tblproducts")->where("id", $hosting->packageid)->first();
            if ($productData->servertype === "ssl2buy" && $productData->configoption3 == 401) {
                $upgradeRow = Illuminate\Database\Capsule\Manager::table("tblupgrades")->where("id", $upgradeId)->first();
                if (!$upgradeRow) {
                    return NULL;
                }
                $allUpgrades = Illuminate\Database\Capsule\Manager::table("tblupgrades")->where("orderid", $upgradeRow->orderid)->where("type", "configoptions")->get();
                $upgradeArray = [];
                foreach ($allUpgrades as $upgrade) {
                    $configOptionId = $upgrade->relid ? $upgrade->relid : NULL;
                    $oldParts = explode("=>", $upgrade->originalvalue);
                    $configId = trim($oldParts[0]);
                    $oldValueId = isset($oldParts[1]) ? trim($oldParts[1]) : "";
                    $newValueId = $upgrade->newvalue;
                    $configName = Illuminate\Database\Capsule\Manager::table("tblproductconfigoptions")->where("id", $configId)->value("optionname");
                    $baseName = explode("|", $configName);
                    $oldValueReadable = Illuminate\Database\Capsule\Manager::table("tblproductconfigoptionssub")->where("id", $oldValueId)->value("optionname");
                    $oldValParts = explode("|", $oldValueReadable);
                    $newValueReadable = Illuminate\Database\Capsule\Manager::table("tblproductconfigoptionssub")->where("id", $newValueId)->value("optionname");
                    $newValParts = explode("|", $newValueReadable);
                    $upgradeArray[trim($baseName[0])] = ["serviceId" => $upgrade->relid, "configId" => $configId, "configName" => $configName, "oldValue" => trim($oldValParts[0]), "newValue" => trim($newValParts[0])];
                }
                $sannumber = $upgradeArray["sannumber"]["newValue"] - $upgradeArray["sannumber"]["oldValue"];
                $wildcardsannumber = $upgradeArray["wildcardsannumber"]["newValue"] - $upgradeArray["wildcardsannumber"]["oldValue"];
                $order = Illuminate\Database\Capsule\Manager::table("tblsslorders")->where("serviceid", $serviceId)->first();
                if (!empty($order)) {
                    require_once __DIR__ . "/LibLoader.php";
                    $dataOrderParsed = json_decode($order->configdata);
                    $orderDetailsForApi = ["PartnerEmail" => $productData->configoption1, "ApiKey" => $productData->configoption2, "OrderNumber" => $dataOrderParsed->orderId, "AddSAN" => $sannumber, "AddWildcardSAN" => $wildcardsannumber];
                    $testMode = $productData->configoption7 == "on" ? true : false;
                    $additionalAcmePurchase = SSL2BuyApi::getInstance($orderDetailsForApi, $testMode)->acmeAdditionalPurchase();
                    if ($additionalAcmePurchase->Errors->ErrorNumber !== 0) {
                        throw new Exception($additionalAcmePurchase->Errors->ErrorMessage);
                    }
                }
            }
        } catch (Exception $e) {
            logActivity("SSL2buy - ACME Additional Purchase Error - Service ID ( " . $serviceId . " ) : " . $e->getMessage());
            return NULL;
        }
    });
    add_hook("ClientAreaHeadOutput", 2, function ($params) {
        if ($params["productinfo"]["module"] == "ssl2buy" && isset($_REQUEST["a"]) && $_REQUEST["a"] == "confproduct") {
            $head_return = "<script type=\"text/javascript\">\n                \$(document).ready(function(){\n                    setTimeout(function() {\n                        \$(\"#productConfigurableOptions\").find(\".form-group\").each(function(){\n                            if(\$(this).find(\"label\").text() == \"Standard Domain\"){\n                                if(\$.trim(\$(this).find(\"select option:first\").text()) != \"0\"){\n                                    var option2 = \$(this).find(\"select option:eq(1)\");\n                                    \$(this).find(\"select\").prepend(option2);\n                                }\n                            }\n                        });\n                    }, 100);\n                });\n            </script>";
            return $head_return;
        }
    });
}

?>