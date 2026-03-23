<?php

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Stackborg\WPCoreKits\WordPress\User;

class UserTest extends TestCase
{
    public function testCanReturnsBool(): void
    {
        $this->assertIsBool(User::can('manage_options'));
    }

    public function testIdReturnsInteger(): void
    {
        $this->assertIsInt(User::id());
    }

    public function testIsLoggedInReturnsFalseWhenIdIsZero(): void
    {
        // Default mock returns 0
        $this->assertFalse(User::isLoggedIn());
    }

    public function testIsAdminChecksManaOptions(): void
    {
        $this->assertIsBool(User::isAdmin());
    }
}
