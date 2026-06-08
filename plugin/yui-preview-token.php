<?php

/**
 * Plugin Name: YUI Preview Token
 * Plugin URI:  https://github.com/uuki/yui-preview-token
 * Description: Issues short-lived preview tokens for Headless WordPress preview authentication.
 * Version:     1.1.2
 * Requires PHP: 7.4
 * License:     GPL-2.0-or-later
 * Text Domain: yui-preview-token
 * Domain Path: /languages
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('YUIPT_PLUGIN_FILE', __FILE__);

require_once __DIR__ . '/vendor/autoload.php';

YUIPT\WordPress\Plugin::get_instance()->init();
