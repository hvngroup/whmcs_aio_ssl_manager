<?php
/**
 * AES-256-CBC Encryption for provider API credentials
 *
 * Derives key from WHMCS cc_encryption_hash + module-specific salt.
 *
 * @package    AioSSL\Core
 * @author     HVN GROUP <dev@hvn.vn>
 * @copyright  2026 HVN GROUP (https://hvn.vn)
 */

namespace AioSSL\Core;

class EncryptionService
{
    private const CIPHER = 'AES-256-CBC';
    private const SALT = '|aio_ssl_v1';
    private const SEPARATOR = '::';

    /**
     * Derive encryption key from WHMCS hash + module salt
     *
     * @return string 32-byte key
     */
    private static function getKey(): string
    {
        $hash = '';

        // Try WHMCS 8.x method first
        if (class_exists('\WHMCS\Security\Encryption')) {
            try {
                $hash = \WHMCS\Security\Encryption::getEncryptionHash();
            } catch (\Exception $e) {
                // fallback below
            }
        }

        // Fallback: global cc_encryption_hash
        if (empty($hash) && isset($GLOBALS['cc_encryption_hash'])) {
            $hash = $GLOBALS['cc_encryption_hash'];
        }

        // Fallback: read from configuration.php
        if (empty($hash)) {
            $configFile = ROOTDIR . '/configuration.php';
            if (file_exists($configFile)) {
                include $configFile;
                if (isset($cc_encryption_hash)) {
                    $hash = $cc_encryption_hash;
                }
            }
        }

        if (empty($hash)) {
            throw new \RuntimeException('Unable to retrieve WHMCS encryption hash.');
        }

        return hash('sha256', $hash . self::SALT, true);
    }

    /**
     * Encrypt plaintext
     *
     * @param string $plaintext
     * @return string Base64-encoded ciphertext
     * @throws \RuntimeException
     */
    public static function encrypt(string $plaintext): string
    {
        if (empty($plaintext)) {
            return '';
        }

        $key = self::getKey();
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $iv = random_bytes($ivLength);

        $encrypted = openssl_encrypt($plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);

        if ($encrypted === false) {
            throw new \RuntimeException('Encryption failed: ' . openssl_error_string());
        }

        // HMAC for integrity verification
        $hmac = hash_hmac('sha256', $iv . $encrypted, $key, true);

        return base64_encode($hmac . $iv . $encrypted);
    }

    /**
     * Decrypt ciphertext
     *
     * @param string $ciphertext Base64-encoded
     * @return string
     * @throws \RuntimeException
     */
    public static function decrypt(string $ciphertext): string
    {
        if (empty($ciphertext)) {
            return '';
        }

        $key = self::getKey();
        $data = base64_decode($ciphertext, true);

        if ($data === false) {
            throw new \RuntimeException('Invalid base64 ciphertext.');
        }

        $hmacLength = 32; // SHA-256 output
        $ivLength = openssl_cipher_iv_length(self::CIPHER);

        if (strlen($data) < $hmacLength + $ivLength + 1) {
            // Legacy format: iv::encrypted (backward compat)
            return self::decryptLegacy($ciphertext);
        }

        $hmac = substr($data, 0, $hmacLength);
        $iv = substr($data, $hmacLength, $ivLength);
        $encrypted = substr($data, $hmacLength + $ivLength);

        // Verify HMAC
        $calcHmac = hash_hmac('sha256', $iv . $encrypted, $key, true);
        if (!hash_equals($hmac, $calcHmac)) {
            throw new \RuntimeException('HMAC verification failed. Data may be tampered.');
        }

        $decrypted = openssl_decrypt($encrypted, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);

        if ($decrypted === false) {
            throw new \RuntimeException('Decryption failed: ' . openssl_error_string());
        }

        return $decrypted;
    }

    /**
     * Decrypt legacy format (iv::encrypted without HMAC)
     *
     * @param string $ciphertext
     * @return string
     */
    private static function decryptLegacy(string $ciphertext): string
    {
        $data = base64_decode($ciphertext, true);
        if ($data === false || strpos($data, self::SEPARATOR) === false) {
            throw new \RuntimeException('Invalid legacy ciphertext format.');
        }

        $parts = explode(self::SEPARATOR, $data, 2);
        if (count($parts) !== 2) {
            throw new \RuntimeException('Invalid legacy ciphertext structure.');
        }

        [$iv, $encrypted] = $parts;
        $key = self::getKey();

        $decrypted = openssl_decrypt($encrypted, self::CIPHER, $key, 0, $iv);

        if ($decrypted === false) {
            throw new \RuntimeException('Legacy decryption failed.');
        }

        return $decrypted;
    }

    /**
     * Encrypt JSON credentials array
     *
     * @param array $credentials
     * @return string
     */
    public static function encryptCredentials(array $credentials): string
    {
        return self::encrypt(json_encode($credentials));
    }

    /**
     * Decrypt JSON credentials to array
     *
     * @param string $encrypted
     * @return array
     */
    public static function decryptCredentials(string $encrypted): array
    {
        $json = self::decrypt($encrypted);
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }
}