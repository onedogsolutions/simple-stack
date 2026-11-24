<?php
/** Modified for Simple Stack by One Dog Solutions on 2026-11-23. */
/**
 * Logs tab.
 *
 * @package simple-stack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This tab is a bit different from others because only users
 * who can manage a server is allowed to operate this tab.
 * System users have the whole run of the server so not good
 * to have non-server users be able to operate here.
 */

/**
 * Class SSTK_WORDPRESS_TABS_SITE_LOGS
 */
class SSTK_WORDPRESS_TABS_SITE_LOGS extends SSTK_WORDPRESS_TABS {

	/**
	 * SSTK_WORDPRESS_TABS_SITE_SYSTEM_USERS constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_filter( "sstk_app_{$this->get_app_name()}_get_tabnames", array( $this, 'get_tab' ), 10, 2 );
		add_filter( "sstk_app_{$this->get_app_name()}_get_tabs", array( $this, 'get_fields' ), 10, 2 );
		add_filter( "sstk_app_{$this->get_app_name()}_tab_action", array( $this, 'tab_action' ), 10, 3 );
	}

	/**
	 * Returns a string that can be used as the unique name for this tab.
	 */
	public function get_tab_slug() {
		return 'site-logs';
	}

	/**
	 * Returns a string that is the name of a view TEAM permission required to view this tab.
	 */
	public function get_view_tab_team_permission_slug() {
		return 'view_wpapp_site_logs_tab';
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
	 * @param int $id The post ID of the site.
	 *
	 * @return boolean
	 */
	public function get_tab_security( $id ) {
		// If admin has an admin lock in place and the user is not admin they cannot view the tab or perform actions on them.
		if ( $this->get_admin_lock_status( $id ) && ! sstk_is_admin() ) {
			return false;
		}
		// If we got here then check team and other permissions.
		return ( true === $this->sstk_wpapp_site_user_can( $this->get_view_tab_team_permission_slug(), $id ) && true === $this->sstk_can_author_view_site_tab( $id, $this->get_tab_slug() ) );
	}

	/**
	 * Called when an action needs to be performed on the tab.
	 *
	 * @param mixed  $result The default value of the result.
	 * @param string $action The action to be performed.
	 * @param int    $id The post ID of the app.
	 *
	 * @return mixed    $result The default value of the result.
	 */
	public function tab_action( $result, $action, $id ) {

		/* Verify that the user is even allowed to view the app before proceeding to do anything else */
		if ( ! $this->sstk_user_can_view_wp_app( $id ) ) {
			return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'simple-stack' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
		}

		/* Now verify that the user can perform actions on this screen, assuming that they can view the server */
		$valid_actions = array( 'site-log-download' );
		if ( in_array( $action, $valid_actions, true ) ) {
			if ( ! $this->get_tab_security( $id ) ) {
				return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'simple-stack' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
			}
		}

		if ( $this->get_tab_security( $id ) ) {
			switch ( $action ) {
				case 'site-log-download':
					$result = array();
					$result = $this->do_site_log_actions( $action, $id );
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
	 * Gets the fields to be shown in the LOGS tab.
	 *
	 * @param array $fields fields.
	 * @param int   $id id.
	 */
	public function get_fields( array $fields, $id ) {

		// If user is not allowed to access the tab then don't paint the fields.
		if ( ! $this->get_tab_security( $id ) ) {
			return $fields;
		}

		return array_merge(
			$fields,
			$this->get_site_logs_fields( $id )
		);

	}

	/**
	 * Gets the fields to be shown in the LOGS area of the tab.
	 *
	 * @param int $id id.
	 */
	public function get_site_logs_fields( $id ) {

		if ( ! $id ) {
			// id not found!
			return array();
		}

		// Bail if site is not enabled.
		if ( ! $this->is_site_enabled( $id ) ) {
			return $this->get_disabled_header_field( 'site-logs' );
		}

		return $this->download_logs_fields( $id );

	}

	/**
	 * Gets the fields to be shown in the DOWNLOAD LOGS area of the tab.
	 *
	 * @param int $id id.
	 */
	public function download_logs_fields( $id ) {

		if ( ! $id ) {
			// id not found!
			return array();
		}

		/* Array variable to hold our field definitions */
		$fields = array();

		// Start new card.
		$fields[] = sstk_start_half_card( $this->get_tab_slug() );

		// Heading text.
		$desc = __( 'Download various log files for this site.', 'simple-stack' );

		$fields[] = array(
			'name' => __( 'Download Logs', 'simple-stack' ),
			'desc' => $desc,
			'tab'  => 'site-logs',
			'type' => 'heading',
		);

		// Get the domain name for this app - we'll need it later.
		$domain = get_post_meta( $id, 'wpapp_domain', true );

		// List of logs for download.
		$fields[] = array(
			'name'       => __( 'Select Log', 'simple-stack' ),
			'id'         => 'sstk_app_site_log_name',
			'tab'        => 'site-logs',
			'type'       => 'select',
			'save_field' => false,
			'attributes' => array(
				// the key of the field (the key goes in the request).
				'data-sstk-name' => 'site_log_name',
			),
			'options'    => $this->get_log_list( $id ),
		);

		$fields[] = array(
			'id'         => 'sstk_app_action_site_log_download_button',
			'tab'        => 'site-logs',
			'type'       => 'button',
			'std'        => __( 'Download', 'simple-stack' ),
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-sstk-action' => 'site-log-download',
				// the id.
				'data-sstk-id'     => $id,
				// fields that contribute data for this action.
				'data-sstk-fields' => json_encode( array( '#sstk_app_site_log_name' ) ),
			),
			'class'      => 'sstk_app_action',
			'save_field' => false,
		);

		// Close up prior card.
		$fields[] = sstk_end_card( $this->get_tab_slug() );

		// Start new card.
		$fields[] = sstk_start_half_card( $this->get_tab_slug() );

		$fields[] = array(
			'name' => __( 'Warning', 'simple-stack' ),
			'desc' => __( 'Attempting to download very large log files can cause your server memory to be exhausted which will likely cause your server to kill this process or, worse, crash. Use this download tool only if you are sure your logs are of a reasonable size. Otherwise connect via sFTP or ssh to download logs.', 'simple-stack' ),
			'tab'  => 'site-logs',
			'type' => 'heading',
		);

		// Close up prior card.
		$fields[] = sstk_end_card( $this->get_tab_slug() );

		return $fields;

	}

	/**
	 * Performs the SITE LOG action.
	 *
	 * @param array $action action.
	 * @param int   $id post id of site record.
	 */
	private function do_site_log_actions( $action, $id ) {

		// Get the instance details.
		$instance = $this->get_app_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			/* Translators: %s is an internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'simple-stack' ), $action ) );
		}

		/* Grab the arguments sent from the front-end JS */
		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );

		// Get the domain...
		$domain = get_post_meta( $id, 'wpapp_domain', true );

		/* Make sure the log name has not been tampered with. We will not be escaping the log file name since we can validate it against our own known good list. */
		if ( ! isset( $this->get_log_list( $id )[ $args['site_log_name'] ] ) ) {
			return new \WP_Error( __( 'We were unable to validate the log file name - this might be a security concern!.', 'simple-stack' ) );
		}

		// Make sure we actually have a domain name.
		if ( empty( $domain ) ) {
			return new \WP_Error( __( 'We were unable to get the domain needed for this action.', 'simple-stack' ) );
		}

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			/* Translators: %s is an internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'simple-stack' ), $action ) );
		}

		// Do the download...
		$result = $this->ssh()->do_file_download( $instance, $args['site_log_name'] );  // We have to send the unescaped file name.
		if ( is_wp_error( $result ) ) {
			return new \WP_Error( sprintf( __( 'Unable to perform action %1$s. It is possible that the file does not exist or that it is empty. Error message: %2$s. Error code: %3$s', 'simple-stack' ), $action, $result->get_error_message(), $result->get_error_code() ) );
		}

		// create log file and store it in temp folder.
		$log_file = sstk_get_log_file_without_extension( $args['site_log_name'] ) . '_' . time() . '.txt';
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
	 * Return a key-value array of logs that we can retrieve for the site.
	 *
	 * @param int $id id.
	 */
	public function get_log_list( $id ) {
		$domain = get_post_meta( $id, 'wpapp_domain', true );
		return array(
			"/var/www/$domain/html/wp-content/debug.log" => __( 'debug.log', 'simple-stack' ),
			'other'                                      => __( 'For Future Use', 'simple-stack' ),
		);
	}

}

new SSTK_WORDPRESS_TABS_SITE_LOGS();
