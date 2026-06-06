<?php

declare(strict_types=1);

namespace WPT\Token;

class TokenValidator
{
    /**
     * @param  string $token
     * @return array{post_id: int, user_id: int, expires_at: int}|false
     */
    public function validate(string $token)
    {
        if ($token === '') {
            return false;
        }

        $data = get_option(TokenIssuer::OPTIONS_PREFIX . hash('sha256', $token));

        if (!is_array($data) || !isset($data['post_id'], $data['expires_at'])) {
            return false;
        }

        if (time() > $data['expires_at']) {
            return false;
        }

        return $data;
    }
}
