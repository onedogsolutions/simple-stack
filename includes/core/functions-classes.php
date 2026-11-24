<?php
/** Modified for Simple Stack by One Dog Solutions on 2026-11-23. */
/**
 * This file contains functions that spin up classes or declare globally accessible functions.
 *
 * @package simple-stack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Function for calling SSTK methods and variables
 *
 * @return SSTK_CORE;
 */
function SSTK() {
	return $GLOBALS['SSTK_CORE'];  // No checking to see if it exists. If it doesn't we want to error out hard.
}

/**
 * Create a class var for SSTK_Server and
 * add it to the SSTK array of classes for management
 *
 * The SSTK_Server class is used to handle all things
 * related to just server operations such as creation,
 * reboot, delete and so on.
 */
add_action( 'init', 'sstk_init_sstk_server', -10, 1 );
function sstk_init_sstk_server() {
	if ( function_exists( 'SSTK' ) ) {
		if ( empty( SSTK()->classes['SSTK_SERVER'] ) ) {
			SSTK()->classes['SSTK_SERVER'] = new SSTK_Server();
		}
	}
}


/**
 * Create a class var for SSTK_Settings and
 * add it to the SSTK array of classes for management
 */
add_action( 'init', 'sstk_init_sstk_settings', -10, 1 );
function sstk_init_sstk_settings() {
	if ( function_exists( 'SSTK' ) ) {
		if ( empty( SSTK()->classes['SSTK_SETTINGS'] ) ) {
			SSTK()->classes['SSTK_SETTINGS'] = new SSTK_Settings();
		}
	}
}


/**
 * Maybe setup classes for custom tables for DNS and PROVIDERS.
 */
if ( true === sstk_is_custom_dns_provider_tables_enabled() ) {
	require_once 'custom-table/api/class-sstk-custom-table-api.php';
	require_once 'custom-table/api/class-sstk-ct-provider-api.php';
	require_once 'custom-table/api/class-sstk-ct-dns-provider-api.php';
	require_once 'custom-table/api/class-sstk-ct-dns-zone-api.php';
	require_once 'custom-table/api/class-sstk-ct-dns-zone-record-api.php';

	require_once 'custom-table/class-sstk-ct-list-table.php';
	require_once 'custom-table/class-sstk-ct-childs-list-table.php';
	require_once 'custom-table/class-sstk-custom-table.php';
	require_once 'custom-table/class-sstk-provider.php';
	require_once 'custom-table/class-sstk-dns-provider.php';
	require_once 'custom-table/class-sstk-dns-zone.php';
	require_once 'custom-table/class-sstk-dns-zone-record.php';


	add_action( 'init', 'sstk_init_sstk_custom_table_classes', -10, 1 );


	/**
	 * Init custom table classes
	 */
	function sstk_init_sstk_custom_table_classes() {

		if ( ! function_exists( 'rwmb_request' ) ) {
			return;
		}

		if ( function_exists( 'SSTK' ) ) {
			if ( empty( SSTK()->classes['SSTK_PROVIDER_SETTINGS'] ) ) {
				SSTK()->classes['SSTK_PROVIDER_SETTINGS'] = SSTK_MB_Custom_Table::get( 'provider' );
			}

			if ( empty( SSTK()->classes['SSTK_DNS_PROVIDER_SETTINGS'] ) ) {
				SSTK()->classes['SSTK_DNS_PROVIDER_SETTINGS'] = SSTK_MB_Custom_Table::get( 'dns_provider' );
			}

			if ( empty( SSTK()->classes['SSTK_DNS_Zone'] ) ) {
				SSTK()->classes['SSTK_DNS_Zone'] = SSTK_MB_Custom_Table::get( 'dns_zone' );
			}

			if ( empty( SSTK()->classes['SSTK_DNS_Zone_Record'] ) ) {
				SSTK()->classes['SSTK_DNS_Zone_Record'] = SSTK_MB_Custom_Table::get( 'dns_zone_record' );
			}
		}
	}
}


/**
 * Create a class var for SSTK_Custom_Fields and
 * add it to the SSTK array of classes for management
 */
add_action( 'init', 'sstk_init_sstk_custom_fields', -10, 1 );
function sstk_init_sstk_custom_fields() {
	if ( function_exists( 'SSTK' ) ) {
		if ( empty( SSTK()->classes['SSTK_CUSTOM_FIELDS'] ) ) {
			SSTK()->classes['SSTK_CUSTOM_FIELDS'] = new SSTK_Custom_Fields();
		}
	}
}

/**
 * Function for calling SSTK_CUSTOM_FIELDS methods and variables
 *
 * @return SSTK_CUSTOM_FIELDS;
 */
function SSTK_CUSTOM_FIELDS() {
	return SSTK()->classes['SSTK_CUSTOM_FIELDS'];
}

/**
 * Create a class var for SSTK_DNS and
 * add it to the SSTK array of classes for management
 */
add_action( 'init', 'sstk_init_sstk_dns', -10, 1 );
function sstk_init_sstk_dns() {
	if ( function_exists( 'SSTK' ) ) {
		if ( empty( SSTK()->classes['SSTK_DNS'] ) ) {
			SSTK()->classes['SSTK_DNS'] = new SSTK_DNS();
		}
	}
}

/**
 * Function for calling SSTK_DNS methods and variables
 *
 * @return SSTK_DNS;
 */
function SSTK_DNS() {
	return SSTK()->classes['SSTK_DNS'];
}

/**
 * Create a class var for SSTK_WORDPRESS_APP and
 * add it to the SSTK array of classes for management
 *
 * This class handles all items related to the management
 * of the WORDPRESS_APP app.
 *
 * @TODO: This really shouldn't happen here.  Instead
 * we need a process to have apps register their main
 * classes with SSTK and go from there.
 */
add_action( 'init', 'sstk_init_wordpress_app', -10, 1 );
function sstk_init_wordpress_app() {
	if ( function_exists( 'SSTK' ) ) {
		if ( class_exists( 'SSTK_WORDPRESS_APP' ) ) {
			if ( empty( SSTK()->classes['SSTK_WORDPRESS_APP'] ) ) {
				SSTK()->classes['SSTK_WORDPRESS_APP'] = new SSTK_WORDPRESS_APP();
			}
		}
	}
}

/**
 * Function for calling SSTK_WORDPRESS_APP methods and variables
 *
 * @return SSTK_WORDPRESS_APP;
 */
function SSTK_WORDPRESS_APP() {
	return SSTK()->classes['SSTK_WORDPRESS_APP'];
}

/**
 * Function for calling SSTK_Settings methods and variables
 *
 * @return SSTK_Settings;
 */
function SSTK_SETTINGS() {
	return SSTK()->classes['SSTK_SETTINGS'];
}

/**
 * Function for calling SSTK_Server methods and variables
 *
 * @return SSTK_Server;
 */
function SSTK_SERVER() {
	return SSTK()->classes['SSTK_SERVER'];
}

/**
 * Function for calling sstk_posts_app_server methods and variables.
 * sstk_posts_app_server is the class that manages the
 * sstk_app_server CPT
 *
 * @return SSTK_POSTS_APP_SERVER;
 */
function SSTK_POSTS_APP_SERVER() {
	return SSTK()->classes['sstk_posts_app_server'];
}

/**
 * Function for calling sstk_posts_cloud_provider methods and variables.
 * sstk_posts_cloud_provider is the class that manages the
 * sstk_cloud_provider CPT
 *
 * @return SSTK_POSTS_CLOUD_PROVIDER;
 */
function SSTK_POSTS_CLOUD_PROVIDER() {
	return SSTK()->classes['sstk_posts_cloud_provider'];
}

/**
 * Function for calling sstk_posts_app methods and variables.
 * sstk_posts_app is the class that manages the
 * sstk_app CPT.
 *
 * @return SSTK_POSTS_APP;
 */
function SSTK_POSTS_APP() {
	return SSTK()->classes['sstk_posts_app'];
}

/**
 * Function for calling sstk_notify_log methods and variables.
 * sstk_notify_log is the class that manages the
 * sstk_notify_log CPT.
 *
 * @return SSTK_NOTIFY_LOG;
 */
function SSTK_POSTS_NOTIFY_LOG() {
	return SSTK()->classes['sstk_posts_notify_log'];
}


/**
 * Function for calling sstk_notify_user methods and variables.
 * sstk_notify_user is the class that manages the
 * sstk_notify_user CPT.
 *
 * @return SSTK_NOTIFY_USER;
 */
function SSTK_POSTS_NOTIFY_USER() {
	return SSTK()->classes['sstk_posts_notify_user'];
}

/**
 * Function for calling SSTK_NOTIFY_SENT methods and variables.
 * sstk_notify_sent is the class that manages the
 * sstk_notify_sent CPT.
 *
 * @return SSTK_NOTIFY_SENT;
 */
function SSTK_POSTS_NOTIFY_SENT() {
	return SSTK()->classes['sstk_posts_notify_sent'];
}

/**
 * Function for calling sstk_ssh_log methods and variables.
 * sstk_ssh_log is the class that manages the
 * sstk_ssh_log CPT.
 *
 * @return SSTK_SSH_LOG;
 */
function SSTK_POSTS_SSH_LOG() {
	return SSTK()->classes['sstk_posts_ssh_log'];
}

/**
 * Function for calling sstk_command_log methods and variables.
 * sstk_command_log is the class that manages the
 * sstk_command_log CPT.
 *
 * @return SSTK_COMMAND_LOG;
 */
function SSTK_POSTS_COMMAND_LOG() {
	return SSTK()->classes['sstk_posts_command_log'];
}

/**
 * Function for calling sstk_pending_tasks_log methods and variables.
 * sstk_pending_tasks_log is the class that manages the
 * sstk_pending_tasks_log CPT.
 *
 * @return SSTK_PENDING_TASKS_LOG;
 */
function SSTK_POSTS_PENDING_TASKS_LOG() {
	return SSTK()->classes['sstk_posts_pending_tasks_log'];
}

/**
 * Function for calling sstk_error_log methods and variables.
 * sstk_error_log is the class that manages the
 * sstk_error_log CPT.
 *
 * @return SSTK_ERROR_LOG;
 */
function SSTK_POSTS_ERROR_LOG() {
	return SSTK()->classes['sstk_posts_error_log'];
}

/**
 * Function for calling sstk_posts_team methods and variables.
 * sstk_posts_team is the class that manages the
 * sstk_team CPT.
 *
 * @return SSTK_POSTS_TEAM;
 */
function SSTK_POSTS_TEAM() {
	return SSTK()->classes['sstk_posts_team'];
}

/**
 * Function for calling sstk_posts_permission_type methods and variables.
 * sstk_posts_permission_type is the class that manages the
 * sstk_permission_type CPT.
 *
 * @return SSTK_POSTS_PERMISSION_TYPE;
 */
function SSTK_POSTS_PERMISSION_TYPE() {
	return SSTK()->classes['sstk_posts_permission_type'];
}

/**
 * Create a class var for SSTK_APP_EXPIRATION and
 * add it to the SSTK array of classes for management
 */
add_action( 'init', 'sstk_init_app_expiration', -10, 1 );
function sstk_init_app_expiration() {
	if ( function_exists( 'SSTK' ) ) {
		if ( empty( SSTK()->classes['sstk_app_expiration'] ) ) {
			SSTK()->classes['sstk_app_expiration'] = new SSTK_App_Expiration();
		}
	}
}

/**
 * Function for calling sstk_app_expiration methods and variables.
 * sstk_app_expiration is the class that manages the app expriation methods.
 *
 * @return SSTK_App_Expiration;
 */
function SSTK_APP_EXPIRATION() {
	return SSTK()->classes['sstk_app_expiration'];
}

/**
 * Create a class var for SSTK_SERVER_STATISTICS and
 * add it to the SSTK array of classes for management
 */
add_action( 'init', 'sstk_init_sstk_server_statistics', -10, 1 );
function sstk_init_sstk_server_statistics() {
	if ( function_exists( 'SSTK' ) ) {
		if ( empty( SSTK()->classes['sstk_server_statistics'] ) ) {
			SSTK()->classes['sstk_server_statistics'] = new SSTK_SERVER_STATISTICS();
		}
	}
}

/**
 * Function for calling sstk_server_statistics methods and variables.
 * sstk_server_statistics is the class that manages the server statistic methods.
 *
 * @return SSTK_SERVER_STATISTICS;
 */
function SSTK_SERVER_STATISTICS() {
	return SSTK()->classes['sstk_server_statistics'];
}

/**
 * Create a class var for SSTK_EMAIL_NOTIFICATIONS and
 * add it to the SSTK array of classes for management
 */
add_action( 'init', 'sstk_init_sstk_email_notifications', -10, 1 );
function sstk_init_sstk_email_notifications() {
	if ( sstk_email_notifications_allowed() ) {
		if ( function_exists( 'SSTK' ) ) {
			if ( empty( SSTK()->classes['sstk_email_notifications'] ) ) {
				SSTK()->classes['sstk_email_notifications'] = new SSTK_EMAIL_NOTIFICATIONS();
			}
		}
	}
}

/**
 * Function for calling SSTK_EMAIL_NOTIFICATIONS methods and variables.
 * sstk_email_notifications is the class that manages the manual email notification.
 *
 * @return SSTK_EMAIL_NOTIFICATIONS;
 */
function SSTK_EMAIL_NOTIFICATIONS() {
	return SSTK()->classes['sstk_email_notifications'];
}

/**
 * Create a class var for SSTK_WORDPRESS_APP_PUBLIC and
 * add it to the SSTK array of classes for management
 *
 * The SSTK_WORDPRESS_APP_PUBLIC class is used to handle all things
 * related to just front-end views/actions for servers and WordPress apps.
 */
add_action( 'init', 'sstk_init_wordpress_app_public', -10, 1 );
function sstk_init_wordpress_app_public() {
	if ( function_exists( 'SSTK' ) ) {
		if ( class_exists( 'SSTK_WORDPRESS_APP_PUBLIC' ) ) {
			if ( empty( SSTK()->classes['SSTK_WORDPRESS_APP_PUBLIC'] ) ) {
				SSTK()->classes['SSTK_WORDPRESS_APP_PUBLIC'] = new SSTK_WORDPRESS_APP_PUBLIC();
			}
		}
	}
}

/**
 * Function for calling SSTK_WORDPRESS_APP_PUBLIC methods and variables
 *
 * @return SSTK_WORDPRESS_APP_PUBLIC;
 */
function SSTK_WORDPRESS_APP_PUBLIC() {
	return SSTK()->classes['SSTK_WORDPRESS_APP_PUBLIC'];
}

