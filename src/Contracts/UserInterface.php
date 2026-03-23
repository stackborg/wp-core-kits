<?php

/**
 * User capabilities and identity abstraction.
 *
 * @package Stackborg\WPCoreKits
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Contracts;

/**
 * User capabilities and identity abstraction.
 */
interface UserInterface
{
    /** Check if current user has a capability. */
    public static function can(string $capability): bool;

    /** Get current user's ID. */
    public static function id(): int;

    /** Check if a user is logged in. */
    public static function isLoggedIn(): bool;

    /** Check if current user is an administrator. */
    public static function isAdmin(): bool;
}
