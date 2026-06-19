# wp-core-kits — API Reference

> Shared PHP library providing typed WordPress wrappers for the Stackborg plugin ecosystem.
> Version: See [CHANGELOG.md](../CHANGELOG.md) for latest version.

---

## Installation

```json
// composer.json
{
    "require": {
        "stackborg/wp-core-kits": "^1.0"
    }
}
```

---

## WordPress Facades

### Database (`Stackborg\WPCoreKits\WordPress\Database`)

Typed `$wpdb` wrapper with auto table prefix and prepared statements.

> ⚠️ **Correct namespace:** `WordPress\Database` — NOT `Database\Database`

| Method | Signature | Returns | Description |
|--------|-----------|---------|-------------|
| `prefix()` | `(): string` | `"wp_"` | Get table prefix |
| `table()` | `(string $name): string` | `"wp_my_table"` | Auto-prefix table name (avoids double-prefix) |
| `charsetCollate()` | `(): string` | `"DEFAULT CHARACTER SET..."` | For CREATE TABLE statements |
| `insert()` | `(string $table, array $data): int\|false` | Insert ID | Auto-prefixes table, returns insert ID |
| `update()` | `(string $table, array $data, array $where): int\|false` | Rows affected | Auto-prefixes table |
| `delete()` | `(string $table, array $where): int\|false` | Rows affected | Auto-prefixes table |
| `getVar()` | `(string $query, ...$args): mixed` | Single value | Auto-prepared query |
| `getRow()` | `(string $query, ...$args): ?array` | Assoc array | Single row, default ARRAY_A |
| `getResults()` | `(string $query, ...$args): array` | Array of rows | Multiple rows, default ARRAY_A |
| `getCol()` | `(string $query, ...$args): array` | Array of values | Single column |
| `query()` | `(string $query, ...$args): int\|bool` | Rows affected | Raw query execution |
| `prepare()` | `(string $query, ...$args): ?string` | Prepared SQL | Safe query preparation |
| `insertId()` | `(): int` | Last insert ID | After INSERT operation |
| `escLike()` | `(string $text): string` | Escaped string | Escape LIKE pattern characters |

#### Usage Examples

```php
use Stackborg\WPCoreKits\WordPress\Database;

// Insert
$id = Database::insert('my_table', ['name' => 'John', 'email' => 'john@example.com']);

// Query with prepared statements
$user = Database::getRow(
    "SELECT * FROM %s WHERE id = %d",
    Database::table('my_table'),
    $id
);

// Multiple results
$active = Database::getResults(
    "SELECT * FROM %s WHERE status = %s",
    Database::table('my_table'),
    'active'
);

// CREATE TABLE migration
$charset = Database::charsetCollate();
$table = Database::table('my_table');
dbDelta("CREATE TABLE {$table} (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    name varchar(255) NOT NULL,
    PRIMARY KEY (id)
) {$charset};");
```

---

### Options (`Stackborg\WPCoreKits\WordPress\Options`)

WordPress Options wrapper with in-memory caching.

| Method | Signature | Returns | Description |
|--------|-----------|---------|-------------|
| `get()` | `(string $key, mixed $default = false): mixed` | Option value | Cached `get_option()` |
| `set()` | `(string $key, mixed $value): bool` | Success | `update_option()` + cache update |
| `delete()` | `(string $key): bool` | Success | `delete_option()` + cache clear |
| `has()` | `(string $key): bool` | Exists | Check if option exists |
| `flushCache()` | `(): void` | — | Clear in-memory cache |

```php
use Stackborg\WPCoreKits\WordPress\Options;

$settings = Options::get('my_plugin_settings', []);
Options::set('my_plugin_version', '1.0.0');
Options::delete('my_plugin_old_key');
```

---

### Hooks (`Stackborg\WPCoreKits\WordPress\Hooks`)

Clean, typed access to WordPress hook system.

| Method | Signature | Description |
|--------|-----------|-------------|
| `action()` | `(string $hook, callable $cb, int $priority = 10, int $args = 1): void` | `add_action()` |
| `filter()` | `(string $hook, callable $cb, int $priority = 10, int $args = 1): void` | `add_filter()` |
| `removeAction()` | `(string $hook, callable $cb, int $priority = 10): void` | `remove_action()` |
| `removeFilter()` | `(string $hook, callable $cb, int $priority = 10): void` | `remove_filter()` |
| `doAction()` | `(string $hook, ...$args): void` | `do_action()` |
| `applyFilters()` | `(string $hook, mixed $value, ...$args): mixed` | `apply_filters()` |

```php
use Stackborg\WPCoreKits\WordPress\Hooks;

Hooks::action('init', [$this, 'onInit']);
Hooks::filter('the_content', [$this, 'filterContent']);
Hooks::doAction('sb_myplugin_after_save', $data);
$value = Hooks::applyFilters('sb_myplugin_settings', $defaults);
```

---

### Sanitizer (`Stackborg\WPCoreKits\WordPress\Sanitizer`)

Centralized sanitization and escaping.

| Method | Signature | WordPress Equivalent |
|--------|-----------|---------------------|
| `text()` | `(string $input): string` | `sanitize_text_field()` |
| `email()` | `(string $input): string` | `sanitize_email()` |
| `url()` | `(string $input): string` | `esc_url_raw()` |
| `int()` | `(mixed $input): int` | `intval()` |
| `absint()` | `(mixed $input): int` | `absint()` |
| `escHtml()` | `(string $input): string` | `esc_html()` |
| `escAttr()` | `(string $input): string` | `esc_attr()` |
| `escUrl()` | `(string $input): string` | `esc_url()` |
| `kses()` | `(string $input): string` | `wp_kses_post()` |

---

### Nonce (`Stackborg\WPCoreKits\WordPress\Nonce`)

CSRF token management.

| Method | Signature | Description |
|--------|-----------|-------------|
| `create()` | `(string $action): string` | Create nonce token |
| `verify()` | `(string $nonce, string $action): bool` | Verify nonce validity |

---

### User (`Stackborg\WPCoreKits\WordPress\User`)

Current user utilities.

| Method | Signature | Description |
|--------|-----------|-------------|
| `can()` | `(string $capability): bool` | Check current user capability |
| `id()` | `(): int` | Get current user ID |
| `isLoggedIn()` | `(): bool` | Check if user is logged in |
| `isAdmin()` | `(): bool` | Check if user is administrator |

---

### Transient (`Stackborg\WPCoreKits\WordPress\Transient`)

Transient cache with typed interface.

| Method | Signature | Description |
|--------|-----------|-------------|
| `get()` | `(string $key): mixed` | Get transient value |
| `set()` | `(string $key, mixed $value, int $ttl = 0): bool` | Set with expiration (seconds) |
| `delete()` | `(string $key): bool` | Delete transient |
| `has()` | `(string $key): bool` | Check existence |

---

### Asset (`Stackborg\WPCoreKits\WordPress\Asset`)

Script and style registration.

| Method | Signature | Description |
|--------|-----------|-------------|
| `setVersion()` | `(string $version): void` | Set default version for assets |
| `script()` | `(string $handle, string $src, array $deps, bool $footer): void` | Enqueue JS |
| `style()` | `(string $handle, string $src, array $deps): void` | Enqueue CSS |
| `localize()` | `(string $handle, string $name, array $data): void` | Pass PHP data to JS |
| `sharedFont()` | `(string $name, string $url): void` | Register shared Google Font |

---

## REST API Layer

### Response (`Stackborg\WPCoreKits\REST\Response`)

> ⚠️ **Correct namespace:** `REST\Response` — NOT `WordPress\Response`

| Method | Signature | Description |
|--------|-----------|-------------|
| `success()` | `(mixed $data = null, int $status = 200): WP_REST_Response` | Success response |
| `error()` | `(string $message, int $status = 400, mixed $errors = null): WP_REST_Response` | Error response |
| `paginated()` | `(array $items, int $total, int $page, int $perPage): WP_REST_Response` | Paginated list |
| `notFound()` | `(string $message = '...'): WP_REST_Response` | 404 response |
| `forbidden()` | `(string $message = '...'): WP_REST_Response` | 403 response |

---

### Controller (`Stackborg\WPCoreKits\REST\Controller`)

Base class for REST endpoint handlers.

**Properties:**
- `$namespace` — REST namespace (e.g., `'sb-myplugin/v1'`)
- `$capability` — Required capability (default: `'manage_options'`)

**Methods:**
| Method | Description |
|--------|-------------|
| `routes(): void` | Abstract — define routes here |
| `register(): void` | Register all routes with WordPress |
| `checkPermission(): bool` | Default permission check using `$capability` |

**Route helpers** (used inside `routes()`):
- `$this->get($path, $method, $args = [])`
- `$this->post($path, $method, $args = [])`
- `$this->put($path, $method, $args = [])`
- `$this->delete($path, $method, $args = [])`

---

### RateLimiter (`Stackborg\WPCoreKits\REST\RateLimiter`)

IP-based rate limiting for REST endpoints.

| Method | Signature | Description |
|--------|-----------|-------------|
| `check()` | `(string $key, int $max = 60, int $window = 60): true\|WP_Error` | Check rate limit |
| `reset()` | `(string $key, ?string $ip = null): void` | Reset rate limit counter |

---

## Support Utilities

### Arr (`Stackborg\WPCoreKits\Support\Arr`)

| Method | Description |
|--------|-------------|
| `get(array, key, default)` | Dot-notation array access |
| `set(array, key, value)` | Dot-notation array setter |
| `has(array, key)` | Check key exists |
| `only(array, keys)` | Filter to specific keys |
| `except(array, keys)` | Exclude specific keys |

### Str (`Stackborg\WPCoreKits\Support\Str`)

| Method | Description |
|--------|-------------|
| `snake(value)` | Convert to snake_case |
| `camel(value)` | Convert to camelCase |
| `studly(value)` | Convert to StudlyCase |
| `startsWith(h, n)` | String starts with |
| `endsWith(h, n)` | String ends with |
| `contains(h, n)` | String contains |
| `limit(value, limit, end)` | Truncate with suffix |

### FileSystem (`Stackborg\WPCoreKits\Support\FileSystem`)

| Method | Description |
|--------|-------------|
| `removeDirectory(dir)` | Recursively remove directory |

---

## Plugin Architecture

### ServiceProvider (`Stackborg\WPCoreKits\Plugin\ServiceProvider`)

| Method | Description |
|--------|-------------|
| `register()` | Called during plugin init — register hooks, routes |
| `boot()` | Called after all providers registered — runtime setup |

### SingletonTrait (`Stackborg\WPCoreKits\Plugin\SingletonTrait`)

| Method | Description |
|--------|-------------|
| `getInstance()` | Get or create single instance |
| `resetInstance()` | Reset (for testing) |

### AdminDashboardTrait (`Stackborg\WPCoreKits\Plugin\AdminDashboardTrait`)

| Method | Description |
|--------|-------------|
| `adminConfig()` | Abstract — return dashboard config array |
| `registerAdminMenu()` | Add admin menu page |
| `renderAdminPage()` | Render React mount point |
| `enqueueAdminAssets()` | Load React dashboard JS/CSS |

### ProviderRegistry (`Stackborg\WPCoreKits\Plugin\ProviderRegistry`)

| Method | Description |
|--------|-------------|
| `add(provider)` | Add a ServiceProvider |
| `registerAll()` | Call register() on all providers |
| `bootAll()` | Call boot() on all providers |
| `all()` | Get all registered providers |
| `count()` | Count providers |

---

## Contracts (Interfaces)

All facades implement corresponding contracts for testability:

| Contract | Implemented By |
|----------|---------------|
| `DatabaseInterface` | `WordPress\Database` |
| `OptionsInterface` | `WordPress\Options` |
| `HooksInterface` | `WordPress\Hooks` |
| `SanitizerInterface` | `WordPress\Sanitizer` |
| `NonceInterface` | `WordPress\Nonce` |
| `TransientInterface` | `WordPress\Transient` |
| `UserInterface` | `WordPress\User` |
| `AssetInterface` | `WordPress\Asset` |
| `ServiceProviderInterface` | `Plugin\ServiceProvider` |
| `AddonInterface` | Used by addon system |

---

*Built by [Stackborg](https://stackborg.com) — © 2026*
