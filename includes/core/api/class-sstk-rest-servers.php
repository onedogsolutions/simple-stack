<?php
/**
 * Modified for Simple Stack by One Dog Solutions on 2026-11-23.
 *
 * Simple Stack REST controller for servers.
 *
 * @package simple-stack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SSTK_REST_Servers
 *
 * Routes for the server list, individual server records, provisioning and the
 * server action bridge.
 *
 * The action bridge is the reason the React cutover does not have to reimplement
 * 22 server tabs: POST /servers/{id}/action forwards straight into the engine that
 * the legacy admin-ajax handler already used.
 */
class SSTK_REST_Servers extends SSTK_REST_Controller_Base {

	/**
	 * Route segment owned by this controller.
	 *
	 * @var string
	 */
	protected $name = 'servers';

	/**
	 * Register the server routes.
	 *
	 * @return void
	 */
	public function register_routes() {

		$this->register_get_route(
			$this->name,
			'list_servers',
			array_merge(
				$this->paging_args(),
				array(
					'provider' => $this->arg_string( __( 'Filter by provider slug.', 'simple-stack' ) ),
					'region'   => $this->arg_string( __( 'Filter by provider region slug.', 'simple-stack' ) ),
					'status'   => $this->arg_string( __( 'Filter by recorded server state.', 'simple-stack' ) ),
					'search'   => $this->arg_string( __( 'Free text search on the server name.', 'simple-stack' ) ),
				)
			)
		);

		$this->register_post_route(
			$this->name,
			'create_server',
			array(
				'name'           => $this->arg_string( __( 'Server name.', 'simple-stack' ), true ),
				'provider'       => $this->arg_string( __( 'Provider slug.', 'simple-stack' ), true ),
				'region'         => $this->arg_string( __( 'Provider region slug.', 'simple-stack' ), true ),
				'size'           => $this->arg_string( __( 'Provider size or plan slug.', 'simple-stack' ), true ),
				'os'             => $this->arg_string( __( 'Initial operating system slug.', 'simple-stack' ) ),
				'webserver_type' => $this->arg_string( __( 'Web server type.', 'simple-stack' ), false, array( 'ols' ), 'ols' ),
				'author_email'   => $this->arg_string( __( 'Owner email address.', 'simple-stack' ) ),
			)
		);

		$this->register_get_route(
			$this->name . static::RESOURCE_ID_PATH,
			'get_server',
			array( 'id' => $this->arg_id() )
		);

		$this->register_delete_route(
			$this->name . static::RESOURCE_ID_PATH,
			'delete_server',
			array( 'id' => $this->arg_id() )
		);

		$this->register_post_route(
			$this->name . static::RESOURCE_ID_PATH . '/action',
			'run_action',
			array(
				'id'         => $this->arg_id(),
				'action'     => $this->arg_string( __( 'Action slug understood by the server action bridge.', 'simple-stack' ), true ),
				'params'     => $this->arg_params( __( 'Parameters addressed to the tab handler.', 'simple-stack' ) ),
				'additional' => $this->arg_object( __( 'Alias of params. Passed on to the lifecycle engine.', 'simple-stack' ) ),
			)
		);
	}

	/**
	 * List server records.
	 *
	 * GET /servers
	 *
	 * @param WP_REST_Request $request The incoming request.
	 *
	 * @return WP_REST_Response
	 */
	public function list_servers( WP_REST_Request $request ) {

		$paging = $this->paging( $request );

		$args = array(
			'post_type'      => 'sstk_app_server',
			'post_status'    => 'private',
			'posts_per_page' => $paging['per_page'],
			'paged'          => $paging['page'],
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => array(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		);

		$provider = (string) $request->get_param( 'provider' );
		if ( ! empty( $provider ) ) {
			$args['meta_query'][] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'key'   => 'sstk_server_provider',
				'value' => $provider,
			);
		}

		$region = (string) $request->get_param( 'region' );
		if ( ! empty( $region ) ) {
			$args['meta_query'][] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'key'   => 'sstk_server_region',
				'value' => $region,
			);
		}

		$status = (string) $request->get_param( 'status' );
		if ( ! empty( $status ) ) {
			$args['meta_query'][] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'key'   => 'sstk_server_current_state',
				'value' => $status,
			);
		}

		$search = (string) $request->get_param( 'search' );
		if ( ! empty( $search ) ) {
			$args['s'] = $search;
		}

		if ( empty( $args['meta_query'] ) ) {
			unset( $args['meta_query'] );
		}

		$query = new WP_Query( $args );

		$items = array();
		foreach ( $query->posts as $post ) {
			$items[] = $this->summarize( $post->ID );
		}

		return $this->respond(
			array(
				'items'    => $items,
				'total'    => (int) $query->found_posts,
				'page'     => $paging['page'],
				'per_page' => $paging['per_page'],
			)
		);
	}

	/**
	 * Provision a new server.
	 *
	 * POST /servers
	 *
	 * @param WP_REST_Request $request The incoming request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_server( WP_REST_Request $request ) {

		$provider = (string) $request->get_param( 'provider' );
		$providers = SSTK()->get_cloud_providers();

		if ( ! array_key_exists( $provider, $providers ) ) {
			return $this->error( 'sstk_invalid_provider', __( 'The selected provider is not available.', 'simple-stack' ), 400 );
		}

		$author_email = '';
		$raw_email    = (string) $request->get_param( 'author_email' );
		if ( ! empty( $raw_email ) ) {
			$author_email = sanitize_email( $raw_email );
			if ( empty( $author_email ) ) {
				return $this->error( 'sstk_invalid_email', __( 'The owner email address is not valid.', 'simple-stack' ), 400 );
			}
		}

		$os = (string) $request->get_param( 'os' );
		if ( empty( $os ) ) {
			$os = SSTK_WORDPRESS_APP()->get_default_os();
		}

		if ( ! isset( SSTK()->get_os_list()[ $os ] ) ) {
			return $this->error( 'sstk_invalid_os', __( 'The selected operating system is not supported.', 'simple-stack' ), 400 );
		}

		$attributes = array(
			'initial_os'       => $os,
			'initial_app_name' => SSTK_WORDPRESS_APP()->get_app_name(),
			'server-type'      => 'wordpress-app',
			'scripts_version'  => SSTK_WORDPRESS_APP()->get_configured_scripts_version(),
			'region'           => (string) $request->get_param( 'region' ),
			'size_raw'         => (string) $request->get_param( 'size' ),
			'name'             => (string) $request->get_param( 'name' ),
			'provider'         => $provider,
			'author_email'     => $author_email,
			'init'             => true,
			'webserver_type'   => 'ols',
			'wp_restapi_flag'  => 'yes',
		);

		/* Create the instance at the provider and the local CPT record. */
		$instance = SSTK_SERVER()->create_server( 'create', $attributes );

		if ( empty( $instance ) || is_wp_error( $instance ) ) {
			$message = is_wp_error( $instance ) ? $instance->get_error_message() : __( 'Unable to create the server.', 'simple-stack' );
			return $this->error( 'sstk_server_create_failed', $message, 400 );
		}

		/* Fill in the remaining server post fields. */
		$instance = SSTK_WORDPRESS_APP()->add_app( $instance );

		if ( empty( $instance ) || is_wp_error( $instance ) ) {
			$message = is_wp_error( $instance ) ? $instance->get_error_message() : __( 'Unable to create the server.', 'simple-stack' );
			return $this->error( 'sstk_server_create_failed', $message, 400 );
		}

		$server_id = (int) $instance['post_id'];

		$task_id = SSTK_POSTS_PENDING_TASKS_LOG()->add_pending_task_log_entry(
			$server_id,
			'sstk_install_server',
			$server_id,
			$instance,
			'in-process',
			$server_id,
			__( 'Simple Stack: Server is being created', 'simple-stack' )
		);

		if ( empty( $task_id ) || empty( $server_id ) ) {
			return $this->error( 'sstk_server_create_failed', __( 'The server record was created but the pending task entry could not be written.', 'simple-stack' ), 500 );
		}

		return $this->respond(
			array(
				'server_id' => $server_id,
				'task_id'   => (int) $task_id,
				'server'    => $this->summarize( $server_id ),
			),
			201
		);
	}

	/**
	 * Fetch a single server record.
	 *
	 * GET /servers/{id}
	 *
	 * @param WP_REST_Request $request The incoming request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_server( WP_REST_Request $request ) {

		$id   = (int) $request->get_param( 'id' );
		$post = $this->get_server_post( $id );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$data                = $this->summarize( $id );
		$data['meta']        = $this->collect_server_meta( $id );
		$data['php_version'] = SSTK_WORDPRESS_APP()->get_php_version_for_app( $id );
		$data['available']   = SSTK_SERVER()->is_server_available_for_commands( $id );

		return $this->respond( $data );
	}

	/**
	 * Delete a server record and the provider instance behind it.
	 *
	 * DELETE /servers/{id}
	 *
	 * @param WP_REST_Request $request The incoming request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_server( WP_REST_Request $request ) {

		$id   = (int) $request->get_param( 'id' );
		$post = $this->get_server_post( $id );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		/* The delete action is a filter/action chain owned by the WordPress app. */
		do_action( 'sstk_server_wordpress-app_action', $id, 'delete' );

		$server = get_post( $id );
		if ( is_object( $server ) && 'trash' !== $server->post_status ) {
			return $this->error( 'sstk_server_delete_failed', __( 'The server record still exists and is not in the trash. The provider instance may still be running.', 'simple-stack' ), 400 );
		}

		return $this->respond(
			array(
				'server_id' => $id,
				'deleted'   => true,
			)
		);
	}

	/**
	 * Run an action against a server.
	 *
	 * POST /servers/{id}/action
	 *
	 * The server tabs register `sstk_app_{app}_tab_action`, not a server-specific filter
	 * (see tabs-server/services.php and its siblings), so the detail screen dispatches
	 * through the same chain the site tabs use. Lifecycle verbs (reboot, resize,
	 * snapshot, delete) still belong to do_instance_action(); it runs when no tab
	 * claimed the action, which is exactly what a null return from the chain means.
	 *
	 * @param WP_REST_Request $request The incoming request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function run_action( WP_REST_Request $request ) {

		$id   = (int) $request->get_param( 'id' );
		$post = $this->get_server_post( $id );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$action = (string) $request->get_param( 'action' );

		$params = $request->get_param( 'params' );
		if ( ! is_array( $params ) || empty( $params ) ) {
			$params = $request->get_param( 'additional' );
		}
		if ( ! is_array( $params ) ) {
			$params = array();
		}

		$result = $this->dispatch_tab_action( $id, $action, $params, $request->get_file_params() );

		if ( null === $result ) {
			$result = SSTK_WORDPRESS_APP()->do_instance_action( $id, $action, $params );
		}

		if ( is_wp_error( $result ) ) {
			return $this->error( $result->get_error_code(), $result->get_error_message(), 400 );
		}

		return $this->respond(
			array(
				'server_id' => $id,
				'action'    => $action,
				'result'    => $result,
			)
		);
	}

	/**
	 * Fetch a server post and verify its type.
	 *
	 * @param int $id The post id.
	 *
	 * @return WP_Post|WP_Error
	 */
	protected function get_server_post( int $id ) {

		$post = get_post( $id );

		if ( ! $post || 'sstk_app_server' !== $post->post_type ) {
			return $this->error( 'sstk_server_not_found', __( 'Server not found.', 'simple-stack' ), 404 );
		}

		return $post;
	}

	/**
	 * Build the list/detail representation of a server.
	 *
	 * @param int $id The server post id.
	 *
	 * @return array
	 */
	protected function summarize( int $id ): array {

		$provider = SSTK_SERVER()->get_server_provider( $id );

		return array(
			'id'             => $id,
			'name'           => SSTK_SERVER()->get_server_name( $id ),
			'provider'       => $provider,
			'provider_label' => $this->provider_label( $provider ),
			'region'         => SSTK_SERVER()->get_server_region( $id ),
			'size'           => SSTK_SERVER()->get_server_size( $id ),
			'state'          => (string) get_post_meta( $id, 'sstk_server_current_state', true ),
			'health'         => (string) get_post_meta( $id, 'sstk_server_health', true ),
			'webserver_type' => (string) get_post_meta( $id, 'sstk_server_webserver_type', true ),
			'ipv4'           => SSTK_SERVER()->get_ipv4_address( $id ),
			'ipv6'           => (string) get_post_meta( $id, 'sstk_server_ipv6', true ),
			'instance_id'    => SSTK_SERVER()->get_server_provider_instance_id( $id ),
			'os'             => SSTK_SERVER()->get_server_os( $id ),
			'app_count'      => (int) SSTK_SERVER()->get_app_count( $id ),
			'author'         => (int) get_post_field( 'post_author', $id ),
			'created'        => (string) get_post_field( 'post_date_gmt', $id ),
		);
	}

	/**
	 * Translate a provider slug into its display label.
	 *
	 * @param string $provider Provider slug.
	 *
	 * @return string
	 */
	protected function provider_label( string $provider ): string {

		$providers = SSTK()->get_cloud_providers();

		return isset( $providers[ $provider ] ) ? (string) $providers[ $provider ] : $provider;
	}

	/**
	 * Return every recorded server meta value, with the sstk_server_ prefix stripped.
	 *
	 * The caller is responsible for redaction; respond() masks credential-shaped
	 * keys before the payload leaves the process.
	 *
	 * @param int $id The server post id.
	 *
	 * @return array
	 */
	protected function collect_server_meta( int $id ): array {

		$meta = array();

		foreach ( get_post_meta( $id ) as $key => $values ) {
			if ( 0 !== strpos( $key, 'sstk_server_' ) ) {
				continue;
			}

			$value = maybe_unserialize( $values );
			if ( is_array( $value ) && 1 === count( $value ) ) {
				$value = $value[0];
			}

			$meta[ str_replace( 'sstk_server_', '', $key ) ] = $value;
		}

		return $meta;
	}
}
