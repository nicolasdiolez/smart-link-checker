<?php
/**
 * PHPUnit bootstrap for unit tests.
 *
 * Loads the Composer autoloader and defines minimal constants
 * required by the plugin without loading WordPress core.
 *
 * @package FlavorLinkChecker\Tests
 */

// Define ABSPATH so the plugin files pass the `defined('ABSPATH') || exit` guard.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
}

if ( ! defined( 'SLKC_VERSION' ) ) {
	define( 'SLKC_VERSION', '1.0.0' );
}

if ( ! defined( 'SLKC_PLUGIN_DIR' ) ) {
	define( 'SLKC_PLUGIN_DIR', dirname( __DIR__, 2 ) . '/' );
}

if ( ! defined( 'SLKC_PLUGIN_FILE' ) ) {
	define( 'SLKC_PLUGIN_FILE', SLKC_PLUGIN_DIR . 'sentinel-link-checker.php' );
}

// Load WordPress function stubs for unit testing without WordPress.
require_once __DIR__ . '/stubs.php';

// Load the Composer autoloader.
require_once SLKC_PLUGIN_DIR . 'vendor/autoload.php';
