<?php

/**
 * CSRF nonce wrapper.
 *
 * @package Stackborg\WPCoreKits
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\WordPress;

use Stackborg\WPCoreKits\Contracts\NonceInterface;

/**
 * CSRF nonce wrapper.
 */
class Nonce implements NonceInterface
{
    public static function create(string $action): string
    {
        return wp_create_nonce($action);
    }

    public static function verify(string $nonce, string $action): bool
    {
        return wp_verify_nonce($nonce, $action) !== false;
    }
}
