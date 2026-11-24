<?php
/** Modified for Simple Stack by One Dog Solutions on 2026-11-23. */
/**
 * SSTK_ROLES_CAPABILITIES class for user roles capabilities.
 *
 * @package simple-stack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SSTK_ROLES_CAPABILITIES
 */
class SSTK_ROLES_CAPABILITIES {

	/**
	 * SSTK_ROLES_CAPABILITIES instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * SSTK_ROLES_CAPABILITIES constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->hooks(); // register hooks to do things...
	}

	/**
	 * To hook custom actions and filters
	 *
	 * @return void
	 */
	private function hooks() {
		// Action hook to create roles and capabilities when a new site is created.
		add_action( 'wp_initialize_site', array( $this, 'sstk_roles_capabilities_new_site' ), 10, 2 );

	}

	/**
	 * Fires on activation of plugin.
	 *
	 * @param  boolean $network_wide    True if plugin is activated network-wide.
	 *
	 * @return void
	 */
	public static function activate( $network_wide ) {
		if ( is_multisite() && $network_wide ) {
			// Get all blogs in the network.
			$blog_ids = get_sites( array( 'fields' => 'ids' ) );
			foreach ( $blog_ids as $blog_id ) {
				switch_to_blog( $blog_id );
				self::sstk_create_roles_capabilities();
				restore_current_blog();
			}
		} else {
			self::sstk_create_roles_capabilities();
		}
	}

	/**
	 * Creates new roles and adds custom capabilities to existing and new roles
	 *
	 * @return void
	 */
	public static function sstk_create_roles_capabilities() {

		// get author role and capabilities.
		$author      = get_role( 'author' );
		$author_caps = $author->capabilities;

		// 1. Simple Stack Admin Role.
		// list of custom capabilities for Simple Stack Admin role.
		$sstk_admin_caps = array(
			'sstk_provision_servers' => true,
			'sstk_manage_servers'    => true,
			'sstk_manage_apps'       => true,
			'sstk_manage_logs'       => true,
			'sstk_manage_settings'   => true,
			'sstk_manage_groups'     => true,
			'sstk_manage_teams'      => true,
			'sstk_manage_all'        => true,
		);

		$all_sstk_admin_caps = array_merge( $author_caps, $sstk_admin_caps );
		add_role( 'sstkadmin', __( 'Simple Stack Admin', 'simple-stack' ), $all_sstk_admin_caps );

		// 2. Simple Stack Manager Role.
		// list of custom capabilities for Simple Stack Manager role.
		$sstk_manager_caps = array(
			'sstk_provision_servers' => true,
			'sstk_manage_servers'    => true,
			'sstk_manage_apps'       => true,
			'sstk_manage_logs'       => true,
			'sstk_manage_settings'   => true,
			'sstk_manage_groups'     => true,
		);

		$all_sstk_manager_caps = array_merge( $author_caps, $sstk_manager_caps );
		add_role( 'sstkmanager', __( 'Simple Stack Manager', 'simple-stack' ), $all_sstk_manager_caps );

		// get subscriber role and capabilities.
		$subscriber      = get_role( 'subscriber' );
		$subscriber_caps = $subscriber->capabilities;

		// 3. Simple Stack System Admin Role.
		// list of custom capabilities for Simple Stack System Admin role.
		$sstk_sys_admin_caps = array(
			'sstk_manage_servers' => true,
			'sstk_manage_apps'    => true,
		);

		$all_sstk_sys_admin_caps = array_merge( $subscriber_caps, $sstk_sys_admin_caps );
		add_role( 'sstksysadmin', __( 'Simple Stack System Admin', 'simple-stack' ), $all_sstk_sys_admin_caps );

		// 4. Simple Stack App Manager Role.
		// list of custom capabilities for Simple Stack App Manager role.
		$sstk_app_manager_caps = array(
			'sstk_manage_apps' => true,
		);

		$all_sstk_app_manager_caps = array_merge( $subscriber_caps, $sstk_app_manager_caps );
		add_role( 'sstkappmanager', __( 'Simple Stack App Manager', 'simple-stack' ), $all_sstk_app_manager_caps );

		// 5. Administrator Role.
		$administrator = get_role( 'administrator' );

		// list of custom capabilities for administrator role.
		$admin_caps = array(
			'sstk_provision_servers' => true,
			'sstk_manage_servers'    => true,
			'sstk_manage_apps'       => true,
			'sstk_manage_logs'       => true,
			'sstk_manage_settings'   => true,
			'sstk_manage_groups'     => true,
			'sstk_manage_teams'      => true,
			'sstk_manage_all'        => true,
		);

		foreach ( $admin_caps as $key => $cap ) {
			$administrator->add_cap( $key );
		}

	}

	/**
	 * To add roles and capabilities on the new site creation
	 *
	 * Action hook: wp_initialize_site
	 *
	 * @param  object $new_site new site.
	 * @param  array  $args args.
	 * @return void
	 */
	public function sstk_roles_capabilities_new_site( $new_site, $args ) {
		$plugin_name = SSTK_PLUGIN;

		// To check the plugin is active network-wide.
		if ( is_plugin_active_for_network( $plugin_name ) ) {
			$blog_id = $new_site->blog_id;

			switch_to_blog( $blog_id );
			self::sstk_create_roles_capabilities();
			restore_current_blog();
		}
	}

	/**
	 * Removes custom roles and capabilities created by the plugin
	 * This function will be invoked at the time of uninstallation/deletion.
	 *
	 * @return void
	 */
	public static function sstk_delete_roles_capabilities() {
		$roles_to_remove = array(
			'sstkadmin',
			'sstkmanager',
			'sstksysadmin',
			'sstkappmanager',
		);

		// Remove the custom roles and their assignments from every user.
		foreach ( $roles_to_remove as $role ) {
			remove_role( $role );
		}

		$user_ids = get_users(
			array(
				'fields' => 'IDs',
				'number' => 0,
			)
		);

		foreach ( (array) $user_ids as $user_id ) {
			$user = get_user_by( 'id', $user_id );
			if ( ! $user ) {
				continue;
			}

			foreach ( $roles_to_remove as $role ) {
				$user->remove_role( $role );
			}

			foreach ( array_keys( $user->caps ) as $capability ) {
				if ( str_starts_with( $capability, 'sstk_' ) ) {
					$user->remove_cap( $capability );
				}
			}
		}

		// Remove every reserved capability from every remaining role, including custom roles.
		foreach ( array_keys( wp_roles()->roles ) as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}

			foreach ( array_keys( $role->capabilities ) as $capability ) {
				if ( str_starts_with( $capability, 'sstk_' ) ) {
					$role->remove_cap( $capability );
				}
			}
		}
	}
}
