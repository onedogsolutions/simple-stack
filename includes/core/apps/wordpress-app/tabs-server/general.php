<?php
/** Modified for Simple Stack by One Dog Solutions on 2026-11-23. */
/**
 * General
 *
 * @package simple-stack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SSTK_WORDPRESS_TABS_SERVER_GENERAL
 */
class SSTK_WORDPRESS_TABS_SERVER_GENERAL extends SSTK_WORDPRESS_TABS {

	/**
	 * SSTK_WORDPRESS_TABS_PHP constructor.
	 */
	public function __construct() {
		parent::__construct();
		/* add_filter( "sstk_server_{$this->get_app_name()}_get_tabnames", array( $this, 'get_tab' ), 10, 1 ); */
		add_filter( "sstk_server_{$this->get_app_name()}_get_tabs", array( $this, 'get_nontab_fields' ), 9, 2 ); // these fields will go at the top of the metabox, before the tabs start.
		// add_filter( "sstk_server_{$this->get_app_name()}_get_tabs", array( $this, 'get_tab_fields' ), 10, 2 );
		// add_filter( "sstk_server_{$this->get_app_name()}_tab_action", array( $this, 'tab_action_server' ), 10, 3 );  // This filter has not been defined and called yet in classs-wordpress-app and might never be because we're using the one below.
		// add_filter( "sstk_app_{$this->get_app_name()}_tab_action", array( $this, 'tab_action' ), 10, 3 );  // This filter says 'sstk_app' because we're using the same functions for server details ajax tabs and app details ajax tabs.
	}

	/**
	 * Populates the tab name.   - NOT USED HERE / PLACEHOLDER ONLY
	 *
	 * @param array $tabs The default value.
	 *
	 * @return array    $tabs The default value.
	 */
	public function get_tab( $tabs ) {
		$tabs['general'] = array(
			'label' => __( 'General', 'simple-stack' ),
		);
		return $tabs;
	}

	/**
	 * Checks whether or not the user can view the current tab.
	 *
	 * @param int $id The post ID of the server.
	 *
	 * @return boolean
	 */
	public function get_tab_security( $id ) {
		return ( true === $this->sstk_wpapp_server_user_can( $this->get_view_tab_team_permission_slug(), $id ) && true === $this->sstk_can_author_view_server_tab( $id, $this->get_tab_slug() ) );
	}

	/**
	 * Gets the fields to be shown in the GENERAL tab.   - NOT USED HERE / PLACEHOLDER ONLY
	 *
	 * Filter hook: sstk_app_{$this->get_app_name()}_get_tabs.
	 *
	 * @param array $fields fields.
	 * @param int   $id id.
	 *
	 * @return array Array of actions, complying with the structure necessary by metabox.io fields.
	 */
	public function get_tab_fields( array $fields, $id ) {

		// If user is not allowed to access the tab then don't paint the fields.
		if ( ! $this->get_tab_security( $id ) ) {
			return $fields;
		}

		return $this->get_fields_for_tab( $fields, $id, 'general' );

	}

	/**
	 * Called when an action needs to be performed on the tab.  - NOT USED HERE / PLACEHOLDER ONLY
	 *
	 * @param mixed  $result The default value of the result.
	 * @param string $action The action to be performed.
	 * @param int    $id The post ID of the server.
	 *
	 * @return mixed    $result The default value of the result.
	 */
	public function tab_action( $result, $action, $id ) {

		/* Verify that the user is even allowed to view the server before proceeding to do anything else */
		if ( ! $this->sstk_user_can_view_wp_server( $id ) ) {
			return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'simple-stack' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
		}

		switch ( $action ) {
			case 'test':
				// Remove all metas related to background processes that might be "hung" or in-processs.
				$result = new \WP_Error( __( 'Test successful.', 'simple-stack' ) );
				if ( ! is_wp_error( $result ) ) {
					$result = array( 'refresh' => 'yes' );
				}
				break;
		}

		return $result;
	}

	/**
	 * Gets the actions to be shown in the GENERAL tab.   - NOT USED HERE / PLACEHOLDER ONLY
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	public function get_actions( $id ) {

		return $this->get_general_fields( $id );

	}

	/**
	 * Gets the fields for the GENERAL tab on the server details page.   - NOT USED HERE / PLACEHOLDER ONLY
	 *
	 * @param int $id the post id of the server cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_general_fields( $id ) {
		$actions = array();

		$actions['general-welcome-header'] = array(
			'label'          => __( 'Welcome', 'simple-stack' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'Awesome - Another WordPress Server!', 'simple-stack' ),
			),
		);

		/* Set the text of the confirmation prompt */
		$confirmation_prompt = __( 'Are you sure you want to run this test?', 'simple-stack' );

		$actions['test'] = array(
			'label'          => __( 'Test', 'simple-stack' ),
			'raw_attributes' => array(
				'std'                 => __( 'Test Button For AJAX', 'simple-stack' ),
				'desc'                => __( 'This is a test for AJAX functions inside the Server Details scree.', 'simple-stack' ),
				'confirmation_prompt' => $confirmation_prompt,
			),
			'type'           => 'button',
		);

		return $actions;

	}

	/**
	 * Gets the fields that go at the top of the metabox on the server details page.
	 *
	 * @param string $actions actions.
	 * @param int    $id the post id of the server cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	public function get_nontab_fields( $actions, $id ) {

		$actions['general-welcome-top-col_1'] = array(
			'name'    => __( 'Server Name', 'simple-stack' ),
			'type'    => 'custom_html',
			'std'     => get_post_meta( $id, 'sstk_server_name', true ),
			'columns' => 3,
			'class'   => 'sstk_server_details_top_row',
		);

		$actions['general-welcome-top-col_2'] = array(
			'name'    => __( 'IPv4', 'simple-stack' ),
			'type'    => 'custom_html',
			'std'     => SSTK_SERVER()->get_all_ip_addresses_for_display( $id ),
			'columns' => 3,
			'class'   => 'sstk_server_details_top_row',
		);

		$actions['general-welcome-top-col_3'] = array(
			'name'    => __( 'Provider', 'simple-stack' ),
			'type'    => 'custom_html',
			'std'     => SSTK()->sstk_get_cloud_provider_desc( get_post_meta( $id, 'sstk_server_provider', true ) ),
			'columns' => 2,
			'class'   => 'sstk_server_details_top_row',
		);

		$actions['general-welcome-top-col_4'] = array(
			'name'    => __( 'Region', 'simple-stack' ),
			'type'    => 'custom_html',
			'std'     => get_post_meta( $id, 'sstk_server_region', true ),
			'columns' => 2,
			'class'   => 'sstk_server_details_top_row',
		);

		$actions['general-welcome-top-col_5'] = array(
			'name'    => __( 'Apps', 'simple-stack' ),
			'type'    => 'custom_html',
			'std'     => sprintf( '<a href="%s" target="_blank">%d</a>', esc_url( admin_url( 'edit.php?post_type=sstk_app&server_id=' . $id ) ), SSTK_SERVER()->get_app_count( $id ) ),
			'columns' => 2,
			'class'   => 'sstk_server_details_top_row',
		);

		$actions['general-welcome-top-divider-01'] = array(
			'name'  => '',
			'type'  => 'custom_html',
			'std'   => '<hr />',
			'class' => 'sstk_server_details_top_row-divider',
		);

		return $actions;

	}

}

/* new SSTK_WORDPRESS_TABS_SERVER_GENERAL(); */
