<?php
/**
 * Link status categories.
 *
 * @package FlavorLinkChecker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace FlavorLinkChecker\Models\Enums;

defined( 'ABSPATH' ) || exit;

/**
 * Represents the verification status of a link.
 *
 * @since 1.0.0
 */
enum LinkStatus: string {

	case Pending  = 'pending';
	case Ok       = 'ok';
	case Redirect = 'redirect';
	case Broken   = 'broken';
	case Error    = 'error';
	case Timeout  = 'timeout';
	case Skipped  = 'skipped';

	/**
	 * Maps an HTTP status code to a LinkStatus.
	 *
	 * @since 1.0.0
	 *
	 * @param int $code HTTP status code.
	 * @return self
	 */
	public static function from_http_status( int $code ): self {
		return match ( true ) {
			200 === $code                        => self::Ok,
			in_array( $code, array( 301, 302, 303, 307, 308 ), true ) => self::Redirect,
			in_array( $code, array( 404, 410 ), true ) => self::Broken,
			$code >= 500                         => self::Error,
			default                              => self::Error,
		};
	}

	/**
	 * Returns a human-readable label for this status.
	 *
	 * @since 1.0.0
	 *
	 * @return string Translated label.
	 */
	public function label(): string {
		return match ( $this ) {
			self::Pending  => __( 'Pending', 'smart-link-checker' ),
			self::Ok       => __( 'OK', 'smart-link-checker' ),
			self::Redirect => __( 'Redirect', 'smart-link-checker' ),
			self::Broken   => __( 'Broken', 'smart-link-checker' ),
			self::Error    => __( 'Error', 'smart-link-checker' ),
			self::Timeout  => __( 'Timeout', 'smart-link-checker' ),
			self::Skipped  => __( 'Skipped', 'smart-link-checker' ),
		};
	}
}
