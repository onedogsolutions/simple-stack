<?php
/**
 * SimpleStack REST controller for command, SSH and error logs.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleStack_REST_Logs
 *
 * Feeds the live log drawer and the activity feed. Both routes are read-only and
 * every payload is passed through the secret redactor, because command output is
 * the most likely place for a password to appear by accident.
 */
class SimpleStack_REST_Logs extends SimpleStack_REST_Controller_Base {

	/**
	 * Route segment owned by this controller.
	 *
	 * @var string
	 */
	protected $name = 'servers';

	/**
	 * Map of the public log type names onto their post types.
	 *
	 * @var array
	 */
	protected $log_types = array(
		'command' => 'wpcd_command_log',
		'ssh'     => 'wpcd_ssh_log',
		'error'   => 'wpcd_error_log',
	);

	/**
	 * Register the log routes.
	 *
	 * @return void
	 */
	public function register_routes() {

		$this->register_get_route(
			$this->name . static::RESOURCE_ID_PATH . '/logs',
			'server_logs',
			array(
				'id'    => $this->arg_id(),
				'type'  => $this->arg_string( __( 'Which log stream to read.', 'wpcd' ), false, array( 'all', 'command', 'ssh', 'error' ), 'all' ),
				'since' => $this->arg_string( __( 'Only return entries dated after this ISO8601 timestamp.', 'wpcd' ) ),
				'limit' => $this->arg_int( __( 'Maximum number of entries to return.', 'wpcd' ), false, 1, 200, 50 ),
			)
		);

		$this->register_get_route(
			'activity',
			'activity',
			array(
				'limit'     => $this->arg_int( __( 'Maximum number of entries to return.', 'wpcd' ), false, 1, 200, 50 ),
				'server_id' => $this->arg_int( __( 'Restrict the feed to one server.', 'wpcd' ), false, 1 ),
			)
		);
	}

	/**
	 * Return the log stream for one server.
	 *
	 * GET /servers/{id}/logs
	 *
	 * @param WP_REST_Request $request The incoming request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function server_logs( WP_REST_Request $request ) {

		$server_id = (int) $request->get_param( 'id' );
		$server    = get_post( $server_id );

		if ( ! $server || 'wpcd_app_server' !== $server->post_type ) {
			return $this->error( 'simplestack_server_not_found', __( 'Server not found.', 'wpcd' ), 404 );
		}

		$type  = (string) $request->get_param( 'type' );
		$since = (string) $request->get_param( 'since' );
		$limit = (int) $request->get_param( 'limit' );

		$post_types = ( 'all' === $type || empty( $type ) ) ? array_values( $this->log_types ) : array( $this->log_types[ $type ] );

		$items = $this->query_logs( $post_types, $server_id, $since, $limit );

		return $this->respond(
			array(
				'items'  => $items,
				'cursor' => $this->cursor_from( $items ),
				'server' => $server_id,
				'type'   => $type,
			)
		);
	}

	/**
	 * Return a merged recent activity feed.
	 *
	 * GET /activity
	 *
	 * @param WP_REST_Request $request The incoming request.
	 *
	 * @return WP_REST_Response
	 */
	public function activity( WP_REST_Request $request ) {

		$limit     = (int) $request->get_param( 'limit' );
		$server_id = (int) $request->get_param( 'server_id' );

		$items = $this->query_logs( array_values( $this->log_types ), $server_id, '', $limit );

		return $this->respond(
			array(
				'items'  => $items,
				'cursor' => $this->cursor_from( $items ),
			)
		);
	}

	/**
	 * Query one or more log post types.
	 *
	 * @param array  $post_types Log post types to include.
	 * @param int    $parent_id  Restrict to this parent post. Zero means all parents.
	 * @param string $since      ISO8601 timestamp; only newer entries are returned.
	 * @param int    $limit      Maximum number of entries.
	 *
	 * @return array
	 */
	protected function query_logs( array $post_types, int $parent_id, string $since, int $limit ): array {

		$args = array(
			'post_type'      => $post_types,
			'post_status'    => 'private',
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		);

		if ( $parent_id > 0 ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => 'parent_post_id',
					'value' => $parent_id,
				),
			);
		}

		if ( ! empty( $since ) ) {
			$timestamp = strtotime( $since );
			if ( false !== $timestamp ) {
				$args['date_query'] = array(
					array(
						'column'    => 'post_date_gmt',
						'after'     => gmdate( 'Y-m-d H:i:s', $timestamp ),
						'inclusive' => false,
					),
				);
			}
		}

		$query = new WP_Query( $args );

		$items = array();
		foreach ( $query->posts as $post ) {
			$items[] = $this->format_entry( $post );
		}

		return $items;
	}

	/**
	 * Convert a log post into the shape the React log drawer consumes.
	 *
	 * @param WP_Post $post The log post.
	 *
	 * @return array
	 */
	protected function format_entry( WP_Post $post ): array {

		$type   = array_search( $post->post_type, $this->log_types, true );
		$parent = (int) get_post_meta( $post->ID, 'parent_post_id', true );

		$entry = array(
			'id'       => (int) $post->ID,
			'type'     => false === $type ? $post->post_type : $type,
			'parent'   => $parent,
			'title'    => $post->post_title,
			'date_gmt' => $post->post_date_gmt,
		);

		switch ( $post->post_type ) {
			case 'wpcd_command_log':
				$entry['command_type'] = (string) get_post_meta( $post->ID, 'command_type', true );
				$entry['reference']    = (string) get_post_meta( $post->ID, 'command_reference', true );
				$entry['body']         = (string) get_post_meta( $post->ID, 'command_result', true );
				break;
			case 'wpcd_ssh_log':
				$entry['reference'] = (string) get_post_meta( $post->ID, 'ssh_cmd', true );
				$entry['body']      = (string) get_post_meta( $post->ID, 'ssh_cmd_result', true );
				break;
			case 'wpcd_error_log':
				$entry['reference'] = (string) get_post_meta( $post->ID, 'error_type', true );
				$entry['body']      = (string) get_post_meta( $post->ID, 'error_msg', true );
				$entry['file']      = (string) get_post_meta( $post->ID, 'error_file', true );
				$entry['line']      = (int) get_post_meta( $post->ID, 'error_line', true );
				break;
			default:
				$entry['body'] = '';
				break;
		}

		return $entry;
	}

	/**
	 * Derive the polling cursor from the newest entry in a batch.
	 *
	 * @param array $items Formatted log entries, newest first.
	 *
	 * @return string
	 */
	protected function cursor_from( array $items ): string {

		if ( empty( $items ) ) {
			return '';
		}

		return (string) $items[0]['date_gmt'];
	}
}
