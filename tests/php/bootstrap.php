<?php
/**
 * PHPUnit bootstrap for unit tests.
 *
 * Loads the Composer autoloader and defines minimal constants
 * required by the plugin without loading WordPress core.
 *
 * @package MuriLinkTracker\Tests
 */

// Define ABSPATH so the plugin files pass the `defined('ABSPATH') || exit` guard.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
}

if ( ! defined( 'MLTR_VERSION' ) ) {
	define( 'MLTR_VERSION', '1.0.0' );
}

if ( ! defined( 'MLTR_PLUGIN_DIR' ) ) {
	define( 'MLTR_PLUGIN_DIR', dirname( __DIR__, 2 ) . '/' );
}

if ( ! defined( 'MLTR_PLUGIN_FILE' ) ) {
	define( 'MLTR_PLUGIN_FILE', MLTR_PLUGIN_DIR . 'muri-link-tracker.php' );
}

// Load WordPress function stubs for unit testing without WordPress.
require_once __DIR__ . '/stubs.php';

// Load the Composer autoloader.
require_once MLTR_PLUGIN_DIR . 'vendor/autoload.php';
