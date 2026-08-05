# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2026-08-05

The rename below removes methods that subclasses call, so under SemVer this is
a major bump. An earlier note in this file suggested 1.2.0; that is wrong on
two counts — a breaking change cannot ship as a minor, and 1.2.0 was already
claimed by a section of this changelog that in fact shipped as v1.1.3–v1.1.7
(see below).

### Changed — BREAKING

- **REST\Controller**: route-registration helpers renamed
  `get()/post()/put()/delete()` → `routeGet()/routePost()/routePut()/routeDelete()`,
  and `publicGet()/publicPost()` → `publicRouteGet()/publicRoutePost()`.

  The short names collided with the most natural REST *handler* names. PHP
  requires an overriding method to match its parent's signature, so a
  controller could not declare `public function delete(WP_REST_Request $r)` at
  all — it fataled at class-load with "Declaration ... must be compatible
  with". Eleven controllers across the sb-* plugins hit exactly that
  (`delete()` in eight, `get()` in three), which blocked adoption of the base
  class entirely.

  Consumers upgrading must rewrite their `routes()` bodies. Handler method
  names are unaffected — routes reference them by string. This package's own
  `Addon\\AddonController` was updated with them.

### Added

- **Privacy\PersonalDataHandler**: base for WordPress personal-data exporters
  and erasers. Three plugins had the same four methods copy-pasted into their
  Plugin class, and both defects came along with every copy:

  - the query used `LIMIT 100` while the response always reported
    `'done' => true`, so a data subject with more than 100 rows silently
    received a truncated export — WordPress passes `$page` for exactly this
    and it was ignored
  - the eraser could not distinguish "deleted nothing" from "query failed",
    so a failed erasure was reported as a successful one

  Subclasses declare slug, label, table and the field mapping; batching, the
  response envelope and the registration shape live in the base.

- **Database::usersTable()**: returns `$wpdb->users`. On multisite the users
  table is shared network-wide and carries no per-site prefix, so
  `table('users')` produces a name that does not exist. `wpdb()` is private,
  so consumers previously had no way to reach it.

### Fixed

- **build**: `npm run build` copied only `styles/index.css` into `lib/`, but
  that file `@import`s `./tokens.css` and `./reset.css`, and the component
  stylesheets (`DashboardShell.css`, `DataTable.css`, `PageHeader.css`) sit
  beside their components. The published `@stackborg/wp-ui-kits/styles` export
  therefore never resolved for consumers. All CSS is now copied with its
  directory structure preserved.

- **Database::getRow()**: return type widened `?array` → `array|object|null`,
  on both the implementation and `Contracts\\DatabaseInterface`. PHP requires
  return types to be covariant, so widening only the implementation is a fatal
  error at class load — widening both is the whole fix.

  The method accepts an output type as its first variadic argument — the same
  contract as `getResults()` — but declared `?array`, so the documented call
  `getRow($sql, OBJECT, $id)` returned a `stdClass` from a method typed
  `?array` and raised a TypeError. Three call sites in sb-woopress had written
  exactly that call and fataled on gift-card redemption and cart recovery. The
  narrow type was the defect, not the callers.

## [1.1.7] - 2026-06-20

### Fixed

- **Tests**: added a `wp_mkdir_p` mock to the test bootstrap so the suite runs on CI

## [1.1.6] - 2026-06-20

### Added

- **Addon\AddonInstaller**: installer improvements, accompanying unit tests, documentation and community files

## [1.1.5] - 2026-06-16

Tagged at the same commit as v1.1.4; no code difference between the two.

## [1.1.4] - 2026-06-16

### Fixed

- **Tests**: `check_ajax_referer` stub typed `string|false` for its `queryArg` parameter
- **Addon\AddonApiClient**: removed a stray trailing newline (PSR-2)
- **Ajax**: resolved a PSR-12 PHPCS violation in `index.php`

## [1.1.3] - 2026-06-16

Previously published in this file as "[1.2.0] - 2026-06-15", a version that was
never tagged. The work below shipped as v1.1.3; the corrections that followed
are listed under v1.1.4 and v1.1.6, which this file had omitted entirely.

### Added

- **Ajax\Controller**: Declarative AJAX handler base class mirroring REST\Controller pattern — centralizes nonce verification, capability checks, and response formatting
- **Support\FileSystem**: Shared `removeDirectory()` utility extracted from AddonInstaller/AddonRemover
- **Contracts\AssetInterface**: Added `sharedFont()` method declaration
- **Tests**: WP HTTP API stubs (`wp_remote_get`, `wp_remote_request`, `is_wp_error`, `check_ajax_referer`) in bootstrap
- **Tests**: `ARRAY_N` constant in test bootstrap
- **Tests**: `AjaxControllerTest` and `FileSystemTest` unit tests

### Changed

- **Addon\AddonInstaller**: Replaced `file_get_contents()` remote download with `wp_remote_get()` — WP.org compliance requirement
- **Addon\AddonApiClient**: Removed `file_get_contents()` cURL fallback — now uses WordPress HTTP API exclusively
- **Addon\AddonInstaller**: Delegated `removeDirectory()` to `FileSystem::removeDirectory()`
- **Addon\AddonRemover**: Delegated `removeDirectory()` to `FileSystem::removeDirectory()`

### Fixed

- **Plugin\AdminDashboardTrait**: Removed Google Fonts CDN dependency — uses system font stack for WP.org GDPR compliance

## [1.1.2] - 2026-06-14

### Fixed

- **PHPStan Bootstrap**: Added `ARRAY_N` and `OBJECT` constant stubs for standalone analysis
- **REST\Controller**: Added `array<string, mixed>` type annotations to all route `$args` parameters
- **REST\Controller**: Replaced `'__return_true'` string with typed closure for PHPStan callable return
- **WordPress\Database**: Added `array<int, array<string, mixed>|object>` return type to `getResults()`

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
