<?php
/*
 * @ https://EasyToYou.eu - IonCube v11 Decoder Online
 * @ PHP 7.1
 * @ Decoder version: 1.1.6
 * @ Release: 10/08/2022
 */

// Decoded file for php version 71.
spl_autoload_register("LibLoader");
function LibLoader($class)
{
    $searchDirs = [__DIR__ . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR, __DIR__ . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "SSL2BuyApi" . DIRECTORY_SEPARATOR, __DIR__ . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "AdminDataProvider" . DIRECTORY_SEPARATOR, __DIR__ . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "SSL2BuyProducts" . DIRECTORY_SEPARATOR, __DIR__ . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "SSL2BuyEmailHandler" . DIRECTORY_SEPARATOR, __DIR__ . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "CountryList" . DIRECTORY_SEPARATOR];
    $found = false;
    foreach ($searchDirs as $dir) {
        $classFile = $dir . ucfirst($class) . ".php";
        if (file_exists($classFile)) {
            require_once $classFile;
            $found = true;
        }
    }
}

?>