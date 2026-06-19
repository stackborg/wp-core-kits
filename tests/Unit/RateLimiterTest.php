<?php

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Stackborg\WPCoreKits\REST\RateLimiter;

// WP_Error mock — not provided by bootstrap but needed by RateLimiter
if (!class_exists('WP_Error')) {
    class_alias(WP_Error_Mock::class, 'WP_Error');
}

/**
 * Minimal WP_Error mock for RateLimiter tests.
 */
class WP_Error_Mock
{
    private string $code;
    private string $message;
    private array $data;

    public function __construct(string $code = '', string $message = '', array $data = [])
    {
        $this->code    = $code;
        $this->message = $message;
        $this->data    = $data;
    }

    public function get_error_code(): string
    {
        return $this->code;
    }

    public function get_error_message(): string
    {
        return $this->message;
    }

    public function get_error_data(): array
    {
        return $this->data;
    }
}

class RateLimiterTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset transient store and $_SERVER between tests
        $GLOBALS['wp_transients'] = [];
        unset($_SERVER['REMOTE_ADDR']);
    }

    /** @test */
    public function itAllowsFirstRequestWithinLimit(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';

        $result = RateLimiter::check('test_endpoint', 10, 60);

        $this->assertTrue($result);
    }

    /** @test */
    public function itAllowsMultipleRequestsWithinLimit(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';

        // Send 5 requests with a limit of 10
        for ($i = 0; $i < 5; $i++) {
            $result = RateLimiter::check('multi_endpoint', 10, 60);
            $this->assertTrue($result, "Request #{$i} should be allowed");
        }
    }

    /** @test */
    public function itBlocksRequestsExceedingLimit(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.2';

        // Exhaust the limit (3 requests allowed)
        for ($i = 0; $i < 3; $i++) {
            $result = RateLimiter::check('limited_endpoint', 3, 60);
            $this->assertTrue($result);
        }

        // 4th request should be blocked
        $result = RateLimiter::check('limited_endpoint', 3, 60);
        $this->assertInstanceOf(\WP_Error::class, $result);
    }

    /** @test */
    public function itReturnsWpErrorWithCorrectCodeWhenBlocked(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.3';

        // Exhaust limit of 1
        RateLimiter::check('single_endpoint', 1, 60);
        $result = RateLimiter::check('single_endpoint', 1, 60);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('rate_limit_exceeded', $result->get_error_code());
    }

    /** @test */
    public function itReturnsErrorWith429StatusData(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.4';

        RateLimiter::check('status_endpoint', 1, 60);
        $result = RateLimiter::check('status_endpoint', 1, 60);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $data = $result->get_error_data();
        $this->assertSame(429, $data['status']);
    }

    /** @test */
    public function itTracksSeparateCountersPerEndpointKey(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.5';

        // Exhaust limit on endpoint_a
        RateLimiter::check('endpoint_a', 1, 60);
        $resultA = RateLimiter::check('endpoint_a', 1, 60);

        // endpoint_b should still be allowed
        $resultB = RateLimiter::check('endpoint_b', 1, 60);

        $this->assertInstanceOf(\WP_Error::class, $resultA);
        $this->assertTrue($resultB);
    }

    /** @test */
    public function itTracksSeparateCountersPerIp(): void
    {
        // IP #1 exhausts its limit
        $_SERVER['REMOTE_ADDR'] = '10.0.0.10';
        RateLimiter::check('shared_endpoint', 1, 60);
        $resultIp1 = RateLimiter::check('shared_endpoint', 1, 60);

        // IP #2 should still be allowed
        $_SERVER['REMOTE_ADDR'] = '10.0.0.11';
        $resultIp2 = RateLimiter::check('shared_endpoint', 1, 60);

        $this->assertInstanceOf(\WP_Error::class, $resultIp1);
        $this->assertTrue($resultIp2);
    }

    /** @test */
    public function itResetsCounterForSpecificKeyAndIp(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.20';

        // Exhaust limit
        RateLimiter::check('reset_endpoint', 1, 60);
        $blocked = RateLimiter::check('reset_endpoint', 1, 60);
        $this->assertInstanceOf(\WP_Error::class, $blocked);

        // Reset and try again
        RateLimiter::reset('reset_endpoint', '10.0.0.20');
        $result = RateLimiter::check('reset_endpoint', 1, 60);
        $this->assertTrue($result);
    }

    /** @test */
    public function itUsesDefaultIpWhenRemoteAddrMissing(): void
    {
        // No REMOTE_ADDR set → should fallback to 127.0.0.1
        unset($_SERVER['REMOTE_ADDR']);

        $result = RateLimiter::check('no_ip_endpoint', 10, 60);
        $this->assertTrue($result);

        // Verify transient was created with a key derived from 127.0.0.1
        $expectedKey = 'sb_rl_' . md5('no_ip_endpoint|127.0.0.1');
        $this->assertSame(1, $GLOBALS['wp_transients'][$expectedKey]);
    }

    /** @test */
    public function itIncrementsCounterOnEachRequest(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.30';

        RateLimiter::check('counter_endpoint', 10, 60);
        RateLimiter::check('counter_endpoint', 10, 60);
        RateLimiter::check('counter_endpoint', 10, 60);

        $key = 'sb_rl_' . md5('counter_endpoint|10.0.0.30');
        $this->assertSame(3, $GLOBALS['wp_transients'][$key]);
    }

    /** @test */
    public function itAllowsExactlyMaxRequestsBeforeBlocking(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.40';
        $max = 5;

        for ($i = 0; $i < $max; $i++) {
            $result = RateLimiter::check('exact_endpoint', $max, 60);
            $this->assertTrue($result, "Request #{$i} should be allowed");
        }

        // The (max+1)th request should be blocked
        $result = RateLimiter::check('exact_endpoint', $max, 60);
        $this->assertInstanceOf(\WP_Error::class, $result);
    }
}
