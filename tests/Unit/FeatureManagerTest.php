<?php

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Stackborg\WPCoreKits\Addon\AddonRegistry;
use Stackborg\WPCoreKits\Addon\FeatureManager;
use Stackborg\WPCoreKits\Addon\LicenseGuard;
use Stackborg\WPCoreKits\Addon\LicenseManager;
use Stackborg\WPCoreKits\Contracts\AddonInterface;

class FeatureManagerTest extends TestCase
{
    private string $verifyKey = 'test_verify_key';
    private AddonRegistry $registry;
    private LicenseManager $licenseManager;
    private FeatureManager $featureManager;

    protected function setUp(): void
    {
        global $wp_options;
        $wp_options = [];

        $this->registry = new AddonRegistry('fm_test_state');
        $this->licenseManager = new LicenseManager('fm_test', $this->verifyKey);
        $this->featureManager = new FeatureManager($this->registry, $this->licenseManager);
    }

    private function createAddon(string $slug, string $type, array $features): AddonInterface
    {
        return new class($slug, $type, $features) implements AddonInterface {
            public function __construct(
                private string $slug, private string $type, private array $features
            ) {}
            public function slug(): string { return $this->slug; }
            public function name(): string { return ucfirst($this->slug); }
            public function version(): string { return '1.0.0'; }
            public function type(): string { return $this->type; }
            public function features(): array { return $this->features; }
            public function providers(): array { return []; }
            public function requires(): array { return []; }
            public function description(): string { return ''; }
            public function cleanup(): void {}
        };
    }

    private function activateLicense(string $addonSlug): void
    {
        $siteUrl = function_exists('site_url') ? site_url() : 'localhost';
        $payload = [
            'status' => 'active',
            'expiry' => gmdate('Y-m-d', strtotime('+1 year')),
            'site'   => $siteUrl,
        ];
        $signed = LicenseGuard::createSignedPayload($payload, $this->verifyKey);
        $this->licenseManager->activate($addonSlug, 'SB-TEST-KEY', $signed);
    }

    // ─── can() ──────────────────────────────────────────

    public function testCanReturnsFalseForUninstalledAddon(): void
    {
        $this->assertFalse($this->featureManager->can('ghost', 'anything'));
    }

    public function testCanReturnsFalseForInactiveAddon(): void
    {
        $addon = $this->createAddon('inactive', 'free', ['basic' => 'free']);
        $this->registry->register($addon);
        // Not activated

        $this->assertFalse($this->featureManager->can('inactive', 'basic'));
    }

    public function testCanReturnsTrueForFreeFeature(): void
    {
        $addon = $this->createAddon('myext', 'freemium', [
            'basic' => 'free', 'advanced' => 'pro',
        ]);
        $this->registry->register($addon);
        $this->registry->activate('myext');

        $this->assertTrue($this->featureManager->can('myext', 'basic'));
    }

    public function testCanReturnsFalseForProFeatureWithoutLicense(): void
    {
        $addon = $this->createAddon('myext', 'freemium', [
            'basic' => 'free', 'advanced' => 'pro',
        ]);
        $this->registry->register($addon);
        $this->registry->activate('myext');

        $this->assertFalse($this->featureManager->can('myext', 'advanced'));
    }

    public function testCanReturnsTrueForProFeatureWithValidLicense(): void
    {
        $addon = $this->createAddon('myext', 'freemium', [
            'basic' => 'free', 'advanced' => 'pro',
        ]);
        $this->registry->register($addon);
        $this->registry->activate('myext');
        $this->activateLicense('myext');

        $this->assertTrue($this->featureManager->can('myext', 'advanced'));
    }

    public function testCanReturnsFalseForNonExistentFeature(): void
    {
        $addon = $this->createAddon('myext', 'free', ['basic' => 'free']);
        $this->registry->register($addon);
        $this->registry->activate('myext');

        $this->assertFalse($this->featureManager->can('myext', 'nonexistent'));
    }

    // ─── gate() ─────────────────────────────────────────

    public function testGateExecutesCallbackWhenAccessible(): void
    {
        $addon = $this->createAddon('gated', 'free', ['feature' => 'free']);
        $this->registry->register($addon);
        $this->registry->activate('gated');

        $result = $this->featureManager->gate('gated', 'feature', fn() => 'executed');
        $this->assertSame('executed', $result);
    }

    public function testGateReturnsFallbackWhenNotAccessible(): void
    {
        $result = $this->featureManager->gate('ghost', 'feature', fn() => 'executed', 'blocked');
        $this->assertSame('blocked', $result);
    }

    // ─── getTier() ──────────────────────────────────────

    public function testGetTierReturnsNoneForInactive(): void
    {
        $this->assertSame('none', $this->featureManager->getTier('ghost'));
    }

    public function testGetTierReturnsFreeWithoutLicense(): void
    {
        $addon = $this->createAddon('tiered', 'freemium', ['x' => 'free']);
        $this->registry->register($addon);
        $this->registry->activate('tiered');

        $this->assertSame('free', $this->featureManager->getTier('tiered'));
    }

    public function testGetTierReturnsProWithLicense(): void
    {
        $addon = $this->createAddon('tiered', 'paid', ['x' => 'pro']);
        $this->registry->register($addon);
        $this->registry->activate('tiered');
        $this->activateLicense('tiered');

        $this->assertSame('pro', $this->featureManager->getTier('tiered'));
    }

    // ─── getFeatureMap() ────────────────────────────────

    public function testGetFeatureMapShowsAccessibility(): void
    {
        $addon = $this->createAddon('mapped', 'freemium', [
            'basic'    => 'free',
            'advanced' => 'pro',
        ]);
        $this->registry->register($addon);
        $this->registry->activate('mapped');

        $map = $this->featureManager->getFeatureMap('mapped');
        $this->assertTrue($map['basic']['accessible']);
        $this->assertFalse($map['advanced']['accessible']);
        $this->assertSame('free', $map['basic']['tier']);
        $this->assertSame('pro', $map['advanced']['tier']);
    }

    public function testGetFeatureMapEmptyForUnknownAddon(): void
    {
        $this->assertEmpty($this->featureManager->getFeatureMap('ghost'));
    }

    // ─── License Expiry Blocks Pro ──────────────────────

    public function testExpiredLicenseBlocksProFeature(): void
    {
        $addon = $this->createAddon('expired', 'paid', ['premium' => 'pro']);
        $this->registry->register($addon);
        $this->registry->activate('expired');

        // Activate with expired date
        $siteUrl = function_exists('site_url') ? site_url() : 'localhost';
        $payload = [
            'status' => 'active',
            'expiry' => '2020-01-01', // expired
            'site'   => $siteUrl,
        ];
        $signed = LicenseGuard::createSignedPayload($payload, $this->verifyKey);
        $this->licenseManager->activate('expired', 'SB-KEY', $signed);

        $this->assertFalse($this->featureManager->can('expired', 'premium'));
    }
}
