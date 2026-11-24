<?php
/** Modified for Simple Stack by One Dog Solutions on 2026-11-23. */
/**
 * Tools tab.
 *
 * @package simple-stack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SSTK_WORDPRESS_TABS_TOOLS
 */
class SSTK_WORDPRESS_TABS_TOOLS extends SSTK_WORDPRESS_TABS {

	/**
	 * SSTK_WORDPRESS_TABS_TOOLS constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_filter( "sstk_app_{$this->get_app_name()}_get_tabnames", array( $this, 'get_tab' ), 10, 2 );
		add_filter( "sstk_app_{$this->get_app_name()}_get_tabs", array( $this, 'get_tab_fields' ), 10, 2 );
		add_filter( "sstk_app_{$this->get_app_name()}_tab_action", array( $this, 'tab_action' ), 10, 3 );

		// Allow the clear_background_processes action to be triggered via an action hook.
		add_action( "sstk_{$this->get_app_name()}_clear_background_processes", array( $this, 'clear_background_processes' ), 10, 2 ); // Hook:sstk_wordpress-app_clear_background_processes.

		// Allow the reset_permissions action to be triggered via an action hook.
		add_action( "sstk_{$this->get_app_name()}_reset_file_permissions", array( $this, 'do_reset_file_permissions_action' ), 10, 2 ); // Hook:sstk_wordpress-app_reset_file_permissions.

		// Allow update wp site option action to be triggered via an action hook.
		add_action( "sstk_{$this->get_app_name()}_update_wp_site_option", array( $this, 'do_update_wp_site_option_action' ), 10, 2 ); // Hook:sstk_wordpress-app_update_wp_site_option.

	}

	/**
	 * Returns a string that can be used as the unique name for this tab.
	 */
	public function get_tab_slug() {
		return 'tools';
	}

	/**
	 * Returns a string that is the name of a view TEAM permission required to view this tab.
	 */
	public function get_view_tab_team_permission_slug() {
		return 'view_wpapp_site_tools_tab';
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
				'label' => __( 'Tools', 'simple-stack' ),
				'icon'  => 'fad fa-toolbox',
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
	 * Gets the fields to be shown in the TOOLs tab.
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
		$valid_actions = array( 'tools-clear-background-processes', 'tools-wpdebug-status', 'tools-update-restricted-php-functions', 'tools-reset-site-file-permissions', 'tools-wp-site-option' );
		if ( in_array( $action, $valid_actions, true ) ) {
			if ( ! $this->get_tab_security( $id ) ) {
				return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'simple-stack' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
			}
		}

		if ( $this->get_tab_security( $id ) ) {
			switch ( $action ) {
				case 'tools-clear-background-processes':
					// Remove all metas related to background processes that might be "hung" or in-processs.
					$result = $this->clear_background_processes( $id, 'clear_background_processes' );
					if ( ! is_wp_error( $result ) ) {
						$result = array( 'refresh' => 'yes' );
					}
					break;
				case 'tools-wpdebug-status':
					$result = $this->toggle_wpdebug_status( $id, $action );
					break;
				case 'tools-enable-debug-log':
					$action = 'enable_debug';
					$result = $this->enable_disable_wp_debug( $id, $action );
					break;
				case 'tools-disable-debug-log':
					$action = 'disable_debug';
					$result = $this->enable_disable_wp_debug( $id, $action );
					break;
				case 'tools-update-restricted-php-functions':
					// Verify that the user is allowed to do this - only admins can...
					if ( ! sstk_is_admin() ) {
						$msg    = __( 'You don\'t have permission to use this function.', 'simple-stack' );
						$result = array(
							'refresh' => 'yes',
							'msg'     => $msg,
						);
						break;
					}
					$action = 'enable_php_function';
					$result = $this->update_restricted_php_functions( $id, $action );
					break;
				case 'tools-reset-site-file-permissions':
					$action = 'reset_permissions';
					$result = $this->reset_file_permissions( $id, $action );
					break;
				case 'tools-reset-site-file-permissions-alt':
					$action = 'reset_permissions_alt';
					$result = $this->reset_file_permissions( $id, $action );
					break;
				case 'tools-wp-site-option':
					$action = 'wp_site_update_option';
					$result = $this->update_wp_site_option( $id, $action );
					break;

			}
		}
		return $result;
	}

	/**
	 * Gets the actions to be shown in the TOOLS tab.
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	public function get_actions( $id ) {

		return $this->get_tools_fields( $id );

	}

	/**
	 * Gets the fields to be shown in the TOOLS tab.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_tools_fields( $id ) {

		// Bail if site is not enabled.
		if ( ! $this->is_site_enabled( $id ) ) {
			return $this->get_disabled_header_field();
		}

		// Basic checks passed, ok to proceed.
		$actions = array();

		// Start new card.
		$actions[] = sstk_start_half_card( $this->get_tab_slug() );

		// Set header description.
		$header_debug_desc  = __( 'Enable or disable the WordPress Debug log file.  This file is stored in the wp-content folder. Once enabled you can retrieve it with your sFTP client.', 'simple-stack' );
		$header_debug_desc .= '<br /><br />' . __( 'Note that if you toggle the WP_DEBUG flag directly in wp-config.php, the status shown here might not be accurate.', 'simple-stack' );
		$header_debug_desc .= '<br /><br />' . __( 'Messages will go directly to the debug.log file located in the wp-content folder.  Nothing will be shown on the screen.', 'simple-stack' );
		$header_debug_desc  = sprintf( '<details>%s %s</details>', sstk_get_html5_detail_element_summary_text(), $header_debug_desc );

		/* DEBUG LOG */
		$actions['tools-debug-log-header'] = array(
			'label'          => __( 'Manage Debug Logs', 'simple-stack' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $header_debug_desc,
			),
		);

		// Get the current debug log flag value.
		if ( true === $this->get_site_local_wpdebug_flag( $id ) ) {
			$debug_status = 'on';
		} else {
			$debug_status = 'off';
		}

		/* Set the confirmation prompt based on the the current status of this flag */
		$confirmation_prompt = '';
		if ( 'on' === $debug_status ) {
			$confirmation_prompt = __( 'Are you sure you would like to disable the WordPress debug log?', 'simple-stack' );
		} else {
			$confirmation_prompt = __( 'Are you sure you would like to enable the WordPress debug log?', 'simple-stack' );
		}

		if ( 'on' !== $debug_status ) {
			$debug_desc = __( 'Click to enable the WordPress debug log.', 'simple-stack' );
		} else {
			$debug_desc = __( 'Click to disable the WordPress debug log', 'simple-stack' );
		}

		$actions['tools-wpdebug-status'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'on_label'            => __( 'Enabled', 'simple-stack' ),
				'off_label'           => __( 'Disabled', 'simple-stack' ),
				'std'                 => 'on' === $debug_status,
				'desc'                => $debug_desc,
				'confirmation_prompt' => $confirmation_prompt,
			),
			'type'           => 'switch',
		);

		// Close up prior card.
		$actions[] = sstk_end_card( $this->get_tab_slug() );

		/* RESET SITE PERMISSIONS */

		// Start new card.
		$actions[] = sstk_start_half_card( $this->get_tab_slug() );

		$actions['tools-reset-site-permissions-header'] = array(
			'label'          => __( 'Reset file permissions', 'simple-stack' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'Sometimes file permissions are inadvertently changed.  Use this to reset them to their defaults: 664 for files and 2775 for folders.', 'simple-stack' ),
			),
		);

		$actions['tools-reset-site-file-permissions'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Reset', 'simple-stack' ),
				'confirmation_prompt' => __( 'Are you sure you would like to reset the file permissions for this website?', 'simple-stack' ),
			),
			'type'           => 'button',
		);

		// Close up prior card.
		$actions[] = sstk_end_card( $this->get_tab_slug() );

		/* RESTRICTED PHP FUNCTIONS */
		if ( sstk_is_admin() ) {

			// Start new card.
			$actions[] = sstk_start_half_card( $this->get_tab_slug() );

			$desc = __( 'For security purposes certain PHP functions should be restricted. You can use this option to make sure you have the most recent restrictions for your site. <br /> Note that under normal circumstances you should not need to use this option.', 'simple-stack' );
			$desc = sprintf( '<details>%s %s</details>', sstk_get_html5_detail_element_summary_text(), $desc );

			$actions['tools-update-restricted-php-functions-header'] = array(
				'label'          => __( 'Restricted PHP Functions', 'simple-stack' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => $desc,
				),
			);

			/* Set the text of the confirmation prompt */
			$confirmation_prompt = __( 'Are you sure you would like to reset the list of restricted PHP functions for this site?', 'simple-stack' );

			$actions['tools-update-restricted-php-functions'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'                 => __( 'Reset', 'simple-stack' ),
					'confirmation_prompt' => $confirmation_prompt,
				),
				'type'           => 'button',
			);

			// Close up prior card.
			$actions[] = sstk_end_card( $this->get_tab_slug() );

		}

		/* BACKGROUND PROCESSES */

		// Start new card.
		$actions[] = sstk_start_half_card( $this->get_tab_slug() );

		$desc = __( 'This plugin uses a lot of CRON background processes.  Sometimes they can get into a state where they never end and start to fill up the database with irrelevant messages. Use this to reset these processes.', 'simple-stack' );
		$desc = sprintf( '<details>%s %s</details>', sstk_get_html5_detail_element_summary_text(), $desc );

		$actions['tools-clear-background-processes-header'] = array(
			'label'          => __( 'Clear Background Processes', 'simple-stack' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $desc,
			),
		);

		/* Set the text of the confirmation prompt */
		$confirmation_prompt = __( 'Are you sure you would like to clear and reset all background processes for this site?', 'simple-stack' );

		$actions['tools-clear-background-processes'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Clear', 'simple-stack' ),
				'confirmation_prompt' => $confirmation_prompt,
			),
			'type'           => 'button',
		);

		// Close up prior card.
		$actions[] = sstk_end_card( $this->get_tab_slug() );

		/* WP SITE OPTIONS */

		// Start new card.
		$actions[] = sstk_start_half_card( $this->get_tab_slug() );

		$desc = __( 'Update certain site options. Note that you should be able to change all these options from directly inside your site so there is rarely a need to use this function..', 'simple-stack' );
		$desc = sprintf( '<details>%s %s</details>', sstk_get_html5_detail_element_summary_text(), $desc );

		$actions['tools-wp-site-option-header'] = array(
			'label'          => __( 'Update WP Site Options', 'simple-stack' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $desc,
			),
		);

		$actions['tools-wp-site-option-name'] = array(
			'label'          => __( 'Option Name', 'simple-stack' ),
			'raw_attributes' => array(
				'desc'           => __( 'Select the option', 'simple-stack' ),
				'options'        => $this->get_site_options_list(),
				'data-sstk-name' => 'wps_option',
			),
			'type'           => 'select',
		);

		$actions['tools-wp-site-option-value'] = array(
			'label'          => __( 'Option Value', 'simple-stack' ),
			'raw_attributes' => array(
				'desc'           => __( 'Enter the value for the option', 'simple-stack' ),
				'data-sstk-name' => 'wps_new_option_value',
			),
			'type'           => 'text',
		);

		$actions['tools-wp-site-option'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Update Site Option', 'simple-stack' ),
				'confirmation_prompt' => __( 'Are you sure you would like to update this site option?', 'simple-stack' ),
				// fields that contribute data for this action.
				'data-sstk-fields'    => json_encode( array( '#sstk_app_action_tools-wp-site-option-name', '#sstk_app_action_tools-wp-site-option-value' ) ),
			),
			'type'           => 'button',
		);

		// Close up prior card.
		$actions[] = sstk_end_card( $this->get_tab_slug() );

		/* RESET SITE PERMISSIONS - ALT */

		// Start new card.
		$actions[] = sstk_start_half_card( $this->get_tab_slug() );

		$desc = __( 'Sometimes you might need a more restrictive set of file permissions compared to the Simple Stack defaults.  Use this to reset permissions to: 644 for files and 775 for folders. Warning: some non-critical operations might fail when these are enabled.', 'simple-stack' );
		$desc = sprintf( '<details>%s %s</details>', sstk_get_html5_detail_element_summary_text(), $desc );

		$actions['tools-reset-site-permissions-header-alt'] = array(
			'label'          => __( 'Reset file permissions (Alternative)', 'simple-stack' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $desc,
			),
		);

		$actions['tools-reset-site-file-permissions-alt'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Reset', 'simple-stack' ),
				'confirmation_prompt' => __( 'Are you sure you would like to reset the file permissions for this website?', 'simple-stack' ),
			),
			'type'           => 'button',
		);

		// Close up prior card.
		$actions[] = sstk_end_card( $this->get_tab_slug() );

		return $actions;

	}

	/**
	 * Get the list of site options that we're going to allow users to update.
	 */
	public function get_site_options_list() {
		$options_list = array(
			'admin_email'      => __( 'Site Admin Email Address', 'simple-stack' ),
			'default_role'     => __( 'Default Role', 'simple-stack' ),
			'timezone_string ' => __( 'Timezone String', 'simple-stack' ),
		);
		return $options_list;
	}


	/**
	 * Clear background processes.
	 *
	 * Action Hook: sstk_wordpress-app_clear_background_processes (optional - most times called directly and not via an action hook.)
	 *
	 * @param int    $id     The postID of the app cpt.
	 * @param string $action The action to be performed. Not used in this script.
	 *
	 * @return boolean|WP_Error    success/failure
	 */
	public function clear_background_processes( $id, $action ) {

		delete_post_meta( $id, "sstk_app_{$this->get_app_name()}_action_status" );
		delete_post_meta( $id, "sstk_app_{$this->get_app_name()}_action" );
		delete_post_meta( $id, "sstk_app_{$this->get_app_name()}_action_args" );
		delete_post_meta( $id, 'sstk_command_mutex' );
		delete_post_meta( $id, 'sstk_temp_log_id' );

		// Return an error so that it can be displayed in a dialog box...
		return new \WP_Error( __( 'Background processes have been reset.', 'simple-stack' ) );
	}

	/**
	 * Enable/disable debug log.
	 *
	 * @param int    $id     The postID of the app cpt.
	 * @param string $action The action to be performed (this matches the string required in the bash scripts).
	 *
	 * @return boolean|WP_Error    success/failure
	 */
	private function toggle_wpdebug_status( $id, $action ) {

		$instance = $this->get_app_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'simple-stack' ), $action ) );
		}

		// Figure out if we're disabling or enabling the flag.
		if ( true === $this->get_site_local_wpdebug_flag( $id ) ) {
			$action = 'disable_debug';
		} else {
			$action = 'enable_debug';
		}

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'toggle_wp_debug.txt',
			array(
				'command' => "{$action}_site",
				'action'  => $action,
				'domain'  => get_post_meta(
					$id,
					'wpapp_domain',
					true
				),
			)
		);

		do_action( 'sstk_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'toggle_wp_debug.txt' );

		if ( ! $success ) {
			return new \WP_Error( sprintf( __( 'Unable to %1$s site: %2$s', 'simple-stack' ), $action, $result ) );
		} else {

			// Update metas.
			if ( true === $this->get_site_local_wpdebug_flag( $id ) ) {
				$this->set_site_local_wpdebug_flag( $id, false );
			} else {
				$this->set_site_local_wpdebug_flag( $id, true );
			}

			$success = array(
				'msg'     => __( 'The WordPress debug flags have been toggled.', 'simple-stack' ),
				'refresh' => 'yes',
			);
		}

		return $success;

	}


	/**
	 * Update the list of restricted PHP functions for a site
	 *
	 * @param int    $id     The postID of the app cpt.
	 * @param string $action The action to be performed (this matches the string required in the bash scripts).
	 *
	 * @return boolean|WP_Error    success/failure
	 */
	private function update_restricted_php_functions( $id, $action ) {

		$instance = $this->get_app_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'simple-stack' ), $action ) );
		}

		// List of functions to remove - ***should be NO spaces between the commas!***.
		/* $remove_functions = "'dl, exec, fpassthru, getmypid, getmyuid, highlight_file, ignore_user_abort, link, opcache_get_configuration, passthru, pcntl_exec, pcntl_get_last_error, pcntl_setpriority, pcntl_strerror, pcntl_wifcontinued, php_uname, phpinfo, popen, posix_ctermid, posix_getcwd, posix_getegid, posix_geteuid, posix_getgid, posix_getgrgid, posix_getgrnam, posix_getgroups, posix_getlogin, posix_getpgid, posix_getpgrp, posix_getpid, posix_getppid, posix_getpwnam, posix_getpwuid, posix_getrlimit, posix_getsid, posix_getuid, posix_isatty, posix_kill, posix_mkfifo, posix_setegid, posix_seteuid, posix_setgid, posix_setpgid, posix_setsid, posix_setuid, posix_times, posix_ttyname, posix_uname, proc_close, proc_get_status, proc_nice, proc_open, proc_terminate, shell_exec, show_source, source, system, virtual, set_time_limit, tmpfile, posix, listen, set_time_limit, php_uname, disk_free_space, diskfreespace, opcache_compile_file, opcache_invalidate, opcache_is_script_cached, pcntl_alarm, pcntl_fork, socket_listen, pcntl_getpriority, pcntl_signal, pcntl_signal_dispatch, pcntl_sigprocmask, pcntl_sigtimedwait, pcntl_sigwaitinfo, pcntl_waitpidpcntl_wait, pcntl_wexitstatus, pcntl_wifexited, pcntl_wifsignaled, pcntl_wifstopped, pcntl_wstopsig, pcntl_wtermsig'"; */
		$remove_functions = "'dl,exec,fpassthru,getmypid,getmyuid,highlight_file,ignore_user_abort,link,opcache_get_configuration,passthru,pcntl_exec,pcntl_get_last_error,pcntl_setpriority,pcntl_strerror,pcntl_wifcontinued,php_uname,phpinfo,popen,posix_ctermid,posix_getcwd,posix_getegid,posix_geteuid,posix_getgid,posix_getgrgid,posix_getgrnam,posix_getgroups,posix_getlogin,posix_getpgid,posix_getpgrp,posix_getpid,posix_getppid,posix_getpwnam,posix_getpwuid,posix_getrlimit,posix_getsid,posix_getuid,posix_isatty,posix_kill,posix_mkfifo,posix_setegid,posix_seteuid,posix_setgid,posix_setpgid,posix_setsid,posix_setuid,posix_times,posix_ttyname,posix_uname,proc_close,proc_get_status,proc_nice,proc_open,proc_terminate,shell_exec,show_source,source,system,virtual,set_time_limit,tmpfile,posix,listen,set_time_limit,php_uname,disk_free_space,diskfreespace,opcache_compile_file,opcache_invalidate,opcache_is_script_cached,pcntl_alarm,pcntl_fork,socket_listen,pcntl_getpriority,pcntl_signal,pcntl_signal_dispatch,pcntl_sigprocmask,pcntl_sigtimedwait,pcntl_sigwaitinfo,pcntl_waitpidpcntl_wait,pcntl_wexitstatus,pcntl_wifexited,pcntl_wifsignaled,pcntl_wifstopped,pcntl_wstopsig,pcntl_wtermsig,leak'";

		// Get the full command to be executed by ssh.
		$action = 'enable_php_function';

		$run_cmd = $this->turn_script_into_command(
			$instance,
			'enable_disable_php_functions.txt',
			array(
				'action'         => $action,
				'domain'         => get_post_meta( $id, 'wpapp_domain', true ),
				'functions_list' => $remove_functions,
			)
		);

		do_action( 'sstk_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		$result = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );

		$success = $this->is_ssh_successful( $result, 'enable_disable_php_functions.txt' );

		if ( $success ) {
			// now we have to add the new list - ***should be NO spaces between the commas!***.
			$add_functions = "'dl,exec,fpassthru,getmypid,getmyuid,highlight_file,ignore_user_abort,link,opcache_get_configuration,passthru,pcntl_exec,pcntl_get_last_error,pcntl_setpriority,pcntl_strerror,pcntl_wifcontinued,phpinfo,popen,posix_ctermid,posix_getcwd,posix_getegid,posix_geteuid,posix_getgid,posix_getgrgid,posix_getgrnam,posix_getgroups,posix_getlogin,posix_getpgid,posix_getpgrp,posix_getpid,posix_getppid,posix_getpwnam,posix_getpwuid,posix_getrlimit,posix_getsid,posix_getuid,posix_isatty,posix_kill,posix_mkfifo,posix_setegid,posix_seteuid,posix_setgid,posix_setpgid,posix_setsid,posix_setuid,posix_times,posix_ttyname,posix_uname,proc_close,proc_get_status,proc_nice,proc_open,proc_terminate,shell_exec,show_source,source,system,virtual'";

			// Get the full command to be executed by ssh.
			$action  = 'disable_php_function';
			$run_cmd = $this->turn_script_into_command(
				$instance,
				'enable_disable_php_functions.txt',
				array(
					'action'         => $action,
					'domain'         => get_post_meta( $id, 'wpapp_domain', true ),
					'functions_list' => $add_functions,
				)
			);

			do_action( 'sstk_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

			$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
			$success = $this->is_ssh_successful( $result, 'enable_disable_php_functions.txt' );

		} else {

			return new \WP_Error( sprintf( __( 'Unable to %1$s site: %2$s', 'simple-stack' ), $action, $result ) );

		}

		// If we got here it means that we've run both parts and we need to see if the second part succeeded.
		if ( ! $success ) {
			return new \WP_Error( sprintf( __( 'Unable to %1$s site: %2$s', 'simple-stack' ), $action, $result ) );
		} else {
			$success = array(
				'msg'     => __( 'The PHP restricted functions list for this site has been updated!', 'simple-stack' ),
				'refresh' => 'yes',
			);
		}

		return $success;

	}

	/**
	 * Reset file permissions for this site
	 *
	 * @param int    $id     The postID of the app cpt.
	 * @param string $action The action to be performed (this matches the string required in the bash scripts).
	 * @param array  $in_args Alternative source of arguments passed via action hook or direct function call instead of pulling from $_POST.
	 *
	 * @return boolean|WP_Error    success/failure
	 */
	private function reset_file_permissions( $id, $action, $in_args = array() ) {

		$instance = $this->get_app_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'simple-stack' ), $action ) );
		}

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'reset_site_permissions.txt',
			array(
				'action' => $action,
				'domain' => get_post_meta(
					$id,
					'wpapp_domain',
					true
				),
			)
		);

		do_action( 'sstk_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'reset_site_permissions.txt' );

		if ( ! $success ) {
			return new \WP_Error( sprintf( __( 'Unable to %1$s site: %2$s', 'simple-stack' ), $action, $result ) );
		} else {
			$success = array(
				'msg'     => __( 'The file permissions for this site has been reset.', 'simple-stack' ),
				'refresh' => 'yes',
			);
		}

		return $success;

	}

	/**
	 * Update a site option.
	 *
	 * @param int    $id     The postID of the app cpt.
	 * @param string $action The action to be performed (this matches the string required in the bash scripts).
	 * @param array  $in_args Alternative source of arguments passed via action hook or direct function call instead of pulling from $_POST.
	 *
	 * @return boolean|WP_Error    success/failure
	 */
	private function update_wp_site_option( $id, $action, $in_args = array() ) {

		if ( empty( $in_args ) ) {
			// Get data from the POST request.
			$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );
		} else {
			$args = $in_args;
		}

		// Get app/server details.
		$instance = $this->get_app_instance_details( $id );

		// Bail if no app/server details.
		if ( is_wp_error( $instance ) ) {
			/* Translators: %s is the action name. */
			$message = sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'simple-stack' ), $action );
			do_action( "sstk_{$this->get_app_name()}_update_wp_site_option_failed", $id, $action, $message, $args );
			return new \WP_Error( $message );
		}

		// Make sure the option is in the approved list.
		if ( ! in_array( $args['wps_option'], array_keys( $this->get_site_options_list() ), true ) ) {
			$message = __( 'The option name is invalid - possible security issue.', 'simple-stack' );
			do_action( "sstk_{$this->get_app_name()}_update_wp_site_option_failed", $id, $action, $message, $args );
			return new \WP_Error( $message );
		}

		// Check to make sure that all required fields have values.
		if ( ! $args['wps_option'] ) {
			$message = __( 'The option name cannot be blank.', 'simple-stack' );
			do_action( "sstk_{$this->get_app_name()}_update_wp_site_option_failed", $id, $action, $message, $args );
			return new \WP_Error( $message );
		} else {
			// Sanitize the option name for use on the linux command line.
			$args['wps_option'] = escapeshellarg( $args['wps_option'] );
		}
		if ( ! $args['wps_new_option_value'] ) {
			$message = __( 'The new option value cannot be blank.', 'simple-stack' );
			do_action( "sstk_{$this->get_app_name()}_update_wp_site_option_failed", $id, $action, $message, $args );
			return new \WP_Error( $message );
		} else {
			// Sanitize the option name for use on the linux command line.
			$args['wps_new_option_value'] = escapeshellarg( $args['wps_new_option_value'] );
		}

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'update_wp_site_option.txt',
			array_merge(
				$args,
				array(
					'action' => $action,
					'domain' => get_post_meta(
						$id,
						'wpapp_domain',
						true
					),
				)
			)
		);

		do_action( 'sstk_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'update_wp_site_option.txt' );

		if ( ! $success ) {
			/* Translators: %1$s is the action; %2$s is the result of the ssh call. */
			$message = sprintf( __( 'Unable to %1$s site: %2$s', 'simple-stack' ), $action, $result );
			do_action( "sstk_{$this->get_app_name()}_update_wp_site_option_failed", $id, $action, $message, $args );
			return new \WP_Error( $message );
		} else {
			$success = array(
				'msg'     => __( 'The site option was updated.', 'simple-stack' ),
				'refresh' => 'yes',
			);

			// Let others know we've been successful.
			do_action( "sstk_{$this->get_app_name()}_update_wp_site_option_successful", $id, $action, $args );
		}

		return $success;

	}

	/**
	 * Trigger the update wp site option action from an action hook.
	 *
	 * Action Hook: sstk_{$this->get_app_name()}_update_wp_site_option | sstk_wordpress-app_update_wp_site_option
	 *
	 * @param string $id ID of app where domain change has to take place.
	 * @param array  $args array arguments that the add admin function needs.
	 */
	public function do_update_wp_site_option_action( $id, $args ) {
		$this->update_wp_site_option( $id, 'wp_site_update_option', $args );
	}

	/**
	 * Trigger the reset file permissions action from an action hook.
	 *
	 * Action Hook: sstk_{$this->get_app_name()}_reset_file_permissions | sstk_wordpress-app_reset_file_permissions
	 *
	 * @param string $id ID of app where domain change has to take place.
	 * @param array  $args array arguments that the add admin function needs.
	 */
	public function do_reset_file_permissions_action( $id, $args ) {
		$this->reset_file_permissions( $id, 'reset_permissions', $args );
	}

}

new SSTK_WORDPRESS_TABS_TOOLS();
