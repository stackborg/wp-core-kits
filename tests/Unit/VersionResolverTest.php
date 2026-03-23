<?php

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Stackborg\WPCoreKits\Addon\AddonMeta;
use Stackborg\WPCoreKits\Addon\VersionResolver;

class VersionResolverTest extends TestCase
{
    // ─── Parse ──────────────────────────────────────────

    public function testParseValidSemVer(): void
    {
        $this->assertSame([1, 2, 3], VersionResolver::parse('1.2.3'));
        $this->assertSame([0, 0, 1], VersionResolver::parse('0.0.1'));
        $this->assertSame([10, 20, 30], VersionResolver::parse('10.20.30'));
    }

    public function testParseStripsLeadingV(): void
    {
        $this->assertSame([1, 0, 0], VersionResolver::parse('v1.0.0'));
        $this->assertSame([2, 3, 1], VersionResolver::parse('V2.3.1'));
    }

    public function testParseIgnoresPreRelease(): void
    {
        $this->assertSame([1, 0, 0], VersionResolver::parse('1.0.0-beta.1'));
        $this->assertSame([2, 1, 0], VersionResolver::parse('2.1.0-rc.2+build.123'));
    }

    public function testParseRejectsInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        VersionResolver::parse('not-a-version');
    }

    public function testParseTwoPartVersionAppendsZero(): void
    {
        // 2-part versions like "8.2" are accepted and treated as "8.2.0"
        $this->assertSame([1, 2, 0], VersionResolver::parse('1.2'));
        $this->assertSame([8, 2, 0], VersionResolver::parse('8.2'));
    }

    public function testParseRejectsSingleNumber(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        VersionResolver::parse('5');
    }

    // ─── Compare ────────────────────────────────────────

    public function testCompareEqual(): void
    {
        $this->assertSame(0, VersionResolver::compare('1.0.0', '1.0.0'));
        $this->assertSame(0, VersionResolver::compare('2.5.3', '2.5.3'));
    }

    public function testCompareMajor(): void
    {
        $this->assertSame(-1, VersionResolver::compare('1.0.0', '2.0.0'));
        $this->assertSame(1, VersionResolver::compare('3.0.0', '2.0.0'));
    }

    public function testCompareMinor(): void
    {
        $this->assertSame(-1, VersionResolver::compare('1.0.0', '1.1.0'));
        $this->assertSame(1, VersionResolver::compare('1.5.0', '1.2.0'));
    }

    public function testComparePatch(): void
    {
        $this->assertSame(-1, VersionResolver::compare('1.0.0', '1.0.1'));
        $this->assertSame(1, VersionResolver::compare('1.0.5', '1.0.3'));
    }

    // ─── Satisfies — Simple Operators ───────────────────

    public function testSatisfiesGreaterOrEqual(): void
    {
        $this->assertTrue(VersionResolver::satisfies('1.0.0', '>=1.0.0'));
        $this->assertTrue(VersionResolver::satisfies('1.5.0', '>=1.0.0'));
        $this->assertTrue(VersionResolver::satisfies('2.0.0', '>=1.0.0'));
        $this->assertFalse(VersionResolver::satisfies('0.9.9', '>=1.0.0'));
    }

    public function testSatisfiesLessOrEqual(): void
    {
        $this->assertTrue(VersionResolver::satisfies('1.0.0', '<=1.0.0'));
        $this->assertTrue(VersionResolver::satisfies('0.5.0', '<=1.0.0'));
        $this->assertFalse(VersionResolver::satisfies('1.0.1', '<=1.0.0'));
    }

    public function testSatisfiesGreaterThan(): void
    {
        $this->assertTrue(VersionResolver::satisfies('1.0.1', '>1.0.0'));
        $this->assertFalse(VersionResolver::satisfies('1.0.0', '>1.0.0'));
    }

    public function testSatisfiesLessThan(): void
    {
        $this->assertTrue(VersionResolver::satisfies('0.9.9', '<1.0.0'));
        $this->assertFalse(VersionResolver::satisfies('1.0.0', '<1.0.0'));
    }

    public function testSatisfiesExactWithOperator(): void
    {
        $this->assertTrue(VersionResolver::satisfies('1.2.3', '=1.2.3'));
        $this->assertFalse(VersionResolver::satisfies('1.2.4', '=1.2.3'));
    }

    public function testSatisfiesExactWithoutOperator(): void
    {
        $this->assertTrue(VersionResolver::satisfies('1.2.3', '1.2.3'));
        $this->assertFalse(VersionResolver::satisfies('1.2.4', '1.2.3'));
    }

    // ─── Satisfies — Caret (^) ──────────────────────────

    public function testSatisfiesCaretBasic(): void
    {
        // ^1.2.0 means >=1.2.0 <2.0.0
        $this->assertTrue(VersionResolver::satisfies('1.2.0', '^1.2.0'));
        $this->assertTrue(VersionResolver::satisfies('1.9.9', '^1.2.0'));
        $this->assertFalse(VersionResolver::satisfies('2.0.0', '^1.2.0'));
        $this->assertFalse(VersionResolver::satisfies('1.1.9', '^1.2.0'));
    }

    public function testSatisfiesCaretZeroMajor(): void
    {
        // ^0.5.0 means >=0.5.0 <1.0.0
        $this->assertTrue(VersionResolver::satisfies('0.5.0', '^0.5.0'));
        $this->assertTrue(VersionResolver::satisfies('0.9.9', '^0.5.0'));
        $this->assertFalse(VersionResolver::satisfies('1.0.0', '^0.5.0'));
    }

    // ─── Satisfies — Compound ───────────────────────────

    public function testSatisfiesCompound(): void
    {
        // >=1.0.0 <3.0.0
        $this->assertTrue(VersionResolver::satisfies('1.0.0', '>=1.0.0 <3.0.0'));
        $this->assertTrue(VersionResolver::satisfies('2.5.0', '>=1.0.0 <3.0.0'));
        $this->assertFalse(VersionResolver::satisfies('0.9.0', '>=1.0.0 <3.0.0'));
        $this->assertFalse(VersionResolver::satisfies('3.0.0', '>=1.0.0 <3.0.0'));
    }

    // ─── Addon Compatibility Check ──────────────────────

    public function testCompatibleAddon(): void
    {
        $meta = AddonMeta::fromArray([
            'slug' => 'ok', 'name' => 'OK', 'version' => '1.0.0',
            'requires' => ['core' => '>=1.0.0', 'ui' => '>=1.0.0', 'php' => '>=8.2'],
        ]);

        $result = VersionResolver::checkAddonCompatibility($meta, '1.5.0', '1.2.0', '8.4.0');
        $this->assertTrue($result->compatible);
        $this->assertEmpty($result->errors);
    }

    public function testIncompatibleCoreVersion(): void
    {
        $meta = AddonMeta::fromArray([
            'slug' => 'ok', 'name' => 'OK', 'version' => '1.0.0',
            'requires' => ['core' => '>=2.0.0'],
        ]);

        $result = VersionResolver::checkAddonCompatibility($meta, '1.5.0', '1.0.0');
        $this->assertFalse($result->compatible);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('core', $result->errors[0]);
    }

    public function testIncompatibleUiVersion(): void
    {
        $meta = AddonMeta::fromArray([
            'slug' => 'ok', 'name' => 'OK', 'version' => '1.0.0',
            'requires' => ['ui' => '>=2.0.0'],
        ]);

        $result = VersionResolver::checkAddonCompatibility($meta, '2.0.0', '1.0.0');
        $this->assertFalse($result->compatible);
        $this->assertStringContainsString('UI', $result->errors[0]);
    }

    public function testIncompatiblePhpVersion(): void
    {
        $meta = AddonMeta::fromArray([
            'slug' => 'ok', 'name' => 'OK', 'version' => '1.0.0',
            'requires' => ['php' => '>=9.0'],
        ]);

        $result = VersionResolver::checkAddonCompatibility($meta, '1.0.0', '1.0.0', '8.4.0');
        $this->assertFalse($result->compatible);
        $this->assertStringContainsString('PHP', $result->errors[0]);
    }

    public function testMultipleIncompatibilities(): void
    {
        $meta = AddonMeta::fromArray([
            'slug' => 'ok', 'name' => 'OK', 'version' => '1.0.0',
            'requires' => ['core' => '>=5.0.0', 'ui' => '>=5.0.0', 'php' => '>=9.0'],
        ]);

        $result = VersionResolver::checkAddonCompatibility($meta, '1.0.0', '1.0.0', '8.2.0');
        $this->assertFalse($result->compatible);
        $this->assertCount(3, $result->errors);
    }

    public function testTestedUpToWarning(): void
    {
        $meta = AddonMeta::fromArray([
            'slug' => 'ok', 'name' => 'OK', 'version' => '1.0.0',
            'requires' => ['core' => '>=1.0.0', 'tested_up_to' => '2.0.0'],
        ]);

        $result = VersionResolver::checkAddonCompatibility($meta, '2.5.0', '1.0.0');
        $this->assertTrue($result->compatible); // still compatible — just a warning
        $this->assertCount(1, $result->warnings);
        $this->assertStringContainsString('tested up to', $result->warnings[0]);
    }

    public function testNoRequirementsAlwaysCompatible(): void
    {
        $meta = AddonMeta::fromArray([
            'slug' => 'ok', 'name' => 'OK', 'version' => '1.0.0',
        ]);

        $result = VersionResolver::checkAddonCompatibility($meta, '99.0.0', '99.0.0');
        $this->assertTrue($result->compatible);
    }
}
