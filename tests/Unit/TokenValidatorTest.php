<?php

declare(strict_types=1);

namespace WPT\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WPT\Token\TokenIssuer;
use WPT\Token\TokenValidator;

class TokenValidatorTest extends TestCase
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

    public function test_validate_returns_data_for_valid_token(): void
    {
        $token    = 'abc123';
        $expected = ['post_id' => 1, 'user_id' => 2, 'expires_at' => 9_999_999_999];

        Functions\expect('get_option')
            ->once()
            ->with(TokenIssuer::OPTIONS_PREFIX . hash('sha256', $token))
            ->andReturn($expected);

        Functions\expect('time')->once()->andReturn(1_000_000);

        $this->assertSame($expected, (new TokenValidator())->validate($token));
    }

    public function test_validate_returns_false_for_expired_token(): void
    {
        $token = 'expiredtoken';

        Functions\expect('get_option')
            ->once()
            ->andReturn(['post_id' => 1, 'user_id' => 1, 'expires_at' => 500]);

        Functions\expect('time')->once()->andReturn(1_000);

        $this->assertFalse((new TokenValidator())->validate($token));
    }

    public function test_validate_returns_false_for_unknown_token(): void
    {
        Functions\expect('get_option')->once()->andReturn(false);

        $this->assertFalse((new TokenValidator())->validate('unknown'));
    }

    public function test_validate_returns_false_for_empty_string(): void
    {
        Functions\expect('get_option')->never();

        $this->assertFalse((new TokenValidator())->validate(''));
    }

    public function test_validate_returns_false_for_malformed_data(): void
    {
        Functions\expect('get_option')->once()->andReturn('not-an-array');

        $this->assertFalse((new TokenValidator())->validate('sometoken'));
    }

    public function test_validate_returns_false_when_required_keys_missing(): void
    {
        Functions\expect('get_option')->once()->andReturn(['post_id' => 1]);

        $this->assertFalse((new TokenValidator())->validate('partial'));
    }
}
