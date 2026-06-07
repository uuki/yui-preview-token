<?php

declare(strict_types=1);

namespace PVT\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use PVT\WordPress\RestEndpoint;
use PVT\WordPress\Settings;
use PVT\Token\TokenValidator;
use PVT\Support\ResponsePipeline;
use PVT\WordPress\RateLimiter;

class RestEndpointCorsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        // Stub WordPress functions used by Settings constructor paths
        Functions\stubs([
            'get_option'  => '',
            'absint'      => static fn($v) => (int) abs((int) $v),
        ]);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    private function endpoint(): RestEndpoint
    {
        $settings  = $this->createMock(Settings::class);
        $validator = $this->createMock(TokenValidator::class);
        $pipeline  = $this->createMock(ResponsePipeline::class);
        $limiter   = $this->createMock(RateLimiter::class);

        return new RestEndpoint($validator, $pipeline, $settings, $limiter);
    }

    // ── Exact match ──────────────────────────────────────────────────────────

    public function test_exact_origin_allowed(): void
    {
        $ep = $this->endpoint();
        $this->assertTrue($ep->is_origin_allowed(
            'https://example.com',
            ['https://example.com']
        ));
    }

    public function test_exact_origin_not_matched_by_different_origin(): void
    {
        $ep = $this->endpoint();
        $this->assertFalse($ep->is_origin_allowed(
            'https://evil.com',
            ['https://example.com']
        ));
    }

    // ── Wildcard * (match-all) ────────────────────────────────────────────────

    public function test_bare_wildcard_matches_any_origin(): void
    {
        $ep = $this->endpoint();
        $this->assertTrue($ep->is_origin_allowed(
            'https://anything.example.com',
            ['*']
        ));
    }

    // ── Subdomain wildcard ───────────────────────────────────────────────────

    public function test_subdomain_wildcard_matches_single_level(): void
    {
        $ep = $this->endpoint();
        $this->assertTrue($ep->is_origin_allowed(
            'https://abc.amplifyapp.com',
            ['https://*.amplifyapp.com']
        ));
    }

    public function test_subdomain_wildcard_does_not_match_root_domain(): void
    {
        $ep = $this->endpoint();
        $this->assertFalse($ep->is_origin_allowed(
            'https://amplifyapp.com',
            ['https://*.amplifyapp.com']
        ));
    }

    public function test_subdomain_wildcard_does_not_cross_scheme(): void
    {
        $ep = $this->endpoint();
        $this->assertFalse($ep->is_origin_allowed(
            'http://sub.amplifyapp.com',
            ['https://*.amplifyapp.com']
        ));
    }

    public function test_subdomain_wildcard_does_not_cross_port_boundary(): void
    {
        $ep = $this->endpoint();
        $this->assertFalse($ep->is_origin_allowed(
            'https://sub.amplifyapp.com:8080',
            ['https://*.amplifyapp.com']
        ));
    }

    public function test_subdomain_wildcard_does_not_match_nested_subdomain(): void
    {
        // https://*.amplifyapp.com should NOT match https://a.b.amplifyapp.com
        $ep = $this->endpoint();
        $this->assertFalse($ep->is_origin_allowed(
            'https://a.b.amplifyapp.com',
            ['https://*.amplifyapp.com']
        ));
    }

    // ── Multiple patterns ───────────────────────────────────────────────────

    public function test_multiple_patterns_first_matches(): void
    {
        $ep = $this->endpoint();
        $this->assertTrue($ep->is_origin_allowed(
            'http://localhost:4321',
            ['http://localhost:4321', 'https://*.amplifyapp.com']
        ));
    }

    public function test_multiple_patterns_second_matches(): void
    {
        $ep = $this->endpoint();
        $this->assertTrue($ep->is_origin_allowed(
            'https://feature-branch.amplifyapp.com',
            ['http://localhost:4321', 'https://*.amplifyapp.com']
        ));
    }

    public function test_multiple_patterns_none_match(): void
    {
        $ep = $this->endpoint();
        $this->assertFalse($ep->is_origin_allowed(
            'https://evil.com',
            ['http://localhost:4321', 'https://*.amplifyapp.com']
        ));
    }

    // ── Empty list ──────────────────────────────────────────────────────────

    public function test_empty_patterns_denies_all(): void
    {
        $ep = $this->endpoint();
        $this->assertFalse($ep->is_origin_allowed(
            'https://example.com',
            []
        ));
    }
}
