<?php
/** Modified for Simple Stack by One Dog Solutions on 2026-11-23. */
/**
 * WordPress App SSTK_WORDPRESS_APP.
 *
 * @package simple-stack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SSTK_WORDPRESS_APP
 */
class SSTK_WORDPRESS_APP extends SSTK_APP {

	/* Include traits */
	use sstk_wpapp_after_prepare_server;
	use sstk_wpapp_tabs_security;
	use sstk_wpapp_commands_and_logs;
	use sstk_wpapp_script_handlers;
	use sstk_wpapp_push_commands;
	use sstk_wpapp_admin_column_data;
	use sstk_wpapp_backup_functions;
	use sstk_wpapp_upgrade_functions;
	use sstk_wpapp_unused_functions;
	use sstk_wpapp_multi_tenant_app;

	/**
	 * Holds a reference to this class
	 *
	 * @var $instance instance.
	 */
	private static $instance;

	/**
	 * Holds singletons of the REST API controllers
	 *
	 * @var array
	 */
	protected array $rest_controllers = array();


	/**
	 * Static function that can initialize the class
	 * and return an instance of itself.
	 *
	 * @TODO: This just seems to duplicate the constructor
	 * and is probably only needed when called by
	 * SPMM()->set_class()
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * SSTK_WORDPRESS_APP constructor.
	 */
	public function __construct() {

		parent::__construct();
		// Set app name.
		$this->set_app_name( 'wordpress-app' );
		$this->set_app_description( 'WordPress' );

		// Register an app id for this app with SSTK...
		SSTK()->set_app_id( array( $this->get_app_name() => $this->get_app_description() ) );

		// Set folder where scripts are located.
		$this->set_scripts_folder( dirname( __FILE__ ) . '/scripts/' );
		$this->set_scripts_folder_relative( "includes/core/apps/{$this->get_app_name()}/scripts/" );

		add_action( 'init', array( &$this, 'init' ), 1 );

		// Global for backwards compatibility.
		$GLOBALS[ "sstk_app_{$this->get_app_name()}" ] = $this;

	}

	/**
	 * Init Function.
	 */
	public function init() {

		// setup needed objects.
		$this->settings();
		$this->ssh();

		// setup WordPress hooks.
		$this->hooks();

	}

	/**
	 * Hook into WordPress and other plugins as needed.
	 */
	private function hooks() {
		$this->include_tabs();
		$this->include_tabs_server();

		// Enable the REST API if the settings allow for it.
		if ( true === (bool) sstk_get_early_option( 'wordpress_app_rest_api_enable' ) ) {
			$this->include_rest_api();
		}

		// Make sure WordPress loads up our css and js scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'wpapp_enqueue_scripts' ), 10, 1 );

		// Actions send from the front-end when installing servers and sites.
		add_action( "sstk_server_{$this->get_app_name()}_action", array( &$this, 'do_instance_action' ), 10, 3 );
		add_action( "sstk_app_{$this->get_app_name()}_action", array( &$this, 'do_app_action' ), 10, 3 );

		add_filter( "sstk_command_{$this->get_app_name()}_logs_done", array( &$this, 'get_logs_done' ), 10, 4 );
		add_filter( "sstk_command_{$this->get_app_name()}_logs_intermed", array( &$this, 'get_logs_intermed' ), 10, 4 );
		add_action( "sstk_command_{$this->get_app_name()}_completed", array( &$this, 'command_completed' ), 10, 2 );
		add_filter( 'sstk_server_script_args', array( $this, 'add_script_args_server' ), 10, 2 );
		add_filter( 'sstk_app_script_args', array( $this, 'add_script_args_app' ), 10, 2 );
		add_filter( 'sstk_actions', array( $this, 'add_post_actions' ), 10, 2 );
		add_filter( "sstk_script_placeholders_{$this->get_app_name()}", array( $this, 'script_placeholders' ), 10, 6 );
		add_filter( 'sstk_app_server_admin_list_local_status_column', array( &$this, 'app_server_admin_list_local_status_column' ), 10, 2 );  // Show the server status.

		// Push commands and callbacks from servers.
		add_action( "sstk_{$this->get_app_name()}_command_server_status_completed", array( &$this, 'push_command_server_status_completed' ), 10, 4 );  // When a server sends us it's daily status report, part 1 - see bash script #24.
		add_action( "sstk_{$this->get_app_name()}_command_sites_status_completed", array( &$this, 'push_command_sites_status_completed' ), 10, 4 );  // When a server sends us it's daily status report, part 2 - see bash script #24.
		add_action( "sstk_{$this->get_app_name()}_command_aptget_status_completed", array( &$this, 'push_command_aptget_status_completed' ), 10, 4 );  // When a server sends us information that apt-get is running - see bash script #24.
		add_action( "sstk_{$this->get_app_name()}_command_posttypes_status_completed", array( &$this, 'push_command_posttypes_status_completed' ), 10, 6 );  // When a server sends us information about the post types on a site - see bash script #24.
		add_action( "sstk_{$this->get_app_name()}_command_maldet_scan_completed", array( &$this, 'push_command_maldet_scan_completed' ), 10, 4 );  // When a server sends us a report of maldet scan results - see bash script #26.
		add_action( "sstk_{$this->get_app_name()}_command_server_restart_completed", array( &$this, 'push_command_server_restart_completed' ), 10, 4 );  // When a server sends us a report of restart or shutdown.
		add_action( "sstk_{$this->get_app_name()}_command_monit_log_completed", array( &$this, 'push_command_monit_log_completed' ), 10, 4 );  // When a server sends us a monit alert or report.
		add_action( "sstk_{$this->get_app_name()}_command_start_domain_backup_completed", array( &$this, 'push_command_domain_backup_v1_started' ), 10, 4 );  // When a server sends us a notification telling us a scheduled backup was started for a domain.
		add_action( "sstk_{$this->get_app_name()}_command_end_domain_backup_completed", array( &$this, 'push_command_domain_backup_v1_completed' ), 10, 4 );  // When a server sends us a notification telling us a scheduled backup was completed for a domain.
		add_action( "sstk_{$this->get_app_name()}_command_server_config_backup_completed", array( &$this, 'push_command_server_config_backup' ), 10, 4 );  // When a server sends us a notification telling us a backup of the server configuration has started or ended.
		add_action( "sstk_{$this->get_app_name()}_command_test_rest_api_completed", array( &$this, 'push_command_test_rest_api_completed' ), 10, 4 );  // When a server sends us a test notification (initiated from the TOOLS tab on a server screen).

		// Push commands and callbacks from sites.
		add_action( "sstk_{$this->get_app_name()}_command_schedule_site_sync_completed", array( &$this, 'push_command_schedule_site_sync' ), 10, 4 );  // When a scheduled site sync has started or ended.

		// After server prepare action hooks.
		$this->sstk_after_server_prepare_action_hooks();

		// When we're querying to find out the status of a server.
		add_filter( 'sstk_is_server_available_for_commands', array( &$this, 'sstk_is_server_available_for_commands' ), 10, 2 );

		// When an app cleanup script is being run.
		add_action( 'sstk_cleanup_app_after', array( $this, 'sstk_cleanup_app_after' ), 10, 1 );

		// When a server cleanup script is being run.
		add_action( 'sstk_cleanup_server_after', array( $this, 'sstk_cleanup_server_after' ), 10, 1 );

		// When WP has been installed, add temp domain to DNS if configured as well as perform other actions after the site is successfully installed.
		add_action( 'sstk_command_wordpress-app_completed_after_cleanup', array( $this, 'sstk_wpapp_install_complete' ), 10, 4 );

		// Ajax Hooks.
		add_action( "wp_ajax_sstk_{$this->get_app_name()}", array( &$this, 'ajax_server' ) );       // For ajax calls dealing with servers in wp-admin.
		add_action( "wp_ajax_sstk_app_{$this->get_app_name()}", array( &$this, 'ajax_app' ) );      // for ajax calls dealing with apps in wp-admin.

		// Add welcome message to the settings screen.
		add_filter( 'sstk_general_settings_after_welcome_message', array( $this, 'welcome_message_settings' ), 10, 1 );

		// Add some additional instructions to the "no application servers found" message.
		add_filter( 'sstk_no_app_servers_found_msg', array( $this, 'no_app_servers_found_msg' ), 10, 1 );

		// Add a state called "WordPress" to the app when its shown on the app list.
		add_filter( 'display_post_states', array( $this, 'display_post_states' ), 20, 2 );

		// Background actions for SERVER.
		add_action( 'sstk_wordpress_deferred_actions_for_server', array( $this, 'do_deferred_actions_for_server' ), 10 );

		// Background actions for APPS.
		add_action( 'sstk_wordpress_deferred_actions_for_apps', array( $this, 'do_deferred_actions_for_app' ), 10 );

		// Delete temp log files.
		add_action( 'sstk_wordpress_file_watcher', array( $this, 'file_watcher_delete_temp_files' ) );

		/*********************************************
		* Hooks and filters for screens in wp-admin
		*/

		// Filter hook to setup additional search fields specific to the wordpress-app.
		add_filter( 'sstk_app_search_fields', array( $this, 'sstk_app_search_fields' ), 10, 1 );

		// Filter hook to add new columns to the APP list.
		add_filter( 'manage_sstk_app_posts_columns', array( $this, 'app_posts_app_table_head' ), 10, 1 );

		// Action hook to add values in new columns in the APP list.
		add_action( 'manage_sstk_app_posts_custom_column', array( $this, 'app_posts_app_table_content' ), 10, 2 );

		// Filter hook to add new columns to the SERVER list.
		add_filter( 'manage_sstk_app_server_posts_columns', array( $this, 'app_server_table_head' ), 10, 1 );

		// Show some app details in the wp-admin list of apps.
		add_filter( 'sstk_app_admin_list_summary_column', array( &$this, 'app_admin_list_summary_column' ), 10, 2 );

		// Show some app details in the wp-admin server column.
		add_filter( 'sstk_app_admin_list_server_column_before_apps_link', array( &$this, 'app_admin_list_server_column_before_apps_link' ), 10, 2 );

		// Show some app details about the health of the app the wp-admin list of apps.
		add_filter( 'sstk_app_admin_list_app_health_column', array( &$this, 'app_admin_list_health_column' ), 10, 2 );

		// Add the INSTALL WordPress button to the server list.
		add_filter( 'sstk_app_server_table_content', array( &$this, 'app_server_table_content' ), 10, 3 );

		// Filter hook to add a REMOVE SITE link to the hover action on an app.
		add_filter( 'post_row_actions', array( $this, 'post_row_actions' ), 10, 2 );

		/*
		 * Meta Box registration is retired as of Phase 4. The server and site screens are
		 * rendered by the React client, which reads the same schema through the
		 * `get_tabnames` / `get_tabs` filters (see SSTK_REST_Tabs) and dispatches
		 * through the `*_tab_action` filters below. Nothing here needs Meta Box any more.
		 */

		// Action hook to fire on new site created on WP Multisite.
		add_action( 'wp_initialize_site', array( $this, 'wpapp_schedule_events_for_new_site' ), 10, 2 );

		// Action hook to set transient if directory is readable and .txt files are accessible.
		add_action( 'admin_init', array( $this, 'wpapp_admin_init_is_readable' ) );

		// Action hook to handle ajax request to set transient if user closed the readable notice check.
		add_action( 'wp_ajax_set_readable_check', array( $this, 'set_readable_check' ) );

		// Action hook to handle ajax request to set transient if user clicked the "check again" option in the "readable check" notice.
		add_action( 'wp_ajax_readable_check_again', array( $this, 'readable_check_again' ) );

		// Action hook to handle ajax request to execute passwordless login.
		add_action( 'wp_ajax_passwordless_login', array( $this, 'passwordless_login' ) );

		// Action hook to extend admin filter options.
		add_action( 'restrict_manage_posts', array( $this, 'wpapp_sstk_app_table_filtering' ) );

		// Filter hook to filter app listing on custom meta data.
		add_filter( 'parse_query', array( $this, 'wpapp_sstk_app_parse_query' ), 10, 1 );

		// Action hook to handle ajax request to set transient if user closed the notice for cron check.
		add_action( 'wp_ajax_set_cron_check', array( $this, 'set_cron_check' ) );

		// Action hook to handle ajax request to set transient if user closed the notice for php version check.
		add_action( 'wp_ajax_php_version_check', array( $this, 'php_version_check' ) );

		// Action hook to handle ajax request to set transient if user closed the notice for localhost check.
		add_action( 'wp_ajax_localhost_version_check', array( $this, 'localhost_version_check' ) );
	}

	/**
	 * Setup some action hooks to do things after a server has been created.
	 * The hook functions themselves are in a trait file  after-prepare-server.php
	 */
	public function sstk_after_server_prepare_action_hooks() {
		/* Add tasks to pending logs after a server is created. */
		add_action( 'sstk_command_wordpress-app_prepare_server_completed', array( $this, 'sstk_wpapp_core_prepare_server_completed' ), 10, 2 );

		/* Pending Logs Background Task: Trigger installation of backup scripts on the server */
		add_action( 'sstk_core_after_server_prepare_install_server_backups', array( $this, 'sstk_core_install_server_backups' ), 10, 3 );

		/*  Install Backups: Handle backup script installation success or failure */
		add_action( 'sstk_server_wordpress-app_server_auto_backup_action_all_sites_successful', array( $this, 'sstk_core_install_server_handle_backup_script_install_success' ), 10, 3 );
		add_action( 'sstk_server_wordpress-app_server_auto_backup_action_all_sites_failed', array( $this, 'sstk_core_install_server_handle_backup_script_install_failed' ), 10, 3 );

		/* Pending Logs Background Task: Trigger installation of server configuration backups on the server */
		add_action( 'sstk_core_after_server_prepare_install_server_configuration_backups', array( $this, 'sstk_core_install_server_configuration_backups' ), 10, 3 );

		/*  Install Server Configuration Backups: Handle backup script installation success or failure */
		add_action( 'sstk_server_wordpress-app_toggle_server_configuration_backups_success', array( $this, 'sstk_core_install_server_handle_config_backup_install_success' ), 10, 3 );
		add_action( 'sstk_server_wordpress-app_toggle_server_configuration_backups_failed', array( $this, 'sstk_core_install_server_handle_config_backup_install_failed' ), 10, 3 );

		/* Pending Logs Background Task: Run callback for the first time on a server after they're installed. */
		add_action( 'sstk_core_after_server_prepare_run_server_callbacks', array( $this, 'sstk_core_run_server_callbacks' ), 10, 3 );

	}

	/**
	 * Include the files corresponding to the tabs for a site
	 */
	private function include_tabs() {

		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs/tabs.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs/general.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs/backup.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs/ssl.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs/cache.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs/sftp.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs/staging.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs/clone-site.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs/copy-to-existing-site.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs/site-sync.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs/crons.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs/php-options.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs/change-domain.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs/misc.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs/tweaks.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs/tools.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs/theme-and-plugin-updates.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs/phpmyadmin.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs/7g_firewall.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs/security.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs/statistics.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs/logs.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs/wp-site-users.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs/wpconfig-options.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs/redirect-rules.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs/file-manager.php';

		if ( true === sstk_is_git_enabled() ) {
			require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs/git-control-site.php';
		}

		if ( true === sstk_is_mt_enabled() ) {
			require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs/multitenant-site.php';
		}

		if ( defined( 'SSTK_SHOW_SITE_USERS_TAB' ) && SSTK_SHOW_SITE_USERS_TAB ) {
			require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs/site-system-users.php';
		}

		/**
		 * Need to add new tabs or add data to existing tabs from an add-on?
		 * Then this action hook MUST be used! Otherwise, weird
		 * stuff will happen and you will not know why!
		 */
		do_action( 'sstk_wpapp_include_app_tabs' );

	}

	/**
	 * Include the files corresponding to the tabs for the server CPT.
	 */
	private function include_tabs_server() {

		// No upgrades needed - show every thing.
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs-server/general.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs-server/services.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs-server/callbacks.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs-server/ufw_firewall.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs-server/backup.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs-server/monit.php';

		// Possibly add required files for ssh console.
		$option_hide_ssh_console = get_option( 'sstk_wpapp_ssh_console_hide' );
		if ( ! empty( $option_hide_ssh_console ) && true == boolval( $option_hide_ssh_console ) ) {
			// ssh console tab should be hidden unless a wp-config option forces it to be shown - so check for that here.
			if ( defined( 'SSTK_HIDE_SSH_CONSOLE' ) && ! SSTK_HIDE_SSH_CONSOLE ) {
				require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs-server/ssh_console.php';
			}
		} else {
			// possibly show the console unless a wp-config option forces it to be hidden so check for that here.
			if ( ! defined( 'SSTK_HIDE_SSH_CONSOLE' ) || ( defined( 'SSTK_HIDE_SSH_CONSOLE' ) && ! SSTK_HIDE_SSH_CONSOLE ) ) {
				require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs-server/ssh_console.php';
			}
		}

		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs-server/statistics.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs-server/tools.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs-server/tweaks.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs-server/upgrade.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs-server/sites.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs-server/power.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs-server/users.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs-server/logs.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs-server/fail2ban.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs-server/sshkeys.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs-server/resize.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs-server/ols_console.php';

		if ( true === sstk_is_git_enabled() ) {
			require_once SSTK_PATH . 'includes/core/apps/wordpress-app/tabs-server/git_control.php';
		}

		/**
		 * Need to add new tabs or add data to existing tabs from an add-on?
		 * Then this action hook MUST be used! Otherwise, weird
		 * stuff will happen and you will not know why!
		 */
		do_action( 'sstk_wpapp_include_server_tabs' );

	}

	/**
	 * Include the files for the REST API and instantiate the controllers
	 */
	private function include_rest_api() {
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/rest-api/class-sstk-rest-api-controller-base.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/rest-api/class-sstk-rest-api-controller-servers.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/rest-api/class-sstk-rest-api-controller-sites.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/rest-api/class-sstk-rest-api-controller-tasks.php';

		// Function specific endpoints for sites - so that the main sites controller file does not get large and unwieldly.
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/rest-api/class-sstk-rest-api-controller-sites-change-domain.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/rest-api/class-sstk-rest-api-controller-sites-clone-site.php';

		/**
		 * Need to add new REST API controllers from an add-on?
		 * Then this action hook MUST be used! Otherwise, weird
		 * stuff will happen and you will not know why!
		 *
		 * Third parties that need to add a plugin to extend
		 * the rest api by adding new controllers should
		 * ensure that the filter a few lines below is used
		 * to add the new controller to the array so that
		 * they can be instantiated.
		 */
		do_action( 'sstk_wpapp_include_rest_api' );

		// List of controllers to instantiate.
		// This list should be added to by other plugins adding their own rest api controllers.
		$controllers = apply_filters(
			"sstk_app_{$this->get_app_name()}_rest_api_controller_list",
			array(
				SSTK_REST_API_Controller_Servers::class,
				SSTK_REST_API_Controller_Sites::class,
				SSTK_REST_API_Controller_Sites_Change_Domain::class,
				SSTK_REST_API_Controller_Sites_Clone_Site::class,
				SSTK_REST_API_Controller_Tasks::class,
			)
		);

		// Loop through list and instantiate.
		foreach ( $controllers as $controller_class ) {
			$controller                                        = new $controller_class();
			$this->rest_controllers[ $controller->get_name() ] = $controller;
		}

	}

	/**
	 * Get general fields for metaboxes on app cpt screen
	 *
	 * @param array $fields fields.
	 * @param int   $app_id is the post id of the app record we're on.
	 *
	 * @return array new array of fields.
	 *
	 * @TODO: strip out inline styles and move to admin stylesheet?
	 */
	private function get_general_fields( array $fields, $app_id ) {

		// What type of web server are we running?
		$webserver_type      = $this->get_web_server_type( $app_id );
		$webserver_type_name = $this->get_web_server_description_by_id( $app_id );

		// SSL enabled?
		$ssl_status               = $this->get_site_local_ssl_status( $app_id );   // Returns a boolean.
		$ssl_status_display_value = true === $ssl_status ? __( 'On', 'simple-stack' ) : __( 'Off', 'simple-stack' );
		if ( true === boolval( $ssl_status ) ) {
			$ssl_class_name = 'sstk_site_details_top_row_element_ssl_on';
		} else {
			$ssl_class_name = 'sstk_site_details_top_row_element_ssl_off';
		}

		// Page Cache.
		$page_cache_status        = $this->get_page_cache_status( $app_id );
		$page_cache_display_value = 'on' === $page_cache_status ? __( 'On', 'simple-stack' ) : __( 'Off', 'simple-stack' );
		if ( 'on' === $page_cache_status ) {
			$page_cache_class_name = 'sstk_site_details_top_row_element_page_cache_on';
		} else {
			$page_cache_class_name = 'sstk_site_details_top_row_element_page_cache_off';
		}

		// Git.
		$git_status               = $this->get_git_status( $app_id );
		$git_status_display_value = '';
		if ( true === $git_status ) {
			$git_status_display_value = __( 'On', 'simple-stack' );
			$git_status_class_name    = 'sstk_site_details_top_row_element_git_status';

			$git_branch_display_value = $this->get_git_branch( $app_id );
			$git_branch_class_name    = 'sstk_site_details_top_row_element_git_branch';  // Not used - for now we're using the $git_status_class_name for both git status and branch name.
		}

		// Site Type.
		$site_type               = $this->get_mt_site_type( $app_id );
		$site_type_display_value = '';
		if ( 'standard' !== $site_type && ! empty( $site_type ) ) {
			$site_type_display_value = $site_type;
			$site_type_class_name    = 'sstk_site_details_top_row_element_site_type';
		}

		/**
		 * Wrap the page cache, ssl status and other elements into a set of spans that will go underneath the domain name.
		 */
		$other_data = '<div class="sstk_site_details_top_row_element_wrapper">';

		if ( sstk_is_admin() && ( ! sstk_get_early_option( 'wordpress_app_disable_passwordless_login' ) ) ) {
			$passwordless_login_link = $this->get_passwordless_login_link_for_display( $app_id, __( 'Login', 'simple-stack' ) );
			$other_data             .= '<span class="sstk_medium_chicklet sstk_site_details_top_row_element_passwordless_login">' . $passwordless_login_link . '</span>';
		}

		$other_data .= '<span class="sstk_medium_chicklet sstk_site_details_top_row_element_wstype">' . $webserver_type_name . '</span>';
		$other_data .= '<span class=" sstk_medium_chicklet ' . $ssl_class_name . '">' . sprintf( __( 'SSL: %s', 'simple-stack' ), $ssl_status_display_value ) . '</span>';
		$other_data .= '<span class=" sstk_medium_chicklet ' . $page_cache_class_name . '">' . sprintf( __( 'Cache: %s', 'simple-stack' ), $page_cache_display_value ) . '</span>';
		if ( ! empty( $git_status ) ) {
			/* Translators: %s is the git status (on or off). */
			$other_data .= '<span class=" sstk_medium_chicklet ' . $git_status_class_name . '">' . sprintf( __( 'Git: %s', 'simple-stack' ), $git_status_display_value ) . '</span>';
		}
		if ( ! empty( $git_branch_display_value ) ) {
			/* Translators: %s is the git branch name. */
			$other_data .= '<span class=" sstk_medium_chicklet ' . $git_status_class_name . '">' . sprintf( __( 'Branch: %s', 'simple-stack' ), $git_branch_display_value ) . '</span>';
		}
		if ( ! empty( $site_type_display_value ) ) {
			/* Translators: %s is the multi-tenant site type. */
			$other_data .= '<span class=" sstk_medium_chicklet ' . $site_type_class_name . '">' . sprintf( __( '%s', 'simple-stack' ), $site_type_display_value ) . '</span>';
		}
		$other_data .= '</div>';
		/* End Wrap page cache, ssl status and other elements */

		// Copy IP.
		$copy_app_ip = sstk_wrap_clipboard_copy( $this->get_ipv4_address( $app_id ) );

		if ( sstk_get_early_option( 'sstk_show_ipv6' ) ) {
			$copy_app_ip .= sstk_wrap_clipboard_copy( $this->get_ipv6_address( $app_id ) );
		}

		// There should be no 'other data' if the setting to not show it is enabled.
		if ( sstk_get_option( 'wordpress_app_hide_chicklet_area_in_site_detail' ) ) {
			$other_data = '';
		}

		// Certain columns need to be expanded or contracted based on whether other columns are allowed to be shown or hidden.
		$view_admin_site_columns       = 2;
		$hide_view_apps_on_server_link = false;
		if ( true === (bool) sstk_get_early_option( 'wordpress_app_hide_view_apps_on_server_link' ) && ! sstk_is_admin() ) {
			$view_admin_site_columns       = 3;
			$hide_view_apps_on_server_link = true;
		}

		$fields[] = array(
			'name'    => __( 'Domain', 'simple-stack' ),
			'type'    => 'custom_html',
			'std'     => sstk_wrap_clipboard_copy( $this->get_domain_name( $app_id ) ) . $other_data,
			'columns' => 'left' === $this->get_tab_style() ? 4 : 4,
			'class'   => 'left' === $this->get_tab_style() ? 'sstk_site_details_top_row sstk_site_details_top_row_domain sstk_site_details_top_row_domain_left' : 'sstk_site_details_top_row sstk_site_details_top_row_domain',
		);
		$fields[] = array(
			'name'    => __( 'IP', 'simple-stack' ),
			'type'    => 'custom_html',
			'std'     => $copy_app_ip,
			'columns' => 'left' === $this->get_tab_style() ? 2 : 2,
			'class'   => 'sstk_site_details_top_row sstk_site_details_top_row_ip',
		);
		$fields[] = array(
			'name'    => __( 'Admin', 'simple-stack' ),
			'id'      => 'sstk_app_action_site-detail-header-view-admin',
			'type'    => 'button',
			'std'     => $this->get_formatted_wpadmin_link( $app_id ),
			'columns' => 'left' === $this->get_tab_style() ? $view_admin_site_columns : $view_admin_site_columns,
			'class'   => 'sstk_site_details_top_row sstk_site_details_top_row_admin_login',
		);
		$fields[] = array(
			'name'    => __( 'Front-end', 'simple-stack' ),
			'id'      => 'sstk_app_action_site-detail-header-view-site',
			'type'    => 'button',
			'std'     => $this->get_formatted_site_link( $app_id ),
			'columns' => 'left' === $this->get_tab_style() ? $view_admin_site_columns : $view_admin_site_columns,
			'class'   => 'sstk_site_details_top_row sstk_site_details_top_row_front_end',
		);

		if ( false === $hide_view_apps_on_server_link ) {
			$server_post_id = get_post_meta( $app_id, 'parent_post_id', true );
			if ( is_admin() ) {
				// We're viewing in the wp-admin area.
				$url = admin_url( 'edit.php?post_type=sstk_app&server_id=' . (string) $server_post_id );
			} else {
				// We're viewing on the front-end.
				$url = get_permalink( (int) $server_post_id );
			}
			$apps_on_server = sprintf( '<a href="%s" target="_blank">%s</a>', $url, __( 'View Apps', 'simple-stack' ) );

			$fields[] = array(
				'name'    => __( 'Apps on Server', 'simple-stack' ),
				'id'      => 'sstk_app_action_site-detail-header-view-apps',
				'type'    => 'button',
				'std'     => $apps_on_server,
				'columns' => 'left' === $this->get_tab_style() ? 2 : 2,
				'class'   => 'sstk_site_details_top_row sstk_site_details_top_row_apps_on_server',
			);
		}

		// Does a warning need to be shown because SITE PACKAGES are likely still running?
		if ( true === $this->sstk_is_site_package_running( $app_id ) ) {
			$message  = '<b><small>' . __( 'Simple Stack: It appears we are still setting up this site for you.', 'simple-stack' );
			$message .= '<br />';
			$message .= __( 'Certain actions for site packages are still running.', 'simple-stack' );
			$message .= '<br />';
			$message .= __( 'The site should be available shortly!', 'simple-stack' ) . '</b></small>';

			$fields[] = array(
				'type' => 'divider',
			);

			$fields[] = array(
				'name'  => __( 'Not-Ready Notice', 'simple-stack' ),
				'type'  => 'custom_html',
				'std'   => $message,
				'class' => 'sstk_site_details_top_row',
			);

		}

		return $fields;
	}

	/**
	 * Get general fields for metaboxes on server cpt screen
	 *
	 * @param array $fields fields.
	 * @param int   $id is the post id of the app record we're on.
	 *
	 * @return array new array of fields
	 *
	 * @TODO: strip out inline styles and move to admin stylesheet?
	 */
	private function get_general_fields_server( array $fields, $id ) {

		// What type of web server are we running?
		$webserver_type      = $this->get_web_server_type( $id );
		$webserver_type_name = $this->get_web_server_description_by_id( $id );

		// Is git installed?
		$git_status = $this->get_git_status( $id );

		$other_data  = '<div class="sstk_site_details_top_row_element_wrapper">';
		$other_data .= '<span class="sstk_medium_chicklet sstk_server_details_top_row_element_wstype">' . $webserver_type_name . '</span>';
		if ( true === $git_status ) {
			$other_data .= '<span class="sstk_medium_chicklet sstk_server_details_top_row_element_git_status">' . __( 'Git On', 'simple-stack' ) . '</span>';
		}
		$other_data .= '</div>';

		$copy_ip = sstk_wrap_clipboard_copy( SSTK_SERVER()->get_ipv4_address( $id ) );
		if ( sstk_get_early_option( 'sstk_show_ipv6' ) ) {
			$copy_ip .= sstk_wrap_clipboard_copy( SSTK_SERVER()->get_ipv6_address( $id ) );
		}

		$fields['general-welcome-top-col_1'] = array(
			'name'    => __( 'Server Name', 'simple-stack' ),
			'type'    => 'custom_html',
			'std'     => get_post_meta( $id, 'sstk_server_name', true ) . $other_data,
			'columns' => 3,
			'class'   => 'sstk_server_details_top_row sstk_server_details_top_row_server_name',
		);

		$fields['general-welcome-top-col_2'] = array(
			'name'    => __( 'IP', 'simple-stack' ),
			'type'    => 'custom_html',
			'std'     => $copy_ip,
			'columns' => 3,
			'class'   => 'sstk_server_details_top_row sstk_server_details_top_row_ip',
		);

		$fields['general-welcome-top-col_3'] = array(
			'name'    => __( 'Provider', 'simple-stack' ),
			'type'    => 'custom_html',
			'std'     => SSTK()->sstk_get_cloud_provider_desc( get_post_meta( $id, 'sstk_server_provider', true ) ),
			'columns' => 2,
			'class'   => 'sstk_server_details_top_row sstk_server_details_top_row_provider',
		);

		$fields['general-welcome-top-col_4'] = array(
			'name'    => __( 'Region', 'simple-stack' ),
			'type'    => 'custom_html',
			'std'     => get_post_meta( $id, 'sstk_server_region', true ),
			'columns' => 2,
			'class'   => 'sstk_server_details_top_row sstk_server_details_top_row_region',
		);

		if ( is_admin() ) {
			$apps_url = admin_url( 'edit.php?post_type=sstk_app&server_id=' . $id );
		} else {
			$apps_url = get_permalink( SSTK_WORDPRESS_APP_PUBLIC::get_apps_list_page_id() ) . '?server_id=' . (string) $id;
		}

		$fields['general-welcome-top-col_5'] = array(
			'name'    => __( 'Apps', 'simple-stack' ),
			'type'    => 'custom_html',
			'std'     => sprintf( '<a href="%s" target="_blank">%d</a>', esc_url( $apps_url ), SSTK_SERVER()->get_app_count( $id ) ),
			'columns' => 2,
			'class'   => 'sstk_server_details_top_row sstk_server_details_top_row_app_count',
		);

		return $fields;
	}

	/**
	 * Returns a filtered array of allowed WP versions.
	 *
	 * @since 5.0.0
	 *
	 * @return array.
	 */
	public static function get_wp_versions() {

		// @SEE: https://wordpress.org/download/releases/
		$versions          = array( 'latest', '6.5.2', '6.4.4', '6.3.4', '6.2.5', '6.1.6', '6.0.7', '5.9.9', '5.8.9', '5.7.11', '5.6.13', '5.5.14', '5.4.15', '5.3.17', '5.2.20', '5.1.18', '5.0.21', '4.9.25', '4.8.24', '4.7.28' );
		$override_versions = sstk_get_option( 'wordpress_app_allowed_wp_versions' );

		if ( ! empty( $override_versions ) ) {
			$versions = $override_versions;
		}

		// Possibly add in the 'nightly' version option.
		if ( sstk_get_option( 'wordpress_app_versions_show_nightly' ) ) {
			$versions = array_merge( $versions, array( 'nightly' ) );
		}

		return apply_filters( 'sstk_allowed_wp_versions', $versions );

	}

	/**
	 * Return a default OS version based on whether settings has one or not.
	 *
	 * @since 5.3.9
	 *
	 * @return string.
	 */
	public static function get_default_os() {
		$default_os = sstk_get_option( 'wordpress_app_default_os' );
		$os_list    = SSTK()->get_os_list();

		if ( empty( $default_os ) || ! isset( $os_list[ $default_os ] ) ) {
			$default_os = 'ubuntu2404lts';
		}

		$default_os = apply_filters( 'sstk_default_os', $default_os );

		return isset( $os_list[ $default_os ] ) ? $default_os : 'ubuntu2404lts';
	}

	/**
	 * Returns the default webserver type.
	 *
	 * @since 5.0.0
	 *
	 * @return string.
	 */
	public static function get_default_webserver() {
		return 'ols';
	}

	/**
	 * Returns the webserver installed on a server.
	 *
	 * @since 5.0.0
	 *
	 * @param int $post_id The post id of the server or app record.
	 *
	 * @return string|boolean
	 */
	public function get_web_server_type( $post_id ) {

		// If for some reason we didn't get a server_id, retrieve it from the app.
		$server_id = $post_id;
		if ( 'sstk_app_server' !== get_post_type( $server_id ) ) {
			$server_id = $this->get_server_id_by_app_id( $server_id );
		}

		$web_server_type = false;
		if ( ! empty( $server_id ) && ( ! is_wp_error( $server_id ) ) ) {
			$web_server_type = get_post_meta( $server_id, 'sstk_server_webserver_type', true );
			if ( empty( $web_server_type ) ) {
				$web_server_type = 'ols';
			} elseif ( 'ols' !== $web_server_type ) {
				$web_server_type = false;
			}
		}

		return $web_server_type;

	}

	/**
	 * Get the webserver name (full name) installed on a server
	 *
	 * @since 5.0
	 *
	 * @param string $web_server_type key.
	 *
	 * @return string
	 */
	public static function get_web_server_description( $web_server_type ) {

		$return = $web_server_type;

		if ( 'ols' === $web_server_type ) {
			return __( 'OpenLiteSpeed', 'simple-stack' );
		}

		return $return;

	}

	/**
	 * Get the webserver name (full name) installed on a server given an app or server id.
	 *
	 * @since 5.0
	 *
	 * @param int $post_id The post id of the server or app record.
	 *
	 * @return string
	 */
	public function get_web_server_description_by_id( $post_id ) {

		$web_server_type = $this->get_web_server_type( $post_id );

		return $this->get_web_server_description( $web_server_type );

	}

	/**
	 * Get the domain name used for a wp app instance
	 *
	 * @param int $app_id post id of app record.
	 *
	 * @return string the domain name.
	 */
	public function get_domain_name( $app_id ) {
		return get_post_meta( $app_id, 'wpapp_domain', true );
	}

	/**
	 * Returns an app ID using the postid of a server and the domain name.
	 *
	 * @param int    $server_id  The server for which to locate the app post.
	 * @param string $domain The domain for which to locate the app post on the server.
	 *
	 * @return int|boolean app post id or false or error message
	 */
	public function get_app_id_by_server_id_and_domain( $server_id, $domain ) {

		// If the server id is not for a server post, return immediately with an error.
		if ( 'sstk_app_server' !== get_post_type( $server_id ) ) {
			return false;
		}

		$posts = get_posts(
			array(
				'post_type'   => 'sstk_app',
				'post_status' => 'private',
				'numberposts' => -1,
				'meta_query'  => array(
					array(
						'key'   => 'parent_post_id',
						'value' => $server_id,
					),
					array(
						'key'   => 'wpapp_domain',
						'value' => $domain,
					),
				),
			),
		);

		if ( $posts ) {
			return $posts[0]->ID;
		} else {
			return false;
		}

	}

	/**
	 * Returns all the app posts for a particular a domain name.
	 *
	 * Usually, only one post exists for a domain.
	 * But if a domain is pushed to another server, multiple posts might exist.
	 *
	 * @param string $domain The domain for which to locate the app post on the server.
	 *
	 * @return array|boolean|string array of posts or false or error message
	 */
	public function get_apps_by_domain( $domain ) {

		$posts = get_posts(
			array(
				'post_type'   => 'sstk_app',
				'post_status' => 'private',
				'numberposts' => -1,
				'meta_query'  => array(
					array(
						'key'   => 'wpapp_domain',
						'value' => $domain,
					),
				),
			),
		);

		if ( $posts ) {
			return $posts;
		} else {
			return false;
		}

	}


	/**
	 * Get the status of ssl stored in the metadata for a site.
	 *
	 * @param string $app_id is the post id of the app record we're asking about.
	 *
	 * @return boolean
	 */
	public function get_site_local_ssl_status( $app_id ) {

		$value = false;

		// Check standard ssl metas.
		if ( 'on' === get_post_meta( $app_id, 'wpapp_ssl_status', true ) ) {
			$value = true;
		} else {
			$value = false;
		}

		// Check wildcard ssl metas.
		if ( false === $value ) {
			if ( 'on' === get_post_meta( $app_id, 'wpapp_multisite_wildcard_ssl_status', true ) ) {
				$value = true;
			}
		}

		return $value;

	}

	/**
	 * Get the status of cusstom ssl stored in the metadata for a site.
	 *
	 * @param string $app_id is the post id of the app record we're asking about.
	 *
	 * @return boolean
	 */
	public function get_site_local_custom_ssl_status( $app_id ) {

		$value = false;

		// Check custom ssl metas.
		if ( 'on' === get_post_meta( $app_id, 'wpapp_custom_ssl_status', true ) ) {
			$value = true;
		} else {
			$value = false;
		}

		return $value;

	}

	/**
	 * Set the status of custom ssl stored in the metadata for a site.
	 *
	 * @param string $app_id is the post id of the app record we're asking about.
	 * @param string $status 'on' or 'off.
	 */
	public function set_site_local_custom_ssl_status( $app_id, $status ) {

		update_post_meta( $app_id, 'wpapp_custom_ssl_status', $status );

	}

	/**
	 * Get the status of WILDCARD ssl stored in the metadata for a site.
	 *
	 * @param string $app_id is the post id of the app record we're asking about.
	 *
	 * @return boolean
	 */
	public function get_site_local_wildcard_ssl_status( $app_id ) {

		$value = false;

		if ( 'on' === get_post_meta( $app_id, 'wpapp_multisite_wildcard_ssl_status', true ) ) {
			$value = true;
		}

		return $value;

	}

	/**
	 * Sets the status of debug metas.
	 *
	 * @param int         $app_id is the post id of the app record we're working with.
	 * @param bool|string $debug_status Should be 'on' or 'off'.
	 *
	 * @return void
	 */
	public function set_site_local_wpdebug_flag( $app_id, $debug_status ) {

		if ( true === $debug_status || 'on' === $debug_status ) {
			update_post_meta( $app_id, 'wpapp_wp_debug', 1 );
		} else {
			update_post_meta( $app_id, 'wpapp_wp_debug', 0 );
		}

	}

	/**
	 * Get the status of the wp_debug flag stored in the metadata for a site.
	 *
	 * @param string $app_id is the post id of the app record we're asking about.
	 *
	 * @return boolean
	 */
	public function get_site_local_wpdebug_flag( $app_id ) {

		if ( 1 === (int) get_post_meta( $app_id, 'wpapp_wp_debug', true ) ) {
			return true;
		} else {
			return false;
		}

	}


	/**
	 * Get a formatted link to wp_admin area
	 *
	 * @param string $app_id is the post id of the app record we're asking about.
	 * @param bool   $icon Whether to style with just an icon instead of text.
	 *
	 * @return string
	 */
	public function get_formatted_wpadmin_link( $app_id, $icon = false ) {

		// get ssl status first.
		$ssl = $this->get_site_local_ssl_status( $app_id );

		// get domain name.
		$domain = $this->get_domain_name( $app_id );

		if ( true == $ssl ) {
			$url_wpadmin = 'https://' . $domain . '/wp-admin';
		} else {
			$url_wpadmin = 'http://' . $domain . '/wp-admin';
		}

		if ( true === $icon ) {
			return sprintf( '<a href = "%s" target="_blank">' . '<i class="fa-duotone fa-user-unlock"></i>' . '</a>', $url_wpadmin );
		} else {
			return sprintf( '<a href = "%s" target="_blank">' . __( 'Admin Login', 'simple-stack' ) . '</a>', $url_wpadmin );
		}

	}

	/**
	 * Get a formatted public admin link (front end admin link.)
	 *
	 * @param string $app_id is the post id of the app record we're asking about.
	 * @param string $label The label for the link.
	 *
	 * @return string
	 */
	public function get_formatted_public_admin_link( $app_id, $label ) {

		$link = get_permalink( $app_id );
		return sprintf( '<a href = "%s" target="_blank">' . $label . '</a>', $link );

	}

	/**
	 * Returns whether PHP 8.3 is part of the server baseline.
	 *
	 * @param int $server_id ID of server being interrogated.
	 *
	 * @return boolean
	 */
	public function is_php_83_installed( $server_id ) {

		$initial_plugin_version = (string) $this->get_server_meta_by_app_id( $server_id, 'sstk_server_plugin_initial_version', true );

		return version_compare( $initial_plugin_version, '1.0.0', '>=' );
	}

	/**
	 * Returns whether WP-CLI 2.10 or newer is installed.
	 *
	 * @param int $server_id ID of server being interrogated.
	 *
	 * @return boolean
	 */
	public function is_wpcli210_installed( $server_id ) {

		$initial_plugin_version = (string) $this->get_server_meta_by_app_id( $server_id, 'sstk_server_plugin_initial_version', true );
		if ( version_compare( $initial_plugin_version, '1.0.0', '>=' ) ) {
			return true;
		}

		$installed_version = (string) $this->get_server_meta_by_app_id( $server_id, 'sstk_server_wpcli_upgrade', true );

		return '' !== $installed_version && version_compare( $installed_version, '2.10', '>=' );
	}

	/**
	 * Returns a boolean true/false if http2 is enabled or disabled on a site
	 *
	 * @param int $app_id ID of app being interrogated.
	 *
	 * @return boolean
	 */
	public function is_http2_enabled( $app_id ) {

		if ( 'on' === $this->http2_status( $app_id ) ) {
			return true;
		} else {
			return false;
		}

	}

	/**
	 * Returns on/off status of http2 for the site being interrogated.
	 *
	 * @param int $app_id ID of app being interrogated.
	 *
	 * @return string 'on' or 'off'
	 */
	public function http2_status( $app_id ) {

		$http2_status = get_post_meta( $app_id, 'wpapp_ssl_http2_status', true );

		if ( empty( $http2_status ) ) {
			$http2_status = 'off';
		}

		return $http2_status;

	}

	/**
	 * Returns whether a site is enabled or disabled.
	 *
	 * @param int $app_id ID of app being interrogated.
	 *
	 * @return boolean true/false
	 */
	public function is_site_enabled( $app_id ) {

		// Assume true.
		$return = true;

		// If meta shows site is disabled then return false.
		if ( 'off' === $this->site_status( $app_id ) ) {
			$return = false;
		}

		// If other indicators show site is unavailable return false.
		// This part should probably be extracted into it's own function and added to the is_site_available() query in all the tabs.
		// But for now it's more expdient to commingle them.
		if ( true === $return ) {
			if ( false === $this->is_site_available_for_commands( true, $app_id ) ) {
				$return = false;
			}
		}

		return $return;
	}

	/**
	 * Returns an indicator whether the site is enabled or disabled.
	 *
	 * @param int $app_id ID of app being interrogated.
	 *
	 * @return string 'on' or 'off'
	 */
	public function site_status( $app_id ) {

		$current_status = get_post_meta( $app_id, 'wpapp_site_status', true );

		if ( empty( $current_status ) ) {
			$current_status = 'on';
		}

		return $current_status;

	}

	/**
	 * Sets the admin lock status.
	 *
	 * @since 5.0
	 *
	 * @param int         $app_id Post id of app being updated.
	 * @param string|bool $status Status of admin lock ('on','off',true,false).
	 *
	 * @return void
	 */
	public function set_admin_lock_status( $app_id, $status ) {

		if ( true === $status || 'on' === $status ) {
			update_post_meta( $app_id, 'sstk_wpapp_admin_lock_status', 'on' );
		}

		if ( false === $status || 'off' === $status ) {
			update_post_meta( $app_id, 'sstk_wpapp_admin_lock_status', 'off' );
		}

	}

	/**
	 * Returns an indicator whether a site has the admin lock disabled or enabled.
	 *
	 * @since 5.0
	 *
	 * @param int $app_id ID of app being interrogated.
	 *
	 * @return boolean
	 */
	public function get_admin_lock_status( $app_id ) {

		$current_status = get_post_meta( $app_id, 'sstk_wpapp_admin_lock_status', true );

		if ( empty( $current_status ) ) {
			$current_status = 'off';
		}

		if ( 'off' === $current_status ) {
			return false;
		}

		if ( 'on' === $current_status ) {
			return true;
		}

		return false;

	}

	/**
	 * Sets the maximum number of sites allowed on a server.
	 *
	 * @since 5.0
	 *
	 * @param int $id ID of server to update.
	 * @param int $count Number of sites allowed on the server.
	 *
	 * @return boolean|object The return value from the update_post_meta function.
	 */
	public function set_server_max_sites_allowed( $id, $count ) {
		return update_post_meta( $id, 'sstk_server_max_sites', $count );
	}


	/**
	 * Returns the maximum sites allowed on a server.
	 *
	 * @since 5.0
	 *
	 * @param int $id ID of server being interrogated.
	 *
	 * @return int
	 */
	public function get_server_max_sites_allowed( $id ) {
		return (int) get_post_meta( $id, 'sstk_server_max_sites', true );
	}

	/**
	 * Returns true if server has exceeded the sites allowed, false otherwise.
	 * If the number of sites allowed on a server is set to zero then return false.
	 *
	 * @since 5.0
	 *
	 * @param int $id ID of server being interrogated.
	 *
	 * @return boolean
	 */
	public function get_has_server_exceeded_sites_allowed( $id ) {

		// Make sure the ID is for a server post type.
		$server_id = 0;
		$post_type = get_post_type( $id );
		if ( ( 'sstk_app_server' !== $post_type ) && ( 'sstk_app' === $post_type ) ) {
			$server_id = $this->get_server_id_by_app_id( $id );
		} else {
			if ( 'sstk_app_server' === $post_type ) {
				$server_id = $id;
			}
		}

		// If we don't have a server id return false.
		if ( ! $server_id ) {
			return false;
		}

		// Get max number of sites sites allowed on server.
		$sites_allowed = $this->get_server_max_sites_allowed( $server_id );

		if ( $sites_allowed > 0 ) {
			// Get count of sites on server.
			$current_count = SSTK_SERVER()->get_app_count( $server_id );

			if ( $current_count >= $sites_allowed ) {
				return true;
			} else {
				return false;
			}
		}

		return false;

	}

	/**
	 * Returns an indicator whether the page cache is enabled or not.
	 *
	 * @since 5.0
	 *
	 * @param int $app_id ID of app being interrogated.
	 *
	 * @return string 'on' or 'off'
	 */
	public function get_page_cache_status( $app_id ) {
		$current_status = sstk_maybe_unserialize( get_post_meta( $app_id, 'wpapp_pagecache_status', true ) );

		if ( empty( $current_status ) ) {
			$current_status = 'off';
		}

		return $current_status;

	}

	/**
	 * Return the PHP version that is the default for this version of the app.
	 */
	public function get_wpapp_default_php_version() {
		return '8.4';
	}

	/**
	 * Returns the default PHP version for a server.
	 *
	 * @param int $server_id The post id of the server (or app).
	 *
	 * @return string
	 */
	public function get_default_php_version_for_server( $server_id ) {
		return '8.4';
	}

	/**
	 * Returns the default PHP version for a server without the period in it.
	 * Eg: 84 instead of 8.4
	 *
	 * @param int $server_id The post id of the server (or app).
	 *
	 * @return string
	 */
	public function get_default_php_version_no_period( $server_id ) {
		$version = $this->get_default_php_version_for_server( $server_id );
		$version = str_replace( '.', '', $version );
		return $version;
	}

	/**
	 * Get PHP version for app.
	 *
	 * @param int $app_id post id of app.
	 *
	 * @return string
	 */
	public function get_php_version_for_app( $app_id ) {

		// Check to see if the version is stamped on the record.
		$php_version = sstk_maybe_unserialize( get_post_meta( $app_id, 'wpapp_php_version', true ) );

		// Use the supported default when the stored version is missing or unsupported.
		if ( ! in_array( (string) $php_version, array( '8.3', '8.4', '8.5' ), true ) ) {
			$php_version = $this->get_wpapp_default_php_version();
		}

		return $php_version;

	}

	/**
	 * Set the PHP version for app.
	 *
	 * @param int    $app_id post id of app.
	 * @param string $php_version new php version (8.3, 8.4 or 8.5).
	 */
	public function set_php_version_for_app( $app_id, $php_version ) {
		if ( ! in_array( (string) $php_version, array( '8.3', '8.4', '8.5' ), true ) ) {
			return false;
		}

		return update_post_meta( $app_id, 'wpapp_php_version', $php_version );
	}

	/**
	 * Get an array of PHP versions that are valid and active on the server.
	 *
	 * @param int|string $id The post id of the server.
	 *
	 * @return array Associated array of php versions.
	 */
	public function get_php_versions( $id ) {
		$php_versions = array(
			'8.3' => '8.3',
			'8.4' => '8.4',
			'8.5' => '8.5',
		);

		return apply_filters( "sstk_{$this->get_app_name()}_php_versions", $php_versions, $id );
	}

	/**
	 * Sets the page cache status meta on an app record..
	 *
	 * @param int    $app_id ID of app.
	 * @param string $status The status to set - should be 'on' or 'off'.
	 *
	 * @return boolean|array|object
	 */
	public function set_page_cache_status( $app_id, $status ) {
		return update_post_meta( $app_id, 'wpapp_pagecache_status', $status );
	}


	/**
	 * Returns whether a site is a staging site.
	 *
	 * @param int $app_id ID of app being interrogated.
	 *
	 * @return boolean
	 */
	public function is_staging_site( $app_id ) {

		return SSTK()->is_staging_site( $app_id );

	}

	/**
	 * Returns the domain of the associated staging site.
	 *
	 * @param int $app_id ID of app being interrogated.
	 *
	 * @return string The staging domain name if it exists.
	 */
	public function get_companion_staging_site_domain( $app_id ) {

		$staging_domain = (string) get_post_meta( $app_id, 'wpapp_staging_domain', true );

		return $staging_domain;

	}

	/**
	 * Returns the post id of the domain of the associated staging site.
	 *
	 * @param int $app_id ID of app being interrogated.
	 *
	 * @return int The post id of the staging domain if it exists.
	 */
	public function get_companion_staging_site_id( $app_id ) {

		$staging_site_id = (int) get_post_meta( $app_id, 'wpapp_staging_domain_id', true );

		return $staging_site_id;

	}

	/**
	 * Returns the domain of the associated live site for a staging site.
	 *
	 * @param int $app_id ID of app being interrogated.
	 *
	 * @return string The live domain name if it exists.
	 */
	public function get_live_domain_for_staging_site( $app_id ) {

		$live_domain = (string) get_post_meta( $app_id, 'wpapp_cloned_from', true );

		return $live_domain;

	}

	/**
	 * Returns the post id of the live domain of associated with a staging site.
	 *
	 * @param int $app_id ID of app being interrogated.
	 *
	 * @return int The post id for the live site related to the staging site.
	 */
	public function get_live_id_for_staging_site( $app_id ) {

		$live_id = (int) get_post_meta( $app_id, 'wpapp_cloned_from_id', true );

		return $live_id;

	}


	/**
	 * Get a formatted link to the front-end of the site
	 *
	 * @param int    $app_id is the post id of the app record we're asking about.
	 * @param string $label Label for link (optional).
	 * @param bool   $icon Whether to style with just an icon instead of text (optional).
	 *
	 * @return string
	 */
	public function get_formatted_site_link( $app_id, $label = '', $icon = false ) {

		// get ssl status first.
		$ssl = $this->get_site_local_ssl_status( $app_id );

		// get domain name.
		$domain = $this->get_domain_name( $app_id );

		// Label.
		if ( empty( $label ) ) {
			$label = __( 'View site', 'simple-stack' );
		}

		if ( true == $ssl ) {
			$url_site = 'https://' . $domain;
		} else {
			$url_site = 'http://' . $domain;
		}
		if ( true === $icon ) {
			return sprintf( '<a href = "%s" target="_blank">' . '<i class="fa-duotone fa-sidebar"></i>' . '</a>', $url_site );
		} else {
			return sprintf( '<a href = "%s" target="_blank">' . $label . '</a>', $url_site );
		}

	}

	/**
	 * Sets the status of SSL metas and, if necessary, http2 as well.
	 *
	 * @param int    $app_id is the post id of the app record we're working with.
	 * @param string $ssl_status Should be 'on' or 'off'.
	 *
	 * @return void
	 */
	public function set_ssl_status( $app_id, $ssl_status ) {

		$webserver_type = $this->get_web_server_type( $app_id );

		update_post_meta( $app_id, 'wpapp_ssl_status', $ssl_status );

		// OpenLiteSpeed enables HTTP/2, HTTP/3 and SPDY with SSL.
		update_post_meta( $app_id, 'wpapp_ssl_http2_status', 'ols' === $webserver_type && 'on' === $ssl_status ? 'on' : 'off' );

	}

	/**
	 * Is the site using a remote database?
	 *
	 * @param int $app_id is the post id of the app record we're working with.
	 *
	 * @return string 'yes', 'no'.
	 */
	public function is_remote_db( $app_id ) {

		$is_remote_database = (string) get_post_meta( $app_id, 'wpapp_is_remote_database', true );

		if ( empty( $is_remote_database ) ) {
			$is_remote_database = 'no';
		}

		return $is_remote_database;

	}

	/**
	 * Mark the site as having a remote db.
	 *
	 * @param int $app_id is the post id of the app record we're working with.
	 */
	public function enable_remote_db_flag( $app_id ) {

		update_post_meta( $app_id, 'wpapp_is_remote_database', 'yes' );

	}

	/**
	 * Mark the site as having a local db.
	 *
	 * @param int $app_id is the post id of the app record we're working with.
	 */
	public function disable_remote_db_flag( $app_id ) {

		update_post_meta( $app_id, 'wpapp_is_remote_database', 'no' );

	}

	/**
	 * Set the wp-login page http auth status
	 *
	 * @param int    $app_id is the post id of the app record we're working with.
	 * @param string $status Should be 'no' or 'yes'.
	 */
	public function set_wplogin_http_auth_status( $app_id, $status ) {

		update_post_meta( $app_id, 'wpapp_wplogin_basic_auth_status', $status );

	}

	/**
	 * Is the site wp-login page protected with http auth?
	 *
	 * @param int $app_id is the post id of the app record we're working with.
	 *
	 * @return string 'yes', 'no'.
	 */
	public function get_wplogin_http_auth_status( $app_id ) {

		$wplogin_basic_auth_status = get_post_meta( $app_id, 'wpapp_wplogin_basic_auth_status', true );

		if ( empty( $wplogin_basic_auth_status ) ) {
			$wplogin_basic_auth_status = 'off';
		}

		return $wplogin_basic_auth_status;

	}

	/**
	 * Set the http auth status for the site.
	 *
	 * @param int    $app_id is the post id of the app record we're working with.
	 * @param string $status Should be 'no' or 'yes'.
	 */
	public function set_site_http_auth_status( $app_id, $status ) {

		update_post_meta( $app_id, 'wpapp_basic_auth_status', $status );

	}

	/**
	 * Is the site protected by http auth?
	 *
	 * @param int $app_id is the post id of the app record we're working with.
	 *
	 * @return string 'yes', 'no'.
	 */
	public function get_site_http_auth_status( $app_id ) {

		$basic_auth_status = get_post_meta( $app_id, 'wpapp_basic_auth_status', true );

		if ( empty( $basic_auth_status ) ) {
			$basic_auth_status = 'off';
		}

		return $basic_auth_status;

	}


	/**
	 * Return a requested provider object
	 *
	 * @param string $provider name of provider.
	 *
	 * @return CLOUD_PROVIDER_API_Provider_{provider}()
	 */
	public function api( $provider ) {

		return SSTK()->get_provider_api( $provider );

	}

	/**
	 * Set the domain name used for a wp app instance.
	 * This can be used when the user changes their domain.
	 *
	 * @param int    $app_id post id of app record.
	 * @param string $new_domain new domain to update the record with.
	 *
	 * @return int|bool the result of the update_post_meta call - see https://developer.wordpress.org/reference/functions/update_post_meta/
	 */
	public function set_domain_name( $app_id, $new_domain ) {
		return update_post_meta( $app_id, 'wpapp_domain', $new_domain );
	}

	/**
	 * Get whether git is enabled for a server or site.
	 *
	 * @param int $post_id The post id of the server or site record.
	 *
	 * @return boolean
	 */
	public function get_git_status( $post_id ) {

		if ( 'sstk_app_server' === (string) get_post_type( $post_id ) ) {
			return (bool) get_post_meta( $post_id, 'sstk_wpapp_git_status', true );
		}

		if ( 'sstk_app' === (string) get_post_type( $post_id ) ) {
			return (bool) get_post_meta( $post_id, 'wpapp_git_status', true );
		}

		return false;
	}

	/**
	 * Set the meta that contains the git status for the server or site.
	 *
	 * @param int      $post_id The post id of the server or site record.
	 * @param int|bool $git_status The git status 1/true 0/false.
	 *
	 * @return int|bool|string
	 */
	public function set_git_status( $post_id, $git_status ) {

		if ( 'sstk_app_server' == get_post_type( $post_id ) ) {
			return update_post_meta( $post_id, 'sstk_wpapp_git_status', (int) $git_status );
		}

		if ( 'sstk_app' == get_post_type( $post_id ) ) {
			return update_post_meta( $post_id, 'wpapp_git_status', (int) $git_status );
		}

	}

	/**
	 * Get the branch name that we think the site is on.
	 *
	 * @param int $app_id The post id of the server or site record.
	 *
	 * @return string
	 */
	public function get_git_branch( $app_id ) {

		return (string) get_post_meta( $app_id, 'wpapp_git_branch', true );

	}

	/**
	 * Set the branch name that we think the site is on.
	 *
	 * @param int    $app_id The post id of the server or site record.
	 * @param string $branch The branch name to set.
	 */
	public function set_git_branch( $app_id, $branch ) {

		return update_post_meta( $app_id, 'wpapp_git_branch', $branch );

	}


	/**
	 * Add additional parameters to the localized script sent to the APPS cpt screen.
	 *
	 * These parameters are intended to be common to all JS scripts for the apps screen.
	 * Unique parameters per script should probably not be added here.
	 *
	 * Filter hook: sstk_app_script_args
	 *
	 * @param array  $args args.
	 * @param string $script script.
	 *
	 * These values will be used in Javascript as part of the 'params' array.
	 * Example, action will be referenced as params.action.
	 */
	public function add_script_args_app( $args, $script ) {
		$args['action']                  = "sstk_app_{$this->get_app_name()}";
		$args['redirect']                = admin_url( '/edit.php?post_type=sstk_app' );
		$args['refresh_seconds']         = 30;  // No point in trying to go too low since the cron process that collects and stores the logs only run once per minute.
		$args['l10n']                    = array(
			'done'                     => __( 'The command has completed.', 'simple-stack' ),
			'checking_for_logs'        => __( 'Checking server for updated progress information...', 'simple-stack' ),
			'no_progress_data_in_logs' => __( 'Last progress check resulted in no new data. The operation seems to still be in progress. However, if you see this error more than 10 times or so, it might be because the connection to the WordPress server has been severed.', 'simple-stack' ),
		);
		$args['user_can_manage_servers'] = current_user_can( 'sstk_manage_servers' );
		return $args;
	}


	/**
	 * Add additional parameters to the localized script sent to the SERVER cpt screen.
	 *
	 * These parameters are intended to be common to all JS scripts for the SERVER screen.
	 * Unique parameters per script should probably not be added here.
	 *
	 * @param array  $args args.
	 * @param string $script script.
	 *
	 * These values will be used in Javascript as part of the 'params' array.
	 * Example, action will be referenced as params.action.
	 */
	public function add_script_args_server( $args, $script ) {
		$this->add_provider_support();
		$args['action']                     = "sstk_{$this->get_app_name()}";
		$args['refresh_seconds']            = 5;
		$args['l10n']                       = array(
			'add_new' => __( 'Deploy A New WordPress Server', 'simple-stack' ),
			'done'    => __( 'The command has completed.', 'simple-stack' ),
		);
		$args['user_can_provision_servers'] = apply_filters( "sstk_{$this->get_app_name()}_show_deploy_server_button", current_user_can( 'sstk_provision_servers' ) );  // Filter: sstk_wordpress-app_show_deploy_server_button.
		return $args;
	}


	/**
	 * Single entry point for all ajax actions for app.
	 */
	public function ajax_app() {

		/* Check nonce */
		check_ajax_referer( 'sstk-app', 'nonce' );

		/* Get action requested */
		$action = sanitize_text_field( filter_input( INPUT_POST, '_action', FILTER_UNSAFE_RAW ) );
		if ( empty( $action ) ) {
			$action = sanitize_text_field( filter_input( INPUT_GET, '_action', FILTER_UNSAFE_RAW ) );
		}

		/* Get app id */
		$id = filter_input( INPUT_POST, 'id', FILTER_VALIDATE_INT );

		/* Make sure user can at least view the app */
		if ( ! $this->sstk_user_can_view_wp_app( $id ) ) {
			echo wp_send_json_error( array( 'msg' => __( 'You are not allowed to perform this operation on this app.', 'simple-stack' ) ) );
			wp_die();
		}

		/* Initialize var that will be sent back to js requester. */
		$result = null;
		$msg    = null;

		switch ( $action ) {
			/* The log console template was retired with the React cutover (Phase 4). */
			case 'fetch-logs-from-db':
				// fetch logs only from the db.
				if ( ! isset( $_POST['params'] ) ) {
					break;
				}
				$id   = sanitize_text_field( $_POST['params']['id'] );
				$name = sanitize_text_field( $_POST['params']['name'] );
				$old  = sanitize_text_field( $_POST['params']['old'] );

				// do not make this === as we are sending a boolean.
				$done = ( $old && $old == 'true' ) || $this->is_command_done( $id, $name );

				$logs = $this->get_app_command_logs( $id, $name );
				// when the logs are unavailable.
				if ( is_wp_error( $logs ) ) {
					break;
				}
				$result = array(
					'logs' => nl2br( $logs ),
					'done' => $done,
				);
				break;
			default:
				$result = apply_filters( "sstk_app_{$this->get_app_name()}_tab_action", $result, $action, $id );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'msg' => $result->get_error_code() ) );
		}

		wp_send_json_success(
			array(
				'msg'    => $msg,
				'result' => $result,
			)
		);
	}


	/**
	 * Validates the server and returns the server instance details.
	 *
	 * @param int $server_id server id.
	 */
	public function get_server_instance_details( $server_id ) {
		if ( ! $server_id ) {
			return new \WP_Error( __( 'Invalid server ID', 'simple-stack' ) );
		}

		// Verify the cpt type.
		$post = get_post( $server_id );
		if ( 'sstk_app_server' !== $post->post_type ) {
			return new \WP_Error( __( 'Invalid server', 'simple-stack' ) );
		}

		$instance = $this->get_instance_details( $server_id );

		return $instance;
	}


	/**
	 * Validates the app and returns the server instance details.
	 *
	 * @param int $id id.
	 */
	public function get_app_instance_details( $id ) {
		if ( ! $id ) {
			return new \WP_Error( __( 'Invalid site ID', 'simple-stack' ) );
		}

		// Verify the app type.
		$post = get_post( $id );
		if ( 'sstk_app' !== $post->post_type || $this->get_app_name() !== get_post_meta( $id, 'app_type', true ) ) {
			return new \WP_Error( __( 'Invalid app', 'simple-stack' ) );
		}

		// @TODO: replace the get_post_meta call below with this line for standardization purposes: $this->get_server_id_by_app_id( $id ) ;
		$server_id = get_post_meta( $id, 'parent_post_id', true );
		if ( ! $server_id ) {
			return new \WP_Error( __( 'No server found', 'simple-stack' ) );
		}

		$instance = $this->get_instance_details( $server_id );

		return $instance;
	}


	/**
	 * Return create server popup view
	 *
	 * @param string $view String indicating whether we're viewing popup from admin or frontend (public).
	 *
	 * @return void|string
	 */
	public function ajax_server_handle_create_popup( $view = 'admin' ) {

		/* Check permissions */
		if ( ! current_user_can( 'sstk_provision_servers' ) ) {
			$invalid_msg = __( 'You don\'t have access to provision a server. Perhaps you\'re not logged in?', 'simple-stack' );
			if ( $view == 'public' ) {
				echo $invalid_msg;
			} else {
				echo wp_send_json_error( array( 'msg' => $invalid_msg ) );
			}
			return;
		}

		/* Simple Stack only supports the locked provisioning script version. */
		$scripts_version = $this->get_configured_scripts_version();

		/* Get list of regions */
		$provider_regions = $this->add_provider_support();
		$provider_regions = apply_filters( "sstk_{$this->get_app_name()}_provider_regions_create_server_popup", $provider_regions );

		/* Get the list of providers - we'll need it in the popup area */
		$providers = $this->get_active_providers();
		$providers = apply_filters( "sstk_{$this->get_app_name()}_providers_create_server_popup", $providers );

		/* Get list of OSes */
		$oslist = SSTK()->get_os_list();
		$oslist = apply_filters( "sstk_{$this->get_app_name()}_oslist_create_server_popup", $oslist );

		/* Get list of webservers */
		$webserver_list = SSTK()->get_webserver_list();
		$webserver_list = apply_filters( "sstk_{$this->get_app_name()}_webserver_list_create_server_popup", $webserver_list );

		/* Include the popup file */
		include apply_filters( "sstk_{$this->get_app_name()}_create_popup", SSTK_PATH . 'includes/core/apps/wordpress-app/templates/create-popup.php' );
	}

	/**
	 * Single entry point for all ajax actions for server.
	 */
	public function ajax_server() {

		check_ajax_referer( 'sstk-server', 'nonce' );

		$action = sanitize_text_field( $_REQUEST['_action'] );

		$result = null;
		$msg    = null;

		switch ( $action ) {
			/*
			 * The admin popups (install-app, create server, log console) were rendered by
			 * templates/ and driven by admin-ajax from the legacy jQuery bundle. Phase 4
			 * replaced them with the React routes, so those actions no longer exist here.
			 * The public (unauthenticated) deploy form in SSTK_WORDPRESS_APP_PUBLIC still
			 * uses ajax_server_handle_create_popup() directly and is unaffected.
			 */
			/* Called when the admin pushes the install button on the server popup to create/deploy a new server */
			case 'create':
				/* Check permissions */
				if ( ! current_user_can( 'sstk_provision_servers' ) ) {
					$invalid_msg = __( 'You don\'t have access to provision a server. Perhaps you\'re not logged in?', 'simple-stack' );
					wp_send_json_error( array( 'msg' => $invalid_msg ) );
					break;
				}

				// Get arguments from form.
				$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_REQUEST['params'] ) ) );

				// Extract and sanitize some data from the args array.
				$webserver = '';
				if ( ! empty( $args['webserver-type'] ) ) {
					$webserver = sanitize_text_field( $args['webserver-type'] );
				}
				if ( empty( $webserver ) ) {
					$webserver = 'ols';
				}
				$os       = sanitize_text_field( $args['os'] );
				$name     = sanitize_text_field( $args['name'] );
				$provider = sanitize_text_field( $args['provider'] );
				if ( isset( $args['invalid_message'] ) ) {
					$invalid_msg = sanitize_text_field( $args['invalid_message'] );
				} else {
					$invalid_msg = __( 'If you are seeing this message, something went very wrong at the start of the create a new server process. However, we are unable to be more specific at this time about its root cause.', 'simple-stack' );
				}

				// Validate the server name and return right away if invalid format.
				$name_pattern = '/^[a-z0-9-_]+$/i';

				if ( false !== strpos( mb_strtolower( $args['provider'] ), 'hivelocity' ) ) {
					// special check for hivelocity server names - periods are allowed because their names must be in xxx.yyy.zzz format
					// @TODO: We need to have a hook for validation and move this check into the HIVELOCITY plugin.
					$name_pattern = '/^[a-z0-9-_.]+$/i';
				}

				if ( ! empty( $name ) && ! preg_match( $name_pattern, $name ) ) {
					wp_send_json_error( array( 'msg' => $invalid_msg ) );
					break;
				}
				// End validate the server name.

				/* Validate the os */
				$oslist = SSTK()->get_os_list();
				if ( ! isset( $oslist[ $os ] ) ) {
					wp_send_json_error( array( 'msg' => __( 'Invalid OS - security issue?', 'simple-stack' ) ) );
					break;
				}

				/* Validate the webserver type */
				if ( ! empty( $webserver ) ) {
					$webserver_list = SSTK()->get_webserver_list();
					if ( ! isset( $webserver_list[ $webserver ] ) ) {
						wp_send_json_error( array( 'msg' => __( 'Invalid Webserver type - security issue?', 'simple-stack' ) ) );
						break;
					}
				}

				/* Everything ok so far - create the server instance. */
				$result = $this->create_instance();

				/* Check and handle errors if server instance was not created correctly. If it was created correctly, set the data that is to be sent back to front-end jscript. */
				if ( ( ! is_wp_error( $result ) ) && $result ) {
					$text_msg          = apply_filters( "sstk_{$this->get_app_name()}_pre_create_server_message", __( 'Waiting for the server to startup...<br />We recommend that you do NOT exit this screen until you see a pop-up indicating that the installation is complete.<br />You can expect this process to take at least 10 minutes or more. Your first feedback message after this can take up to 5 minutes so please be patient.<br />If you do exit this screen, the process will continue in the background - you can check the COMMAND & SSH logs for progress reports.', 'simple-stack' ), $args );
					$text_msg          = apply_filters( "sstk_{$this->get_app_name()}_{$provider}_pre_create_server_message", $text_msg, $args );
					$msg               = '<span class="sstk_pre_install_text">' . $text_msg . '</span>';
					$msg              .= sstk_get_loading_svg_code(); // add in the loading icon.
					$result['command'] = array( 'name' => 'prepare_server' );
				} else {
					$err_msg = '';
					if ( is_wp_error( $result ) ) {
						$err_msg = $result->get_error_message();
					}
					/* Translators: %1: Error message. */
					$msg = sprintf( __( 'Unfortunately we could not start deploying this server - most likely because of an error from the provider api. <br />Please contact support or you can check the COMMAND & SSH logs for errors.<br />  You can close this screen and then retry the operation.<br />%s', 'simple-stack' ), $err_msg );
					$msg = apply_filters( 'sstk_wordpress-app-server_deployment_error', $msg );
					$msg = '<span class="sstk_pre_install_text_error">' . $msg . '</span>';
				}

				break;

			/* Collect one or more log files based on parameters passed in $_POST */
			case 'logs':
				if ( ! isset( $_POST['params'] ) ) {
					break;
				}
				$id   = sanitize_text_field( $_POST['params']['id'] );
				$name = sanitize_text_field( $_POST['params']['name'] );
				$old  = sanitize_text_field( $_POST['params']['old'] );
				$done = false;
				// do not make this === as we are sending a boolean.
				if ( $old && $old == 'true' ) {
					$logs = $this->get_old_command_logs( $id, $name );
					$done = true;
				} else {
					$logs = $this->get_command_logs( $id, $name );
					// when the logs are unavailable.
					if ( is_wp_error( $logs ) ) {
						break;
					}
					if ( $this->is_command_done( $id, $name ) ) {
						$done = true;
					}
				}
				$result = array(
					'logs' => nl2br( $logs ),
					'done' => $done,
				);
				break;

			/* Called when the installation of an app is about to start - after the user has pushed the install button */
			case 'install-app':
				// Verify that the user is allowed to add a site.
				$server_id   = sanitize_text_field( $_REQUEST['id'] );
				$user_id     = get_current_user_id();
				$post_author = get_post( $server_id )->post_author;
				if ( ! sstk_user_can( $user_id, 'add_app_wpapp', $server_id ) && $post_author != $user_id ) {
					$msg = __( 'You don\'t have permission to install WordPress on this server.', 'simple-stack' );
					break;
				}

				// Grab some data, so we can validate it...
				$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_REQUEST['params'] ) ) );

				// Make sure that we get an unsanitized version of the array.
				// We need this to get the password field.
				// Text sanitation will remove certain special chars which are valid for password fields.
				// So we cannot use the sanitized password field.
				$args_unsanitized = wp_parse_args( wp_unslash( $_REQUEST['params'] ) );

				// Make sure we have data for all fields. Do not do a check for wp_locale though because, if it's blank, we'll default it 'en_US' later when the installation starts.
				if ( empty( $args['wp_domain'] ) || empty( $args['wp_user'] ) || empty( $args_unsanitized['wp_password'] ) || empty( $args['wp_email'] ) || empty( $args['wp_version'] ) || empty( $args['sstk_app_type'] ) ) {
					$msg = __( 'Please fill out all the requested data - we cannot create a site without all the requested data!  Please close this screen and try again.', 'simple-stack' );
					break;
				}

				// Prepare for the installation (which will escape the chars in the unsanitized password field).
				$result = $this->install_wp_validate();

				if ( ! is_wp_error( $result ) ) {
					$msg               = '<span class="sstk_pre_install_text">' . __( 'Waiting for the installation to begin...<br />Do not exit this screen until you see a popup indicating that the installation is complete.', 'simple-stack' ) . '</span>';
					$msg              .= sstk_get_loading_svg_code(); // add in the loading icon.
					$result['post_id'] = sanitize_text_field( $_REQUEST['id'] );
					$result['command'] = array( 'name' => $result['command'] );
				}
				break;

			/* Called when the admin clicks on a link to get the true status of a server.  This link is generally in the server list. */
			case 'update-status':
				/* Check for missing parameters */
				if ( ! isset( $_POST['params']['id'] ) ) {
					wp_send_json_error( array( 'msg' => __( 'Missing required parameter.', 'simple-stack' ) ) );
					break;
				}

				/* Make sure we have a valid post type */
				$id = sanitize_text_field( $_POST['params']['id'] ); // server_id.
				if ( get_post_type( $id ) != 'sstk_app_server' ) {
					wp_send_json_error( array( 'msg' => __( 'Invalid post type.', 'simple-stack' ) ) );
					break;
				}

				// Verify that the user is allowed to access the server .
				if ( ! $this->sstk_user_can_view_wp_server( $id ) ) {
					wp_send_json_error( array( 'msg' => __( 'You are not allowed to perform this operation on this server.', 'simple-stack' ) ) );
					break;
				}

				$provider    = SSTK_SERVER()->get_server_provider( $id ); // provider.
				$instance_id = SSTK_SERVER()->get_server_provider_instance_id( $id ); // instance id so the provider knows which one we're talking about.

				$details = SSTK()->get_provider_api( $provider )->call( 'details', array( 'id' => $instance_id ) );

				if ( ! is_wp_error( $details ) ) {
					$done = true;
					update_post_meta( $id, 'sstk_server_current_state', $details['status'] );
					$msg    = __( 'Status sync completed.', 'simple-stack' );
					$result = array(
						'status' => $details['status'],
						'done'   => $done,
					);
				} else {
					$result = $details;
				}
				break;

			/* Delete server records */
			case 'delete-server-record':
				// If for some reason this is called via CRON, do nothing and return.
				// We're not doing anything to delete servers via cron right now.
				// But we might later so adding this check now since we have it in the sstk_app_delete_post() function.
				if ( true === wp_doing_cron() || true === sstk_is_doing_cron() ) {
					$result = array(
						'status' => __( 'You cannot delete a server record inside of a CRON process.', 'simple-stack' ),
						'done'   => false,
					);
					break;
				}
				// Which server record are we deleting?.
				$delete_server_id = sanitize_text_field( wp_unslash( $_POST['server_id'] ) );
				$user_id          = (int) get_current_user_id();
				$post_author      = (int) get_post( $id )->post_author;
				/**
				 * We're not deleting anything if the current user does not have permission to delete the server record
				 * or if the current user id does not match the server author id.
				 * Note: Changes to this permission logic might also need to be done in function
				 * sstk_app_server_delete_post() located in file class-sstk-posts-app-server.php.
				 */
				if ( ! sstk_is_admin() ) {
					if ( ! sstk_user_can( $user_id, 'delete_server', $delete_server_id ) && $post_author !== $user_id ) {
						$result = array(
							'status' => __( "Sorry! you don't have permission to delete a server record.", 'simple-stack' ),
							'done'   => false,
						);
						break;
					}
				}
				// If we got here, ok to delete the server record and related child posts.
				$deleted_server_record = wp_delete_post( $delete_server_id );
				if ( $deleted_server_record ) {
					sstk_delete_child_posts( 'sstk_app', $delete_server_id );
					$result = array(
						'status' => __( 'Server record successfully deleted.', 'simple-stack' ),
						'done'   => true,
					);
				} else {
					$result = array(
						'status' => __( 'Failed! something went wrong during delete server record', 'simple-stack' ),
						'done'   => false,
					);
				}
				break;
			/* Every other request is sent here - which is most of them  */
			default:
				$additional = array();
				if ( isset( $_POST['additional'] ) ) {
					$additional = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['additional'] ) ) );
				}
				$result = $this->do_instance_action( sanitize_text_field( $_POST['_id'] ), sanitize_text_field( $_POST['_action'] ), $additional );
		}

		if ( is_wp_error( $result ) ) {
			if ( ! empty( $msg ) ) {
				wp_send_json_error( array( 'msg' => $msg . '<br />' . $result->get_error_code() ) );
			} else {
				wp_send_json_error( array( 'msg' => $result->get_error_code() ) );
			}
		}

		wp_send_json_success(
			array(
				'msg'    => $msg,
				'result' => $result,
			)
		);
	}


	/**
	 * Generates the instances' details that are required by other functions.
	 *
	 * @TODO: This probably should be moved to the class-sstk-app.php file.
	 * If we do that, we'll need to rethink the location parameter of how
	 * we get the custom fields list.
	 *
	 * @param int $id id.
	 */
	public function get_instance_details( $id ) {
		$attributes = array(
			'post_id' => $id,
		);

		/**
		 * Get list of custom fields for the server.
		 */

		/* @TODO: We're running this through a filter and requesting a specific location but not sure that is the right thing to do. */
		$custom_fields = apply_filters( "sstk_{$this->get_app_name()}_create_wp_server_parms_custom_fields", SSTK_CUSTOM_FIELDS()->get_fields_for_location( 'wordpress-app-new-server-popup' ), $attributes, $attributes );

		/* Get data from server post */
		$all_meta = get_post_meta( $id );
		foreach ( $all_meta as $key => $value ) {
			if ( 'sstk_server_app_post_id' == $key ) {
				continue;  // this key, if present, should not be added to the array since it shouldn't even be in the server cpt in the first place. But it might get there accidentally on certain operations.
			}

			// Any field that starts with "sstk_server_" goes into the array.
			if ( strpos( $key, 'sstk_server_' ) === 0 ) {
				$value = sstk_maybe_unserialize( $value );
				$attributes[ str_replace( 'sstk_server_', '', $key ) ] = is_array( $value ) && count( $value ) === 1 ? $value[0] : $value;
			}

			// Any custom field with data goes into the array regardless of it's name prefix.
			if ( isset( $custom_fields[ $key ] ) ) {
				$attributes[ $key ] = is_array( $value ) && count( $value ) === 1 ? $value[0] : $value;
			}
		}

		$details = SSTK()->get_provider_api( $attributes['provider'] )->call( 'details', array( 'id' => $attributes['provider_instance_id'] ) );

		// Merge post ids and server details into a single array.
		$attributes = array_merge( $attributes, $details );

		return $attributes;
	}


	/**
	 * Validate that install WP is good to go on the basis of the inputs.
	 *
	 * @param array $args args.
	 */
	public function install_wp_validate( $args = null ) {

		// Get WP data to process.
		if ( empty( $args ) ) {
			// data is coming in via $_REQUEST which means that the site is being provisioned via wp-admin or a UI.
			$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_REQUEST['params'] ) ) );
			$id   = sanitize_text_field( $_REQUEST['id'] );  // Post ID of the server where the wp app is being installed.
		} else {
			// Data is being passed in directly by the REST API or another internal caller.
			$id = $args['id'];
		}

		$app_type = $args['sstk_app_type'];
		// @codingStandardsIgnoreLine - added to ignore the misspelling in 'wordpress' below when linting with PHPcs.
		if ( $app_type !== 'wordpress' ) {
			return false;
		}

		// Get domain and check to make sure it's not a duplicate or otherwise in use in SSTK...
		$domain = sanitize_text_field( $args['wp_domain'] );
		if ( ! defined( 'SSTK_ALLOW_DUPLICATE_DOMAINS' ) || ( defined( 'SSTK_ALLOW_DUPLICATE_DOMAINS' ) && ! SSTK_ALLOW_DUPLICATE_DOMAINS ) ) {
			$existing_app_id = $this->get_app_id_by_domain_name( $domain );
			if ( $existing_app_id > 0 ) {
				return new \WP_Error( __( 'This domain already exists in our system.', 'simple-stack' ) );
			}
		}

		// Set site package id if one is passed in.
		$wp_site_package = 0;
		if ( ! empty( $args['wp_site_package'] ) ) {
			$wp_site_package = $args['wp_site_package'];
		}

		// Get other fields needed to provision the site.
		$wp_user     = sanitize_user( sanitize_text_field( $args['wp_user'] ) );
		$wp_password = $args['wp_password'];  // Note that we are NOT sanitizing the password field.  We'll escape every non-alpha-numeric character later before passing to bash.
		$wp_email    = sanitize_email( $args['wp_email'] );
		$wp_version  = sanitize_text_field( $args['wp_version'] );
		$wp_locale   = sanitize_text_field( $args['wp_locale'] );
		if ( empty( $wp_locale ) ) {
			$wp_locale = 'en_US';
		}

		// Make sure our password does not contain any invalid characters that would cause BASH to throw up.
		if ( false !== strpbrk( $args['wp_password'], "\;/|<>&()`'" ) || false !== strpbrk( $args['wp_password'], '"' ) || false !== strpbrk( $args['wp_password'], ' ' ) ) {
			return new \WP_Error( __( 'The password for the site contains invalid characters.', 'simple-stack' ) );
		}
		// Make sure our email address field does not contain any invalid characters that would cause BASH or WP to throw up.  If it does, stop the process and return.
		if ( $wp_email !== $args['wp_email'] ) {
			// This comparison works for this purpose because we're comparing the sanitized email value to the orignal value.  If there are issues, the two will not be the same.
			return new \WP_Error( __( 'The email address for the site contains invalid characters.', 'simple-stack' ) );
		}
		// Make sure our user name field does not contain any invalid characters that would cause BASH or WP to throw up.  If it does, stop the process and return.
		if ( false === validate_username( $args['wp_user'] ) ) {
			return new \WP_Error( __( 'The user name for the site contains invalid characters.', 'simple-stack' ) );
		}

		// Escape special chars in password with backslashes.
		$wp_password_original = $wp_password;
		$wp_password          = sstk_escape_for_bash( $wp_password );

		// remove https/http/www. to make the domain a consistent NAME.TLD and make it lowercase...
		$domain                     = strtolower( sstk_clean_domain( $domain ) );
		$args['wp_domain']          = $domain;
		$args['wp_original_domain'] = $args['wp_domain'];

		$additional = array(
			'domain'               => escapeshellarg( $domain ),
			'wp_user'              => escapeshellarg( $wp_user ),
			'wp_password'          => escapeshellarg( $wp_password ),
			'wp_password_original' => $wp_password_original,
			'wp_email'             => escapeshellarg( $wp_email ),
			'wp_version'           => escapeshellarg( $wp_version ),
			'wp_locale'            => escapeshellarg( $wp_locale ),
			'wp_site_package'      => escapeshellarg( $wp_site_package ),
		);

		// Add custom fields into the $additional array - these fields are from the app-popup in wp-admin...
		$custom_fields = apply_filters( "sstk_{$this->get_app_name()}_install_wp_app_parms_custom_fields", SSTK_CUSTOM_FIELDS()->get_fields_for_location( 'wordpress-app-new-app-popup' ), $args, $additional );
		foreach ( $custom_fields as $field ) {
			if ( isset( $args[ $field['name'] ] ) ) {
				$additional[ $field['name'] ] = escapeshellarg( $args[ $field['name'] ] );
			}
		}

		// Get any post-processing bash script urls from settings. Note that this will not end up in the site's postmeta.
		$additional['post_processing_script_site'] = '';
		if ( $this->sstk_can_user_execute_bash_scripts() ) {
			$post_process_script                       = sstk_get_option( 'sstk_wpapp_custom_script_after_site_create' );
			$additional['post_processing_script_site'] = $post_process_script;
		}

		// Get the secret key manager api key from settings. Note that this will not end up in the site's postmeta.
		$secret_key_manager_api_key               = sstk_get_option( 'sstk_wpapp_custom_script_secrets_manager_api_key' );
		$additional['secret_key_manager_api_key'] = $secret_key_manager_api_key;

		/**
		 * Allow devs to hook into the array to add their own elements for use later - likely to be rarely used given that we now have the custom fields array.
		 * Filter Name: sstk_wordpress-app_install_wp_app_parms.
		 */
		$additional = apply_filters( "sstk_{$this->get_app_name()}_install_wp_app_parms", $additional, $args );

		/**
		 * Allow devs to validate data in a way that can terminate processing.
		 * Filter Name: sstk_wordpress-app_install_wp_app_parms_validate.
		 */
		if ( ! apply_filters( "sstk_{$this->get_app_name()}_install_wp_app_parms_validate", true, $additional, $args ) ) {
			return new \WP_Error( __( 'There are some invalid data in this create site request. Please correct and try again.', 'simple-stack' ) );
		}

		// command length should be <= 42.
		// $command = 'install_wp_' . md5( $domain );.
		$command = 'install_wp_' . (string) time();

		$additional['command'] = $command;

		/* add the APP CPT records first so there's some place to put logs and status info for this site */
		$app_post_id                   = $this->add_wp_app_post( $id, $args, $additional );
		$additional['new_app_post_id'] = $app_post_id;

		update_post_meta( $app_post_id, 'sstk_app_action_status', 'yet-to-be' );
		update_post_meta( $app_post_id, 'sstk_install_wp_command_name', $command );
		update_post_meta( $app_post_id, 'sstk_app_action_args', $additional );

		update_post_meta( $id, "sstk_server_{$this->get_app_name()}_action", 'install-wordpress' );
		update_post_meta( $id, "sstk_server_{$this->get_app_name()}_action_status", 'in-progress' );
		SSTK_SERVER()->add_deferred_action_history( $id, $this->get_app_name() );

		/* Give other addons and developers a chance to update the new app record now that we've added all our preliminary data to it. */
		do_action( "sstk_{$this->get_app_name()}_after_create_post", $app_post_id, $args, $additional );

		return $additional;
	}


	/**
	 * Install WP on the instance on the basis of the inputs.
	 *
	 * @param array $attributes attributes.
	 */
	public function install_wp( $attributes ) {
		$id = $attributes['post_id'];

		// get the app for this post id where the app is in progress.
		$posts = get_posts(
			array(
				'post_type'   => 'sstk_app',
				'post_status' => 'private',
				'numberposts' => 1,
				'meta_query'  => array(
					array(
						'key'   => 'sstk_app_action_status',
						'value' => 'yet-to-be',
					),
					array(
						'key'   => 'parent_post_id',
						'value' => $id,
					),
				),
				'fields'      => 'ids',
			)
		);

		if ( ! $posts ) {
			// @TODO: no app in-progress found for this server? handle this.
			return false;
		}

		$app_post_id = $posts[0];
		$command     = get_post_meta( $app_post_id, 'sstk_install_wp_command_name', true );
		$args        = get_post_meta( $app_post_id, 'sstk_app_action_args', true );

		// Get_post_meta seems to strips slashes which causes an issue with the password field if they contain that and certain other special chars.
		$args['wp_password'] = escapeshellarg( $args['wp_password_original'] );

		// Now merge the arrays.
		$attributes = array_merge( $attributes, $args );

		if ( $this->is_command_done( $id, $command ) ) {
			// Do nothing.
		} elseif ( ! $this->is_command_running( $id, $command ) ) {
			$instance = $this->get_instance_details( $id );
			$run_cmd  = $this->turn_script_into_command( $instance, 'install_wordpress_site.txt', $attributes );

			do_action( 'sstk_log_error', sprintf( 'attempting to run install_wp command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

			if ( ! empty( $run_cmd ) ) {
				/* Now try to create the site */
				$result = $this->execute_ssh(
					'generic',
					$instance,
					array( 'commands' => $run_cmd ),
					function() use ( $instance, $command, $app_post_id ) {
						if ( ! $this->is_command_running( $instance['post_id'], $command ) ) {
							$this->set_command_started( $instance['post_id'], $command );
							// if this is a long running command (which it is not), make sure it does not run again.
							update_post_meta( $app_post_id, 'sstk_app_action_status', 'in-progress' );
						}
					}
				);
				if ( is_wp_error( $result ) ) {
					do_action( 'sstk_log_error', sprintf( 'Unable to run command %s on %s ', $run_cmd, print_r( $instance, true ) ), 'error', __FILE__, __LINE__, $instance, false );

					// if something went wrong, remove the 'temporary' meta so that another attempt will run.
					delete_post_meta( $app_post_id, 'sstk_app_action_status' );
					delete_post_meta( $app_post_id, 'sstk_install_wp_command_name' );
					delete_post_meta( $app_post_id, 'sstk_app_action_args' );

				} else {
					return true;
				}
			}
		}
		return false;

	}


	/**
	 * Add an app record to the sstk_app cpt for the
	 * WP APP being installed.
	 *
	 * This is generally called from the install_wp_validate() method
	 * above.
	 *
	 * @param int   $server_id the post id of the server where the app is being installed.
	 * @param array $args the arguments sent from the JS script that triggered the install_wp_validate() method above.
	 * @param array $additional the arguments that the install_wp_validate() method above is using to install the app on the server.
	 *
	 * @return int|boolean Post if of the app or FALSE if the creation of the CPT records fail.
	 */
	public function add_wp_app_post( $server_id, $args, $additional ) {

		/**
		 * If we still don't have a post author, then check to see if a 'user_id' element is set and use that.
		 */
		if ( empty( $post_author ) ) {
			if ( isset( $args['user_id'] ) ) {
				$post_author = $args['user_id'];
			}
		}

		/**
		 * If we still don't have a post author, then check to see if a 'author_email' element is set and use that.
		 * This element might be set by a call from the REST API but, obviously, can also be set from anywhere.
		 */
		if ( empty( $post_author ) ) {
			if ( isset( $args['author_email'] ) ) {
				$author_email = $args['author_email'];
				if ( ! empty( $author_email ) ) {
					$user = get_user_by( 'email', $author_email );
					if ( ! empty( $user ) ) {
						$post_author = $user->ID;
					}
				}
			}
		}

		/**
		 * If we still don't have an author, set it to the current user.
		 */
		if ( empty( $post_author ) ) {
			$post_author = get_current_user_id();
		}
		if ( empty( $post_author ) ) {
			$post_author = 1;
		}

		/**
		 * Create an app cpt record and add our data fields to it.
		 * $args consist of the following pieces of data that we
		 * will make use of below:
		 * $domain = $args['wp_domain'];
		 * $wp_user = $args['wp_user'];
		 * $wp_password = $args['wp_password'];
		 * $wp_email = $args['wp_email'];
		 * $wp_version = $args['wp_version'];
		 */
		$title       = $args['wp_domain'];
		$app_post_id = SSTK_POSTS_APP()->add_app( $this->get_app_name(), $server_id, $post_author, $title );

		if ( ! is_wp_error( $app_post_id ) && ! empty( $app_post_id ) ) {

			/* Enable app delete protection flag if global option is enabled in settings. */
			if ( sstk_get_option( 'wordpress_app_sites_add_delete_protection' ) ) {
				$this->set_delete_protection_flag( $app_post_id ); // This function is in the ancestor app class (file: class-sstk-app.php).
			}

			/**
			 * Setup an array of fields and loop through them to add them to the sstk_app cpt record using the array_map function.
			 * Note that we are passing in the $args variable to the anonymous function by REFERENCE via a USE parm.
			*/
			$appfields = array( 'domain', 'user', 'email', 'version', 'original_domain', 'site_package' );
			$appfields = apply_filters( "sstk_{$this->get_app_name()}_add_wp_app_post_fields", $appfields );
			$x         = array_map(
				function( $f ) use ( &$args, $app_post_id ) {
					if ( isset( $args[ 'wp_' . $f ] ) ) {
						update_post_meta( $app_post_id, 'wpapp_' . $f, $args[ 'wp_' . $f ] );
						$args['apps']['app'][ 'wpapp_' . $f ] = $args[ 'wp_' . $f ];
					}
				},
				$appfields
			);

			/* Now, lets loop through our custom fields and see if any of them needs to be added to the database. */
			$custom_fields = apply_filters( "sstk_{$this->get_app_name()}_add_wp_app_custom_post_fields", SSTK_CUSTOM_FIELDS()->get_fields_for_location( 'wordpress-app-new-app-popup' ), $args, $additional );
			foreach ( $custom_fields as $field ) {
				if ( isset( $args[ $field['name'] ] ) ) {
					update_post_meta( $app_post_id, $field['name'], $args[ $field['name'] ] );
				} elseif ( isset( $additional[ $field['name'] ] ) ) {
					update_post_meta( $app_post_id, $field['name'], $additional[ $field['name'] ] );
				}
			}

			/* Stamp the PHP version on the app record. */
			$this->set_php_version_for_app( $app_post_id, $this->get_wpapp_default_php_version() );

			/* Add the password field to the CPT separately because it needs to be encrypted */
			update_post_meta( $app_post_id, 'wpapp_password', $this::encrypt( $args['wp_password'] ) );

			// Page caching is enabled by default on OpenLiteSpeed.
			$this->set_page_cache_status( $app_post_id, 'on' );

			// Redis object caching is enabled when the service is installed.
			if ( $this->is_redis_installed( $server_id ) ) {
				$this->set_app_redis_installed_status( $app_post_id, true );
			}

			/* Everything good, return the post id of the new app record */
			return $app_post_id;

		} else {

			return false;

		}

		return false;

	}

	/**
	 * Add temp domain to DNS when WP install is complete.
	 *
	 * Action Hook: sstk_command_wordpress-app_completed_after_cleanup
	 *
	 * @param int    $server_id      post id of server.
	 * @param int    $app_id         post id of wp app.
	 * @param string $name           command name executed for new site.
	 * @param string $base_command   basename of command.
	 */
	public function sstk_wpapp_install_complete( $server_id, $app_id, $name, $base_command ) {

		// If not installing an app, return.
		if ( 'install_wp' <> $base_command ) {
			return;
		}

		$app_post = get_post( $app_id );

		// Bail if not a post object.
		if ( ! $app_post || is_wp_error( $app_post ) ) {
			return;
		}

		// Bail if not a WordPress app...
		if ( 'wordpress-app' <> SSTK_WORDPRESS_APP()->get_app_type( $app_id ) ) {
			return;
		}

		// Get app instance array.
		$instance = SSTK_WORDPRESS_APP()->get_app_instance_details( $app_id );

		// Log what we're doing.
		do_action( 'sstk_log_error', 'Sending request to DNS module to add domain if applicable ' . print_r( $instance, true ), 'trace', __FILE__, __LINE__ );

		// Do the DNS thing.
		$this->handle_temp_dns_for_new_site( $server_id, $app_id );

		// Switch PHP version.
		$this->handle_switch_php_version( $app_id, $instance );

		// Maybe disable page_cache.
		$this->handle_page_cache_for_new_site( $app_id, $instance );

		// Maybe disable redis object cache.
		$this->handle_redis_object_cache_for_new_site( $app_id, $instance );

		// Maybe activate solidwp security.
		$this->handle_solidwp_security_for_new_site( $app_id, $instance );

		// Handle site package rules.
		$this->handle_site_package_rules( $app_id );

	}

	/**
	 * Execute the site package rules for a newly installed site.
	 *
	 * @param int $app_id The post id of the app record.
	 */
	public function handle_site_package_rules( $app_id ) {

		$site_package_id = get_post_meta( $app_id, 'wpapp_site_package', true );

		// Bail if no package id.
		if ( empty( $site_package_id ) ) {
			return;
		}

		// Get the server id from the app_id.
		$server_id = $this->get_server_id_by_app_id( $app_id );

		// Bail if no server id.
		if ( empty( $server_id ) ) {
			return;
		}

		// Get the post record.
		$app_post = get_post( $app_id );

		// Bail if not a post object.
		if ( ! $app_post || is_wp_error( $app_post ) ) {
			return;
		}

		// Bail if not a WordPress app...
		if ( 'wordpress-app' <> $this->get_app_type( $app_id ) ) {
			return;
		}

		// Get the domain.
		$domain = $this->get_domain_name( $app_id, );

		// Bail if we have no domain.
		if ( empty( $domain ) ) {
			return;
		}

		// Set transient to indicate to others that something is still happening.
		$transient_name = 'sstk_site_package_running_' . $app_id;
		set_transient( $transient_name, 'running', 120 );

		// Get the class instance that will allow us to send dynamic commands to the server via ssh.
		$ssh = new SSTK_WORDPRESS_TABS();

		// Add any custom version label to wp-config as well as the tenant site.
		// Will override any version label from template site.
		$version_label = get_post_meta( $site_package_id, 'sstk_site_package_app_version', true );
		if ( ! empty( $version_label ) ) {
			do_action( 'sstk_wordpress-app_do_update_wpconfig_option', $app_id, 'SSTK_VERSION_LABEL', $version_label );
			update_post_meta( $app_id, 'sstk_app_std_site_version_label', $version_label );
		}

		// Push custom wp-config.php data.
		$keypairs = get_post_meta( $site_package_id, 'sstk_wp_config_custom_data', true );
		if ( ! empty( $keypairs ) ) {
			foreach ( $keypairs as $keypair ) {
				switch ( $keypair[1] ) {
					case 'true':
						// Value is 'true' so need to pass boolean with 'raw' parameter to wp-cli.
						do_action( 'sstk_wordpress-app_do_update_wpconfig_option', $app_id, $keypair[0], true, 'yes' );
						break;
					case 'false':
						// Value is 'false' so need to pass boolean with 'raw' parameter to wp-cli.
						do_action( 'sstk_wordpress-app_do_update_wpconfig_option', $app_id, $keypair[0], false, 'yes' );
						break;
					default:
						do_action( 'sstk_wordpress-app_do_update_wpconfig_option', $app_id, $keypair[0], $keypair[1], 'no' );
				}
			}
		}

		// Add custom site metas.
		$keypairs = get_post_meta( $site_package_id, 'sstk_site_package_site_meta', true );
		if ( ! empty( $keypairs ) ) {
			foreach ( $keypairs as $keypair ) {
				update_post_meta( $app_id, $keypair[0], $keypair[1] );
			}
		}

		// Add or update wp options to tenant site.
		$keypairs = get_post_meta( $site_package_id, 'sstk_site_package_tenant_wp_option', true );
		if ( ! empty( $keypairs ) ) {
			foreach ( $keypairs as $keypair ) {
				// Add option.  If it exists already, this might error.
				$command    = sprintf( 'sudo su - "%s" -c "wp --no-color option add %s %s" ', $domain, $keypair[0], $keypair[1] );
				$action     = 'add_custom_wp_option';
				$raw_status = $ssh->submit_generic_server_command( $server_id, $action, $command, true );

				// Update the option in case it already exists.
				$command    = sprintf( 'sudo su - "%s" -c "wp --no-color option update %s %s" ', $domain, $keypair[0], $keypair[1] );
				$action     = 'update_custom_wp_option';
				$raw_status = $ssh->submit_generic_server_command( $server_id, $action, $command, true );
			}
		}

		// Run the package's before script for new sites when enabled.
		if ( SSTK_SITE_PACKAGE()->can_user_execute_bash_scripts() ) {
			$script = get_post_meta( $site_package_id, 'sstk_bash_scripts_new_sites_before', true );
			if ( ! empty( $script ) ) {
				$command  = sprintf( 'export DOMAIN=%s', escapeshellarg( $domain ) ) . ' && ';
				$command .= 'sudo -E wget --no-check-certificate -O sstk_site_package_script_new_site_before.sh "%s" > /dev/null 2>&1 ';
				$command  = sprintf( $command, $script );
				$command .= ' && sudo -E dos2unix sstk_site_package_script_new_site_before.sh > /dev/null 2>&1';
				$command .= ' && echo "Executing Site Package New Site Bash Custom Script..." ';
				$command .= ' && sudo -E bash ./sstk_site_package_script_new_site_before.sh';

				$ssh->submit_generic_server_command( $server_id, 'site_pkg_bash_new_site_package_before', $command, true );
			}
		}

		// Switch PHP version.
		$new_php_version = get_post_meta( $site_package_id, 'sstk_new_php_version', true );
		if ( ! empty( $new_php_version ) ) {
			// What is the version on the current site?
			$current_php_version = $this->get_php_version_for_app( $app_id );
			if ( (string) $new_php_version !== (string) $current_php_version ) {
				// Call the action hook that will switch the php versions.
				do_action( 'sstk_wordpress-app_do_change_php_version', $app_id, $new_php_version );
			}
		}

		// PHP Memory Limit.
		$new_memory_limit = get_post_meta( $site_package_id, 'sstk_php_memory_limit', true );
		if ( ! empty( $new_memory_limit ) ) {
			do_action( 'sstk_wordpress-app_do_change_php_options', $app_id, 'memory_limit', $new_memory_limit . 'M' );
		}

		// Max Execution Time.
		$new_execution_time = get_post_meta( $site_package_id, 'sstk_php_max_execution_time', true );
		if ( ! empty( $new_execution_time ) ) {
			do_action( 'sstk_wordpress-app_do_change_php_options', $app_id, 'max_execution_time', $new_execution_time );
		}

		// Max Execution Time.
		$new_input_vars = get_post_meta( $site_package_id, 'sstk_php_max_input_vars', true );
		if ( ! empty( $new_input_vars ) ) {
			do_action( 'sstk_wordpress-app_do_change_php_options', $app_id, 'max_input_vars', $new_input_vars );
		}

		// Get the list of repo plugins to install and activate.
		$plugins_to_install_activate = get_post_meta( $site_package_id, 'sstk_plugins_to_install_from_repo', true );
		$plugins_to_install_activate = trim( preg_replace( '/\s+/', ' ', $plugins_to_install_activate ) );  // Strip line breaks and replace with space to make a single string of plugins separated by spaces.

		// Install and activate repo plugins.
		if ( ! empty( $plugins_to_install_activate ) ) {
			$command    = sprintf( 'sudo su - "%s" -c "wp --no-color plugin install %s --activate" ', $domain, $plugins_to_install_activate );
			$action     = 'site_pkg_activate_plugins_from_repo';
			$raw_status = $ssh->submit_generic_server_command( $server_id, $action, $command, true );
		}

		// Get the list of custom url plugins to install and activate.
		$plugins_to_install_activate = get_post_meta( $site_package_id, 'sstk_plugins_to_install_from_url', true );
		$plugins_to_install_activate = trim( preg_replace( '/\s+/', ' ', $plugins_to_install_activate ) );

		// Install and activate external/custom url plugins.
		if ( ! empty( $plugins_to_install_activate ) ) {
			$command    = sprintf( 'sudo su - "%s" -c "wp --no-color plugin install %s --activate" ', $domain, $plugins_to_install_activate );
			$action     = 'site_pkg_activate_plugins_external';
			$raw_status = $ssh->submit_generic_server_command( $server_id, $action, $command, true );
		}

		// Get the list of repo themes to install.
		$themes_to_install = get_post_meta( $site_package_id, 'sstk_themes_to_install_from_repo', true );
		$themes_to_install = trim( preg_replace( '/\s+/', ' ', $themes_to_install ) );

		// Install repo themes.
		if ( ! empty( $themes_to_install ) ) {
			$command    = sprintf( 'sudo su - "%s" -c "wp --no-color theme install %s " ', $domain, $themes_to_install );
			$action     = 'site_pkg_install_themes_from_repo';
			$raw_status = $ssh->submit_generic_server_command( $server_id, $action, $command, true );
		}

		// Get the list of external themes to install.
		$themes_to_install = get_post_meta( $site_package_id, 'sstk_themes_to_install_from_url', true );
		$themes_to_install = trim( preg_replace( '/\s+/', ' ', $themes_to_install ) );

		// Install external themes.
		if ( ! empty( $themes_to_install ) ) {
			$command    = sprintf( 'sudo su - "%s" -c "wp --no-color theme install %s " ', $domain, $themes_to_install );
			$action     = 'site_pkg_install_themes_external';
			$raw_status = $ssh->submit_generic_server_command( $server_id, $action, $command, true );
		}

		// Activate theme.
		$theme_to_activate = get_post_meta( $site_package_id, 'sstk_theme_to_activate', true );
		if ( ! empty( $theme_to_activate ) ) {
			$command    = sprintf( 'sudo su - "%s" -c "wp --no-color theme activate %s" ', $domain, $theme_to_activate );
			$action     = 'site_pkg_activate_plugins';
			$raw_status = $ssh->submit_generic_server_command( $server_id, $action, $command, true );
		}

		// Delete local Updraft backups when requested for a new site.
		$delete_updraft = get_post_meta( $site_package_id, 'sstk_site_package_delete_updraft', true );
		if ( true === (bool) $delete_updraft ) {
			$command = sprintf( 'sudo rm /var/www/%s/html/wp-content/updraft/*.zip && sudo rm /var/www/%s/html/wp-content/updraft/*.txt && sudo rm /var/www/%s/html/wp-content/updraft/*.gz && echo "Updraft Folder Contents Deleted." ', $domain, $domain, $domain );
			$ssh->submit_generic_server_command( $server_id, 'site_pkg_delete_updraft_folder_contents', $command, true );
		}

		// Delete debug.log when requested for a new site.
		$delete_debug = get_post_meta( $site_package_id, 'sstk_site_package_delete_debug', true );
		if ( true === (bool) $delete_debug ) {
			$command = sprintf( 'sudo rm /var/www/%s/html/wp-content/debug.log && echo "Debug.log file Deleted." ', $domain );
			$ssh->submit_generic_server_command( $server_id, 'site_pkg_delete_debug_log', $command, true );
		}

		// Activate SolidWP on the site.
		$activate_solidwp_security = get_post_meta( $site_package_id, 'sstk_site_package_activate_solidwp_security', true );
		if ( true === (bool) $activate_solidwp_security ) {
			do_action( 'sstk_wordpress-app_do-activate_solidwp_security_for_site', $app_id, '' );
		}

		// Disable HTTP Authentication.
		$disable_http_auth = get_post_meta( $site_package_id, 'sstk_site_package_disable_http_auth', true );
		if ( true === (bool) $disable_http_auth ) {
			do_action( 'sstk_wordpress-app_do_site_disable_http_auth', $app_id );
		}

		// Apply categories/groups to the new site.
		$groups = get_post_meta( $site_package_id, 'sstk_site_package_apply_categories_new_sites', true );
		if ( ! empty( $groups ) ) {
			wp_set_post_terms( $app_id, $groups, 'sstk_app_group', true );
		}

		// Set the initial expiration.
		$expiration_in_minutes = (int) get_post_meta( $site_package_id, 'sstk_site_package_expire_site_minutes', true );
		if ( ! empty( $expiration_in_minutes ) ) {
			SSTK_APP_EXPIRATION()->set_expiration( $app_id, $expiration_in_minutes );
		}

		// Search and replace here - future use.

		// Crons here - future use.

		// Plugin & Theme updates here - future use.

		// Run the package's after script for new sites when enabled.
		if ( SSTK_SITE_PACKAGE()->can_user_execute_bash_scripts() ) {
			$script = get_post_meta( $site_package_id, 'sstk_bash_scripts_new_sites_after', true );
			if ( ! empty( $script ) ) {
				$command  = sprintf( 'export DOMAIN=%s', escapeshellarg( $domain ) ) . ' && ';
				$command .= 'sudo -E wget --no-check-certificate -O sstk_site_package_script_new_site_after.sh "%s" > /dev/null 2>&1 ';
				$command  = sprintf( $command, $script );
				$command .= ' && sudo -E dos2unix sstk_site_package_script_new_site_after.sh > /dev/null 2>&1';
				$command .= ' && echo "Executing Site Package New Site Bash Custom Script..." ';
				$command .= ' && sudo -E bash ./sstk_site_package_script_new_site_after.sh';

				$ssh->submit_generic_server_command( $server_id, 'site_pkg_bash_new_site_package_after', $command, true );
			}
		}
		// If we get here then it means that we have completed the core site package rules.
		// So flag the record as such.
		update_post_meta( $app_id, 'wpapp_site_package_core_rules_complete', true );

		// Clear 'in-process' transient.
		$transient_name = 'sstk_site_package_running_' . $app_id;
		delete_transient( $transient_name );

	}

	/**
	 * Add temp domain to DNS when WP install is complete.
	 *
	 * Called from function sstk_wpapp_install_complete
	 *
	 * @param int $server_id      post id of server.
	 * @param int $app_id         post id of wp app.
	 */
	public function handle_temp_dns_for_new_site( $server_id, $app_id ) {

		// What's the IP of the server?
		$ipv4 = SSTK_SERVER()->get_ipv4_address( $server_id );

		// What's the IPv6 of the server?
		$ipv6 = SSTK_SERVER()->get_ipv6_address( $server_id );

		// What's the domain of the site?
		$domain = $this->get_domain_name( $app_id );

		// Add the DNS...
		$dns_success = SSTK_DNS()->set_dns_for_domain( $domain, $ipv4, $ipv6 );

		// Attempt to issue SSL..
		if ( true === (bool) $dns_success ) {
			if ( sstk_get_option( 'wordpress_app_auto_issue_ssl' ) ) {
				do_action( 'sstk_wordpress-app_do_toggle_ssl_status_on', $app_id, 'ssl-status' );
			}
		}

		return $dns_success;

	}

	/**
	 * Switch PHP version if necessary after a new site has been installed.
	 *
	 * Called from function sstk_wpapp_install_complete
	 *
	 * @param int   $app_id        post id of app.
	 * @param array $instance      Array passed by calling function containing details of the server and site. It's not used here yet and could be empty.
	 */
	public function handle_switch_php_version( $app_id, $instance ) {

		// What's the default new php version?
		$new_php_default_version = sstk_get_option( 'wordpress_app_sites_set_php_version' );

		// if empty new default php version, bail.
		if ( empty( $new_php_default_version ) ) {
			return true;
		}

		// Bail if the new default php version is the sstk app default.
		if ( (string) $this->get_wpapp_default_php_version() === (string) $new_php_default_version ) {
			return true;
		}

		// Call the action hook that will switch the php versions.
		do_action( 'sstk_wordpress-app_do_change_php_version', $app_id, $new_php_default_version );

		return true;

	}

	/**
	 * Disable page cache when WP install is complete.
	 *
	 * Called from function sstk_wpapp_install_complete
	 *
	 * @param int   $app_id        post id of app.
	 * @param array $instance      Array passed by calling function containing details of the server and site.
	 */
	public function handle_page_cache_for_new_site( $app_id, $instance ) {

		if ( sstk_get_option( 'wordpress_app_sites_disable_page_cache' ) ) {
			$instance['action_hook'] = 'sstk_wordpress-app_pending_log_toggle_page_cache';
			SSTK_POSTS_PENDING_TASKS_LOG()->add_pending_task_log_entry( $app_id, 'disable-page-cache', $app_id, $instance, 'ready', $app_id, __( 'Disable Page Cache For New Site', 'simple-stack' ) );
		}

	}

	/**
	 * Remove redis object cache when WP install is complete.
	 *
	 * Called from function sstk_wpapp_install_complete
	 *
	 * @param int   $app_id        post id of app.
	 * @param array $instance      Array passed by calling function containing details of the server and site.
	 */
	public function handle_redis_object_cache_for_new_site( $app_id, $instance ) {

		if ( sstk_get_option( 'wordpress_app_sites_disable_redis_cache' ) ) {
			$instance['action_hook'] = 'sstk_wordpress-app_pending_log_toggle_redis_object_cache';
			SSTK_POSTS_PENDING_TASKS_LOG()->add_pending_task_log_entry( $app_id, 'disable-redis-object-cache', $app_id, $instance, 'ready', $app_id, __( 'Disable Redis Object Cache For New Site', 'simple-stack' ) );
		}

	}

	/**
	 * Activate SolidWP Security Pro when WP install is complete.
	 *
	 * Called from function sstk_wpapp_install_complete
	 *
	 * @param int   $app_id        post id of app.
	 * @param array $instance      Array passed by calling function containing details of the server and site.
	 */
	public function handle_solidwp_security_for_new_site( $app_id, $instance ) {

		if ( sstk_get_option( 'wordpress_app_sites_activate_solidwp_security' ) ) {
			$instance['action_hook'] = 'sstk_wordpress-app_pending_log_activate_solidwp_security';
			SSTK_POSTS_PENDING_TASKS_LOG()->add_pending_task_log_entry( $app_id, 'activate-solidwp-security', $app_id, $instance, 'ready', $app_id, __( 'Activate SolidWP Security Pro On New Site', 'simple-stack' ) );
		}

	}

	/**
	 * Create the server instance on the basis of the inputs.
	 *
	 */
	public function create_instance() {
		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_REQUEST['params'] ) ) );

		$webserver = $args['webserver-type'] ?? 'ols';
		if ( empty( $webserver ) ) {
			$webserver = 'ols';
		}
		if ( 'ols' !== $webserver ) {
			return new \WP_Error( 'sstk_invalid_webserver_type', __( 'Simple Stack supports OpenLiteSpeed servers only.', 'simple-stack' ) );
		}
		$os       = sanitize_text_field( $args['os'] );
		$provider = sanitize_text_field( $args['provider'] );
		$region   = sanitize_text_field( $args['region'] );
		$size     = sanitize_text_field( $args['size'] );
		$user_id  = (int) $args['wp_user_id'];
		if ( empty( $user_id ) ) {
			$user = wp_get_current_user();
		} else {
			$user = get_user_by( 'ID', $user_id );
		}
		$type    = sanitize_text_field( $args['server_type'] );
		$version = $this->get_configured_scripts_version();

		/* Make sure we have a server type */
		if ( empty( $type ) ) {
			$type                = 'wordpress-app';
			$args['server_type'] = $type;
		}

		/* Generate the server name */
		if ( sstk_get_option( 'wordpress_app_use_extended_server_name' ) || empty( $args['name'] ) ) {
			if ( empty( $args['name'] ) ) {
				$name = sanitize_title( sprintf( '%s-%s-%s', $provider, $user->user_nicename, get_gmt_from_date( '' ) ) );
			} else {
				$name = sanitize_title( sprintf( '%s-%s-%s-%s', $provider, $user->user_nicename, $args['name'], get_gmt_from_date( '' ) ) );
			}
		} else {
			$name = sanitize_text_field( $args['name'] );
		}

		$attributes = array(
			'webserver_type'   => $webserver,
			'initial_os'       => $os,
			'initial_app_name' => $type,
			'scripts_version'  => $version,
			'region'           => $region,
			'size_raw'         => $size,
			'name'             => $name,
			'provider'         => $provider,
			'init'             => true,
			'server-type'      => $type,
			'user_id'          => $user->ID,
		);

		// Add custom fields into the $attributes array...
		$custom_fields = apply_filters( "sstk_{$this->get_app_name()}_create_wp_server_parms_custom_fields", SSTK_CUSTOM_FIELDS()->get_fields_for_location( 'wordpress-app-new-server-popup' ), $attributes, $args );
		foreach ( $custom_fields as $field ) {
			if ( isset( $args[ $field['name'] ] ) ) {
				$attributes[ $field['name'] ] = sanitize_text_field( $args[ $field['name'] ] );
			}
		}

		// Get any post-processing bash script urls from settings.
		// This one will end up getting added to the servers' post meta but should be deleted afterwards in the sstk_wpapp_core_prepare_server_completed() function located in traits/after-prepare-server.php.
		$attributes['post_processing_script_server'] = '';
		if ( $this->sstk_can_user_execute_bash_scripts() ) {
			$post_process_script                         = sstk_get_option( 'sstk_wpapp_custom_script_after_server_create' );
			$attributes['post_processing_script_server'] = $post_process_script;
		}

		// Get the secret key manager api key from settings.
		// This one will end up getting added to the servers' post meta but should be deleted afterwards in the sstk_wpapp_core_prepare_server_completed() function located in traits/after-prepare-server.php.
		$secret_key_manager_api_key               = sstk_get_option( 'sstk_wpapp_custom_script_secrets_manager_api_key' );
		$attributes['secret_key_manager_api_key'] = $secret_key_manager_api_key;

		/* Allow others to populate the attributes array which should get stored in the CPT automatically. */
		$attributes = apply_filters( "sstk_{$this->get_app_name()}_initial_server_attributes", $attributes, $args );

		/* Create server */
		$instance = SSTK_SERVER()->create_server( 'create', $attributes );  // fire up a new server.

		/* Check for error */
		if ( ( is_wp_error( $instance ) ) || ( ! $instance ) ) {
			return $instance;
		}

		/* If we have a valid post id, add custom fields data to the server post object */
		$post_id = $instance['post_id'];
		if ( ( ! empty( $post_id ) ) && ( ! is_wp_error( $post_id ) ) ) {
			$custom_fields = apply_filters( "sstk_{$this->get_app_name()}_create_wp_server_custom_post_fields", SSTK_CUSTOM_FIELDS()->get_fields_for_location( 'wordpress-app-new-server-popup' ), $attributes, $args );
			foreach ( $custom_fields as $field ) {
				if ( isset( $instance[ $field['name'] ] ) ) {
					update_post_meta( $post_id, $field['name'], $instance[ $field['name'] ] );
				}
			}
		}

		/* Install app on server */
		if ( $instance ) {
			$instance = $this->add_app( $instance );
		}

		return $instance;
	}

	/**
	 * Implement empty method from parent class that adds an app to a server.
	 *
	 * This is generally called immediately after the server is provisioned.
	 * But in some cases such as this one, it is called without an APP actually
	 * needing to be installed.  Instead it is used to complete adding data to
	 * the server CPT and to schedule subsequent actions that need to be handled
	 * via the scheduler.
	 *
	 * When a WP APP is actually being installed on the server, the add_wp_app_post
	 * function will be called instead.
	 *
	 * @param array $instance Array of elements that contain information about the server.
	 *
	 * @return array Array of elements that contain information about the server AND the app.
	 */
	public function add_app( &$instance ) {

		$post_id = $instance['post_id']; // extract the server cpt postid from the instance reference.

		/* Loop through the $instance array and add certain elements to the server cpt record */
		foreach ( $instance as $key => $value ) {

			if ( in_array( $key, array( 'init' ), true ) ) {
				continue;
			}

			/* If we're here, then this is a field that's for the server record. */
			update_post_meta( $post_id, 'sstk_server_' . $key, $value );
		}

		/* Restructure the server instance array to add the app data that is going into the sstk_app CPT */
		if ( ! isset( $instance['apps'] ) ) {
			$instance['apps'] = array();
		}

		if ( 'something-else' === $instance['server-type'] ) {
			/**
			 * No apps are being installed.
			 */
			return;
		}

		/* Schedule after-server-create commands (commands to run after the server has been instantiated for the first time) */
		update_post_meta( $post_id, "sstk_server_{$this->get_app_name()}_action", 'after-server-create-commands' );
		/* update_post_meta( $post_id, 'sstk_server_after_create_action_app_id', $app_post_id ); */  // No app so no app_post_id var.
		update_post_meta( $post_id, "sstk_server_{$this->get_app_name()}_action_status", 'in-progress' );
		SSTK_SERVER()->add_deferred_action_history( $post_id, $this->get_app_name() );
		if ( isset( $instance['init'] ) && true === $instance['init'] ) {
			update_post_meta( $post_id, 'sstk_server_init', '1' );
		}

		return $instance;

	}



	/**
	 * Adds a welcome message to the GENERAL SETTINGS tab.
	 *
	 * Filter hook: sstk_general_settings_after_welcome_message
	 *
	 * @param array $meta_boxes current set of metaboxes defined on the settings screen.
	 *
	 * @return array $meta_boxes
	 */
	public function welcome_message_settings( $meta_boxes ) {

		$welcome_message  = '';
		$welcome_message .= '<b>' . __( 'Here are the basic steps needed to build your first server and deploy your first WordPress server & site:', 'simple-stack' ) . '</b>';
		$welcome_message .= '<br />';

		$welcome_message .= '<ol>';
		$welcome_message .= '<li>';
		$welcome_message .= __( 'Double-check that your current web server where this plugin is installed has the appropriate timeouts as listed at the bottom of this screen. You will not be able to build your first server unless your web server timeouts are increased.', 'simple-stack' );
		$welcome_message .= '</li>';
		$welcome_message .= '<li>';
		$welcome_message .= __( 'Generate an API key in your server providers\' dashboard.', 'simple-stack' );
		$welcome_message .= '</li>';
		$welcome_message .= '<li>';
		$welcome_message .= __( 'If you see the startup wizard notice at the top of this screen, use it to connect your Vultr or UpCloud account and set up your SSH keys.', 'simple-stack' );
		$welcome_message .= '</li>';
		$welcome_message .= '</ol>';

		$welcome_message .= '';
		$welcome_message .= '<b>' . __( 'If you cannot use the wizard or do not see the wizard notice:', 'simple-stack' ) . '</b>';
		$welcome_message .= '<br />';

		$welcome_message .= '<ol>';
		$welcome_message .= '<li>';
		$welcome_message .= __( 'Make sure you have created and uploaded an SSH key pair to your server providers\' dashboard.', 'simple-stack' );
		$welcome_message .= '</li>';
		$welcome_message .= '<li>';
		$welcome_message .= __( 'Add your cloud server provider API key and other credentials on the Simple Stack Providers screen.', 'simple-stack' );
		$welcome_message .= '</li>';
		$welcome_message .= '</ol>';

		$welcome_message .= '';
		$welcome_message .= '<b>' . __( 'Now you can deploy your first WordPress server & site:', 'simple-stack' ) . '</b>';
		$welcome_message .= '<br />';

		$welcome_message .= '<ol>';
		$welcome_message .= '<li>';
		$welcome_message .= __( 'Click on the ALL CLOUD SERVERS menu option and use the DEPLOY A NEW WordPress SERVER button to deploy a server.', 'simple-stack' );
		$welcome_message .= '</li>';
		$welcome_message .= '<li>';
		$welcome_message .= __( 'After the server is deployed, go back to the CLOUD SERVERS menu option and click the INSTALL WordPress button in the server list.', 'simple-stack' );
		$welcome_message .= '</li>';
		$welcome_message .= '</ol>';
		$welcome_message .= '<br />';
		$welcome_message .= '&nbsp;&nbsp;&nbsp;&nbsp;';
		$welcome_message .= sprintf( '<a style="padding: 10px; border: solid; border-width: 1px; text-decoration: none; color: white; background-color: #03114A; border-color: #03114A;" target="_blank" href="%s">' . __( 'Quick Start Guide', 'simple-stack' ) . '</a>', 'https://github.com/onedogsolutions/simple-stack/blob/main/README.md' );

		$welcome_message = apply_filters( 'sstk_settings_deploy_first_wp_site_text', $welcome_message );

		$meta_boxes[] = array(
			'id'             => 'sstk_deploy_your_first_wordpress_site',
			'title'          => __( 'Deploy Your First WordPress Site', 'simple-stack' ),
			'settings_pages' => 'sstk_settings',
			'tab'            => 'general',
			'fields'         => array(
				array(
					'type' => 'heading',
					'id'   => 'sstk_deploy_your_first_wordpress_site_heading',
					'name' => 'Deploy Your First WordPress Site',
					'desc' => $welcome_message,
				),
			),
		);

		return $meta_boxes;

	}

	/**
	 * Adds an additional set of message strings to the default "no application servers were found" message
	 * that shows up when there are no app servers in the app servers list.
	 *
	 * **** It looks like this FILTER does not work at all.
	 * **** The CPT creation process might be too early for
	 * **** it to fire properly?
	 *
	 * Filter hook: sstk_no_app_servers_found_msg
	 *
	 * @param string $msg message.
	 *
	 * @return string $msg
	 */
	public function no_app_servers_found_msg( $msg ) {
		$msg .= '<br />';
		$msg .= __( 'To create a new server, first make sure you add your cloud provider credentials under the SETTINGS screen.', 'simple-stack' );
		$msg .= '<br />';
		$msg .= __( 'Then, you can click the DEPLOY A NEW WordPress SERVER button at the top of this screen to create your first server.', 'simple-stack' );
		return $msg;
	}

	/**
	 * Return an instance of self.
	 *
	 * @return SSTK_WORDPRESS_APP
	 */
	public function get_this() {
		return $this;
	}

	/**
	 * SSH function.
	 *
	 * @return WORDPRESS_SSH()
	 */
	public function ssh() {
		if ( empty( SSTK()->classes['sstk_wordpress_ssh'] ) ) {
			SSTK()->classes['sstk_wordpress_ssh'] = new WORDPRESS_SSH();
		}
		return SSTK()->classes['sstk_wordpress_ssh'];
	}

	/**
	 * Settings function.
	 *
	 * @return WORDPRESS_APP_SETTINGS()
	 */
	public function settings() {
		if ( empty( SSTK()->classes['sstk_app_wordpress_settings'] ) ) {
			SSTK()->classes['sstk_app_wordpress_settings'] = new WORDPRESS_APP_SETTINGS();
		}
		return SSTK()->classes['sstk_app_wordpress_settings'];
	}


	/**
	 * Sets the meta that indicates whether memcached is installed on a server.
	 *
	 * @param int    $server_id  The post id of the server or app.
	 * @param string $status true/false.
	 */
	public function set_server_memcached_installed_status( $server_id, $status ) {

		if ( true === $status ) {
			update_post_meta( $server_id, 'sstk_wpapp_memcached_installed', 'yes' );
		} else {
			update_post_meta( $server_id, 'sstk_wpapp_memcached_installed', 'no' );
		}

	}

	/**
	 * Returns whether memcached is installed on a server.
	 *
	 * @param int $id  The post id of the server or app.
	 */
	public function is_memcached_installed( $id ) {
		// What kind of id did we get?  App or server?
		if ( 'sstk_app' === get_post_type( $id ) ) {
			$server_post = $this->get_server_by_app_id( $id );
			if ( $server_post ) {
				$server_id = $server_post->ID;
			}
		} elseif ( 'sstk_app_server' === get_post_type( $id ) ) {
			$server_id = $id;
		}

		$meta_status = get_post_meta( $server_id, 'sstk_wpapp_memcached_installed', true );

		if ( 'yes' === $meta_status ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Set flag that indicates whether or not MEMCACHED is installed on a site.
	 *
	 * @param int         $app_id  The post id of the  app.
	 * @param bool|string $status true/false or 'on'/'off'.
	 */
	public function set_app_memcached_installed_status( $app_id, $status ) {

		if ( true === $status || 'on' === $status ) {
			update_post_meta( $app_id, 'wpapp_memcached_status', 'on' );
		} else {
			update_post_meta( $app_id, 'wpapp_memcached_status', 'off' );
		}

	}

	/**
	 * Returns whether MEMCACHED is installed on a site.
	 *
	 * @param int $app_id  The post id of the app.
	 */
	public function get_app_is_memcached_installed( $app_id ) {

		$meta_status = get_post_meta( $app_id, 'wpapp_memcached_status', true );

		if ( 'on' === $meta_status ) {
			return true;
		} else {
			return false;
		}

	}

	/**
	 * Sets the meta that indicates whether REDIS is installed on a server.
	 *
	 * @param int    $server_id  The post id of the server or app.
	 * @param string $status true/false.
	 */
	public function set_server_redis_installed_status( $server_id, $status ) {

		if ( true === $status ) {
			update_post_meta( $server_id, 'sstk_wpapp_redis_installed', 'yes' );
		} else {
			update_post_meta( $server_id, 'sstk_wpapp_redis_installed', 'no' );
		}

	}

	/**
	 * Returns whether REDIS is installed on a server.
	 *
	 * @param int $id  The post id of the server or app.
	 */
	public function is_redis_installed( $id ) {
		// What kind of id did we get?  App or server?
		if ( 'sstk_app' === get_post_type( $id ) ) {
			$server_post = $this->get_server_by_app_id( $id );
			if ( $server_post ) {
				$server_id = $server_post->ID;
			}
		} elseif ( 'sstk_app_server' === get_post_type( $id ) ) {
			$server_id = $id;
		}

		$meta_status = get_post_meta( $server_id, 'sstk_wpapp_redis_installed', true );

		if ( 'yes' === $meta_status ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Set flag that indicates whether or not REDIS is installed on a site.
	 *
	 * @param int         $app_id  The post id of the  app.
	 * @param bool|string $status true/false or 'on'/'off'.
	 */
	public function set_app_redis_installed_status( $app_id, $status ) {

		if ( true === $status || 'on' === $status ) {
			update_post_meta( $app_id, 'wpapp_redis_status', 'on' );
		} else {
			update_post_meta( $app_id, 'wpapp_redis_status', 'off' );
		}

	}

	/**
	 * Returns whether REDIS is installed on a site.
	 *
	 * @param int $app_id  The post id of the app.
	 */
	public function get_app_is_redis_installed( $app_id ) {

		$meta_status = get_post_meta( $app_id, 'wpapp_redis_status', true );

		if ( 'on' === $meta_status ) {
			return true;
		} else {
			return false;
		}

	}

	/**
	 * Returns the installed status of a service on the server.
	 * Services include OpenLiteSpeed, MariaDB, Redis and Memcached.
	 *
	 * @param int    $id  The post id of the server or app.
	 * @param string $service    The name of the service.
	 *
	 * @return string   'installed'/'not-installed'/'unknown'
	 */
	public function get_server_installed_service_status( $id, $service ) {

		$server_id = null;

		// What kind of id did we get?  App or server?
		if ( 'sstk_app' === get_post_type( $id ) ) {
			$server_post = $this->get_server_by_app_id( $id );
			if ( $server_post ) {
				$server_id = $server_post->ID;
			}
		} elseif ( 'sstk_app_server' === get_post_type( $id ) ) {
			$server_id = $id;
		}

		$status = 'not installed';

		if ( $server_id ) {
			switch ( $service ) {
				case 'memcached':
					$meta_status = $this->is_memcached_installed( $server_id );
					if ( true === $meta_status ) {
						$status = 'installed';
					} else {
						$status = 'not-installed';
					}
					break;

				case 'redis':
					$meta_status = $this->is_redis_installed( $server_id );
					if ( true === $meta_status ) {
						$status = 'installed';
					} else {
						$status = 'not-installed';
					}
					break;

			}
		}

		return $status;

	}

	/**
	 * Register the scripts for custom post types for the wp app.
	 *
	 * @param string $hook The page name hook.
	 */
	public function wpapp_enqueue_scripts( $hook ) {
		/*
		 * The tabbed edit screens are retired (Phase 4), so the jQuery bundle that drove
		 * them (sstk-wpapp-admin-common.js and sstk-wpapp-admin-app.js, both dependent on
		 * select2 and magnific-popup) is no longer enqueued. The React client owns those
		 * screens now and talks to the REST layer instead of admin-ajax.php.
		 */
		if ( in_array( $hook, array( 'edit.php' ) ) || in_array( $hook, array( 'post.php' ) ) ) {
			$screen = get_current_screen();
			if ( ( is_object( $screen ) && in_array( $screen->post_type, array( 'sstk_app' ) ) ) || SSTK_WORDPRESS_APP_PUBLIC::is_apps_list_page() || SSTK_WORDPRESS_APP_PUBLIC::is_app_edit_page() ) {
				wp_enqueue_style( 'sstk-wpapp-admin-app-css', SSTK_URL . 'includes/core/apps/wordpress-app/assets/css/sstk-wpapp-admin-app.css', array(), SSTK_SCRIPTS_VERSION );

				wp_enqueue_script( 'sstk-wpapp-admin-post-type-sstk-app', SSTK_URL . 'includes/core/apps/wordpress-app/assets/js/sstk-wpapp-admin-post-type-sstk-app.js', array( 'jquery' ), SSTK_SCRIPTS_VERSION, true );
				wp_localize_script(
					'sstk-wpapp-admin-post-type-sstk-app',
					'params3',
					apply_filters(
						'sstk_app_script_args',
						array(
							'nonce'                     => wp_create_nonce( 'sstk-app' ),
							'_action'                   => 'remove',
							'passwordless_login_action' => 'passwordless_login',
							'i10n'                      => array(
								'remove_site_prompt' => __( 'Are you sure you would like to delete this site and data? This action is NOT reversible!', 'simple-stack' ),
								'install_wpapp'      => __( 'Install WordPress', 'simple-stack' ),
							),
							'install_wpapp_url'         => admin_url( 'edit.php?post_type=sstk_app_server' ),
							'bulk_actions_confirm'      => __( 'Are you sure you want to perform this bulk action?', 'simple-stack' ),
						),
						'sstk-wpapp-admin-post-type-sstk-app'
					)
				);
			}
		}

		$screen     = get_current_screen();
		$post_types = array( 'sstk_app_server', 'sstk_app', 'sstk_team', 'sstk_permission_type', 'sstk_command_log', 'sstk_ssh_log', 'sstk_error_log', 'sstk_pending_log' );

		if ( ( is_object( $screen ) && in_array( $screen->post_type, $post_types ) ) || SSTK_WORDPRESS_APP_PUBLIC::is_public_page() ) {

			wp_enqueue_style( 'sstk-wpapp-admin-app-css', SSTK_URL . 'includes/core/apps/wordpress-app/assets/css/sstk-wpapp-admin-app.css', array(), SSTK_SCRIPTS_VERSION );

			wp_enqueue_script( 'sstk-admin-common', SSTK_URL . 'includes/core/apps/wordpress-app/assets/js/sstk-admin-common.js', array( 'jquery' ), SSTK_SCRIPTS_VERSION, true );
			wp_localize_script(
				'sstk-admin-common',
				'readableCheck',
				array(
					'nonce'                    => wp_create_nonce( 'sstk-admin' ),
					'action'                   => 'set_readable_check',
					'check_again_action'       => 'readable_check_again',
					'cron_check_action'        => 'set_cron_check',
					'php_version_check_action' => 'php_version_check',
					'localhost_check_action'   => 'localhost_version_check',
				)
			);
		}

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
				self::wpapp_schedule_events();
				restore_current_blog();
			}
		} else {
			self::wpapp_schedule_events();
		}

	}

	/**
	 * Schedule events on Activation of the plugin.
	 *
	 * @return void
	 */
	public static function wpapp_schedule_events() {
		// Schedule nothing if our 'better cron' constant is true.
		if ( defined( 'DISABLE_SSTK_CRON' ) && DISABLE_SSTK_CRON == true ) {
			return;
		}

		// setup temporary script deletion.
		wp_clear_scheduled_hook( 'sstk_wordpress_file_watcher' );
		wp_schedule_event( time(), 'sstk_every_minute', 'sstk_wordpress_file_watcher' );

		// setup deferred instance actions schedule that acts on server records.
		wp_clear_scheduled_hook( 'sstk_wordpress_deferred_actions_for_server' );
		wp_schedule_event( time(), 'sstk_every_minute', 'sstk_wordpress_deferred_actions_for_server' );

		// setup actions schedule that acts on app records.
		wp_clear_scheduled_hook( 'sstk_wordpress_deferred_actions_for_apps' );
		wp_schedule_event( time(), 'sstk_every_minute', 'sstk_wordpress_deferred_actions_for_apps' );
	}

	/**
	 * Fires on deactivation of plugin.
	 *
	 * @param  boolean $network_wide    True if plugin is deactivated network-wide.
	 *
	 * @return void
	 */
	public static function deactivate( $network_wide ) {

		if ( is_multisite() && $network_wide ) {
			// Get all blogs in the network.
			$blog_ids = get_sites( array( 'fields' => 'ids' ) );
			foreach ( $blog_ids as $blog_id ) {
				switch_to_blog( $blog_id );
				self::wpapp_clear_scheduled_events();
				restore_current_blog();
			}
		} else {
			self::wpapp_clear_scheduled_events();
		}

	}

	/**
	 * Clears scheduled events on Deactivation of the plugin.
	 *
	 * @return void
	 */
	public static function wpapp_clear_scheduled_events() {
		wp_clear_scheduled_hook( 'sstk_wordpress_file_watcher' );
		wp_clear_scheduled_hook( 'sstk_wordpress_deferred_actions_for_server' );
		wp_clear_scheduled_hook( 'sstk_wordpress_deferred_actions_for_apps' );
	}

	/**
	 * To schedule events for newly created site on WP Multisite.
	 *
	 * Action hook: wp_initialize_site
	 *
	 * @param  object $new_site new sites.
	 * @param  array  $args args.
	 * @return void
	 */
	public function wpapp_schedule_events_for_new_site( $new_site, $args ) {

		$plugin_name = SSTK_PLUGIN;

		// To check the plugin is active network-wide.
		if ( is_plugin_active_for_network( $plugin_name ) ) {
			$blog_id = $new_site->blog_id;

			switch_to_blog( $blog_id );
			self::wpapp_schedule_events();
			restore_current_blog();
		}

	}

	/**
	 * Checks whether the specified directory is readable and .txt files can be accessible
	 */
	public function wpapp_admin_init_is_readable() {
		if ( ! get_transient( 'sstk_readable_check' ) ) {
			/* Check the only provisioning script directory shipped by Simple Stack. */
			$scripts_relative = $this->get_scripts_folder_relative() . $this->get_configured_scripts_version() . '/raw/';
			$dir              = SSTK_PATH . $scripts_relative;
			$url              = SSTK_URL . $scripts_relative . '01-prepare_server.txt';

			// Use CURL to check to see if the server is responding for a request for the file defined in the $url var above..
			$curl_error = false;
			$ch         = curl_init( $url );
			$txt        = curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_HEADER, 0 );
			curl_exec( $ch );
			if ( curl_error( $ch ) ) {
				$curl_error = true;
			}

			// Checks if directory is readable and .txt files can be accessible. Setting transient so that notice doesnt show at admin screens everytime.
			if ( is_readable( $dir ) && ( ! $curl_error ) ) {

				set_transient( 'sstk_readable_check', 1, 12 * HOUR_IN_SECONDS );
			}
		}
	}

	/**
	 * Sets the transient for readable directory check
	 * This will be set when user dismisses the notice for readable directory check
	 */
	public function set_readable_check() {

		/* Nonce check */
		check_ajax_referer( 'sstk-admin', 'nonce' );

		/* Permission check - unsure that this is needed since the action is not destructive and might cause issues if the user sees the message and can't dismiss it because they're not an admin. */
		if ( ! sstk_is_admin() ) {
			wp_send_json_error( array( 'msg' => __( 'You are not authorized to perform this action - dismiss readable check.', 'simple-stack' ) ) );
		}

		/* All checks passed so set the transient */
		set_transient( 'sstk_readable_check', 1, 12 * HOUR_IN_SECONDS );
		wp_die();

	}

	/**
	 * Returns the metabox.io tab style to use for the main tabs in the WordPress app detail screen
	 */
	public function get_tab_style() {
		$tab_style = ( ! empty( sstk_get_early_option( 'wordpress_app_tab_style' ) ) ) ? sstk_get_early_option( 'wordpress_app_tab_style' ) : 'left';
		return $tab_style;
	}

	/**
	 * Returns the metabox.io tab style to use for the main tabs in the WordPress app server screen
	 */
	public function get_tab_style_server() {
		$tab_style = ( ! empty( sstk_get_early_option( 'wordpress_app_server_tab_style' ) ) ) ? sstk_get_early_option( 'wordpress_app_server_tab_style' ) : 'left';
		return $tab_style;
	}

	/**
	 * Sets the transient for readable directory check
	 * This will be set when user clicks check again link on the notice for readable directory check
	 */
	public function readable_check_again() {

		/* Nonce check */
		check_ajax_referer( 'sstk-admin', 'nonce' );

		/* Permission check - unsure that this is needed since the action is not destructive and might cause issues if the user sees the message and can't dismiss it because they're not an admin. */
		if ( ! sstk_is_admin() ) {
			wp_send_json_error( array( 'msg' => __( 'You are not authorized to perform this action - do readable check again.', 'simple-stack' ) ) );
		}

		$this->wpapp_admin_init_is_readable();

		if ( get_transient( 'sstk_readable_check' ) ) {
			$return = array(
				'message' => __( 'Readable check successful!', 'simple-stack' ),
			);
			wp_send_json_success( $return );
		} else {
			$return = array(
				'message' => __( 'Readable check failed!', 'simple-stack' ),
			);
			wp_send_json_error( $return );
		}

		wp_die();
	}


	/**
	 * Get the link to be shown for a passwordless login.
	 * This is used in multiple places hence extracted into this function.
	 *
	 * @param int    $app_id The app_id for the site that needs this link.
	 * @param string $label The label to use for the link.
	 */
	public function get_passwordless_login_link_for_display( $app_id, $label ) {
		$return = sprintf(
			'<a class="sstk_action_passwordless_login" data-sstk-id="%d" data-sstk-domain="%s" href="">%s</a>',
			$app_id,
			$this->get_domain_name( $app_id ),
			esc_html( $label ),
		);
		return apply_filters( 'sstk_wordpress-app_passwordless_login_link', $return, $app_id );
	}


	/**
	 * Handle passwordless login ajax request.
	 *
	 * Action Hook: wp_ajax_passwordless_login
	 */
	public function passwordless_login() {

		/* Nonce check */
		check_ajax_referer( 'sstk-app', 'nonce' );

		/* Permission check - unsure that this is needed since the action is not destructive and might cause issues if the user sees the message and can't dismiss it because they're not an admin. */
		if ( ! sstk_is_admin() ) {
			wp_send_json_error( array( 'msg' => __( 'You are not authorized to perform this action.', 'simple-stack' ) ) );
		}

		/**
		 * From here on out, we're going to use the style of processing that we use in tabs.
		 * But because we're not in a tab we have to change some things around!
		 */

		// Grab data out of $POST.
		$id     = (int) $_POST['id'];
		$domain = sanitize_text_field( $_POST['domain'] );

		// Get app/server details.
		$instance = $this->get_app_instance_details( $id );

		// Set action var.
		$action = 'wp_site_get_passwordless_link';

		// Bail if no app/server details.
		if ( is_wp_error( $instance ) ) {
			/* Translators: %s is the action name. */
			$message = sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'simple-stack' ), $action );
			return new \WP_Error( $message );
		}

		// Create args array.
		$args['domain'] = escapeshellarg( $domain );

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'passwordless_login.txt',
			array_merge(
				$args,
				array(
					'action' => $action,
				)
			)
		);

		do_action( 'sstk_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'passwordless_login.txt' );

		if ( ! $success ) {
			/* Translators: %1$s is the action; %2$s is the result of the ssh call. */
			$message = sprintf( __( 'Unable to %1$s site: %2$s', 'simple-stack' ), $action, $result );
			return new \WP_Error( $message );
		} else {
			// grab the very last line in the results that should contain the url.
			list($url_array[]) = array_slice( explode( PHP_EOL, trim( $result ) ), -1, 1 );
			$return            = array(
				'redirect_to' => $url_array[0],
			);
			wp_send_json_success( $return );
		}

		wp_die();
	}

	/**
	 * Add filters on the app listing screen at the backend
	 *
	 * Action hook: restrict_manage_posts
	 *
	 * @return void
	 */
	public function wpapp_sstk_app_table_filtering() {

		global $typenow, $pagenow;

		$post_type = 'sstk_app';

		if ( ( is_admin() && 'edit.php' === $pagenow && $typenow == $post_type ) || SSTK_WORDPRESS_APP_PUBLIC::is_apps_list_page() ) {

			// APP STATUS.
			$app_status_options = array(
				'on'  => __( 'Enabled', 'simple-stack' ),
				'off' => __( 'Disabled', 'simple-stack' ),
			);
			$app_status         = $this->generate_meta_dropdown( 'wpapp_site_status', __( 'App Status', 'simple-stack' ), $app_status_options );
			echo sstk_kses_select( $app_status );

			// PHP VERSION.
			$php_version_options = array(
				'8.3' => '8.3',
				'8.4' => '8.4',
				'8.5' => '8.5',
			);
			$php_version         = $this->generate_meta_dropdown( 'wpapp_php_version', __( 'PHP Version', 'simple-stack' ), $php_version_options );
			echo sstk_kses_select( $php_version );

			// CACHE.
			$cache_options  = array(
				'on'  => __( 'Enabled', 'simple-stack' ),
				'off' => __( 'Disabled', 'simple-stack' ),
			);
			$php_page_cache = $this->generate_meta_dropdown( 'wpapp_page_cache_status', __( 'Page Cache', 'simple-stack' ), $cache_options );
			echo sstk_kses_select( $php_page_cache );

			$php_object_cache = $this->generate_meta_dropdown( 'wpapp_object_cache_status', __( 'Object Cache', 'simple-stack' ), $cache_options );
			echo sstk_kses_select( $php_object_cache );

			// SITE NEEDS UPDATES.
			$updates_options    = array(
				'yes' => __( 'Yes', 'simple-stack' ),
				'no'  => __( 'No', 'simple-stack' ),
			);
			$site_needs_updates = $this->generate_meta_dropdown( 'wpapp_sites_needs_updates', __( 'Site Needs Updates', 'simple-stack' ), $updates_options );
			echo sstk_kses_select( $site_needs_updates );

			/* Stuff only the admin should see. */
			if ( sstk_is_admin() ) {

				/**
				 * Filters for site expiration status.
				 */
				$expiration_options = array(
					'1' => __( 'Expired', 'simple-stack' ),
					'0' => __( 'Not Expired (Marked)', 'simple-stack' ),
				);
				$expiration         = $this->generate_meta_dropdown( 'sstk_app_expired_status', __( 'Expiration Status', 'simple-stack' ), $expiration_options );

				echo sstk_kses_select( $expiration );

				/**
				 * Filters specific to Multi-tenant
				 */
				if ( true === sstk_is_mt_enabled() ) {

					// TEMPLATE FLAGS.
					$template_flag_options = array(
						'1' => __( 'Yes', 'simple-stack' ),
						'0' => __( 'No', 'simple-stack' ),
					);
					$is_template           = $this->generate_meta_dropdown( 'wpapp_is_template', __( 'Template', 'simple-stack' ), $template_flag_options );
					echo sstk_kses_select( $is_template );

				}

				/**
				 * Filters specific to Multi-tenant
				 */
				if ( true === sstk_is_mt_enabled() ) {

					// MT SITE TYPE.
					$mt_site_type = SSTK_POSTS_APP()->generate_meta_dropdown( 'sstk_app', 'sstk_app_mt_site_type', __( 'MT Site Type', 'simple-stack' ) );
					echo sstk_kses_select( $mt_site_type );

					// MT VERSION.
					$mt_site_type = SSTK_POSTS_APP()->generate_meta_dropdown( 'sstk_app', 'sstk_app_mt_version', __( 'MT Version', 'simple-stack' ) );
					echo sstk_kses_select( $mt_site_type );

					// MT PARENT ID.
					$mt_parent_id = SSTK_POSTS_APP()->generate_meta_dropdown( 'sstk_app', 'sstk_app_mt_parent', __( 'MT Parent Template', 'simple-stack' ) );
					echo sstk_kses_select( $mt_parent_id );
				}

			}
		}
	}

	/**
	 * To modify default query parameters and to show app listing based on custom filters
	 *
	 * Action filter: parse_query
	 *
	 * @param  object $query query.
	 */
	public function wpapp_sstk_app_parse_query( $query ) {
		global $pagenow;

		$filter_action = sanitize_text_field( filter_input( INPUT_GET, 'filter_action', FILTER_UNSAFE_RAW ) );
		if ( ( ( is_admin() && $query->is_main_query() && 'edit.php' === $pagenow ) || sstk_is_public_apps_list_query( $query ) ) && 'sstk_app' === $query->query['post_type'] && ! empty( $filter_action ) ) {
			$qv = &$query->query_vars;

			// APP STATUS.
			if ( isset( $_GET['wpapp_site_status'] ) && ! empty( $_GET['wpapp_site_status'] ) ) {
				$wpapp_site_status = sanitize_text_field( filter_input( INPUT_GET, 'wpapp_site_status', FILTER_UNSAFE_RAW ) );

				if ( $wpapp_site_status === 'on' ) {

					$qv['meta_query'][] = array(
						'relation' => 'OR',
						array(
							'key'     => 'wpapp_site_status',
							'value'   => $wpapp_site_status,
							'compare' => '=',
						),
						array(
							'key'     => 'wpapp_site_status',
							'compare' => 'NOT EXISTS',
						),
					);

				} else {
					$qv['meta_query'][] = array(
						'key'     => 'wpapp_site_status',
						'value'   => $wpapp_site_status,
						'compare' => '=',
					);
				}
			}

			// PHP VERSION.
			if ( isset( $_GET['wpapp_php_version'] ) && ! empty( $_GET['wpapp_php_version'] ) ) {
				$wpapp_php_version = sanitize_text_field( filter_input( INPUT_GET, 'wpapp_php_version', FILTER_UNSAFE_RAW ) );

				if ( $wpapp_php_version === $this->get_wpapp_default_php_version() ) {

					$qv['meta_query'][] = array(
						'relation' => 'OR',
						array(
							'key'     => 'wpapp_php_version',
							'value'   => $wpapp_php_version,
							'compare' => '=',
						),
						array(
							'key'     => 'wpapp_php_version',
							'compare' => 'NOT EXISTS',
						),
					);

				} else {
					$qv['meta_query'][] = array(
						'key'     => 'wpapp_php_version',
						'value'   => $wpapp_php_version,
						'compare' => '=',
					);
				}
			}

			// PAGE CACHE.
			if ( isset( $_GET['wpapp_page_cache_status'] ) && ! empty( $_GET['wpapp_page_cache_status'] ) ) {
				$wpapp_page_cache_status = sanitize_text_field( filter_input( INPUT_GET, 'wpapp_page_cache_status', FILTER_UNSAFE_RAW ) );

				if ( $wpapp_page_cache_status === 'off' ) {

					$qv['meta_query'][] = array(
						'relation' => 'OR',
						array(
							'key'     => 'wpapp_pagecache_status',
							'value'   => $wpapp_page_cache_status,
							'compare' => '=',
						),
						array(
							'key'     => 'wpapp_pagecache_status',
							'compare' => 'NOT EXISTS',
						),
					);

				} else {
					$qv['meta_query'][] = array(
						'key'     => 'wpapp_pagecache_status',
						'value'   => $wpapp_page_cache_status,
						'compare' => '=',
					);
				}
			}

			// OBJECT CACHE.
			if ( isset( $_GET['wpapp_object_cache_status'] ) && ! empty( $_GET['wpapp_object_cache_status'] ) ) {
				$wpapp_object_cache_status = sanitize_text_field( filter_input( INPUT_GET, 'wpapp_object_cache_status', FILTER_UNSAFE_RAW ) );

				if ( $wpapp_object_cache_status === 'off' ) {

					$qv['meta_query'][] = array(
						'relation' => 'OR',
						array(
							'key'     => 'wpapp_memcached_status',
							'value'   => $wpapp_object_cache_status,
							'compare' => '=',
						),
						array(
							'key'     => 'wpapp_memcached_status',
							'compare' => 'NOT EXISTS',
						),
					);

					$qv['meta_query'][] = array(
						'relation' => 'OR',
						array(
							'key'     => 'wpapp_redis_status',
							'value'   => $wpapp_object_cache_status,
							'compare' => '=',
						),
						array(
							'key'     => 'wpapp_redis_status',
							'compare' => 'NOT EXISTS',
						),
					);

				} else {
					$qv['meta_query'][] = array(
						'relation' => 'OR',
						array(
							'key'     => 'wpapp_memcached_status',
							'value'   => $wpapp_object_cache_status,
							'compare' => '=',
						),
						array(
							'key'     => 'wpapp_redis_status',
							'value'   => $wpapp_object_cache_status,
							'compare' => '=',
						),
					);
				}
			}

			// SITE NEEDS UPDATES.
			if ( isset( $_GET['wpapp_sites_needs_updates'] ) && ! empty( $_GET['wpapp_sites_needs_updates'] ) ) {
				$wpapp_sites_needs_updates = sanitize_text_field( filter_input( INPUT_GET, 'wpapp_sites_needs_updates', FILTER_UNSAFE_RAW ) );

				if ( $wpapp_sites_needs_updates === 'yes' ) {

					$qv['meta_query'][] = array(
						'relation' => 'OR',
						array(
							'key'     => 'sstk_site_needs_updates',
							'value'   => $wpapp_sites_needs_updates,
							'compare' => '=',
						),
					);

				} else {
					$qv['meta_query'][] = array(
						'key'     => 'sstk_site_needs_updates',
						'value'   => $wpapp_sites_needs_updates,
						'compare' => '=',
					);
				}
			}

			// SITE EXPIRATION.
			if ( isset( $_GET['sstk_app_expired_status'] ) && ! empty( $_GET['sstk_app_expired_status'] ) ) {
				$wpapp_site_expired_status = sanitize_text_field( filter_input( INPUT_GET, 'sstk_app_expired_status', FILTER_UNSAFE_RAW ) );

				if ( '0' === $wpapp_site_expired_status || '1' === $wpapp_site_expired_status ) {

					$qv['meta_query'][] = array(
						'relation' => 'OR',
						array(
							'key'     => 'sstk_app_expired_status',
							'value'   => $wpapp_site_expired_status,
							'type'    => 'NUMERIC',
							'compare' => '=',
						),
					);
				} else {
					$qv['meta_query'][] = array(
						'key'     => 'sstk_app_expired_status',
						'value'   => $wpapp_site_expired_status,
						'compare' => '=',
					);
				}
			}

			// Template Flag.
			// @todo: This logic does not handle empty metas.
			if ( isset( $_GET['wpapp_is_template'] ) && ! empty( $_GET['wpapp_is_template'] ) ) {
				$wpapp_template_flag = sanitize_text_field( filter_input( INPUT_GET, 'wpapp_is_template', FILTER_UNSAFE_RAW ) );

				if ( $wpapp_template_flag === '1' ) {

					$qv['meta_query'][] = array(
						'relation' => 'OR',
						array(
							'key'     => 'sstk_is_template_site',
							'value'   => $wpapp_template_flag,
							'compare' => '=',
						),
					);

				} else {
					$qv['meta_query'][] = array(
						'key'     => 'sstk_is_template_site',
						'value'   => $wpapp_template_flag,
						'compare' => '=',
					);
				}
			}

			// MT Site Type.
			if ( isset( $_GET['sstk_app_mt_site_type'] ) && ! empty( $_GET['sstk_app_mt_site_type'] ) ) {
				$wpapp_mt_site_type = sanitize_text_field( filter_input( INPUT_GET, 'sstk_app_mt_site_type', FILTER_UNSAFE_RAW ) );

				if ( ! empty( $wpapp_mt_site_type ) ) {
					$qv['meta_query'][] = array(
						array(
							'key'     => 'sstk_app_mt_site_type',
							'value'   => $wpapp_mt_site_type,
							'compare' => '=',
						),
					);
				}
			}

			// MT Version.
			if ( isset( $_GET['sstk_app_mt_version'] ) && ! empty( $_GET['sstk_app_mt_version'] ) ) {
				$sstk_app_mt_version = sanitize_text_field( filter_input( INPUT_GET, 'sstk_app_mt_version', FILTER_UNSAFE_RAW ) );

				if ( ! empty( $sstk_app_mt_version ) ) {
					$qv['meta_query'][] = array(
						array(
							'key'     => 'sstk_app_mt_version',
							'value'   => $sstk_app_mt_version,
							'compare' => '=',
						),
					);
				}
			}

			// MT Parent.
			if ( isset( $_GET['sstk_app_mt_parent'] ) && ! empty( $_GET['sstk_app_mt_parent'] ) ) {
				$sstk_app_mt_parent = sanitize_text_field( filter_input( INPUT_GET, 'sstk_app_mt_parent', FILTER_UNSAFE_RAW ) );

				if ( ! empty( $sstk_app_mt_parent ) ) {
					$qv['meta_query'][] = array(
						array(
							'key'     => 'sstk_app_mt_parent',
							'value'   => $sstk_app_mt_parent,
							'compare' => '=',
						),
					);
				}
			}

		}

		if ( ( ( is_admin() && $query->is_main_query() && 'edit.php' === $pagenow ) || sstk_is_public_apps_list_query( $query ) ) && 'sstk_app' === $query->query['post_type'] && ! empty( $_GET['wpapp_php_version'] ) && empty( $filter_action ) ) {

			$qv               = &$query->query_vars;
			$qv['meta_query'] = array();

			$wpapp_php_version = sanitize_text_field( filter_input( INPUT_GET, 'wpapp_php_version', FILTER_UNSAFE_RAW ) );

			if ( $wpapp_php_version === $this->get_wpapp_default_php_version() ) {
				$qv['meta_query'][] = array(
					'relation' => 'OR',
					array(
						'key'     => 'wpapp_php_version',
						'value'   => $wpapp_php_version,
						'compare' => '=',
					),
					array(
						'key'     => 'wpapp_php_version',
						'compare' => 'NOT EXISTS',
					),
				);
			} else {
				$qv['meta_query'][] = array(
					'key'     => 'wpapp_php_version',
					'value'   => $wpapp_php_version,
					'compare' => '=',
				);
			}
		}

	}

	/**
	 * To add custom filtering options based on meta fields.
	 * This filter will be added on app listing screen at the backend
	 *
	 * @param  string $field_key field key.
	 * @param  string $first_option first option.
	 * @param array  $options options.
	 *
	 * @return string
	 */
	public function generate_meta_dropdown( $field_key, $first_option, $options ) {

		$html          = '';
		$html         .= sprintf( '<select name="%s" id="filter-by-%s">', $field_key, $field_key );
		$html         .= sprintf( '<option value="">%s</option>', $first_option );
		$get_field_key = sanitize_text_field( filter_input( INPUT_GET, $field_key, FILTER_UNSAFE_RAW ) );
		foreach ( $options as $key => $value ) {

			$selected = selected( $get_field_key, $key, false );
			$html    .= sprintf( '<option value="%s" %s>%s</option>', $key, $selected, $value );
		}
		$html .= '</select>';

		return $html;
	}

	/**
	 * Runs when the APP cleanup function is being called.
	 *
	 * Action Hook: sstk_cleanup_app_after
	 *
	 * @param int $app_id app id.
	 */
	public function sstk_cleanup_app_after( $app_id ) {

		update_post_meta( $app_id, 'sstk_app_wordpress-app_action_status', '' );
		update_post_meta( $app_id, 'sstk_app_wordpress-app_action', '' );
		update_post_meta( $app_id, 'sstk_app_wordpress-app_action_args', '' );

	}

	/**
	 * Runs when the SERVER cleanup function is being called.
	 *
	 * Action Hook: sstk_cleanup_server_after
	 *
	 * @param int $server_id server id.
	 */
	public function sstk_cleanup_server_after( $server_id ) {

		delete_post_meta( $server_id, 'sstk_server_wordpress-app_action' );
		delete_post_meta( $server_id, 'sstk_server_wordpress-app_action_status' );
		delete_post_meta( $server_id, 'sstk_server_wordpress-app_action_args' );
	}

	/**
	 * Sets the transient for cron check
	 * This will be set when user dismisses the notice for cron check
	 *
	 * Action Hook: wp_ajax_set_cron_check
	 */
	public function set_cron_check() {

		/* Nonce check */
		check_ajax_referer( 'sstk-admin', 'nonce' );

		/* Permission check - unsure that this is needed since the action is not destructive and might cause issues if the user sees the message and can't dismiss it because they're not an admin. */
		if ( ! sstk_is_admin() ) {
			wp_send_json_error( array( 'msg' => __( 'You are not authorized to perform this action - dismiss cron check.', 'simple-stack' ) ) );
		}

		/* Permissions passed - set transient. */
		set_transient( 'sstk_cron_check', 1, 12 * HOUR_IN_SECONDS );
		wp_die();

	}

	/**
	 * Sets the transient for php version check
	 * This will be set when user dismisses the notice for php version check
	 *
	 * Action Hook: wp_ajax_php_version_check
	 */
	public function php_version_check() {

		/* Nonce check */
		check_ajax_referer( 'sstk-admin', 'nonce' );

		/* Permission check - unsure that this is needed since the action is not destructive and might cause issues if the user sees the message and can't dismiss it because they're not an admin. */
		if ( ! sstk_is_admin() ) {
			wp_send_json_error( array( 'msg' => __( 'You are not authorized to perform this action - dismiss php version check.', 'simple-stack' ) ) );
		}

		/* Permissions passed - set transient. */
		set_transient( 'sstk_php_version_check', 1, 24 * HOUR_IN_SECONDS );
		wp_die();

	}

	/**
	 * Sets the transient for localhost check
	 * This will be set when user dismisses the notice for localhost check
	 *
	 * Action Hook: wp_ajax_localhost_version_check
	 */
	public function localhost_version_check() {

		/* Nonce check */
		check_ajax_referer( 'sstk-admin', 'nonce' );

		/* Permission check - unsure that this is needed since the action is not destructive and might cause issues if the user sees the message and can't dismiss it because they're not an admin. */
		if ( ! sstk_is_admin() ) {
			wp_send_json_error( array( 'msg' => __( 'You are not authorized to perform this action - dismiss localhost check.', 'simple-stack' ) ) );
		}

		/* Permissions passed - set transient. */
		set_transient( 'sstk_localhost_check', 1, 24 * HOUR_IN_SECONDS );
		wp_die();

	}

	/**
	 * This function checks to see if commands can be run
	 * on the server.
	 *
	 * It does this by checking a variety of metas that
	 * variously indicates something is going on with the server.
	 *
	 * Filter Hook: sstk_is_server_available_for_commands
	 *
	 * @param boolean $is_available   Current boolean that indicates whether the server is available.
	 * @param int     $server_id      Server id to check.
	 *
	 * @return boolean
	 */
	public function sstk_is_server_available_for_commands( $is_available, $server_id ) {

		// Only need to check if server is unavailable if $is_available is true - we're not going to override it if it's already false!
		if ( true === $is_available ) {
			if ( ( ! empty( get_post_meta( $server_id, 'sstk_server_wordpress-app_action', true ) ) ) || ( ! empty( get_post_meta( $server_id, 'sstk_server_wordpress-app_action_status', true ) ) ) ) {
				return false;
			}
		}

		// Is aptget running?
		if ( $this->sstk_is_aptget_running( $server_id ) ) {
			return false;
		}

		// Ok, so far the server is still available for commands.  Lets check the app records.
		$args = array(
			'post_type'      => 'sstk_app',
			'post_status'    => 'private',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'   => 'parent_post_id',
					'value' => $server_id,
				),
				array(
					'key'   => 'sstk_app_wordpress-app_action_status',
					'value' => 'in-progress',
				),
			),
		);

		$app_posts = get_posts( $args );

		if ( $app_posts ) {
			return false;
		}

		return $is_available;
	}

	/**
	 * Checks a special transient to see if aptget is running on the server.
	 *
	 * @param int $server_id The post id of the server.
	 *
	 * @return boolean
	 */
	public function sstk_is_aptget_running( $server_id ) {

		$is_running = false;

		$transient_name = 'sstk_server_aptget_status_' . $server_id;
		$running_status = get_transient( $transient_name );
		if ( 'running' === $running_status ) {
			$is_running = true;
		}

		return $is_running;

	}

	/**
	 * This function checks to see if commands can be run
	 * on the site.
	 *
	 * Filter Hook: sstk_is_site_available_for_commands [hook not used anywhere - for future use - this function is called directly for now.]
	 *
	 * @param boolean $is_available   Current boolean that indicates whether the server is available.
	 * @param int     $app_id App id to check.
	 *
	 * @return boolean
	 */
	public function is_site_available_for_commands( $is_available, $app_id ) {

		if ( true === $is_available ) {
			if ( true === $this->sstk_is_site_package_running( $app_id ) ) {
				return false;
			}
		}

		return $is_available;
	}

	/**
	 * Checks a special transient to see if site package might be running for an app/site.
	 *
	 * @param int $app_id The post id of the app/site.
	 *
	 * @return boolean
	 */
	public function sstk_is_site_package_running( $app_id ) {

		$is_running = false;

		$transient_name = 'sstk_site_package_running_' . $app_id;
		$running_status = get_transient( $transient_name );
		if ( 'running' === $running_status ) {
			$is_running = true;
		}

		return $is_running;
	}

	/**
	 * Return whether or not bash scripts can be run in site packages.
	 *
	 * You might not want to run them if SSTK is installed in a shared sites
	 * environment (saas).
	 *
	 * @param int $user_id User id running site packages - not used yet.
	 */
	public function sstk_can_user_execute_bash_scripts( $user_id = 0 ) {

		if ( ! defined( 'SSTK_CUSTOM_SCRIPTS_NO_BASH' ) || ( defined( 'SSTK_CUSTOM_SCRIPTS_NO_BASH' ) && false === (bool) SSTK_CUSTOM_SCRIPTS_NO_BASH ) ) {
			return true;
		}

		return false;

	}

	/**
	 * Return the REST API controller instance for a given name (base path)
	 *
	 * @param string $controller_name Name of controller.
	 *
	 * @return SSTK_REST_API_Controller_Base
	 */
	public function get_rest_controller( string $controller_name ) {
		return $this->rest_controllers[ $controller_name ];
	}

}
