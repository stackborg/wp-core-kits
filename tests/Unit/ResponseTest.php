<?php

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Stackborg\WPCoreKits\REST\Response;

class ResponseTest extends TestCase
{
    public function testSuccessReturnsCorrectStructure(): void
    {
        $response = Response::success(['key' => 'value']);
        $data = $response->get_data();

        $this->assertTrue($data['success']);
        $this->assertSame(['key' => 'value'], $data['data']);
        $this->assertSame(200, $response->get_status());
    }

    public function testSuccessWithCustomStatus(): void
    {
        $response = Response::success(null, 201);
        $this->assertSame(201, $response->get_status());
    }

    public function testErrorReturnsCorrectStructure(): void
    {
        $response = Response::error('Something failed', 422);
        $data = $response->get_data();

        $this->assertFalse($data['success']);
        $this->assertSame('Something failed', $data['message']);
        $this->assertSame(422, $response->get_status());
    }

    public function testErrorWithValidationErrors(): void
    {
        $errors = ['email' => 'Required'];
        $response = Response::error('Validation failed', 422, $errors);
        $data = $response->get_data();

        $this->assertSame($errors, $data['errors']);
    }

    public function testPaginatedReturnsMetadata(): void
    {
        $items = [['id' => 1], ['id' => 2]];
        $response = Response::paginated($items, 50, 2, 10);
        $data = $response->get_data();

        $this->assertTrue($data['success']);
        $this->assertCount(2, $data['data']);
        $this->assertSame(50, $data['meta']['total']);
        $this->assertSame(2, $data['meta']['page']);
        $this->assertSame(10, $data['meta']['per_page']);
        $this->assertSame(5, $data['meta']['pages']);
    }

    public function testNotFoundReturns404(): void
    {
        $response = Response::notFound();
        $this->assertSame(404, $response->get_status());
    }

    public function testForbiddenReturns403(): void
    {
        $response = Response::forbidden();
        $this->assertSame(403, $response->get_status());
    }
}
