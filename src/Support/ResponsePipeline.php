<?php

declare(strict_types=1);

namespace PVT\Support;

class ResponsePipeline
{
    /** @var callable[] */
    private array $filters;

    /** @param callable[] $filters */
    public function __construct(array $filters)
    {
        $this->filters = $filters;
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function process(array $data): array
    {
        return array_reduce(
            $this->filters,
            static fn(array $carry, callable $filter): array => $filter($carry),
            $data
        );
    }
}
