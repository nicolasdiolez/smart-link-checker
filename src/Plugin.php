<?php
/**
 * Main plugin bootstrap class.
 *
 * @package FlavorLinkChecker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace FlavorLinkChecker;

defined( 'ABSPATH' ) || exit;

use FlavorLinkChecker\Admin\AdminPage;
use FlavorLinkChecker\Database\InstancesRepository;
use FlavorLinkChecker\Database\LinksRepository;
use FlavorLinkChecker\Database\QueryBuilder;
use FlavorLinkChecker\Queue\BatchOrchestrator;
use FlavorLinkChecker\Queue\CheckJob;
use FlavorLinkChecker\Queue\ScanJob;
use FlavorLinkChecker\Queue\SchedulerBootstrap;
use FlavorLinkChecker\REST\LinksController;
use FlavorLinkChecker\REST\ScanController;
use FlavorLinkChecker\REST\SettingsController;
use FlavorLinkChecker\Scanner\BlockParser;
use FlavorLinkChecker\Scanner\ContentParser;
use FlavorLinkChecker\Scanner\HttpChecker;
use FlavorLinkChecker\Scanner\LinkClassifier;
use FlavorLinkChecker\Scanner\LinkExtractor;

/**
 * Singleton that boots the plugin and registers all hooks.
 *
 * @since 1.0.0
 */
class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Returns the singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor to enforce singleton.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {}

	/**
	 * Registers all plugin hooks.
	 *
	 * @since 1.0.0
	 */
	public function register(): void {
		add_action( 'init', $this->load_textdomain( ... ) );

		// Queue system must register on ALL requests (not just admin)
		// because Action Scheduler processes actions via WP-Cron/frontend.
		$this->register_queue();

		// REST API must register on ALL requests for wp-json availability.
		$this->register_rest_api();

		if ( is_admin() ) {
			$admin_page = new AdminPage();
			$admin_page->register();
		}
	}

	/**
	 * Registers REST API controllers.
	 *
	 * @since 1.0.0
	 */
	private function register_rest_api(): void {
		global $wpdb;

		$links_repo     = new LinksRepository( $wpdb );
		$instances_repo = new InstancesRepository( $wpdb );
		$query_builder  = new QueryBuilder( $wpdb );
		$orchestrator   = new BatchOrchestrator( $links_repo, $instances_repo );

		$links_ctrl    = new LinksController( $query_builder, $links_repo, $instances_repo );
		$scan_ctrl     = new ScanController( $orchestrator );
		$settings_ctrl = new SettingsController();

		add_action( 'rest_api_init', $links_ctrl->register_routes( ... ) );
		add_action( 'rest_api_init', $scan_ctrl->register_routes( ... ) );
		add_action( 'rest_api_init', $settings_ctrl->register_routes( ... ) );
	}

	/**
	 * Registers the background processing queue system.
	 *
	 * Wires up the scanner, checker, repositories, and Action Scheduler jobs.
	 *
	 * @since 1.0.0
	 */
	private function register_queue(): void {
		if ( ! SchedulerBootstrap::is_available() ) {
			return;
		}

		global $wpdb;

		// Repositories.
		$links_repo     = new LinksRepository( $wpdb );
		$instances_repo = new InstancesRepository( $wpdb );

		// Scanner pipeline.
		$content_parser = new ContentParser();
		$block_parser   = new BlockParser( $content_parser );
		$classifier     = new LinkClassifier();
		$extractor      = new LinkExtractor( $content_parser, $block_parser, $classifier );

		// HTTP checker.
		$settings     = get_option( 'flc_settings', array() );
		$timeout      = (int) ( $settings['check_timeout'] ?? 15 );
		$http_checker = new HttpChecker( $timeout );

		// Jobs.
		$scan_job  = new ScanJob( $extractor, $links_repo, $instances_repo );
		$check_job = new CheckJob( $http_checker, $links_repo );

		// Hook jobs to Action Scheduler actions.
		add_action( SchedulerBootstrap::SCAN_BATCH_HOOK, array( $scan_job, 'process_batch' ) );
		add_action( SchedulerBootstrap::CHECK_BATCH_HOOK, array( $check_job, 'process_batch' ) );

		// Orchestrator for daily recheck and batch tracking.
		$orchestrator = new BatchOrchestrator( $links_repo, $instances_repo );
		add_action( SchedulerBootstrap::RECHECK_DAILY_HOOK, array( $orchestrator, 'recheck_stale_links' ) );

		// Batch tracking hooks.
		add_action( 'flc/scan/batch_complete', array( $orchestrator, 'remove_scan_batch' ) );
		add_action( 'flc/check/batch_complete', array( $orchestrator, 'remove_check_batch' ) );
		add_action( 'flc/check/batch_split', array( $orchestrator, 'handle_check_batch_split' ), 10, 2 );

		// Orphan cleanup.
		add_action(
			SchedulerBootstrap::CLEANUP_HOOK,
			static function () use ( $links_repo ): void {
				$links_repo->cleanup_orphans();
			}
		);
	}

	/**
	 * Loads the plugin text domain for translations.
	 *
	 * @since 1.0.0
	 */
	private function load_textdomain(): void {
		load_plugin_textdomain(
			'flavor-link-checker',
			false,
			dirname( plugin_basename( FLC_PLUGIN_FILE ) ) . '/languages'
		);
	}
}
