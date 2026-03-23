<?php
/**
 * FeatureManager - single-line feature access control.
 *
 * @package Stackborg\WPCoreKits
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Addon;

/**
 * FeatureManager — single-line feature access control.
 *
 * Combines addon state + license verification for feature gating.
 * This is the primary API that plugin code uses:
 *
 *   if (FeatureManager::can($registry, $licenseManager, 'automation', 'conditional_logic')) {
 *       // Show the feature
 *   }
 */
class FeatureManager
{
    public function __construct(
        private readonly AddonRegistry $registry,
        private readonly LicenseManager $licenseManager,
    ) {}

    /**
     * Check if a specific addon feature is accessible.
     *
     * Decision tree:
     * 1. Addon installed? → No → false
     * 2. Addon active? → No → false
     * 3. Feature exists? → No → false
     * 4. Feature tier = 'free'? → true
     * 5. Feature tier = 'pro'? → LicenseManager::isValid() → true/false
     */
    public function can(string $addonSlug, string $feature): bool
    {
        // Check 1 & 2: Addon must be installed and active
        if (!$this->registry->isActive($addonSlug)) {
            return false;
        }

        // Check 3: Get feature tier from addon metadata
        $tier = $this->getFeatureTier($addonSlug, $feature);
        if ($tier === null) {
            return false; // Feature doesn't exist
        }

        // Check 4: Free features always accessible
        if ($tier === 'free') {
            return true;
        }

        // Check 5: Pro features require valid license
        if ($tier === 'pro') {
            return $this->licenseManager->isValid($addonSlug);
        }

        return false;
    }

    /**
     * Execute a callback only if the feature is accessible.
     *
     * @param string   $addonSlug Addon slug
     * @param string   $feature   Feature slug
     * @param callable $callback  Code to execute if feature is accessible
     * @param mixed    $fallback  Value to return if feature is not accessible
     * @return mixed Result of callback or fallback
     */
    public function gate(string $addonSlug, string $feature, callable $callback, mixed $fallback = null): mixed
    {
        if ($this->can($addonSlug, $feature)) {
            return $callback();
        }

        return $fallback;
    }

    /**
     * Get the current tier for an addon (based on license status).
     *
     * @return string 'pro'|'free'|'none'
     */
    public function getTier(string $addonSlug): string
    {
        if (!$this->registry->isActive($addonSlug)) {
            return 'none';
        }

        if ($this->licenseManager->isValid($addonSlug)) {
            return 'pro';
        }

        return 'free';
    }

    /**
     * Get feature tier from addon metadata.
     */
    private function getFeatureTier(string $addonSlug, string $feature): ?string
    {
        $addon = $this->registry->get($addonSlug);
        if ($addon === null) {
            return null;
        }

        $features = $addon->features();
        return $features[$feature] ?? null;
    }

    /**
     * Get all features for an addon with their accessibility status.
     *
     * @return array<string, array{tier: string, accessible: bool}>
     */
    public function getFeatureMap(string $addonSlug): array
    {
        $addon = $this->registry->get($addonSlug);
        if ($addon === null) {
            return [];
        }

        $map = [];
        foreach ($addon->features() as $feature => $tier) {
            $map[$feature] = [
                'tier'       => $tier,
                'accessible' => $this->can($addonSlug, $feature),
            ];
        }

        return $map;
    }
}
