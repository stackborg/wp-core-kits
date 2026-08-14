<?php

/**
 * The placeholder that stands in the root until the dashboard mounts.
 *
 * @package Stackborg\WPCoreKits\Tests\Unit
 */

namespace Stackborg\WPCoreKits\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Stackborg\WPCoreKits\Plugin\AdminDashboardTrait;

/**
 * A plugin's own loading state has to be able to replace the generic one.
 *
 * The default preloader is a spinner, a title and a progress bar in the
 * plugin's configured colours. A dashboard then paints its *own* loading state
 * the moment React commits — so unless the two look alike, opening the screen
 * shows two different loaders in a row and reads as the plugin starting twice.
 * Passing `preloader` lets a plugin emit exactly what its dashboard renders,
 * which makes the handover invisible.
 */
final class AdminDashboardPreloaderTest extends TestCase
{
    /** @param array<string, mixed> $extra */
    private function screen(array $extra = []): object
    {
        return new class ($extra) {
            use AdminDashboardTrait;

            /** @param array<string, mixed> $extra */
            public function __construct(private array $extra)
            {
            }

            /** @return array<string, mixed> */
            protected function adminConfig(): array
            {
                return array_merge([
                    'slug'       => 'sb-example',
                    'page_title' => 'Example',
                    'menu_title' => 'Example',
                    'capability' => 'manage_options',
                    'icon'       => 'dashicons-admin-generic',
                    'position'   => 65,
                    'color'      => '#4f46e5',
                    'gradient'   => 'linear-gradient(135deg, #4f46e5, #3730a3)',
                    'bg_color'   => '#f6f7f9',
                ], $this->extra);
            }
        };
    }

    private function render(object $screen): string
    {
        ob_start();
        $screen->renderAdminPage();

        return (string) ob_get_clean();
    }

    public function testFallsBackToTheGenericPreloader(): void
    {
        $html = $this->render($this->screen());

        $this->assertStringContainsString('id="sb-example-root"', $html);
        $this->assertStringContainsString('Loading dashboard', $html);
    }

    public function testACallablePreloaderReplacesIt(): void
    {
        $html = $this->render($this->screen([
            'preloader' => static function (): void {
                echo '<div class="ex-load">the plugin\'s own</div>';
            },
        ]));

        $this->assertStringContainsString('id="sb-example-root"', $html);
        $this->assertStringContainsString('<div class="ex-load">the plugin\'s own</div>', $html);
        $this->assertStringNotContainsString('Loading dashboard', $html, 'The generic one must not also be printed');
    }

    public function testAStringPreloaderReplacesIt(): void
    {
        $html = $this->render($this->screen([
            'preloader' => '<span class="ex-load">markup</span>',
        ]));

        $this->assertStringContainsString('<span class="ex-load">markup</span>', $html);
        $this->assertStringNotContainsString('Loading dashboard', $html);
    }

    public function testTheRootIsStillThereWhateverIsInIt(): void
    {
        // Whatever the placeholder, createDashboard() mounts into this id —
        // an override that forgot the wrapper would leave React nothing to
        // find, and the screen would stay on the loader forever.
        foreach ([null, '<i>x</i>', static fn () => print('<i>x</i>')] as $preloader) {
            $html = $this->render($this->screen($preloader === null ? [] : ['preloader' => $preloader]));
            $this->assertStringContainsString('id="sb-example-root"', $html);
        }
    }
}
