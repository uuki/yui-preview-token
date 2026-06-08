<?php

declare(strict_types=1);

namespace YUIPT\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use YUIPT\WordPress\IssueEndpoint;
use YUIPT\WordPress\Settings;
use YUIPT\WordPress\RateLimiter;
use YUIPT\Token\TokenIssuer;

/**
 * Tests for the "Allow External Token Issuance" guard in IssueEndpoint.
 *
 * Attack scenario: a request arrives with valid WordPress credentials
 * (e.g. Application Password) but without the X-WP-Nonce header that
 * the admin UI automatically supplies. When external issuance is off
 * (the default), such requests must be rejected with 403
 * external_issuance_disabled — not 2xx.
 *
 * WP_REST_Request is stubbed in tests/Stubs/WP_REST_Request.php and
 * loaded via tests/bootstrap.php.
 */
class IssueEndpointExternalIssuanceTest extends TestCase
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

    // ── is_admin_ui_request() ─────────────────────────────────────────────────

    public function test_no_nonce_header_is_not_admin_ui(): void
    {
        Functions\when('wp_verify_nonce')->justReturn(false);
        self::assertFalse($this->callIsAdminUiRequest(null));
    }

    public function test_empty_nonce_header_is_not_admin_ui(): void
    {
        Functions\when('wp_verify_nonce')->justReturn(false);
        self::assertFalse($this->callIsAdminUiRequest(''));
    }

    public function test_invalid_nonce_is_not_admin_ui(): void
    {
        Functions\when('wp_verify_nonce')->justReturn(false);
        self::assertFalse($this->callIsAdminUiRequest('tampered_nonce'));
    }

    public function test_valid_nonce_is_admin_ui(): void
    {
        Functions\when('wp_verify_nonce')->justReturn(1);
        self::assertTrue($this->callIsAdminUiRequest('valid_nonce'));
    }

    // ── External-issuance guard in handle_post() ──────────────────────────────

    public function test_external_issuance_off_without_nonce_returns_403(): void
    {
        $settings = $this->createMock(Settings::class);
        $settings->method('get_allow_external_issuance')->willReturn(false);

        $issuer  = $this->createMock(TokenIssuer::class);
        $limiter = $this->createMock(RateLimiter::class);
        $limiter->method('is_allowed')->willReturn(true);

        Functions\when('wp_verify_nonce')->justReturn(false);
        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('wp_unslash')->returnArg();

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $endpoint = new IssueEndpoint($issuer, $settings, $limiter);
        $result   = $endpoint->handle_post(new \WP_REST_Request(null)); // no nonce

        self::assertInstanceOf(\WP_Error::class, $result);
        self::assertSame('external_issuance_disabled', $result->get_error_code());
        self::assertSame(403, $result->get_error_data()['status']);
    }

    public function test_external_issuance_on_without_nonce_does_not_block_early(): void
    {
        // When external issuance is ON, the guard is skipped entirely.
        // The request proceeds to capability checks (which fail here for other
        // reasons). The important assertion is that the error is NOT
        // 'external_issuance_disabled'.
        $settings = $this->createMock(Settings::class);
        $settings->method('get_allow_external_issuance')->willReturn(true);
        $settings->method('get_min_capability')->willReturn('edit_posts');

        $issuer  = $this->createMock(TokenIssuer::class);
        $limiter = $this->createMock(RateLimiter::class);
        $limiter->method('is_allowed')->willReturn(true);

        Functions\when('wp_verify_nonce')->justReturn(false);
        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('wp_unslash')->returnArg();
        Functions\when('get_current_user_id')->justReturn(1);
        Functions\when('user_can')->justReturn(false); // capability denied on purpose
        Functions\when('do_action')->justReturn(null);

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $endpoint = new IssueEndpoint($issuer, $settings, $limiter);
        $result   = $endpoint->handle_post(new \WP_REST_Request(null));

        self::assertInstanceOf(\WP_Error::class, $result);
        self::assertNotSame('external_issuance_disabled', $result->get_error_code());
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function callIsAdminUiRequest(?string $nonce): bool
    {
        $settings = $this->createMock(Settings::class);
        $issuer   = $this->createMock(TokenIssuer::class);
        $limiter  = $this->createMock(RateLimiter::class);

        $endpoint  = new IssueEndpoint($issuer, $settings, $limiter);
        $reflector = new \ReflectionMethod($endpoint, 'is_admin_ui_request');
        $reflector->setAccessible(true);

        return $reflector->invoke($endpoint, new \WP_REST_Request($nonce));
    }
}
