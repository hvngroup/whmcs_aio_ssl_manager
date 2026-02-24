<?php
/**
 * Provider Controller — CRUD operations for SSL providers
 *
 * List, Add, Edit, Test Connection, Enable/Disable, Delete providers.
 *
 * @package    AioSSL\Controller
 * @author     HVN GROUP <dev@hvn.vn>
 * @copyright  2026 HVN GROUP (https://hvn.vn)
 */

namespace AioSSL\Controller;

use WHMCS\Database\Capsule;
use AioSSL\Core\EncryptionService;
use AioSSL\Core\ProviderFactory;
use AioSSL\Core\ProviderRegistry;
use AioSSL\Core\ActivityLogger;

class ProviderController extends BaseController
{
    /**
     * Credential field definitions per provider type
     */
    private $credentialFields = [
        'nicsrs' => [
            ['key' => 'api_token', 'label' => 'API Token', 'type' => 'text', 'required' => true],
        ],
        'gogetssl' => [
            ['key' => 'username', 'label' => 'Username / Email', 'type' => 'text', 'required' => true],
            ['key' => 'password', 'label' => 'Password', 'type' => 'password', 'required' => true],
        ],
        'thesslstore' => [
            ['key' => 'partner_code', 'label' => 'Partner Code', 'type' => 'text', 'required' => true],
            ['key' => 'auth_token', 'label' => 'Auth Token', 'type' => 'text', 'required' => true],
        ],
        'ssl2buy' => [
            ['key' => 'partner_email', 'label' => 'Partner Email', 'type' => 'email', 'required' => true],
            ['key' => 'api_key', 'label' => 'API Key', 'type' => 'text', 'required' => true],
        ],
    ];

    /**
     * Provider tier mapping
     */
    private $providerTiers = [
        'nicsrs'      => 'full',
        'gogetssl'    => 'full',
        'thesslstore' => 'full',
        'ssl2buy'     => 'limited',
    ];

    /**
     * Render provider management page
     */
    public function render(string $action = ''): void
    {
        switch ($action) {
            case 'add':
                $this->renderAddForm();
                break;
            case 'edit':
                $this->renderEditForm();
                break;
            default:
                $this->renderList();
                break;
        }
    }

    /**
     * Handle AJAX requests
     */
    public function handleAjax(string $action = ''): array
    {
        switch ($action) {
            case 'test':
                return $this->testConnection();
            case 'save':
                return $this->saveProvider();
            case 'toggle':
                return $this->toggleProvider();
            case 'delete':
                return $this->deleteProvider();
            case 'credential_fields':
                return $this->getCredentialFields();
            default:
                return ['success' => false, 'message' => 'Unknown action'];
        }
    }

    // ─── Render Methods ────────────────────────────────────────────

    /**
     * Render provider list table
     */
    private function renderList(): void
    {
        $providers = ProviderRegistry::getAllRecords();
        $registeredSlugs = ProviderFactory::getRegisteredSlugs();
        $existingSlugs = array_map(function ($p) { return $p->slug; }, $providers);
        $availableSlugs = array_diff($registeredSlugs, $existingSlugs);

        $this->renderTemplate('providers.php', [
            'providers'      => $providers,
            'availableSlugs' => $availableSlugs,
            'canAdd'         => !empty($availableSlugs),
        ]);
    }

    private function renderAddForm(): void
    {
        $existingSlugs = array_map(
            function ($p) { return $p->slug; },
            ProviderRegistry::getAllRecords()
        );
        $availableSlugs = array_diff(ProviderFactory::getRegisteredSlugs(), $existingSlugs);

        $this->renderTemplate('provider_edit.php', [
            'mode'             => 'add',
            'provider'         => null,
            'availableSlugs'   => $availableSlugs,
            'credentialFields' => $this->credentialFields,
            'providerTiers'    => $this->providerTiers,
        ]);
    }

    private function renderEditForm(): void
    {
        $id = (int)$this->input('id');
        $provider = Capsule::table('mod_aio_ssl_providers')->find($id);

        if (!$provider) {
            echo '<div class="alert alert-danger">Provider not found.</div>';
            return;
        }

        $credentials = [];
        if (!empty($provider->api_credentials)) {
            try {
                $credentials = EncryptionService::decryptCredentials($provider->api_credentials);
            } catch (\Exception $e) {
                $credentials = [];
            }
        }

        $fields = $this->credentialFields[$provider->slug] ?? [];

        $this->renderTemplate('provider_edit.php', [
            'mode'             => 'edit',
            'provider'         => $provider,
            'credentials'      => $credentials,
            'credentialFields' => [$provider->slug => $fields],
            'providerTiers'    => $this->providerTiers,
        ]);
    }

    // ─── AJAX Actions ──────────────────────────────────────────────

    /**
     * Test provider API connection
     */
    private function testConnection(): array
    {
        $slug = $this->input('slug');
        if (empty($slug)) {
            return ['success' => false, 'message' => 'Provider slug is required.'];
        }

        try {
            $provider = ProviderRegistry::get($slug);
            $result = $provider->testConnection();

            // Update test result in DB
            Capsule::table('mod_aio_ssl_providers')
                ->where('slug', $slug)
                ->update([
                    'last_test'   => date('Y-m-d H:i:s'),
                    'test_result' => $result['success'] ? 1 : 0,
                ]);

            ActivityLogger::log(
                $result['success'] ? 'provider_test_ok' : 'provider_test_fail',
                'provider',
                $slug,
                $result['message']
            );

            return $result;

        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Test failed: ' . $e->getMessage()];
        }
    }

    /**
     * Save provider (add or update)
     */
    private function saveProvider(): array
    {
        $id = (int)$this->rawInput('id', 0);
        $slug = $this->input('slug');
        $name = $this->input('name');
        $apiMode = $this->input('api_mode', 'live');

        // Validate
        if (empty($slug) || empty($name)) {
            return ['success' => false, 'message' => 'Provider slug and name are required.'];
        }

        if (!in_array($apiMode, ['live', 'sandbox'])) {
            $apiMode = 'live';
        }

        // Build credentials
        $credFields = $this->credentialFields[$slug] ?? [];
        $credentials = [];
        $submittedCreds = $_POST['credentials'] ?? $_REQUEST['credentials'] ?? [];

        foreach ($credFields as $field) {
            $val = isset($submittedCreds[$field['key']]) ? trim($submittedCreds[$field['key']]) : '';

            if (empty($val)) {
                // On edit, if empty, keep existing credentials
                if ($id > 0) {
                    $existing = Capsule::table('mod_aio_ssl_providers')->find($id);
                    if ($existing && !empty($existing->api_credentials)) {
                        $oldCreds = EncryptionService::decryptCredentials($existing->api_credentials);
                        $val = $oldCreds[$field['key']] ?? '';
                    }
                }

                // Still empty after fallback? Check if required
                if ($field['required'] && empty($val)) {
                    return ['success' => false, 'message' => $field['label'] . ' is required.'];
                }
            }

            $credentials[$field['key']] = $val;
        }
        
        $encryptedCreds = EncryptionService::encryptCredentials($credentials);
        $tier = $this->providerTiers[$slug] ?? 'full';

        $data = [
            'slug'            => $slug,
            'name'            => $name,
            'tier'            => $tier,
            'api_credentials' => $encryptedCreds,
            'api_mode'        => $apiMode,
            'updated_at'      => date('Y-m-d H:i:s'),
        ];

        try {
            if ($id > 0) {
                // Update
                Capsule::table('mod_aio_ssl_providers')->where('id', $id)->update($data);
                ActivityLogger::log('provider_updated', 'provider', $slug, "Provider {$name} updated");
                ProviderRegistry::clearCache();
                return ['success' => true, 'message' => "Provider {$name} updated successfully."];
            } else {
                // Insert
                $data['is_enabled'] = 1;
                $data['sort_order'] = Capsule::table('mod_aio_ssl_providers')->count() + 1;
                $data['created_at'] = date('Y-m-d H:i:s');
                $newId = Capsule::table('mod_aio_ssl_providers')->insertGetId($data);
                ActivityLogger::log('provider_added', 'provider', $slug, "Provider {$name} added (ID: {$newId})");
                ProviderRegistry::clearCache();
                return ['success' => true, 'message' => "Provider {$name} added successfully.", 'id' => $newId];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Save failed: ' . $e->getMessage()];
        }
    }

    /**
     * Toggle provider enable/disable
     */
    private function toggleProvider(): array
    {
        $id = (int)$this->input('id');
        $provider = Capsule::table('mod_aio_ssl_providers')->find($id);

        if (!$provider) {
            return ['success' => false, 'message' => 'Provider not found.'];
        }

        $newStatus = $provider->is_enabled ? 0 : 1;
        Capsule::table('mod_aio_ssl_providers')
            ->where('id', $id)
            ->update(['is_enabled' => $newStatus]);

        $statusLabel = $newStatus ? 'enabled' : 'disabled';
        ActivityLogger::log("provider_{$statusLabel}", 'provider', $provider->slug, "Provider {$provider->name} {$statusLabel}");
        ProviderRegistry::clearCache();

        return ['success' => true, 'message' => "Provider {$provider->name} {$statusLabel}.", 'enabled' => $newStatus];
    }

    /**
     * Delete provider (only if no active orders)
     */
    private function deleteProvider(): array
    {
        $id = (int)$this->input('id');
        $provider = Capsule::table('mod_aio_ssl_providers')->find($id);

        if (!$provider) {
            return ['success' => false, 'message' => 'Provider not found.'];
        }

        // Check for active orders
        $activeOrders = Capsule::table('tblsslorders')
            ->where('module', 'aio_ssl')
            ->where('status', '!=', 'Cancelled')
            ->whereRaw("JSON_EXTRACT(configdata, '$.provider') = ?", [$provider->slug])
            ->count();

        if ($activeOrders > 0) {
            return [
                'success' => false,
                'message' => "Cannot delete {$provider->name}: {$activeOrders} active order(s) exist. Disable instead.",
            ];
        }

        // Delete products associated with this provider
        Capsule::table('mod_aio_ssl_products')->where('provider_slug', $provider->slug)->delete();

        // Delete the provider
        Capsule::table('mod_aio_ssl_providers')->where('id', $id)->delete();

        ActivityLogger::log('provider_deleted', 'provider', $provider->slug, "Provider {$provider->name} deleted");
        ProviderRegistry::clearCache();

        return ['success' => true, 'message' => "Provider {$provider->name} deleted successfully."];
    }

    /**
     * Return credential fields for a given provider slug (dynamic form)
     */
    private function getCredentialFields(): array
    {
        $slug = $this->input('slug');
        $fields = $this->credentialFields[$slug] ?? [];
        $tier = $this->providerTiers[$slug] ?? 'full';

        return [
            'success' => true,
            'fields'  => $fields,
            'tier'    => $tier,
        ];
    }
}