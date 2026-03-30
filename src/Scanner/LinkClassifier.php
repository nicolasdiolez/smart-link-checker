<?php
/**
 * Link classification engine.
 *
 * @package FlavorLinkChecker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace FlavorLinkChecker\Scanner;

defined( 'ABSPATH' ) || exit;

use FlavorLinkChecker\Models\Enums\LinkType;
use FlavorLinkChecker\Models\Enums\RelAttribute;

/**
 * Classifies links by type (internal/external), affiliate status, and rel attributes.
 *
 * @since 1.0.0
 */
class LinkClassifier {

	/**
	 * Known affiliate network domains grouped by network name.
	 *
	 * @since 1.0.0
	 * @var array<string, string[]>
	 */
	private const AFFILIATE_DOMAINS = array(
		'amazon'             => array( 'amzn.to', 'amazon.com', 'amazon.fr', 'amazon.co.uk', 'amazon.de', 'amazon.ca', 'amazon.it', 'amazon.es', 'amazon.co.jp', 'amazon.com.br', 'amazon.com.au', 'amazon.nl', 'amazon.pl', 'amazon.se', 'amazon.sg', 'amazon.com.mx', 'amazon.in' ),
		'shareasale'         => array( 'shareasale.com', 'shareasale-analytics.com' ),
		'cj'                 => array( 'anrdoezrs.net', 'dpbolvw.net', 'jdoqocy.com', 'tkqlhce.com', 'kqzyfj.com', 'commission-junction.com' ),
		'impact'             => array( 'sjv.io', '7eer.net', 'evyy.net', 'pxf.io', 'r.imp.i336560.net' ),
		'rakuten'            => array( 'click.linksynergy.com', 'linksynergy.com' ),
		'clickbank'          => array( 'hop.clickbank.net' ),
		'partnerstack'       => array( 'partnerstack.com' ),
		'awin'               => array( 'awin1.com', 'zenaps.com' ),
		'tradedoubler'       => array( 'tradedoubler.com', 'clkde.tradedoubler.com', 'clkuk.tradedoubler.com' ),
		'webgains'           => array( 'track.webgains.com' ),
		'commission_factory' => array( 't.cfjump.com' ),
		'flexoffers'         => array( 'track.flexlinks.com' ),
		'skimlinks'          => array( 'go.skimresources.com', 'go.redirectingat.com' ),
		'sovrn'              => array( 'redirect.viglink.com', 'sovrn.co' ),
	);

	/**
	 * URL path patterns commonly used for affiliate/cloaked links.
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	private const AFFILIATE_PATH_PATTERNS = array(
		'/go/',
		'/refer/',
		'/recommends/',
		'/out/',
		'/aff/',
		'/partner/',
		'/affiliate/',
		'/ref/',
		'/click/',
		'/redirect/',
		'/link/',
		'/grab/',
		'/clk/',
		'/visit/',
		'/suggest/',
		'/deal/',
		'/offer/',
	);

	/**
	 * Query parameter names commonly used for affiliate tracking.
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	private const AFFILIATE_PARAMS = array( 'tag', 'ref', 'aff_id', 'affiliate_id', 'partner', 'aff_sub', 'aff_sub2', 'clickid', 'subid', 'pubid', 'tracking_id' );

	/**
	 * Cached site host for internal/external comparison.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private string $site_host;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param string $site_url Optional site URL for testability. Defaults to home_url().
	 */
	public function __construct(
		private readonly string $site_url = '',
	) {
		$url             = '' !== $this->site_url ? $this->site_url : home_url();
		$parsed          = wp_parse_url( $url );
		$this->site_host = strtolower( $parsed['host'] ?? '' );
	}

	/**
	 * Determines whether a URL is internal or external.
	 *
	 * Relative URLs (starting with /) are considered internal.
	 * Protocol-relative URLs (//) are classified by host comparison.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url The URL to classify.
	 * @return LinkType Internal or External.
	 */
	public function classify_type( string $url ): LinkType {
		$url = trim( $url );

		// Relative URLs are internal.
		if ( str_starts_with( $url, '/' ) && ! str_starts_with( $url, '//' ) ) {
			return LinkType::Internal;
		}

		$parsed = wp_parse_url( $url );
		if ( ! isset( $parsed['host'] ) ) {
			return LinkType::Internal;
		}

		$host = strtolower( $parsed['host'] );

		/**
		 * Filters the list of hosts considered as internal.
		 *
		 * Useful for multisite or domain alias configurations.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $hosts Array of internal hostnames.
		 */
		$internal_hosts = apply_filters( 'slkc/classifier/site_hosts', array( $this->site_host ) );

		foreach ( $internal_hosts as $internal_host ) {
			if ( $host === $internal_host || str_ends_with( $host, '.' . $internal_host ) ) {
				return LinkType::Internal;
			}
		}

		return LinkType::External;
	}

	/**
	 * Detects whether a URL is an affiliate link and identifies the network.
	 *
	 * Checks four layers in order:
	 * 1. Domain match against known affiliate networks
	 * 2. Path patterns commonly used for cloaked/affiliate links
	 * 3. Query parameters used for affiliate tracking
	 * 4. rel="sponsored" attribute as a fallback hint
	 *
	 * @since 1.0.0
	 * @since 1.0.0 Added $rel parameter for sponsored attribute detection.
	 *
	 * @param string $url The URL to check.
	 * @param string $rel Optional rel attribute value from the link.
	 * @return array{is_affiliate: bool, network: string|null}
	 */
	public function detect_affiliate( string $url, string $rel = '' ): array {
		$parsed = wp_parse_url( $url );
		$host   = strtolower( $parsed['host'] ?? '' );
		$path   = $parsed['path'] ?? '';
		$query  = $parsed['query'] ?? '';

		/**
		 * Filters the affiliate detection patterns.
		 *
		 * @since 1.0.0
		 *
		 * @param array{domains: array<string, string[]>, paths: string[], params: string[]} $patterns
		 */
		$patterns = apply_filters(
			'slkc/classifier/affiliate_patterns',
			array(
				'domains' => self::AFFILIATE_DOMAINS,
				'paths'   => self::AFFILIATE_PATH_PATTERNS,
				'params'  => self::AFFILIATE_PARAMS,
			)
		);

		// 1. Check domain.
		if ( '' !== $host ) {
			foreach ( $patterns['domains'] as $network => $domains ) {
				foreach ( $domains as $domain ) {
					if ( $this->matches_domain( $host, $domain ) ) {
						return array(
							'is_affiliate' => true,
							'network'      => $network,
						);
					}
				}
			}
		}

		// 2. Check path patterns.
		if ( '' !== $path && $this->has_affiliate_path( $path, $patterns['paths'] ) ) {
			return array(
				'is_affiliate' => true,
				'network'      => null,
			);
		}

		// 3. Check query parameters.
		if ( '' !== $query && $this->has_affiliate_params( $query, $patterns['params'] ) ) {
			return array(
				'is_affiliate' => true,
				'network'      => null,
			);
		}

		// 4. Check rel="sponsored" as affiliate hint.
		if ( '' !== $rel && str_contains( strtolower( $rel ), 'sponsored' ) ) {
			return array(
				'is_affiliate' => true,
				'network'      => null,
			);
		}

		return array(
			'is_affiliate' => false,
			'network'      => null,
		);
	}

	/**
	 * Parses the rel attribute string into structured boolean flags.
	 *
	 * @since 1.0.0
	 *
	 * @param string $rel The raw rel attribute string.
	 * @return array{rel_nofollow: bool, rel_sponsored: bool, rel_ugc: bool, is_dofollow: bool}
	 */
	public function classify_rel( string $rel ): array {
		$attributes = RelAttribute::parse_rel_string( $rel );

		$nofollow  = in_array( RelAttribute::Nofollow, $attributes, true );
		$sponsored = in_array( RelAttribute::Sponsored, $attributes, true );
		$ugc       = in_array( RelAttribute::Ugc, $attributes, true );

		return array(
			'rel_nofollow'  => $nofollow,
			'rel_sponsored' => $sponsored,
			'rel_ugc'       => $ugc,
			'is_dofollow'   => ! $nofollow && ! $sponsored && ! $ugc,
		);
	}

	/**
	 * Checks if a host matches a domain pattern, handling subdomains.
	 *
	 * @since 1.0.0
	 *
	 * @param string $host    The host to check (e.g. "www.amazon.fr").
	 * @param string $pattern The domain pattern (e.g. "amazon.fr").
	 * @return bool
	 */
	private function matches_domain( string $host, string $pattern ): bool {
		return $host === $pattern || str_ends_with( $host, '.' . $pattern );
	}

	/**
	 * Checks if a URL path contains affiliate patterns.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $path     The URL path.
	 * @param string[] $patterns Path patterns to check.
	 * @return bool
	 */
	private function has_affiliate_path( string $path, array $patterns ): bool {
		foreach ( $patterns as $pattern ) {
			if ( str_contains( $path, $pattern ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks if a query string contains affiliate tracking parameters.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $query  The query string (without leading ?).
	 * @param string[] $params Parameter names to check.
	 * @return bool
	 */
	private function has_affiliate_params( string $query, array $params ): bool {
		parse_str( $query, $query_vars );

		foreach ( $params as $param ) {
			if ( isset( $query_vars[ $param ] ) ) {
				return true;
			}
		}

		return false;
	}
}
