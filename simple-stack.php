<?php
/** Modified for Simple Stack by One Dog Solutions on 2026-11-23. */
/**
Plugin Name: Simple Stack
Plugin URI: https://onedogsolutions.com
Description: Internal hosting control plane for One Dog Solutions. Deploys and manages OpenLiteSpeed servers and WordPress sites from inside wp-admin.
Version: 1.0.0
Requires at least: 6.5
Requires PHP: 8.1
Author: One Dog Solutions
Author URI: https://onedogsolutions.com
Domain Path: /languages
Text Domain: simple-stack
 */
require_once ABSPATH . 'wp-admin/includes/plugin.php';

/**
 * Class SSTK_Init.
 */
class SSTK_Init {

	/**
	 * Construct function.
	 */
	public function __construct() {

		$plugin_data = get_plugin_data( __FILE__ );

		if ( ! defined( 'SSTK_URL' ) ) {
			define( 'SSTK_URL', plugin_dir_url( __FILE__ ) );
			define( 'SSTK_PATH', plugin_dir_path( __FILE__ ) );
			define( 'SSTK_ROOT', dirname( plugin_basename( __FILE__ ) ) );
			define( 'SSTK_PLUGIN', plugin_basename( __FILE__ ) );
			define( 'SSTK_BASE_FILE', __FILE__ );
			define( 'SSTK_EXTENSION', $plugin_data['Name'] );
			define( 'SSTK_VERSION', $plugin_data['Version'] );
			define( 'SSTK_TEXTDOMAIN', 'simple-stack' );
			define( 'SSTK_REQUIRES', '2.0.3' );
			define( 'SSTK_REST_VERSION', '1' );
			define( 'SSTK_DB_VERSION', '1' );

			// Define the default brand colors for wp-admin.
			define( 'SSTK_PRIMARY_BRAND_COLOR', '#E91E63' );
			define( 'SSTK_SECONDARY_BRAND_COLOR', '#FF5722' );
			define( 'SSTK_TERTIARY_BRAND_COLOR', '#03114A' );
			define( 'SSTK_ACCENT_BG_COLOR', '#0d091a' );
			define( 'SSTK_MEDIUM_ACCENT_BG_COLOR', '#D3D3D3' );
			define( 'SSTK_MEDIUM_BG_COLOR', '#F3F3F5' );
			define( 'SSTK_LIGHT_BG_COLOR', '#FDFDFD' );
			define( 'SSTK_ALTERNATE_ACCENT_BG_COLOR', '#CFD8DC' );
			define( 'SSTK_POSITIVE_COLOR', '#008000' );
			define( 'SSTK_NEGATIVE_COLOR', '#8B0000' );
			define( 'SSTK_ALT_NEGATIVE_COLOR', '#FF0000' );
			define( 'SSTK_WHITE_COLOR', '#ffffff' );
			define( 'SSTK_TERMINAL_BG_COLOR', '#000000' );
			define( 'SSTK_TERMINAL_FG_COLOR', '#ffffff' );

			// Define the default brand colors for front-end.
			define( 'SSTK_FE_PRIMARY_BRAND_COLOR', '#E91E63' );
			define( 'SSTK_FE_SECONDARY_BRAND_COLOR', '#281d67' );
			define( 'SSTK_FE_TERTIARY_BRAND_COLOR', '#03114A' );
			define( 'SSTK_FE_ACCENT_BG_COLOR', '#0d091a' );
			define( 'SSTK_FE_MEDIUM_ACCENT_BG_COLOR', '#D3D3D3' );
			define( 'SSTK_FE_MEDIUM_BG_COLOR', '#F3F3F5' );
			define( 'SSTK_FE_LIGHT_BG_COLOR', '#FDFDFD' );
			define( 'SSTK_FE_ALTERNATE_ACCENT_BG_COLOR', '#CFD8DC' );
			define( 'SSTK_FE_POSITIVE_COLOR', '#008000' );
			define( 'SSTK_FE_NEGATIVE_COLOR', '#8B0000' );
			define( 'SSTK_FE_ALT_NEGATIVE_COLOR', '#FF0000' );
			define( 'SSTK_FE_WHITE_COLOR', '#ffffff' );

			// Define a variable that can be used for versioning scripts - required to force multisite to use different version numbers for each site.
			if ( is_multisite() ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					define( 'SSTK_SCRIPTS_VERSION', (string) get_current_blog_id() . '_' . (string) time() );
				} else {
					define( 'SSTK_SCRIPTS_VERSION', (string) get_current_blog_id() . '_' . (string) SSTK_VERSION );
				}
			} else {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					define( 'SSTK_SCRIPTS_VERSION', (string) time() );
				} else {
					define( 'SSTK_SCRIPTS_VERSION', (string) SSTK_VERSION );
				}
			}
		}

		/* Use init hook to load up required files */
		add_action( 'init', array( $this, 'required_files' ), -20 );

		/* Create a custom schedule for 1 minute */
		add_filter( 'cron_schedules', array( $this, 'custom_cron_schedule' ) );

		/* Activation and deactivation hooks */
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		/* Show some notices as required */
		add_action( 'admin_notices', array( $this, 'sstk_global_admin_notice' ) );

		/* Show documentation and quick-start links in the plugin list. */
		add_filter( 'plugin_row_meta', array( $this, 'sstk_append_support_and_faq_links' ), 10, 4 );

		/* Load languages files */
		add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );

		/* Send email to admin if critical crons aren't running. */
		add_action( 'shutdown', array( $this, 'send_email_for_absent_crons' ), 20 );

		/* Show version number in admin footer */
		add_filter( 'admin_footer_text', array( $this, 'admin_footer_text' ), 10, 1 );
	}

	/**
	 * Create cron timers for use by other sstk functions.
	 * Three timers - 1 min, 2 min and 15 min.
	 *
	 * @param Array $schedules schedules.
	 */
	public function custom_cron_schedule( $schedules ) {
		// if this schedule is not already defined by someone else...
		if ( ! isset( $schedules['sstk_every_minute'] ) ) {
			$schedules['sstk_every_minute'] = array(
				'interval' => MINUTE_IN_SECONDS,
				'display'  => __( 'Every minute', 'simple-stack' ),
			);
		}
		if ( ! isset( $schedules['sstk_every_two_minutes'] ) ) {
			$schedules['sstk_every_two_minutes'] = array(
				'interval' => 120,
				'display'  => __( 'Every 2 minutes', 'simple-stack' ),
			);
		}
		if ( ! isset( $schedules['sstk_every_fifteen_minutes'] ) ) {
			$schedules['sstk_every_fifteen_minutes'] = array(
				'interval' => 900,
				'display'  => __( 'Every 15 minutes', 'simple-stack' ),
			);
		}
		return $schedules;
	}

	/**
	 * Fires on activation of plugin.
	 *
	 * @param String $network_wide network_wide.
	 */
	public function activate( $network_wide ) {

		require_once SSTK_PATH . 'includes/core/class-sstk-base.php';
		require_once SSTK_PATH . 'includes/core/class-sstk-posts-base.php';
		require_once SSTK_PATH . 'includes/core/apps/class-sstk-app.php';
		require_once SSTK_PATH . 'includes/core/class-sstk-posts-permission-type.php';
		require_once SSTK_PATH . 'includes/core/class-sstk-dns.php';
		require_once SSTK_PATH . 'includes/core/functions.php';
		require_once SSTK_PATH . 'includes/core/functions-kses.php';
		require_once SSTK_PATH . 'includes/core/functions-icons.php';
		require_once SSTK_PATH . 'includes/core/functions-metabox.php';

		$this->sstk_load_wpapp_traits();
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/class-wordpress-app.php';
		SSTK_WORDPRESS_APP::activate( $network_wide );

		SSTK_POSTS_PERMISSION_TYPE::activate( $network_wide );

		require_once SSTK_PATH . 'includes/core/class-sstk-roles-capabilities.php';
		SSTK_ROLES_CAPABILITIES::activate( $network_wide );

		$this->sstk_load_core_traits();
		require_once SSTK_PATH . 'includes/core/class-sstk-posts-app-server.php';
		require_once SSTK_PATH . 'includes/core/class-sstk-posts-app.php';
		SSTK_POSTS_APP_SERVER::activate( $network_wide );
		SSTK_POSTS_APP::activate( $network_wide );

		/* Custom tables for DNS and PROVIDERS */
		if ( true === sstk_is_custom_dns_provider_tables_enabled() ) {
			require_once SSTK_PATH . 'includes/core/custom-table/api/class-sstk-custom-table-api.php';
			require_once SSTK_PATH . 'includes/core/custom-table/api/class-sstk-ct-provider-api.php';
			require_once SSTK_PATH . 'includes/core/custom-table/api/class-sstk-ct-dns-provider-api.php';
			require_once SSTK_PATH . 'includes/core/custom-table/api/class-sstk-ct-dns-zone-api.php';
			require_once SSTK_PATH . 'includes/core/custom-table/api/class-sstk-ct-dns-zone-record-api.php';

			require_once SSTK_PATH . 'includes/core/apps/wordpress-app/public/class-wordpress-app-public.php';
			require_once SSTK_PATH . 'required_plugins/mb-custom-table/mb-custom-table.php';
			require_once SSTK_PATH . 'includes/core/custom-table/class-sstk-custom-table.php';
			require_once SSTK_PATH . 'includes/core/custom-table/class-sstk-provider.php';
			require_once SSTK_PATH . 'includes/core/custom-table/class-sstk-dns-provider.php';
			require_once SSTK_PATH . 'includes/core/custom-table/class-sstk-dns-zone.php';
			require_once SSTK_PATH . 'includes/core/custom-table/class-sstk-dns-zone-record.php';

			SSTK_MB_Custom_Table::Activate( $network_wide );
		}

		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/public/class-wordpress-app-public.php';
		SSTK_WORDPRESS_APP_PUBLIC::activate( $network_wide );

		require_once SSTK_PATH . 'includes/core/class-sstk-posts-notify-user.php';
		SSTK_NOTIFY_USER::activate( $network_wide );

		if ( defined( 'SSTK_DISABLE_EMAIL_NOTIFICATIONS' ) && ( false === SSTK_DISABLE_EMAIL_NOTIFICATIONS ) ) {
			require_once SSTK_PATH . 'includes/core/class-sstk-email-notifications.php';
			SSTK_EMAIL_NOTIFICATIONS::activate( $network_wide );
		}

		// Set transient for not showing the cron check message immediately as soon as plugin is activated.
		// It will be shown after 2 minutes if crons are not scheduled and loaded.
		set_transient( 'sstk_loaded_timeout', 1, 2 * MINUTE_IN_SECONDS );

		require_once SSTK_PATH . 'includes/core/class-sstk-posts-log.php';
		require_once SSTK_PATH . 'includes/core/class-sstk-posts-pending-tasks-log.php';
		SSTK_PENDING_TASKS_LOG::activate( $network_wide );

		flush_rewrite_rules();
	}

	/**
	 * Fires on deactivation of plugin.
	 *
	 * @param String $network_wide network_wide.
	 */
	public function deactivate( $network_wide ) {
		require_once SSTK_PATH . 'includes/core/apps/class-sstk-app.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/class-wordpress-app.php';

		SSTK_WORDPRESS_APP::deactivate( $network_wide );

		require_once SSTK_PATH . 'includes/core/class-sstk-posts-notify-user.php';
		SSTK_NOTIFY_USER::deactivate( $network_wide );

		if ( sstk_email_notifications_allowed() ) {
			require_once SSTK_PATH . 'includes/core/class-sstk-email-notifications.php';
			SSTK_EMAIL_NOTIFICATIONS::deactivate( $network_wide );
		}

		require_once SSTK_PATH . 'includes/core/class-sstk-posts-log.php';
		require_once SSTK_PATH . 'includes/core/class-sstk-posts-pending-tasks-log.php';
		SSTK_PENDING_TASKS_LOG::deactivate( $network_wide );
	}

	/**
	 * Include additional files as needed
	 */
	public function required_files() {
		/* These two files ensure that certain third party plugins are available - primarily METABOX.IO */
		require_once SSTK_PATH . 'required_plugins/class-tgm-plugin-activation.php';
		require_once SSTK_PATH . 'required_plugins/sstk-required-plugins.php';
		/* End ensure third party plugins are available */

		/* Include the SETTINGS PAGE and other related metabox.io extension files */
		require_once SSTK_PATH . 'required_plugins/mb-settings-page/mb-settings-page.php';
		require_once SSTK_PATH . '/required_plugins/mb-admin-columns/mb-admin-columns.php';
		require_once SSTK_PATH . '/required_plugins/meta-box-conditional-logic/meta-box-conditional-logic.php';
		require_once SSTK_PATH . '/required_plugins/meta-box-tabs/meta-box-tabs.php';
		require_once SSTK_PATH . '/required_plugins/meta-box-tooltip/meta-box-tooltip.php';
		require_once SSTK_PATH . '/required_plugins/mb-term-meta/mb-term-meta.php';
		require_once SSTK_PATH . '/required_plugins/meta-box-columns/meta-box-columns.php';
		require_once SSTK_PATH . '/required_plugins/meta-box-group/meta-box-group.php';
		require_once SSTK_PATH . '/required_plugins/mb-user-meta/mb-user-meta.php';
		require_once SSTK_PATH . '/required_plugins/mb-custom-table/mb-custom-table.php';

		/* Include custom metabox.io fields we created. */
		require_once SSTK_PATH . 'includes/core/metabox-io-custom-fields/sstk-card-container-field.php';

		/* Load up our files */
		require_once SSTK_PATH . 'includes/core/functions.php';
		require_once SSTK_PATH . 'includes/core/functions-kses.php';
		require_once SSTK_PATH . 'includes/core/functions-icons.php';
		require_once SSTK_PATH . 'includes/core/functions-metabox.php';
		require_once SSTK_PATH . 'includes/core/class-sstk-custom-fields.php';
		require_once SSTK_PATH . 'includes/core/class-sstk-roles-capabilities.php';
		require_once SSTK_PATH . 'includes/core/class-sstk-server-statistics.php';
		$this->sstk_load_core_traits();
		require_once SSTK_PATH . 'includes/core/class-sstk-base.php';
		require_once SSTK_PATH . 'includes/core/class-sstk-dns.php';
		require_once SSTK_PATH . 'includes/core/class-sstk-posts-base.php';
		require_once SSTK_PATH . 'includes/core/class-sstk-posts-app.php';
		require_once SSTK_PATH . 'includes/core/class-sstk-posts-app-server.php';
		require_once SSTK_PATH . 'includes/core/class-sstk-posts-cloud-provider.php';
		require_once SSTK_PATH . 'includes/core/class-sstk-posts-log.php';
		require_once SSTK_PATH . 'includes/core/class-sstk-posts-notify-log.php';
		require_once SSTK_PATH . 'includes/core/class-sstk-posts-notify-user.php';
		require_once SSTK_PATH . 'includes/core/class-sstk-posts-notify-sent-log.php';
		if ( sstk_email_notifications_allowed() ) {
			require_once SSTK_PATH . 'includes/core/class-sstk-email-notifications.php';
		}
		require_once SSTK_PATH . 'includes/core/class-sstk-posts-ssh-log.php';
		require_once SSTK_PATH . 'includes/core/class-sstk-posts-error-log.php';
		require_once SSTK_PATH . 'includes/core/class-sstk-posts-command-log.php';
		require_once SSTK_PATH . 'includes/core/class-sstk-posts-pending-tasks-log.php';
		require_once SSTK_PATH . 'includes/core/class-sstk-app-expiration.php';
		require_once SSTK_PATH . 'includes/core/class-sstk-server.php';
		require_once SSTK_PATH . 'includes/core/class-sstk-settings.php';
		require_once SSTK_PATH . 'includes/core/class-sstk-setup.php';

		require_once SSTK_PATH . 'includes/core/class-sstk-posts-team.php';
		require_once SSTK_PATH . 'includes/core/class-sstk-posts-permission-type.php';

		require_once SSTK_PATH . 'includes/core/providers/class-cloud-provider-api.php';

		/* Simple Stack cloud providers. Guarded so a partial checkout still boots. */
		if ( file_exists( SSTK_PATH . 'includes/core/providers/class-vultr-v2.php' ) ) {
			require_once SSTK_PATH . 'includes/core/providers/class-vultr-v2.php';
		}
		if ( file_exists( SSTK_PATH . 'includes/core/providers/class-upcloud.php' ) ) {
			require_once SSTK_PATH . 'includes/core/providers/class-upcloud.php';
		}

		require_once SSTK_PATH . 'includes/core/apps/class-sstk-app.php';
		require_once SSTK_PATH . 'includes/core/apps/class-sstk-app-settings.php';
		require_once SSTK_PATH . 'includes/core/apps/class-sstk-ssh.php';

		// Handle admin notices.
		if ( is_admin() ) {
			require_once SSTK_PATH . 'includes/core/functions-handle-admin-notices.php';
		}

		// SSTK Better Crons.
		require_once SSTK_PATH . 'includes/core/class-sstk-better-crons.php';

		/**
		* For the WP App
		*/
		/* @TODO: Need to find a more dynamic way to load these by letting apps register themselves at the right time and having them load up their own files */
		$this->sstk_load_wpapp_traits();
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/class-wordpress-app.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/class-wordpress-app-settings.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/class-wordpress-ssh.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/class-wordpress-posts-site-package.php';

		// Remaining files for the WP APP.
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/functions-classes-wordpress-app.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/public/class-wordpress-app-public.php';

		/**
		 * Remaining core files.
		 */
		require_once SSTK_PATH . 'includes/core/simple-stack.php';
		require_once SSTK_PATH . 'includes/core/class-sstk-server.php';
		require_once SSTK_PATH . 'includes/core/functions-classes.php';
		
		/**
		 * Simple Stack REST layer (simple-stack/v1).
		 *
		 * Loaded after the app classes so the controllers can reach the app engine and
		 * the provider registry. The registrar hooks rest_api_init, which fires well
		 * after this point, so instantiating here is safe. plugins_loaded is already in
		 * the past by the time required_files() runs on init.
		 */
		require_once SSTK_PATH . 'includes/core/api/class-sstk-rest-api.php';
		SSTK_REST_API::load_files();
		sstk_rest_api();

		/**
		 * Now fire up the main class
		 * The constructor should place
		 * a reference to  itself inside the
		 * $GLOBAL variable.
		 *
		 * @TODO: There's likely a better way to do this - maybe an action hook such as plugins-loaded or init
		 * that calls the constructor function directly?
		 */
		$sstk_throwaaway_var = new SSTK_CORE();

		/**
		 * Finally, maybe ask for setup using Setup wizard.
		 * Proceed only if both options 'sstk_plugin_setup' & 'sstk_skip_wizard_setup' = false
		 * 'sstk_plugin_setup' will be added at the end of wizard steps
		 * 'sstk_skip_wizard_setup' will be set to true if user chose to skip wizard from admin notice
		 */
		if ( ! get_option( 'sstk_plugin_setup', false ) && ! get_option( 'sstk_skip_wizard_setup', false ) ) {
			require_once SSTK_PATH . 'includes/core/class-sstk-setup-wizard.php';
		}

	}

	/**
	 * Load CORE Trait Files
	 */
	public function sstk_load_core_traits() {
		require_once SSTK_PATH . 'includes/core/traits/get_set_post_type.php';
		require_once SSTK_PATH . 'includes/core/traits/metaboxes_for_taxonomies_for_servers_and_apps.php';
		require_once SSTK_PATH . 'includes/core/traits/metaboxes_for_teams_for_servers_and_apps.php';
		require_once SSTK_PATH . 'includes/core/traits/metaboxes_for_labels_notes_for_servers_and_apps.php';
	}


	/**
	 * Load WP APP Trait Files
	 */
	public function sstk_load_wpapp_traits() {
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/traits/traits-for-class-wordpress-app/tabs-security.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/traits/traits-for-class-wordpress-app/commands-and-logs.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/traits/traits-for-class-wordpress-app/script-handlers.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/traits/traits-for-class-wordpress-app/after-prepare-server.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/traits/traits-for-class-wordpress-app/push-commands.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/traits/traits-for-class-wordpress-app/admin-columns.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/traits/traits-for-class-wordpress-app/backup.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/traits/traits-for-class-wordpress-app/multi-tenant-app.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/traits/traits-for-class-wordpress-app/upgrade.php';
		require_once SSTK_PATH . 'includes/core/apps/wordpress-app/traits/traits-for-class-wordpress-app/unused.php';
	}

	/**
	 * Warn user of a number of issues that could affect the clean running of sstk:
	 *  1. If encryption key in wp-config is not defined.
	 *  2. Permalink structure is incorrect.
	 *  3. Certain files cannot be read.
	 *  4. Certain crons are not running on a regular basis.
	 */
	public function sstk_global_admin_notice() {

		// Only show messages to admins.
		if ( ! sstk_is_admin() ) {
			return;
		}

		$screen     = get_current_screen();
		$post_types = array( 'sstk_app_server', 'sstk_app', 'sstk_team', 'sstk_permission_type', 'sstk_command_log', 'sstk_ssh_log', 'sstk_error_log', 'sstk_pending_log' );

		$php_version    = phpversion();
		$php_version_id = str_replace( '.', '0', $php_version );

		// Checks to see if "php version check" transient is set or not. If not set then show an admin notice.
		if ( ! get_transient( 'sstk_php_version_check' ) && is_object( $screen ) && in_array( $screen->post_type, $post_types, true ) ) {
			// Here 80100 is PHP version 8.1.0.
			if ( (int) $php_version_id < 80100 ) {
				$class = 'notice notice-error is-dismissible sstk-php-version-check';
				/* translators: %s php version */
				$message = sprintf( __( '<strong>Simple Stack requires PHP 8.1 or newer. You are running %s.</strong>', 'simple-stack' ), $php_version );
				/* Translators: %2$s is a set of CSS classes; %2$s is a text message about the PHP version being incompatible. */
				printf( '<div data-dismissible="notice-php-warning" class="%2$s"><p>%3$s</p></div>', wp_create_nonce( 'sstk-admin-dismissible-notice' ), $class, $message );
			}
		}

		// Are we running on localhost?  Of so throw error.
		if ( in_array( isset( $_SERVER['SERVER_ADDR'] ) ? $_SERVER['SERVER_ADDR'] : '', array( '127.0.0.1', '::1' ), true ) ) {
			if ( ! sstk_get_early_option( 'hide-local-host-warning' ) ) {
				// Checks to see if "localhost check" transient is set or not. If not set then show an admin notice.
				if ( ! get_transient( 'sstk_localhost_check' ) && is_object( $screen ) && in_array( $screen->post_type, $post_types, true ) ) {
					$class   = 'notice notice-error is-dismissible sstk-localhost-check';
					$message = __( '<strong>Simple Stack cannot run on a localhost server or a server that cannot be reached from the internet.</strong>', 'simple-stack' );
					/* Translators: %2$s is a set of CSS classes; %2$s is a text message about Simple Stack not being compatible with localhost installations. */
					printf( '<div data-dismissible="notice-localhost-warning" class="%2$s"><p>%3$s</p></div>', wp_create_nonce( 'sstk-admin-dismissible-notice' ), $class, $message );
				}
			}
		}

		if ( ! defined( 'SSTK_ENCRYPTION_KEY' ) ) {
			$class = 'notice notice-error is-dismissible';
			/* translators: %s read more */
			$message = sprintf( __( '<strong>SSTK_ENCRYPTION_KEY</strong> is not defined in wp-config.php. Define an encryption key there so server passwords and private SSH keys are stored encrypted. Until then a persistent fallback key has been created and stored in the database. %s', 'simple-stack' ), '<a href="https://github.com/onedogsolutions/simple-stack" target="_blank">' . __( 'Read More', 'simple-stack' ) . '</a>' );
			/* Translators: %1$s is a set of CSS classes; %2$s is a text message about the encryption key being required in wp-config.php. */
			printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message );
		}

		if ( defined( 'SSTK_ENCRYPTION_KEY' ) && ( 'your very long encryption key goes here' === SSTK_ENCRYPTION_KEY ) ) {
			$class = 'notice notice-error is-dismissible';
			/* translators: %s read more */
			$message = sprintf( __( '<strong>SSTK_ENCRYPTION_KEY</strong> is defined in wp-config.php but uses the example key from the documentation. Set a new encryption key. %s', 'simple-stack' ), '<a href="https://github.com/onedogsolutions/simple-stack" target="_blank">' . __( 'Read More', 'simple-stack' ) . '</a>' );
			/* Translators: %1$s is a set of CSS classes; %2$s is a text message about the encryption key not being correct. */
			printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message );
		}

		// Check to make sure we're not using the default permalink structure.
		$structure = get_option( 'permalink_structure' );
		if ( empty( $structure ) ) {
			// means that default structure is in use which is no good for callbacks.
			$class   = 'notice notice-error';
			$message = __( 'Warning: Simple Stack cannot use the WordPress default permalink. Please change the permalinks option to something other than <em>plain.</em> This can be done under the WordPress <strong>SETTINGS->Permalinks</strong> menu option.', 'simple-stack' );
			/* Translators: %1$s is a set of CSS classes; %2$s is a text message about permalinks not being set properly. */
			printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message );
		}

		// Checks to see if "text files are readable" transient is set or not. If not set then show an admin notice.
		if ( ! get_transient( 'sstk_readable_check' ) && is_object( $screen ) && in_array( $screen->post_type, $post_types, true ) ) {
			$class   = 'notice notice-error is-dismissible sstk-readability-check';
			$message = __( '<strong>Simple Stack: Warning</strong> - The <em>includes/core/apps/wordpress-app/scripts/v2/raw/ </em> folder on your WordPress server does not allow text files to be read by browsers and other outside viewers. This folder contains the script files that we execute on your WordPress server. Please modify your web server configuration to allow .txt files in this folder to be readable. Otherwise, the Simple Stack plugin will not be able to manage your servers and sites. <br /><br /> Note that you CANNOT run this plugin on a local machine - it must be run on a server that is reachable from the public internet. <br /><br /> If you dismiss this message but do not resolve the issue, it will appear again in 12 hours. <br /><br /> <a href="" id="sstk-check-again">Check Again</a>', 'simple-stack' );
			/* Translators: %1$s is a set of CSS classes; %2$s is a text message about the files that are not readable but should be. */
			printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message );
		}

		// Make sure all our CRONS are running or, if not, that the user has seen and dismissed the warning notice.
		$not_loaded_crons = $this->get_list_of_absent_crons();

		// Checks to see if "cron check" transient is set or not. If not set then show an admin notice.
		if ( ! get_transient( 'sstk_loaded_timeout' ) && ! get_transient( 'sstk_cron_check' ) && is_object( $screen ) && in_array( $screen->post_type, $post_types, true ) ) {
			$class            = 'notice notice-error is-dismissible sstk-cron-check';
			$not_loaded_crons = implode( ', ', $not_loaded_crons );
			$message          = __( '<strong>Simple Stack: Warning</strong> - ' . $not_loaded_crons . ' cron(s) are not running.', 'simple-stack' );
			/* Translators: %1$s is a set of CSS classes; %2$s is a text message about crons that are not currently running. */
			printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message );
		}

		// If our REDIS plugin is active, show a message since that's no longer necessary for REDIS functionality.
		if ( class_exists( 'SSTK_Redis_Init' ) ) {
			$class   = 'notice notice-error sstk-redis-deprecation-check';
			$message = __( '<strong>Simple Stack: Warning</strong> - The legacy <em>REDIS</em> plugin is no longer required. Please deactivate it!', 'simple-stack' );
			/* Translators: %1$s is a set of CSS classes; %2$s is a text message about the REDIS plugin not being required. */
			printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message );
		}
	}

	/**
	 * If critical crons aren't running, send email to admin.
	 *
	 * Action Hook: Shutdown.
	 */
	public function send_email_for_absent_crons() {

		// Exit if option to suppress sending these emails is turned on.
		if ( true === (bool) sstk_get_early_option( 'sstk_do_not_send_cron_warning_emails' ) ) {
			return;
		}

		// Get list of crons that aren't running.
		$not_loaded_crons = $this->get_list_of_absent_crons();

		// Send email to site administrator if we have some crons that are not running.
		if ( ! empty( $not_loaded_crons ) ) {

			$str_crons = implode( ', ', $not_loaded_crons );
			$to        = get_site_option( 'admin_email' );
			$headers   = array( 'Content-Type: text/html; charset=UTF-8' );
			$subject   = sprintf(
				/* translators: %s: Site title. */
				__( '[%s] - One or more critical crons are not running...', 'simple-stack' ),
				wp_specialchars_decode( get_option( 'blogname' ) )
			);
			$body   = array();
			$body[] = __( 'Hello Admin,', 'simple-stack' );
			$body[] = '';
			$body[] = __( '<strong>Simple Stack: Warning</strong> - certain critical CRONS are not running on your site.  Below are the ones that appear to be missing:', 'simple-stack' );
			$body[] = '';
			$body[] = $str_crons;
			$body[] = '';
			$body[] = __( 'It is possible that this is a minor hiccup or false positive and the cron(s) are still running. You can use the free WP CRONTROL plugin to examine running crons to see if the crons are still present and active.', 'simple-stack' );
			$body[] = '';
			$body[] = __( 'Before contacting support, please try to disable and renable the plugin to reactivate crons. Additionally, please verify that your WP CRON is firing every 1 minute - either from enough frequent site traffic, a native LINUX cron process or better yet, the Simple Stack Better Crons method.', 'simple-stack' );
			$body[] = '';
			$body[] = __( '--------', 'simple-stack' );
			$body[] = '';
			$body[] = __( 'If you do not want to receive these emails you can turn them off in the settings area under the MISC tab.', 'simple-stack' );
			$body[] = '';
			$body[] = __( 'Thanks,', 'simple-stack' );
			$body[] = '';
			$body[] = __( '- Your Simple Stack Management Dashboard BOT.', 'simple-stack' );

			// Apply filters to each var for the outgoing email.
			$headers = apply_filters( 'sstk_send_email_for_absent_crons_to', $headers );
			$to      = apply_filters( 'sstk_send_email_for_absent_crons_to', $to );
			$subject = apply_filters( 'sstk_send_email_for_absent_crons_subject', $subject );
			$body    = apply_filters( 'sstk_send_email_for_absent_crons_body', $body );

			$email = array(
				'to'      => $to,
				'subject' => $subject,
				'body'    => implode( "\n" . PHP_EOL . '<br />', $body ),
				'headers' => $headers,
			);

			if ( defined( 'SSTK_CRONS_NOT_RUNNING_EMAIL_TEST' ) && SSTK_CRONS_NOT_RUNNING_EMAIL_TEST ) {
				// When this constant is set, we're always going to send an email - useful for TESTING.
				wp_mail( $email['to'], wp_specialchars_decode( $email['subject'] ), $email['body'], $email['headers'] );
			} else {
				// Send an email every 8 hours. Start by getting the current date and time.
				$current_datetime     = gmdate( 'Y-m-d H:i:s' );
				$str_current_datetime = strtotime( $current_datetime );

				// Calculate the next date/time to send an alert email. The option sstk_crons_not_running_email_next_send_date is where we store the next time we have to send an email if all our crons aren't running.
				$next_email_send_date     = get_option( 'sstk_crons_not_running_email_next_send_date' );
				$str_next_email_send_date = strtotime( $next_email_send_date );

				if ( $str_current_datetime >= $str_next_email_send_date ) {
					$next_send_time = gmdate( 'Y-m-d H:i:s', strtotime( '+8 hours' ) );
					update_option( 'sstk_crons_not_running_email_next_send_date', $next_send_time );
					wp_mail( $email['to'], wp_specialchars_decode( $email['subject'] ), $email['body'], $email['headers'] );
				}
			}
		}

	}

	/**
	 * Returns a list of critical crons that aren't running.
	 *
	 * Only returns the list if the user has not dismissed the notification at the top of the screen.
	 */
	public function get_list_of_absent_crons() {

		$not_loaded_crons = array();

		if ( ! get_transient( 'sstk_cron_check' ) ) {
			$sstk_crons = array( 'send_email_alert_for_long_pending_tasks', 'do_deferred_actions_for_server', 'do_deferred_actions_for_app', 'wordpress_file_watcher_delete_temp_files', 'scan_new_notifications_to_send', 'clean_up_pending_logs' );
			$sstk_crons = apply_filters( 'sstk_crons_needing_active_check', $sstk_crons );

			foreach ( $sstk_crons as $cron ) {
				$cron_transient = "sstk_{$cron}_is_active";

				if ( ! get_transient( $cron_transient ) ) {
					$not_loaded_crons[] = $cron;
				}
			}

			// If empty means all crons are loaded properly, then set the transient so that we can recheck once per hour.
			// This transient will also be set if the user dismisses the notice they get at the top of the screen.
			// Here we will set it so that we'll recheck in 1 hour.  If it gets set when the user dismisses the notice it will be set for 12 hours.
			// See the function set_cron_check() in file class-wordpress-app.php.
			if ( count( $not_loaded_crons ) === 0 ) {
				set_transient( 'sstk_cron_check', 1, 1 * HOUR_IN_SECONDS );
			}
		} else {
			// Transient is set.  But the time remaining can sometimes be negative. While we're not sure why that happens, if it is, delete it!
			$time_left = (int) sstk_get_transient_remaining_time_in_mins( 'sstk_cron_check' );

			if ( false === $time_left ) {
				// looks like an object cache is in use so we can't get the time left data.  Therefore do nothing.
			} else {
				if ( $time_left < 0 ) {
					delete_transient( 'sstk_cron_check' );
				}
			}
		}

		return $not_loaded_crons;

	}

	/**
	 * Filters the array of row meta for each/specific plugin in the Plugins list table.
	 * Appends additional links below each/specific plugin on the plugins page.
	 *
	 * Action Hook: plugin_row_meta
	 *
	 * @since 4.15.1
	 *
	 * @access  public
	 * @param   array  $links_array            An array of the plugin's metadata.
	 * @param   string $plugin_file_name       Path to the plugin file.
	 * @param   array  $plugin_data            An array of plugin data.
	 * @param   string $status                 Status of the plugin.
	 *
	 * @return  array  $links_array New links to be added to the plugin list.
	 */
	public function sstk_append_support_and_faq_links( $links_array, $plugin_file_name, $plugin_data, $status ) {
		if ( plugin_basename( __FILE__ ) === $plugin_file_name ) {

			// You can still use `array_unshift()` to add links at the beginning.
			$links_array[] = '<a href="https://github.com/onedogsolutions/simple-stack" target="_blank">Repository</a>';

			$settings_link = admin_url( 'admin.php?page=sstk_settings' );
			$links_array[] = '<a href="' . $settings_link . '">Settings</a>';
		}

		return $links_array;
	}

	/**
	 * Add our version number and message to the admin footer area.
	 *
	 * @since 5.3.0
	 *
	 * Filter Hook: admin_footer_text
	 *
	 * @param string $msg The existing message.
	 *
	 * @return string
	 */
	public function admin_footer_text( $msg ) {

		// Bail if we're not far enough into the initialization process to get the screen id.
		if ( ! function_exists( 'get_current_screen' ) ) {
			return $msg;
		}

		// Message to show defaults to the original incoming message string.
		$return = $msg;

		$screen = get_current_screen();

		/* @TODO: The list of post types and taxonomies used below should be a global function that is filtered and called by add-ons to add their own CPT to the arrays. */
		if ( ( is_object( $screen ) && ( in_array( $screen->post_type, array( 'sstk_app', 'sstk_app_server', 'sstk_cloud_provider', 'sstk_ssh_log', 'sstk_team', 'sstk_command_log', 'sstk_pending_log', 'sstk_error_log', 'sstk_schedules', 'sstk_snapshots', 'sstk_permission_type', 'sstk_notify_log', 'sstk_notify_user', 'sstk_notify_sent' ), true ) || in_array( $screen->taxonomy, array( 'sstk_app_group', 'sstk_app_server_group', 'sstk_reporting_group' ), true ) ) ) ) {

			if ( defined( 'SSTK_LONG_NAME' ) && ! empty( SSTK_LONG_NAME ) ) {
				$product_name = SSTK_LONG_NAME;
			} else {
				$product_name = 'Simple Stack';
			}

			/* Translators: %1$s is the SSTK Product Name; %2$s is the SSTK product version. */
			$return = sprintf( __( 'Powered by %1$s %2$s.', 'simple-stack' ), $product_name, SSTK_VERSION ) . '<br />' . $return;

		}

		return $return;

	}


	/**
	 * Load the plugin text domain for translation.
	 *
	 * With the introduction of plugins language packs in WordPress loading the textdomain is slightly more complex.
	 *
	 * We now have 3 steps:
	 *
	 * 1. Check for the language pack in the WordPress core directory
	 * 2. Check for the translation file in the plugin's language directory
	 * 3. Fallback to loading the textdomain the classic way
	 *
	 * @since    2.8.0
	 * @return boolean True if the language file was loaded, false otherwise
	 */
	public function load_plugin_textdomain() {

		$lang_dir       = trailingslashit( SSTK_ROOT ) . 'languages/';
		$lang_path      = trailingslashit( SSTK_PATH ) . 'languages/';
		$locale         = apply_filters( 'plugin_locale', get_locale(), 'simple-stack' );
		$mofile         = "simple-stack-$locale.mo";
		$glotpress_file = WP_LANG_DIR . '/plugins/simple-stack/' . $mofile;

		// Look for the GlotPress language pack first of all.
		if ( file_exists( $glotpress_file ) ) {
			$language = load_textdomain( 'simple-stack', $glotpress_file );
		} elseif ( file_exists( $lang_path . $mofile ) ) {
			$language = load_textdomain( 'simple-stack', $lang_path . $mofile );
		} else {
			$language = load_plugin_textdomain( 'simple-stack', false, $lang_dir );
		}

		return $language;

	}

	/**
	 * Activation hook
	 *
	 * *** This function not used yet.
	 */
	public function activation_hook() {
		// first install.
		$version = get_option( 'sstk_version' );
		if ( ! $version ) {
			update_option( 'sstk_last_version_upgrade', SSTK_VERSION );
		}

		if ( SSTK_VERSION !== $version ) {
			update_option( 'sstk_version', SSTK_VERSION );
		}

		// run setup.
		if ( ! class_exists( 'SSTK_Setup' ) ) {
			require_once SSTK_PATH . 'includes/core/class-sstk-setup.php';
		}

		$sstk_setup = new SSTK_Setup();
		$sstk_setup->run_setup();
	}

}

/**
 * Get SSTK running.
 */
$sstk_throwaway = new SSTK_Init();
