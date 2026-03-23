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

    /** @var array<int, array{methods: string, path: string, handler: string}> Collected route definitions before registration. */
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
        // Collect route definitions
        $this->routes();

        // Register each route with WordPress
        foreach ($this->routeDefinitions as $route) {
            register_rest_route($this->namespace, $route['path'], [
                'methods'             => $route['methods'],
                'callback'            => [$this, $route['handler']],
                'permission_callback' => [$this, 'checkPermission'],
            ]);
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

    protected function get(string $path, string $handler): void
    {
        $this->addRoute(WP_REST_Server::READABLE, $path, $handler);
    }

    protected function post(string $path, string $handler): void
    {
        $this->addRoute(WP_REST_Server::CREATABLE, $path, $handler);
    }

    protected function put(string $path, string $handler): void
    {
        $this->addRoute(WP_REST_Server::EDITABLE, $path, $handler);
    }

    protected function delete(string $path, string $handler): void
    {
        $this->addRoute(WP_REST_Server::DELETABLE, $path, $handler);
    }

    private function addRoute(string $methods, string $path, string $handler): void
    {
        $this->routeDefinitions[] = compact('methods', 'path', 'handler');
    }
}
