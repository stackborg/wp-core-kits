# Contributing to WP Core Kits

Thank you for your interest in contributing to **WP Core Kits** — the WordPress Plugin Foundation library that provides typed wrappers for WP APIs, DI container, REST infrastructure, and plugin architecture primitives. This is the shared foundation that **all Stackborg plugins depend on**, so changes here have ecosystem-wide impact.

## Code of Conduct

We are committed to a welcoming and harassment-free environment. Be respectful, constructive, and professional in all interactions. Discrimination, personal attacks, and disruptive behavior will not be tolerated.

## Getting Started

1. **Fork** the repository on GitHub.
2. **Clone** your fork locally:
   ```bash
   git clone git@github.com:YOUR_USERNAME/wp-core-kits.git
   cd wp-core-kits
   ```
3. **Create a branch** for your work:
   ```bash
   git checkout -b feature/your-feature-name
   ```

## Development Setup

### Requirements
- PHP 8.2+
- Composer

### Installation
```bash
# Install all dependencies
composer install
```

> **Note:** This is a library package (`"type": "library"`), not a WordPress plugin. It is consumed by plugins via Composer. You do not need a running WordPress instance to develop or test core kits.

## Project Structure

```
src/
├── WordPress/      # Typed facades: Database, Options, Hooks, Sanitizer, Nonce
├── REST/           # REST API infrastructure: Response, Controller base classes
├── Container/      # Dependency injection container
├── Foundation/     # Base plugin class, service providers, lifecycle hooks
tests/              # PHPUnit test files
```

Namespace: `Stackborg\WPCoreKits`

## Coding Standards

- **PHP 8.2+** — every file must start with `declare(strict_types=1);`
- **PSR-4 autoloading** — classes in `src/` map to `Stackborg\WPCoreKits\*`
- **ABSPATH guard** — every PHP file must include:
  ```php
  defined('ABSPATH') || exit;
  ```
- **Correct namespace conventions** — downstream plugins must use:
  - `WordPress\Database` (not `Database\Database`) for `$wpdb` operations
  - `WordPress\Options` for `get_option` / `update_option`
  - `WordPress\Hooks` for `add_action` / `add_filter`
  - `WordPress\Sanitizer` for input sanitization
  - `WordPress\Nonce` for nonce verification
  - `REST\Response` (not `WordPress\Response`) for REST API responses
- **Backward compatibility** — breaking changes must be versioned. All public APIs must remain stable within a major version.

## Testing

```bash
# Run all tests
composer test

# Run static analysis
composer analyse

# Run code style checks
composer phpcs

# Run all checks together
composer check
```

Write tests in `tests/` following the namespace `Stackborg\WPCoreKits\Tests`. Every public facade and utility must have test coverage.

## Submitting Changes

### Branch Naming
- `feature/short-description` — new features
- `fix/short-description` — bug fixes
- `docs/short-description` — documentation updates

### Commit Messages
Write clear, descriptive commit messages in English:
```
Add typed wrapper for wp_schedule_event in WordPress\Cron

Fix Options facade returning unserialize errors on array values
```

### Pull Request Process
1. Ensure all checks pass: `composer check`
2. Write a clear PR description explaining **what** and **why**
3. Note any downstream plugin impact
4. Reference any related issues (e.g., `Fixes #42`)

> **⚠️ Important:** Since all Stackborg plugins depend on this library, any breaking changes require a major version bump and must be discussed in an issue first.

## Security

If you discover a security vulnerability, **do not** open a public issue. Instead, email **security@stackborg.com** with a detailed description. We will respond within 48 hours and work with you to resolve the issue before any public disclosure.

---

Thank you for helping build a stronger foundation for the WordPress ecosystem! 🏗️
