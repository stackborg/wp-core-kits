<?php

/**
 * REST Response helpers.
 *
 * @package Stackborg\WPCoreKits
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\REST;

use WP_REST_Response;

/**
 * REST Response helpers.
 *
 * Provides consistent, typed response formatting
 * for all plugin REST endpoints.
 */
class Response
{
    /**
     * Success response with data.
     *
     * @param mixed $data    Response data.
     * @param int   $status  HTTP status code.
     */
    public static function success(mixed $data = null, int $status = 200): WP_REST_Response
    {
        return new WP_REST_Response([
            'success' => true,
            'data'    => $data,
        ], $status);
    }

    /**
     * Error response with message.
     *
     * @param string $message Error message.
     * @param int    $status  HTTP status code.
     * @param mixed  $errors  Additional error details.
     */
    public static function error(string $message, int $status = 400, mixed $errors = null): WP_REST_Response
    {
        $body = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $body['errors'] = $errors;
        }

        return new WP_REST_Response($body, $status);
    }

    /**
     * Paginated list response.
     *
     * @param array<mixed> $items Items for current page.
     * @param int          $total Total number of items.
     * @param int          $page  Current page number.
     * @param int          $perPage Items per page.
     */
    public static function paginated(array $items, int $total, int $page = 1, int $perPage = 20): WP_REST_Response
    {
        return new WP_REST_Response([
            'success' => true,
            'data'    => $items,
            'meta'    => [
                'total'    => $total,
                'page'     => $page,
                'per_page' => $perPage,
                'pages'    => (int) ceil($total / max($perPage, 1)),
            ],
        ], 200);
    }

    /**
     * 404 Not Found response.
     */
    public static function notFound(string $message = 'Resource not found'): WP_REST_Response
    {
        return self::error($message, 404);
    }

    /**
     * 403 Forbidden response.
     */
    public static function forbidden(string $message = 'Access denied'): WP_REST_Response
    {
        return self::error($message, 403);
    }
}
