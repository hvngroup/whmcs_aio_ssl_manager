<?php
/*
 * @ https://EasyToYou.eu - IonCube v11 Decoder Online
 * @ PHP 7.1
 * @ Decoder version: 1.1.6
 * @ Release: 10/08/2022
 */

// Decoded file for php version 71.
class AdminDataProvider
{
    public static function getAdminName()
    {
        $admins = Illuminate\Database\Capsule\Manager::table("tbladmins")->where("roleid", 1)->limit(1)->get();
        if (!empty($admins) && is_object($admins[0])) {
            $adminuser = $admins[0]->username;
            return $adminuser;
        }
        return false;
    }
}

?>