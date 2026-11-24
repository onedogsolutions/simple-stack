<?php
/** Modified for Simple Stack by One Dog Solutions on 2026-11-23. */
/**
 * Upgrade Tab
 *
 * @package simple-stack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Class SSTK_WORDPRESS_TABS_SERVER_UPGRADE
 */
class SSTK_WORDPRESS_TABS_SERVER_UPGRADE extends SSTK_WORDPRESS_TABS {

	/**
	 * SSTK_WORDPRESS_TABS_PHP constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_filter( "sstk_server_{$this->get_app_name()}_get_tabnames", array( $this, 'get_tab' ), 10, 2 );
		add_filter( "sstk_server_{$this->get_app_name()}_get_tabs", array( $this, 'get_tab_fields' ), 10, 2 );
		add_filter( "sstk_server_{$this->get_app_name()}_tab_action", array( $this, 'tab_action_server' ), 10, 3 );  // This filter has not been defined and called yet in classs-wordpress-app and might never be because we're using the one below.
		add_filter( "sstk_app_{$this->get_app_name()}_tab_action", array( $this, 'tab_action' ), 10, 3 );  // This filter says 'sstk_app' because we're using the same functions for server details ajax tabs and app details ajax tabs.

		// Add bulk action option to the server list screen to run linux updates.
		add_filter( 'bulk_actions-edit-sstk_app_server', array( $this, 'sstk_add_new_bulk_actions_server' ) );

		// Action hook to handle bulk actions for server. For example to run linux updates.
		add_filter( 'handle_bulk_actions-edit-sstk_app_server', array( $this, 'sstk_bulk_action_handler_server_app' ), 10, 3 );

		// Allow the update cron action to be triggered via an action hook.
		add_action( 'sstk_wordpress-upgrade_linux_all', array( $this, 'upgrade_linux_all' ), 10, 2 );

		/* Pending Logs Background Task: Trigger all linux updates. */
		add_action( 'pending_log_apply_all_linux_updates', array( $this, 'pending_log_apply_all_linux_updates' ), 10, 3 );

		/* Pending Logs Background Task: Trigger linux security updates. */
		add_action( 'pending_log_apply_security_linux_updates', array( $this, 'pending_log_apply_linux_security_updates' ), 10, 3 );

		/* Handle callback success and tag the pending log record as successful */
		add_action( 'sstk_server_wordpress-app_upgrade_linux_all_action_successful', array( $this, 'handle_upgrade_linux_all_success' ), 10, 3 );
		add_action( 'sstk_server_wordpress-app_upgrade_linux_security_action_successful', array( $this, 'handle_upgrade_linux_all_success' ), 10, 3 );

		/* Handle callback failures and tag the pending log record as failed */
		add_action( 'sstk_server_wordpress-app_upgrade_linux_security_action_failed', array( $this, 'handle_upgrade_linux_all_failed' ), 10, 3 );
		add_action( 'sstk_server_wordpress-app_upgrade_linux_all_action_failed', array( $this, 'handle_upgrade_linux_all_failed' ), 10, 3 );

	}

	/**
	 * Returns a string that can be used as the unique name for this tab.
	 */
	public function get_tab_slug() {
		return 'server_upgrade';
	}

	/**
	 * Returns a string that is the name of a view TEAM permission required to view this tab.
	 */
	public function get_view_tab_team_permission_slug() {
		return 'view_wpapp_server_upgrade_tab';
	}

	/**
	 * Populates the tab name.
	 *
	 * @param array $tabs   The default value.
	 * @param int   $id     The post ID of the server.
	 *
	 * @return array    $tabs   New array of tabs
	 */
	public function get_tab( $tabs, $id ) {
		if ( $this->get_tab_security( $id ) ) {
			$tabs[ $this->get_tab_slug() ] = array(
				'label' => __( 'Upgrades', 'simple-stack' ),
				'icon'  => 'fad fa-chart-network',
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
	 * Gets the fields to be shown in the UPGRADE tab.
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

		return $this->get_fields_for_tab( $fields, $id, $this->get_tab_slug() );

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
			/* translators: %1s is replaced with an internal action name; %2$s is replaced with the file name; %3$s is replaced with the post id being acted on. %4$s is the user id running this action. */
			return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'simple-stack' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
		}

		// Skipping security action check that would allow us to show the user a nice message if it failed.
		// If user is not permitted to do something and actually somehow ends up here, it will fall through the SWITCH statement below and silently fail.

		// Perform actions if allowed to do so.
		if ( $this->get_tab_security( $id ) ) {
			switch ( $action ) {
				case 'run-linux-updates-all':
					$action = 'run_updates_cron';  // The bash script expects this.
					$result = $this->upgrade_linux_all( $id, $action );
					break;
				case 'run-linux-updates-security':
					$action = 'run_security_updates_cron';  // The bash script expects this.
					$result = $this->upgrade_linux_all( $id, $action );
					break;

				case 'server-upgrade-php83':
					$result = $this->install_php83( $id, $action );
					break;

				case 'server-upgrade-wpcli':
					$result = $this->upgrade_wpcli( $id, $action );
					break;

			}
		}

		return $result;
	}

	/**
	 * Gets the actions to be shown in the UPGRADE tab.
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	public function get_actions( $id ) {

		return $this->get_upgrade_fields( $id );

	}

	/**
	 * Gets the fields to show in the UPGRADE tab in the server details screen.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_upgrade_fields( $id ) {

		$actions = $this->get_upgrade_fields_default( $id );

		// PHP 8.3.
		if ( ! $this->is_php_83_installed( $id ) ) {
			$upgrade_php_83_fields = $this->get_upgrade_fields_php83( $id );
			$actions               = array_merge( $actions, $upgrade_php_83_fields );
		}

		// WP-CLI Upgrade Options.
		if ( ! $this->is_wpcli210_installed( $id ) ) {
			$upgrade_wpcli_fields = $this->get_upgrade_fields_wpcli( $id );
			$actions              = array_merge( $actions, $upgrade_wpcli_fields );
		}

		// Linux Updates.
		$upgrade_linux_fields = $this->get_upgrade_fields_linux( $id );
		$actions              = array_merge( $actions, $upgrade_linux_fields );

		// Get Upgrade History Fields.
		$upgrade_history_fields = $this->get_upgrade_history_fields( $id );
		if ( ! empty( $upgrade_history_fields ) ) {
			$actions = array_merge( $actions, $upgrade_history_fields );
		}

		return $actions;

	}

	/**
	 * Gets the fields to show in the UPGRADE tab in the server details screen.
	 * These fields are for LINUX level upgrades.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_upgrade_fields_linux( $id ) {

		// Set up metabox items.
		$actions = array();

		$upg_desc  = __( 'Set a cron on the server to immediately run all pending LINUX updates. There will be no interactive feedback as the updates are run in the background.', 'simple-stack' );
		$upg_desc .= '<br />';
		$upg_desc .= __( 'If you need interactive feedback you should log into the server via SSH and run the updates from the command line.', 'simple-stack' );
		$upg_desc .= '<br />';
		$upg_desc .= __( 'To view the results of the updates you will also need to log into the server via SSH and examine the log files.', 'simple-stack' );
		$upg_desc .= '<br />';
		$upg_desc .= __( 'Please note: Security updates are usually run automatically every night. Therefore you should only use this option to force them to run immediately or to run all updates.', 'simple-stack' );

		// Get the number of updates pending...
		// To do this, first get server status data from meta.
		$server_status_items = get_post_meta( $id, 'sstk_server_status_push', true );

		$pending_updates          = '';
		$secupdates               = '';
		$pending_security_updates = '';
		if ( ! empty( $server_status_items ) ) {

			if ( isset( $server_status_items['total_memory'] ) ) {
				$memory = $server_status_items['total_memory'];

				if ( (int) $memory > 0 ) {
					// We have RAM information which means the server status callback reported at least once and we can safely assume that other status information is available.

					if ( isset( $server_status_items['security_updates'] ) ) {
						$secupdates = $server_status_items['security_updates'];
						/* translators: %s is replaced with the number of security updates available. */
						$pending_security_updates .= sprintf( __( 'There are %s Security Updates ', 'simple-stack' ), $secupdates );

						if ( isset( $server_status_items['total_updates'] ) ) {
							/* translators: %s is replaced with the total number of updates available. */
							$pending_updates .= sprintf( __( ' There are %s updates pending.', 'simple-stack' ), $server_status_items['total_updates'] );
						}
					}
				}
			}
		}
		// End get number of updates pending.

		$actions['linux-updates-header'] = array(
			'label'          => __( '(Optional) Run Linux Updates', 'simple-stack' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $upg_desc,
			),
		);

		$actions['run-linux-updates-all'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Run All Linux Updates Now', 'simple-stack' ),
				// make sure we give the user a confirmation prompt.
				'confirmation_prompt' => __( 'Are you sure you would like to run the linux updates for this server?', 'simple-stack' ),
				'desc'                => $pending_updates,
			),
			'type'           => 'button',
			'desc'           => $pending_updates,
		);

		$actions['run-linux-updates-security'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Run Linux Security Updates Only', 'simple-stack' ),
				// make sure we give the user a confirmation prompt.
				'confirmation_prompt' => __( 'Are you sure you would like to run the linux security updates for this server?', 'simple-stack' ),
				'desc'                => $pending_security_updates,
			),
			'type'           => 'button',
			'desc'           => $pending_updates,
		);

		return $actions;

	}

	/**
	 * Gets the fields to show in the UPGRADE tab in the server details screen
	 * if PHP 8.3 needs to be installed.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_upgrade_fields_php83( $id ) {

		// Set up metabox items.
		$actions = array();

		$upg_desc  = __( 'Use this button to install PHP 8.3 if it is missing from this server.', 'simple-stack' );
		$upg_desc .= '<br />';
		$upg_desc .= __( 'OpenLiteSpeed will be restarted after installation so that the new LSPHP handler is available.', 'simple-stack' );

		$actions['server-upgrade-header-php83'] = array(
			'label'          => __( '(Optional) Install PHP 8.3', 'simple-stack' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $upg_desc,
			),
		);

		$actions['server-upgrade-php83'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Install PHP 8.3 on this server', 'simple-stack' ),
				// make sure we give the user a confirmation prompt.
				'confirmation_prompt' => __( 'Are you sure you would like to install PHP 8.3 on this server?', 'simple-stack' ),
			),
			'type'           => 'button',
		);

		/*
		$actions['server-upgrade-php83-meta'] = array(
		'label'          => '',
		'raw_attributes' => array(
			'std'                 => __( 'Remove PHP 8.3 Install Option', 'simple-stack' ),
			'desc'                => __( 'Tag server as having PHP 8.3 installed.', 'simple-stack' ),
			// make sure we give the user a confirmation prompt.
			'confirmation_prompt' => __( 'Are you sure you would like to tag this server as being upgraded without running the upgrade script?', 'simple-stack' ),
		),
		'type'           => 'button',
		);
		*/

		return $actions;

	}


	/**
	 * Gets the fields to show in the UPGRADE tab in the server details screen
	 * when wpcli needs to be upgraded.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_upgrade_fields_wpcli( $id ) {

		// Set up metabox items.
		$actions = array();

		$upg_desc  = __( 'Use this button to upgrade WP-CLI to v2.10.', 'simple-stack' );
		$upg_desc .= '<br />';
		$upg_desc .= __( 'If your server already has the latest version this will have no effect.', 'simple-stack' );

		$actions['server-upgrade-header-wpcli'] = array(
			'label'          => __( 'Upgrade WPCLI', 'simple-stack' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $upg_desc,
			),
		);

		$actions['server-upgrade-wpcli'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Upgrade WPCLI', 'simple-stack' ),
				// make sure we give the user a confirmation prompt.
				'confirmation_prompt' => __( 'Are you sure you would like to upgrade WPCLI on this server?', 'simple-stack' ),
			),
			'type'           => 'button',
		);

		/*
		$actions['server-upgrade-wpcli-meta'] = array(
		'label'          => '',
		'raw_attributes' => array(
			'std'                 => __( 'Remove WPCLI Upgrade Option', 'simple-stack' ),
			'desc'                => __( 'Tag server as having WPCLI upgraded.', 'simple-stack' ),
			// make sure we give the user a confirmation prompt.
			'confirmation_prompt' => __( 'Are you sure you would like to tag this server as being upgraded without running the upgrade script?', 'simple-stack' ),
		),
		'type'           => 'button',
		);
		*/

		return $actions;

	}

	/**
	 * Gets the fields to show in the UPGRADE tab in the server details screen
	 * when there are no upgrades to be done.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_upgrade_fields_default( $id ) {

		// Set up metabox items.
		$actions = array();

		$upg_desc  = __( 'There are no urgent or mandatory configuration updates required for this server.', 'simple-stack' );
		$upg_desc .= '<br />';

		$actions['server-upgrade-header'] = array(
			'label'          => __( 'Upgrade Server', 'simple-stack' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $upg_desc,
			),
		);

		return $actions;

	}

	/**
	 * Gets the history data to show in the UPGRADE tab in the server details screen.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array|boolean Array with history.
	 */
	private function get_upgrade_history_fields( $id ) {

		$history_string = $this->get_formatted_update_history( $id );

		if ( empty( $history_string ) ) {
			return false;
		}

		// Set up metabox items.
		$actions = array();

		$history_desc = __( 'History of maintenance actions performed on this server.', 'simple-stack' );

		$actions['server-upgrade-header-history'] = array(
			'label'          => __( 'Upgrade History', 'simple-stack' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $history_desc,
			),
		);

		$actions['server-upgrade-history-details'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std' => $history_string,
			),
			'type'           => 'custom_html',
		);

		return $actions;
	}

	/**
	 * Run install script for PHP 8.3
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean success/failure/other
	 */
	public function install_php83( $id, $action ) {

		// Upgrade History Type.
		$upgrade_history_key_type = 'install-php83';
		$upgrade_description      = __( 'Installed PHP 8.3', 'simple-stack' );

		// Get data about the server.
		$instance = $this->get_server_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'simple-stack' ), $action ) );
		}

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'run_upgrade_install_php_83.txt',
			array(
				'action'      => $action,
				'interactive' => 'no',
			)
		);

		// log.
		// phpcs:ignore
		do_action( 'sstk_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false ); //PHPcs warning normally issued because of print_r

		// execute.
		$result = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );

		// Make sure we don't have a wp_error object being returned...
		if ( is_wp_error( $result ) ) {
			return new \WP_Error( __( 'There was a problem installing PHP 8.3 - please check the server logs for more information.', 'simple-stack' ) );
		}

		// Evaluate results.
		if ( strpos( $result, 'journalctl -xe' ) !== false ) {
			/* translators: %s is replaced with the text of the result of the operation. */
			return new \WP_Error( sprintf( __( 'There was a problem restarting OpenLiteSpeed after installing PHP 8.3 - here is the full output of the install process: %s', 'simple-stack' ), $result ) );
		}

		// Run the standard success checks.
		$success = $this->is_ssh_successful( $result, 'run_upgrade_install_php_83.txt' );
		if ( ! $success ) {
			/* translators: %1$s is replaced with the internal action name; %2$s is replaced with the result of the call, usually an error message. */
			return new \WP_Error( sprintf( __( 'Unable to perform action %1$s for server: %2$s', 'simple-stack' ), $action, $result ) );
		} else {
			// update server field to tag server as being upgraded.
			update_post_meta( $id, 'sstk_server_php83_installed', 1 );

			// Let user know command is complete and force a page rfresh.
			$result = array(
				'msg'     => __( 'PHP 8.3 install has been completed - this page will now refresh', 'simple-stack' ),
				'refresh' => 'yes',
			);

			// Add to update history.
			$this->update_history( $id, $upgrade_history_key_type, $upgrade_description );
		}

		return $result;

	}

	/**
	 * Run upgrade script for WPCLI.
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean success/failure/other
	 */
	public function upgrade_wpcli( $id, $action ) {

		// Upgrade History Type.
		$upgrade_history_key_type = 'wpcli-2.10';
		$upgrade_description      = __( 'Updated WPCLI to v2.10', 'simple-stack' );

		// Get data about the server.
		$instance = $this->get_server_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'simple-stack' ), $action ) );
		}

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'run_upgrade_wpcli.txt',
			array(
				'action'      => $action,
				'interactive' => 'no',
			)
		);

		// log.
		// phpcs:ignore
		do_action( 'sstk_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false ); //PHPcs warning normally issued because of print_r

		// execute.
		$result = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );

		// Make sure we don't have a wp_error object being returned...
		if ( is_wp_error( $result ) ) {
			return new \WP_Error( __( 'There was a problem upgrading WPCLI - please check the server logs for more information.', 'simple-stack' ) );
		}

		// Standard success checks.
		$success = $this->is_ssh_successful( $result, 'run_upgrade_wpcli.txt' );
		if ( ! $success ) {
			/* translators: %1$s is replaced with the internal action name; %2$s is replaced with the result of the call, usually an error message. */
			return new \WP_Error( sprintf( __( 'Unable to perform action %1$s for server: %2$s', 'simple-stack' ), $action, $result ) );
		} else {
			// update server field to tag server as being upgraded.
			update_post_meta( $id, 'sstk_server_wpcli_upgrade', '2.10' );

			// Let user know command is complete and force a page refresh.
			$result = array(
				'msg'     => __( 'The WPCLI upgrade has been completed - this page will now refresh', 'simple-stack' ),
				'refresh' => 'yes',
			);

			// Add to update history.
			$this->update_history( $id, $upgrade_history_key_type, $upgrade_description );
		}

		return $result;

	}

	/**
	 * Add a cron to the server to force all updates to run right away.
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean success/failure/other
	 */
	public function upgrade_linux_all( $id, $action ) {

		// Upgrade History Type.
		$upgrade_history_key_type = 'request-linux-update-' . $action;
		/* Translators: %s is the type of updates requested. It is a code/key, not a word. */
		$upgrade_description = sprintf( __( 'Requested Linux Updates of Type: %s', 'simple-stack' ), $action );

		// Get data about the server.
		$instance = $this->get_server_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the server instance details for action %s', 'simple-stack' ), $action ) );
		}

		if ( ! empty( $_POST['params'] ) ) {
			$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );
		} else {
			$args = array();
		}

		// get the callback url.
		$callback_name                  = 'server_status';  // Note: we are using the same callback name as the regular server_status callback since the script is returning a subset of that data after the updates are run.
		$args['callback_server_status'] = $this->get_command_url( $id, $callback_name, 'completed' );

		// Now lets make sure we escape all the arguments so it's safe for the command line.
		$original_args = $args;
		$args          = array_map(
			function( $item ) {
				return escapeshellarg( $item );
			},
			$args
		);

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command( $instance, 'server_update.txt', array_merge( $args, array( 'action' => $action ) ) );

		// phpcs:ignore
		do_action( 'sstk_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );  //PHPcs warning normally issued because of print_r

		// Execute command and check result.
		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'server_update.txt' );
		if ( ! $success ) {
			switch ( $action ) {
				case 'run_updates_cron':
					do_action( "sstk_server_{$this->get_app_name()}_upgrade_linux_all_action_failed", $id, $action, $success );
					break;
				case 'run_security_updates_cron':
					do_action( "sstk_server_{$this->get_app_name()}_upgrade_linux_security_action_failed", $id, $action, $success );
					break;
			}

			// send default error message.
			/* translators: %1$s is replaced with the internal action name; %2$s is replaced with the result of the call, usually an error message. */
			return new \WP_Error( sprintf( __( 'Unable to %1$s : %2$s', 'simple-stack' ), $action, $result ) );
		} else {
			/* Fire action hook to let other things know this action succeeded - usually used by things triggered from PENDING TASKS */
			switch ( $action ) {
				case 'run_updates_cron':
					do_action( "sstk_server_{$this->get_app_name()}_upgrade_linux_all_action_successful", $id, $action, $success );
					break;
				case 'run_security_updates_cron':
					do_action( "sstk_server_{$this->get_app_name()}_upgrade_linux_security_action_successful", $id, $action, $success );
					break;
			}

			$success = array(
				'msg'     => __( 'We have scheduled your LINUX server updates to run via CRON which should begin shortly.', 'simple-stack' ),
				'refresh' => 'yes',
			);

			// Add to update history.
			$this->update_history( $id, $upgrade_history_key_type, $upgrade_description );
		}

		return $success;

	}

	/**
	 * Add new bulk options in server list screen.
	 *
	 * @param array $bulk_array bulk array.
	 */
	public function sstk_add_new_bulk_actions_server( $bulk_array ) {

		if ( sstk_is_admin() ) {
			$bulk_array['sstk_apply_all_linux_updates']      = __( 'Apply All Linux Updates', 'simple-stack' );
			$bulk_array['sstk_apply_security_linux_updates'] = __( 'Apply Only Linux Security Updates', 'simple-stack' );
			return $bulk_array;
		}

	}

	/**
	 * Handle bulk actions for server.
	 *
	 * @param string $redirect_url  redirect url.
	 * @param string $action        action.
	 * @param array  $post_ids      all post ids.
	 */
	public function sstk_bulk_action_handler_server_app( $redirect_url, $action, $post_ids ) {
		// Let's remove query args first for redirect url.
		$redirect_url = remove_query_arg( array( 'sstk_apply_all_linux_updates' ), $redirect_url );

		// Lets make sure we're an admin otherwise return an error.
		if ( ! sstk_is_admin() ) {
			do_action( 'sstk_log_error', 'Someone attempted to run a function that required admin privileges.', 'security', __FILE__, __LINE__ );

			// Show error message to user at the top of the admin list as a dismissible notice.
			sstk_global_add_admin_notice( __( 'You attempted to run a function that requires admin privileges.', 'simple-stack' ), 'error' );

			return $redirect_url;
		}

		// Apply all Linux updates.
		if ( 'sstk_apply_all_linux_updates' === $action ) {

			if ( ! empty( $post_ids ) ) {
				foreach ( $post_ids as $server_id ) {
					$args['action_hook'] = 'pending_log_apply_all_linux_updates';
					SSTK_POSTS_PENDING_TASKS_LOG()->add_pending_task_log_entry( $server_id, 'apply-all-linux-updates', $server_id, $args, 'ready', $server_id, __( 'Schedule Running All Linux Updates From Bulk Operation', 'simple-stack' ) );
				}

				// Add message to be displayed in admin header.
				sstk_global_add_admin_notice( __( 'Updates have been scheduled for the selected servers. You can view the progress in the PENDING TASKS screen.', 'simple-stack' ), 'success' );

			}
		}

		// Apply only Linux security updates.
		if ( 'sstk_apply_security_linux_updates' === $action ) {

			// @todo: need to ask for confirmation here first (or maybe before we get here).

			if ( ! empty( $post_ids ) ) {
				foreach ( $post_ids as $server_id ) {
					$args['action_hook'] = 'pending_log_apply_security_linux_updates';
					SSTK_POSTS_PENDING_TASKS_LOG()->add_pending_task_log_entry( $server_id, 'apply-linux-security-updates', $server_id, $args, 'ready', $server_id, __( 'Schedule Running Linux SECURITY Updates only From Bulk Operation', 'simple-stack' ) );
				}

				// Add message to be displayed in admin header.
				sstk_global_add_admin_notice( __( 'Updates have been scheduled for the selected servers. You can view the progress in the PENDING TASKS screen.', 'simple-stack' ), 'success' );

			}

			// @todo: show confirmation message in a dialog box or at the top of the admin screen as a dismissible notice.
		}

		return $redirect_url;
	}

	/**
	 * Apply all linux updates - triggered via pending logs background process.
	 *
	 * Called from an action hook from the pending logs background process - SSTK_POSTS_PENDING_TASKS_LOG()->do_tasks()
	 *
	 * Action Hook: pending_log_apply_all_linux_updates
	 *
	 * @param int   $task_id    Id of pending task that is firing this thing...
	 * @param int   $server_id  Id of server on which to install the new website.
	 * @param array $args       All the data needed to install the WP site on the server.
	 */
	public function pending_log_apply_all_linux_updates( $task_id, $server_id, $args ) {

		// Grab our data array from pending tasks record...
		$data = SSTK_POSTS_PENDING_TASKS_LOG()->get_data_by_id( $task_id );

		/* Apply all linux updates */
		do_action( 'sstk_wordpress-upgrade_linux_all', $server_id, 'run_updates_cron' );

	}

	/**
	 * Apply linux security updates - triggered via pending logs background process.
	 *
	 * Called from an action hook from the pending logs background process - SSTK_POSTS_PENDING_TASKS_LOG()->do_tasks()
	 *
	 * Action Hook: pending_log_apply_linux_security_updates
	 *
	 * @param int   $task_id    Id of pending task that is firing this thing...
	 * @param int   $server_id  Id of server on which to install the new website.
	 * @param array $args       All the data needed to install the WP site on the server.
	 */
	public function pending_log_apply_linux_security_updates( $task_id, $server_id, $args ) {

		// Grab our data array from pending tasks record...
		$data = SSTK_POSTS_PENDING_TASKS_LOG()->get_data_by_id( $task_id );

		/* Apply just linux security updates - the  sstk_wordpress-upgrade_linux_all action hook can take either a 'run_updates_cron' or 'run_security_updates_cron' parameter.  */
		do_action( 'sstk_wordpress-upgrade_linux_all', $server_id, 'run_security_updates_cron' );

	}

	/**
	 * Handle scheduling of all linux updates successful
	 *
	 * Action Hook: sstk_server_{$this->get_app_name()}_upgrade_linux_all_action_successful || sstk_server_wordpress-app_upgrade_linux_all_action_successful
	 * Action Hook: sstk_server_{$this->get_app_name()}_upgrade_linux_security_action_successful || sstk_server_wordpress-app_upgrade_linux_security_action_successful
	 *
	 * @param int     $server_id            Id of server.
	 * @param string  $action               What action were we running.
	 * @param mixed[] $success_msg_array    An array that was passed through from the installation function (manage_server_status_callback above).
	 */
	public function handle_upgrade_linux_all_success( $server_id, $action, $success_msg_array ) {
		$this->handle_upgrade_linux_all_success_or_failure( $server_id, $action, $success_msg_array, 'success' );
	}

	/**
	 * Handle scheduling of all linux updates failed
	 *
	 * Action Hook: sstk_server_{$this->get_app_name()_upgrade_linux_all_action_failed || sstk_server_wordpress-app_upgrade_linux_all_action_failed
	 * Action Hook: sstk_server_{$this->get_app_name()_upgrade_linux_security_action_failed || sstk_server_wordpress-app_upgrade_linux_security_action_failed
	 *
	 * @param int     $server_id            Id of server.
	 * @param string  $action               What action were we running.
	 * @param mixed[] $success_msg_array    An array that was passed through from the installation function (manage_server_status_callback above).
	 */
	public function handle_upgrade_linux_all_failed( $server_id, $action, $success_msg_array ) {
		$this->handle_upgrade_linux_all_success_or_failure( $server_id, $action, $success_msg_array, 'failed' );
	}

	/**
	 * Handle scheduling of all linux updates successful or failed when being processed from pending logs / via action hooks.
	 *
	 * @param int     $server_id            Id of server.
	 * @param string  $action               What action were we running.
	 * @param mixed[] $success_msg_array    An array that was passed through from the installation function (manage_server_status_callback above).
	 * @param boolean $success              Was the scheduling of the linux updates a sucesss or failure.
	 */
	public function handle_upgrade_linux_all_success_or_failure( $server_id, $action, $success_msg_array, $success ) {

		$server_post = get_post( $server_id );

		// Bail if not a post object.
		if ( ! $server_post || is_wp_error( $server_post ) ) {
			return;
		}

		// Bail if not a WordPress app...
		if ( 'wordpress-app' !== SSTK_WORDPRESS_APP()->get_server_type( $server_id ) ) {
			return;
		}

		// This only matters if we were running linux updates.  If not, then bail.
		if ( 'run_updates_cron' !== $action && 'run_security_updates_cron' !== $action ) {
			return;
		}

		// Get server instance array.
		$instance = SSTK_WORDPRESS_APP()->get_instance_details( $server_id );

		if ( 'sstk_app_server' === get_post_type( $server_id ) ) {

				// Now check the pending tasks table for a record where the key=$server_id and type='apply-all-linux-updates' and state='in-process'
				// We are depending on the fact that there should only be one process running on a server a time and in this case it should be in-process.
			switch ( $action ) {
				case 'run_updates_cron':
					$posts = SSTK_POSTS_PENDING_TASKS_LOG()->get_tasks_by_key_state_type( $server_id, 'in-process', 'apply-all-linux-updates' );
					break;
				case 'run_security_updates_cron':
					$posts = SSTK_POSTS_PENDING_TASKS_LOG()->get_tasks_by_key_state_type( $server_id, 'in-process', 'apply-linux-security-updates' );
					break;
			}

			if ( $posts ) {

				// Grab our data array from pending tasks record...
				$task_id = $posts[0]->ID;
				$data    = SSTK_POSTS_PENDING_TASKS_LOG()->get_data_by_id( $task_id );

				// And mark it as successful or failed.
				if ( 'failed' === $success ) {
					SSTK_POSTS_PENDING_TASKS_LOG()->update_task_by_id( $task_id, $data, 'failed' );
				} else {
					SSTK_POSTS_PENDING_TASKS_LOG()->update_task_by_id( $task_id, $data, 'complete' );
				}
			}
		}

	}

}

new SSTK_WORDPRESS_TABS_SERVER_UPGRADE();
