<?php
/** Modified for Simple Stack by One Dog Solutions on 2026-11-23. */
/**
 * PHP Tab.
 *
 * @package simple-stack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SSTK_WORDPRESS_TABS_PHP_OPTIONS
 */
class SSTK_WORDPRESS_TABS_PHP_OPTIONS extends SSTK_WORDPRESS_TABS {

	/**
	 * SSTK_WORDPRESS_TABS_PHP constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_filter( "sstk_app_{$this->get_app_name()}_get_tabnames", array( $this, 'get_tab' ), 10, 2 );
		add_filter( "sstk_app_{$this->get_app_name()}_get_tabs", array( $this, 'get_tab_fields' ), 10, 2 );
		add_filter( "sstk_app_{$this->get_app_name()}_tab_action", array( $this, 'tab_action' ), 10, 3 );

		// Allow the change_php_version action to be triggered via an action hook.
		add_action( 'sstk_wordpress-app_do_change_php_version', array( $this, 'change_php_version_action' ), 10, 2 );

		// Allow the advanced add_php_param (change_php_options) action to be triggered via an action hook.
		add_action( 'sstk_wordpress-app_do_change_php_options', array( $this, 'change_php_options_action' ), 10, 3 );

	}

	/**
	 * Returns a string that can be used as the unique name for this tab.
	 */
	public function get_tab_slug() {
		return 'php-options';
	}

	/**
	 * Returns a string that is the name of a view TEAM permission required to view this tab.
	 */
	public function get_view_tab_team_permission_slug() {
		return 'view_wpapp_site_php_options_tab';
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
				'label' => __( 'PHP', 'simple-stack' ),
				'icon'  => 'fad fa-cog',
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
	 * Gets the fields to be shown in the PHP tab.
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
		$valid_actions = array( 'restart-php-service', 'change-php-version', 'change-php-common-options', 'change-php-advanced-options' );
		if ( in_array( $action, $valid_actions, true ) ) {
			if ( ! $this->get_tab_security( $id ) ) {
				return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'simple-stack' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
			}
		}

		if ( $this->get_tab_security( $id ) ) {
			switch ( $action ) {
				case 'restart-php-service':
					$result = $this->restart_php_service( $id, 'restart_php' );
					if ( ! is_wp_error( $result ) ) {
						$result = array( 'refresh' => 'yes' );
					}
					break;
				case 'change-php-version':
					// change the php version.
					$result = $this->change_php_version( $id, 'change_php_version' );
					if ( ! is_wp_error( $result ) ) {
						$result = array( 'refresh' => 'yes' );
					}
					break;
				case 'change-php-common-options':
					// Verify that the user is allowed to update php site options.
					if ( ! $this->sstk_wpapp_user_can_change_php_options( $id ) ) {
						$msg    = __( 'You don\'t have permission to update PHP options for this site.', 'simple-stack' );
						$result = array(
							'refresh' => 'yes',
							'msg'     => $msg,
						);
						break;
					}
					// User is allowed to update php options so proceed.
					$result = $this->change_php_options( $id, 'add_php_param' );
					if ( ! is_wp_error( $result ) ) {
						$result = array( 'refresh' => 'yes' );
					}
					break;
				case 'change-php-advanced-options':
					// Verify that the user is allowed to update php site options.
					if ( ! $this->sstk_wpapp_user_can_change_php_advanced_options( $id ) ) {
						$msg    = __( 'You don\'t have permission to update PHP options for this site.', 'simple-stack' );
						$result = array(
							'refresh' => 'yes',
							'msg'     => $msg,
						);
						break;
					}
					// User is allowed to update php options so proceed.
					$result = $this->change_php_options( $id, 'add_php_param' );
					if ( ! is_wp_error( $result ) ) {
						$result = array( 'refresh' => 'yes' );
					}
					break;
			}
		}
		return $result;
	}

	/**
	 * Gets the actions to be shown in the PHP tab.
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	public function get_actions( $id ) {

		// Bail if site is not enabled.
		if ( ! $this->is_site_enabled( $id ) ) {
			return $this->get_disabled_header_field();
		}

		// Basic checks passed, ok to proceed.
		return array_merge(
			$this->get_php_version_fields( $id ),
			$this->get_php_restart_fields( $id ),
			$this->get_common_php_options_fields( $id ),
			$this->get_advanced_php_options_fields( $id ),
		);

	}

	/**
	 * Gets the fields required to allow the user to restart the PHP service for the current site.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_php_restart_fields( $id ) {

		$actions = array();

		// Start new card.
		$actions[] = sstk_start_half_card( $this->get_tab_slug() );

		$desc = __( 'Restart OpenLiteSpeed to reload this site\'s PHP configuration. This temporarily interrupts all sites on the server.', 'simple-stack' );

		/* Set the text of the confirmation prompt. */
		$confirmation_prompt = __( 'Are you sure you would like to restart OpenLiteSpeed? All sites on this server will be temporarily unavailable.', 'simple-stack' );

		$actions['restart-php-header'] = array(
			/* Translators: %s is a fontawesome or similar icon. */
			'label'          => sstk_apply_restart_icon( __( '%s Restart OpenLiteSpeed', 'simple-stack' ) ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $desc,
			),
		);

		$actions['restart-php-service'] = array(
			'label'          => '',
			'raw_attributes' => array(
				/* Translators: %s is a fontawesome or similar icon. */
				'std'                 => sstk_apply_restart_icon( __( '%s Restart OpenLiteSpeed', 'simple-stack' ) ),
				'confirmation_prompt' => $confirmation_prompt,
			),
			'type'           => 'button',
		);

		// Close up prior card.
		$actions[] = sstk_end_card( $this->get_tab_slug() );

		return $actions;

	}

	/**
	 * Gets the fields for the PHP version options to be shown in the PHP tab.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_php_version_fields( $id ) {

		$actions = array();

		// Start new card.
		$actions[] = sstk_start_half_card( $this->get_tab_slug() );

		/* What is the current php version on the site? */
		$current_version = $this->get_php_version_for_app( $id );

		/* Set the text of the confirmation prompt */
		$confirmation_prompt = '';
		$confirmation_prompt = __( 'Are you sure you would like to switch PHP versions?', 'simple-stack' );

		$desc = __( 'Use this section to change the PHP version for this site. If you have installed any custom PHP options, you will need to reinstall them after switching versions. Simple Stack supports PHP 8.3, 8.4 and 8.5; PHP 8.4 is the default.', 'simple-stack' );
		$desc = sprintf( '<details>%s %s</details>', sstk_get_html5_detail_element_summary_text(), $desc );

		$actions['change-php-version-header'] = array(
			/* Translators: %s is a fontawesome or similar icon. */
			'label'          => sstk_apply_php_icon( __( '%s Change PHP Version', 'simple-stack' ) ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $desc,
			),
		);

		// Get an associated array of PHP versions.
		$php_select_options_raw = $this->get_php_versions( $id );

		// OpenLiteSpeed selects PHP through the LSPHP external processor name.
		$php_select_options = array();
		foreach ( $php_select_options_raw as $php_version ) {
			$service = sstk_convert_php_version_to_ols_service( $php_version );
			if ( false !== $service ) {
				$php_select_options[ $service ] = $php_version;
			}
		}

		// Convert the current version so it can be selected by default.
		$current_version = sstk_convert_php_version_to_ols_service( $current_version );

		$actions['change-php-version-new-version'] = array(
			'label'          => __( 'PHP Version', 'simple-stack' ),
			'desc'           => '',
			'type'           => 'select',
			'raw_attributes' => array(
				'options'        => $php_select_options,
				'std'            => $current_version,
				// the key of the field (the key goes in the request).
				'data-sstk-name' => 'new_php_version',
			),
		);

		$actions['change-php-version'] = array(
			'label'          => '',
			'raw_attributes' => array(
				/* Translators: %s is a fontawesome or similar icon. */
				'std'                 => sstk_apply_change_icon( __( '%s Change PHP Version', 'simple-stack' ) ),
				'confirmation_prompt' => $confirmation_prompt,
				// fields that contribute data for this action.
				'data-sstk-fields'    => json_encode( array( '#sstk_app_action_change-php-version-new-version' ) ),
			),
			'type'           => 'button',
		);

		// Close up prior card.
		$actions[] = sstk_end_card( $this->get_tab_slug() );

		return $actions;

	}

	/**
	 * Gets the fields for the common PHP options to be shown in the PHP tab.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_common_php_options_fields( $id ) {

		// Start new card.
		$actions[] = sstk_start_half_card( $this->get_tab_slug() );

		/* Set the text of the confirmation prompt */
		$confirmation_prompt = __( 'Are you sure you would like to set this option?', 'simple-stack' );

		$desc = __( 'These are the most commonly changed PHP options.  Make sure you set a VALID value for the option - we do not validate your input before sending it on to the server! ', 'simple-stack' );
		$desc = sprintf( '<details>%s %s</details>', sstk_get_html5_detail_element_summary_text(), $desc );

		$actions['change-php-common-options-header'] = array(
			/* Translators: %s is a fontawesome or similar icon. */
			'label'          => sstk_apply_change_icon( __( '%s Add Or Update Some Common PHP Options', 'simple-stack' ) ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $desc,
			),
		);

		/* Check permissions and fill out fields array if appropriate */
		if ( $this->sstk_wpapp_user_can_change_php_options( $id ) ) {

			$actions['change-php-common-options-select'] = array(
				'label'          => __( 'Select a common PHP option to add or change', 'simple-stack' ),
				'type'           => 'select',
				'raw_attributes' => array(
					'options'        => $this->get_common_php_options_list(),
					'desc'           => '',
					// the key of the field (the key goes in the request).
					'data-sstk-name' => 'php_common_option_to_set',
				),
			);

			$actions['change-php-common-options-value'] = array(
				'label'          => __( 'Enter a value for the option', 'simple-stack' ),
				'type'           => 'text',
				'raw_attributes' => array(
					/* Translators: %s is a fontawesome or similar icon. */
					'desc'           => sstk_apply_warning_icon( __( '%s Make sure you set a VALID value for the option - we do not validate your input before sending it on to the server!', 'simple-stack' ) ),
					// the key of the field (the key goes in the request).
					'data-sstk-name' => 'php_common_option_value',
				),
			);

			$actions['change-php-common-options'] = array(
				'label'          => '',
				'raw_attributes' => array(
					/* Translators: %s is a fontawesome or similar icon. */
					'std'                 => sstk_apply_install_icon( __( '%s Set the selected option', 'simple-stack' ) ),
					'confirmation_prompt' => $confirmation_prompt,                  // fields that contribute data for this action.
					'data-sstk-fields'    => json_encode( array( '#sstk_app_action_change-php-common-options-select', '#sstk_app_action_change-php-common-options-value' ) ),
				),
				'type'           => 'button',
			);
		} else {
			// Show message indicating that user cannot update php options.
			$actions['change-php-common-options-no-permit'] = array(
				'label'          => '',
				'type'           => 'custom_html',
				'raw_attributes' => array(
					'std' => '<h4>' . __( '***You do not have permissions to add or update common php options.***', 'simple-stack' ) . '</h4>',
				),
			);
		}

		// Close up prior card.
		$actions[] = sstk_end_card( $this->get_tab_slug() );

		// Start new card.
		$actions[] = sstk_start_half_card( $this->get_tab_slug() );

		$custom_options_desc = __( 'Here are the custom PHP options currently set for this site.', 'simple-stack' );
		$custom_options_desc = sprintf( '<details>%s %s</details>', sstk_get_html5_detail_element_summary_text(), $custom_options_desc );

		/* Fields to show existing options that the user has set */
		$actions['change-php-common-existing-header'] = array(
			/* Translators: %s is a fontawesome or similar icon. */
			'label'          => sstk_apply_php_icon( __( '%s Existing Options', 'simple-stack' ) ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $custom_options_desc,
			),
		);

		/* Get the existing php options that were set from the database and construct a custom HTML string*/
		$customhtml   = '';
		$saved_values = sstk_maybe_unserialize( get_post_meta( $id, 'wpapp_php_custom_options', true ) );
		if ( empty( $saved_values ) || ( ! is_array( $saved_values ) ) ) {
			/* Translators: %s is a fontawesome or similar icon. */
			$customhtml = '<p>' . sstk_apply_x_icon( __( '%s No common or custom php values have been set for this site.', 'simple-stack' ) ) . '</p>';
		} else {
			// At least one value was set in the past so lets loop through the array and construct the html.
			foreach ( $saved_values as $key => $value ) {
				$value_pair  = "$key: <b>$value</b>";
				$value_pair  = sstk_apply_checkmark_icon( '%s ' . $value_pair );
				$customhtml .= '<p>' . $value_pair . '</p>';
			}
		}

		$actions['change-php-common-existing-values'] = array(
			'label'          => '',
			'type'           => 'custom_html',
			'raw_attributes' => array(
				'std' => $customhtml,
			),
		);

		// Close up prior card.
		$actions[] = sstk_end_card( $this->get_tab_slug() );

		return $actions;

	}

	/**
	 * Get a list of "common" php functions.
	 *
	 * Will be used to show list to user.
	 * If you change something here, change the function below it as well!
	 */
	private function get_common_php_options_list() {
		return array(
			'upload_max_filesize' => 'upload_max_filesize: requires a numeric value in megabytes, eg 10M would be 10 megabtyes',
			'post_max_size'       => 'post_max_size: requires a numeric value in megabytes, eg 10M would be 10 megabtyes',
			'memory_limit'        => 'memory_limit: requires a numeric value in megabytes, eg 10M would be 10 megabtyes',
			'max_execution_time'  => 'max_execution_time: requires a numeric value in seconds, eg 30 would be 30 seconds',
			'max_input_time'      => 'max_input_time: requires a numeric value in seconds, eg 60 would be 60 seconds',
			'max_input_vars'      => 'max_input_vars: requires a numeric value.',
		);
	}

	/**
	 * Get a list of "common" php function keys.
	 *
	 * Will be used to validate when user chooses action.
	 */
	private function get_common_php_options_keys() {
		return array( 'upload_max_filesize', 'post_max_size', 'memory_limit', 'max_execution_time', 'max_input_time', 'max_input_vars' );
	}

	/**
	 * Gets the fields for the Advanced PHP options to be shown in the PHP tab.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_advanced_php_options_fields( $id ) {

		if ( ! $this->sstk_wpapp_user_can_change_php_advanced_options( $id ) ) {
			return array();
		}

		// Start new card.
		$actions[] = sstk_start_half_card( $this->get_tab_slug() );

		/* Set the text of the confirmation prompt */
		$confirmation_prompt = __( 'Are you sure you would like to set this option?', 'simple-stack' );

		$desc = __( 'Use this to set a custom value for a PHP.ini value. Custom values are placed in the web server vhost configuration files and apply only to this site. Please make sure that you set a VALID value for the option - we do not validate your input before sending it on to the server! ', 'simple-stack' );
		$desc = sprintf( '<details>%s %s</details>', sstk_get_html5_detail_element_summary_text(), $desc );

		$actions['change-php-advanced-options-header'] = array(
			/* Translators: %s is a fontawesome or similar icon. */
			'label'          => sstk_apply_change_icon( __( '%s [Danger Zone] Add Or Update a PHP Option', 'simple-stack' ) ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => $desc,
			),
		);

		if ( $this->sstk_wpapp_user_can_change_php_options( $id ) ) {

			$actions['change-php-advanced-options-item'] = array(
				'label'          => __( 'php.ini item', 'simple-stack' ),
				'type'           => 'text',
				'raw_attributes' => array(
					'desc'           => __( 'Enter a text string corresponding to an item in the php.ini file. Make sure you set a valid string for the option - we do not validate your input before sending it on to the server!', 'simple-stack' ),
					// the key of the field (the key goes in the request).
					'data-sstk-name' => 'php_advanced_option_to_set',
				),
			);

			$actions['change-php-advanced-options-value'] = array(
				'label'          => __( 'Enter a value for the option', 'simple-stack' ),
				'type'           => 'text',
				'raw_attributes' => array(
					/* Translators: %s is a fontawesome or similar icon. */
					'desc'           => sstk_apply_warning_icon( __( ' %s Make sure you set a VALID value for the option - we do not validate your input before sending it on to the server!', 'simple-stack' ) ),
					// the key of the field (the key goes in the request).
					'data-sstk-name' => 'php_advanced_option_value',
				),
			);

			$actions['change-php-advanced-options'] = array(
				'label'          => '',
				'raw_attributes' => array(
					/* Translators: %s is a fontawesome or similar icon. */
					'std'                 => sstk_apply_install_icon( __( '%s Set the selected option', 'simple-stack' ) ),
					'confirmation_prompt' => $confirmation_prompt,                  // fields that contribute data for this action.
					'data-sstk-fields'    => json_encode( array( '#sstk_app_action_change-php-advanced-options-item', '#sstk_app_action_change-php-advanced-options-value' ) ),
				),
				'type'           => 'button',
			);
		} else {
			// Show message indicating that user cannot update php options.
			$actions['change-php-advanced-options-header-no-permit'] = array(
				'label'          => '',
				'type'           => 'custom_html',
				'raw_attributes' => array(
					'std' => '<h4>' . __( '***You do not have permissions to add or update advanced php options.***', 'simple-stack' ) . '</h4>',
				),
			);
		}

		// Close up prior card.
		$actions[] = sstk_end_card( $this->get_tab_slug() );

		return $actions;

	}

	/**
	 * Helper function to change the PHP version.
	 *
	 * Can be called directly or by an action hook.
	 *
	 * Action hook: sstk_wordpress-app_do_change_php_version  (Optional).
	 *
	 * @param int    $id     The postID of the app cpt.
	 * @param string $php_version The new PHP version: 8.3, 8.4 or 8.5.
	 *
	 * @return string|WP_Error
	 */
	public function change_php_version_action( $id, $php_version ) {

		$php_version = (string) $php_version;
		if ( ! in_array( $php_version, array( '8.3', '8.4', '8.5' ), true ) ) {
			return new \WP_Error( __( 'You must specify a supported PHP version: 8.3, 8.4 or 8.5.', 'simple-stack' ) );
		}

		/* If we're already at the current version, do nothing. */
		if ( $this->get_php_version_for_app( $id ) === $php_version ) {
			return true;
		}

		// OpenLiteSpeed uses the LSPHP external processor name in the shell command.
		$args['new_php_version'] = sstk_convert_php_version_to_ols_service( $php_version );

		return $this->change_php_version( $id, 'change_php_version', $args );

	}


	/**
	 * Switch PHP Version.
	 *
	 * @param int    $id     The postID of the app cpt.
	 * @param string $action The action to be performed (this matches the string required in the bash scripts).
	 * @param array  $in_args Alternative source of arguments passed via action hook or direct function call instead of pulling from $_POST.
	 *
	 * @return boolean|WP_Error    success/failure
	 */
	private function change_php_version( $id, $action, $in_args = array() ) {
		$instance = $this->get_app_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'simple-stack' ), $action ) );
		}

		if ( empty( $in_args ) ) {
			// Get data from the POST request.
			$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );
		} else {
			$args = $in_args;
		}

		// Accept a dotted version from internal callers or an LSPHP service from the UI.
		$new_php_version = isset( $args['new_php_version'] ) ? (string) $args['new_php_version'] : '';
		if ( in_array( $new_php_version, array( '8.3', '8.4', '8.5' ), true ) ) {
			$new_php_version = sstk_convert_php_version_to_ols_service( $new_php_version );
		}
		if ( ! in_array( $new_php_version, array( 'lsphp83', 'lsphp84', 'lsphp85' ), true ) ) {
			return new \WP_Error( __( 'You must specify a supported PHP version: 8.3, 8.4 or 8.5.', 'simple-stack' ) );
		}
		$args['new_php_version'] = escapeshellarg( $new_php_version );

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'change_php_version_misc.txt',
			array_merge(
				$args,
				array(
					'command' => "{$action}_site",
					'action'  => $action,
					'domain'  => get_post_meta(
						$id,
						'wpapp_domain',
						true
					),
				)
			)
		);

		do_action( 'sstk_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'change_php_version_misc.txt' );
		if ( ! $success ) {
			/* Translators: %1 is an action, %2 is the site / domain name. */
			return new \WP_Error( sprintf( __( 'Unable to %1$s site: %2$s', 'simple-stack' ), $action, $result ) );
		}

		// Record the dotted version rather than the LSPHP service name.
		$new_php_version = sstk_convert_ols_phpservice_to_php_version( $new_php_version );
		$this->set_php_version_for_app( $id, $new_php_version );

		return $success;
	}

	/**
	 * Helper function to change php options
	 *
	 * Can be called directly or by an action hook.
	 *
	 * Action hook: sstk_wordpress-app_do_change_php_options  (Optional).
	 *
	 * @param int    $id     The postID of the app cpt.
	 * @param string $php_param The php option/parameter to set - eg: max_execution_time.
	 * @param string $php_value The option value.
	 *
	 * @return string|WP_Error
	 */
	public function change_php_options_action( $id, $php_param, $php_value ) {

		// Array to pass into function call.
		$args['php_advanced_option_to_set'] = $php_param;
		$args['php_advanced_option_value']  = $php_value;

		$return = $this->change_php_options( $id, 'add_php_param', $args );

		return $return;

	}

	/**
	 * Change PHP Options.
	 *
	 * @param int    $id     The postID of the app cpt.
	 * @param string $action The action to be performed (this matches the string required in the bash scripts).
	 * @param array  $in_args Alternative source of arguments passed via action hook or direct function call instead of pulling from $_POST.
	 *
	 * @return boolean|WP_Error    success/failure
	 */
	private function change_php_options( $id, $action, $in_args = array() ) {
		$instance = $this->get_app_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'simple-stack' ), $action ) );
		}

		if ( empty( $in_args ) ) {
			// Get data from the POST request.
			$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );
		} else {
			$args = $in_args;
		}

		// Are we changing a 'common' option or an 'advanced' option?
		if ( ! empty( $args['php_common_option_value'] ) ) {
			// common options.
			$php_option       = sanitize_text_field( $args['php_common_option_to_set'] );
			$php_option_value = sanitize_text_field( $args['php_common_option_value'] );

			// Make sure the "common" option is in a known good list.
			if ( ! in_array( $php_option, $this->get_common_php_options_keys(), true ) ) {
				return new \WP_Error( __( 'Whoa...looks like you are trying to hack the system - this is most definitely not allowed!', 'simple-stack' ) );
			}

			// Now make sure we sanitize the fields for use on the linux command line and put them back into the array - in this case under a different KEY!...
			$args['php_option_to_set'] = escapeshellarg( $args['php_common_option_to_set'] );
			$args['php_option_value']  = escapeshellarg( $args['php_common_option_value'] );
		} else {
			if ( ! empty( $args['php_advanced_option_value'] ) ) {
				// advanced option.
				$php_option       = sanitize_text_field( $args['php_advanced_option_to_set'] );
				$php_option_value = sanitize_text_field( $args['php_advanced_option_value'] );

				// Now make sure we sanitize the fields for use on the linux command line and put them back into the array - in this case under a different KEY!...
				$args['php_option_to_set'] = escapeshellarg( $args['php_advanced_option_to_set'] );
				$args['php_option_value']  = escapeshellarg( $args['php_advanced_option_value'] );
			} else {
				// whoops, neither field has a value so error...
				return new \WP_Error( sprintf( __( 'Looks like no valid data was entered so we are unable to execute this request for action %s. Please check to make sure you have entered both an option and a value for the option!', 'simple-stack' ), $action ) );
			}
		}

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'change_php_option_misc.txt',
			array_merge(
				$args,
				array(
					'command' => "{$action}_site",
					'action'  => $action,
					'domain'  => get_post_meta(
						$id,
						'wpapp_domain',
						true
					),
				)
			)
		);

		do_action( 'sstk_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		$result = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );

		$success = $this->is_ssh_successful( $result, 'change_php_option_misc.txt' );
		if ( ! $success ) {
			return new \WP_Error( sprintf( __( 'Unable to %1$s site: %2$s', 'simple-stack' ), $action, $result ) );
		}

		// Record the new data onto the app record.
		$saved_values = sstk_maybe_unserialize( get_post_meta( $id, 'wpapp_php_custom_options', true ) );
		if ( empty( $saved_values ) || ( ! is_array( $saved_values ) ) ) {
			$saved_values = array();
		}
		$saved_values[ $php_option ] = $php_option_value;
		update_post_meta( $id, 'wpapp_php_custom_options', $saved_values );

		return $success;
	}

	/**
	 * Restart OpenLiteSpeed to reload the site's PHP configuration.
	 *
	 * @param int    $id     The postID of the app cpt.
	 * @param string $action The action to be performed (this matches the string required in the bash scripts).
	 *
	 * @return boolean|WP_Error    success/failure
	 */
	private function restart_php_service( $id, $action ) {
		$instance = $this->get_app_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'simple-stack' ), $action ) );
		}

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'restart_php_service.txt',
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

		$result = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );

		$success = $this->is_ssh_successful( $result, 'restart_php_service.txt' );
		if ( ! $success ) {
			return new \WP_Error( sprintf( __( 'Unable to %1$s site: %2$s', 'simple-stack' ), $action, $result ) );
		}

		return $success;

	}

}

new SSTK_WORDPRESS_TABS_PHP_OPTIONS();
