<?php

/**
 * LicenseResult - outcome of a license operation.
 *
 * @package Stackborg\WPCoreKits
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Addon;

/**
 * LicenseResult — outcome of a license operation.
 */
class LicenseResult
{
    public function __construct(
        public readonly bool $valid,
        public readonly string $status,
        public readonly string $message,
        public readonly ?string $expiry = null,
        public readonly ?string $siteUrl = null,
    ) {
    }

    public static function active(string $expiry, string $siteUrl): self
    {
        return new self(true, 'active', 'License is active', $expiry, $siteUrl);
    }

    public static function expired(string $expiry): self
    {
        return new self(false, 'expired', 'License has expired', $expiry);
    }

    public static function invalid(string $message = 'Invalid license key'): self
    {
        return new self(false, 'invalid', $message);
    }

    public static function none(): self
    {
        return new self(false, 'none', 'No license activated');
    }
}
