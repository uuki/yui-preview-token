<?php

declare(strict_types=1);

namespace PVT\Support;

class ResponseFilters
{
    private const INTERNAL_FIELDS = ['guid', 'ping_status', 'template'];

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function strip_password(array $data): array
    {
        unset($data['password']);
        return $data;
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function strip_internal_fields(array $data): array
    {
        foreach (self::INTERNAL_FIELDS as $field) {
            unset($data[$field]);
        }
        return $data;
    }
}
