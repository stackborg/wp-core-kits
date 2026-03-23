<?php

/**
 * CompatResult - result of a version compatibility check.
 *
 * @package Stackborg\WPCoreKits
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Addon;

/**
 * CompatResult — result of a version compatibility check.
 *
 * Immutable value object returned by VersionResolver.
 */
class CompatResult
{
    /**
     * @param bool     $compatible Whether the addon is compatible
     * @param string[] $errors     Blocking issues (addon won't work)
     * @param string[] $warnings   Non-blocking issues (addon may work but untested)
     */
    public function __construct(
        public readonly bool $compatible,
        public readonly array $errors = [],
        public readonly array $warnings = [],
    ) {
    }
}
