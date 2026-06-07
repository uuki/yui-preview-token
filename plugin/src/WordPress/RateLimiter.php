<?php

declare(strict_types=1);

namespace PVT\WordPress;

class RateLimiter
{
    private const PREFIX = 'pvt_rl_';

    private int $max_requests;
    private int $window;

    public function __construct(int $max_requests, int $window)
    {
        $this->max_requests = $max_requests;
        $this->window       = $window;
    }

    public function is_allowed(string $identifier): bool
    {
        $key  = self::PREFIX . hash('sha256', $identifier);
        $data = get_transient($key);
        $now  = time();

        if (!is_array($data) || $now >= ($data['window_start'] + $this->window)) {
            set_transient($key, ['count' => 1, 'window_start' => $now], $this->window);
            return true;
        }

        if ((int) $data['count'] >= $this->max_requests) {
            return false;
        }

        set_transient(
            $key,
            ['count' => (int) $data['count'] + 1, 'window_start' => $data['window_start']],
            $this->window
        );

        return true;
    }
}
