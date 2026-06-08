<?php

declare(strict_types=1);

namespace YUIPT\Tests\Unit;

use PHPUnit\Framework\TestCase;
use YUIPT\Support\ResponsePipeline;

class ResponsePipelineTest extends TestCase
{
    public function test_process_applies_filters_in_order(): void
    {
        $log = [];

        $pipeline = new ResponsePipeline([
            static function (array $data) use (&$log): array {
                $log[] = 'first';
                $data['first'] = true;
                return $data;
            },
            static function (array $data) use (&$log): array {
                $log[] = 'second';
                $data['second'] = true;
                return $data;
            },
        ]);

        $result = $pipeline->process([]);

        $this->assertSame(['first', 'second'], $log);
        $this->assertTrue($result['first']);
        $this->assertTrue($result['second']);
    }

    public function test_process_returns_data_unchanged_with_no_filters(): void
    {
        $data = ['foo' => 'bar'];

        $this->assertSame($data, (new ResponsePipeline([]))->process($data));
    }

    public function test_process_passes_output_of_each_filter_to_next(): void
    {
        $pipeline = new ResponsePipeline([
            static fn(array $d): array => array_merge($d, ['a' => 1]),
            static fn(array $d): array => array_merge($d, ['b' => $d['a'] + 1]),
        ]);

        $result = $pipeline->process([]);

        $this->assertSame(1, $result['a']);
        $this->assertSame(2, $result['b']);
    }
}
