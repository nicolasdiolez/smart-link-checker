<?php
/**
 * Admin page registration and React asset enqueue.
 *
 * @package FlavorLinkChecker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace FlavorLinkChecker\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the top-level admin menu page and enqueues the React app.
 *
 * @since 1.0.0
 */
class AdminPage {

	/**
	 * Registers WordPress hooks.
	 *
	 * @since 1.0.0
	 */
	public function register(): void {
		add_action( 'admin_menu', $this->add_menu_page( ... ) );
		add_action( 'admin_enqueue_scripts', $this->enqueue_assets( ... ) );
	}

	/**
	 * Adds the plugin menu page.
	 *
	 * @since 1.0.0
	 */
	private function add_menu_page(): void {
		add_menu_page(
			__( 'LinkChecker', 'flavor-link-checker' ),
			__( 'LinkChecker', 'flavor-link-checker' ),
			'manage_options',
			'flavor-link-checker',
			$this->render_page( ... ),
			'dashicons-admin-links',
			82
		);
	}

	/**
	 * Renders the admin page container for the React app.
	 *
	 * @since 1.0.0
	 */
	private function render_page(): void {
		echo '<div class="wrap"><div id="flc-root"></div></div>';
	}

	/**
	 * Conditionally enqueues React build assets on the plugin page only.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	private function enqueue_assets( string $hook_suffix ): void {
		if ( 'toplevel_page_flavor-link-checker' !== $hook_suffix ) {
			return;
		}

		$asset_file = FLC_PLUGIN_DIR . 'build/index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'flc-admin',
			FLC_PLUGIN_URL . 'build/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		if ( file_exists( FLC_PLUGIN_DIR . 'build/index.css' ) ) {
			wp_enqueue_style(
				'flc-admin',
				FLC_PLUGIN_URL . 'build/index.css',
				array( 'wp-components' ),
				$asset['version']
			);
		}

		wp_add_inline_script(
			'flc-admin',
			'window.flcData = ' . wp_json_encode(
				array(
					'restUrl'  => rest_url( 'flavor-link-checker/v1/' ),
					'nonce'    => wp_create_nonce( 'wp_rest' ),
					'adminUrl' => admin_url(),
					'version'  => FLC_VERSION,
				)
			) . ';',
			'before'
		);
	}
}
