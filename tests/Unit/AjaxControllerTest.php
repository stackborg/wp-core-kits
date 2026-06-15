<?php

/**
 * Unit tests for Ajax\Controller base class.
 *
 * Tests that the AJAX controller correctly registers actions,
 * wraps handlers with nonce verification and capability checks,
 * and provides consistent response helpers.
 *
 * @package Stackborg\WPCoreKits\Tests\Unit
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Stackborg\WPCoreKits\Ajax\Controller;

class AjaxControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Reset hook registry for each test
        $GLOBALS['wp_hook_registry'] = [];
        $GLOBALS['wp_current_user_can'] = true;
    }

    public function testControllerClassExists(): void
    {
        $this->assertTrue(class_exists(Controller::class));
    }

    public function testControllerIsAbstract(): void
    {
        $reflection = new \ReflectionClass(Controller::class);
        $this->assertTrue($reflection->isAbstract());
    }

    public function testActionsMethodIsAbstract(): void
    {
        $reflection = new \ReflectionClass(Controller::class);
        $method = $reflection->getMethod('actions');
        $this->assertTrue($method->isAbstract());
    }

    public function testRegisterMethodExists(): void
    {
        $this->assertTrue(
            method_exists(Controller::class, 'register'),
            'Controller must expose a register() method'
        );
    }

    public function testRegisterCallsActions(): void
    {
        $controller = $this->createMockController();
        $controller->register();

        // Verify wp_ajax_ hooks were registered
        $this->assertArrayHasKey('wp_ajax_test_admin_action', $GLOBALS['wp_hook_registry']);
    }

    public function testHandleRegistersAdminOnlyAction(): void
    {
        $controller = $this->createMockController();
        $controller->register();

        // Admin action should only have wp_ajax_ hook, not wp_ajax_nopriv_
        $this->assertArrayHasKey('wp_ajax_test_admin_action', $GLOBALS['wp_hook_registry']);
        $this->assertArrayNotHasKey('wp_ajax_nopriv_test_admin_action', $GLOBALS['wp_hook_registry']);
    }

    public function testPublicHandleRegistersBothHooks(): void
    {
        $controller = $this->createMockController();
        $controller->register();

        // Public action should have both hooks
        $this->assertArrayHasKey('wp_ajax_test_public_action', $GLOBALS['wp_hook_registry']);
        $this->assertArrayHasKey('wp_ajax_nopriv_test_public_action', $GLOBALS['wp_hook_registry']);
    }

    public function testSuccessMethodExists(): void
    {
        $reflection = new \ReflectionClass(Controller::class);
        $method = $reflection->getMethod('success');
        $this->assertTrue($method->isProtected());
    }

    public function testErrorMethodExists(): void
    {
        $reflection = new \ReflectionClass(Controller::class);
        $method = $reflection->getMethod('error');
        $this->assertTrue($method->isProtected());
    }

    public function testDefaultCapabilityIsManageOptions(): void
    {
        $reflection = new \ReflectionClass(Controller::class);
        $property = $reflection->getProperty('capability');
        $this->assertSame('manage_options', $property->getDefaultValue());
    }

    public function testDefaultNonceFieldIsNonce(): void
    {
        $reflection = new \ReflectionClass(Controller::class);
        $property = $reflection->getProperty('nonceField');
        $this->assertSame('nonce', $property->getDefaultValue());
    }

    public function testMultipleRegistrationsAccumulate(): void
    {
        $controller = $this->createMockController();
        $controller->register();

        // Should have registered at least 2 actions (admin + public)
        $ajaxHooks = array_filter(
            array_keys($GLOBALS['wp_hook_registry']),
            fn ($key) => str_starts_with($key, 'wp_ajax_')
        );

        $this->assertGreaterThanOrEqual(2, count($ajaxHooks));
    }

    /**
     * Create a mock controller for testing registration.
     */
    private function createMockController(): Controller
    {
        return new class extends Controller {
            protected string $nonceAction = 'test_nonce';

            public function actions(): void
            {
                $this->handle('test_admin_action', 'handleAdmin');
                $this->publicHandle('test_public_action', 'handlePublic');
            }

            public function handleAdmin(): void
            {
                $this->success(['admin' => true]);
            }

            public function handlePublic(): void
            {
                $this->success(['public' => true]);
            }
        };
    }
}
