<?php

/**
 * Centralized sanitization and escaping.
 *
 * @package Stackborg\WPCoreKits
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\WordPress;

use Stackborg\WPCoreKits\Contracts\SanitizerInterface;

/**
 * Centralized sanitization and escaping.
 *
 * Plugins never need to remember which WP function to call —
 * Sanitizer provides a single, discoverable API.
 */
class Sanitizer implements SanitizerInterface
{
    public static function text(string $input): string
    {
        return sanitize_text_field($input);
    }

    public static function email(string $input): string
    {
        return sanitize_email($input);
    }

    public static function url(string $input): string
    {
        return esc_url_raw($input);
    }

    public static function int(mixed $input): int
    {
        return (int) $input;
    }

    public static function absint(mixed $input): int
    {
        return absint($input);
    }

    public static function escHtml(string $input): string
    {
        return esc_html($input);
    }

    public static function escAttr(string $input): string
    {
        return esc_attr($input);
    }

    public static function escUrl(string $input): string
    {
        return esc_url($input);
    }

    public static function kses(string $input): string
    {
        return wp_kses_post($input);
    }
}
