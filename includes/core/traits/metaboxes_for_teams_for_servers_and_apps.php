<?php
/** Modified for Simple Stack by One Dog Solutions on 2026-11-23. */
/**
 * Trait:
 * Contains some of the metabox related code for the server and apps cpt screens.
 * Used only by the class-sstk-posts-app-server.php and class-sstk-posts-app.php files which define the SSTK_POSTS_APP_SERVER and SSTK_POSTS_APP classes respectively.
 *
 * @package simple-stack
 */

/**
 * Trait sstk_metaboxes_for_teams_for_servers_and_apps
 */
trait sstk_metaboxes_for_teams_for_servers_and_apps {

	/**
	 * Initialization hook to bootstrap the other hooks need.
	 * This function needs to be called by the class using this
	 * trait.
	 */
	public function init_hooks_for_teams_for_servers_and_apps() {

		// Filter hook to add metabox for team.
		add_filter( 'rwmb_meta_boxes', array( $this, 'sstk_register_teams_metaboxes' ) );

		// Filter hook to restrict change in team metabox if user is not admin or superadmin.
		add_action( 'rwmb_sstk_assign_teams_before_save_post', array( $this, 'sstk_assign_teams_before_save_post' ) );
	}

	/**
	 * Register the team metaboxes - these are metabox.io metaboxes, not standard WP metaboxes.
	 *
	 * Filter hook: rwmb_meta_boxes
	 *
	 * @param array $metaboxes Array of existing metaboxes.
	 *
	 * @return array new array of metaboxes
	 */
	public function sstk_register_teams_metaboxes( $metaboxes ) {

		if ( ! sstk_is_admin() ) {
			return $metaboxes;
		}

		$post_type = $this->get_post_type();

		$prefix = 'sstk_';

		// Register the metabox for assigning teams.
		$metaboxes[] = array(
			'id'       => $prefix . 'assign_teams',
			'title'    => __( 'Assign Teams', 'simple-stack' ),
			'pages'    => array( $post_type ), // displays on sstk_app_server post type only.
			'context'  => 'side',
			'priority' => 'low',
			'fields'   => array(
				array(
					'name'        => '',
					'id'          => $prefix . 'assigned_teams',
					'type'        => 'post',
					'post_type'   => array( 'sstk_team' ),
					'field_type'  => 'select_advanced',
					'placeholder' => __( 'Select a team', 'simple-stack' ),
					'multiple'    => true,
					'query_args'  => array(
						'post_status'    => 'private',
						'posts_per_page' => -1,
					),
				),
			),
		);
		return $metaboxes;
	}

	/**
	 * Restrict the change in Assigned Team metabox if the user is not admin or super admin
	 *
	 * @param  int $post_id post id.
	 *
	 * @return void
	 */
	public function sstk_assign_teams_before_save_post( $post_id ) {

		$sstk_assigned_teams = filter_input( INPUT_POST, 'sstk_assigned_teams', FILTER_SANITIZE_NUMBER_INT, FILTER_REQUIRE_ARRAY );
		$post_teams          = get_post_meta( $post_id, 'sstk_assigned_teams', false );

		if ( ! sstk_is_admin() && count( $sstk_assigned_teams ) != count( $post_teams ) && array_diff( $sstk_assigned_teams, $post_teams ) != array_diff( $post_teams, $sstk_assigned_teams ) ) {
			wp_die( esc_html( __( 'You are not allowed to save/change teams.', 'simple-stack' ) ) );
		}
	}
}
