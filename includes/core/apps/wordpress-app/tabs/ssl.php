<?php
/** Modified for Simple Stack by One Dog Solutions on 2026-11-23. */
/**
 * SSL
 *
 * @package simple-stack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SSTK_WORDPRESS_TABS_SSL
 */
class SSTK_WORDPRESS_TABS_SSL extends SSTK_WORDPRESS_TABS {

	/**
	 * SSTK_WORDPRESS_TABS_SSL constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_filter( "sstk_app_{$this->get_app_name()}_get_tabnames", array( $this, 'get_tab' ), 10, 2 );
		add_filter( "sstk_app_{$this->get_app_name()}_get_tabs", array( $this, 'get_tab_fields' ), 10, 2 );
		add_filter( "sstk_app_{$this->get_app_name()}_tab_action", array( $this, 'tab_action' ), 10, 3 );

		// Allow the SSL status action to be triggered via an action hook.
		add_action( 'sstk_wordpress-app_do_toggle_ssl_status', array( $this, 'toggle_ssl_status_action' ), 10, 2 );  // Toggle SSL state based on what's there now.
		add_action( 'sstk_wordpress-app_do_toggle_ssl_status_on', array( $this, 'toggle_ssl_status_on_action' ), 10, 2 );  // Force SSL 'on' regardless of current state.

	}

	/**
	 * Returns a string that can be used as the unique name for this tab.
	 */
	public function get_tab_slug() {
		return 'ssl';
	}

	/**
	 * Returns a string that is the name of a view TEAM permission required to view this tab.
	 */
	public function get_view_tab_team_permission_slug() {
		return 'view_wpapp_site_ssl_tab';
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
				'label' => __( 'SSL', 'simple-stack' ),
				'icon'  => 'fad fa-lock-alt',
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
	 * Gets the fields to be shown in the SSL tab.
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
		$valid_actions = array( 'ssl-status', 'ssl-http2-status', 'ssl-flip-meta-status', 'custom-ssl-flip-meta-status' );
		if ( in_array( $action, $valid_actions, true ) ) {
			if ( ! $this->get_tab_security( $id ) ) {
				return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'simple-stack' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
			}
		}

		if ( $this->get_tab_security( $id ) ) {
			switch ( $action ) {
				case 'ssl-status':
				case 'ssl-http2-status':
					$result = $this->toggle_ssl_status_action( $id, $action );
					break;
				case 'ssl-flip-meta-status':
					$result = $this->toggle_ssl_meta_status( $id, $action );
					break;
				case 'custom-ssl-flip-meta-status':
					$result = $this->toggle_custom_ssl_meta_status( $id, $action );
					break;
			}
		}

		return $result;
	}

	/**
	 * Gets the actions to be shown in the SSL tab.
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	public function get_actions( $id ) {

		$actions = array();

		// Bail if site is not enabled.
		if ( ! $this->is_site_enabled( $id ) ) {
			return $this->get_disabled_header_field();
		}

		// Bail if the site is a multisite enabled with wildcard ssl options.
		$multisite_type = get_post_meta( $id, 'wpapp_multisite_type', true );
		if ( 'subdomain-wildcard-ssl' === $multisite_type ) {
			// Show a special header.
			$actions['ssl-status-header-wc-enabled'] = array(
				'name'           => '',
				/* Translators: %s is a fontawesome or similar icon. */
				'label'          => sstk_apply_ssl_icon( __( '%s WildCard SSL Multisite', 'simple-stack' ) ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => __( 'This site is a MULTISITE that is configured to use a wildcard SSL certificate. You need to manage SSL options under the MULTISITE tab.', 'simple-stack' ),
				),
			);

			return $actions;
		}

		/**
		 * Basic checks passed, ok to proceed
		 */

		// Get SSL status.
		if ( true === $this->get_site_local_ssl_status( $id ) ) {
			$status = 'on';
		} else {
			$status = 'off';
		}

		/* Set the confirmation prompt based on the the current status of this flag */
		$confirmation_prompt = '';
		if ( 'on' === $status ) {
			$confirmation_prompt = __( 'Are you sure you would like to disable SSL?', 'simple-stack' );
		} else {
			$confirmation_prompt = __( 'Are you sure you would like to enable SSL?', 'simple-stack' );
		}

		// Get HTTP2 status.
		$http2_status = $this->http2_status( $id );

		/**
		 * SSL.
		 */
		if ( $this->get_site_local_custom_ssl_status( $id ) ) {
			/**
			 * Custom SSL is enabled.
			 */
			$actions[] = sstk_start_half_card( $this->get_tab_slug() ); // Start new card.

			$actions['ssl-status-header'] = array(
				'name'           => '',
				/* Translators: %s is a fontawesome or similar icon. */
				'label'          => sstk_apply_warning_icon( __( '%s Custom SSL Enabled', 'simple-stack' ) ),
				'type'           => 'heading',
				'raw_attributes' => array(
					/* Translators: %s is a fontawesome or similar icon. */
					'desc' => sstk_apply_warning_icon( __( '%s The admin has indicated that a Custom SSL certificate is installed for this site. You cannot request a regular SSL certificate from LetsEncrypt at this time.', 'simple-stack' ) ),
				),
			);

			$actions[] = sstk_end_card( $this->get_tab_slug() ); // Close up prior card.

		} else {
			/**
			 * Standard SSL.
			 */
			$actions[] = sstk_start_half_card( $this->get_tab_slug() ); // Start new card.

			$desc                         = __( 'Turning this on will result in an attempt to obtain a certificate from LETSEncrypt.  <br />If it fails, check the logs under the SSH LOG menu option. <br />Note that if you attempt to turn on SSL too many times in a row LETSEncrypt will block your domain for 7 days or more.', 'simple-stack' );
			$desc                         = sprintf( '<details>%s %s</details>', sstk_get_html5_detail_element_summary_text(), $desc );
			$actions['ssl-status-header'] = array(
				'name'           => '',
				/* Translators: %s is a fontawesome or similar icon. */
				'label'          => sstk_apply_ssl_icon( __( '%s SSL', 'simple-stack' ) ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => $desc,
				),
			);

			if ( 'on' !== $status ) {
				$desc = __( 'Click to enable SSL.', 'simple-stack' );
			} else {
				$desc = __( 'Click to disable SSL', 'simple-stack' );
			}

			$actions['ssl-status'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'on_label'            => __( 'Enabled', 'simple-stack' ),
					'off_label'           => __( 'Disabled', 'simple-stack' ),
					'std'                 => $status === 'on',
					'desc'                => $desc,
					'confirmation_prompt' => $confirmation_prompt,
				),
				'type'           => 'switch',
			);

			$actions[] = sstk_end_card( $this->get_tab_slug() ); // Close up prior card.

			/**
			 * Show SSL notes if SSL is not turned on.
			 */
			if ( 'on' <> $status ) {

				$actions[] = sstk_start_half_card( $this->get_tab_slug() ); // Start new card.

				$actions['ssl-notes-heading'] = array(
					'type'           => 'heading',
					/* Translators: %s is a fontawesome or similar icon. */
					'label'          => sstk_apply_warning_icon( __( '%s Some things to be aware of before enabling SSL', 'simple-stack' ) ),
					'raw_attributes' => array(
						'desc' => __( 'Please read before attempting to turn on SSL for your site!', 'simple-stack' ),
					),
				);
				$actions['ssl-notes-1']       = array(
					'type'           => 'custom_html',
					'label'          => '',
					'raw_attributes' => array(
						'std' => __( '1. If you are behind a proxy service, you might need to turn it off temporarily prior to enabling ssl.  This allows LETSEncrypt to connect directly and verify your site. <b>CloudFlare</b> will usually work as-is but if you are having trouble, try turning it off as well.', 'simple-stack' ),
					),
				);
				$actions['ssl-notes-2']       = array(
					'type'           => 'custom_html',
					'label'          => '',
					'raw_attributes' => array(
						'std' => __( '2. If your server is behind a firewall or NAT, make sure HTTPS traffic on port 443 is allowed before enabling SSL here.', 'simple-stack' ),
					),
				);

				$actions[] = sstk_end_card( $this->get_tab_slug() ); // Close up prior card.
			}

			/**
			 * If SSL is on, show HTTP 2 options
			 */
			if ( 'on' === $status ) {

				$actions[] = sstk_start_half_card( $this->get_tab_slug() ); // Start new card.

				// Check multisite status and do nothing if multisite is enabled.
				if ( 'on' <> get_post_meta( $id, 'wpapp_multisite_enabled', true ) ) {

					$http_fragment_text = __( 'HTTP2', 'simple-stack' );

					/* Set the confirmation prompt based on the current status of this flag */
					if ( 'on' === $http2_status ) {
						$confirmation_prompt_http2 = sprintf( __( 'Are you sure you would like to disable %s?', 'simple-stack' ), $http_fragment_text );
					} else {
						$confirmation_prompt_http2 = sprintf( __( 'Are you sure you would like to enable %s?', 'simple-stack' ), $http_fragment_text );
					}

					$actions['ssl-http2-header'] = array(
						'name'           => '',
						'label'          => sprintf( '%s', $http_fragment_text ),
						'type'           => 'heading',
						'raw_attributes' => array(
							'desc' => sprintf( __( 'Manage %s', 'simple-stack' ), $http_fragment_text ),
						),
					);

					$actions['ssl-http2-status'] = array(
						'label'          => '',
						'raw_attributes' => array(
							'on_label'            => __( 'Enabled', 'simple-stack' ),
							'off_label'           => __( 'Disabled', 'simple-stack' ),
							'std'                 => $http2_status === 'on',
							'confirmation_prompt' => $confirmation_prompt_http2,
						),
						'type'           => 'switch',
					);

				}

				// Close up prior card.
				$actions[] = sstk_end_card( $this->get_tab_slug() );
			}
		}

		/**
		 * Advanced action to flip metas only
		 */

		$actions[]                = sstk_start_half_card( $this->get_tab_slug() ); // Start new card.
		$confirmation_prompt_meta = '';
		if ( 'on' === $status ) {
			$confirmation_prompt_meta = __( 'Are you sure you would like to flip the SSL flag to show SSL is disabled? This will not execute any code to disable SSL if it is actually enabled on the server.', 'simple-stack' );
		} else {
			$confirmation_prompt_meta = __( 'Are you sure you would like to flip the SSL flag to show SSL is enabled?  This will not execute any code to request a new SSL certificate.', 'simple-stack' );
		}

		$actions['ssl-status-meta-only-header'] = array(
			'name'           => '',
			/* Translators: %s is a fontawesome or similar icon. */
			'label'          => sstk_apply_change_icon( __( '%s Advanced - Flip SSL Status Flag', 'simple-stack' ) ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'Flip the SSL status flag (aka the SSL meta value). No code will be executed on the server to disable or enable SSL. Only the status shown on this dashboard will be flipped.', 'simple-stack' ),
			),
		);

		$actions['ssl-flip-meta-status'] = array(
			'label'          => '',
			'raw_attributes' => array(
				/* Translators: %s is a fontawesome or similar icon. */
				'std'                 => sstk_apply_change_icon( __( '%s Flip SSL Status Flag', 'simple-stack' ) ),
				'confirmation_prompt' => $confirmation_prompt_meta,
			),
			'type'           => 'button',
		);

		$actions[] = sstk_end_card( $this->get_tab_slug() ); // Close up prior card.

		/**
		 * Set Flag for Custom SSL
		 */

		$actions[]                = sstk_start_half_card( $this->get_tab_slug() ); // Start new card.
		$confirmation_prompt_meta = '';
		if ( true === $this->get_site_local_custom_ssl_status( $id ) ) {
			$confirmation_prompt_meta = __( 'Are you sure you would like to flip the SSL flag to show that custom SSL is disabled? This will not execute any code to disable SSL if it is actually enabled on the server.', 'simple-stack' );
		} else {
			$confirmation_prompt_meta = __( 'Are you sure you would like to flip the SSL flag to show that a custom SSL certificate is enabled?  This will not execute any code to request a new SSL certificate.', 'simple-stack' );
		}

		$actions['custom-ssl-status-meta-only-header'] = array(
			'name'           => '',
			/* Translators: %s is a fontawesome or similar icon. */
			'label'          => sstk_apply_change_icon( __( '%s Flip Custom SSL Status Flag', 'simple-stack' ) ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'Set or unset a flag that indicates that a custom SSL certificate is installed on this site. This will also flip the regular SSL flag!', 'simple-stack' ),
			),
		);

		$actions['custom-ssl-flip-meta-status'] = array(
			'label'          => '',
			'raw_attributes' => array(
				/* Translators: %s is a fontawesome or similar icon. */
				'std'                 => sstk_apply_change_icon( __( '%s Flip Custom SSL Status Flag', 'simple-stack' ) ),
				'confirmation_prompt' => $confirmation_prompt_meta,
			),
			'type'           => 'button',
		);

		if ( true === $this->get_site_local_custom_ssl_status( $id ) ) {
			$actions['custom-ssl-status-display'] = array(
				'name'           => '',
				'type'           => 'custom_html',
				'raw_attributes' => array(
					/* Translators: %s is a fontawesome or similar icon. */
					'std' => sstk_apply_checkmark_icon( __( '%s This meta is ON.', 'simple-stack' ) ),
				),
				'std'            => sstk_apply_checkmark_icon( __( '%s This meta is ON.', 'simple-stack' ) ),
			);
		}

		$actions[] = sstk_end_card( $this->get_tab_slug() ); // Close up prior card.

		return $actions;
	}

	/**
	 * Attempt to turn SSL on (regardless of the current SSL status)
	 *
	 * Can be called directly or by an action hook.
	 *
	 * Action hook: sstk_wordpress-app_do_toggle_ssl_status_on  (Optional).
	 *
	 * @param int    $id     The postID of the app cpt.
	 * @param string $action The action to be performed  - 'ssl-status' or 'ssl-http2-status'.  This is ignored in this function.
	 *
	 * @return string|WP_Error
	 */
	public function toggle_ssl_status_on_action( $id, $action ) {

		$result = $this->toggle_ssl_status( $id, 'enable' );

		if ( ! is_wp_error( $result ) ) {

			// Update metas.
			$this->set_ssl_status( $id, 'on' );

			$result = array( 'refresh' => 'yes' );
		}

		return $result;  // Will not matter in an action hook.

	}

	/**
	 * Helper function to set some meta values before and after toggling SSL.
	 *
	 * Can be called directly or by an action hook.
	 *
	 * Action hook: sstk_wordpress-app_do_toggle_ssl_status  (Optional).
	 *
	 * @param int    $id     The postID of the app cpt.
	 * @param string $action The action to be performed  - 'ssl-status' or 'ssl-http2-status'.
	 *
	 * @return string|WP_Error
	 */
	public function toggle_ssl_status_action( $id, $action ) {

		// What type of web server are we running?
		$webserver_type = $this->get_web_server_type( $id );

		$result = '';

		switch ( $action ) {
			case 'ssl-status':
				if ( true === $this->get_site_local_ssl_status( $id ) ) {
					$current_status = 'on';
				} else {
					$current_status = 'off';
				}
				$result = $this->toggle_ssl_status( $id, $current_status === 'on' ? 'disable' : 'enable' );
				if ( ! is_wp_error( $result ) ) {

					// What's the new status?
					$new_ssl_status = 'on' === $current_status ? 'off' : 'on';

					// Update metas.
					$this->set_ssl_status( $id, $new_ssl_status );

					$result = array( 'refresh' => 'yes' );
				}
				break;

			case 'ssl-http2-status':
				$current_status = $this->http2_status( $id );
				$result         = $this->toggle_ssl_status( $id, $current_status === 'on' ? 'disable_http2' : 'enable_http2' );
				if ( ! is_wp_error( $result ) ) {
					update_post_meta( $id, 'wpapp_ssl_http2_status', $current_status === 'on' ? 'off' : 'on' );
					$result = array( 'refresh' => 'yes' );
				}
				break;

		}

		return $result;  // Will not matter in an action hook.

	}

	/**
	 * Construct the command to toggle SSL status and then send it via SSH to the server.
	 *
	 * Action hook: sstk_wordpress-app_do_toggle_ssl_status
	 *
	 * @param int    $id     The postID of the app cpt.
	 * @param string $action The action to be performed 'enable' or 'disable' for ssl and 'enable_http2' or 'disable_http2' for http.
	 *
	 * @return boolean|WP_Error    success/failure
	 */
	public function toggle_ssl_status( $id, $action ) {
		$instance = $this->get_app_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'simple-stack' ), $action ) );
		}

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'manage_https.txt',
			array(
				'command' => "{$action}_https",
				'action'  => $action,
				'domain'  => get_post_meta( $id, 'wpapp_domain', true ),
				'email'   => get_post_meta(
					$id,
					'wpapp_email',
					true
				),
			)
		);

		do_action( 'sstk_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'manage_https.txt' );
		if ( ! $success ) {
			return new \WP_Error( sprintf( __( 'Unable to %1$s site: %2$s', 'simple-stack' ), $action, $result ) );
		}
		return $success;
	}

	/**
	 * Toggle just the SSL meta.
	 *
	 * @param int    $id     The postID of the app cpt.
	 * @param string $action The action to be performed  - 'ssl-flip-meta-status'.
	 *
	 * @return string|WP_Error
	 */
	public function toggle_ssl_meta_status( $id, $action ) {

		if ( true === $this->get_site_local_ssl_status( $id ) ) {
			$current_status = 'on';
		} else {
			$current_status = 'off';
		}

		// What's the new status?
		$new_ssl_status = 'on' === $current_status ? 'off' : 'on';

		// Update metas.
		$this->set_ssl_status( $id, $new_ssl_status );

		$result = array( 'refresh' => 'yes' );

		return $result;  // Will not matter in an action hook.

	}

	/**
	 * Toggle the CUSTOM SSL meta.
	 *
	 * @param int    $id     The postID of the app cpt.
	 * @param string $action The action to be performed  - 'custom-ssl-flip-meta-status'.
	 *
	 * @return string|WP_Error
	 */
	public function toggle_custom_ssl_meta_status( $id, $action ) {

		if ( true === $this->get_site_local_custom_ssl_status( $id ) ) {
			$current_status = 'on';
		} else {
			$current_status = 'off';
		}

		// What's the new status?
		$new_ssl_status = 'on' === $current_status ? 'off' : 'on';

		// Update both custom SSL meta and standard SSL meta.
		$this->set_site_local_custom_ssl_status( $id, $new_ssl_status );
		$this->set_ssl_status( $id, $new_ssl_status );

		$result = array( 'refresh' => 'yes' );

		return $result;  // Will not matter in an action hook.

	}


}

new SSTK_WORDPRESS_TABS_SSL();
