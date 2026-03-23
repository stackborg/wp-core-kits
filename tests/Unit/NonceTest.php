<?php

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Stackborg\WPCoreKits\WordPress\Nonce;

class NonceTest extends TestCase
{
    public function testCreateReturnsString(): void
    {
        $nonce = Nonce::create('my_action');
        $this->assertIsString($nonce);
        $this->assertNotEmpty($nonce);
    }

    public function testVerifyReturnsTrueForValidNonce(): void
    {
        $nonce = Nonce::create('test_action');
        $this->assertTrue(Nonce::verify($nonce, 'test_action'));
    }

    public function testVerifyReturnsFalseForInvalidNonce(): void
    {
        $this->assertFalse(Nonce::verify('invalid_token', 'test_action'));
    }
}
