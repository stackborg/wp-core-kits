<?php

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Stackborg\WPCoreKits\Addon\LicenseGuard;

class LicenseGuardTest extends TestCase
{
    private string $testKey = 'test_secret_key_for_unit_tests';

    // ─── Sign & Verify ──────────────────────────────────

    public function testSignProducesConsistentHash(): void
    {
        $payload = ['status' => 'active', 'expiry' => '2027-03-23'];
        $sig1 = LicenseGuard::sign($payload, $this->testKey);
        $sig2 = LicenseGuard::sign($payload, $this->testKey);

        $this->assertSame($sig1, $sig2);
        $this->assertSame(64, strlen($sig1)); // SHA256 hex = 64 chars
    }

    public function testSignIsDeterministicRegardlessOfKeyOrder(): void
    {
        $payload1 = ['status' => 'active', 'expiry' => '2027'];
        $payload2 = ['expiry' => '2027', 'status' => 'active'];

        $sig1 = LicenseGuard::sign($payload1, $this->testKey);
        $sig2 = LicenseGuard::sign($payload2, $this->testKey);

        $this->assertSame($sig1, $sig2);
    }

    public function testVerifyValidSignature(): void
    {
        $payload = ['status' => 'active', 'site' => 'example.com'];
        $sig = LicenseGuard::sign($payload, $this->testKey);

        $this->assertTrue(LicenseGuard::verify($payload, $sig, $this->testKey));
    }

    public function testVerifyRejectsTamperedPayload(): void
    {
        $payload = ['status' => 'active', 'expiry' => '2027-03-23'];
        $sig = LicenseGuard::sign($payload, $this->testKey);

        // Tamper: change expiry
        $payload['expiry'] = '2099-12-31';
        $this->assertFalse(LicenseGuard::verify($payload, $sig, $this->testKey));
    }

    public function testVerifyRejectsTamperedSignature(): void
    {
        $payload = ['status' => 'active'];
        $this->assertFalse(LicenseGuard::verify($payload, 'fake_signature', $this->testKey));
    }

    public function testVerifyRejectsEmptySignature(): void
    {
        $payload = ['status' => 'active'];
        $this->assertFalse(LicenseGuard::verify($payload, '', $this->testKey));
    }

    public function testVerifyRejectsWrongKey(): void
    {
        $payload = ['status' => 'active'];
        $sig = LicenseGuard::sign($payload, $this->testKey);

        $this->assertFalse(LicenseGuard::verify($payload, $sig, 'wrong_key'));
    }

    public function testDifferentKeysProduceDifferentSignatures(): void
    {
        $payload = ['status' => 'active'];
        $sig1 = LicenseGuard::sign($payload, 'key_one');
        $sig2 = LicenseGuard::sign($payload, 'key_two');

        $this->assertNotSame($sig1, $sig2);
    }

    // ─── Encrypt & Decrypt ──────────────────────────────

    public function testEncryptDecryptRoundTrip(): void
    {
        $data = ['status' => 'active', 'expiry' => '2027-03-23', 'license_key' => 'SB-XXX'];
        $encrypted = LicenseGuard::encrypt($data);

        // Encrypted should be a base64 string
        $this->assertNotEmpty($encrypted);
        $this->assertNotSame(json_encode($data), $encrypted);

        $decrypted = LicenseGuard::decrypt($encrypted);
        $this->assertSame($data, $decrypted);
    }

    public function testEncryptProducesDifferentOutputEachTime(): void
    {
        $data = ['status' => 'active'];
        $enc1 = LicenseGuard::encrypt($data);
        $enc2 = LicenseGuard::encrypt($data);

        // Random IV means different ciphertext each time
        $this->assertNotSame($enc1, $enc2);

        // But both decrypt to same data
        $this->assertSame(LicenseGuard::decrypt($enc1), LicenseGuard::decrypt($enc2));
    }

    public function testDecryptRejectsEmptyString(): void
    {
        $this->assertNull(LicenseGuard::decrypt(''));
    }

    public function testDecryptRejectsTamperedData(): void
    {
        $data = ['status' => 'active'];
        $encrypted = LicenseGuard::encrypt($data);

        // Tamper by modifying the encrypted string
        $tampered = substr($encrypted, 0, -5) . 'XXXXX';
        $result = LicenseGuard::decrypt($tampered);

        // Should return null (decryption fails) or different data
        $this->assertTrue($result === null || $result !== $data);
    }

    public function testDecryptRejectsGarbageInput(): void
    {
        $this->assertNull(LicenseGuard::decrypt('not_valid_base64!!!'));
    }

    public function testDecryptRejectsTooShortInput(): void
    {
        $this->assertNull(LicenseGuard::decrypt(base64_encode('short')));
    }

    // ─── Site Key ───────────────────────────────────────

    public function testSiteKeyIsConsistent(): void
    {
        $key1 = LicenseGuard::siteKey();
        $key2 = LicenseGuard::siteKey();

        $this->assertSame($key1, $key2);
        $this->assertSame(64, strlen($key1)); // SHA256 hex
    }

    // ─── Integrity Hash ─────────────────────────────────

    public function testIntegrityHashForDirectory(): void
    {
        $dir = sys_get_temp_dir() . '/sb_integrity_test_' . uniqid();
        mkdir($dir . '/src', 0755, true);
        file_put_contents($dir . '/addon.json', '{"slug":"test"}');
        file_put_contents($dir . '/src/init.php', '<?php echo "hello";');

        $hash = LicenseGuard::integrityHash($dir);
        $this->assertSame(64, strlen($hash));

        // Same content = same hash
        $hash2 = LicenseGuard::integrityHash($dir);
        $this->assertSame($hash, $hash2);

        // Modify file = different hash
        file_put_contents($dir . '/src/init.php', '<?php echo "modified";');
        $hash3 = LicenseGuard::integrityHash($dir);
        $this->assertNotSame($hash, $hash3);

        // Cleanup
        unlink($dir . '/src/init.php');
        unlink($dir . '/addon.json');
        rmdir($dir . '/src');
        rmdir($dir);
    }

    public function testIntegrityHashEmptyForNonExistentDir(): void
    {
        $this->assertSame('', LicenseGuard::integrityHash('/tmp/no_such_dir_' . uniqid()));
    }

    // ─── Signed Payload (Mock Server) ───────────────────

    public function testCreateSignedPayloadVerifiable(): void
    {
        $license = ['status' => 'active', 'expiry' => '2027-03-23', 'site' => 'example.com'];
        $result = LicenseGuard::createSignedPayload($license, $this->testKey);

        $this->assertArrayHasKey('payload', $result);
        $this->assertArrayHasKey('signature', $result);

        // Payload should verify with the same key
        $this->assertTrue(
            LicenseGuard::verify($result['payload'], $result['signature'], $this->testKey)
        );
    }
}
