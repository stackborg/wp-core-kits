<?php

/**
 * InstallResult - outcome of an addon install/update operation.
 *
 * @package Stackborg\WPCoreKits
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Addon;

/**
 * InstallResult — outcome of an addon install/update operation.
 */
class InstallResult
{
    /**
     * @param string[] $errors
     */
    public function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly ?AddonMeta $meta = null,
        public readonly array $errors = [],
    ) {
    }

    public static function ok(string $message, ?AddonMeta $meta = null): self
    {
        return new self(true, $message, $meta);
    }

    /**
     * @param string[] $errors
     */
    public static function fail(string $message, array $errors = []): self
    {
        return new self(false, $message, null, $errors);
    }
}
