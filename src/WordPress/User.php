<?php
/**
 * User capabilities and identity wrapper.
 *
 * @package Stackborg\WPCoreKits
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\WordPress;

use Stackborg\WPCoreKits\Contracts\UserInterface;

/**
 * User capabilities and identity wrapper.
 */
class User implements UserInterface
{
    public static function can(string $capability): bool
    {
        return current_user_can($capability);
    }

    public static function id(): int
    {
        return get_current_user_id();
    }

    public static function isLoggedIn(): bool
    {
        return self::id() > 0;
    }

    public static function isAdmin(): bool
    {
        return self::can('manage_options');
    }
}
