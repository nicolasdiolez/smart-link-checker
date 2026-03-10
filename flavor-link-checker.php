<?php
/**
 * Plugin Name:       LinkChecker
 * Plugin URI:
 * Description:       A high-performance link checker for WordPress with affiliate detection.
 * Version:           1.0.0
 * Requires at least: 6.9
 * Requires PHP:      8.2
 * Author:            Flavor
 * Author URI:
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       flavor-link-checker
 * Domain Path:       /languages
 *
 * @package FlavorLinkChecker
 * @since   1.0.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin version.
 *
 * @since 1.0.0
 */
define( 'FLC_VERSION', '1.0.0' );

/**
 * Path to the main plugin file.
 *
 * @since 1.0.0
 */
define( 'FLC_PLUGIN_FILE', __FILE__ );

/**
 * Plugin directory path with trailing slash.
 *
 * @since 1.0.0
 */
define( 'FLC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL with trailing slash.
 *
 * @since 1.0.0
 */
define( 'FLC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/*
 * Composer autoloader.
 */
if ( ! file_exists( FLC_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'LinkChecker: Composer autoloader not found. Please run "composer install" in the plugin directory.', 'flavor-link-checker' );
			echo '</p></div>';
		}
	);
	return;
}

require_once FLC_PLUGIN_DIR . 'vendor/autoload.php';

/*
 * Action Scheduler.
 */
$flc_as_file = FLC_PLUGIN_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
if ( file_exists( $flc_as_file ) ) {
	require_once $flc_as_file;
}

/*
 * Activation / Deactivation hooks.
 */
register_activation_hook( __FILE__, array( \FlavorLinkChecker\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \FlavorLinkChecker\Deactivator::class, 'deactivate' ) );

/*
 * Bootstrap the plugin.
 */
add_action(
	'plugins_loaded',
	static function (): void {
		\FlavorLinkChecker\Plugin::get_instance()->register();
	}
);
