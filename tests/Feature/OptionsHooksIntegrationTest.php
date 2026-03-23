<?php

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Tests\Feature;

use PHPUnit\Framework\TestCase;
use Stackborg\WPCoreKits\WordPress\Options;
use Stackborg\WPCoreKits\WordPress\Hooks;

/**
 * Feature test — Options + Hooks integration.
 *
 * Tests how Options and Hooks work together
 * in a realistic plugin lifecycle scenario.
 */
class OptionsHooksIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        Options::flushCache();
        global $wp_options, $wp_hook_registry;
        $wp_options = [];
        $wp_hook_registry = [];
    }

    public function testOptionsChangeTriggersHook(): void
    {
        $hookFired = false;
        $oldValue = null;
        $newValue = null;

        // Register a hook that fires when settings change
        Hooks::action('settings_updated', function ($old, $new) use (&$hookFired, &$oldValue, &$newValue) {
            $hookFired = true;
            $oldValue = $old;
            $newValue = $new;
        }, 10, 2);

        // Simulate plugin settings update flow
        $old = Options::get('plugin_settings', []);
        $new = ['api_key' => 'abc123', 'debug' => true];
        Options::set('plugin_settings', $new);
        Hooks::doAction('settings_updated', $old, $new);

        $this->assertTrue($hookFired);
        $this->assertSame([], $oldValue);
        $this->assertSame($new, $newValue);

        // Verify options persisted
        $this->assertSame($new, Options::get('plugin_settings'));
    }

    public function testFilterModifiesOptionBeforeSave(): void
    {
        // Filter sanitizes settings before save
        Hooks::filter('sanitize_settings', function (array $settings) {
            $settings['email'] = strtolower(trim($settings['email']));
            return $settings;
        });

        $raw = ['email' => '  USER@Example.COM  ', 'name' => 'John'];
        $clean = Hooks::applyFilters('sanitize_settings', $raw);
        Options::set('user_settings', $clean);

        $saved = Options::get('user_settings');
        $this->assertSame('user@example.com', $saved['email']);
        $this->assertSame('John', $saved['name']);
    }
}
