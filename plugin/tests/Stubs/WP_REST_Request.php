<?php

/**
 * Minimal WP_REST_Request stub for PHPUnit tests running under Brain Monkey.
 * Loaded via phpunit.xml <file> directive or require_once in bootstrap.php.
 */
if (!function_exists('__')) {
    function __($text, $domain = 'default') { return $text; }
}

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        private string $code;
        private string $message;
        /** @var array<string,mixed> */
        private array $data;

        /** @param array<string,mixed> $data */
        public function __construct(string $code = '', string $message = '', array $data = [])
        {
            $this->code    = $code;
            $this->message = $message;
            $this->data    = $data;
        }

        public function get_error_code(): string { return $this->code; }
        public function get_error_message(): string { return $this->message; }
        /** @return array<string,mixed> */
        public function get_error_data(): array { return $this->data; }
    }
}

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request
    {
        private ?string $nonce;

        public function __construct(?string $nonce = null)
        {
            $this->nonce = $nonce;
        }

        public function get_header(string $key): ?string
        {
            return strtolower($key) === 'x-wp-nonce' ? $this->nonce : null;
        }

        /** @return mixed */
        public function get_param(string $key)
        {
            return 0;
        }
    }
}
