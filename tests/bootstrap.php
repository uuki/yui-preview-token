<?php

declare(strict_types=1);

// Prevent direct web access when served by a web server.
// Define ABSPATH for CLI (PHPUnit) execution so the plugin autoloader works.
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

require_once __DIR__ . '/../vendor/autoload.php';
