<?php
/** Modified for Simple Stack by One Dog Solutions on 2026-11-23. */
/**
 * OLS Console
 *
 * @package simple-stack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SSTK_WORDPRESS_TABS_SERVER_OLS_CONSOLE
 */
class SSTK_WORDPRESS_TABS_SERVER_OLS_CONSOLE extends SSTK_WORDPRESS_TABS {

	/**
	 * SSTK_WORDPRESS_TABS_PHP constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_filter( "sstk_server_{$this->get_app_name()}_get_tabnames", array( $this, 'get_tab' ), 10, 2 );
		add_filter( "sstk_server_{$this->get_app_name()}_get_tabs", array( $this, 'get_tab_fields' ), 10, 2 );
		add_filter( "sstk_app_{$this->get_app_name()}_tab_action", array( $this, 'tab_action' ), 10, 3 );  // This filter says 'sstk_app' because we're using the same functions for server details ajax tabs and app details ajax tabs.
	}

	/**
	 * Returns a string that can be used as the unique name for this tab.
	 */
	public function get_tab_slug() {
		return 'ols_console';
	}

	/**
	 * Returns a string that is the name of a view TEAM permission required to view this tab.
	 */
	public function get_view_tab_team_permission_slug() {
		return 'view_wpapp_server_ols_console_tab';
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
		if ( 'ols' === $this->get_web_server_type( $id ) ) {
			if ( $this->get_tab_security( $id ) ) {
				$tabs[ $this->get_tab_slug() ] = array(
					'label' => __( 'OLS Console', 'simple-stack' ),
					'icon'  => 'far fa-phone-office',
				);
			}
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
	 * Gets the fields to be shown in the OLS Console tab.
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

		// Ols Console is only valid for OLS servers.
		if ( ! ( 'ols' === $this->get_web_server_type( $id ) ) ) {
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
			return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'simple-stack' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
		}

		/* Now verify that the user can perform actions on this screen, assuming that they can view the server */
		$valid_actions = array( 'enable-ols-console', 'disable-ols-console' );
		if ( in_array( $action, $valid_actions, true ) ) {
			if ( ! $this->get_tab_security( $id ) ) {
				/* translators: %1s is replaced with an internal action name; %2$s is replaced with the file name; %3$s is replaced with the post id being acted on. %4$s is the user id running this action. */
				return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'simple-stack' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
			}
		}

		// Perform actions if allowed to do so.
		if ( $this->get_tab_security( $id ) ) {
			switch ( $action ) {
				case 'enable-ols-console':
					$action = 'enable_ols_console';
					$result = $this->manage_ols_console( $id, $action );
					break;
				case 'disable-ols-console':
					$action = 'disable_ols_console';
					$result = $this->manage_ols_console( $id, $action );
					break;
			}
		}

		return $result;
	}

	/**
	 * Gets the actions to be shown in the OLS Console tab.
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	public function get_actions( $id ) {

		return $this->get_ols_console_fields( $id );

	}

	/**
	 * Return a string that can be used in the header of the fields screen.
	 *
	 * @param string $type type.
	 * @param int    $id id.
	 */
	public function get_field_header_desc( $type, $id ) {

		// Check whether the ols console is enabled or not and return an appropriate string for the header.
		switch ( $type ) {
			case 1:
				$check_ols_console_status = $this->is_ols_console_enabled( $id );
				if ( empty( $check_ols_console_status ) ) :
					$desc  = __( 'The OpenLiteSpeed Webserver Manager console is not installed.', 'simple-stack' );
					$desc .= '<br />' . __( 'To install it please enter a username & password, then click the INSTALL button.', 'simple-stack' );
				else :
					$desc = __( 'When launching the OpenLiteSpeed Webserver Manager console you will encounter a security warning related to SSL. This is because the OLS server is using a self-signed certificate. Traffic is still encrypted between your browser and the console with the self-signed certificate so you should approve the browser request to ignore the warning.', 'simple-stack' );
				endif;
				break;
			default:
				$desc = '';
				break;
		}

		return $desc;

	}

	/**
	 * Gets the fields for the services to be shown in the OLS Console tab in the server details screen.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_ols_console_fields( $id ) {

		// Set up metabox items.
		$actions = array();

		// Check ols console enable or not.
		$check_ols_console_status = $this->is_ols_console_enabled( $id );

		/* Console is enabled, show appropriate fields for that. */
		if ( ! empty( $check_ols_console_status ) ) {

			// get data from server record.
			$check_ols_console_status = get_post_meta( $id, 'sstk_wpapp_enable_ols_console', true );
			$ols_console_username     = get_post_meta( $id, 'sstk_wpapp_username_for_ols_console', true );
			$ols_console_pass         = $this::decrypt( get_post_meta( $id, 'sstk_wpapp_password_for_ols_console', true ) );
			$ipv4                     = SSTK_SERVER()->get_ipv4_address( $id );
			$ols_console_url          = 'https://' . $ipv4 . ':7080';
			$launch                   = sprintf( '<a href="%s" target="_blank">', $ols_console_url ) . __( 'View Console', 'simple-stack' ) . '</a>';

			// classes to wrap around the data.
			$css_styles_start = '<div class="sstk_push_data_value_item sstk_server_status_push_data_value_item">';
			$css_styles_end   = '</div>';

			/**
			 * Display Console Enabled Flag.
			 */

			$actions[] = sstk_start_one_third_card( $this->get_tab_slug() ); // Start new card.

			$actions['ols-console-enabled-header'] = array(
				'label'          => __( 'Console Enabled?', 'simple-stack' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => '',
				),
			);

			$actions['ols-console-enabled-value'] = array(
				'type'           => 'custom_html',
				'label'          => '',
				'raw_attributes' => array(
					'std' => $css_styles_start . esc_html( $check_ols_console_status ) . $css_styles_end,
				),
			);

			$actions[] = sstk_end_card( $this->get_tab_slug() ); // Close up prior card.

			/**
			 * Display link to launch ols.
			 */

			$actions[] = sstk_start_one_third_card( $this->get_tab_slug() ); // Start new card.

			$actions['ols-console-launch-header'] = array(
				'label'          => __( 'View Console', 'simple-stack' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => '',
				),
			);

			$actions['ols-console-launch-value'] = array(
				'type'           => 'custom_html',
				'label'          => '',
				'raw_attributes' => array(
					'std' => $css_styles_start . wp_kses_post( $launch ) . $css_styles_end,
				),
			);

			$actions[] = sstk_end_card( $this->get_tab_slug() ); // Close up prior card.

			/**
			 * Show Credentials
			 */

			$actions[] = sstk_start_one_third_card( $this->get_tab_slug() ); // Start new card.

			$actions['ols-console-show-credentials'] = array(
				'label'          => __( 'Credentials', 'simple-stack' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => '',
				),
			);

			$actions[] = array(
				'label'          => '',
				'type'           => 'custom_html',
				'raw_attributes' => array(
					'std' => __( 'User Id: ', 'simple-stack' ) . sstk_wrap_clipboard_copy( $ols_console_username, false ) . '<br />' . __( 'Password: ', 'simple-stack' ) . sstk_wrap_clipboard_copy( $ols_console_pass, false ),
				),
			);

			$actions[] = sstk_end_card( $this->get_tab_slug() ); // Close up prior card.

		}

		if ( empty( $check_ols_console_status ) ) {
			/* Console is disabled, show appropriate fields for that. */
			$actions[] = sstk_start_half_card( $this->get_tab_slug() ); // Start new card.

			$actions['ols-console-header-main'] = array(
				'label'          => __( 'OpenLiteSpeed Webserver Manager Console', 'simple-stack' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => $this->get_field_header_desc( 1, $id ),
				),
			);

			$actions['username-for-ols-console'] = array(
				'label'          => __( 'User Name', 'simple-stack' ),
				'type'           => 'text',
				'raw_attributes' => array(
					'desc'           => '',
					'std'            => sstk_generate_alpha_numeric_string( 12 ),
					// the key of the field (the key goes in the request).
					'data-sstk-name' => 'username_for_ols_console',
					'spellcheck'     => 'false',
				),
			);

			$actions['password-for-ols-console'] = array(
				'label'          => __( 'Password', 'simple-stack' ),
				'type'           => 'password',
				'raw_attributes' => array(
					'desc'           => '',
					'std'            => sstk_generate_default_password(),
					// the key of the field (the key goes in the request).
					'data-sstk-name' => 'password_for_ols_console',
					'spellcheck'     => 'false',
				),
			);

			$actions['enable-ols-console'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'                 => __( 'Install', 'simple-stack' ),
					'desc'                => '',
					// make sure we give the user a confirmation prompt.
					'confirmation_prompt' => __( 'Are you sure you would like to enable the OpenLiteSpeed webserver manager console?', 'simple-stack' ),
					'data-sstk-fields'    => json_encode( array( '#sstk_app_action_username-for-ols-console', '#sstk_app_action_password-for-ols-console' ) ),
				),
				'type'           => 'button',
			);

			$actions[] = sstk_end_card( $this->get_tab_slug() ); // Close up prior card.

		} else {

			/* Console is enabled, show additional appropriate fields for that. */
			$actions[] = sstk_start_one_third_card( $this->get_tab_slug() ); // Start new card.

			$actions['disable-ols-console-header'] = array(
				'label' => __( 'Disable The OpenLiteSpeed Webserver Manager Console', 'simple-stack' ),
				'type'  => 'heading',
			);

			$actions['disable-ols-console'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'                 => __( 'Disable', 'simple-stack' ),
					'desc'                => '',
					// make sure we give the user a confirmation prompt.
					'confirmation_prompt' => __( 'Are you sure you would like to disable the OpenLiteSpeed webserver manager console?', 'simple-stack' ),
				),
				'type'           => 'button',
			);

			$actions[] = sstk_end_card( $this->get_tab_slug() ); // Close up prior card.

		}

		return $actions;

	}

	/**
	 * Take the most current data and format it for a nice display
	 *
	 * *** This function is no longer used.  Ok to delete later.
	 *
	 * @param int $id id.
	 */
	public function get_ols_console_enable_details( $id ) {

		// setup return variable.
		$return = '';

		// get data from server record.
		$check_ols_console_status = get_post_meta( $id, 'sstk_wpapp_enable_ols_console', true );
		$ols_console_username     = get_post_meta( $id, 'sstk_wpapp_username_for_ols_console', true );
		$ols_console_pass         = get_post_meta( $id, 'sstk_wpapp_password_for_ols_console', true );
		$ipv4                     = SSTK_SERVER()->get_ipv4_address( $id );
		$ols_console_url          = 'https://' . $ipv4 . ':7080';
		$launch                   = sprintf( '<a href="%s" target="_blank">', $ols_console_url ) . __( 'Launch OLS Console', 'simple-stack' ) . '</a>';

		// Format the data.
		$return              = '<div class="sstk_push_data sstk_server_status_push_data">';
				$return     .= '<div class="sstk_push_data_inner_wrap sstk_server_status_push_data_inner_wrap">';
				$return     .= '<div class="sstk_push_data_label_item sstk_server_status_push_data_label_item">';
					$return .= __( 'Is OLS Console Enabled?', 'simple-stack' );
				$return     .= '</div>';

				$return     .= '<div class="sstk_push_data_value_item sstk_server_status_push_data_value_item">';
					$return .= ! empty( $check_ols_console_status ) ? esc_html( 'Yes' ) : 'No';
				$return     .= '</div>';

				$return     .= '<div class="sstk_push_data_label_item sstk_server_status_push_data_label_item">';
					$return .= __( 'URL:', 'simple-stack' );
				$return     .= '</div>';

				$return     .= '<div class="sstk_push_data_label_item sstk_server_status_push_data_label_item">';
					$return .= $launch;
				$return     .= '</div>';

				$return     .= '<div class="sstk_push_data_label_item sstk_server_status_push_data_label_item">';
					$return .= __( 'User Name:', 'simple-stack' );
				$return     .= '</div>';

				$return     .= '<div class="sstk_push_data_value_item sstk_server_status_push_data_value_item">';
					$return .= sstk_wrap_clipboard_copy( esc_html( $ols_console_username ) );
				$return     .= '</div>';

				$return     .= '<div class="sstk_push_data_label_item sstk_server_status_push_data_label_item">';
					$return .= __( 'Password:', 'simple-stack' );
				$return     .= '</div>';

				$return     .= '<div class="sstk_push_data_value_item sstk_server_status_push_data_value_item">';
					$return .= sstk_wrap_clipboard_copy( SSTK()->decrypt( $ols_console_pass ) );
				$return     .= '</div>';

			$return .= '</div>';
		$return     .= '</div>';

		return $return;
	}

	/**
	 * Install / manage OLS Console
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean|object|string    success/failure/other
	 */
	public function manage_ols_console( $id, $action ) {

		// Get data about the server.
		$instance = $this->get_server_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			/* Translators: %s is the action id we're trying to execute. It is usually a string without spaces, not a number. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the server instance details for action %s', 'simple-stack' ), $action ) );
		}

		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );

		// Take certain steps based on the type of action.
		switch ( $action ) {
			case 'enable_ols_console':
				// Make sure all four required fields have been provided and return error if not.
				if ( empty( $args['username_for_ols_console'] ) ) {
					return new \WP_Error( __( 'Please enter user name', 'simple-stack' ) );
				}

				// Make sure all required fields have been provided and return error if not.
				if ( empty( $args['password_for_ols_console'] ) ) {
					return new \WP_Error( __( 'Please enter password', 'simple-stack' ) );
				}
				$command_name               = 'enable_ols_console';
				$args['enable_ols_console'] = $this->get_command_url( $id, $command_name, 'completed' );
				break;
			case 'disable_ols_console':
				$command_name                = 'disable_ols_console';
				$args['disable_ols_console'] = $this->get_command_url( $id, $command_name, 'completed' );
				break;
		}

		// Now lets make sure we escape all the arguments so it's safe for the command line.
		$original_args = $args;
		$args          = array_map(
			function( $item ) {
				return escapeshellarg( $item );
			},
			$args
		);

		// Get the full command to be executed by ssh.
		switch ( $action ) {
			case 'enable_ols_console':
				$user_name = $original_args['username_for_ols_console'];
				$pass      = $original_args['password_for_ols_console'];
				$run_cmd   = $this->turn_script_into_command(
					$instance,
					'ols_manage_admin_console.txt',
					array_merge(
						$args,
						array(
							'action' => $action,
							'user'   => $user_name,
							'pass'   => $pass,
						)
					)
				);
				break;
			case 'disable_ols_console':
				$run_cmd = $this->turn_script_into_command(
					$instance,
					'ols_manage_admin_console.txt',
					array_merge(
						$args,
						array( 'action' => $action )
					)
				);
				break;
		}

		do_action( 'sstk_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		// Execute command and check result.
		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'ols_manage_admin_console.txt' );
		if ( ! $success ) {
			/* Translators: %1$s: Action string; %2$s: Error string/message. */
			return new \WP_Error( sprintf( __( 'Unable to %1$s : %2$s', 'simple-stack' ), $action, $result ) );
		} else {

			// Success - update some postmetas and set response message according to action.
			switch ( $action ) {
				case 'enable_ols_console':
					update_post_meta( $id, 'sstk_wpapp_enable_ols_console', 'yes' );
					update_post_meta( $id, 'sstk_wpapp_username_for_ols_console', $user_name );
					update_post_meta( $id, 'sstk_wpapp_password_for_ols_console', SSTK()->encrypt( $pass ) );
					$success = array(
						'msg'     => __( 'OLS console successfully enabled.', 'simple-stack' ),
						'refresh' => 'yes',
					);
					break;
				case 'disable_ols_console':
					delete_post_meta( $id, 'sstk_wpapp_enable_ols_console' );
					delete_post_meta( $id, 'sstk_wpapp_username_for_ols_console' );
					delete_post_meta( $id, 'sstk_wpapp_password_for_ols_console' );
					$success = array(
						'msg'     => __( 'OLS console successfully disabled.', 'simple-stack' ),
						'refresh' => 'yes',
					);
					break;
			}
		}

		return $success;

	}

	/**
	 * Check OLS Console
	 *
	 * @param int $id     The postID of the server cpt.
	 * @return boolean    true/false
	 */
	public function is_ols_console_enabled( $id ) {

		$check_ols_console_enable = get_post_meta( $id, 'sstk_wpapp_enable_ols_console', 'yes' );

		if ( ! empty( $check_ols_console_enable ) ) {

			return true;
		}

		return false;
	}
}

new SSTK_WORDPRESS_TABS_SERVER_OLS_CONSOLE();
