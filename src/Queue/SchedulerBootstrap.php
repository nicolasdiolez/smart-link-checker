<?php
/**
 * Action Scheduler bootstrap and helpers.
 *
 * @package FlavorLinkChecker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace FlavorLinkChecker\Queue;

defined( 'ABSPATH' ) || exit;

/**
 * Registers Action Scheduler hooks and provides helpers
 * for enqueueing and managing background actions.
 *
 * @since 1.0.0
 */
class SchedulerBootstrap {

	/**
	 * Action Scheduler group name for all plugin actions.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public const GROUP = 'flavor-link-checker';

	/**
	 * Action hook for processing a batch of posts (link extraction).
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public const SCAN_BATCH_HOOK = 'flc/scan/process_batch';

	/**
	 * Action hook for processing a batch of links (HTTP checks).
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public const CHECK_BATCH_HOOK = 'flc/check/process_batch';

	/**
	 * Action hook for the daily recheck of stale links.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public const RECHECK_DAILY_HOOK = 'flc/recheck/daily';

	/**
	 * Action hook for periodic maintenance (orphan cleanup).
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public const CLEANUP_HOOK = 'flc/maintenance/cleanup';

	/**
	 * Checks if Action Scheduler is available.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public static function is_available(): bool {
		return function_exists( 'as_enqueue_async_action' );
	}

	/**
	 * Enqueues an async scan batch action.
	 *
	 * @since 1.0.0
	 *
	 * @param string $batch_id Batch identifier (references a transient with post IDs).
	 */
	public static function enqueue_scan_batch( string $batch_id ): int {
		if ( ! self::is_available() ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[FlavorLinkChecker] enqueue_scan_batch: Action Scheduler not available.' );
			}
			return 0;
		}

		$action_id = as_enqueue_async_action(
			self::SCAN_BATCH_HOOK,
			array( $batch_id ),
			self::GROUP
		);

		if ( 0 === $action_id ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( "[FlavorLinkChecker] enqueue_scan_batch: as_enqueue_async_action returned 0 for batch {$batch_id}." );
			}
		}

		return $action_id;
	}

	/**
	 * Enqueues an async check batch action.
	 *
	 * @since 1.0.0
	 *
	 * @param int[] $link_ids Array of link IDs to check.
	 */
	public static function enqueue_check_batch( array $link_ids ): int {
		if ( ! self::is_available() ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[FlavorLinkChecker] enqueue_check_batch: Action Scheduler not available.' );
			}
			return 0;
		}

		$action_id = as_enqueue_async_action(
			self::CHECK_BATCH_HOOK,
			array( $link_ids ),
			self::GROUP
		);

		if ( 0 === $action_id ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[FlavorLinkChecker] enqueue_check_batch: as_enqueue_async_action returned 0.' );
			}
		}

		return $action_id;
	}

	/**
	 * Schedules the daily recheck recurring action if not already scheduled.
	 *
	 * @since 1.0.0
	 */
	public static function schedule_daily_recheck(): void {
		if ( ! self::is_available() ) {
			return;
		}

		if ( false === as_next_scheduled_action( self::RECHECK_DAILY_HOOK, array(), self::GROUP ) ) {
			as_schedule_recurring_action(
				time(),
				DAY_IN_SECONDS,
				self::RECHECK_DAILY_HOOK,
				array(),
				self::GROUP
			);
		}
	}

	/**
	 * Cancels all pending actions for this plugin.
	 *
	 * @since 1.0.0
	 */
	public static function cancel_all(): void {
		if ( ! self::is_available() ) {
			return;
		}

		as_unschedule_all_actions( '', array(), self::GROUP );
	}

	/**
	 * Returns the number of pending actions for this plugin.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	/**
	 * Returns diagnostic information about Action Scheduler health.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public static function get_diagnostics(): array {
		global $wpdb;

		$as_available   = self::is_available();
		$as_initialized = class_exists( 'ActionScheduler', false ) && \ActionScheduler::is_initialized();

		$as_version = null;
		if ( class_exists( 'ActionScheduler_Versions', false ) ) {
			$as_version = \ActionScheduler_Versions::instance()->latest_version();
		}

		$tables_exist = (bool) $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$wpdb->prefix . 'actionscheduler_actions'
			)
		);

		$pending_count = 0;
		$failed_count  = 0;
		if ( $as_available && $as_initialized ) {
			$pending_count = self::get_pending_count();

			$failed_actions = as_get_scheduled_actions(
				array(
					'group'    => self::GROUP,
					'status'   => \ActionScheduler_Store::STATUS_FAILED,
					'per_page' => 0,
				),
				'ids'
			);
			$failed_count   = count( $failed_actions );
		}

		return array(
			'as_available'      => $as_available,
			'as_initialized'    => $as_initialized,
			'as_version'        => $as_version,
			'tables_exist'      => $tables_exist,
			'pending_count'     => $pending_count,
			'failed_count'      => $failed_count,
			'wp_cron_enabled'   => ! ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ),
			'as_cron_scheduled' => (bool) wp_next_scheduled( 'action_scheduler_run_queue' ),
		);
	}

	/**
	 * Manually triggers the Action Scheduler queue runner.
	 *
	 * Works around environments where WP-Cron loopback fails (e.g. LocalWP)
	 * by processing pending actions synchronously during status polls.
	 *
	 * @since 1.0.0
	 */
	public static function maybe_run_queue(): void {
		if ( ! self::is_available() ) {
			return;
		}

		if ( 0 === self::get_pending_count() ) {
			return;
		}

		if ( ! class_exists( 'ActionScheduler', false ) || ! \ActionScheduler::is_initialized() ) {
			return;
		}

		// Prevent re-entrancy if already inside a queue run.
		if ( doing_action( 'action_scheduler_run_queue' ) ) {
			return;
		}

		\ActionScheduler_QueueRunner::instance()->run( 'FLC Status Poll' );
	}

	/**
	 * Returns the number of pending actions for this plugin.
	 *
	 * @since 1.0.0
	 *
	 * @return int Number of pending actions.
	 */
	public static function get_pending_count(): int {
		if ( ! self::is_available() || ! function_exists( 'as_get_scheduled_actions' ) ) {
			return 0;
		}

		$actions = as_get_scheduled_actions(
			array(
				'group'    => self::GROUP,
				'status'   => \ActionScheduler_Store::STATUS_PENDING,
				'per_page' => 0,
			),
			'ids'
		);

		return count( $actions );
	}
}
