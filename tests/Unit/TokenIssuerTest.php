<?php

declare(strict_types=1);

namespace WPT\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WPT\Token\TokenIssuer;

class TokenIssuerTest extends TestCase
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

    /** Stub all WP functions not under assertion in this test. */
    private function stubAll(): void
    {
        Functions\when('get_post_meta')->justReturn('');
        Functions\when('delete_post_meta')->justReturn(true);
        Functions\when('update_post_meta')->justReturn(1);
        Functions\when('update_option')->justReturn(true);
        Functions\when('delete_option')->justReturn(true);
        Functions\when('get_option')->justReturn(false);
        Functions\when('time')->justReturn(1_000_000);
        Functions\when('do_action')->justReturn(null);
    }

    public function test_issue_returns_64_char_hex_string(): void
    {
        $this->stubAll();

        $token = (new TokenIssuer())->issue(1, 1, 1_749_456_000);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    public function test_issue_stores_option_with_hashed_key_and_correct_data(): void
    {
        // Stub everything except update_option, which we assert on
        Functions\when('get_post_meta')->justReturn('');
        Functions\when('delete_post_meta')->justReturn(true);
        Functions\when('update_post_meta')->justReturn(1);
        Functions\when('delete_option')->justReturn(true);
        Functions\when('time')->justReturn(1_000_000);
        Functions\when('do_action')->justReturn(null);

        $capturedKey  = '';
        $capturedData = [];
        Functions\expect('update_option')
            ->once()
            ->andReturnUsing(function (string $key, array $data) use (&$capturedKey, &$capturedData): bool {
                $capturedKey  = $key;
                $capturedData = $data;
                return true;
            });

        $token = (new TokenIssuer())->issue(1, 2, 1_749_456_000);

        $this->assertSame('wpt_tk_' . hash('sha256', $token), $capturedKey);
        $this->assertSame(1,             $capturedData['post_id']);
        $this->assertSame(2,             $capturedData['user_id']);
        $this->assertSame(1_749_456_000, $capturedData['expires_at']);
    }

    public function test_issue_fires_wpt_token_issued_action(): void
    {
        // Stub everything except do_action, which we assert on
        Functions\when('get_post_meta')->justReturn('');
        Functions\when('delete_post_meta')->justReturn(true);
        Functions\when('update_option')->justReturn(true);
        Functions\when('update_post_meta')->justReturn(1);
        Functions\when('time')->justReturn(1_000_000);

        $fired = false;
        Functions\expect('do_action')
            ->once()
            ->with('wpt_token_issued', 10, 99)
            ->andReturnUsing(static function () use (&$fired): void { $fired = true; });

        (new TokenIssuer())->issue(10, 99, 1_749_456_000);

        $this->assertTrue($fired);
    }

    public function test_issue_generates_unique_tokens(): void
    {
        $this->stubAll();

        $issuer = new TokenIssuer();
        $this->assertNotSame($issuer->issue(1, 1, 1_000), $issuer->issue(1, 1, 1_000));
    }

    public function test_update_expiry_returns_false_when_no_token(): void
    {
        Functions\expect('get_post_meta')
            ->once()
            ->with(1, TokenIssuer::META_HASH, true)
            ->andReturn('');

        $this->assertFalse((new TokenIssuer())->update_expiry(1, 9_999_999));
    }

    public function test_update_expiry_updates_option_and_meta(): void
    {
        Functions\when('get_post_meta')
            ->justReturn('abc123hash');

        Functions\when('get_option')
            ->justReturn(['post_id' => 1, 'user_id' => 1, 'expires_at' => 1_000]);

        $capturedExpiry = 0;
        Functions\expect('update_option')
            ->once()
            ->andReturnUsing(function (string $key, array $data) use (&$capturedExpiry): bool {
                $capturedExpiry = $data['expires_at'];
                return true;
            });

        Functions\expect('update_post_meta')
            ->once()
            ->with(1, TokenIssuer::META_EXPIRES_AT, 9_999_999)
            ->andReturn(1);

        $result = (new TokenIssuer())->update_expiry(1, 9_999_999);

        $this->assertTrue($result);
        $this->assertSame(9_999_999, $capturedExpiry);
    }

    public function test_delete_by_post_removes_option_and_meta(): void
    {
        Functions\when('get_post_meta')->justReturn('deadbeefhash');

        $deletedOption = '';
        Functions\expect('delete_option')
            ->once()
            ->andReturnUsing(function (string $key) use (&$deletedOption): bool {
                $deletedOption = $key;
                return true;
            });

        Functions\expect('delete_post_meta')->times(5)->andReturn(true);

        (new TokenIssuer())->delete_by_post(1);

        $this->assertSame('wpt_tk_deadbeefhash', $deletedOption);
    }

    public function test_get_by_post_returns_null_when_no_meta(): void
    {
        Functions\expect('get_post_meta')
            ->once()
            ->with(1, TokenIssuer::META_HASH, true)
            ->andReturn('');

        $this->assertNull((new TokenIssuer())->get_by_post(1));
    }

    public function test_get_by_post_returns_data(): void
    {
        $meta = [
            TokenIssuer::META_HASH       => 'myhash',
            TokenIssuer::META_RAW        => 'rawtoken',
            TokenIssuer::META_EXPIRES_AT => 1_749_456_000,
            TokenIssuer::META_ISSUED_BY  => 2,
            TokenIssuer::META_ISSUED_AT  => 1_749_369_600,
        ];

        Functions\when('get_post_meta')
            ->alias(static fn($post_id, $key, $single) => $meta[$key] ?? '');

        $result = (new TokenIssuer())->get_by_post(1);

        $this->assertSame('myhash',      $result['hash']);
        $this->assertSame('rawtoken',    $result['raw']);
        $this->assertSame(1_749_456_000, $result['expires_at']);
        $this->assertSame(2,             $result['issued_by']);
        $this->assertSame(1_749_369_600, $result['issued_at']);
    }
}
