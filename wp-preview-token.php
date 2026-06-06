<?php

/**
 * Plugin Name: WP Preview Token
 * Plugin URI:  https://github.com/uuki/wp-preview-token
 * Description: Issues short-lived preview tokens for Headless WordPress preview authentication.
 * Version:     1.0.0
 * Requires PHP: 7.4
 * License:     MIT
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

WPT\WordPress\Plugin::get_instance()->init();
