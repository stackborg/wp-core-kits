<?php

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Stackborg\WPCoreKits\Addon\AddonMeta;

class AddonMetaTest extends TestCase
{
    // ─── Valid Parsing ───────────────────────────────────

    public function testParsesValidArray(): void
    {
        $meta = AddonMeta::fromArray([
            'slug'        => 'automation',
            'name'        => 'Automation',
            'version'     => '1.2.0',
            'type'        => 'freemium',
            'description' => 'Email automation sequences',
            'icon'        => 'zap',
            'features'    => ['basic' => 'free', 'advanced' => 'pro'],
            'requires'    => ['core' => '>=1.0.0', 'php' => '>=8.2'],
            'price'       => ['yearly' => 49],
        ]);

        $this->assertSame('automation', $meta->slug);
        $this->assertSame('Automation', $meta->name);
        $this->assertSame('1.2.0', $meta->version);
        $this->assertSame('freemium', $meta->type);
        $this->assertSame('Email automation sequences', $meta->description);
        $this->assertCount(2, $meta->features);
        $this->assertCount(2, $meta->requires);
    }

    public function testFromPathParsesJsonFile(): void
    {
        $dir = sys_get_temp_dir() . '/addon_test_' . uniqid();
        mkdir($dir, 0755, true);
        file_put_contents($dir . '/addon.json', json_encode([
            'slug' => 'test-addon',
            'name' => 'Test Addon',
            'version' => '1.0.0',
            'type' => 'free',
        ]));

        $meta = AddonMeta::fromPath($dir);
        $this->assertSame('test-addon', $meta->slug);

        // Cleanup
        unlink($dir . '/addon.json');
        rmdir($dir);
    }

    // ─── Validation Failures ────────────────────────────

    public function testRejectsEmptySlug(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('slug is required');
        AddonMeta::fromArray(['slug' => '', 'name' => 'X', 'version' => '1.0.0']);
    }

    public function testRejectsInvalidSlug(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Addon slug must start with a letter');
        AddonMeta::fromArray(['slug' => 'UPPERCASE_BAD', 'name' => 'X', 'version' => '1.0.0']);
    }

    public function testRejectsEmptyName(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('name is required');
        AddonMeta::fromArray(['slug' => 'ok', 'name' => '', 'version' => '1.0.0']);
    }

    public function testRejectsInvalidType(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid addon type');
        AddonMeta::fromArray([
            'slug' => 'ok', 'name' => 'X', 'version' => '1.0.0', 'type' => 'premium'
        ]);
    }

    public function testRejectsInvalidVersion(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid version');
        AddonMeta::fromArray(['slug' => 'ok', 'name' => 'X', 'version' => 'abc']);
    }

    public function testFromPathThrowsWhenMissing(): void
    {
        $this->expectException(\RuntimeException::class);
        AddonMeta::fromPath('/tmp/definitely_not_exists_' . uniqid());
    }

    // ─── Feature Queries ────────────────────────────────

    public function testGetFeatureTierReturnsTier(): void
    {
        $meta = AddonMeta::fromArray([
            'slug' => 'ok', 'name' => 'X', 'version' => '1.0.0',
            'features' => ['basic' => 'free', 'advanced' => 'pro'],
        ]);
        $this->assertSame('free', $meta->getFeatureTier('basic'));
        $this->assertSame('pro', $meta->getFeatureTier('advanced'));
        $this->assertNull($meta->getFeatureTier('missing'));
    }

    public function testHasPaidFeatures(): void
    {
        $free = AddonMeta::fromArray([
            'slug' => 'ok', 'name' => 'X', 'version' => '1.0.0',
            'features' => ['a' => 'free', 'b' => 'free'],
        ]);
        $this->assertFalse($free->hasPaidFeatures());

        $paid = AddonMeta::fromArray([
            'slug' => 'ok', 'name' => 'X', 'version' => '1.0.0',
            'features' => ['a' => 'free', 'b' => 'pro'],
        ]);
        $this->assertTrue($paid->hasPaidFeatures());
    }

    // ─── Serialization ──────────────────────────────────

    public function testToArrayRoundTrips(): void
    {
        $data = [
            'slug' => 'roundtrip', 'name' => 'Roundtrip', 'version' => '2.0.0',
            'type' => 'paid', 'description' => 'Test', 'icon' => 'star',
            'features' => ['x' => 'pro'], 'requires' => ['core' => '>=1.0.0'],
            'price' => ['yearly' => 29], 'providers' => [],
        ];
        $meta = AddonMeta::fromArray($data);
        $arr = $meta->toArray();
        $this->assertSame('roundtrip', $arr['slug']);
        $this->assertSame('emoji', $arr['icon_type']);
    }

    // ─── Defaults ───────────────────────────────────────

    public function testDefaultType(): void
    {
        $meta = AddonMeta::fromArray(['slug' => 'ok', 'name' => 'X', 'version' => '1.0.0']);
        $this->assertSame('free', $meta->type);
    }

    public function testDefaultEmptyFeatures(): void
    {
        $meta = AddonMeta::fromArray(['slug' => 'ok', 'name' => 'X', 'version' => '1.0.0']);
        $this->assertSame([], $meta->features);
    }

    // ─── Icon Type Detection ────────────────────────

    public function testIconTypeUrl(): void
    {
        $meta = AddonMeta::fromArray([
            'slug' => 'ok', 'name' => 'X', 'version' => '1.0.0',
            'icon' => 'https://cdn.stackborg.com/icons/automation.svg',
        ]);
        $this->assertSame('url', $meta->iconType());
    }

    public function testIconTypeSvg(): void
    {
        $meta = AddonMeta::fromArray([
            'slug' => 'ok', 'name' => 'X', 'version' => '1.0.0',
            'icon' => '<svg viewBox="0 0 24 24"><circle r="10"/></svg>',
        ]);
        $this->assertSame('svg', $meta->iconType());
    }

    public function testIconTypeEmoji(): void
    {
        $meta = AddonMeta::fromArray([
            'slug' => 'ok', 'name' => 'X', 'version' => '1.0.0',
            'icon' => '⚡',
        ]);
        $this->assertSame('emoji', $meta->iconType());
    }

    public function testIconTypeNone(): void
    {
        $meta = AddonMeta::fromArray([
            'slug' => 'ok', 'name' => 'X', 'version' => '1.0.0',
        ]);
        $this->assertSame('none', $meta->iconType());
    }
}
