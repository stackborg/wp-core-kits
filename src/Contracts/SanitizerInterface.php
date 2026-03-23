<?php
/**
 * Input sanitization abstraction.
 *
 * @package Stackborg\WPCoreKits
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Contracts;

/**
 * Input sanitization abstraction.
 *
 * Centralizes all sanitization and escaping so plugins
 * never need to remember which WP function to call.
 */
interface SanitizerInterface
{
    /** Sanitize a plain text string. */
    public static function text(string $input): string;

    /** Sanitize an email address. */
    public static function email(string $input): string;

    /** Sanitize a URL. */
    public static function url(string $input): string;

    /** Sanitize an integer value. */
    public static function int(mixed $input): int;

    /** Sanitize to absolute integer (non-negative). */
    public static function absint(mixed $input): int;

    /** Escape HTML for safe output. */
    public static function escHtml(string $input): string;

    /** Escape attribute for safe output. */
    public static function escAttr(string $input): string;

    /** Escape URL for safe output. */
    public static function escUrl(string $input): string;

    /** Allow safe HTML (post content). */
    public static function kses(string $input): string;
}
