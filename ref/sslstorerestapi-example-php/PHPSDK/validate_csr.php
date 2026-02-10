<?php
require_once('api_settings.php');

$testcsr = '-----BEGIN NEW CERTIFICATE REQUEST-----
MIID3TCCAsUCAQAweTELMAkGA1UEBhMCVVMxEzARBgNVBAgMCk5ldyBNZXhpY28x
FDASBgNVBAcMC0FsYnVxdWVycXVlMRAwDgYDVQQKDAdJbmtTb2Z0MRIwEAYDVQQL
DAlNYXJrZXRpbmcxGTAXBgNVBAMMEGRlbW8uaW5rc29mdC5jb20wggEiMA0GCSqG
SIb3DQEBAQUAA4IBDwAwggEKAoIBAQDZxMc4eqWM/JgQuH2cE9bDEDkxLN79JIwQ
SlQ5dw1rhlQl9u7cZwt1rlvXh9AcydjF8BJpmzOnL9tyqpto2ba4smC5A0eY1Tkp
03piVVxqapwKyo93oYAcy1mEqw13NeFHeUhclBZQ10mJz55JLkZ0sJYt5OyYA1fV
WFdeJxOSJQi0c8qocFUb4wjh0ghgp+E57d5LARO369eZTik5JABsBaPcYuXP72wy
bzoqhoigiIMx7aPR/+Blrl+STLruz3JiVEhfZGIVWc1U2D4RknGFABDp6w5b9Hw7
FhStFXEkWdTTSvY5iATCfGBBd8AKUw/1RlaI1/8z/JXJNub+CUbhAgMBAAGgggEd
MBoGCisGAQQBgjcNAgMxDBYKNi4xLjc2MDEuMjA/BgkrBgEEAYI3FRQxMjAwAgEF
DA9BUFAyLmlua3NvZnQudXMMEElOS1NPRlRcc3lzYWRtaW4MCHczd3AuZXhlMFYG
CSqGSIb3DQEJDjFJMEcwDgYDVR0PAQH/BAQDAgTwMBYGA1UdJQQPMA0GC2CGSAGG
/W4BBxcBMB0GA1UdDgQWBBTex9WLR+kVyFSBENnT7R/qYg/jgDBmBgorBgEEAYI3
DQICMVgwVgIBAR5OAE0AaQBjAHIAbwBzAG8AZgB0ACAAUwB0AHIAbwBuAGcAIABD
AHIAeQBwAHQAbwBnAHIAYQBwAGgAaQBjACAAUAByAG8AdgBpAGQAZQByAwEAMA0G
CSqGSIb3DQEBCwUAA4IBAQCCRHG7ek7oiaTGzhbhYxdpZZeFwZTejuJbO7mbPOkJ
wSkiIy5qgHgm0Uxuw+l3eBEZu9OOT6J61RQmAx+OUbKKIB8usHWUZiLInAdnOGHn
Ax0Hsf4XVU3cuOD3xnFQnUTwHplhBwCrZPwXq7fWzm3B6FdPVnrSuyMxdQ+GnIie
TF5qXHU8SaQ8GgjNQZdZU2tKhUdjQr8THxvpZ2xJqZ/a+gRf3Uwc4b+Em3qKHimP
rpmBjsKEXSyN+7dPNliZUuA1MqFlsrLeBY4j9f9hgq2FydqgJAjnrhlvFyB4I3YZ
j04caH1WgRsRYrL3+J6w6jNFyXtUXbHKk3oT+vL+4kx+
-----END NEW CERTIFICATE REQUEST-----';


messagehelper::writeinfo('Checking CSR');
$csrrequest = new csr_request();
$csrrequest->ProductCode = 'rapidssl';
$csrrequest->CSR = $testcsr;
$checkcsrresp =  $sslapi->csr($csrrequest);
if($checkcsrresp->AuthResponse->isError)
    messagehelper::writeerror(($checkcsrresp->AuthResponse->Message));
else
    messagehelper::writevarinfo($checkcsrresp);
?>