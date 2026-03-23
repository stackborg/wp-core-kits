# WP Core Kits — Developer Guide

Complete API reference for Stackborg WordPress plugin development.

---

## Options

In-memory cached — same key read multiple times = 1 DB hit.

```php
use Stackborg\WPCoreKits\WordPress\Options;

Options::get('key', $default);   // Read
Options::set('key', $value);     // Write
Options::delete('key');           // Delete
Options::has('key');              // Exists
Options::flushCache();            // Clear in-memory cache
```

---

## Hooks

```php
use Stackborg\WPCoreKits\WordPress\Hooks;

Hooks::action('admin_init', [$this, 'setup']);
Hooks::action('admin_init', [$this, 'setup'], 20);       // custom priority
Hooks::filter('the_content', [$this, 'modify'], 10, 2);  // 2 args
Hooks::removeAction('admin_init', [$this, 'setup']);
Hooks::doAction('my_plugin_ready', $data);
$result = Hooks::applyFilters('my_data', $default, $context);
```

---

## Sanitizer

Single entry point for all sanitization and escaping.

```php
use Stackborg\WPCoreKits\WordPress\Sanitizer;

Sanitizer::text($input);      // sanitize_text_field
Sanitizer::email($input);     // sanitize_email
Sanitizer::url($input);       // esc_url_raw
Sanitizer::int($input);       // (int) cast
Sanitizer::absint($input);    // abs((int))
Sanitizer::escHtml($input);   // esc_html
Sanitizer::escAttr($input);   // esc_attr
Sanitizer::escUrl($input);    // esc_url
Sanitizer::kses($input);      // wp_kses_post
```

---

## Asset

Auto-versioned by plugin version. Call `setVersion()` once in bootstrap.

```php
use Stackborg\WPCoreKits\WordPress\Asset;

Asset::setVersion('1.2.0');
Asset::script('handle', $url, ['react'], true);          // footer = true
Asset::style('handle', $url, ['wp-components']);
Asset::localize('handle', 'sbMyPluginData', [
    'apiUrl' => rest_url('my-plugin/v1'),
    'nonce'  => wp_create_nonce('wp_rest'),
]);
```

---

## Database

Auto table prefix. All queries use prepared statements.

```php
use Stackborg\WPCoreKits\WordPress\Database;

// Table name helper
$table = Database::table('my_table');  // → wp_my_table

// CRUD
Database::insert('my_table', ['col' => 'value']);  // returns insert_id
Database::update('my_table', ['col' => 'new'], ['id' => 1]);
Database::delete('my_table', ['id' => 1]);

// Queries
$count = Database::getVar("SELECT COUNT(*) FROM " . Database::table('my_table'));
$rows  = Database::getResults("SELECT * FROM " . Database::table('my_table') . " WHERE status = %s", 'active');
$row   = Database::getRow("SELECT * FROM " . Database::table('my_table') . " WHERE id = %d", 5);
Database::query("TRUNCATE TABLE " . Database::table('my_table'));
```

---

## Nonce

```php
use Stackborg\WPCoreKits\WordPress\Nonce;

$token = Nonce::create('my_action');
$valid = Nonce::verify($token, 'my_action');  // bool
```

---

## Transient

```php
use Stackborg\WPCoreKits\WordPress\Transient;

Transient::set('key', $data, 3600);  // TTL in seconds
$data = Transient::get('key');        // false if expired
Transient::has('key');                // bool
Transient::delete('key');
```

---

## User

```php
use Stackborg\WPCoreKits\WordPress\User;

User::can('manage_options');  // bool
User::id();                   // int
User::isAdmin();              // bool (manage_options)
User::isLoggedIn();           // bool (id > 0)
```

---

## REST Controller

Extend `Controller`, define routes declaratively.

```php
use Stackborg\WPCoreKits\REST\Controller;
use Stackborg\WPCoreKits\REST\Response;
use Stackborg\WPCoreKits\WordPress\Options;

class SettingsController extends Controller
{
    protected string $namespace = 'my-plugin/v1';
    protected string $capability = 'manage_options';  // default

    public function routes(): void
    {
        $this->get('/settings', 'index');
        $this->post('/settings', 'store');
        $this->delete('/settings/(?P<key>[\\w]+)', 'destroy');
    }

    public function index(): \WP_REST_Response
    {
        return Response::success(Options::get('my_settings', []));
    }

    public function store(\WP_REST_Request $req): \WP_REST_Response
    {
        Options::set('my_settings', $req->get_json_params());
        return Response::success(null, 201);
    }

    public function destroy(\WP_REST_Request $req): \WP_REST_Response
    {
        $key = $req->get_param('key');
        return Response::success(['deleted' => $key]);
    }
}
```

### Response Helpers

```php
Response::success($data);                         // 200
Response::success($data, 201);                    // custom status
Response::error('Not found', 404);                // error with status
Response::error('Validation', 422, $fieldErrors); // with details
Response::paginated($items, $total, $page, $perPage);
Response::notFound();                              // 404
Response::forbidden();                             // 403
```

---

## Service Provider

Modular plugin architecture. Each feature area = one provider.

```php
use Stackborg\WPCoreKits\Plugin\ServiceProvider;
use Stackborg\WPCoreKits\WordPress\Hooks;

class SettingsProvider extends ServiceProvider
{
    public function register(): void
    {
        Hooks::action('rest_api_init', [new SettingsController(), 'register']);
        Hooks::action('admin_menu', [$this, 'addMenu']);
    }

    // boot() is optional — called after all providers are registered
    public function boot(): void
    {
        // Logic that depends on other providers
    }
}
```

---

## Plugin Singleton + Provider Registry

```php
use Stackborg\WPCoreKits\Plugin\SingletonTrait;
use Stackborg\WPCoreKits\Plugin\ProviderRegistry;

class Plugin
{
    use SingletonTrait;

    private ProviderRegistry $providers;

    protected function init(): void
    {
        $this->providers = new ProviderRegistry();
        $this->providers->add(new SettingsProvider());
        $this->providers->add(new DashboardProvider());
        $this->providers->registerAll();
        $this->providers->bootAll();
    }
}

// In main plugin file:
Plugin::getInstance();
```

---

## HookManager (Bulk Registration)

```php
use Stackborg\WPCoreKits\Plugin\HookManager;

HookManager::register([
    ['action', 'admin_init', [$this, 'setup']],
    ['action', 'rest_api_init', [$this, 'routes']],
    ['filter', 'plugin_action_links', [$this, 'links'], 10, 2],
]);
```

---

## Support Utilities

### Arr (dot notation)

```php
use Stackborg\WPCoreKits\Support\Arr;

$value = Arr::get($config, 'database.host', 'localhost');
Arr::set($config, 'database.port', 3306);
Arr::has($config, 'database.host');     // bool
Arr::only($data, ['name', 'email']);    // subset
Arr::except($data, ['password']);       // exclude keys
```

### Str

```php
use Stackborg\WPCoreKits\Support\Str;

Str::snake('myPlugin');      // 'my_plugin'
Str::camel('my_plugin');     // 'myPlugin'
Str::studly('my_plugin');    // 'MyPlugin'
Str::contains($s, 'foo');   // bool
Str::startsWith($s, 'sb');  // bool
Str::limit($s, 50);         // truncate with '...'
```

---

## Addon System (Opt-in)

Plugins that need addon support add a trait — others are unaffected.

### Code Conventions

#### Path Alias (`@/`)

All internal imports in `wp-ui-kits` use the `@/` path alias instead of relative paths.

```typescript
// ✅ Correct
import { Button } from '@/components/Button';
import type { AddonState } from '@/types/addon.d';

// ❌ Wrong — do not use relative paths
import { Button } from './Button';
import type { AddonState } from '../types/addon.d';
```

**Configuration** — the alias is set in `tsconfig.json` and `vitest.config.ts`:
```json
{ "paths": { "@/*": ["./src/*"] } }
```

#### File Headers

Every component and module file **must** start with a JSDoc header block that explains:
1. **What** — the component name and purpose
2. **Why** — when to use it
3. **Usage** — example code

**Applies to both `wp-core-kits` (PHP) and `wp-ui-kits` (TypeScript).**

**TypeScript / TSX:**
```tsx
/**
 * Button — shared button primitive with variants, sizes, and loading state.
 *
 * All Stackborg plugins use this component for consistent button styling.
 * Colors are controlled via CSS tokens so plugins can override branding.
 *
 * Usage:
 *   <Button variant="primary" size="sm" onClick={fn}>Save</Button>
 */
```

**PHP:**
```php
/**
 * AddonMeta — reads and validates addon.json metadata.
 *
 * Each addon directory must contain an addon.json file that
 * describes the addon. This class parses that file and provides
 * typed access to all metadata fields.
 */
class AddonMeta
```

### Naming Conventions

#### Addon Slug (`slug`)

The slug is the unique identifier for the addon. **Rules:**

| Rule | Detail |
|------|--------|
| Length | 2-50 characters |
| Start | Must start with a **lowercase letter** (`a-z`) |
| End | Must end with a **letter or number** |
| Characters | Only lowercase letters, numbers, and hyphens |
| Hyphens | No consecutive hyphens (`--`) |
| Format | `kebab-case` |

```
✅ Valid:   automation, email-templates, ab-testing, woo-bridge
❌ Invalid: A_Automation, 1addon, my--addon, -start, end-, ab
```

#### Feature Keys

Feature keys follow the same `snake_case` convention:

```json
{
  "features": {
    "basic_reports": "free",
    "conditional_logic": "pro",
    "ab_testing": "pro"
  }
}
```

#### Addon Type

| Type | Meaning |
|------|---------|
| `free` | All features free, no license needed |
| `paid` | All features require license |
| `freemium` | Some free features + some pro (licensed) features |

#### Version Format

Strict SemVer: `MAJOR.MINOR.PATCH` (e.g. `1.0.0`, `2.3.1`)

#### Directory Structure

```
your-plugin/
└── addons/
    └── automation/          ← slug = directory name
        ├── addon.json       ← required metadata
        ├── src/
        │   └── AutomationProvider.php
        └── ...
```

### Enabling Addon Support

```php
use Stackborg\WPCoreKits\Addon\HasAddons;

class Plugin
{
    use SingletonTrait, HasAddons;

    protected function init(): void
    {
        $this->enableAddons(__DIR__ . '/addons', 'sb_mailpress');
    }
}
```

### addon.json Schema

Each addon contains an `addon.json`:

```json
{
  "slug": "automation",
  "name": "Automation",
  "version": "1.0.0",
  "type": "freemium",
  "description": "Email automation sequences",
  "icon": "https://cdn.stackborg.com/icons/automation.svg",
  "update_policy": "auto",
  "features": {
    "sequences": "free",
    "conditional_logic": "pro"
  },
  "requires": {
    "core": ">=1.0.0",
    "ui": ">=1.0.0",
    "php": ">=8.2",
    "plugin": ">=1.5.0",
    "addons": { "templates": ">=1.0.0" },
    "plugins": { "woocommerce": ">=8.0.0" }
  },
  "price": {
    "display": "$4.99/mo",
    "url": "https://stackborg.com/pricing/automation"
  }
}
```

| Key | Purpose | Values/Example |
|-----|---------|----------------|
| `slug` | Unique identifier | `automation` (kebab-case, 2-50 chars) |
| `icon` | Icon display | URL, inline `<svg>`, or emoji |
| `type` | Addon pricing model | `free`, `paid`, `freemium` |
| `update_policy` | Auto-update behavior | `auto`, `manual`, `security` |
| `price.display` | Price hint (server-controlled) | `"$4.99/mo"` |
| `price.url` | Pricing page link | URL string |
| `core` | wp-core-kits version | `>=1.0.0` |
| `plugin` | Host plugin version | `^1.5.0` |
| `addons` | Addon→addon deps | `{"templates": ">=1.0.0"}` |
| `plugins` | WordPress plugin deps | `{"woocommerce": ">=8.0.0"}` |

**Update Policy:**
- `auto` — minor/patch auto-update, major needs confirmation
- `manual` — always needs user confirmation
- `security` — only patch-level auto-updates

### Lifecycle

```php
use Stackborg\WPCoreKits\Addon\{AddonRegistry, AddonInstaller, AddonRemover, AddonUpdater};

// Install from zip
$installer = new AddonInstaller($registry, $addonsDir, '1.0.0', '1.0.0');
$result = $installer->install($zipUrl, $checksum, $licenseKey);
$result = $installer->installFromZip($path);

// Activate / Deactivate
$registry->activate('automation');
$registry->deactivate('automation');

// Uninstall
$remover = new AddonRemover($registry, $addonsDir);
$remover->uninstall('automation');

// Check updates
$updater = new AddonUpdater($registry, $installer, $addonsDir);
$updates = $updater->checkUpdates($catalog);
$updater->update('automation', $zipUrl, $checksum);
```

### Feature Gating

```php
use Stackborg\WPCoreKits\Addon\FeatureManager;

// Check access
if ($featureManager->can('automation', 'conditional_logic')) {
    // Feature available
}

// Gate with callback
$result = $featureManager->gate('automation', 'conditional_logic',
    fn() => 'pro content',
    'upgrade required'
);

// Get feature map
$map = $featureManager->getFeatureMap('automation');
// ['sequences' => ['tier' => 'free', 'accessible' => true], ...]
```

### License Management

```php
use Stackborg\WPCoreKits\Addon\{LicenseManager, LicenseGuard};

$manager = new LicenseManager('sb_mailpress', $verifyKey);

// Activate (signed API response required)
$result = $manager->activate('automation', $key, $apiResponse);

// Check validity (5-point: decrypt → signature → expiry → site → grace)
$valid = $manager->isValid('automation');

// Refresh (call periodically)
$manager->refreshVerification('automation', $freshApiResponse);
```

### REST Endpoints

| Method | Route | Purpose |
|--------|-------|---------|
| GET | `/addons` | List installed addons |
| POST | `/addons/{slug}/install` | Install addon |
| DELETE | `/addons/{slug}` | Uninstall |
| POST | `/addons/{slug}/activate` | Activate |
| POST | `/addons/{slug}/deactivate` | Deactivate |
| POST | `/addons/{slug}/license` | Activate license |
| DELETE | `/addons/{slug}/license` | Deactivate license |
| POST | `/addons/{slug}/update` | Update addon |

