<?php
/**
 * AddonMeta - reads and validates addon.json metadata.
 *
 * @package Stackborg\WPCoreKits
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Addon;

/**
 * AddonMeta — reads and validates addon.json metadata.
 *
 * Each addon directory must contain an addon.json file that
 * describes the addon. This class parses that file and provides
 * typed access to all metadata fields.
 */
class AddonMeta
{
    public readonly string $slug;
    public readonly string $name;
    public readonly string $version;
    public readonly string $type;
    public readonly string $description;
    public readonly string $icon;

    /** @var array<string, string> feature_slug => tier */
    public readonly array $features;

    /** @var array<string, string> requirement => constraint */
    public readonly array $requires;

    /** @var array<string, mixed> price info */
    public readonly array $price;

    /** @var array<int, class-string> */
    public readonly array $providers;

    /** @var string auto|manual|security */
    public readonly string $updatePolicy;

    /**
     * @param array<string, mixed> $data Raw decoded addon.json
     */
    private function __construct(array $data)
    {
        $this->slug        = (string) ($data['slug'] ?? '');
        $this->name        = (string) ($data['name'] ?? '');
        $this->version     = (string) ($data['version'] ?? '0.0.0');
        $this->type        = (string) ($data['type'] ?? 'free');
        $this->description = (string) ($data['description'] ?? '');
        $this->icon        = (string) ($data['icon'] ?? '');
        $this->features    = (array)  ($data['features'] ?? []);
        $this->requires    = (array)  ($data['requires'] ?? []);
        $this->price       = (array)  ($data['price'] ?? []);
        $this->providers   = (array)  ($data['providers'] ?? []);
        $this->updatePolicy = (string) ($data['update_policy'] ?? 'auto');
    }

    /**
     * Parse addon.json from a directory path.
     *
     * @throws \RuntimeException If addon.json missing or invalid
     */
    public static function fromPath(string $addonDir): self
    {
        $file = rtrim($addonDir, '/') . '/addon.json';

        if (!file_exists($file)) {
            throw new \RuntimeException("addon.json not found in: {$addonDir}");
        }

        $content = file_get_contents($file);
        if ($content === false) {
            throw new \RuntimeException("Cannot read addon.json in: {$addonDir}");
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            throw new \RuntimeException("Invalid JSON in addon.json: {$addonDir}");
        }

        $meta = new self($data);
        $meta->validate();

        return $meta;
    }

    /**
     * Create from an array (useful for API responses or testing).
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $meta = new self($data);
        $meta->validate();

        return $meta;
    }

    /**
     * Validate required fields are present and correct.
     *
     * @throws \RuntimeException On validation failure
     */
    private function validate(): void
    {
        if ($this->slug === '') {
            throw new \RuntimeException('Addon slug is required');
        }

        // Slug rules:
        // - 2-50 characters
        // - lowercase letters, numbers, and hyphens only
        // - must start with a letter
        // - must end with a letter or number (not hyphen)
        // - no consecutive hyphens
        if (strlen($this->slug) < 2 || strlen($this->slug) > 50) {
            throw new \RuntimeException(
                "Addon slug must be 2-50 characters: '{$this->slug}'"
            );
        }

        if (!preg_match('/^[a-z][a-z0-9-]*[a-z0-9]$/', $this->slug)) {
            throw new \RuntimeException(
                "Addon slug must start with a letter, end with a letter/number, "
                . "and contain only lowercase letters, numbers, hyphens: '{$this->slug}'"
            );
        }

        if (str_contains($this->slug, '--')) {
            throw new \RuntimeException(
                "Addon slug cannot contain consecutive hyphens: '{$this->slug}'"
            );
        }

        if ($this->name === '') {
            throw new \RuntimeException('Addon name is required');
        }

        if (!in_array($this->type, ['free', 'paid', 'freemium'], true)) {
            throw new \RuntimeException("Invalid addon type: {$this->type}");
        }

        // Validate update policy
        if (!in_array($this->updatePolicy, ['auto', 'manual', 'security'], true)) {
            throw new \RuntimeException("Invalid update_policy: {$this->updatePolicy}");
        }

        // Validate version format (strict SemVer: major.minor.patch)
        if (!preg_match('/^\d+\.\d+\.\d+/', $this->version)) {
            throw new \RuntimeException("Invalid version format: {$this->version}");
        }
    }

    /**
     * Check if this addon has any pro-tier features.
     */
    public function hasPaidFeatures(): bool
    {
        foreach ($this->features as $tier) {
            if ($tier === 'pro') {
                return true;
            }
        }
        return false;
    }

    /**
     * Get feature tier. Returns null if feature doesn't exist.
     */
    public function getFeatureTier(string $feature): ?string
    {
        return $this->features[$feature] ?? null;
    }

    /**
     * Get price display string (e.g. "$4.99/mo").
     * This is a display hint only — full pricing is server-controlled.
     */
    public function priceDisplay(): ?string
    {
        return $this->price['display'] ?? null;
    }

    /**
     * Get pricing page URL.
     */
    public function priceUrl(): ?string
    {
        return $this->price['url'] ?? null;
    }

    /**
     * Detect icon type: url, svg, emoji, or none.
     *
     * URL  → starts with http:// or https://
     * SVG  → starts with <svg (inline SVG markup)
     * Emoji/text → any other non-empty string
     * None → empty string
     */
    public function iconType(): string
    {
        if ($this->icon === '') {
            return 'none';
        }

        if (str_starts_with($this->icon, 'http://') || str_starts_with($this->icon, 'https://')) {
            return 'url';
        }

        if (str_starts_with(trim($this->icon), '<svg')) {
            return 'svg';
        }

        return 'emoji';
    }

    /**
     * Convert back to array (for API responses, serialization).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'slug'        => $this->slug,
            'name'        => $this->name,
            'version'     => $this->version,
            'type'        => $this->type,
            'description' => $this->description,
            'icon'        => $this->icon,
            'icon_type'   => $this->iconType(),
            'features'    => $this->features,
            'requires'    => $this->requires,
            'price'         => $this->price,
            'price_display' => $this->priceDisplay(),
            'price_url'      => $this->priceUrl(),
            'providers'      => $this->providers,
            'update_policy'  => $this->updatePolicy,
        ];
    }
}
