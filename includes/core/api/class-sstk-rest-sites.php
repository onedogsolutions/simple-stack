<?php
/**
 * SimpleStack REST controller for WordPress sites.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleStack_REST_Sites
 *
 * Routes for the site list, site detail, deployment and the site action bridge.
 *
 * Site tabs read their inputs from $_POST, so the action route copies the request
 * body into the superglobal for the duration of the call and restores it afterwards.
 * That is exactly what the legacy admin-ajax handler did, without the jQuery client.
 */
class SimpleStack_REST_Sites extends SimpleStack_REST_Controller_Base {

	/**
	 * Route segment owned by this controller.
	 *
	 * @var string
	 */
	protected $name = 'sites';

	/**
	 * Register the site routes.
	 *
	 * @return void
	 */
	public function register_routes() {

		$this->register_get_route(
			$this->name,
			'list_sites',
			array_merge(
				$this->paging_args(),
				array(
					'server_id' => $this->arg_int( __( 'Only return sites on this server.', 'wpcd' ), false, 1 ),
					'search'    => $this->arg_string( __( 'Free text search on the site title.', 'wpcd' ) ),
				)
			)
		);

		$this->register_post_route(
			$this->name,
			'deploy_site',
			array(
				'server_id'     => $this->arg_int( __( 'Server that will host the new site.', 'wpcd' ), true, 1 ),
				'domain'        => $this->arg_string( __( 'Primary domain for the site.', 'wpcd' ), true ),
				'admin_user'    => $this->arg_string( __( 'WordPress administrator user name.', 'wpcd' ), true ),
				'admin_email'   => $this->arg_string( __( 'WordPress administrator email address.', 'wpcd' ), true ),
				'admin_password' => $this->arg_text( __( 'WordPress administrator password. Never logged or echoed back.', 'wpcd' ), true ),
				'wp_version'    => $this->arg_string( __( 'WordPress version to install.', 'wpcd' ), true ),
				'locale'        => $this->arg_string( __( 'WordPress locale, defaults to en_US.', 'wpcd' ) ),
				'site_package'  => $this->arg_int( __( 'Optional site package id.', 'wpcd' ), false, 0 ),
				'php_version'   => $this->arg_string( __( 'PHP version to bind to the site.', 'wpcd' ) ),
			)
		);

		$this->register_get_route(
			$this->name . static::RESOURCE_ID_PATH,
			'get_site',
			array( 'id' => $this->arg_id() )
		);

		$this->register_delete_route(
			$this->name . static::RESOURCE_ID_PATH,
			'delete_site',
			array( 'id' => $this->arg_id() )
		);

		$this->register_post_route(
			$this->name . static::RESOURCE_ID_PATH . '/action',
			'run_action',
			array(
				'id'     => $this->arg_id(),
				'action' => $this->arg_string( __( 'Action slug handled by a site tab.', 'wpcd' ), true ),
				'params' => $this->arg_params( __( 'Parameters the tab expects, matching the legacy $_POST payload.', 'wpcd' ) ),
			)
		);
	}

	/**
	 * List site records.
	 *
	 * GET /sites
	 *
	 * @param WP_REST_Request $request The incoming request.
	 *
	 * @return WP_REST_Response
	 */
	public function list_sites( WP_REST_Request $request ) {

		$paging = $this->paging( $request );

		$args = array(
			'post_type'      => 'wpcd_app',
			'post_status'    => 'private',
			'posts_per_page' => $paging['per_page'],
			'paged'          => $paging['page'],
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => 'app_type',
					'value' => WPCD_WORDPRESS_APP()->get_app_name(),
				),
			),
		);

		$server_id = (int) $request->get_param( 'server_id' );
		if ( $server_id > 0 ) {
			$args['meta_query'][] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'key'   => 'parent_post_id',
				'value' => $server_id,
			);
		}

		$search = (string) $request->get_param( 'search' );
		if ( ! empty( $search ) ) {
			$args['s'] = $search;
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
	 * Deploy a new WordPress site onto an existing server.
	 *
	 * POST /sites
	 *
	 * Uses the same validator the legacy popup used, so every guard (duplicate
	 * domain, shell-unsafe password, invalid email, invalid user name) still runs.
	 *
	 * @param WP_REST_Request $request The incoming request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function deploy_site( WP_REST_Request $request ) {

		$server_id = (int) $request->get_param( 'server_id' );
		$server    = get_post( $server_id );

		if ( ! $server || 'wpcd_app_server' !== $server->post_type ) {
			return $this->error( 'simplestack_server_not_found', __( 'Server not found.', 'wpcd' ), 404 );
		}

		$args = array(
			'id'              => $server_id,
			'wpcd_app_type'   => 'wordpress',
			'wp_domain'       => (string) $request->get_param( 'domain' ),
			'wp_user'         => (string) $request->get_param( 'admin_user' ),
			'wp_password'     => (string) $request->get_param( 'admin_password' ),
			'wp_email'        => (string) $request->get_param( 'admin_email' ),
			'wp_version'      => (string) $request->get_param( 'wp_version' ),
			'wp_locale'       => (string) $request->get_param( 'locale' ),
			'wp_site_package' => (int) $request->get_param( 'site_package' ),
		);

		$result = WPCD_WORDPRESS_APP()->install_wp_validate( $args );

		if ( is_wp_error( $result ) ) {
			return $this->error( $result->get_error_code(), $result->get_error_message(), 400 );
		}

		if ( empty( $result ) ) {
			return $this->error( 'simplestack_site_deploy_failed', __( 'The site could not be queued for installation.', 'wpcd' ), 400 );
		}

		$app_id = isset( $result['new_app_post_id'] ) ? (int) $result['new_app_post_id'] : 0;

		/* Bind the requested PHP version before the installer runs. */
		$php_version = (string) $request->get_param( 'php_version' );
		if ( $app_id > 0 && ! empty( $php_version ) ) {
			WPCD_WORDPRESS_APP()->set_php_version_for_app( $app_id, $php_version );
		}

		return $this->respond(
			array(
				'app_id'    => $app_id,
				'server_id' => $server_id,
				'command'   => isset( $result['command'] ) ? (string) $result['command'] : '',
				'domain'    => isset( $result['domain'] ) ? (string) $result['domain'] : '',
			),
			201
		);
	}

	/**
	 * Fetch a single site record.
	 *
	 * GET /sites/{id}
	 *
	 * @param WP_REST_Request $request The incoming request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_site( WP_REST_Request $request ) {

		$id   = (int) $request->get_param( 'id' );
		$post = $this->get_site_post( $id );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$data                  = $this->summarize( $id );
		$data['meta']          = $this->collect_app_meta( $id );
		$data['action_status'] = (string) get_post_meta( $id, 'wpcd_app_action_status', true );

		return $this->respond( $data );
	}

	/**
	 * Trash a site record.
	 *
	 * DELETE /sites/{id}
	 *
	 * @param WP_REST_Request $request The incoming request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_site( WP_REST_Request $request ) {

		$id   = (int) $request->get_param( 'id' );
		$post = $this->get_site_post( $id );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		/* The CPT keeps its own permission gate; ask it before trashing. */
		if ( true !== WPCD_POSTS_APP()->wpcd_app_delete_post( $id, true ) ) {
			return $this->error( 'simplestack_site_delete_forbidden', __( 'You are not allowed to delete this site.', 'wpcd' ), 403 );
		}

		$trashed = wp_trash_post( $id );

		if ( empty( $trashed ) ) {
			return $this->error( 'simplestack_site_delete_failed', __( 'The site record could not be moved to the trash.', 'wpcd' ), 400 );
		}

		return $this->respond(
			array(
				'app_id'  => $id,
				'deleted' => true,
			)
		);
	}

	/**
	 * Run an action against a site through the existing tab action bridge.
	 *
	 * POST /sites/{id}/action
	 *
	 * The legacy admin-ajax handler passed $_POST straight to this filter. Site
	 * tabs read $_POST['params'], so the body is mapped back onto the superglobal
	 * for the duration of the dispatch and restored immediately afterwards.
	 *
	 * @param WP_REST_Request $request The incoming request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function run_action( WP_REST_Request $request ) {

		$id   = (int) $request->get_param( 'id' );
		$post = $this->get_site_post( $id );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$action = (string) $request->get_param( 'action' );
		$params = $request->get_param( 'params' );
		if ( ! is_array( $params ) ) {
			$params = array();
		}

		$result = $this->dispatch_tab_action( $id, $action, $params, $request->get_file_params() );

		if ( is_wp_error( $result ) ) {
			return $this->error( $result->get_error_code(), $result->get_error_message(), 400 );
		}

		return $this->respond(
			array(
				'app_id'    => $id,
				'server_id' => (int) get_post_meta( $id, 'parent_post_id', true ),
				'action'    => $action,
				'result'    => $result,
			)
		);
	}

	/**
	 * Fetch a site post and verify its type and app name.
	 *
	 * @param int $id The post id.
	 *
	 * @return WP_Post|WP_Error
	 */
	protected function get_site_post( int $id ) {

		$post = get_post( $id );

		if ( ! $post || 'wpcd_app' !== $post->post_type ) {
			return $this->error( 'simplestack_site_not_found', __( 'Site not found.', 'wpcd' ), 404 );
		}

		if ( WPCD_WORDPRESS_APP()->get_app_name() !== get_post_meta( $id, 'app_type', true ) ) {
			return $this->error( 'simplestack_site_not_found', __( 'Site not found.', 'wpcd' ), 404 );
		}

		return $post;
	}

	/**
	 * Build the list/detail representation of a site.
	 *
	 * @param int $id The site post id.
	 *
	 * @return array
	 */
	protected function summarize( int $id ): array {

		$server_id = (int) get_post_meta( $id, 'parent_post_id', true );

		return array(
			'id'          => $id,
			'name'        => get_the_title( $id ),
			'domain'      => WPCD_WORDPRESS_APP()->get_domain_name( $id ),
			'server_id'   => $server_id,
			'server_name' => $server_id > 0 ? WPCD_SERVER()->get_server_name( $server_id ) : '',
			'php_version' => WPCD_WORDPRESS_APP()->get_php_version_for_app( $id ),
			'wp_version'  => (string) get_post_meta( $id, 'wpapp_version', true ),
			'owner'       => (int) get_post_field( 'post_author', $id ),
			'created'     => (string) get_post_field( 'post_date_gmt', $id ),
		);
	}

	/**
	 * Return every recorded app meta value, with the wpapp_ prefix stripped.
	 *
	 * @param int $id The site post id.
	 *
	 * @return array
	 */
	protected function collect_app_meta( int $id ): array {

		$meta = array();

		foreach ( get_post_meta( $id ) as $key => $values ) {
			if ( 0 !== strpos( $key, 'wpapp_' ) ) {
				continue;
			}

			$value = maybe_unserialize( $values );
			if ( is_array( $value ) && 1 === count( $value ) ) {
				$value = $value[0];
			}

			/* wpapp_domain is already exposed as a first class field. */
			if ( 'wpapp_domain' === $key ) {
				continue;
			}

			$meta[ str_replace( 'wpapp_', '', $key ) ] = $value;
		}

		return $meta;
	}
}
