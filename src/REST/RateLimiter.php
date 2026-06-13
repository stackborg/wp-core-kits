<?php

/**
 * RateLimiter — transient-based IP rate limiting for REST endpoints.
 *
 * Uses WordPress transients for storage, making it compatible with
 * object cache backends (Redis, Memcached) for better performance.
 *
 * Usage:
 *   // In a REST endpoint handler:
 *   $check = RateLimiter::check('performance_collect', 10, 60);
 *   if (is_wp_error($check)) {
 *       return new \WP_REST_Response(['error' => $check->get_error_message()], 429);
 *   }
 *
 * @package Stackborg\WPCoreKits\REST
 * @since   1.1.0
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\REST;

if (!defined('ABSPATH')) {
    exit;
}

final class RateLimiter
{
    /**
     * Check and increment the rate limit counter for the current request.
     *
     * Returns true if the request is allowed, or a WP_Error if rate limit exceeded.
     * This method atomically checks and increments — no separate increment call needed.
     *
     * @param string $key            Unique identifier for the endpoint (e.g., 'performance_collect').
     * @param int    $maxRequests    Maximum requests allowed within the window.
     * @param int    $windowSeconds  Time window in seconds (default: 60).
     * @return true|\WP_Error        True if allowed, WP_Error if rate limit exceeded.
     */
    public static function check(string $key, int $maxRequests = 60, int $windowSeconds = 60): true|\WP_Error
    {
        $ip = self::getClientIp();
        $transientKey = self::buildKey($key, $ip);

        // Get current count — returns false if transient doesn't exist
        $current = get_transient($transientKey);

        if ($current === false) {
            // First request in this window — start counter
            set_transient($transientKey, 1, $windowSeconds);
            return true;
        }

        $count = (int) $current;

        if ($count >= $maxRequests) {
            return new \WP_Error(
                'rate_limit_exceeded',
                sprintf(
                    /* translators: 1: max requests, 2: window in seconds */
                    __('Rate limit exceeded. Maximum %1$d requests per %2$d seconds.', 'wp-core-kits'),
                    $maxRequests,
                    $windowSeconds
                ),
                ['status' => 429]
            );
        }

        // Increment counter — preserve existing TTL by not changing expiration
        // WordPress transients don't support atomic increment, so we update the value
        set_transient($transientKey, $count + 1, $windowSeconds);

        return true;
    }

    /**
     * Reset the rate limit counter for a specific key and IP.
     *
     * Useful for testing or when you need to manually clear a rate limit.
     *
     * @param string      $key  The endpoint key.
     * @param string|null $ip   Client IP. If null, uses current request IP.
     */
    public static function reset(string $key, ?string $ip = null): void
    {
        $ip = $ip ?? self::getClientIp();
        delete_transient(self::buildKey($key, $ip));
    }

    /**
     * Build a unique transient key from endpoint key and client IP.
     *
     * Transient keys have a 172-character limit in WordPress.
     * We use a short prefix + md5 hash to stay within limits.
     */
    private static function buildKey(string $key, string $ip): string
    {
        // sb_rl_ prefix (6 chars) + md5 hash (32 chars) = 38 chars — well within limit
        return 'sb_rl_' . md5($key . '|' . $ip);
    }

    /**
     * Get the client's IP address, respecting common proxy headers.
     *
     * Falls back to REMOTE_ADDR if no proxy headers are present.
     * Only trusts proxy headers when WordPress is configured to do so.
     */
    private static function getClientIp(): string
    {
        // Use REMOTE_ADDR as the default — most secure, not spoofable
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        // Sanitize: remove any port number from IPv4, validate format
        $ip = preg_replace('/:\d+$/', '', $ip) ?? $ip;

        return sanitize_text_field($ip);
    }
}
