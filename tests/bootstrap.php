<?php

/**
 * PHPUnit bootstrap for wp-core-kits tests.
 *
 * Provides WordPress function stubs so unit tests can run
 * without a full WordPress installation. These mocks replicate
 * enough WP behavior for meaningful unit testing.
 *
 * @package Stackborg\WPCoreKits\Tests
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

// ─── Constants ──────────────────────────────────────────

if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}
if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}

// ─── Options ────────────────────────────────────────────

$GLOBALS['wp_options'] = [];
$GLOBALS['wp_options_read_count'] = 0;

if (!function_exists('get_option')) {
    function get_option(string $option, $default = false)
    {
        $GLOBALS['wp_options_read_count']++;
        return $GLOBALS['wp_options'][$option] ?? $default;
    }
}

if (!function_exists('update_option')) {
    function update_option(string $option, $value, $autoload = null): bool
    {
        $GLOBALS['wp_options'][$option] = $value;
        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option(string $option): bool
    {
        unset($GLOBALS['wp_options'][$option]);
        return true;
    }
}

// ─── Transients ─────────────────────────────────────────

$GLOBALS['wp_transients'] = [];

if (!function_exists('get_transient')) {
    function get_transient(string $key)
    {
        return $GLOBALS['wp_transients'][$key] ?? false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient(string $key, $value, int $expiration = 0): bool
    {
        $GLOBALS['wp_transients'][$key] = $value;
        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient(string $key): bool
    {
        unset($GLOBALS['wp_transients'][$key]);
        return true;
    }
}

// ─── Hooks (functional mocks) ───────────────────────────

$GLOBALS['wp_hook_registry'] = [];

if (!function_exists('add_action')) {
    function add_action(string $hook, $callback, int $priority = 10, int $args = 1): bool
    {
        $GLOBALS['wp_hook_registry'][$hook][$priority][] = [
            'callback' => $callback,
            'args' => $args,
        ];
        return true;
    }
}

if (!function_exists('add_filter')) {
    function add_filter(string $hook, $callback, int $priority = 10, int $args = 1): bool
    {
        return add_action($hook, $callback, $priority, $args);
    }
}

if (!function_exists('do_action')) {
    function do_action(string $hook, ...$args): void
    {
        if (!isset($GLOBALS['wp_hook_registry'][$hook])) {
            return;
        }
        $priorities = $GLOBALS['wp_hook_registry'][$hook];
        ksort($priorities);
        foreach ($priorities as $callbacks) {
            foreach ($callbacks as $entry) {
                call_user_func_array($entry['callback'], array_slice($args, 0, $entry['args']));
            }
        }
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters(string $hook, $value, ...$args)
    {
        if (!isset($GLOBALS['wp_hook_registry'][$hook])) {
            return $value;
        }
        $priorities = $GLOBALS['wp_hook_registry'][$hook];
        ksort($priorities);
        foreach ($priorities as $callbacks) {
            foreach ($callbacks as $entry) {
                $value = call_user_func_array($entry['callback'], array_merge([$value], array_slice($args, 0, max(0, $entry['args'] - 1))));
            }
        }
        return $value;
    }
}

if (!function_exists('remove_action')) {
    function remove_action(string $hook, $callback, int $priority = 10): bool
    {
        if (!isset($GLOBALS['wp_hook_registry'][$hook][$priority])) {
            return false;
        }
        foreach ($GLOBALS['wp_hook_registry'][$hook][$priority] as $i => $entry) {
            if ($entry['callback'] === $callback) {
                unset($GLOBALS['wp_hook_registry'][$hook][$priority][$i]);
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('remove_filter')) {
    function remove_filter(string $hook, $callback, int $priority = 10): bool
    {
        return remove_action($hook, $callback, $priority);
    }
}

// ─── Sanitization / Escaping ────────────────────────────

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $str): string
    {
        return trim(strip_tags($str));
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email(string $email): string
    {
        return filter_var($email, FILTER_SANITIZE_EMAIL) ?: '';
    }
}

if (!function_exists('esc_html')) {
    function esc_html(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    function esc_url(string $url): string
    {
        return filter_var($url, FILTER_SANITIZE_URL) ?: '';
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw(string $url): string
    {
        return filter_var($url, FILTER_SANITIZE_URL) ?: '';
    }
}

if (!function_exists('wp_kses_post')) {
    function wp_kses_post(string $data): string
    {
        // Simple mock — strip script/style, keep safe tags
        return preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $data) ?? $data;
    }
}

if (!function_exists('absint')) {
    function absint($val): int
    {
        return abs((int) $val);
    }
}

// ─── Nonce ──────────────────────────────────────────────

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce(string $action = ''): string
    {
        return 'nonce_' . md5($action);
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce(string $nonce, string $action = ''): int|false
    {
        return ($nonce === 'nonce_' . md5($action)) ? 1 : false;
    }
}

// ─── User ───────────────────────────────────────────────

if (!function_exists('current_user_can')) {
    function current_user_can(string $capability): bool
    {
        return $GLOBALS['wp_current_user_can'] ?? false;
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id(): int
    {
        return $GLOBALS['wp_current_user_id'] ?? 0;
    }
}

// ─── Assets ─────────────────────────────────────────────

$GLOBALS['wp_enqueued_scripts'] = [];
$GLOBALS['wp_enqueued_styles'] = [];
$GLOBALS['wp_localized'] = [];

if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script(string $handle, string $src = '', array $deps = [], $ver = false, $args = false): void
    {
        $footer = is_bool($args) ? $args : false;
        $version = is_string($ver) ? $ver : '1.0.0';
        $GLOBALS['wp_enqueued_scripts'][$handle] = [
            'src' => $src,
            'deps' => $deps,
            'version' => $version,
            'footer' => $footer,
        ];
    }
}

if (!function_exists('wp_enqueue_style')) {
    function wp_enqueue_style(string $handle, string $src = '', array $deps = [], $ver = false, string $media = 'all'): void
    {
        $version = is_string($ver) ? $ver : '1.0.0';
        $GLOBALS['wp_enqueued_styles'][$handle] = [
            'src' => $src,
            'deps' => $deps,
            'version' => $version,
        ];
    }
}

if (!function_exists('wp_localize_script')) {
    function wp_localize_script(string $handle, string $objectName, array $data): bool
    {
        $GLOBALS['wp_localized'][$handle] = ['name' => $objectName, 'data' => $data];
        return true;
    }
}

// ─── REST API ───────────────────────────────────────────

$GLOBALS['wp_rest_routes'] = [];

if (!function_exists('register_rest_route')) {
    function register_rest_route(string $namespace, string $route, array $args = []): bool
    {
        $GLOBALS['wp_rest_routes'][] = compact('namespace', 'route', 'args');
        return true;
    }
}

if (!function_exists('rest_url')) {
    function rest_url(string $path = ''): string
    {
        return 'https://test.local/wp-json/' . ltrim($path, '/');
    }
}

// ─── i18n stubs ─────────────────────────────────────────

if (!function_exists('__')) {
    function __(string $text, string $domain = ''): string
    {
        return $text;
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__(string $text, string $domain = ''): string
    {
        return esc_html($text);
    }
}

// ─── REST Classes ───────────────────────────────────────

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request
    {
        private array $params = [];
        private ?array $jsonBody = null;
        private string $method;

        public function __construct(string $method = 'GET', string $route = '', array $params = [])
        {
            $this->method = $method;
            $this->params = $params;
        }

        public function get_method(): string { return $this->method; }
        public function set_param(string $key, $value): void { $this->params[$key] = $value; }
        public function get_param(string $key) { return $this->params[$key] ?? null; }
        public function get_params(): array { return $this->params; }
        public function set_body(string $body): void { $this->jsonBody = json_decode($body, true); }
        public function get_json_params(): ?array { return $this->jsonBody; }
    }
}

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response
    {
        public $data;
        public int $status;

        public function __construct($data = null, int $status = 200)
        {
            $this->data = $data;
            $this->status = $status;
        }

        public function get_data() { return $this->data; }
        public function get_status(): int { return $this->status; }
    }
}

if (!class_exists('WP_REST_Server')) {
    class WP_REST_Server
    {
        public const READABLE = 'GET';
        public const CREATABLE = 'POST';
        public const EDITABLE = 'POST, PUT, PATCH';
        public const DELETABLE = 'DELETE';
    }
}

// ─── $wpdb ──────────────────────────────────────────────

if (!isset($GLOBALS['wpdb'])) {
    $GLOBALS['wpdb'] = new class {
        public string $prefix = 'wp_';
        public int $insert_id = 1;

        public function prepare(string $query, ...$args): string
        {
            return empty($args) ? $query : vsprintf(str_replace(['%s', '%d'], ["'%s'", '%d'], $query), $args);
        }

        public function get_var($query) { return '1'; }
        public function get_results(string $query, string $type = 'OBJECT'): array { return []; }
        public function get_row($query, $output = 'OBJECT') { return null; }

        public function insert(string $table, array $data, $format = null): int|false
        {
            $this->insert_id++;
            return 1;
        }

        public function update(string $table, array $data, array $where, $format = null, $whereFormat = null): int|false { return 1; }
        public function delete(string $table, array $where, $format = null): int|false { return 1; }
        public function query(string $query): int|bool { return true; }
    };
}
