<?php

/**
 * AddonApiClient - Stackborg API communication layer.
 *
 * @package Stackborg\WPCoreKits
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Addon;

/**
 * AddonApiClient — Stackborg API communication layer.
 *
 * Handles all communication with the Stackborg API server
 * for addon catalog, downloads, and license verification.
 *
 * In development mode, this can be pointed at a mock server.
 */
class AddonApiClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $pluginSlug,
    ) {
    }

    /**
     * Fetch available addons catalog from API.
     *
     * @return array<int, array<string, mixed>> List of addon metadata
     */
    public function getCatalog(): array
    {
        $response = $this->request('GET', "/v1/addons?plugin={$this->pluginSlug}");
        if ($response === null) {
            return [];
        }

        return $response['addons'] ?? [];
    }

    /**
     * Get download URL for an addon.
     */
    public function getDownloadUrl(string $slug, ?string $licenseKey = null): ?string
    {
        $params = ['plugin' => $this->pluginSlug];
        if ($licenseKey !== null) {
            $params['license'] = $licenseKey;
        }

        $query = http_build_query($params);
        $response = $this->request('GET', "/v1/addons/{$slug}/download?{$query}");

        return $response['download_url'] ?? null;
    }

    /**
     * Verify a license key with the API server.
     *
     * @return array{payload: array<string, mixed>, signature: string}|null Signed response or null on failure
     */
    public function verifyLicense(string $slug, string $licenseKey, string $siteUrl): ?array
    {
        $response = $this->request('POST', '/v1/license/verify', [
            'slug'    => $slug,
            'key'     => $licenseKey,
            'site'    => $siteUrl,
            'plugin'  => $this->pluginSlug,
        ]);

        if ($response === null || !isset($response['payload'], $response['signature'])) {
            return null;
        }

        return [
            'payload'   => $response['payload'],
            'signature' => $response['signature'],
        ];
    }

    /**
     * Get checksum for a specific addon version.
     */
    public function getChecksum(string $slug, string $version): ?string
    {
        $response = $this->request('GET', "/v1/addons/{$slug}/checksum?version={$version}");
        return $response['checksum'] ?? null;
    }

    /**
     * Make an HTTP request to the API.
     *
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>|null Decoded JSON response or null on failure
     */
    private function request(string $method, string $path, ?array $body = null): ?array
    {
        $url = rtrim($this->baseUrl, '/') . $path;

        // Use WordPress HTTP API if available, otherwise fall back to curl
        if (function_exists('wp_remote_request')) {
            return $this->wpRequest($method, $url, $body);
        }

        return $this->curlRequest($method, $url, $body);
    }

    /**
     * WordPress HTTP API request.
     *
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>|null
     */
    private function wpRequest(string $method, string $url, ?array $body): ?array
    {
        $args = [
            'method'  => $method,
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
        ];

        if ($body !== null) {
            $args['body'] = json_encode($body);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return null;
        }

        $responseBody = wp_remote_retrieve_body($response);
        $decoded = json_decode($responseBody, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * cURL fallback request (for non-WordPress environments).
     *
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>|null
     */
    private function curlRequest(string $method, string $url, ?array $body): ?array
    {
        $opts = [
            'http' => [
                'method'  => $method,
                'header'  => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'timeout' => 30,
            ],
        ];

        if ($body !== null) {
            $opts['http']['content'] = json_encode($body);
        }

        $context = stream_context_create($opts);
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            return null;
        }

        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : null;
    }
}
