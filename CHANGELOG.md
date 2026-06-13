# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.1] - 2026-06-13

### Fixed

- **Plugin\AdminDashboardTrait**: Removed `ABSPATH` guard (PSR-1 side effects), fixed inline control structure, broke long heredoc lines for 120-char limit
- **REST\RateLimiter**: Removed `ABSPATH` guard (PSR-1 side effects), fixed inline control structure
- **REST\Controller**: Broke `addRoute()` method signature across multiple lines for 120-char compliance

## [1.1.0] - 2026-06-13

### Added

- **Database**: Output type support — `ARRAY_A` (default), `ARRAY_N`, `OBJECT` for `select()`, `row()`, and raw query methods
- **Database**: `lastInsertId()` helper for post-insert workflows
- **Plugin\AdminDashboardTrait**: Reusable React-based admin dashboard rendering with automatic script/style enqueue and settings localization
- **WordPress\Response**: `forbidden()` helper added to REST response builders

### Changed

- **Database**: Default output type reverted to `ARRAY_A` for backward compatibility with array-syntax access patterns across all consuming plugins
- **Compliance**: All consuming plugins updated to use `wp_unslash()` before sanitization on `$_POST`, `$_GET`, `$_COOKIE` superglobal access — WordPress.org plugin review requirement
- **Compliance**: All hardcoded error messages wrapped with `esc_html__()` for i18n
- **Compliance**: `stripslashes()` replaced with `wp_unslash()` across all consuming plugins

### Fixed

- **LoginPopup nonce mismatch**: `wp_localize_script` nonce action corrected to match `check_ajax_referer` action

## [1.0.3] - 2026-05-27

### Changed

- **Addon\AddonRegistry**: Updated core addon registry logic
- **Addon\AddonController**: Updated controller response handling
- **Addon\AddonUpdater**: Improved updater workflow

### Removed

- **composer.json**: Removed hardcoded `version` property — Packagist now detects version from git tags automatically

## [1.0.2] - 2026-03-22

### Fixed

- **Contracts\AddonInterface**: Added missing `tables()` method to interface contract
- **WordPress\Database**: Fixed `getRow()` return type annotation
- **Tests**: Added `tables()` to test mock classes implementing `AddonInterface`

## [1.0.1] - 2026-03-23

### Changed

- **Code Style**: Standardized file headers, constructor bodies, and docblock formatting across all core kit source files

## [1.0.0] - 2026-03-21

### Added

- **Contracts**: `OptionsInterface`, `HooksInterface`, `SanitizerInterface`, `AssetInterface`, `NonceInterface`, `TransientInterface`, `UserInterface`, `DatabaseInterface`, `ServiceProviderInterface`, `AddonInterface`
- **WordPress wrappers**: `Options` (in-memory cached), `Hooks`, `Sanitizer`, `Asset` (auto-versioned), `Nonce`, `Transient`, `User`, `Database` (auto table prefix, prepared statements)
- **REST layer**: Declarative `Controller` with route helpers, `Response` with success/error/paginated/notFound/forbidden
- **Plugin primitives**: `SingletonTrait` (thread-safe), `ServiceProvider`, `ProviderRegistry` (ordered lifecycle), `HookManager` (bulk registration)
- **Support utilities**: `Arr` (dot notation access), `Str` (case conversion, string helpers)
- **Addon System** (opt-in): `AddonMeta`, `AddonRegistry`, `HasAddons`, `AddonServiceProvider`, `VersionResolver`, `CompatResult`, `AddonInstaller`, `AddonRemover`, `AddonUpdater`, `InstallResult`, `FeatureManager`, `LicenseManager`, `LicenseGuard`, `LicenseResult`, `AddonApiClient`, `AddonController`
- PHPUnit test bootstrap with comprehensive WordPress function stubs
- PHPStan level 6 configuration
- PSR-12 coding standards via PHP_CodeSniffer
