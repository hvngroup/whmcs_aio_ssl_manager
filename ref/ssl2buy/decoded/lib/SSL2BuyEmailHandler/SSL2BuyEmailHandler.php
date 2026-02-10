<?php
/*
 * @ https://EasyToYou.eu - IonCube v11 Decoder Online
 * @ PHP 7.1
 * @ Decoder version: 1.1.6
 * @ Release: 10/08/2022
 */

// Decoded file for php version 71.
class SSL2BuyEmailHandler
{
    private static $values;
    public static $configurationDetailsTemplateName = "SSL2Buy Configuration Details";
    public static $createTemplateName = "SSL2Buy Place Order";
    public static $removeTemplateName = "SSL2Buy Remove";
    private static $adminName;
    private static $instance;
    public function __construct($values)
    {
        self::$adminName = AdminDataProvider::getAdminName();
        self::$values = $values;
    }
    public static function getInstance($values = [])
    {
        self::$instance = new self($values);
        return self::$instance;
    }
    public static function createSendMail()
    {
        self::$values["messagename"] = self::$createTemplateName;
        return localAPI("sendemail", self::$values, self::$adminName);
    }
    public static function createSendMailCustom($templateName)
    {
        self::$values["messagename"] = $templateName;
        return localAPI("sendemail", self::$values, self::$adminName);
    }
}

?>