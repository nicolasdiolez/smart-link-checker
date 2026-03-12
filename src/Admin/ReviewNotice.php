<?php
/**
 * Review notice logic for WordPress.org.
 *
 * @package FlavorLinkChecker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace FlavorLinkChecker\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the display and dismissal of the review notice.
 *
 * @since 1.0.0
 */
class ReviewNotice {

	/**
	 * Option key for first scan completion timestamp.
	 *
	 * @since 1.0.0
	 */
	private const OPTION_FIRST_SCAN = 'flc_first_scan_date';

	/**
	 * Option key for notice dismissal.
	 *
	 * @since 1.0.0
	 */
	private const OPTION_DISMISSED = 'flc_review_notice_dismissed';

	/**
	 * Ajax action name for dismissal.
	 *
	 * @since 1.0.0
	 */
	private const AJAX_ACTION = 'flc_dismiss_review_notice';

	/**
	 * Registers WordPress hooks.
	 *
	 * @since 1.0.0
	 */
	public function register(): void {
		\add_action( 'admin_notices', $this->maybe_display_notice( ... ) );
		\add_action( 'wp_ajax_' . self::AJAX_ACTION, $this->ajax_dismiss_notice( ... ) );
		\add_action( 'admin_enqueue_scripts', $this->enqueue_assets( ... ) );
		
		// Track completion via scan controller/orchestrator would be ideal, 
		// but we can also hook into flc/scan/complete.
		\add_action( 'flc/scan/complete', $this->track_scan_completion( ... ) );
	}

	/**
	 * Tracks the first time a scan is completed.
	 *
	 * @since 1.0.0
	 */
	public function track_scan_completion(): void {
		if ( ! \get_option( self::OPTION_FIRST_SCAN ) ) {
			\update_option( self::OPTION_FIRST_SCAN, \time() );
		}
	}

	/**
	 * Checks conditions and displays the admin notice if appropriate.
	 *
	 * @since 1.0.0
	 */
	public function maybe_display_notice(): void {
		// Do not show on the plugin's own page to avoid clutter.
		$screen = \get_current_screen();
		if ( $screen && 'toplevel_page_smart-link-checker' === $screen->id ) {
			return;
		}

		if ( \get_option( self::OPTION_DISMISSED ) ) {
			return;
		}

		$first_scan = \get_option( self::OPTION_FIRST_SCAN );
		if ( ! $first_scan ) {
			return;
		}

		// Wait 1 day after the first scan before showing the notice.
		if ( \time() < ( (int) $first_scan + DAY_IN_SECONDS ) ) {
			return;
		}

		$review_url = 'https://wordpress.org/support/plugin/smart-link-checker/reviews/#new-post';
		?>
		<div id="flc-review-notice" class="notice notice-info is-dismissible" style="position: relative;">
			<p>
				<strong><?php \esc_html_e( 'How do you like Smart Link Checker?', 'smart-link-checker' ); ?></strong><br>
				<?php \esc_html_e( 'We hope the plugin is helping you maintain a healthy site! If you have a moment, could you please leave us a 5-star rating on WordPress.org? It helps us a lot!', 'smart-link-checker' ); ?>
			</p>
			<p>
				<a href="<?php echo \esc_url( $review_url ); ?>" class="button button-primary" target="_blank" rel="noopener noreferrer">
					<?php \esc_html_e( 'Leave a Review', 'smart-link-checker' ); ?>
				</a>
				<button type="button" class="button button-link flc-dismiss-review" style="margin-left: 10px;">
					<?php \esc_html_e( 'Maybe later', 'smart-link-checker' ); ?>
				</button>
			</p>
		</div>
		<?php
	}

	/**
	 * Dismisses the notice via Ajax.
	 *
	 * @since 1.0.0
	 */
	public function ajax_dismiss_notice(): void {
		\check_ajax_referer( self::AJAX_ACTION, 'nonce' );
		\update_option( self::OPTION_DISMISSED, true );
		\wp_send_json_success();
	}

	/**
	 * Enqueues the JS to handle notice dismissal.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_assets(): void {
		$first_scan = \get_option( self::OPTION_FIRST_SCAN );
		if ( ! $first_scan || \get_option( self::OPTION_DISMISSED ) ) {
			return;
		}

		\wp_add_inline_script(
			'common',
			'jQuery(document).on("click", "#flc-review-notice .notice-dismiss, .flc-dismiss-review", function() {
				jQuery.post(ajaxurl, {
					action: "' . self::AJAX_ACTION . '",
					nonce: "' . \wp_create_nonce( self::AJAX_ACTION ) . '"
				});
				jQuery("#flc-review-notice").fadeOut();
			});'
		);
	}
}
