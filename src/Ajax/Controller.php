<?php

/**
 * Base AJAX Controller for plugin endpoints.
 *
 * Mirrors the REST Controller pattern for consistency.
 * Provides centralized nonce verification, capability checks,
 * and JSON response formatting for WordPress AJAX handlers.
 *
 * @package Stackborg\WPCoreKits
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Ajax;

use Stackborg\WPCoreKits\WordPress\Hooks;

/**
 * Base AJAX Controller for plugin endpoints.
 *
 * Provides a clean, declarative way to register AJAX handlers.
 * Subclasses define actions() and handler methods — the base
 * class handles registration, nonce verification, capability
 * checks, and response formatting.
 *
 * Usage:
 *   class CartAjax extends Controller {
 *       protected string $nonceAction = 'sb_woo_cart';
 *
 *       public function actions(): void {
 *           $this->handle('sb_woo_add_to_cart', 'addToCart');
 *           $this->publicHandle('sb_woo_get_cart', 'getCart');
 *       }
 *
 *       public function addToCart(): void {
 *           $productId = absint($_POST['product_id'] ?? 0);
 *           // ... business logic ...
 *           $this->success(['added' => true]);
 *       }
 *   }
 */
abstract class Controller
{
    /** Required capability for admin AJAX actions. */
    protected string $capability = 'manage_options';

    /** Nonce action name for verification. */
    protected string $nonceAction = '';

    /** Text domain for i18n — subclasses should override with their plugin slug. */
    protected string $textDomain = 'default';

    /** POST/GET field name containing the nonce value. */
    protected string $nonceField = 'nonce';

    /**
     * Collected action definitions before registration.
     *
     * @var array<int, array{
     *     action: string,
     *     handler: string,
     *     public: bool
     * }>
     */
    private array $actionDefinitions = [];

    /**
     * Subclasses define their AJAX actions here using handle/publicHandle helpers.
     */
    abstract public function actions(): void;

    /**
     * Register all AJAX actions with WordPress.
     * Call this during plugin bootstrap (e.g., in a ServiceProvider).
     */
    public function register(): void
    {
        // Collect action definitions from subclass
        $this->actions();

        // Register each action with WordPress
        foreach ($this->actionDefinitions as $definition) {
            $action  = $definition['action'];
            $handler = $definition['handler'];
            $public  = $definition['public'];

            // Create a wrapper that handles nonce + capability before calling the handler
            $callback = $this->wrapHandler($handler, $public);

            // Register for logged-in users
            Hooks::action("wp_ajax_{$action}", $callback);

            // Also register for non-logged-in users if public
            if ($public) {
                Hooks::action("wp_ajax_nopriv_{$action}", $callback);
            }
        }
    }

    // ── Action Registration Helpers ─────────────────────────

    /**
     * Register an admin-only AJAX action.
     *
     * Auto-verifies nonce and checks capability before calling handler.
     * Only logged-in users with the required capability can access.
     */
    protected function handle(string $action, string $handler): void
    {
        $this->actionDefinitions[] = [
            'action'  => $action,
            'handler' => $handler,
            'public'  => false,
        ];
    }

    /**
     * Register a public AJAX action (accessible to non-logged-in users).
     *
     * Registers both wp_ajax_ and wp_ajax_nopriv_ hooks.
     * Auto-verifies nonce but does NOT check capability.
     * Use with caution — consider rate limiting for public endpoints.
     */
    protected function publicHandle(string $action, string $handler): void
    {
        $this->actionDefinitions[] = [
            'action'  => $action,
            'handler' => $handler,
            'public'  => true,
        ];
    }

    // ── Response Helpers ────────────────────────────────────

    /**
     * Send a success JSON response and terminate.
     *
     * @param mixed $data Response data
     */
    protected function success(mixed $data = null): void
    {
        wp_send_json_success($data);
    }

    /**
     * Send an error JSON response and terminate.
     *
     * @param string $message Error message
     * @param int    $status  HTTP status code
     */
    protected function error(string $message, int $status = 400): void
    {
        wp_send_json_error($message, $status);
    }

    // ── Internal Helpers ────────────────────────────────────

    /**
     * Wrap a handler method with nonce verification and capability check.
     *
     * @return callable The wrapped handler
     */
    private function wrapHandler(string $handler, bool $public): callable
    {
        return function () use ($handler, $public): void {
            // Step 1: Verify nonce (always required)
            if ($this->nonceAction !== '') {
                check_ajax_referer($this->nonceAction, $this->nonceField);
            }

            // Step 2: Check capability (skip for public actions)
            if (!$public && !current_user_can($this->capability)) {
                $this->error(
                    esc_html__('You do not have permission to perform this action.', $this->textDomain),
                    403
                );
            }

            // Step 3: Call the actual handler method
            $this->{$handler}();
        };
    }
}
