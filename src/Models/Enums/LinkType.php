<?php
/**
 * Link type classification.
 *
 * @package FlavorLinkChecker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace FlavorLinkChecker\Models\Enums;

defined( 'ABSPATH' ) || exit;

/**
 * Classifies a link as internal or external.
 *
 * @since 1.0.0
 */
enum LinkType: string {

	case Internal = 'internal';
	case External = 'external';
}
