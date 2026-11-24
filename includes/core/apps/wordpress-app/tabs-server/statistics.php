<?php
/** Modified for Simple Stack by One Dog Solutions on 2026-11-23. */
/**
 * Statistics Tab
 *
 * @package simple-stack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SSTK_WORDPRESS_TABS_SERVER_STATISTICS
 */
class SSTK_WORDPRESS_TABS_SERVER_STATISTICS extends SSTK_WORDPRESS_TABS {

	/**
	 * SSTK_WORDPRESS_TABS_PHP constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_filter( "sstk_server_{$this->get_app_name()}_get_tabnames", array( $this, 'get_tab' ), 10, 2 );
		add_filter( "sstk_server_{$this->get_app_name()}_get_tabs", array( $this, 'get_tab_fields' ), 10, 2 );
		add_filter( "sstk_server_{$this->get_app_name()}_tab_action", array( $this, 'tab_action_server' ), 10, 3 );  // This filter has not been defined and called yet in classs-wordpress-app and might never be because we're using the one below.
		add_filter( "sstk_app_{$this->get_app_name()}_tab_action", array( $this, 'tab_action' ), 10, 3 );  // This filter says 'sstk_app' because we're using the same functions for server details ajax tabs and app details ajax tabs.

	}

	/**
	 * Returns a string that can be used as the unique name for this tab.
	 */
	public function get_tab_slug() {
		return 'svr_statistics';
	}

	/**
	 * Returns a string that is the name of a view TEAM permission required to view this tab.
	 */
	public function get_view_tab_team_permission_slug() {
		return 'view_wpapp_server_statistics_tab';
	}

	/**
	 * Populates the tab name.
	 *
	 * @param array $tabs The default value.
	 * @param int   $id   The post ID of the server.
	 *
	 * @return array    $tabs The default value.
	 */
	public function get_tab( $tabs, $id ) {
		if ( $this->get_tab_security( $id ) ) {
			$tabs[ $this->get_tab_slug() ] = array(
				'label' => __( 'Statistics', 'simple-stack' ),
				'icon'  => 'fad fa-chart-bar',
			);
		}
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
	 * Gets the fields to be shown in the STATISTICS tab.
	 *
	 * Filter hook: sstk_app_{$this->get_app_name()}_get_tabs
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

		return $this->get_fields_for_tab( $fields, $id, 'svr_statistics' );

	}

	/**
	 * Called when an action needs to be performed on the tab.
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

		/* Now verify that the user can perform actions on this screen, assuming that they can view the server */
		$valid_actions = array( 'server-refresh-statistics' );
		if ( in_array( $action, $valid_actions, true ) ) {
			if ( ! $this->get_tab_security( $id ) ) {
				return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'simple-stack' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
			}
		}

		if ( $this->get_tab_security( $id ) ) {
			switch ( $action ) {
				case 'server-refresh-statistics':
					$result = $this->refresh_statistics( $id, $action );
					break;
			}
		}
		return $result;
	}

	/**
	 * Gets the actions to be shown in the FIREWALL tab.
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	public function get_actions( $id ) {

		return $this->get_statistics_fields( $id );

	}

	/**
	 * Gets the fields to shown in the FIREWALL tab in the server details screen.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_statistics_fields( $id ) {

		$actions = array();

		/* Set the text of the confirmation prompt */
		$confirmation_prompt = __( 'Are you sure you would like to refresh the statistics for this server?', 'simple-stack' );

		/* Get the last date stats were run */
		$last_statistics_date = get_post_meta( $id, 'sstk_wpapp_server_stats_list_date', true );
		if ( empty( $last_statistics_date ) ) {
			$desc = __( 'Statistics have never been collected for this server. Push the button above to get some.', 'simple-stack' );
		} else {
			$desc = sprintf( __( 'Statistics for this server was last refreshed on %s', 'simple-stack' ), $last_statistics_date );
		}

		$actions['server-refresh-statistics'] = array(
			'label'          => __( 'Refresh Server Statistics', 'simple-stack' ),
			'raw_attributes' => array(
				'std'                 => __( 'Refresh', 'simple-stack' ),
				'confirmation_prompt' => $confirmation_prompt,
				'desc'                => $desc,
			),
			'type'           => 'button',
		);

		/* Disk Space Used */

		/* Get any disk space data we might have stored already */
		$disk_statistics = get_post_meta( $id, 'sstk_wpapp_disk_statistics', true );
		if ( ! empty( $disk_statistics ) ) {
			$desc = __( 'Viewing the used and available diskspace on this server.', 'simple-stack' );
		} else {
			$desc = __( 'View the used and available diskspace on this server.  However, no disk statistics are available yet. Push the REFRESH button at the top of this page to get some!', 'simple-stack' );
		}

		$actions['show-server-diskspace-used-header'] = array(
			'label'          => __( 'Disk Space', 'simple-stack' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $desc,
			),
		);

		if ( ! empty( $disk_statistics ) ) {

			$disk_stat_switch  = '<a href="" class="sstk-stat-switch active" data-section-to-hide="#sstk-diskstatistics-text" data-section-to-show="#sstk-diskstatistics-chart">' . __( 'Chart', 'simple-stack' ) . '</a>';
			$disk_stat_switch .= '|<a href="" class="sstk-stat-switch" data-section-to-hide="#sstk-diskstatistics-chart" data-section-to-show="#sstk-diskstatistics-text">' . __( 'Text', 'simple-stack' ) . '</a>';

			$actions['server-disk-stats-switch'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std' => '<div class="sstk-stat-switch-wrapper">' . $disk_stat_switch . '</div>',
				),
				'type'           => 'custom_html',
			);

			$actions['server-disk-stats-data'] = array(
				'label'          => __( 'Disk Statistics', 'simple-stack' ),
				'raw_attributes' => array(
					'std' => '<div id="sstk-diskstatistics-text">' . $disk_statistics . '</div>',
				),
				'type'           => 'custom_html',
			);

			$actions['server-disk-stats-data-chart'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std' => '<div id="sstk-diskstatistics-chart"><canvas id="sstk-diskstatistics-canvas"></canvas></div>',
				),
				'type'           => 'custom_html',
			);
		}

		/* VNSTAT */

		/* Get any VNstat data we might have stored */
		$vnstat = get_post_meta( $id, 'sstk_wpapp_vnstat_data', true );
		if ( ! empty( $vnstat ) ) {
			$desc = __( 'VNSTAT is a lightweight traffic statistics program in Linux that provides a formatted text output. You are viewing those statistics here.', 'simple-stack' );
		} else {
			$desc = __( 'VNSTAT is a lightweight traffic statistics program in Linux that provides a formatted text output. However, no statistics are available yet. Push the REFRESH button at the top of this page to get some!', 'simple-stack' );
		}

		$actions['server-show-vnstat-header'] = array(
			'label'          => __( 'VNSTAT Traffic', 'simple-stack' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $desc,
			),
		);

		if ( ! empty( $vnstat ) ) {

			$vnstat_switch  = '<a href="" class="sstk-stat-switch active" data-section-to-hide="#sstk-vnstat-text" data-section-to-show="#sstk-vnstat-data-day-canvas">' . __( 'Chart', 'simple-stack' ) . '</a>';
			$vnstat_switch .= '|<a href="" class="sstk-stat-switch" data-section-to-hide="#sstk-vnstat-data-day-canvas" data-section-to-show="#sstk-vnstat-text">' . __( 'Text', 'simple-stack' ) . '</a>';

			$actions['server-vnstats-switch'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std' => '<div class="sstk-stat-switch-wrapper">' . $vnstat_switch . '</div>',
				),
				'type'           => 'custom_html',
			);

			$actions['server-vnstat-data'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std' => '<div id="sstk-vnstat-text">' . $vnstat . '</div>',
				),
				'type'           => 'custom_html',
			);

			$actions['server-vnstat-data-chart-day'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'     => '<canvas id="sstk-vnstat-data-day-canvas"></canvas>',
					'columns' => 4,
				),
				'type'           => 'custom_html',
				'name'           => '',
			);

			$actions['server-vnstat-data-chart-month'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'     => '<canvas id="sstk-vnstat-data-month-canvas"></canvas>',
					'columns' => 4,
				),
				'type'           => 'custom_html',
				'name'           => '',
			);

			$actions['server-vnstat-data-chart-all'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'     => '<canvas id="sstk-vnstat-data-all-canvas"></canvas>',
					'columns' => 4,
				),
				'type'           => 'custom_html',
				'name'           => '',
			);

		}
		/* End VNSTAT */

		/* VMSTAT */

		/* Get any VMSTAT data we might have stored */
		$vmstat = get_post_meta( $id, 'sstk_wpapp_vmstat_data', true );
		if ( ! empty( $vmstat ) ) {
			$desc = __( 'Memory data - Output from the standard Linux VMSTAT command.', 'simple-stack' );
		} else {
			$desc = __( 'Memory data - Output from the standard Linux VMSTAT command. However, no statistics are available yet. Push the REFRESH button at the top of this page to get some!', 'simple-stack' );
		}

		$actions['server-show-vmstat-header'] = array(
			'label'          => __( 'VMSTAT (Memory)', 'simple-stack' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $desc,
			),
		);

		if ( ! empty( $vmstat ) ) {

			$vmstat_switch  = '<a href="" class="sstk-stat-switch active" data-section-to-hide="#sstk-vmstat-text" data-section-to-show="#sstk-vmstat-chart">' . __( 'Chart', 'simple-stack' ) . '</a>';
			$vmstat_switch .= '|<a href="" class="sstk-stat-switch" data-section-to-hide="#sstk-vmstat-chart" data-section-to-show="#sstk-vmstat-text">' . __( 'Text', 'simple-stack' ) . '</a>';

			$actions['server-vmstat-switch'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std' => '<div class="sstk-stat-switch-wrapper">' . $vmstat_switch . '</div>',
				),
				'type'           => 'custom_html',
			);

			$actions['server-vmstat-data'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std' => '<div id="sstk-vmstat-text">' . $vmstat . '</div>',
				),
				'type'           => 'custom_html',
			);

			$actions['server-vmstat-data-chart'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std' => '<div id="sstk-vmstat-chart"><canvas id="sstk-vmstat-canvas"></canvas></div>',
				),
				'type'           => 'custom_html',
			);

		}
		/* End VMSTAT */

		/* TOP */

		/* Get any TOP data we might have stored */
		$top = get_post_meta( $id, 'sstk_wpapp_top_data', true );
		if ( ! empty( $top ) ) {
			$desc = __( 'Output from the standard Linux TOP command', 'simple-stack' );
		} else {
			$desc = __( 'Output from the standard Linux TOP command. However, no statistics are available yet. Push the button below to get some!', 'simple-stack' );
		}

		$actions['server-show-top-header'] = array(
			'label'          => __( 'TOP', 'simple-stack' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $desc,
			),
		);

		if ( ! empty( $top ) ) {
			$actions['server-top-data'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std' => $top,
				),
				'type'           => 'custom_html',
			);
		}
		/* End TOP */

		return $actions;

	}

	/**
	 * Refresh Server Statistics
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean success/failure/other
	 */
	private function refresh_statistics( $id, $action ) {

		$instance = $this->get_server_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'simple-stack' ), $action ) );
		}

		/* Execute command for vnstat */
		$result = $this->execute_ssh( 'generic', $instance, array( 'commands' => 'sudo vnstat' ) );

		// update the data...
		update_post_meta( $id, 'sstk_wpapp_vnstat_data', '<pre>' . $result . '<pre/>' );

		/* Execute command for vnstat - oneline */
		$result = $this->execute_ssh( 'generic', $instance, array( 'commands' => 'sudo vnstat --oneline' ) );

		// update the data...
		update_post_meta( $id, 'sstk_wpapp_vnstat_one_line_data', '<pre>' . $result . '<pre/>' );

		/* Execute command for disk free space */
		$result = $this->execute_ssh( 'generic', $instance, array( 'commands' => 'sudo df' ) );

		// update the data...
		update_post_meta( $id, 'sstk_wpapp_disk_statistics', '<pre>' . $result . '<pre/>' );

		/* Execute command for vmstat (memory) */
		$result = $this->execute_ssh( 'generic', $instance, array( 'commands' => 'sudo vmstat -s' ) );

		// update the data...
		update_post_meta( $id, 'sstk_wpapp_vmstat_data', '<pre>' . $result . '<pre/>' );

		/* Execute command for TOP */
		$result = $this->execute_ssh( 'generic', $instance, array( 'commands' => ' sudo top -n 1 -b > top-output.txt && sudo cat top-output.txt  && sudo rm top-output.txt' ) );

		// update the data...
		update_post_meta( $id, 'sstk_wpapp_top_data', '<pre>' . $result . '<pre/>' );

		// Finally, record the date that we got these stats!
		update_post_meta( $id, 'sstk_wpapp_server_stats_list_date', date( 'Y-m-d H:i:s', current_time( 'timestamp' ) ) );

		// Return the data as an error so it can be shown in a dialog box.
		return new \WP_Error( __( 'Server statistics have been collected. This page will now refresh.  Navigate back to this tab to see the new data!', 'simple-stack' ) );

	}

}

new SSTK_WORDPRESS_TABS_SERVER_STATISTICS();
