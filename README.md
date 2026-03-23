# WP Core Kits

Typed PHP abstraction layer over WordPress APIs for building scalable plugins.

> **Internal package.** Maintained by [Stackborg](https://stackborg.com) for its own products. Public use permitted under MIT — no external support or guarantees. Use at your own risk.

## Requirements

- PHP 8.2+
- WordPress 6.0+

## Install

```bash
composer require stackborg/wp-core-kits
```

## What's Inside

| Module | Purpose |
|--------|---------|
| `WordPress\Options` | Cached options API |
| `WordPress\Hooks` | Typed hook registration |
| `WordPress\Sanitizer` | Centralized sanitization |
| `WordPress\Asset` | Auto-versioned script/style enqueue |
| `WordPress\Database` | Auto-prefixed, prepared-statement DB |
| `WordPress\Nonce` | CSRF token management |
| `WordPress\Transient` | Cached data with TTL |
| `WordPress\User` | Capability checks |
| `REST\Controller` | Declarative REST route registration |
| `REST\Response` | Typed response helpers |
| `Plugin\SingletonTrait` | Thread-safe singleton |
| `Plugin\ServiceProvider` | Modular plugin architecture |
| `Plugin\ProviderRegistry` | Ordered provider lifecycle |
| `Support\Arr` | Dot-notation array access |
| `Support\Str` | String case conversion |
| **Addon System (opt-in)** | |
| `Contracts\AddonInterface` | Addon contract |
| `Addon\AddonMeta` | addon.json parser + validation |
| `Addon\AddonRegistry` | Lifecycle — register, activate, deactivate, scan |
| `Addon\HasAddons` | Opt-in trait for plugins |
| `Addon\VersionResolver` | SemVer parse, compare, constraint matching |
| `Addon\AddonInstaller` | Download, verify, extract, install |
| `Addon\AddonRemover` | Deactivate, cleanup, delete |
| `Addon\AddonUpdater` | Version check, update with state preserve |
| `Addon\FeatureManager` | Feature gating — free/pro tier |
| `Addon\LicenseManager` | Encrypted license lifecycle |
| `Addon\LicenseGuard` | HMAC-SHA256 + AES-256-CBC security |
| `Addon\AddonApiClient` | Stackborg API communication |
| `Addon\AddonController` | REST endpoints for addon management |

## Usage

See **[DEVELOPER_GUIDE.md](DEVELOPER_GUIDE.md)** for complete API reference and examples.

## Development

```bash
composer install
composer check    # phpcs + phpstan + phpunit
```

## License

MIT — No warranty. No external support.
