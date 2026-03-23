<?php

/**
 * VersionResolver - SemVer parsing and constraint matching.
 *
 * @package Stackborg\WPCoreKits
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Addon;

/**
 * VersionResolver — SemVer parsing and constraint matching.
 *
 * Checks whether an addon's version requirements are satisfied
 * by the current environment (core version, UI version, PHP version).
 *
 * Supports constraints: >=, <=, >, <, =, ^ (caret/compatible), exact match.
 * Also supports compound constraints: ">=1.0.0 <3.0.0"
 */
class VersionResolver
{
    /**
     * Parse a SemVer string into [major, minor, patch] integers.
     *
     * @return array{int, int, int}
     * @throws \InvalidArgumentException If version is not valid SemVer
     */
    public static function parse(string $version): array
    {
        // Strip leading 'v' if present (e.g. v1.2.3)
        $version = ltrim($version, 'vV');

        // Append .0 for 2-part versions like "8.2" → "8.2.0"
        if (preg_match('/^\d+\.\d+$/', $version)) {
            $version .= '.0';
        }

        // Match major.minor.patch (ignore pre-release/build metadata)
        if (!preg_match('/^(\d+)\.(\d+)\.(\d+)/', $version, $matches)) {
            throw new \InvalidArgumentException("Invalid SemVer: {$version}");
        }

        return [(int) $matches[1], (int) $matches[2], (int) $matches[3]];
    }

    /**
     * Compare two SemVer strings.
     *
     * @return int -1 if $a < $b, 0 if equal, 1 if $a > $b
     */
    public static function compare(string $a, string $b): int
    {
        [$aMaj, $aMin, $aPat] = self::parse($a);
        [$bMaj, $bMin, $bPat] = self::parse($b);

        if ($aMaj !== $bMaj) {
            return $aMaj <=> $bMaj;
        }
        if ($aMin !== $bMin) {
            return $aMin <=> $bMin;
        }
        return $aPat <=> $bPat;
    }

    /**
     * Check if a version satisfies a constraint string.
     *
     * Supported formats:
     *   ">=1.0.0"          — at least 1.0.0
     *   "<=2.0.0"          — at most 2.0.0
     *   ">1.0.0"           — greater than 1.0.0
     *   "<2.0.0"           — less than 2.0.0
     *   "=1.0.0" or "1.0.0" — exactly 1.0.0
     *   "^1.2.0"           — compatible (>=1.2.0 <2.0.0)
     *   ">=1.0.0 <3.0.0"   — compound (AND)
     */
    public static function satisfies(string $version, string $constraint): bool
    {
        $constraint = trim($constraint);

        // Compound constraint: ">=1.0.0 <3.0.0" (space-separated AND)
        if (str_contains($constraint, ' ')) {
            $parts = preg_split('/\s+/', $constraint);
            foreach ($parts as $part) {
                if (!self::satisfies($version, $part)) {
                    return false;
                }
            }
            return true;
        }

        // Caret constraint: ^1.2.0 means >=1.2.0 <2.0.0
        if (str_starts_with($constraint, '^')) {
            $base = substr($constraint, 1);
            [$major] = self::parse($base);
            $upper = ($major + 1) . '.0.0';
            return self::satisfies($version, ">={$base}") && self::satisfies($version, "<{$upper}");
        }

        // Operator + version
        if (preg_match('/^(>=|<=|>|<|=)(.+)$/', $constraint, $m)) {
            $op = $m[1];
            $target = trim($m[2]);
            $cmp = self::compare($version, $target);

            return match ($op) {
                '>=' => $cmp >= 0,
                '<=' => $cmp <= 0,
                '>'  => $cmp > 0,
                '<'  => $cmp < 0,
                '='  => $cmp === 0,
            };
        }

        // Exact match (no operator)
        return self::compare($version, $constraint) === 0;
    }

    /**
     * Check full addon compatibility against current environment.
     *
     * @param AddonMeta    $meta           The addon metadata
     * @param string       $coreVersion    Current wp-core-kits version
     * @param string       $uiVersion      Current wp-ui-kits version
     * @param string|null  $phpVersion     Current PHP version (auto-detected if null)
     * @param string|null  $pluginVersion  Current host plugin version (e.g. MailPress 1.5.0)
     * @param callable|null $addonVersionResolver  fn(string $slug): ?string — get installed addon version
     * @param callable|null $pluginVersionResolver fn(string $slug): ?string — get WP plugin version
     */
    public static function checkAddonCompatibility(
        AddonMeta $meta,
        string $coreVersion,
        string $uiVersion,
        ?string $phpVersion = null,
        ?string $pluginVersion = null,
        ?callable $addonVersionResolver = null,
        ?callable $pluginVersionResolver = null,
    ): CompatResult {
        $phpVersion ??= PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.0';
        $errors = [];
        $warnings = [];

        $requires = $meta->requires;

        // Check core version
        if (isset($requires['core'])) {
            if (!self::satisfies($coreVersion, $requires['core'])) {
                $errors[] = "Requires core {$requires['core']}, you have {$coreVersion}";
            }
        }

        // Check UI version
        if (isset($requires['ui'])) {
            if (!self::satisfies($uiVersion, $requires['ui'])) {
                $errors[] = "Requires UI {$requires['ui']}, you have {$uiVersion}";
            }
        }

        // Check PHP version
        if (isset($requires['php'])) {
            if (!self::satisfies($phpVersion, $requires['php'])) {
                $errors[] = "Requires PHP {$requires['php']}, you have {$phpVersion}";
            }
        }

        // Check host plugin version (the plugin this addon belongs to)
        if (isset($requires['plugin']) && $pluginVersion !== null) {
            if (!self::satisfies($pluginVersion, $requires['plugin'])) {
                $errors[] = "Requires plugin {$requires['plugin']}, you have {$pluginVersion}";
            }
        }

        // Check addon-to-addon dependencies
        if (isset($requires['addons']) && is_array($requires['addons'])) {
            foreach ($requires['addons'] as $depSlug => $constraint) {
                $depVersion = $addonVersionResolver ? $addonVersionResolver($depSlug) : null;

                if ($depVersion === null) {
                    $errors[] = "Requires addon '{$depSlug}' ({$constraint}) — not installed";
                } elseif (!self::satisfies($depVersion, $constraint)) {
                    $errors[] = "Requires addon '{$depSlug}' {$constraint}, installed {$depVersion}";
                }
            }
        }

        // Check WordPress plugin dependencies
        if (isset($requires['plugins']) && is_array($requires['plugins'])) {
            foreach ($requires['plugins'] as $depSlug => $constraint) {
                $depVersion = $pluginVersionResolver ? $pluginVersionResolver($depSlug) : null;

                if ($depVersion === null) {
                    $errors[] = "Requires plugin '{$depSlug}' ({$constraint}) — not installed/active";
                } elseif (!self::satisfies($depVersion, $constraint)) {
                    $errors[] = "Requires plugin '{$depSlug}' {$constraint}, installed {$depVersion}";
                }
            }
        }

        // Check tested_up_to (warnings only — addon may still work)
        if (isset($requires['tested_up_to'])) {
            if (self::compare($coreVersion, $requires['tested_up_to']) > 0) {
                $warnings[] = "Addon tested up to core {$requires['tested_up_to']}, you have {$coreVersion}";
            }
        }

        return new CompatResult(
            compatible: count($errors) === 0,
            errors: $errors,
            warnings: $warnings,
        );
    }

    /**
     * Create an addon version resolver from AddonRegistry.
     *
     * @return callable fn(string $slug): ?string
     */
    public static function addonResolver(AddonRegistry $registry): callable
    {
        return function (string $slug) use ($registry): ?string {
            $addon = $registry->get($slug);
            return $addon?->version();
        };
    }

    /**
     * Create a WordPress plugin version resolver.
     * Uses get_plugins() + is_plugin_active() to check plugin availability.
     *
     * @return callable fn(string $slug): ?string
     */
    public static function pluginResolver(): callable
    {
        return function (string $slug): ?string {
            if (!function_exists('get_plugins')) {
                return null;
            }

            $plugins = get_plugins();
            foreach ($plugins as $file => $data) {
                // Match by directory name (e.g. 'woocommerce' matches 'woocommerce/woocommerce.php')
                $dir = dirname($file);
                if ($dir === $slug || $file === $slug) {
                    // Check if active
                    if (function_exists('is_plugin_active') && !is_plugin_active($file)) {
                        return null; // Installed but not active
                    }
                    return $data['Version'] ?? null;
                }
            }

            return null;
        };
    }
}
