<?php
/**
 * Plugin deactivation handler.
 *
 * @package FlavorLinkChecker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace FlavorLinkChecker;

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
			as_unschedule_all_actions( '', array(), 'smart-link-checker' );
		}
	}
}
