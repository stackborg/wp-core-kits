<?php

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Stackborg\WPCoreKits\Addon\AddonUpdater;

class AddonUpdaterTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['wp_options'] = [];
    }

    // ─── shouldAutoUpdate (static, no dependencies) ────────────────

    /** @test */
    public function itAutoUpdatesPatchVersionWithAutoPolicy(): void
    {
        // auto policy: minor + patch are auto-updated
        $this->assertTrue(AddonUpdater::shouldAutoUpdate('auto', '1.0.0', '1.0.1'));
    }

    /** @test */
    public function itAutoUpdatesMinorVersionWithAutoPolicy(): void
    {
        $this->assertTrue(AddonUpdater::shouldAutoUpdate('auto', '1.0.0', '1.1.0'));
    }

    /** @test */
    public function itDoesNotAutoUpdateMajorVersionWithAutoPolicy(): void
    {
        $this->assertFalse(AddonUpdater::shouldAutoUpdate('auto', '1.9.9', '2.0.0'));
    }

    /** @test */
    public function itAutoUpdatesPatchVersionWithSecurityPolicy(): void
    {
        // security policy: only patch is auto-updated
        $this->assertTrue(AddonUpdater::shouldAutoUpdate('security', '1.0.0', '1.0.5'));
    }

    /** @test */
    public function itDoesNotAutoUpdateMinorVersionWithSecurityPolicy(): void
    {
        $this->assertFalse(AddonUpdater::shouldAutoUpdate('security', '1.0.0', '1.1.0'));
    }

    /** @test */
    public function itDoesNotAutoUpdateMajorVersionWithSecurityPolicy(): void
    {
        $this->assertFalse(AddonUpdater::shouldAutoUpdate('security', '1.0.0', '2.0.0'));
    }

    /** @test */
    public function itNeverAutoUpdatesWithManualPolicy(): void
    {
        $this->assertFalse(AddonUpdater::shouldAutoUpdate('manual', '1.0.0', '1.0.1'));
        $this->assertFalse(AddonUpdater::shouldAutoUpdate('manual', '1.0.0', '1.1.0'));
        $this->assertFalse(AddonUpdater::shouldAutoUpdate('manual', '1.0.0', '2.0.0'));
    }

    /** @test */
    public function itReturnsFalseForUnknownPolicy(): void
    {
        $this->assertFalse(AddonUpdater::shouldAutoUpdate('unknown', '1.0.0', '1.0.1'));
    }

    // ─── checkUpdates (requires registry mock) ─────────────────────

    /** @test */
    public function itDetectsAvailableUpdates(): void
    {
        // Setup: an addon is installed at 1.0.0, catalog has 1.1.0
        $GLOBALS['wp_options']['test_addons_state'] = [
            'email-templates' => [
                'active'       => true,
                'installed_at' => '2025-01-01T00:00:00Z',
                'version'      => '1.0.0',
            ],
        ];

        $registry = new \Stackborg\WPCoreKits\Addon\AddonRegistry('test_addons_state');
        $installer = $this->createMock(\Stackborg\WPCoreKits\Addon\AddonInstaller::class);

        $updater = new AddonUpdater($registry, $installer);

        $catalog = [
            'email-templates' => [
                'version'      => '1.1.0',
                'download_url' => 'https://example.com/download/email-templates-1.1.0.zip',
            ],
        ];

        $updates = $updater->checkUpdates($catalog);

        $this->assertArrayHasKey('email-templates', $updates);
        $this->assertSame('1.0.0', $updates['email-templates']['current']);
        $this->assertSame('1.1.0', $updates['email-templates']['available']);
    }

    /** @test */
    public function itSkipsAddonsNotInCatalog(): void
    {
        $GLOBALS['wp_options']['test_addons_state'] = [
            'local-addon' => [
                'active'       => true,
                'installed_at' => '2025-01-01T00:00:00Z',
                'version'      => '1.0.0',
            ],
        ];

        $registry = new \Stackborg\WPCoreKits\Addon\AddonRegistry('test_addons_state');
        $installer = $this->createMock(\Stackborg\WPCoreKits\Addon\AddonInstaller::class);

        $updater = new AddonUpdater($registry, $installer);

        // Catalog does not contain 'local-addon'
        $updates = $updater->checkUpdates([]);

        $this->assertEmpty($updates);
    }

    /** @test */
    public function itSkipsAddonsAlreadyOnLatestVersion(): void
    {
        $GLOBALS['wp_options']['test_addons_state'] = [
            'email-templates' => [
                'active'       => true,
                'installed_at' => '2025-01-01T00:00:00Z',
                'version'      => '2.0.0',
            ],
        ];

        $registry = new \Stackborg\WPCoreKits\Addon\AddonRegistry('test_addons_state');
        $installer = $this->createMock(\Stackborg\WPCoreKits\Addon\AddonInstaller::class);

        $updater = new AddonUpdater($registry, $installer);

        $catalog = [
            'email-templates' => [
                'version'      => '2.0.0',
                'download_url' => 'https://example.com/download.zip',
            ],
        ];

        $updates = $updater->checkUpdates($catalog);
        $this->assertEmpty($updates);
    }

    /** @test */
    public function itSeparatesPendingUpdatesIntoAutoAndManual(): void
    {
        $GLOBALS['wp_options']['test_addons_state'] = [
            'addon-a' => [
                'active' => true, 'installed_at' => '2025-01-01T00:00:00Z', 'version' => '1.0.0',
            ],
            'addon-b' => [
                'active' => true, 'installed_at' => '2025-01-01T00:00:00Z', 'version' => '1.0.0',
            ],
        ];

        $registry = new \Stackborg\WPCoreKits\Addon\AddonRegistry('test_addons_state');
        $installer = $this->createMock(\Stackborg\WPCoreKits\Addon\AddonInstaller::class);

        $updater = new AddonUpdater($registry, $installer);

        $catalog = [
            'addon-a' => [
                'version' => '1.0.5', 'download_url' => 'https://example.com/a.zip',
                'update_policy' => 'auto',
            ],
            'addon-b' => [
                'version' => '2.0.0', 'download_url' => 'https://example.com/b.zip',
                'update_policy' => 'auto', // major upgrade → needs confirmation even with 'auto'
            ],
        ];

        $pending = $updater->getPendingUpdates($catalog);

        // addon-a: patch with auto policy → auto
        $this->assertArrayHasKey('addon-a', $pending['auto']);
        // addon-b: major with auto policy → manual
        $this->assertArrayHasKey('addon-b', $pending['manual']);
    }

    /** @test */
    public function itReturnsFailResultWhenUpdatingUninstalledAddon(): void
    {
        $GLOBALS['wp_options']['test_addons_state'] = [];

        $registry = new \Stackborg\WPCoreKits\Addon\AddonRegistry('test_addons_state');
        $installer = $this->createMock(\Stackborg\WPCoreKits\Addon\AddonInstaller::class);

        $updater = new AddonUpdater($registry, $installer);

        $result = $updater->update('nonexistent-addon', 'https://example.com/download.zip');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not installed', $result->message);
    }
}
