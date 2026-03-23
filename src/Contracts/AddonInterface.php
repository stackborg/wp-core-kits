<?php
/**
 * AddonInterface - contract for all addon modules.
 *
 * @package Stackborg\WPCoreKits
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Contracts;

/**
 * AddonInterface — contract for all addon modules.
 *
 * Every addon must implement this interface. The parent plugin
 * uses this to discover capabilities, register features,
 * and boot the addon's service providers.
 */
interface AddonInterface
{
    /**
     * Unique addon identifier used in file paths, options, and API calls.
     * Must be lowercase, alphanumeric with hyphens (e.g. 'email-templates').
     */
    public function slug(): string;

    /**
     * Human-readable addon name for dashboard display.
     */
    public function name(): string;

    /**
     * Current addon version (SemVer format: major.minor.patch).
     */
    public function version(): string;

    /**
     * Addon monetization type.
     *
     * @return string 'free'|'paid'|'freemium'
     */
    public function type(): string;

    /**
     * Feature manifest — maps feature slugs to their access tier.
     *
     * Example:
     *   [
     *     'basic_sequences'    => 'free',
     *     'conditional_logic'  => 'pro',
     *     'ab_split'           => 'pro',
     *   ]
     *
     * @return array<string, string> feature_slug => tier ('free'|'pro')
     */
    public function features(): array;

    /**
     * ServiceProvider class names that this addon registers.
     * Only booted when addon is active (and licensed, if paid).
     *
     * @return array<int, class-string>
     */
    public function providers(): array;

    /**
     * Version requirements for compatibility checking.
     *
     * Example:
     *   [
     *     'core' => '>=1.0.0',
     *     'ui'   => '>=1.0.0',
     *     'php'  => '>=8.2',
     *   ]
     *
     * @return array<string, string>
     */
    public function requires(): array;

    /**
     * Short description for the addons directory listing.
     */
    public function description(): string;

    /**
     * Cleanup hook — called before addon files are deleted.
     * Use this to remove addon-specific DB tables, options, transients.
     */
    public function cleanup(): void;
}
