<?php
/**
 * LicenseManager - secure license lifecycle management.
 *
 * @package Stackborg\WPCoreKits
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Addon;

/**
 * LicenseManager — secure license lifecycle management.
 *
 * Uses LicenseGuard internally for all cryptographic operations.
 * License data is encrypted in wp_options and signed by the server.
 *
 * Multi-point verification:
 * 1. Decrypt stored data (AES-256-CBC with per-site key)
 * 2. Verify server signature (HMAC-SHA256)
 * 3. Check expiry date
 * 4. Check site URL match
 *
 * Grace period: 72h without server contact → auto-downgrade.
 */
class LicenseManager
{
    /** Grace period in seconds (72 hours) */
    private const GRACE_PERIOD = 72 * 3600;

    /** Re-verification interval in seconds (24 hours) */
    private const VERIFY_INTERVAL = 24 * 3600;

    /**
     * @param string $optionPrefix  Prefix for wp_options keys (e.g. 'sb_mailpress')
     * @param string $verifyKey     Public verification key (embedded in plugin)
     */
    public function __construct(
        private readonly string $optionPrefix,
        private readonly string $verifyKey,
    ) {}

    /**
     * Activate a license for an addon.
     *
     * @param string $addonSlug  Addon to license
     * @param string $licenseKey License key from purchase
     * @param array<string, mixed> $apiResponse Signed response from server verification
     *        Expected: { payload: {status, expiry, site, ...}, signature: string }
     */
    public function activate(string $addonSlug, string $licenseKey, array $apiResponse): LicenseResult
    {
        $payload = $apiResponse['payload'] ?? [];
        $signature = $apiResponse['signature'] ?? '';

        // Verify server signature
        if (!LicenseGuard::verify($payload, $signature, $this->verifyKey)) {
            return LicenseResult::invalid('License signature verification failed');
        }

        // Check response status
        $status = $payload['status'] ?? '';
        if ($status !== 'active') {
            return LicenseResult::invalid('License is not active: ' . $status);
        }

        // Store encrypted license data
        $storeData = [
            'license_key'   => $licenseKey,
            'status'        => 'active',
            'expiry'        => $payload['expiry'] ?? '',
            'site'          => $payload['site'] ?? '',
            'signature'     => $signature,
            'payload'       => $payload,
            'verified_at'   => time(),
            'activated_at'  => time(),
        ];

        $encrypted = LicenseGuard::encrypt($storeData);
        update_option($this->optionKey($addonSlug), $encrypted);

        return LicenseResult::active(
            $storeData['expiry'],
            $storeData['site']
        );
    }

    /**
     * Deactivate a license for an addon.
     */
    public function deactivate(string $addonSlug): bool
    {
        delete_option($this->optionKey($addonSlug));
        return true;
    }

    /**
     * Check if an addon has a valid, non-expired license.
     *
     * Multi-point verification:
     * 1. Decrypt stored data
     * 2. Verify signature
     * 3. Check expiry
     * 4. Check site URL
     * 5. Check grace period
     */
    public function isValid(string $addonSlug): bool
    {
        $data = $this->getDecryptedData($addonSlug);
        if ($data === null) {
            return false;
        }

        // Check 1: Status must be active
        if (($data['status'] ?? '') !== 'active') {
            return false;
        }

        // Check 2: Verify server signature hasn't been tampered
        $payload = $data['payload'] ?? [];
        $signature = $data['signature'] ?? '';
        if (!LicenseGuard::verify($payload, $signature, $this->verifyKey)) {
            return false;
        }

        // Check 3: Check expiry
        $expiry = $data['expiry'] ?? '';
        if ($expiry !== '' && strtotime($expiry) < time()) {
            return false;
        }

        // Check 4: Site URL must match
        $licensedSite = $data['site'] ?? '';
        $currentSite = function_exists('site_url') ? site_url() : 'localhost';
        if ($licensedSite !== '' && $licensedSite !== $currentSite) {
            return false;
        }

        // Check 5: Grace period — if not verified recently, downgrade
        $verifiedAt = $data['verified_at'] ?? 0;
        if ((time() - $verifiedAt) > self::GRACE_PERIOD) {
            return false; // Hasn't contacted server in 72h
        }

        return true;
    }

    /**
     * Get license status string.
     *
     * @return string 'active'|'expired'|'invalid'|'none'
     */
    public function getStatus(string $addonSlug): string
    {
        $data = $this->getDecryptedData($addonSlug);
        if ($data === null) {
            return 'none';
        }

        if ($this->isValid($addonSlug)) {
            return 'active';
        }

        $expiry = $data['expiry'] ?? '';
        if ($expiry !== '' && strtotime($expiry) < time()) {
            return 'expired';
        }

        return 'invalid';
    }

    /**
     * Get license expiry date.
     */
    public function getExpiry(string $addonSlug): ?string
    {
        $data = $this->getDecryptedData($addonSlug);
        return $data['expiry'] ?? null;
    }

    /**
     * Update verification timestamp (called after successful server re-verify).
     * Also updates the payload and signature with fresh server data.
     *
     * @param array<string, mixed> $apiResponse Signed response from server
     */
    public function refreshVerification(string $addonSlug, array $apiResponse): bool
    {
        $data = $this->getDecryptedData($addonSlug);
        if ($data === null) {
            return false;
        }

        $payload = $apiResponse['payload'] ?? [];
        $signature = $apiResponse['signature'] ?? '';

        if (!LicenseGuard::verify($payload, $signature, $this->verifyKey)) {
            return false;
        }

        $data['verified_at'] = time();
        $data['payload'] = $payload;
        $data['signature'] = $signature;
        $data['status'] = $payload['status'] ?? $data['status'];
        $data['expiry'] = $payload['expiry'] ?? $data['expiry'];

        $encrypted = LicenseGuard::encrypt($data);
        update_option($this->optionKey($addonSlug), $encrypted);

        return true;
    }

    /**
     * Check if re-verification with server is needed (every 24h).
     */
    public function needsReVerification(string $addonSlug): bool
    {
        $data = $this->getDecryptedData($addonSlug);
        if ($data === null) {
            return false;
        }

        $verifiedAt = $data['verified_at'] ?? 0;
        return (time() - $verifiedAt) > self::VERIFY_INTERVAL;
    }

    /**
     * Read and decrypt stored license data.
     *
     * @return array<string, mixed>|null Null if no data or decryption fails
     */
    private function getDecryptedData(string $addonSlug): ?array
    {
        $encrypted = get_option($this->optionKey($addonSlug), '');
        if (!is_string($encrypted) || $encrypted === '') {
            return null;
        }

        return LicenseGuard::decrypt($encrypted);
    }

    /**
     * Generate wp_options key for an addon license.
     */
    private function optionKey(string $addonSlug): string
    {
        return $this->optionPrefix . '_license_' . $addonSlug;
    }
}
