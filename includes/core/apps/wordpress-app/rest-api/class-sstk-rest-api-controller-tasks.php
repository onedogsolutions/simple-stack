<?php
/**
 * Modified for Simple Stack by One Dog Solutions on 2026-11-23.
 *
 * WordPress App SSTK_REST_API_Controller_Tasks.
 *
 * @package simple-stack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SSTK_REST_API_Controller_Tasks
 *
 * Endpoints for interacting with tasks (sstk_pending_log)
 */
class SSTK_REST_API_Controller_Tasks extends SSTK_REST_API_Controller_Base {

	/**
	 * Controller base path
	 *
	 * @var string
	 */
	protected $name = 'tasks';

	/**
	 * Implements base method
	 */
	public function register_routes() {
		$this->register_get_route( $this->name, 'list_tasks' );
		$this->register_get_route( $this->name . static::RESOURCE_ID_PATH, 'get_task' );
	}

	/**
	 * Lists all tasks
	 *
	 * GET /tasks
	 *
	 * @return array
	 */
	public function list_tasks(): array {
		$tasks = get_posts(
			array(
				'post_type'      => 'sstk_pending_log',
				'post_status'    => 'private',
				'posts_per_page' => -1,
			),
		);
		return array_map( array( $this, 'get_task_data' ), $tasks );
	}

	/**
	 * Returns a single task with the given ID
	 *
	 * GET /tasks/{id}
	 *
	 * @param WP_REST_Request $request - incoming request object.
	 *
	 * @return array
	 * @throws Exception - Task not found.
	 */
	public function get_task( WP_REST_Request $request ): array {
		$id   = (int) $request->get_param( 'id' );
		$task = $this->get_task_post( $id );
		return $this->get_task_data( $task );
	}

	/**
	 * Fetches post and verifies the correct post type
	 *
	 * @param int $id - requested post id.
	 *
	 * @return WP_Post
	 * @throws Exception - Task not found.
	 */
	protected function get_task_post( int $id ): WP_Post {
		$site = get_post( $id );
		if ( ! ( $site && 'sstk_pending_log' === $site->post_type ) ) {
			throw new Exception( 'Task not found', 400 );
		}
		return $site;
	}

	/**
	 * Builds response data for a task
	 *
	 * @param WP_Post $task - fetched task post.
	 *
	 * @return array
	 */
	protected function get_task_data( WP_Post $task ): array {
		$display_complete_date = get_post_meta( $task->ID, 'pending_task_complete_date', true );
		return array(
			'id'                    => $task->ID,
			'name'                  => $task->post_title,
			'type'                  => get_post_meta( $task->ID, 'pending_task_type', true ),
			'key'                   => get_post_meta( $task->ID, 'pending_task_key', true ),
			'state'                 => get_post_meta( $task->ID, 'pending_task_state', true ),
			'related_server_id'     => get_post_meta( $task->ID, 'pending_task_associated_server_id', true ),
			'reference'             => get_post_meta( $task->ID, 'pending_task_reference', true ),
			'comment'               => get_post_meta( $task->ID, 'pending_task_comment', true ),
			'user_id'               => (int) $task->post_author,
			'parent_id'             => (int) get_post_meta( $task->ID, 'parent_post_id', true ),
			'parent_type'           => get_post_meta( $task->ID, 'pending_task_parent_post_type', true ),
			'display_start_date'    => gmdate( 'Y-m-d @ H:i', get_post_meta( $task->ID, 'pending_task_start_date', true ) ),
			'display_complete_date' => empty( $display_complete_date ) ? null : gmdate( 'Y-m-d @ H:i', get_post_meta( $task->ID, 'pending_task_complete_date', true ) ),
			'unix_start_date'       => get_post_meta( $task->ID, 'pending_task_start_date', true ),
			'unix_complete_date'    => get_post_meta( $task->ID, 'pending_task_complete_date', true ),
		);
	}
}
