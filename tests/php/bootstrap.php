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

if ( ! defined( 'FLC_VERSION' ) ) {
	define( 'FLC_VERSION', '1.0.0' );
}

if ( ! defined( 'FLC_PLUGIN_DIR' ) ) {
	define( 'FLC_PLUGIN_DIR', dirname( __DIR__, 2 ) . '/' );
}

if ( ! defined( 'FLC_PLUGIN_FILE' ) ) {
	define( 'FLC_PLUGIN_FILE', FLC_PLUGIN_DIR . 'sentinel-link-checker.php' );
}

// Load WordPress function stubs for unit testing without WordPress.
require_once __DIR__ . '/stubs.php';

// Load the Composer autoloader.
require_once FLC_PLUGIN_DIR . 'vendor/autoload.php';
