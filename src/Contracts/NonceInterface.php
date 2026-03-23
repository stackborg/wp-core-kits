<?php

/**
 * Nonce (CSRF protection) abstraction.
 *
 * @package Stackborg\WPCoreKits
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Contracts;

/**
 * Nonce (CSRF protection) abstraction.
 */
interface NonceInterface
{
    /** Create a nonce token. */
    public static function create(string $action): string;

    /** Verify a nonce token. Returns true if valid. */
    public static function verify(string $nonce, string $action): bool;
}
