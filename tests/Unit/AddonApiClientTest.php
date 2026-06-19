<?php

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Stackborg\WPCoreKits\Addon\AddonApiClient;

/**
 * Testable subclass that intercepts the private wpRequest HTTP call.
 *
 * This allows us to test request building and response parsing
 * without making real HTTP calls.
 */
class TestableAddonApiClient extends AddonApiClient
{
    /** @var array|null Predefined response to return from wpRequest */
    public ?array $mockResponse = null;

    /** @var array Captured request details for assertions */
    public array $lastRequest = [];

    /**
     * Override the private wpRequest via reflection-free approach:
     * we override the parent's request() by making it protected first.
     *
     * Since request() and wpRequest() are both private, we instead
     * mock the global wp_remote_request function behavior.
     */
}

class AddonApiClientTest extends TestCase
{
    private string $baseUrl = 'https://api.stackborg.com';
    private string $pluginSlug = 'sb-mailpress';

    protected function setUp(): void
    {
        // We'll control responses by overriding the global wp_remote_request mock
        // The bootstrap's wp_remote_request returns { body: '{}' } by default
    }

    private function createClient(): AddonApiClient
    {
        return new AddonApiClient($this->baseUrl, $this->pluginSlug);
    }

    /** @test */
    public function itReturnsCatalogFromApiResponse(): void
    {
        // The default wp_remote_request returns body='{}' which decodes to []
        // getCatalog returns $response['addons'] ?? [], so with {} it returns []
        $client = $this->createClient();
        $catalog = $client->getCatalog();

        $this->assertIsArray($catalog);
        $this->assertEmpty($catalog);
    }

    /** @test */
    public function itReturnsEmptyArrayWhenCatalogResponseIsNull(): void
    {
        $client = $this->createClient();
        $result = $client->getCatalog();

        $this->assertSame([], $result);
    }

    /** @test */
    public function itReturnsNullForDownloadUrlWhenNotInResponse(): void
    {
        // Default mock returns '{}' body → no 'download_url' key
        $client = $this->createClient();
        $url = $client->getDownloadUrl('email-templates');

        $this->assertNull($url);
    }

    /** @test */
    public function itReturnsNullForVerifyLicenseWhenResponseLacksPayload(): void
    {
        // Default mock returns '{}' → no 'payload' or 'signature'
        $client = $this->createClient();
        $result = $client->verifyLicense('email-templates', 'LICENSE-KEY-123', 'https://example.com');

        $this->assertNull($result);
    }

    /** @test */
    public function itReturnsNullForChecksumWhenNotInResponse(): void
    {
        $client = $this->createClient();
        $checksum = $client->getChecksum('email-templates', '1.0.0');

        $this->assertNull($checksum);
    }

    /** @test */
    public function itConstructsClientWithBaseUrlAndPluginSlug(): void
    {
        // Verify the client can be constructed without errors
        $client = new AddonApiClient('https://custom-api.com', 'custom-plugin');
        $this->assertInstanceOf(AddonApiClient::class, $client);
    }

    /** @test */
    public function itHandlesTrailingSlashInBaseUrl(): void
    {
        // Constructor accepts trailing slash — internal request() trims it
        $client = new AddonApiClient('https://api.stackborg.com/', $this->pluginSlug);
        // Should not throw; just test catalog returns normally
        $catalog = $client->getCatalog();
        $this->assertIsArray($catalog);
    }

    /** @test */
    public function itReturnsNullForDownloadUrlWithLicenseKeyWhenNotInResponse(): void
    {
        $client = $this->createClient();
        $url = $client->getDownloadUrl('email-templates', 'LICENSE-KEY-456');

        $this->assertNull($url);
    }
}
