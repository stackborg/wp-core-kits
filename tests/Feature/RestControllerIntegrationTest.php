<?php

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Tests\Feature;

use PHPUnit\Framework\TestCase;
use Stackborg\WPCoreKits\REST\Response;
use Stackborg\WPCoreKits\REST\Controller;

/**
 * Concrete test controller.
 */
class TestSettingsController extends Controller
{
    protected string $namespace = 'test-plugin/v1';

    public function routes(): void
    {
        $this->get('/settings', 'index');
        $this->post('/settings', 'store');
        $this->delete('/settings/(?P<id>[\\d]+)', 'destroy');
    }

    public function index(): \WP_REST_Response
    {
        return Response::success(['theme' => 'dark']);
    }

    public function store(\WP_REST_Request $req): \WP_REST_Response
    {
        return Response::success($req->get_json_params(), 201);
    }

    public function destroy(\WP_REST_Request $req): \WP_REST_Response
    {
        return Response::success(['deleted' => $req->get_param('id')]);
    }
}

/**
 * Feature test — REST Controller route registration + handler execution.
 */
class RestControllerIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        global $wp_rest_routes;
        $wp_rest_routes = [];
    }

    public function testRegisterCreatesRoutes(): void
    {
        $controller = new TestSettingsController();
        $controller->register();

        global $wp_rest_routes;
        $this->assertCount(3, $wp_rest_routes);

        // Verify namespaces
        foreach ($wp_rest_routes as $route) {
            $this->assertSame('test-plugin/v1', $route['namespace']);
        }

        // Verify route paths
        $paths = array_column($wp_rest_routes, 'route');
        $this->assertContains('/settings', $paths);
        $this->assertContains('/settings/(?P<id>[\\d]+)', $paths);
    }

    public function testIndexHandlerReturnsSuccess(): void
    {
        $controller = new TestSettingsController();
        $response = $controller->index();
        $data = $response->get_data();

        $this->assertTrue($data['success']);
        $this->assertSame('dark', $data['data']['theme']);
        $this->assertSame(200, $response->get_status());
    }

    public function testStoreHandlerReturns201(): void
    {
        $controller = new TestSettingsController();
        $request = new \WP_REST_Request('POST', '/settings');
        $request->set_body(json_encode(['theme' => 'light']));

        $response = $controller->store($request);
        $this->assertSame(201, $response->get_status());
    }

    public function testCheckPermissionDefaultsToCapability(): void
    {
        global $wp_current_user_can;
        $wp_current_user_can = true;

        $controller = new TestSettingsController();
        $this->assertTrue($controller->checkPermission());

        $wp_current_user_can = false;
        $this->assertFalse($controller->checkPermission());
    }
}
