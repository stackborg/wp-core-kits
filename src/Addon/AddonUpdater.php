<?php

/**
 * AddonUpdater - checks for and applies addon updates.
 *
 * @package Stackborg\WPCoreKits
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Addon;

/**
 * AddonUpdater — checks for and applies addon updates.
 *
 * Compares installed versions against available versions
 * from the Stackborg API, then uses AddonInstaller to apply updates.
 */
class AddonUpdater
{
    public function __construct(
        private readonly AddonRegistry $registry,
        private readonly AddonInstaller $installer,
    ) {
    }

    /**
     * Check which installed addons have updates available.
     *
     * @param array<string, array{version: string, download_url: string, checksum?: string}> $catalog
     *        Available addons from API, keyed by slug
     *
     * @return array<string, array{current: string, available: string, download_url: string}>
     *         Addons that have updates, keyed by slug
     */
    public function checkUpdates(array $catalog): array
    {
        $updates = [];
        $state = $this->registry->getState();

        foreach ($state as $slug => $info) {
            if (!isset($catalog[$slug])) {
                continue; // Not in catalog — skip
            }

            $currentVersion = $info['version'];
            $availableVersion = $catalog[$slug]['version'];

            if (VersionResolver::compare($availableVersion, $currentVersion) > 0) {
                $updates[$slug] = [
                    'current'      => $currentVersion,
                    'available'    => $availableVersion,
                    'download_url' => $catalog[$slug]['download_url'],
                    'checksum'     => $catalog[$slug]['checksum'] ?? null,
                ];
            }
        }

        return $updates;
    }

    /**
     * Update a specific addon to the latest version.
     *
     * @param string      $slug       Addon slug to update
     * @param string      $zipUrl     URL to download the new version
     * @param string|null $checksum   Expected SHA256 checksum
     * @param string|null $licenseKey License key for paid addon downloads
     */
    public function update(
        string $slug,
        string $zipUrl,
        ?string $checksum = null,
        ?string $licenseKey = null,
    ): InstallResult {
        if (!$this->registry->isInstalled($slug)) {
            return InstallResult::fail("Addon '{$slug}' is not installed");
        }

        $wasActive = $this->registry->isActive($slug);

        // Deactivate before update to prevent issues
        if ($wasActive) {
            $this->registry->deactivate($slug);
        }

        // Install overwrites existing
        $result = $this->installer->install($zipUrl, $checksum, $licenseKey);

        // Re-activate if it was active before
        if ($result->success && $wasActive) {
            $this->registry->activate($slug);
        }

        return $result;
    }

    /**
     * Determine if an addon should auto-update based on its policy
     * and the version change type (major/minor/patch).
     *
     * - auto:     minor/patch = auto, major = needs confirmation
     * - security: only patch = auto, minor/major = needs confirmation
     * - manual:   always needs confirmation
     */
    public static function shouldAutoUpdate(string $policy, string $current, string $available): bool
    {
        [$curMaj, $curMin] = VersionResolver::parse($current);
        [$newMaj, $newMin] = VersionResolver::parse($available);

        $isMajor = $newMaj > $curMaj;
        $isMinor = !$isMajor && $newMin > $curMin;
        // isPatch = everything else (same major.minor, different patch)

        return match ($policy) {
            'auto'     => !$isMajor,              // auto-update minor + patch
            'security' => !$isMajor && !$isMinor,  // auto-update patch only
            'manual'   => false,                    // never auto
            default    => false,
        };
    }

    /**
     * Get pending updates separated into auto-eligible and confirmation-needed.
     *
     * @param array<string, array{version: string, download_url: string,
     *        checksum?: string, update_policy?: string}> $catalog
     * @return array{auto: array<string, array{current: string,
     *         available: string, download_url: string}>,
     *         manual: array<string, array{current: string,
     *         available: string, download_url: string}>}
     */
    public function getPendingUpdates(array $catalog): array
    {
        $updates = $this->checkUpdates($catalog);
        $auto = [];
        $manual = [];

        foreach ($updates as $slug => $info) {
            $policy = $catalog[$slug]['update_policy'] ?? 'auto';
            $canAuto = self::shouldAutoUpdate($policy, $info['current'], $info['available']);

            if ($canAuto) {
                $auto[$slug] = $info;
            } else {
                $manual[$slug] = $info;
            }
        }

        return ['auto' => $auto, 'manual' => $manual];
    }

    /**
     * Batch update all auto-eligible addons.
     *
     * @param array<string, array{version: string, download_url: string,
     *        checksum?: string, update_policy?: string}> $catalog
     * @return array<string, InstallResult>
     */
    public function batchUpdate(array $catalog): array
    {
        $pending = $this->getPendingUpdates($catalog);
        $results = [];

        foreach ($pending['auto'] as $slug => $info) {
            $results[$slug] = $this->update(
                $slug,
                $info['download_url'],
                $catalog[$slug]['checksum'] ?? null,
            );
        }

        return $results;
    }
}
