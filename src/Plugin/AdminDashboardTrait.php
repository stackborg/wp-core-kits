<?php

/**
 * AdminDashboardTrait — shared admin page boilerplate for all Stackborg plugins.
 *
 * Eliminates ~80 lines of duplicated admin page code across 10 plugins.
 * Each plugin only needs to define configuration constants/methods,
 * and the trait handles menu registration, asset loading, preloader,
 * and React SPA bootstrapping.
 *
 * Usage in Plugin.php:
 *   use Stackborg\WPCoreKits\Plugin\AdminDashboardTrait;
 *
 *   class Plugin {
 *       use SingletonTrait, AdminDashboardTrait;
 *
 *       // Required config for the trait:
 *       protected function adminConfig(): array {
 *           return [
 *               'slug'        => 'sb-mailpress',
 *               'page_title'  => 'MailPress',
 *               'menu_title'  => 'MailPress',
 *               'icon'        => 'dashicons-email-alt',
 *               'position'    => 26,
 *               'text_domain' => 'sb-mailpress',
 *               'namespace'   => 'sb-mailpress/v1',
 *               'version'     => SB_MAILPRESS_VERSION,
 *               'dir'         => SB_MAILPRESS_DIR,
 *               'url'         => SB_MAILPRESS_URL,
 *               'color'       => '#7c3aed',
 *               'gradient'    => 'linear-gradient(135deg, #7c3aed, #6d28d9)',
 *               'bg_color'    => '#faf5ff',
 *           ];
 *       }
 *   }
 *
 * @package Stackborg\WPCoreKits\Plugin
 * @since   1.1.0
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Plugin;

use Stackborg\WPCoreKits\WordPress\Asset;

if (!defined('ABSPATH')) exit;

trait AdminDashboardTrait
{
    /**
     * Return admin dashboard configuration.
     * Must be implemented by the consuming class.
     *
     * @return array{
     *     slug: string,
     *     page_title: string,
     *     menu_title: string,
     *     icon: string,
     *     position: int,
     *     text_domain: string,
     *     namespace: string,
     *     version: string,
     *     dir: string,
     *     url: string,
     *     color: string,
     *     gradient: string,
     *     bg_color: string,
     *     localize_data?: array,
     *     localize_object?: string,
     * }
     */
    abstract protected function adminConfig(): array;

    /**
     * Register the admin menu page.
     */
    public function registerAdminMenu(): void
    {
        $config = $this->adminConfig();

        add_menu_page(
            __($config['page_title'], $config['text_domain']),
            __($config['menu_title'], $config['text_domain']),
            $config['capability'] ?? 'manage_options',
            $config['slug'],
            [$this, 'renderAdminPage'],
            $config['icon'],
            $config['position']
        );
    }

    /**
     * Render the React root container with animated preloader.
     */
    public function renderAdminPage(): void
    {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 100% static HTML, no user data
        echo $this->buildPreloaderHtml();
    }

    /**
     * Enqueue admin CSS/JS on the plugin admin page only.
     *
     * Handles: Google Fonts dedup, cache-busting, React localization,
     * and admin notice suppression for clean SPA UI.
     */
    public function enqueueAdminAssets(string $hook): void
    {
        $config = $this->adminConfig();

        if ($hook !== 'toplevel_page_' . $config['slug']) {
            return;
        }

        $url  = $config['url'];
        $path = $config['dir'];

        // Use file modification time for cache-busting during development
        $jsVersion  = file_exists($path . 'assets/js/admin.js')
            ? (string) filemtime($path . 'assets/js/admin.js')
            : $config['version'];
        $cssVersion = file_exists($path . 'assets/css/admin.css')
            ? (string) filemtime($path . 'assets/css/admin.css')
            : $config['version'];

        // Shared Google Fonts — deduplicates across all Stackborg plugins
        Asset::sharedFont(
            'inter',
            'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap'
        );

        // Dashboard CSS
        wp_enqueue_style(
            $config['slug'] . '-admin',
            $url . 'assets/css/admin.css',
            ['sb-shared-inter-fonts'],
            $cssVersion
        );

        // React app JS (WordPress-bundled React via wp-element)
        wp_enqueue_script(
            $config['slug'] . '-admin',
            $url . 'assets/js/admin.js',
            ['wp-element'],
            $jsVersion,
            true
        );

        // Localize server-side data for the React app
        $localizeObject = $config['localize_object'] ?? $this->buildLocalizeObjectName($config['slug']);
        $localizeData = array_merge([
            'apiUrl'    => esc_url_raw(rest_url($config['namespace'])),
            'nonce'     => wp_create_nonce('wp_rest'),
            'version'   => $config['version'],
            'siteTitle' => esc_html(get_bloginfo('name')),
            'siteUrl'   => esc_url_raw(site_url()),
            'adminUrl'  => esc_url_raw(admin_url()),
        ], $config['localize_data'] ?? []);

        wp_localize_script($config['slug'] . '-admin', $localizeObject, $localizeData);

        // Hide WP admin notices on our page for clean React SPA UI
        remove_all_actions('admin_notices');
        remove_all_actions('all_admin_notices');
    }

    /**
     * Build animated preloader HTML for the React SPA root.
     *
     * The preloader is shown until React hydrates and replaces
     * the root container content.
     */
    private function buildPreloaderHtml(): string
    {
        $config = $this->adminConfig();
        $slug   = esc_attr($config['slug']);
        $name   = esc_html($config['menu_title']);
        $color  = esc_attr($config['color']);
        $grad   = esc_attr($config['gradient']);
        $bg     = esc_attr($config['bg_color']);
        $prefix = str_replace('-', '', $slug);

        return <<<HTML
        <div id="{$slug}-root">
          <div style="display:flex;align-items:center;justify-content:center;min-height:100vh;background:{$bg};font-family:Inter,-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif">
            <div style="text-align:center">
              <div style="position:relative;width:80px;height:80px;margin:0 auto 24px">
                <div style="position:absolute;inset:0;border-radius:50%;background:{$grad};animation:{$prefix}Float 2s ease-in-out infinite"></div>
                <div style="position:absolute;inset:8px;border-radius:50%;border:3px solid transparent;border-top-color:#fff;animation:{$prefix}Orbit 1s linear infinite"></div>
                <div style="position:absolute;inset:0;border-radius:50%;background:radial-gradient(circle,rgba(255,255,255,0.3) 0%,transparent 70%);animation:{$prefix}Glow 2s ease-in-out infinite"></div>
              </div>
              <div style="font-size:20px;font-weight:700;color:{$color};margin-bottom:6px">{$name}</div>
              <div style="font-size:13px;color:{$color}80">Loading dashboard…</div>
              <div style="margin-top:20px;width:200px;height:3px;background:{$color}20;border-radius:3px;overflow:hidden;margin-left:auto;margin-right:auto">
                <div style="width:40%;height:100%;background:{$grad};border-radius:3px;animation:{$prefix}Progress 1.5s ease-in-out infinite"></div>
              </div>
            </div>
          </div>
          <style>
            @keyframes {$prefix}Float{0%,100%{transform:translateY(0) scale(1)}50%{transform:translateY(-6px) scale(1.05)}}
            @keyframes {$prefix}Orbit{to{transform:rotate(360deg)}}
            @keyframes {$prefix}Glow{0%,100%{opacity:.5}50%{opacity:1}}
            @keyframes {$prefix}Progress{0%{transform:translateX(-100%)}100%{transform:translateX(350%)}}
            .toplevel_page_{$slug} #wpfooter,
            .toplevel_page_{$slug} .screen-meta-toggle{display:none!important}
            .toplevel_page_{$slug} #wpcontent{padding-left:0!important}
          </style>
        </div>
        HTML;
    }

    /**
     * Convert slug to camelCase localize object name.
     * e.g., 'sb-mailpress' => 'sbMailPressData'
     */
    private function buildLocalizeObjectName(string $slug): string
    {
        $parts = explode('-', $slug);
        $name = '';
        foreach ($parts as $i => $part) {
            $name .= $i === 0 ? $part : ucfirst($part);
        }
        return $name . 'Data';
    }
}
