# wp-core-kits Features

## Plugin Architecture
- **SingletonTrait** — thread-safe singleton pattern for plugin classes
- **ServiceProvider** — modular feature registration with boot/register lifecycle
- **ProviderRegistry** — automatic provider discovery and management
- **HookManager** — bulk WordPress hook registration

## WordPress Abstractions
- **Hooks** — typed add_action/add_filter wrapper
- **Options** — WordPress options with internal caching
- **Transient** — cached data with expiry
- **Database** — typed $wpdb wrapper with auto table prefix
- **Asset** — script/style enqueuing with auto-versioning
- **Nonce** — CSRF protection wrapper
- **Sanitizer** — centralized input sanitization
- **User** — capabilities and identity wrapper

## REST API
- **Controller** — declarative route registration with permission handling
- **Response** — standardized success/error response helpers

## Addon System (Opt-in)
- **AddonRegistry** — discover, register, activate/deactivate addons
- **AddonMeta** — addon.json metadata parsing with validation
- **AddonInstaller** — ZIP download, checksum verify, install
- **AddonUpdater** — version check, auto-update policies, batch update
- **AddonRemover** — safe deactivation and cleanup
- **AddonController** — REST endpoints for all addon operations
- **FeatureManager** — single-line feature gating (addon + license check)
- **LicenseManager** — secure license activation/deactivation
- **LicenseGuard** — cryptographic license tamper protection
- **VersionResolver** — SemVer parsing, constraint matching
- **DependencyResolver** — addon→addon and plugin dependency checking

## Contracts
- Typed interfaces for all WordPress abstractions (testable, mockable)

## Utilities
- **Arr** — dot-notation array access, flatten, only, except
- **Str** — slug, snake_case, camelCase, truncate, contains
