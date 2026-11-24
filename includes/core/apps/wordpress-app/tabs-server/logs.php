<?php
/** Modified for Simple Stack by One Dog Solutions on 2026-11-23. */
/**
 * Logs tab
 *
 * @package simple-stack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SSTK_WORDPRESS_TABS_SERVER_LOGS
 */
class SSTK_WORDPRESS_TABS_SERVER_LOGS extends SSTK_WORDPRESS_TABS {

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
		return 'server-logs';
	}

	/**
	 * Returns a string that is the name of a view TEAM permission required to view this tab.
	 */
	public function get_view_tab_team_permission_slug() {
		return 'view_wpapp_server_logs_tab';
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
				'label' => __( 'Logs', 'simple-stack' ),
				'icon'  => 'fad fa-th-list',
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
	 * Gets the fields to be shown in the SERVER LOGS tab.
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

		return $this->get_fields_for_tab( $fields, $id, 'server-logs' );

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
		$valid_actions = array( 'server-logs-download' );
		if ( in_array( $action, $valid_actions, true ) ) {
			if ( ! $this->get_tab_security( $id ) ) {
				return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'simple-stack' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
			}
		}

		if ( $this->get_tab_security( $id ) ) {
			switch ( $action ) {
				case 'server-logs-download':
					$result = array();
					$result = $this->do_server_log_actions( $id, $action );
					// most actions need to refresh the page so that new data can be loaded or so that the data entered into data entry fields cleared out.
					if ( ! in_array( $action, array(), true ) && ! is_wp_error( $result ) ) {
						$result['refresh'] = 'yes';
					}
					break;
			}
		}

		return $result;
	}

	/**
	 * Gets the actions to be shown in the SERVER LOGS tab.
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	public function get_actions( $id ) {

		return $this->get_server_logs_fields( $id );

	}

	/**
	 * Gets the fields to shown in the SERVER LOGS tab in the server details screen.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_server_logs_fields( $id ) {

		if ( ! $id ) {
			// id not found!
			return array();
		}

		/* Array variable to hold our field definitions */
		$actions = array();

		/**
		 * Manage Server Logs
		 */

		$actions[] = sstk_start_half_card( $this->get_tab_slug() ); // Start new card.

		// Manage server logs heading.
		$desc = __( 'Download OpenLiteSpeed and system log files for this server.', 'simple-stack' );

		$actions['server-logs-header'] = array(
			/* Translators: %s is a fontawesome or similar icon. */
			'label'          => sstk_apply_logs_icon( __( '%s Download Logs', 'simple-stack' ) ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $desc,
			),
		);

		// List of logs for download.
		$actions['server-logs-log-name-select'] = array(
			'label'          => __( 'Select Log', 'simple-stack' ),
			// 'id'   => 'sstk_app_server_log_name',
			'type'           => 'select',
			'raw_attributes' => array(
				'options'        => $this->get_log_list( $id ),
				// the key of the field (the key goes in the request).
				'data-sstk-name' => 'server_log_name',
			),
		);

		$actions['server-logs-download'] = array(
			// 'id'   => 'sstk_app_action_server_log_download_button',
			// 'tab'  => 'server-logs',
			'label'          => '',
			'type'           => 'button',
			'raw_attributes' => array(
				/* Translators: %s is a fontawesome or similar icon. */
				'std'              => sstk_apply_download_icon( __( '%s Download', 'simple-stack' ) ),
				// the _action that will be called in ajax.
				// 'data-sstk-action' => 'server-log-download'.
				// the id.
				// 'data-sstk-id' => $id.
				// fields that contribute data for this action.
				'data-sstk-fields' => json_encode( array( '#sstk_app_action_server-logs-log-name-select' ) ),
			),
		);

		$actions[] = sstk_end_card( $this->get_tab_slug() ); // Close up prior card.

		/**
		 * Server logs warning.
		 */

		$actions[] = sstk_start_half_card( $this->get_tab_slug() ); // Start new card.

		$actions['server-logs-warning-header'] = array(
			/* Translators: %s is a fontawesome or similar icon. */
			'label'          => sstk_apply_warning_icon( __( '%s Warning', 'simple-stack' ) ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'Attempting to download very large log files can cause your server memory to be exhausted which will likely cause your server to kill this process or, worse, crash. Use this download tool only if you are sure your logs are of a reasonable size. Otherwise connect via sFTP or ssh to download logs.', 'simple-stack' ),
			),

		);

		$actions[] = sstk_end_card( $this->get_tab_slug() ); // Close up prior card.

		return $actions;

	}

	/**
	 * Performs the SERVER LOG action.
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return bool|\WP_Error  success/failure/other
	 */
	private function do_server_log_actions( $id, $action ) {

		// Get the instance details.
		$instance = $this->get_server_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'simple-stack' ), $action ) );
		}

		/* Grab the arguments sent from the front-end JS */
		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );

		/* Validate the log name against the fixed OpenLiteSpeed allowlist. */
		$log_name = isset( $args['server_log_name'] ) ? $args['server_log_name'] : '';
		$log_list = $this->get_log_list( $id );
		if ( ! isset( $log_list[ $log_name ] ) ) {
			return new \WP_Error( __( 'We were unable to validate the log file name - this might be a security concern!.', 'simple-stack' ) );
		}

		// Do the download...
		$result = $this->ssh()->do_file_download( $instance, $log_name );
		if ( is_wp_error( $result ) ) {
			return new \WP_Error( sprintf( __( 'Unable to perform action %1$s. It is possible that the file does not exist or that it is empty. Error message: %2$s. Error code: %3$s', 'simple-stack' ), $action, $result->get_error_message(), $result->get_error_code() ) );
		}

		// create log file and store it in temp folder.
		$log_file = sstk_get_log_file_without_extension( $log_name ) . '_' . time() . '.txt';
		$temppath = trailingslashit( $this->get_script_temp_path() );

		/* Put the log file into the temp folder... */
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;
		$filepath    = $temppath . $log_file;
		$file_result = $wp_filesystem->put_contents(
			$filepath,
			$result,
			false
		);

		/* Send the file name to the browser which will handle the download via JS */
		if ( $file_result ) {
			$file_url = trailingslashit( $this->get_script_temp_path_uri() ) . $log_file;
			$result   = array(
				'file_url'  => $file_url,
				'file_name' => $log_file,
				'file_data' => $result,
			);
		}

		return $result;

	}

	/**
	 * Return a key-value array of logs that we can retrieve for the server.
	 *
	 * @param int $id post id of server record.
	 *
	 * @return array
	 */
	public function get_log_list( $id ) {

		return array(
			'/usr/local/lsws/logs/access.log' => __( 'OpenLiteSpeed Access Log', 'simple-stack' ),
			'/usr/local/lsws/logs/error.log'  => __( 'OpenLiteSpeed Error Log', 'simple-stack' ),
			'/usr/local/lsws/logs/stderr.log' => __( 'OpenLiteSpeed Standard Output Error Log', 'simple-stack' ),
			'/var/log/mysql/error.log'        => __( 'MariaDB Error Log', 'simple-stack' ),
			'/var/log/wp-backup.log'          => __( 'Site WordPress Backup Logs', 'simple-stack' ),
			'/var/log/wp-full-backup.log'     => __( 'Full Server WordPress Backup Logs', 'simple-stack' ),
			'/var/log/syslog'                 => __( 'System Log', 'simple-stack' ),
			'/var/log/unattended-upgrades/unattended-upgrades.log' => __( 'Unattended Upgrades Summary Log', 'simple-stack' ),
			'/var/log/unattended-upgrades/unattended-upgrades-dpkg.log' => __( 'Unattended Upgrades Detail Log', 'simple-stack' ),
			'/var/log/dpkg.log'               => __( 'Recently Installed or Removed Packages (dpkg.log)', 'simple-stack' ),
			'/var/log/redis/redis-server.log' => __( 'Redis Log', 'simple-stack' ),
			'/var/log/auth.log'               => __( 'Authorization Log', 'simple-stack' ),
			'/var/log/ufw.log'                => __( 'UFW Firewall Log', 'simple-stack' ),
			'/var/log/wp-server-status.log'   => __( 'Simple Stack Server Status Callback Log', 'simple-stack' ),
		);
	}

}

new SSTK_WORDPRESS_TABS_SERVER_LOGS();
