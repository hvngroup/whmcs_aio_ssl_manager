<?php
/**
 * ActionDispatcher — Routes AJAX step parameters to ActionController methods
 *
 * Supports 25+ aliases for backward compatibility with legacy modules
 *
 * @package    AioSSL\Server
 * @author     HVN GROUP <dev@hvn.vn>
 * @copyright  2026 HVN GROUP (https://hvn.vn)
 */

namespace AioSSL\Server;

class ActionDispatcher
{
    /**
     * Step → ActionController method mapping (with aliases)
     */
    private static $routes = [
        // CSR
        'generateCSR'    => 'generateCSR',
        'generateCsr'    => 'generateCSR',
        'decodeCsr'      => 'decodeCsr',
        'decodeCSR'      => 'decodeCsr',

        // Application
        'submitApply'    => 'submitApply',
        'applyssl'       => 'submitApply',     // Legacy NicSRS
        'saveDraft'      => 'saveDraft',
        'savedraft'      => 'saveDraft',

        // Status
        'refreshStatus'  => 'refreshStatus',
        'refresh'        => 'refreshStatus',

        // Download
        'downloadCert'   => 'downloadCert',
        'downCert'       => 'downloadCert',
        'downcert'       => 'downloadCert',
        'download'       => 'downloadCert',
        'downkey'        => 'downloadCert',    // Legacy: private key download

        // DCV
        'getDcvEmails'   => 'getDcvEmails',
        'resendDcvEmail' => 'resendDcvEmail',
        'resendDCVEmail' => 'resendDcvEmail',
        'resendDCV'      => 'resendDcvEmail',
        'batchUpdateDcv' => 'batchUpdateDcv',
        'batchUpdateDCV' => 'batchUpdateDcv',

        // Reissue / Renew
        'submitReissue'  => 'submitReissue',
        'reissue'        => 'submitReissue',
        'submitReplace'  => 'submitReissue',   // Legacy GoGetSSL
        'replacessl'     => 'submitReissue',   // Legacy NicSRS
        'renew'          => 'renew',
        'renewCertificate'=> 'renew',

        // Revoke / Cancel
        'revoke'         => 'revoke',
        'revokeOrder'    => 'revoke',
        'cancelOrder'    => 'cancelOrder',
        'cancleOrder'    => 'cancelOrder',     // Legacy typo from GoGetSSL module
        'cancel'         => 'cancelOrder',

        // SSL2Buy specific
        'getConfigLink'  => 'getConfigLink',
    ];

    /**
     * Dispatch AJAX action request
     *
     * @param string $step   Step parameter from request
     * @param array  $params WHMCS service params
     * @return array JSON-serializable response
     */
    public static function dispatch(string $step, array $params): array
    {
        $method = self::$routes[$step] ?? null;

        if (!$method) {
            return ['success' => false, 'message' => 'Unknown action: ' . htmlspecialchars($step)];
        }

        if (!method_exists(ActionController::class, $method)) {
            return ['success' => false, 'message' => 'Action not implemented: ' . $method];
        }

        try {
            return ActionController::$method($params);
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Check if a step is a valid action
     *
     * @param string $step
     * @return bool
     */
    public static function isValidAction(string $step): bool
    {
        return isset(self::$routes[$step]);
    }
}