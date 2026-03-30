<?php
/**
 * Plugin activation handler.
 *
 * @package FlavorLinkChecker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace FlavorLinkChecker;

defined( 'ABSPATH' ) || exit;

use FlavorLinkChecker\Database\Migrator;

/**
 * Runs on plugin activation via register_activation_hook().
 *
 * @since 1.0.0
 */
class Activator {

	/**
	 * Creates database tables and sets default options.
	 *
	 * @since 1.0.0
	 */
	public static function activate(): void {
		( new Migrator() )->create_tables();

		if ( false === get_option( 'slkc_settings' ) ) {
			update_option(
				'slkc_settings',
				array(
					'scan_post_types'    => array( 'post', 'page' ),
					'check_timeout'      => 15,
					'batch_size'         => 50,
					'recheck_interval'   => 7,
					'excluded_urls'      => array(),
					'scan_custom_fields' => false,
					'http_request_delay' => 300,
				)
			);
		}
	}
}
