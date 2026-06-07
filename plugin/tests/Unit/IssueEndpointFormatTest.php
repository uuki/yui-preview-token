<?php

declare(strict_types=1);

namespace PVT\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use PVT\WordPress\IssueEndpoint;
use PVT\WordPress\Settings;
use PVT\WordPress\RateLimiter;
use PVT\Token\TokenIssuer;

/**
 * Verifies that the preview_url returned by IssueEndpoint contains
 * all expected query parameters: p, preview, and token.
 */
class IssueEndpointFormatTest extends TestCase
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

    public function test_preview_url_contains_p_preview_and_token(): void
    {
        $post_id    = 42;
        $raw_token  = str_repeat('a', 64);
        $frontend   = 'https://front.example.com/preview';

        $settings = $this->createMock(Settings::class);
        $settings->method('get_frontend_url')->willReturn($frontend);
        $settings->method('get_allow_no_expiry')->willReturn(false);
        $settings->method('get_min_capability')->willReturn('edit_posts');

        $issuer = $this->createMock(TokenIssuer::class);
        $issuer->method('get_by_post')->with($post_id)->willReturn([
            'raw'        => $raw_token,
            'hash'       => hash('sha256', $raw_token),
            'expires_at' => time() + 3600,
            'issued_by'  => 1,
            'issued_at'  => time(),
        ]);

        $limiter = $this->createMock(RateLimiter::class);

        // Stub WordPress functions used during format()
        Functions\when('add_query_arg')->alias(static function ($args, $url) {
            $query = http_build_query($args);
            $sep   = str_contains($url, '?') ? '&' : '?';
            return $url . $sep . $query;
        });
        Functions\when('home_url')->justReturn('https://wp.example.com');
        Functions\when('get_post_type')->justReturn('post');

        $endpoint  = new IssueEndpoint($issuer, $settings, $limiter);
        $reflector = new \ReflectionMethod($endpoint, 'format');
        $reflector->setAccessible(true);

        $data   = $issuer->get_by_post($post_id);
        $result = $reflector->invoke($endpoint, $data, $post_id);

        $url = $result['preview_url'];

        self::assertStringContainsString('p=42',          $url, 'preview_url must contain p=<post_id>');
        self::assertStringContainsString('pt=post',       $url, 'preview_url must contain pt=<post_type>');
        self::assertStringContainsString('preview=true',  $url, 'preview_url must contain preview=true');
        self::assertStringContainsString('token=' . $raw_token, $url, 'preview_url must contain token=<raw>');
    }

    public function test_pt_is_front_page_when_show_on_front_is_page_and_post_id_matches_page_on_front(): void
    {
        $post_id   = 2;
        $raw_token = str_repeat('c', 64);
        $frontend  = 'https://front.example.com';

        $settings = $this->createMock(Settings::class);
        $settings->method('get_frontend_url')->willReturn($frontend);

        $issuer = $this->createMock(TokenIssuer::class);
        $issuer->method('get_by_post')->with($post_id)->willReturn([
            'raw'        => $raw_token,
            'hash'       => hash('sha256', $raw_token),
            'expires_at' => time() + 3600,
            'issued_by'  => 1,
            'issued_at'  => time(),
        ]);

        $limiter = $this->createMock(RateLimiter::class);

        Functions\when('add_query_arg')->alias(static function ($args, $url) {
            return $url . '?' . http_build_query($args);
        });
        Functions\when('home_url')->justReturn('https://wp.example.com');
        Functions\when('get_post_type')->justReturn('page');
        Functions\when('get_option')->alias(static function (string $key) use ($post_id) {
            if ($key === 'show_on_front')  return 'page';
            if ($key === 'page_on_front')  return (string) $post_id;
            if ($key === 'page_for_posts') return '99';
            return false;
        });

        $endpoint  = new IssueEndpoint($issuer, $settings, $limiter);
        $reflector = new \ReflectionMethod($endpoint, 'format');
        $reflector->setAccessible(true);

        $result = $reflector->invoke($endpoint, $issuer->get_by_post($post_id), $post_id);

        self::assertStringContainsString('pt=front_page', $result['preview_url']);
    }

    public function test_pt_is_posts_page_when_show_on_front_is_page_and_post_id_matches_page_for_posts(): void
    {
        $post_id   = 5;
        $raw_token = str_repeat('d', 64);
        $frontend  = 'https://front.example.com';

        $settings = $this->createMock(Settings::class);
        $settings->method('get_frontend_url')->willReturn($frontend);

        $issuer = $this->createMock(TokenIssuer::class);
        $issuer->method('get_by_post')->with($post_id)->willReturn([
            'raw'        => $raw_token,
            'hash'       => hash('sha256', $raw_token),
            'expires_at' => time() + 3600,
            'issued_by'  => 1,
            'issued_at'  => time(),
        ]);

        $limiter = $this->createMock(RateLimiter::class);

        Functions\when('add_query_arg')->alias(static function ($args, $url) {
            return $url . '?' . http_build_query($args);
        });
        Functions\when('home_url')->justReturn('https://wp.example.com');
        Functions\when('get_post_type')->justReturn('page');
        Functions\when('get_option')->alias(static function (string $key) use ($post_id) {
            if ($key === 'show_on_front')  return 'page';
            if ($key === 'page_on_front')  return '2';
            if ($key === 'page_for_posts') return (string) $post_id;
            return false;
        });

        $endpoint  = new IssueEndpoint($issuer, $settings, $limiter);
        $reflector = new \ReflectionMethod($endpoint, 'format');
        $reflector->setAccessible(true);

        $result = $reflector->invoke($endpoint, $issuer->get_by_post($post_id), $post_id);

        self::assertStringContainsString('pt=posts_page', $result['preview_url']);
    }

    public function test_pt_is_page_when_show_on_front_is_not_page(): void
    {
        $post_id   = 2;
        $raw_token = str_repeat('e', 64);
        $frontend  = 'https://front.example.com';

        $settings = $this->createMock(Settings::class);
        $settings->method('get_frontend_url')->willReturn($frontend);

        $issuer = $this->createMock(TokenIssuer::class);
        $issuer->method('get_by_post')->with($post_id)->willReturn([
            'raw'        => $raw_token,
            'hash'       => hash('sha256', $raw_token),
            'expires_at' => time() + 3600,
            'issued_by'  => 1,
            'issued_at'  => time(),
        ]);

        $limiter = $this->createMock(RateLimiter::class);

        Functions\when('add_query_arg')->alias(static function ($args, $url) {
            return $url . '?' . http_build_query($args);
        });
        Functions\when('home_url')->justReturn('https://wp.example.com');
        Functions\when('get_post_type')->justReturn('page');
        Functions\when('get_option')->alias(static function (string $key) {
            if ($key === 'show_on_front') return 'posts'; // latest posts mode
            return false;
        });

        $endpoint  = new IssueEndpoint($issuer, $settings, $limiter);
        $reflector = new \ReflectionMethod($endpoint, 'format');
        $reflector->setAccessible(true);

        $result = $reflector->invoke($endpoint, $issuer->get_by_post($post_id), $post_id);

        self::assertStringContainsString('pt=page', $result['preview_url']);
        self::assertStringNotContainsString('pt=front_page', $result['preview_url']);
    }

    public function test_preview_url_param_order_is_p_preview_token(): void
    {
        $post_id   = 7;
        $raw_token = str_repeat('b', 64);
        $frontend  = 'https://front.example.com';

        $settings = $this->createMock(Settings::class);
        $settings->method('get_frontend_url')->willReturn($frontend);
        $settings->method('get_allow_no_expiry')->willReturn(false);
        $settings->method('get_min_capability')->willReturn('edit_posts');

        $issuer = $this->createMock(TokenIssuer::class);
        $issuer->method('get_by_post')->with($post_id)->willReturn([
            'raw'        => $raw_token,
            'hash'       => hash('sha256', $raw_token),
            'expires_at' => time() + 3600,
            'issued_by'  => 1,
            'issued_at'  => time(),
        ]);

        $limiter = $this->createMock(RateLimiter::class);

        Functions\when('add_query_arg')->alias(static function ($args, $url) {
            $query = http_build_query($args);
            $sep   = str_contains($url, '?') ? '&' : '?';
            return $url . $sep . $query;
        });
        Functions\when('home_url')->justReturn('https://wp.example.com');
        Functions\when('get_post_type')->justReturn('post');

        $endpoint  = new IssueEndpoint($issuer, $settings, $limiter);
        $reflector = new \ReflectionMethod($endpoint, 'format');
        $reflector->setAccessible(true);

        $data   = $issuer->get_by_post($post_id);
        $result = $reflector->invoke($endpoint, $data, $post_id);

        $url   = $result['preview_url'];
        $pos_p       = strpos($url, 'p=');
        $pos_pt      = strpos($url, 'pt=');
        $pos_preview = strpos($url, 'preview=');
        $pos_token   = strpos($url, 'token=');

        self::assertNotFalse($pos_p,       'p param must be present');
        self::assertNotFalse($pos_pt,      'pt param must be present');
        self::assertNotFalse($pos_preview, 'preview param must be present');
        self::assertNotFalse($pos_token,   'token param must be present');
        self::assertLessThan($pos_pt,      $pos_p,       'p must come before pt');
        self::assertLessThan($pos_preview, $pos_pt,      'pt must come before preview');
        self::assertLessThan($pos_token,   $pos_preview, 'preview must come before token');
    }
}
