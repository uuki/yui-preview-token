<?php

declare(strict_types=1);

namespace YUIPT\Tests\Unit;

use PHPUnit\Framework\TestCase;
use YUIPT\Support\ResponseFilters;

class ResponseFiltersTest extends TestCase
{
    public function test_strip_password_removes_password_field(): void
    {
        $result = ResponseFilters::strip_password([
            'id'       => 1,
            'title'    => 'Test',
            'password' => 'secret',
        ]);

        $this->assertArrayNotHasKey('password', $result);
        $this->assertSame(1, $result['id']);
    }

    public function test_strip_password_is_noop_when_no_password_field(): void
    {
        $data = ['id' => 1, 'title' => 'Test'];

        $this->assertSame($data, ResponseFilters::strip_password($data));
    }

    public function test_strip_internal_fields_removes_expected_keys(): void
    {
        $result = ResponseFilters::strip_internal_fields([
            'id'          => 1,
            'title'       => 'Test',
            'guid'        => 'http://example.com/?p=1',
            'ping_status' => 'open',
            'template'    => '',
        ]);

        $this->assertArrayNotHasKey('guid', $result);
        $this->assertArrayNotHasKey('ping_status', $result);
        $this->assertArrayNotHasKey('template', $result);
        $this->assertSame(1, $result['id']);
        $this->assertSame('Test', $result['title']);
    }

    public function test_strip_internal_fields_is_noop_when_no_target_keys(): void
    {
        $data = ['id' => 1, 'title' => 'Test'];

        $this->assertSame($data, ResponseFilters::strip_internal_fields($data));
    }
}
