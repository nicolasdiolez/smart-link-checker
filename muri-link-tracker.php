<?php
/**
 * Plugin Name:       Muri Link Tracker
 * Plugin URI:        https://github.com/nicolasdiolez/muri-link-tracker
 * Description:       A high-performance link checker for WordPress with affiliate detection, redirect tracking, and background processing.
 * Version:           1.0.0
 * Requires at least: 6.9
 * Requires PHP:      8.2
 * Tested up to:      6.9
 * Author:            Nicolas Diolez
 * Author URI:        https://nicolasdiolez.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       muri-link-tracker
 * Domain Path:       /languages
 *
 * @package MuriLinkTracker
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
define( 'MLTR_VERSION', '1.0.0' );

/**
 * Path to the main plugin file.
 *
 * @since 1.0.0
 */
define( 'MLTR_PLUGIN_FILE', __FILE__ );

/**
 * Plugin directory path with trailing slash.
 *
 * @since 1.0.0
 */
define( 'MLTR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL with trailing slash.
 *
 * @since 1.0.0
 */
define( 'MLTR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/*
 * Composer autoloader.
 */
if ( ! file_exists( MLTR_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'Muri Link Tracker: Composer autoloader not found. Please run "composer install" in the plugin directory.', 'muri-link-tracker' );
			echo '</p></div>';
		}
	);
	return;
}

require_once MLTR_PLUGIN_DIR . 'vendor/autoload.php';

/*
 * Action Scheduler.
 */
if ( file_exists( MLTR_PLUGIN_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php' ) ) {
	require_once MLTR_PLUGIN_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
}

/*
 * Activation / Deactivation hooks.
 */
register_activation_hook( __FILE__, array( \MuriLinkTracker\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \MuriLinkTracker\Deactivator::class, 'deactivate' ) );

/*
 * Bootstrap the plugin.
 */
add_action(
	'plugins_loaded',
	static function (): void {
		\MuriLinkTracker\Plugin::get_instance()->register();
	}
);
