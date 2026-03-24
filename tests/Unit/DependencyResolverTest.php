<?php

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Stackborg\WPCoreKits\Addon\AddonMeta;
use Stackborg\WPCoreKits\Addon\VersionResolver;
use Stackborg\WPCoreKits\Addon\AddonRegistry;
use Stackborg\WPCoreKits\Contracts\AddonInterface;

/**
 * Tests for dependency resolution:
 * - Addon→Addon dependency
 * - Addon→Plugin dependency
 * - Host plugin version check
 */
class DependencyResolverTest extends TestCase
{
    private function makeMeta(array $requires): AddonMeta
    {
        return AddonMeta::fromArray([
            'slug' => 'test-addon',
            'name' => 'Test Addon',
            'version' => '1.0.0',
            'type' => 'free',
            'features' => ['basic' => 'free'],
            'requires' => $requires,
        ]);
    }

    private function mockAddon(string $slug, string $version): AddonInterface
    {
        return new class($slug, $version) implements AddonInterface {
            public function __construct(private string $s, private string $v) {}
            public function slug(): string { return $this->s; }
            public function name(): string { return 'Mock'; }
            public function version(): string { return $this->v; }
            public function type(): string { return 'free'; }
            public function features(): array { return []; }
            public function providers(): array { return []; }
            public function requires(): array { return []; }
            public function description(): string { return ''; }
            public function tables(): array { return []; }
            public function cleanup(): void {}
        };
    }

    // ─── Host Plugin Version ─────────────────────────

    public function testHostPluginVersionSatisfied(): void
    {
        $meta = $this->makeMeta(['plugin' => '>=1.0.0']);
        $result = VersionResolver::checkAddonCompatibility($meta, '1.0.0', '1.0.0', '8.2.0', '2.0.0');
        $this->assertTrue($result->compatible);
    }

    public function testHostPluginVersionTooLow(): void
    {
        $meta = $this->makeMeta(['plugin' => '>=2.0.0']);
        $result = VersionResolver::checkAddonCompatibility($meta, '1.0.0', '1.0.0', '8.2.0', '1.0.0');
        $this->assertFalse($result->compatible);
        $this->assertStringContainsString('plugin', $result->errors[0]);
    }

    public function testHostPluginVersionCaretConstraint(): void
    {
        $meta = $this->makeMeta(['plugin' => '^1.2.0']);
        // 1.5.0 is within ^1.2.0 (>=1.2.0 <2.0.0)
        $result = VersionResolver::checkAddonCompatibility($meta, '1.0.0', '1.0.0', '8.2.0', '1.5.0');
        $this->assertTrue($result->compatible);

        // 2.0.0 is outside ^1.2.0
        $result2 = VersionResolver::checkAddonCompatibility($meta, '1.0.0', '1.0.0', '8.2.0', '2.0.0');
        $this->assertFalse($result2->compatible);
    }

    public function testHostPluginSkippedWhenNull(): void
    {
        $meta = $this->makeMeta(['plugin' => '>=5.0.0']);
        // pluginVersion is null — requirement should be skipped
        $result = VersionResolver::checkAddonCompatibility($meta, '1.0.0', '1.0.0', '8.2.0', null);
        $this->assertTrue($result->compatible);
    }

    // ─── Addon→Addon Dependency ──────────────────────

    public function testAddonDependencySatisfied(): void
    {
        $meta = $this->makeMeta(['addons' => ['automation' => '>=1.0.0']]);
        $resolver = fn(string $slug) => $slug === 'automation' ? '2.0.0' : null;

        $result = VersionResolver::checkAddonCompatibility(
            $meta, '1.0.0', '1.0.0', '8.2.0', null, $resolver
        );
        $this->assertTrue($result->compatible);
    }

    public function testAddonDependencyNotInstalled(): void
    {
        $meta = $this->makeMeta(['addons' => ['automation' => '>=1.0.0']]);
        $resolver = fn(string $slug) => null; // Nothing installed

        $result = VersionResolver::checkAddonCompatibility(
            $meta, '1.0.0', '1.0.0', '8.2.0', null, $resolver
        );
        $this->assertFalse($result->compatible);
        $this->assertStringContainsString('not installed', $result->errors[0]);
    }

    public function testAddonDependencyVersionTooLow(): void
    {
        $meta = $this->makeMeta(['addons' => ['automation' => '>=2.0.0']]);
        $resolver = fn(string $slug) => '1.0.0'; // Installed but old

        $result = VersionResolver::checkAddonCompatibility(
            $meta, '1.0.0', '1.0.0', '8.2.0', null, $resolver
        );
        $this->assertFalse($result->compatible);
        $this->assertStringContainsString('installed 1.0.0', $result->errors[0]);
    }

    public function testMultipleAddonDependencies(): void
    {
        $meta = $this->makeMeta([
            'addons' => [
                'automation' => '>=1.0.0',
                'templates'  => '>=2.0.0',
            ],
        ]);
        $resolver = fn(string $slug) => match ($slug) {
            'automation' => '1.5.0',
            'templates' => '1.0.0', // Too low!
            default => null,
        };

        $result = VersionResolver::checkAddonCompatibility(
            $meta, '1.0.0', '1.0.0', '8.2.0', null, $resolver
        );
        $this->assertFalse($result->compatible);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('templates', $result->errors[0]);
    }

    // ─── Addon→Plugin Dependency ─────────────────────

    public function testPluginDependencySatisfied(): void
    {
        $meta = $this->makeMeta(['plugins' => ['woocommerce' => '>=8.0.0']]);
        $pluginResolver = fn(string $slug) => $slug === 'woocommerce' ? '9.0.0' : null;

        $result = VersionResolver::checkAddonCompatibility(
            $meta, '1.0.0', '1.0.0', '8.2.0', null, null, $pluginResolver
        );
        $this->assertTrue($result->compatible);
    }

    public function testPluginDependencyNotInstalled(): void
    {
        $meta = $this->makeMeta(['plugins' => ['woocommerce' => '>=8.0.0']]);
        $pluginResolver = fn(string $slug) => null;

        $result = VersionResolver::checkAddonCompatibility(
            $meta, '1.0.0', '1.0.0', '8.2.0', null, null, $pluginResolver
        );
        $this->assertFalse($result->compatible);
        $this->assertStringContainsString('not installed/active', $result->errors[0]);
    }

    public function testPluginDependencyVersionMismatch(): void
    {
        $meta = $this->makeMeta(['plugins' => ['woocommerce' => '>=9.0.0']]);
        $pluginResolver = fn(string $slug) => '8.5.0';

        $result = VersionResolver::checkAddonCompatibility(
            $meta, '1.0.0', '1.0.0', '8.2.0', null, null, $pluginResolver
        );
        $this->assertFalse($result->compatible);
    }

    // ─── Combined Dependencies ───────────────────────

    public function testAllDependencyTypesTogether(): void
    {
        $meta = $this->makeMeta([
            'core'    => '>=1.0.0',
            'ui'      => '>=1.0.0',
            'php'     => '>=8.2',
            'plugin'  => '>=1.5.0',
            'addons'  => ['templates' => '>=1.0.0'],
            'plugins' => ['woocommerce' => '>=8.0.0'],
        ]);

        $addonResolver = fn(string $slug) => '1.2.0';
        $pluginResolver = fn(string $slug) => '8.5.0';

        $result = VersionResolver::checkAddonCompatibility(
            $meta, '1.0.0', '1.0.0', '8.2.0', '2.0.0', $addonResolver, $pluginResolver
        );
        $this->assertTrue($result->compatible);
    }

    public function testMultipleFailures(): void
    {
        $meta = $this->makeMeta([
            'plugin'  => '>=5.0.0',
            'addons'  => ['automation' => '>=3.0.0'],
            'plugins' => ['woocommerce' => '>=10.0.0'],
        ]);

        $result = VersionResolver::checkAddonCompatibility(
            $meta, '1.0.0', '1.0.0', '8.2.0', '1.0.0',
            fn($s) => '1.0.0',
            fn($s) => '8.0.0'
        );
        $this->assertFalse($result->compatible);
        $this->assertCount(3, $result->errors);
    }

    // ─── addonResolver Factory ───────────────────────

    public function testAddonResolverReturnsVersion(): void
    {
        global $wp_options;
        $wp_options = [];

        $registry = new AddonRegistry('dep_test_state');
        $registry->register($this->mockAddon('templates', '1.5.0'));

        $resolver = VersionResolver::addonResolver($registry);
        $this->assertSame('1.5.0', $resolver('templates'));
        $this->assertNull($resolver('nonexistent'));
    }
}
