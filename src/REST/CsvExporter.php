<?php
/**
 * CSV exporter for link data.
 *
 * @package FlavorLinkChecker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace FlavorLinkChecker\REST;

defined( 'ABSPATH' ) || exit;

use FlavorLinkChecker\Database\InstancesRepository;
use FlavorLinkChecker\Database\QueryBuilder;
use FlavorLinkChecker\Models\Link;

/**
 * Generates and streams a CSV file from the link database.
 *
 * Extracted from LinksController to keep the controller focused on
 * request routing and permission checks.
 *
 * @since 1.0.0
 */
class CsvExporter {

	/**
	 * Generates CSV content for all links matching $args.
	 *
	 * Iterates page by page (100 rows per page) to avoid loading the entire
	 * result set into memory at once.
	 *
	 * @since 1.0.0
	 *
	 * @param QueryBuilder        $query_builder  Filtered query builder.
	 * @param InstancesRepository $instances_repo Instances CRUD repository.
	 * @param array<string,mixed> $args           Query args (status, link_type, orderby, etc.).
	 * @return string Raw CSV content (UTF-8, with headers).
	 */
	public function export( QueryBuilder $query_builder, InstancesRepository $instances_repo, array $args ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$csv = fopen( 'php://temp', 'r+' );

		fputcsv(
			$csv,
			array(
				__( 'ID', 'sentinel-link-checker' ),
				__( 'URL', 'sentinel-link-checker' ),
				__( 'Final URL', 'sentinel-link-checker' ),
				__( 'HTTP Status', 'sentinel-link-checker' ),
				__( 'Status', 'sentinel-link-checker' ),
				__( 'Type', 'sentinel-link-checker' ),
				__( 'Affiliate', 'sentinel-link-checker' ),
				__( 'Network', 'sentinel-link-checker' ),
				__( 'Cloaked', 'sentinel-link-checker' ),
				__( 'Redirect Count', 'sentinel-link-checker' ),
				__( 'Response Time (ms)', 'sentinel-link-checker' ),
				__( 'Instances', 'sentinel-link-checker' ),
				__( 'Last Checked', 'sentinel-link-checker' ),
				__( 'Last Error', 'sentinel-link-checker' ),
			)
		);

		$page = 1;
		do {
			$args['page'] = $page;
			$result       = $query_builder->query( $args );
			$items        = $result['items'];

			if ( empty( $items ) ) {
				break;
			}

			$link_ids        = array_map( fn( Link $link ) => $link->id, $items );
			$instance_counts = $instances_repo->count_by_link_ids( $link_ids );

			foreach ( $items as $link ) {
				$is_cloaked = $link->is_affiliate && ! $link->is_external;
				fputcsv(
					$csv,
					array(
						$link->id,
						$this->sanitize_csv_value( $link->url ),
						$this->sanitize_csv_value( $link->final_url ?? '' ),
						$link->http_status ?? '',
						$link->status_category->value,
						$link->is_external ? __( 'external', 'sentinel-link-checker' ) : __( 'internal', 'sentinel-link-checker' ),
						$link->is_affiliate ? __( 'yes', 'sentinel-link-checker' ) : __( 'no', 'sentinel-link-checker' ),
						$this->sanitize_csv_value( $link->affiliate_network ?? '' ),
						$is_cloaked ? __( 'yes', 'sentinel-link-checker' ) : __( 'no', 'sentinel-link-checker' ),
						$link->redirect_count,
						$link->response_time ?? '',
						$instance_counts[ $link->id ] ?? 0,
						$link->last_checked?->format( 'Y-m-d H:i:s' ) ?? '',
						$this->sanitize_csv_value( $link->last_error ?? '' ),
					)
				);
			}

			$item_count = count( $items );
			++$page;
		} while ( 100 === $item_count );

		rewind( $csv );
		$csv_data = stream_get_contents( $csv );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $csv );

		return (string) $csv_data;
	}

	/**
	 * Intercepts a REST response for CSV export to send raw CSV instead of JSON.
	 *
	 * Called via the `rest_pre_serve_request` filter. Returns true to signal
	 * that the request has been served, preventing JSON serialization.
	 *
	 * @since 1.0.0
	 *
	 * @param bool              $served  Whether the request has already been served.
	 * @param \WP_HTTP_Response $result  Response object.
	 * @param \WP_REST_Request  $request Current REST request.
	 * @param \WP_REST_Server   $server  REST server instance.
	 * @return bool True if this method served the response, original $served otherwise.
	 */
	public function serve_response( bool $served, \WP_HTTP_Response $result, \WP_REST_Request $request, \WP_REST_Server $server ): bool {
		if ( ! str_contains( $request->get_route(), '/links/export' ) ) {
			return $served;
		}

		$filename = \sanitize_file_name( 'links-export-' . \gmdate( 'Y-m-d' ) . '.csv' );
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		echo $result->get_data(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw CSV data.

		return true;
	}

	/**
	 * Sanitizes a string value for safe CSV output.
	 *
	 * Prevents CSV injection by prefixing cells starting with formula
	 * characters (=, +, -, @, tab, carriage return) with a tab character.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value Raw cell value.
	 * @return string Sanitized value safe for CSV.
	 */
	public function sanitize_csv_value( string $value ): string {
		if ( '' === $value ) {
			return $value;
		}

		$first_char = $value[0];

		if ( in_array( $first_char, array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			return "\t" . $value;
		}

		return $value;
	}
}
