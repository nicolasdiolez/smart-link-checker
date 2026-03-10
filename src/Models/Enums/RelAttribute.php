<?php
/**
 * Link rel attribute values.
 *
 * @package FlavorLinkChecker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace FlavorLinkChecker\Models\Enums;

defined( 'ABSPATH' ) || exit;

/**
 * Represents known rel attribute values for links.
 *
 * @since 1.0.0
 */
enum RelAttribute: string {

	case Nofollow   = 'nofollow';
	case Sponsored  = 'sponsored';
	case Ugc        = 'ugc';
	case Noopener   = 'noopener';
	case Noreferrer = 'noreferrer';

	/**
	 * Parses a space-separated rel attribute string into an array of RelAttribute values.
	 *
	 * Unknown tokens are silently ignored.
	 *
	 * @since 1.0.0
	 *
	 * @param string $rel The rel attribute string (e.g. "nofollow sponsored noopener").
	 * @return self[] Array of matched RelAttribute enum values.
	 */
	public static function parse_rel_string( string $rel ): array {
		if ( '' === trim( $rel ) ) {
			return array();
		}

		$tokens  = preg_split( '/\s+/', strtolower( trim( $rel ) ) );
		$results = array();

		foreach ( $tokens as $token ) {
			$case = self::tryFrom( $token );
			if ( null !== $case ) {
				$results[] = $case;
			}
		}

		return $results;
	}
}
