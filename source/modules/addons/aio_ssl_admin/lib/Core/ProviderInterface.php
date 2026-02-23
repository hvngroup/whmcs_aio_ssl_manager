<?php
/**
 * Provider Interface — Contract for all SSL provider plugins
 *
 * Every SSL provider (NicSRS, GoGetSSL, TheSSLStore, SSL2Buy) must
 * implement this interface. Methods that are unsupported by a provider
 * should throw UnsupportedOperationException.
 *
 * @package    AioSSL\Core
 * @author     HVN GROUP <dev@hvn.vn>
 * @copyright  2026 HVN GROUP (https://hvn.vn)
 */

namespace AioSSL\Core;

interface ProviderInterface
{
    // ─── Identity ──────────────────────────────────────────────────

    /**
     * Unique slug identifier (e.g., 'nicsrs', 'gogetssl')
     *
     * @return string
     */
    public function getSlug(): string;

    /**
     * Human-readable display name (e.g., 'NicSRS', 'GoGetSSL')
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Provider tier: 'full' or 'limited'
     * Full: complete API lifecycle management
     * Limited: basic ordering with external management (e.g., SSL2Buy)
     *
     * @return string
     */
    public function getTier(): string;

    /**
     * Provider capabilities for dynamic UI rendering
     *
     * @return array e.g., ['order','reissue','renew','revoke','cancel','download',
     *                       'dcv_email','dcv_http','dcv_cname','balance']
     */
    public function getCapabilities(): array;

    // ─── Connection ────────────────────────────────────────────────

    /**
     * Test API connection with current credentials
     *
     * @return array ['success' => bool, 'message' => string, 'balance' => float|null]
     */
    public function testConnection(): array;

    /**
     * Get account balance (if supported)
     *
     * @return array ['balance' => float, 'currency' => string]
     * @throws UnsupportedOperationException
     */
    public function getBalance(): array;

    // ─── Product Catalog ───────────────────────────────────────────

    /**
     * Fetch all available products from provider
     *
     * @return NormalizedProduct[]
     */
    public function fetchProducts(): array;

    /**
     * Fetch pricing for a specific product
     *
     * @param string $productCode Provider-specific product code
     * @return array Pricing data ['12' => price, '24' => price, ...]
     */
    public function fetchPricing(string $productCode): array;

    // ─── Order Lifecycle ───────────────────────────────────────────

    /**
     * Validate order parameters before submission
     *
     * @param array $params Order parameters
     * @return array ['valid' => bool, 'errors' => string[]]
     */
    public function validateOrder(array $params): array;

    /**
     * Place a new SSL certificate order
     *
     * @param array $params [
     *   'product_code' => string,
     *   'period'       => int (months),
     *   'csr'          => string,
     *   'server_type'  => int,
     *   'domains'      => string[],
     *   'dcv_method'   => string,
     *   'admin_contact'=> array (OV/EV),
     *   'tech_contact' => array (OV/EV),
     *   'org_info'     => array (OV/EV),
     * ]
     * @return array ['order_id' => string, 'status' => string, 'extra' => array]
     */
    public function placeOrder(array $params): array;

    /**
     * Get current order status and certificate data
     *
     * @param string $orderId Provider's remote order ID
     * @return array ['status'=>string, 'certificate'=>array|null, 'domains'=>string[], ...]
     */
    public function getOrderStatus(string $orderId): array;

    /**
     * Download issued certificate
     *
     * @param string $orderId
     * @return array ['cert'=>string, 'ca'=>string, 'private_key'=>string|null]
     * @throws UnsupportedOperationException
     */
    public function downloadCertificate(string $orderId): array;

    /**
     * Reissue certificate with new CSR
     *
     * @param string $orderId
     * @param array  $params ['csr'=>string, 'dcv_method'=>string, ...]
     * @return array ['success'=>bool, 'message'=>string]
     * @throws UnsupportedOperationException
     */
    public function reissueCertificate(string $orderId, array $params): array;

    /**
     * Renew certificate
     *
     * @param string $orderId
     * @param array  $params
     * @return array ['order_id'=>string, 'status'=>string]
     * @throws UnsupportedOperationException
     */
    public function renewCertificate(string $orderId, array $params): array;

    /**
     * Revoke certificate
     *
     * @param string $orderId
     * @param string $reason
     * @return array ['success'=>bool, 'message'=>string]
     * @throws UnsupportedOperationException
     */
    public function revokeCertificate(string $orderId, string $reason = ''): array;

    /**
     * Cancel pending order
     *
     * @param string $orderId
     * @return array ['success'=>bool, 'message'=>string]
     * @throws UnsupportedOperationException
     */
    public function cancelOrder(string $orderId): array;

    // ─── DCV Management ────────────────────────────────────────────

    /**
     * Get available DCV email addresses for a domain
     *
     * @param string $domain
     * @return string[] List of available validation emails
     * @throws UnsupportedOperationException
     */
    public function getDcvEmails(string $domain): array;

    /**
     * Resend DCV validation email
     *
     * @param string $orderId
     * @param string $email
     * @return array ['success'=>bool, 'message'=>string]
     * @throws UnsupportedOperationException
     */
    public function resendDcvEmail(string $orderId, string $email = ''): array;

    /**
     * Change DCV method for an order
     *
     * @param string $orderId
     * @param string $method  'email'|'http'|'https'|'cname'
     * @param array  $params  Additional parameters
     * @return array ['success'=>bool, 'message'=>string]
     * @throws UnsupportedOperationException
     */
    public function changeDcvMethod(string $orderId, string $method, array $params = []): array;

    // ─── SSL2Buy Specific (Limited Tier) ───────────────────────────

    /**
     * Get external configuration link (SSL2Buy)
     *
     * @param string $orderId
     * @return array ['url'=>string, 'pin'=>string|null]
     * @throws UnsupportedOperationException
     */
    public function getConfigurationLink(string $orderId): array;
}