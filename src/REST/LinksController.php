<?php
/**
 * REST controller for link endpoints.
 *
 * @package FlavorLinkChecker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace FlavorLinkChecker\REST;

defined( 'ABSPATH' ) || exit;

use FlavorLinkChecker\Database\InstancesRepository;
use FlavorLinkChecker\Database\LinksRepository;
use FlavorLinkChecker\Database\QueryBuilder;
use FlavorLinkChecker\Models\Enums\LinkStatus;
use FlavorLinkChecker\Models\Link;
use FlavorLinkChecker\Models\LinkInstance;
use FlavorLinkChecker\Queue\SchedulerBootstrap;
use FlavorLinkChecker\Scanner\LinkHtmlEditor;

/**
 * Handles REST API endpoints for links.
 *
 * @since 1.0.0
 */
class LinksController extends \WP_REST_Controller {

	/**
	 * REST namespace.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $namespace = 'sentinel-link-checker/v1';

	/**
	 * REST base route.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $rest_base = 'links';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param QueryBuilder        $query_builder  Filtered query builder.
	 * @param LinksRepository     $links_repo     Links CRUD repository.
	 * @param InstancesRepository $instances_repo Instances CRUD repository.
	 * @param LinkHtmlEditor      $html_editor    HTML link editor.
	 * @param CsvExporter         $csv_exporter   CSV export handler.
	 */
	public function __construct(
		private readonly QueryBuilder $query_builder,
		private readonly LinksRepository $links_repo,
		private readonly InstancesRepository $instances_repo,
		private readonly LinkHtmlEditor $html_editor,
		private readonly CsvExporter $csv_exporter,
	) {}

	/**
	 * Registers REST routes.
	 *
	 * @since 1.0.0
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => $this->get_items( ... ),
					'permission_callback' => $this->check_permissions( ... ),
					'args'                => $this->get_collection_params(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => $this->get_item( ... ),
					'permission_callback' => $this->check_permissions( ... ),
					'args'                => array(
						'id' => array(
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => $this->update_item( ... ),
					'permission_callback' => $this->check_write_permissions( ... ),
					'args'                => $this->get_update_params(),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => $this->delete_item( ... ),
					'permission_callback' => $this->check_write_permissions( ... ),
					'args'                => array(
						'id' => array(
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/bulk',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => $this->bulk_action( ... ),
					'permission_callback' => $this->check_write_permissions( ... ),
					'args'                => $this->get_bulk_params(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/recheck',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => $this->recheck_item( ... ),
					'permission_callback' => $this->check_permissions( ... ),
					'args'                => array(
						'id' => array(
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/stats',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => $this->get_stats( ... ),
					'permission_callback' => $this->check_permissions( ... ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/export',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => $this->export_csv( ... ),
					'permission_callback' => $this->check_permissions( ... ),
					'args'                => $this->get_collection_params(),
				),
			)
		);

		add_filter( 'rest_pre_serve_request', $this->serve_csv_response( ... ), 10, 4 );
	}

	/**
	 * Permission check for all endpoints.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return bool
	 */
	public function check_permissions( \WP_REST_Request $request ): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Permission check for write endpoints that modify post content.
	 *
	 * Requires both admin access and the ability to edit posts.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return bool
	 */
	public function check_write_permissions( \WP_REST_Request $request ): bool {
		return current_user_can( 'manage_options' ) && current_user_can( 'edit_posts' );
	}

	/**
	 * Retrieves a paginated, filtered list of links.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return \WP_REST_Response
	 */
	public function get_items( $request ): \WP_REST_Response {
		$args = array(
			'status'            => $request->get_param( 'status' ),
			'link_type'         => $request->get_param( 'link_type' ),
			'is_affiliate'      => $request->has_param( 'is_affiliate' ) ? $request->get_param( 'is_affiliate' ) : null,
			'is_cloaked'        => $request->has_param( 'is_cloaked' ) ? $request->get_param( 'is_cloaked' ) : null,
			'affiliate_network' => $request->get_param( 'affiliate_network' ),
			'rel'               => $request->get_param( 'rel' ),
			'search'            => $request->get_param( 'search' ),
			'post_id'           => $request->get_param( 'post_id' ),
			'orderby'           => $request->get_param( 'orderby' ),
			'order'             => $request->get_param( 'order' ),
			'page'              => $request->get_param( 'page' ),
			'per_page'          => $request->get_param( 'per_page' ),
		);

		// Remove null values so QueryBuilder uses defaults.
		$args = array_filter( $args, fn( $v ) => null !== $v );

		$result      = $this->query_builder->query( $args );
		$total       = $result['total'];
		$per_page    = min( max( (int) ( $args['per_page'] ?? 25 ), 1 ), 100 );
		$total_pages = (int) ceil( $total / $per_page );

		$data = array_map( fn( Link $link ) => $this->prepare_link_for_response( $link ), $result['items'] );

		$response = new \WP_REST_Response( $data, 200 );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $total_pages );

		return $response;
	}

	/**
	 * Retrieves a single link with its instances.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_item( $request ): \WP_REST_Response|\WP_Error {
		$link = $this->links_repo->find( (int) $request->get_param( 'id' ) );

		if ( null === $link ) {
			return new \WP_Error(
				'slkc_link_not_found',
				__( 'Link not found.', 'sentinel-link-checker' ),
				array( 'status' => 404 )
			);
		}

		$instances = $this->instances_repo->find_by_link( $link->id );

		$data              = $this->prepare_link_for_response( $link );
		$data['instances'] = array_map( fn( LinkInstance $inst ) => $this->prepare_instance_for_response( $inst ), $instances );

		return new \WP_REST_Response( $data, 200 );
	}

	/**
	 * Updates a link's URL and/or rel attributes in the source post content.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_item( $request ): \WP_REST_Response|\WP_Error {
		$link = $this->links_repo->find( (int) $request->get_param( 'id' ) );

		if ( null === $link ) {
			return new \WP_Error(
				'slkc_link_not_found',
				__( 'Link not found.', 'sentinel-link-checker' ),
				array( 'status' => 404 )
			);
		}

		$new_url = $request->get_param( 'url' );
		$new_rel = $request->get_param( 'rel' );

		if ( null === $new_url && null === $new_rel ) {
			return new \WP_Error(
				'slkc_nothing_to_update',
				__( 'Provide at least url or rel to update.', 'sentinel-link-checker' ),
				array( 'status' => 400 )
			);
		}

		$instances     = $this->instances_repo->find_by_link( $link->id );
		$updated_posts = 0;

		foreach ( $instances as $instance ) {
			$post = get_post( $instance->post_id );
			if ( null === $post ) {
				continue;
			}

			$updated_content = $this->html_editor->replace_link_in_html(
				$post->post_content,
				$link->url,
				$new_url,
				$new_rel
			);

			if ( $updated_content !== $post->post_content ) {
				$this->html_editor->update_post_content_silently( $post->ID, $updated_content );
				++$updated_posts;
			}
		}

		// Update the link record in DB if URL changed.
		if ( null !== $new_url && $new_url !== $link->url ) {
			global $wpdb;
			$links_table = $wpdb->prefix . 'slkc_links';
			$new_hash    = hash( 'sha256', $new_url );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE %i SET url = %s, url_hash = %s, status_category = 'pending', http_status = NULL, last_checked = NULL WHERE id = %d",
					$links_table,
					$new_url,
					$new_hash,
					$link->id
				)
			);
		}

		// Refresh link data.
		$updated_link          = $this->links_repo->find( $link->id );
		$data                  = $this->prepare_link_for_response( $updated_link ?? $link );
		$data['updated_posts'] = $updated_posts;

		return new \WP_REST_Response( $data, 200 );
	}

	/**
	 * Deletes a link and removes it from post content.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_item( $request ): \WP_REST_Response|\WP_Error {
		$id   = (int) $request->get_param( 'id' );
		$link = $this->links_repo->find( $id );

		if ( null === $link ) {
			return new \WP_Error(
				'slkc_link_not_found',
				__( 'Link not found.', 'sentinel-link-checker' ),
				array( 'status' => 404 )
			);
		}

		$this->perform_link_deletion( $link );

		return new \WP_REST_Response(
			array(
				'deleted' => true,
				'id'      => $id,
			),
			200
		);
	}

	/**
	 * Performs a bulk action on multiple links.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function bulk_action( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$action = $request->get_param( 'action' );
		$ids    = $request->get_param( 'ids' );

		$results = array(
			'success' => 0,
			'failed'  => 0,
		);

		foreach ( $ids as $id ) {
			$id = \absint( $id );

			if ( 'recheck' === $action ) {
				$link = $this->links_repo->find( $id );
				if ( null !== $link ) {
					SchedulerBootstrap::enqueue_check_batch( array( $id ) );
					++$results['success'];
				} else {
					++$results['failed'];
				}
			} elseif ( 'delete' === $action ) {
				$link = $this->links_repo->find( $id );
				if ( null !== $link ) {
					$this->perform_link_deletion( $link );
					++$results['success'];
				} else {
					++$results['failed'];
				}
			}
		}

		$results['action'] = $action;

		return new \WP_REST_Response( $results, 200 );
	}

	/**
	 * Re-checks a single link by enqueueing an HTTP verification.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function recheck_item( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id   = (int) $request->get_param( 'id' );
		$link = $this->links_repo->find( $id );

		if ( null === $link ) {
			return new \WP_Error(
				'slkc_link_not_found',
				__( 'Link not found.', 'sentinel-link-checker' ),
				array( 'status' => 404 )
			);
		}

		// Reset status to pending before enqueuing.
		$this->links_repo->update_check_result(
			$id,
			0,
			LinkStatus::Pending,
			null,
			0,
			0,
			null,
			null
		);

		SchedulerBootstrap::enqueue_check_batch( array( $id ) );

		return new \WP_REST_Response(
			array(
				'id'      => $id,
				'status'  => 'pending',
				'message' => __( 'Link queued for re-checking.', 'sentinel-link-checker' ),
			),
			200
		);
	}

	/**
	 * Returns aggregated link statistics by status, category, and network.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return \WP_REST_Response
	 */
	public function get_stats( \WP_REST_Request $request ): \WP_REST_Response {
		$by_status   = $this->links_repo->count_by_status();
		$by_category = $this->links_repo->get_category_stats();
		$by_network  = $this->links_repo->count_by_network();

		return new \WP_REST_Response(
			array(
				'byStatus'   => $by_status,
				'byCategory' => $by_category,
				'byNetwork'  => $by_network,
			),
			200
		);
	}

	/**
	 * Exports all matching links as CSV.
	 *
	 * Delegates to CsvExporter to keep the controller focused on routing.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return \WP_REST_Response
	 */
	public function export_csv( \WP_REST_Request $request ): \WP_REST_Response {
		$args = array(
			'status'            => $request->get_param( 'status' ),
			'link_type'         => $request->get_param( 'link_type' ),
			'is_affiliate'      => $request->has_param( 'is_affiliate' ) ? $request->get_param( 'is_affiliate' ) : null,
			'is_cloaked'        => $request->has_param( 'is_cloaked' ) ? $request->get_param( 'is_cloaked' ) : null,
			'affiliate_network' => $request->get_param( 'affiliate_network' ),
			'rel'               => $request->get_param( 'rel' ),
			'search'            => $request->get_param( 'search' ),
			'post_id'           => $request->get_param( 'post_id' ),
			'orderby'           => $request->get_param( 'orderby' ),
			'order'             => $request->get_param( 'order' ),
			'per_page'          => 100,
		);

		$args     = array_filter( $args, fn( $v ) => null !== $v );
		$csv_data = $this->csv_exporter->export( $this->query_builder, $this->instances_repo, $args );

		$response = new \WP_REST_Response( $csv_data, 200 );
		$response->header( 'X-SLKC-Export', 'csv' );

		return $response;
	}

	/**
	 * Intercepts REST response for CSV export to send raw CSV instead of JSON.
	 *
	 * Delegates to CsvExporter::serve_response().
	 *
	 * @since 1.0.0
	 *
	 * @param bool              $served  Whether the request has been served.
	 * @param \WP_HTTP_Response $result  Response object.
	 * @param \WP_REST_Request  $request Request object.
	 * @param \WP_REST_Server   $server  REST server instance.
	 * @return bool
	 */
	public function serve_csv_response( bool $served, \WP_HTTP_Response $result, \WP_REST_Request $request, \WP_REST_Server $server ): bool {
		return $this->csv_exporter->serve_response( $served, $result, $request, $server );
	}

	/**
	 * Returns the query params for the collection endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_collection_params(): array {
		return array(
			'page'              => array(
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
			),
			'per_page'          => array(
				'type'              => 'integer',
				'default'           => 25,
				'minimum'           => 1,
				'maximum'           => 100,
				'sanitize_callback' => 'absint',
			),
			'status'            => array(
				'type'              => 'string',
				'enum'              => array( 'ok', 'redirect', 'broken', 'error', 'timeout', 'pending', 'skipped' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'link_type'         => array(
				'type'              => 'string',
				'enum'              => array( 'internal', 'external' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'is_affiliate'      => array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
			),
			'is_cloaked'        => array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
			),
			'affiliate_network' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'rel'               => array(
				'type'              => 'string',
				'enum'              => array( 'nofollow', 'sponsored', 'ugc', 'dofollow' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'search'            => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'post_id'           => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'orderby'           => array(
				'type'              => 'string',
				'default'           => 'created_at',
				'enum'              => array( 'url', 'http_status', 'last_checked', 'created_at' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'order'             => array(
				'type'              => 'string',
				'default'           => 'desc',
				'enum'              => array( 'asc', 'desc' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Prepares a Link DTO for the REST response.
	 *
	 * @since 1.0.0
	 *
	 * @param Link $link Link DTO.
	 * @return array<string, mixed>
	 */
	private function prepare_link_for_response( Link $link ): array {
		return array(
			'id'               => $link->id,
			'url'              => $link->url,
			'urlHash'          => $link->url_hash,
			'finalUrl'         => $link->final_url,
			'httpStatus'       => $link->http_status,
			'statusCategory'   => $link->status_category->value,
			'isExternal'       => $link->is_external,
			'isAffiliate'      => $link->is_affiliate,
			'affiliateNetwork' => $link->affiliate_network,
			'responseTime'     => $link->response_time,
			'redirectCount'    => $link->redirect_count,
			'redirectChain'    => $link->redirect_chain ? json_decode( $link->redirect_chain, true ) : null,
			'lastChecked'      => $link->last_checked?->format( 'c' ),
			'checkCount'       => $link->check_count,
			'lastError'        => $link->last_error,
			'createdAt'        => $link->created_at->format( 'c' ),
			'updatedAt'        => $link->updated_at->format( 'c' ),
		);
	}

	/**
	 * Prepares a LinkInstance DTO for the REST response.
	 *
	 * @since 1.0.0
	 *
	 * @param LinkInstance $instance Instance DTO.
	 * @return array<string, mixed>
	 */
	private function prepare_instance_for_response( LinkInstance $instance ): array {
		$post_title = get_the_title( $instance->post_id );

		return array(
			'id'           => $instance->id,
			'linkId'       => $instance->link_id,
			'postId'       => $instance->post_id,
			'postTitle'    => $post_title,
			'postEditUrl'  => get_edit_post_link( $instance->post_id, 'raw' ),
			'sourceType'   => $instance->source_type,
			'anchorText'   => $instance->anchor_text,
			'relNofollow'  => $instance->rel_nofollow,
			'relSponsored' => $instance->rel_sponsored,
			'relUgc'       => $instance->rel_ugc,
			'isDofollow'   => $instance->is_dofollow,
			'linkPosition' => $instance->link_position,
			'blockName'    => $instance->block_name,
			'createdAt'    => $instance->created_at->format( 'c' ),
		);
	}



	/**
	 * Deletes a link from the database and removes all its instances from post content.
	 *
	 * @since 1.0.0
	 *
	 * @param \FlavorLinkChecker\Models\Link $link Link DTO.
	 * @return void
	 */
	private function perform_link_deletion( \FlavorLinkChecker\Models\Link $link ): void {
		// Remove link from post content (replace <a> with its text content).
		$instances = $this->instances_repo->find_by_link( $link->id );

		foreach ( $instances as $instance ) {
			$post = \get_post( $instance->post_id );
			if ( null === $post ) {
				continue;
			}

			$updated_content = $this->html_editor->unlink_in_html( $post->post_content, $link->url );

			if ( $updated_content !== $post->post_content ) {
				$this->html_editor->update_post_content_silently( $post->ID, $updated_content );
			}
		}

		$this->links_repo->delete( $link->id );
	}

	/**
	 * Returns args schema for update endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_update_params(): array {
		return array(
			'id'  => array(
				'type'              => 'integer',
				'required'          => true,
				'sanitize_callback' => 'absint',
			),
			'url' => array(
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
			),
			'rel' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Returns args schema for bulk endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_bulk_params(): array {
		return array(
			'action' => array(
				'type'              => 'string',
				'required'          => true,
				'enum'              => array( 'recheck', 'delete' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'ids'    => array(
				'type'              => 'array',
				'required'          => true,
				'items'             => array( 'type' => 'integer' ),
				'maxItems'          => 100,
				'sanitize_callback' => static function ( array $ids ): array {
					return array_map( 'absint', $ids );
				},
			),
		);
	}
}
