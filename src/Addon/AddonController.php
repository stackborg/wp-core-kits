<?php
/**
 * AddonController - REST API endpoints for addon management.
 *
 * @package Stackborg\WPCoreKits
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Addon;

use Stackborg\WPCoreKits\REST\Controller;

/**
 * AddonController — REST API endpoints for addon management.
 *
 * Provides the dashboard with all addon operations:
 * listing, installing, uninstalling, activating, deactivating,
 * license management, and updates.
 *
 * All endpoints require 'manage_options' capability (admin only).
 */
class AddonController extends Controller
{
    protected string $capability = 'manage_options';

    public function __construct(
        private readonly AddonRegistry $registry,
        private readonly AddonInstaller $installer,
        private readonly AddonRemover $remover,
        private readonly AddonUpdater $updater,
        private readonly LicenseManager $licenseManager,
        private readonly FeatureManager $featureManager,
        private readonly string $coreVersion = '1.0.0',
        private readonly string $uiVersion = '1.0.0',
        private readonly ?AddonApiClient $apiClient = null,
    ) {}

    public function routes(): void
    {
        $this->get('/addons', 'index');
        $this->post('/addons/(?P<slug>[a-z0-9-]+)/install', 'install');
        $this->delete('/addons/(?P<slug>[a-z0-9-]+)', 'uninstall');
        $this->post('/addons/(?P<slug>[a-z0-9-]+)/activate', 'activate');
        $this->post('/addons/(?P<slug>[a-z0-9-]+)/deactivate', 'deactivate');
        $this->post('/addons/(?P<slug>[a-z0-9-]+)/license', 'activateLicense');
        $this->delete('/addons/(?P<slug>[a-z0-9-]+)/license', 'deactivateLicense');
        $this->post('/addons/(?P<slug>[a-z0-9-]+)/update', 'update');
        $this->post('/addons/batch-update', 'batchUpdate');
    }

    /**
     * GET /addons — List all addons with their state, features, and license info.
     */
    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        $addons = [];
        $state = $this->registry->getState();

        foreach ($this->registry->getAll() as $slug => $addon) {
            $addonState = $state[$slug] ?? [];

            // Check dependencies for each addon
            $depErrors = [];
            $requires = $addon->requires();
            if (!empty($requires)) {
                $meta = AddonMeta::fromArray([
                    'slug'     => $addon->slug(),
                    'name'     => $addon->name(),
                    'version'  => $addon->version(),
                    'type'     => $addon->type(),
                    'features' => $addon->features(),
                    'requires' => $requires,
                ]);
                $compat = VersionResolver::checkAddonCompatibility(
                    $meta,
                    $this->coreVersion,
                    $this->uiVersion,
                    addonVersionResolver: VersionResolver::addonResolver($this->registry),
                );
                $depErrors = $compat->errors;
            }

            $addons[] = [
                'slug'        => $addon->slug(),
                'name'        => $addon->name(),
                'version'     => $addon->version(),
                'type'        => $addon->type(),
                'description' => $addon->description(),
                'active'      => $this->registry->isActive($slug),
                'installed'   => true,
                'tier'        => $this->featureManager->getTier($slug),
                'features'    => $this->featureManager->getFeatureMap($slug),
                'license'     => [
                    'status' => $this->licenseManager->getStatus($slug),
                    'expiry' => $this->licenseManager->getExpiry($slug),
                ],
                'installed_at'      => $addonState['installed_at'] ?? null,
                'dependency_errors' => $depErrors,
            ];
        }

        return new \WP_REST_Response([
            'addons' => $addons,
            'count'  => count($addons),
        ]);
    }

    /**
     * POST /addons/{slug}/install — Download and install an addon.
     */
    public function install(\WP_REST_Request $request): \WP_REST_Response
    {
        $slug = $request->get_param('slug');
        $zipUrl = $request->get_param('zip_url') ?? '';
        $checksum = $request->get_param('checksum');
        $licenseKey = $request->get_param('license_key');

        if ($zipUrl === '') {
            // Try fetching from API if client is available
            if ($this->apiClient !== null) {
                $zipUrl = $this->apiClient->getDownloadUrl($slug, $licenseKey);
                if ($zipUrl === null) {
                    return new \WP_REST_Response([
                        'success' => false,
                        'message' => 'Could not get download URL for addon',
                    ], 400);
                }
            } else {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => 'zip_url is required',
                ], 400);
            }
        }

        $result = $this->installer->install($zipUrl, $checksum, $licenseKey);

        return new \WP_REST_Response([
            'success' => $result->success,
            'message' => $result->message,
            'errors'  => $result->errors,
            'meta'    => $result->meta?->toArray(),
        ], $result->success ? 200 : 400);
    }

    /**
     * DELETE /addons/{slug} — Uninstall an addon.
     */
    public function uninstall(\WP_REST_Request $request): \WP_REST_Response
    {
        $slug = $request->get_param('slug');
        $result = $this->remover->uninstall($slug);

        return new \WP_REST_Response([
            'success' => $result->success,
            'message' => $result->message,
        ], $result->success ? 200 : 400);
    }

    /**
     * POST /addons/{slug}/activate — Activate an installed addon.
     */
    public function activate(\WP_REST_Request $request): \WP_REST_Response
    {
        $slug = $request->get_param('slug');

        if (!$this->registry->isInstalled($slug)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => "Addon '{$slug}' is not installed",
            ], 404);
        }

        $activated = $this->registry->activate($slug);

        return new \WP_REST_Response([
            'success' => $activated,
            'message' => $activated ? 'Addon activated' : 'Failed to activate',
        ]);
    }

    /**
     * POST /addons/{slug}/deactivate — Deactivate an active addon.
     */
    public function deactivate(\WP_REST_Request $request): \WP_REST_Response
    {
        $slug = $request->get_param('slug');

        if (!$this->registry->isInstalled($slug)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => "Addon '{$slug}' is not installed",
            ], 404);
        }

        $deactivated = $this->registry->deactivate($slug);

        return new \WP_REST_Response([
            'success' => $deactivated,
            'message' => $deactivated ? 'Addon deactivated' : 'Failed to deactivate',
        ]);
    }

    /**
     * POST /addons/{slug}/license — Activate a license key.
     */
    public function activateLicense(\WP_REST_Request $request): \WP_REST_Response
    {
        $slug = $request->get_param('slug');
        $licenseKey = $request->get_param('license_key') ?? '';

        if ($licenseKey === '') {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'license_key is required',
            ], 400);
        }

        // Verify with API server first
        $siteUrl = function_exists('site_url') ? site_url() : 'localhost';
        $apiResponse = null;

        if ($this->apiClient !== null) {
            $apiResponse = $this->apiClient->verifyLicense($slug, $licenseKey, $siteUrl);
        }

        if ($apiResponse === null) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Could not verify license with server',
            ], 502);
        }

        $result = $this->licenseManager->activate($slug, $licenseKey, $apiResponse);

        return new \WP_REST_Response([
            'success' => $result->valid,
            'status'  => $result->status,
            'message' => $result->message,
            'expiry'  => $result->expiry,
        ], $result->valid ? 200 : 400);
    }

    /**
     * DELETE /addons/{slug}/license — Deactivate a license.
     */
    public function deactivateLicense(\WP_REST_Request $request): \WP_REST_Response
    {
        $slug = $request->get_param('slug');
        $this->licenseManager->deactivate($slug);

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'License deactivated',
        ]);
    }

    /**
     * POST /addons/{slug}/update — Update addon to latest version.
     */
    public function update(\WP_REST_Request $request): \WP_REST_Response
    {
        $slug = $request->get_param('slug');
        $zipUrl = $request->get_param('zip_url') ?? '';
        $checksum = $request->get_param('checksum');
        $licenseKey = $request->get_param('license_key');

        if ($zipUrl === '') {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'zip_url is required for updates',
            ], 400);
        }

        $result = $this->updater->update($slug, $zipUrl, $checksum, $licenseKey);

        return new \WP_REST_Response([
            'success' => $result->success,
            'message' => $result->message,
            'errors'  => $result->errors,
        ], $result->success ? 200 : 400);
    }

    /**
     * POST /addons/batch-update — Batch update all auto-eligible addons.
     */
    public function batchUpdate(\WP_REST_Request $request): \WP_REST_Response
    {
        $catalog = $request->get_param('catalog') ?? [];

        if (empty($catalog)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'catalog is required',
            ], 400);
        }

        $results = $this->updater->batchUpdate($catalog);
        $response = [];

        foreach ($results as $slug => $result) {
            $response[$slug] = [
                'success' => $result->success,
                'message' => $result->message,
            ];
        }

        return new \WP_REST_Response([
            'success' => true,
            'updated' => $response,
            'count'   => count($results),
        ]);
    }
}
