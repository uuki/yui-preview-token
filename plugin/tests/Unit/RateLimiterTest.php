<?php

declare(strict_types=1);

namespace YUIPT\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use YUIPT\WordPress\RateLimiter;

class RateLimiterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_first_request_is_allowed(): void
    {
        Functions\expect('get_transient')->once()->andReturn(false);
        Functions\expect('set_transient')->once()->andReturn(true);
        Functions\expect('time')->once()->andReturn(1000);

        $this->assertTrue((new RateLimiter(30, 60))->is_allowed('127.0.0.1'));
    }

    public function test_request_within_limit_is_allowed(): void
    {
        Functions\expect('get_transient')
            ->once()
            ->andReturn(['count' => 5, 'window_start' => 1000]);
        Functions\expect('set_transient')->once()->andReturn(true);
        Functions\expect('time')->once()->andReturn(1010);

        $this->assertTrue((new RateLimiter(30, 60))->is_allowed('127.0.0.1'));
    }

    public function test_request_over_limit_is_rejected(): void
    {
        Functions\expect('get_transient')
            ->once()
            ->andReturn(['count' => 30, 'window_start' => 1000]);
        Functions\expect('set_transient')->never();
        Functions\expect('time')->once()->andReturn(1010);

        $this->assertFalse((new RateLimiter(30, 60))->is_allowed('127.0.0.1'));
    }

    public function test_expired_window_resets_counter(): void
    {
        Functions\expect('get_transient')
            ->once()
            ->andReturn(['count' => 30, 'window_start' => 1000]);
        Functions\expect('set_transient')
            ->once()
            ->andReturnUsing(function (string $key, array $data): bool {
                $this->assertSame(1, $data['count']);
                return true;
            });
        // time() returns 1061: window_start(1000) + window(60) = 1060 < 1061 → expired
        Functions\expect('time')->once()->andReturn(1061);

        $this->assertTrue((new RateLimiter(30, 60))->is_allowed('127.0.0.1'));
    }

    public function test_different_identifiers_are_tracked_independently(): void
    {
        Functions\expect('get_transient')->twice()->andReturn(false);
        Functions\expect('set_transient')->twice()->andReturn(true);
        Functions\expect('time')->twice()->andReturn(1000);

        $limiter = new RateLimiter(1, 60);
        $this->assertTrue($limiter->is_allowed('1.1.1.1'));
        $this->assertTrue($limiter->is_allowed('2.2.2.2'));
    }
}
