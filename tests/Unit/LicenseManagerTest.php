<?php

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Stackborg\WPCoreKits\Addon\LicenseGuard;
use Stackborg\WPCoreKits\Addon\LicenseManager;

class LicenseManagerTest extends TestCase
{
    private string $optionPrefix = 'sb_test';
    private string $verifyKey = 'test-secret-key-for-signing';

    protected function setUp(): void
    {
        $GLOBALS['wp_options'] = [];
    }

    /**
     * Helper: create a properly signed API response that LicenseManager accepts.
     */
    private function makeSignedResponse(array $payload): array
    {
        return LicenseGuard::createSignedPayload($payload, $this->verifyKey);
    }

    private function createManager(): LicenseManager
    {
        return new LicenseManager($this->optionPrefix, $this->verifyKey);
    }

    /** @test */
    public function itActivatesLicenseWithValidSignature(): void
    {
        $manager = $this->createManager();

        $response = $this->makeSignedResponse([
            'status' => 'active',
            'expiry' => '2030-12-31',
            'site'   => 'localhost',
        ]);

        $result = $manager->activate('email-templates', 'LICENSE-KEY-123', $response);

        $this->assertTrue($result->valid);
        $this->assertSame('active', $result->status);
    }

    /** @test */
    public function itRejectsActivationWithInvalidSignature(): void
    {
        $manager = $this->createManager();

        $response = [
            'payload'   => ['status' => 'active', 'expiry' => '2030-12-31', 'site' => 'localhost'],
            'signature' => 'invalid-signature-tampered',
        ];

        $result = $manager->activate('email-templates', 'LICENSE-KEY-123', $response);

        $this->assertFalse($result->valid);
        $this->assertSame('invalid', $result->status);
        $this->assertStringContainsString('signature', $result->message);
    }

    /** @test */
    public function itRejectsActivationWhenStatusIsNotActive(): void
    {
        $manager = $this->createManager();

        $response = $this->makeSignedResponse([
            'status' => 'suspended',
            'expiry' => '2030-12-31',
            'site'   => 'localhost',
        ]);

        $result = $manager->activate('email-templates', 'LICENSE-KEY-123', $response);

        $this->assertFalse($result->valid);
        $this->assertStringContainsString('not active', $result->message);
    }

    /** @test */
    public function itDeactivatesLicense(): void
    {
        $manager = $this->createManager();

        // First activate
        $response = $this->makeSignedResponse([
            'status' => 'active',
            'expiry' => '2030-12-31',
            'site'   => 'localhost',
        ]);
        $manager->activate('email-templates', 'KEY', $response);

        // Then deactivate
        $result = $manager->deactivate('email-templates');
        $this->assertTrue($result);

        // Status should be 'none' after deactivation
        $this->assertSame('none', $manager->getStatus('email-templates'));
    }

    /** @test */
    public function itValidatesActiveLicense(): void
    {
        $manager = $this->createManager();

        $response = $this->makeSignedResponse([
            'status' => 'active',
            'expiry' => '2030-12-31',
            'site'   => 'localhost',
        ]);

        $manager->activate('email-templates', 'KEY', $response);
        $this->assertTrue($manager->isValid('email-templates'));
    }

    /** @test */
    public function itRejectsExpiredLicense(): void
    {
        $manager = $this->createManager();

        $response = $this->makeSignedResponse([
            'status' => 'active',
            'expiry' => '2020-01-01', // Already expired
            'site'   => 'localhost',
        ]);

        $manager->activate('email-templates', 'KEY', $response);
        $this->assertFalse($manager->isValid('email-templates'));
    }

    /** @test */
    public function itReturnsFalseForNonexistentLicense(): void
    {
        $manager = $this->createManager();

        $this->assertFalse($manager->isValid('nonexistent-addon'));
    }

    /** @test */
    public function itReturnsNoneStatusWhenNoLicense(): void
    {
        $manager = $this->createManager();

        $this->assertSame('none', $manager->getStatus('nonexistent-addon'));
    }

    /** @test */
    public function itReturnsCorrectExpiry(): void
    {
        $manager = $this->createManager();

        $response = $this->makeSignedResponse([
            'status' => 'active',
            'expiry' => '2030-06-15',
            'site'   => 'localhost',
        ]);

        $manager->activate('email-templates', 'KEY', $response);
        $this->assertSame('2030-06-15', $manager->getExpiry('email-templates'));
    }

    /** @test */
    public function itReturnsNullExpiryForNonexistentAddon(): void
    {
        $manager = $this->createManager();
        $this->assertNull($manager->getExpiry('nonexistent'));
    }

    /** @test */
    public function itRefreshesVerificationTimestamp(): void
    {
        $manager = $this->createManager();

        // Activate first
        $response = $this->makeSignedResponse([
            'status' => 'active',
            'expiry' => '2030-12-31',
            'site'   => 'localhost',
        ]);
        $manager->activate('email-templates', 'KEY', $response);

        // Refresh with new signed response
        $refreshResponse = $this->makeSignedResponse([
            'status' => 'active',
            'expiry' => '2031-12-31',
            'site'   => 'localhost',
        ]);

        $result = $manager->refreshVerification('email-templates', $refreshResponse);
        $this->assertTrue($result);

        // Expiry should be updated
        $this->assertSame('2031-12-31', $manager->getExpiry('email-templates'));
    }

    /** @test */
    public function itRejectsRefreshWithInvalidSignature(): void
    {
        $manager = $this->createManager();

        $response = $this->makeSignedResponse([
            'status' => 'active',
            'expiry' => '2030-12-31',
            'site'   => 'localhost',
        ]);
        $manager->activate('email-templates', 'KEY', $response);

        // Tampered refresh response
        $badRefresh = [
            'payload'   => ['status' => 'active', 'expiry' => '2031-12-31', 'site' => 'localhost'],
            'signature' => 'tampered-signature',
        ];

        $result = $manager->refreshVerification('email-templates', $badRefresh);
        $this->assertFalse($result);
    }

    /** @test */
    public function itReturnsFalseForRefreshOnNonexistentAddon(): void
    {
        $manager = $this->createManager();

        $response = $this->makeSignedResponse([
            'status' => 'active',
            'expiry' => '2030-12-31',
            'site'   => 'localhost',
        ]);

        $result = $manager->refreshVerification('nonexistent', $response);
        $this->assertFalse($result);
    }
}
