<?php

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Stackborg\WPCoreKits\WordPress\Asset;

class AssetTest extends TestCase
{
    protected function setUp(): void
    {
        global $wp_enqueued_scripts, $wp_enqueued_styles, $wp_localized;
        $wp_enqueued_scripts = [];
        $wp_enqueued_styles = [];
        $wp_localized = [];
    }

    public function testSetVersionUpdatesVersion(): void
    {
        Asset::setVersion('2.0.0');
        // Version is private, test via script enqueue
        Asset::script('test-js', '/path/test.js');

        global $wp_enqueued_scripts;
        $this->assertSame('2.0.0', $wp_enqueued_scripts['test-js']['version']);
    }

    public function testScriptEnqueuesCorrectly(): void
    {
        Asset::setVersion('1.0.0');
        Asset::script('my-script', '/js/app.js', ['react'], true);

        global $wp_enqueued_scripts;
        $this->assertArrayHasKey('my-script', $wp_enqueued_scripts);
        $this->assertSame('/js/app.js', $wp_enqueued_scripts['my-script']['src']);
        $this->assertSame(['react'], $wp_enqueued_scripts['my-script']['deps']);
        $this->assertTrue($wp_enqueued_scripts['my-script']['footer']);
    }

    public function testStyleEnqueuesCorrectly(): void
    {
        Asset::setVersion('1.0.0');
        Asset::style('my-style', '/css/app.css', ['wp-components']);

        global $wp_enqueued_styles;
        $this->assertArrayHasKey('my-style', $wp_enqueued_styles);
        $this->assertSame('/css/app.css', $wp_enqueued_styles['my-style']['src']);
    }

    public function testLocalizeStoresData(): void
    {
        Asset::localize('my-script', 'sbData', ['apiUrl' => '/api']);

        global $wp_localized;
        $this->assertArrayHasKey('my-script', $wp_localized);
        $this->assertSame('sbData', $wp_localized['my-script']['name']);
        $this->assertSame(['apiUrl' => '/api'], $wp_localized['my-script']['data']);
    }
}
