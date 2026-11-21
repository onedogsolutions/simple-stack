<?php
/**
 * Uninstall SimpleStack.
 *
 * Two gates, both opt-in:
 *
 * - Settings → Uninstall → "remove data on delete" (`wpcd_options['uninstall_on_delete']`)
 *   controls the settings themselves.
 * - The `wpcd_delete_data_on_plugin_removal` constant additionally drops roles, capabilities
 *   and the plugin's custom tables.
 *
 * Server, site and log posts are never deleted here. They describe machines that keep running
 * in the cloud after the plugin is gone, so removing the rows would destroy the only record of
 * what exists and how to reach it. Deactivating and reactivating restores management of the
 * same servers.
 *
 * @package wpcd
 */

// Exit if accessed directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! defined( 'wpcd_path' ) ) {
	define( 'wpcd_path', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'wpcd_url' ) ) {
	define( 'wpcd_url', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'wpcd_plugin' ) ) {
	define( 'wpcd_plugin', plugin_basename( __FILE__ ) );
}

$options = get_option( 'wpcd_options', array() );

if ( ! empty( $options['uninstall_on_delete'] ) ) {

	/*
	 * The two option arrays are deleted whole rather than filtered key by key. `wpcd_settings`
	 * holds the provider API keys and encrypted SSH keys, `wpcd_options` holds the early
	 * options; a filtered delete would silently leave behind every key this file does not
	 * happen to know about, and a stale credential in the database is still a credential.
	 */
	delete_option( 'wpcd_options' );
	delete_option( 'wpcd_settings' );

	/*
	 * Everything else the plugin writes outside those two arrays: the global SSH console
	 * toggle, the upgrade bookkeeping markers, the setup wizard answers, the cron cooldown
	 * timestamp, and the IDs of the front-end pages the plugin created.
	 */
	$standalone_options = array(
		'wpcd_wpapp_ssh_console_hide',
		'wpcd_last_upgrade_done',
		'wpcd_last_silent_auto_upgrade_done',
		'wpcd_last_version_upgrade',
		'wpcd_version',
		'wpcd_setup_wizard_selected_provider',
		'wpcd_plugin_setup',
		'wpcd_skip_wizard_setup',
		'wpcd_plugin_first_time_activated',
		'wpcd_crons_not_running_email_next_send_date',
		'wpcd_public_servers_list_page_id',
		'wpcd_public_apps_list_page_id',
		'wpcd_public_deploy_server_page_id',
	);

	foreach ( $standalone_options as $option ) {
		delete_option( $option );
	}

	/*
	 * The front-end pages those IDs pointed at are left in place: they are ordinary
	 * WordPress pages that an operator may have edited or linked to, so deleting them is a
	 * content decision rather than part of uninstalling a plugin.
	 */
}

// If defined in wp-config.php then remove the roles, capabilities and custom tables as well.
if ( defined( 'wpcd_delete_data_on_plugin_removal' ) && wpcd_delete_data_on_plugin_removal ) {
	require_once wpcd_path . 'includes/core/class-wpcd-roles-capabilities.php';
	require_once wpcd_path . 'includes/core/class-wpcd-posts-permission-type.php';
	require_once wpcd_path . 'includes/core/class-wpcd-sync.php';

	if ( is_multisite() ) {
		$blog_ids = get_sites( array( 'fields' => 'ids' ) );
		foreach ( $blog_ids as $blog_id ) {
			switch_to_blog( $blog_id );
			WPCD_ROLES_CAPABILITIES::wpcd_delete_roles_capabilities();
			WPCD_POSTS_PERMISSION_TYPE::wpcd_delete_table();
			WPCD_SYNC::wpcd_delete_restore_files_table();
			restore_current_blog();
		}
	} else {
		WPCD_ROLES_CAPABILITIES::wpcd_delete_roles_capabilities();
		WPCD_POSTS_PERMISSION_TYPE::wpcd_delete_table();
		WPCD_SYNC::wpcd_delete_restore_files_table();
	}
}
