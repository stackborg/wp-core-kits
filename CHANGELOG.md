# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-03-23

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
