<?php
require_once('api_settings.php');

messagehelper::writeinfo('Download Certificate as Zip');

$downloadreq = new order_download_zip_request();
$downloadreq->TheSSLStoreOrderID = '';
$downloadreq->ReturnPKCS7Cert = false;
$downloadresp = $sslapi->order_download_zip($downloadreq);
messagehelper::writevarinfo($downloadresp);
if(!$downloadresp->AuthResponse->isError)
{
    $certdecoded = base64_decode($downloadresp->Zip);
    $filename = $downloadreq->TheSSLStoreOrderID . '.zip';
    file_put_contents($filename,$certdecoded);
    messagehelper::writeinfo('Certificate Zip file written to '. $filename);

    //Download certificate in PKCS7 format
    if($downloadresp->pkcs7zip!='')
    {
        $certdecoded = base64_decode($downloadresp->pkcs7zip);
        $filename = $downloadreq->TheSSLStoreOrderID . 'pkcs7.zip';
        file_put_contents($filename,$certdecoded);
        messagehelper::writeinfo('PKCS7 Certificate Zip file written to '. $filename);
    }
}
else
    messagehelper::writeerror($downloadresp->AuthResponse->Message);
?>