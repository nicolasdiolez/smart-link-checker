<?php
/**
 * Plugin deactivation handler.
 *
 * @package MuriLinkTracker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace MuriLinkTracker;

defined( 'ABSPATH' ) || exit;

/**
 * Runs on plugin deactivation via register_deactivation_hook().
 *
 * @since 1.0.0
 */
class Deactivator {

	/**
	 * Unschedules all Action Scheduler actions owned by this plugin.
	 *
	 * @since 1.0.0
	 */
	public static function deactivate(): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( '', array(), 'muri-link-tracker' );
		}
	}
}
