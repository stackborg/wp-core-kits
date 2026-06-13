<?php

/**
 * Base REST Controller for plugin endpoints.
 *
 * @package Stackborg\WPCoreKits
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\REST;

use WP_REST_Server;

/**
 * Base REST Controller for plugin endpoints.
 *
 * Provides a clean, declarative way to register REST routes.
 * Subclasses define routes() and handler methods — the base
 * class handles registration, permission checks, and response formatting.
 *
 * Usage:
 *   class SettingsController extends Controller {
 *       protected string $namespace = 'sb-mailpress/v1';
 *       public function routes(): void {
 *           $this->get('/settings', 'index');
 *           $this->post('/settings', 'update');
 *       }
 *   }
 */
abstract class Controller
{
    /** REST namespace (e.g. 'sb-mailpress/v1'). Must be set by subclass. */
    protected string $namespace = '';

    /** Required capability to access these endpoints. */
    protected string $capability = 'manage_options';

    /**
     * Collected route definitions before registration.
     *
     * @var array<int, array{
     *     methods: string,
     *     path: string,
     *     handler: string,
     *     args: array<string, mixed>,
     *     permission: string|callable|null
     * }>
     */
    private array $routeDefinitions = [];

    /**
     * Subclasses define their routes here using get/post/put/delete helpers.
     */
    abstract public function routes(): void;

    /**
     * Register all routes with WordPress REST API.
     * Call this during rest_api_init hook.
     */
    public function register(): void
    {
        if ($this->namespace === '') {
            throw new \LogicException('REST controller namespace must be defined by subclass.');
        }

        // Collect route definitions
        $this->routes();

        // Register each route with WordPress
        foreach ($this->routeDefinitions as $route) {
            $routeConfig = [
                'methods'             => $route['methods'],
                'callback'            => [$this, $route['handler']],
                'permission_callback' => $this->resolvePermission($route['permission']),
            ];

            // Add args schema if provided — enables validate/sanitize on input
            if (!empty($route['args'])) {
                $routeConfig['args'] = $route['args'];
            }

            register_rest_route($this->namespace, $route['path'], $routeConfig);
        }
    }

    /**
     * Default permission check — override in subclass for custom logic.
     */
    public function checkPermission(): bool
    {
        return current_user_can($this->capability);
    }

    // ── Route Registration Helpers ────────────────────────

    /**
     * @param array<string, mixed> $args
     */
    protected function get(string $path, string $handler, array $args = []): void
    {
        $this->addRoute(WP_REST_Server::READABLE, $path, $handler, $args);
    }

    /**
     * @param array<string, mixed> $args
     */
    protected function post(string $path, string $handler, array $args = []): void
    {
        $this->addRoute(WP_REST_Server::CREATABLE, $path, $handler, $args);
    }

    /**
     * @param array<string, mixed> $args
     */
    protected function put(string $path, string $handler, array $args = []): void
    {
        $this->addRoute(WP_REST_Server::EDITABLE, $path, $handler, $args);
    }

    /**
     * @param array<string, mixed> $args
     */
    protected function delete(string $path, string $handler, array $args = []): void
    {
        $this->addRoute(WP_REST_Server::DELETABLE, $path, $handler, $args);
    }

    // ── Public Route Helpers (no auth required) ────────────

    /**
     * Register a public GET route — accessible without authentication.
     * Use with caution and consider adding rate limiting.
     *
     * @param array<string, mixed> $args
     */
    protected function publicGet(string $path, string $handler, array $args = []): void
    {
        $this->addRoute(WP_REST_Server::READABLE, $path, $handler, $args, 'public');
    }

    /**
     * Register a public POST route — accessible without authentication.
     * Use with caution and consider adding rate limiting.
     *
     * @param array<string, mixed> $args
     */
    protected function publicPost(string $path, string $handler, array $args = []): void
    {
        $this->addRoute(WP_REST_Server::CREATABLE, $path, $handler, $args, 'public');
    }

    // ── Internal Helpers ──────────────────────────────────

    /**
     * @param array<string, mixed> $args
     */
    private function addRoute(
        string $methods,
        string $path,
        string $handler,
        array $args = [],
        ?string $permission = null
    ): void {
        $this->routeDefinitions[] = compact(
            'methods',
            'path',
            'handler',
            'args',
            'permission'
        );
    }

    /**
     * Resolve permission callback from the route definition.
     *
     * @param string|callable|null $permission  'public' for __return_true, null for default checkPermission.
     * @return callable
     */
    private function resolvePermission(string|callable|null $permission): callable
    {
        if ($permission === 'public') {
            // Wrap WP function reference in closure for PHPStan type safety
            return static function (): bool {
                return true;
            };
        }

        if (is_callable($permission)) {
            return $permission;
        }

        return [$this, 'checkPermission'];
    }
}
