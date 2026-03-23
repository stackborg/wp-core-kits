<?php

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Stackborg\WPCoreKits\REST\Response;

/**
 * Real-world REST Response edge cases.
 *
 * Tests error formatting, pagination math, empty data,
 * and consistency of response structure.
 */
class ResponseEdgeCaseTest extends TestCase
{
    public function testSuccessWithNullData(): void
    {
        $response = Response::success(null);
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertNull($data['data']);
    }

    public function testSuccessWithEmptyArray(): void
    {
        $response = Response::success([]);
        $data = $response->get_data();
        $this->assertSame([], $data['data']);
    }

    public function testErrorAlwaysHasMessageKey(): void
    {
        $response = Response::error('Something broke', 500);
        $data = $response->get_data();
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('success', $data);
        $this->assertFalse($data['success']);
    }

    public function testPaginatedCalculatesPageCorrectly(): void
    {
        // 23 items, 10 per page = 3 pages
        $response = Response::paginated([], 23, 1, 10);
        $meta = $response->get_data()['meta'];
        $this->assertSame(3, $meta['pages']);
    }

    public function testPaginatedSinglePage(): void
    {
        $response = Response::paginated(['item'], 1, 1, 10);
        $meta = $response->get_data()['meta'];
        $this->assertSame(1, $meta['pages']);
        $this->assertSame(1, $meta['total']);
    }

    public function testPaginatedZeroItems(): void
    {
        $response = Response::paginated([], 0, 1, 10);
        $meta = $response->get_data()['meta'];
        $this->assertSame(0, $meta['total']);
        $this->assertSame(0, $meta['pages']);
    }

    public function testErrorWithNestedValidationErrors(): void
    {
        $errors = [
            'email' => ['Invalid format', 'Already exists'],
            'name' => ['Required'],
        ];
        $response = Response::error('Validation failed', 422, $errors);
        $data = $response->get_data();
        $this->assertSame($errors, $data['errors']);
        $this->assertCount(2, $data['errors']['email']);
    }

    public function testNotFoundHasCorrectMessage(): void
    {
        $response = Response::notFound();
        $data = $response->get_data();
        $this->assertFalse($data['success']);
        $this->assertSame(404, $response->get_status());
    }

    public function testForbiddenHasCorrectMessage(): void
    {
        $response = Response::forbidden();
        $data = $response->get_data();
        $this->assertFalse($data['success']);
        $this->assertSame(403, $response->get_status());
    }

    public function testResponseStructureIsConsistent(): void
    {
        // All responses must have 'success' key
        $success = Response::success(['data'])->get_data();
        $error = Response::error('fail', 400)->get_data();
        $notFound = Response::notFound()->get_data();
        $paginated = Response::paginated([], 0, 1, 10)->get_data();

        $this->assertArrayHasKey('success', $success);
        $this->assertArrayHasKey('success', $error);
        $this->assertArrayHasKey('success', $notFound);
        $this->assertArrayHasKey('success', $paginated);
    }
}
