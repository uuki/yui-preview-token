<?php

/**
 * Plugin Name: Preview Token
 * Plugin URI:  https://github.com/uuki/wp-preview-token
 * Description: Issues short-lived preview tokens for Headless WordPress preview authentication.
 * Version:     1.0.4
 * Requires PHP: 7.4
 * License:     GPL-2.0-or-later
 * Text Domain: preview-token
 * Domain Path: /languages
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('PVT_PLUGIN_FILE', __FILE__);

require_once __DIR__ . '/vendor/autoload.php';

PVT\WordPress\Plugin::get_instance()->init();
