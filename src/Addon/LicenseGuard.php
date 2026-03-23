<?php
/**
 * LicenseGuard - cryptographic security layer for license protection.
 *
 * @package Stackborg\WPCoreKits
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Addon;

/**
 * LicenseGuard — cryptographic security layer for license protection.
 *
 * Prevents license tampering through:
 * - HMAC-SHA256 payload signing (server signs, client verifies)
 * - AES-256-CBC encrypted storage (wp_options not readable)
 * - Per-site encryption key (license can't be copied between sites)
 * - File integrity hashing (detects addon file modifications)
 *
 * This is the core anti-tampering mechanism. Even if a user modifies
 * LicenseManager::isValid() to return true, the signed payload
 * verification in ServiceProvider boot will still block pro features.
 */
class LicenseGuard
{
    /**
     * Generate HMAC-SHA256 signature for a payload.
     *
     * @param array<string, mixed> $payload Data to sign
     * @param string               $key     Secret key for signing
     */
    public static function sign(array $payload, string $key): string
    {
        // Sort keys for deterministic signing regardless of order
        ksort($payload);
        $data = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return hash_hmac('sha256', $data, $key);
    }

    /**
     * Verify HMAC-SHA256 signature against a payload.
     * Uses timing-safe comparison to prevent timing attacks.
     *
     * @param array<string, mixed> $payload   Data that was signed
     * @param string               $signature Signature to verify
     * @param string               $key       Secret key used for signing
     */
    public static function verify(array $payload, string $signature, string $key): bool
    {
        if ($signature === '') {
            return false;
        }

        $expected = self::sign($payload, $key);

        // Timing-safe comparison prevents side-channel attacks
        return hash_equals($expected, $signature);
    }

    /**
     * Encrypt data for secure storage in wp_options.
     *
     * Uses AES-256-CBC with per-site key and random IV.
     * Output format: base64(iv + ciphertext)
     *
     * @param array<string, mixed> $data Data to encrypt
     * @return string Encrypted string safe for database storage
     */
    public static function encrypt(array $data): string
    {
        $key = self::siteKey();
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $cipher = 'aes-256-cbc';
        $ivLength = openssl_cipher_iv_length($cipher);
        $iv = openssl_random_pseudo_bytes($ivLength);

        $encrypted = openssl_encrypt($json, $cipher, $key, OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) {
            throw new \RuntimeException('Encryption failed');
        }

        // Prepend IV to ciphertext so we can decrypt later
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt data from wp_options.
     *
     * @param string $encrypted Encrypted string from encrypt()
     * @return array<string, mixed>|null Decrypted data, or null if tampered/invalid
     */
    public static function decrypt(string $encrypted): ?array
    {
        if ($encrypted === '') {
            return null;
        }

        $key = self::siteKey();
        $cipher = 'aes-256-cbc';
        $ivLength = openssl_cipher_iv_length($cipher);

        $raw = base64_decode($encrypted, true);
        if ($raw === false || strlen($raw) < $ivLength) {
            return null; // Tampered or corrupted data
        }

        $iv = substr($raw, 0, $ivLength);
        $ciphertext = substr($raw, $ivLength);

        $decrypted = openssl_decrypt($ciphertext, $cipher, $key, OPENSSL_RAW_DATA, $iv);
        if ($decrypted === false) {
            return null; // Decryption failed — wrong key or tampered
        }

        $data = json_decode($decrypted, true);
        if (!is_array($data)) {
            return null;
        }

        return $data;
    }

    /**
     * Generate a unique encryption key for this site.
     *
     * Derived from WordPress security salts + site URL.
     * Each site produces a different key, so encrypted data
     * from one site cannot be decrypted on another.
     */
    public static function siteKey(): string
    {
        $authKey = defined('AUTH_KEY') ? AUTH_KEY : 'sb_default_auth';
        $secureKey = defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : 'sb_default_secure';
        $siteUrl = function_exists('site_url') ? site_url() : 'localhost';

        return hash('sha256', $authKey . $secureKey . $siteUrl);
    }

    /**
     * Generate integrity hash for an addon directory.
     *
     * Hashes all PHP files in the addon to detect modifications.
     * Used optionally to verify addon code hasn't been tampered.
     */
    public static function integrityHash(string $addonDir): string
    {
        if (!is_dir($addonDir)) {
            return '';
        }

        $hashes = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($addonDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && in_array($file->getExtension(), ['php', 'json'], true)) {
                // Use relative path + content hash for deterministic result
                $relativePath = str_replace($addonDir, '', $file->getPathname());
                $hashes[$relativePath] = hash_file('sha256', $file->getPathname());
            }
        }

        // Sort by path for deterministic hash regardless of filesystem order
        ksort($hashes);

        return hash('sha256', json_encode($hashes));
    }

    /**
     * Create a signed license payload (for mock server / testing).
     *
     * In production, the real server creates this.
     * This method exists for development and testing.
     *
     * @param array<string, mixed> $licenseData License fields
     * @param string               $secretKey   Server's signing key
     * @return array{payload: array<string, mixed>, signature: string}
     */
    public static function createSignedPayload(array $licenseData, string $secretKey): array
    {
        $signature = self::sign($licenseData, $secretKey);

        return [
            'payload'   => $licenseData,
            'signature' => $signature,
        ];
    }
}
