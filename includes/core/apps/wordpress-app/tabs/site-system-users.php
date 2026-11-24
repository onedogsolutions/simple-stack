<?php
/** Modified for Simple Stack by One Dog Solutions on 2026-11-23. */
/**
 * Site system users.
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
 * Class SSTK_WORDPRESS_TABS_SITE_SYSTEM_USERS
 */
class SSTK_WORDPRESS_TABS_SITE_SYSTEM_USERS extends SSTK_WORDPRESS_TABS {

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
		return 'site-system-users';
	}

	/**
	 * Returns a string that is the name of a view TEAM permission required to view this tab.
	 */
	public function get_view_tab_team_permission_slug() {
		return 'view_wpapp_site_site_system_users_tab';
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
				'label' => __( 'System Users', 'simple-stack' ),
				'icon'  => 'fad fa-users-friends',
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

		/* Verify that the list of actions is valid for this tab. */
		$valid_actions = array( 'site-user-change-password', 'site-user-remove-password', 'site-user-remove-key', 'site-user-set-key' );
		if ( ! in_array( $action, $valid_actions, true ) ) {
			return $result;
		}

		/* Verify that the user is even allowed to view the server before proceeding to do anything else */
		$server_post = $this->get_server_by_app_id( $id );
		if ( ! $this->sstk_user_can_view_wp_server( $server_post->ID ) ) {
			return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'simple-stack' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
		}

		/* Verify that the user is even allowed to view the app before proceeding to do anything else */
		if ( ! $this->sstk_user_can_view_wp_app( $id ) ) {
			return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'simple-stack' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
		}

		/* Verify that the tab is enabled */
		if ( ( ! defined( 'SSTK_SHOW_SITE_USERS_TAB' ) ) || ( defined( 'SSTK_SHOW_SITE_USERS_TAB' ) && ! SSTK_SHOW_SITE_USERS_TAB ) ) {
			return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'simple-stack' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
		}

		/**
		 * Now, verify that the user can perform actions on this screen, assuming that they can view the server.
		 * Note: The valid actions array that is usually defined in this block has been defined earlier above.
		 */
		if ( in_array( $action, $valid_actions, true ) ) {
			if ( ! $this->get_tab_security( $id ) ) {
				return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'simple-stack' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
			}
		}

		if ( $this->get_tab_security( $id ) ) {
			switch ( $action ) {
				case 'site-user-change-password':
				case 'site-user-remove-password':
				case 'site-user-remove-key':
				case 'site-user-set-key':
					$result = $this->do_site_user_actions( $action, $id );
					// most actions need to refresh the page so that new data can be loaded or so that the data entered into data entry fields cleared out.
					if ( ! in_array( $action, array(), true ) && ! is_wp_error( $result ) ) {
						$result = array( 'refresh' => 'yes' );
					}
					break;
			}
		}
		return $result;
	}

	/**
	 * Gets the fields to be shown in the SITE USERS tab.
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
			$this->get_site_users_fields( $fields, $id )
		);

	}

	/**
	 * Gets the fields to be shown in the SITE USERS area of the tab.
	 *
	 * @param array $fields fields.
	 * @param int   $id id.
	 */
	public function get_site_users_fields( array $fields, $id ) {

		if ( ! $id ) {
			// id not found!
			return $fields;
		}

		// Bail if site is not enabled.
		if ( ! $this->is_site_enabled( $id ) ) {
			return array_merge( $fields, $this->get_disabled_header_field( 'site-system-users' ) );
		}

		/* Array variable to hold our field definitions */
		$fields = array();

		/* Only show fields to users who can view servers */
		$server_post = $this->get_server_by_app_id( $id );
		if ( ! $this->sstk_user_can_view_wp_server( $server_post->ID ) ) {

			$fields[] = array(
				'name' => __( 'Manage The Site User', 'simple-stack' ),
				'desc' => __( 'You are not allowed to manage site users - only admins with full access to the server hosting this site can manage these users', 'simple-stack' ),
				'tab'  => 'site-system-users',
				'type' => 'heading',
			);

			return $fields;

		}

		// Get password from database...
		$encrypted_pass = get_post_meta( $id, 'wpapp_site_user_pass', true );
		if ( empty( $encrypted_pass ) ) {
			$pass = '';
		} else {
			$pass = self::decrypt( $encrypted_pass );
		}

		// manage site user heading.
		$desc  = __( 'Site users are special users under which the website is run.  They normally have no password, are run in a chroot jail and cannot be used to log into the server.  The options in this section allow you to override this default behavior. Once a password or publickey is assigned, these users can log in with both sftp and ssh.', 'simple-stack' );
		$desc .= '<br />' . sprintf( __( 'Your site user username is %s', 'simple-stack' ), '<em><b>' . get_post_meta( $id, 'wpapp_domain', true ) . '</b></em>' );
		$desc .= '<br />' . __( 'Warning: Site users with passwords or public keys will have been released from their jails and have privileged access to your server resources so please be VERY careful if you assign a password or publickey to this user!', 'simple-stack' );

		/*
		$fields[] = array(
				'name' => '',
				'tab'	=> 'site-system-users',
				'type' => 'divider',
		);
		*/

		$fields[] = array(
			'name' => __( 'Manage The Site User', 'simple-stack' ),
			'desc' => $desc,
			'tab'  => 'site-system-users',
			'type' => 'heading',
		);

		// change site user password.
		$fields[] = array(
			'name'       => __( 'Password', 'simple-stack' ),
			'id'         => 'sstk_app_site_user_pass',
			'tab'        => 'site-system-users',
			'type'       => 'password',
			'save_field' => false,
			'std'        => $pass,
			'attributes' => array(
				// the key of the field (the key goes in the request).
				'data-sstk-name' => 'site_user_pass',
			),
			'class'      => 'sstk_app_pass_toggle',
		);
		$fields[] = array(
			'id'         => 'sstk_app_action_site_user_changepass_button',
			'tab'        => 'site-system-users',
			'type'       => 'button',
			'std'        => __( 'Change Password', 'simple-stack' ),
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-sstk-action' => 'site-user-change-password',
				// the id.
				'data-sstk-id'     => $id,
				// fields that contribute data for this action.
				'data-sstk-fields' => json_encode( array( '#sstk_app_site_user_pass' ) ),
			),
			'class'      => 'sstk_app_action',
			'save_field' => false,
		);

		// remove site user password.
		if ( ! empty( $pass ) ) {
			$fields[] = array(
				'id'         => 'sstk_app_action_site_user_removepass_button',
				'tab'        => 'site-system-users',
				'type'       => 'button',
				'std'        => __( 'Remove Password', 'simple-stack' ),
				'attributes' => array(
					// the _action that will be called in ajax.
					'data-sstk-action' => 'site-user-remove-password',
					// the id.
					'data-sstk-id'     => $id,
				),
				'class'      => 'sstk_app_action',
				'save_field' => false,
			);
		}

		// set public key for site user.
		$fields[] = array(
			'name'             => __( 'Public Key File', 'simple-stack' ),
			'id'               => 'sstk_app_site_user_public_key_file',
			'tab'              => 'site-system-users',
			'type'             => 'file',
			'max_file_uploads' => 1,
			'placeholder'      => __( 'Select a key', 'simple-stack' ),
			'save_field'       => false,
			'class'            => 'sstk_app_setkey sstk_app_site_user_setkey',
			'attributes'       => array(
				// the key of the field (the key goes in the request).
				'data-sstk-name' => 'site_user_public_key_file',
			),
		);
		$fields[] = array(
			'id'         => 'sstk_app_action_site_user_setkey_button',
			'tab'        => 'site-system-users',
			'type'       => 'button',
			'std'        => __( 'Set Public Key', 'simple-stack' ),
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-sstk-action' => 'site-user-set-key',
				// the id.
				'data-sstk-id'     => $id,
				// fields that contribute data for this action.
				'data-sstk-fields' => json_encode( array( '#sstk_app_site_user_public_key_file' ) ),
			),
			'class'      => 'sstk_app_action',
			'save_field' => false,
		);

		// Remove public key for site user...
		if ( ! empty( get_post_meta( $id, 'wpapp_site_user_public_key_flag', true ) ) ) {
			$fields[] = array(
				'id'         => 'sstk_app_action_site_user_removekey_button',
				'tab'        => 'site-system-users',
				'type'       => 'button',
				'std'        => __( 'Remove Public Key', 'simple-stack' ),
				'attributes' => array(
					// the _action that will be called in ajax.
					'data-sstk-action' => 'site-user-remove-key',
					// the id.
					'data-sstk-id'     => $id,
				),
				'class'      => 'sstk_app_action',
				'save_field' => false,
			);
		}

		return $fields;

	}

	/**
	 * Performs the SITE USER action.
	 *
	 * @param string $action action.
	 * @param int    $id id.
	 */
	private function do_site_user_actions( $action, $id ) {
		$instance = $this->get_app_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'simple-stack' ), $action ) );
		}

		/* Grab the arguments sent from the front-end JS */
		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );

		// The user is the application domain.
		$user         = get_post_meta( $id, 'wpapp_domain', true );
		$args['user'] = $user;

		// Do some manipulation of the data depending on the action.
		switch ( $action ) {
			case 'site-user-change-password':
				// Grab the original password.
				if ( isset( $args['site_user_pass'] ) ) {
					$original_pass = $args['site_user_pass'];
				} else {
					$original_pass = '';
				}

				// add it back to the array with the proper key...
				$args['pass'] = $original_pass;

				break;
		}

		// Now lets make sure we escape all the arguments so it's safe for the command line.
		$esc_args = array_map(
			function( $item ) {
				return escapeshellarg( $item );
			},
			$args
		);

		$run_cmd = '';

		switch ( $action ) {
			case 'site-user-change-password':
				$run_cmd = $this->turn_script_into_command(
					$instance,
					'manage_site_users.txt',
					array_merge(
						$esc_args,
						array(
							'action' => 'site_user_change_password',
							'domain' => get_post_meta(
								$id,
								'wpapp_domain',
								true
							),
						)
					)
				);
				break;
			case 'site-user-remove-password':
				$run_cmd = $this->turn_script_into_command(
					$instance,
					'manage_site_users.txt',
					array_merge(
						$esc_args,
						array(
							'action' => 'site_user_remove_password',
							'domain' => get_post_meta(
								$id,
								'wpapp_domain',
								true
							),
						)
					)
				);
				break;
			case 'site-user-remove-key':
				$run_cmd = $this->turn_script_into_command(
					$instance,
					'manage_site_users.txt',
					array_merge(
						$esc_args,
						array(
							'action' => 'site_user_remove_key',
							'domain' => get_post_meta(
								$id,
								'wpapp_domain',
								true
							),
						)
					)
				);
				break;
			case 'site-user-set-key':
				// first upload the file to /tmp/ and then specify its path in the script.
				$tmp_file = sanitize_file_name( $_FILES['file']['name'] );
				$tmp_file = sprintf( '/tmp/%s%s', time(), $tmp_file );
				$run_cmd  = $this->execute_ssh(
					'upload',
					$instance,
					array(
						'remote' => $tmp_file,
						/* No sanitization needed for this temporary file name since it's automatically generated by php. Usually in the form of /tmp/<randomstring> */
						'local'  => $_FILES['file']['tmp_name'],
					)
				);
				if ( ! is_wp_error( $run_cmd ) ) {
					$run_cmd = $this->turn_script_into_command(
						$instance,
						'manage_site_users.txt',
						array_merge(
							$esc_args,
							array(
								'action'     => 'site_user_set_key',
								'domain'     => get_post_meta( $id, 'wpapp_domain', true ),
								'public_key' => $tmp_file,
							)
						)
					);
				}
				break;
		}

		do_action( 'sstk_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		$result = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );

		$success = $this->is_ssh_successful( $result, 'manage_site_users.txt', $action );
		if ( ! $success ) {
			return new \WP_Error( sprintf( __( 'Unable to %1$s site: %2$s', 'simple-stack' ), $action, $result ) );
		}

		switch ( $action ) {
			case 'site-user-change-password':
				update_post_meta( $id, 'wpapp_site_user_pass', self::encrypt( $original_pass ) );
				break;
			case 'site-user-remove-password':
				update_post_meta( $id, 'wpapp_site_user_pass', '' );
				break;
			case 'site-user-remove-key':
				update_post_meta( $id, 'wpapp_site_user_public_key_flag', '' );
				break;
			case 'site-user-set-key':
				update_post_meta( $id, 'wpapp_site_user_public_key_flag', '1' );
				break;
		}

		return $success;
	}


}

new SSTK_WORDPRESS_TABS_SITE_SYSTEM_USERS();
