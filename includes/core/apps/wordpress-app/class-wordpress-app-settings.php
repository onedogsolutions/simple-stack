<?php
/** Modified for Simple Stack by One Dog Solutions on 2026-11-23. */
/**
 * WordPress app settings.
 *
 * @package simple-stack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WORDPRESS_APP_SETTINGS
 */
class WORDPRESS_APP_SETTINGS extends SSTK_APP_SETTINGS {

	/**
	 * Holds a reference to this class
	 *
	 * @var $instance instance.
	 */
	private static $instance;

	/**
	 * Static function that can initialize the class
	 * and return an instance of itself.
	 *
	 * @TODO: This just seems to duplicate the constructor
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Wordpress_SERVER_APP_SETTINGS constructor.
	 */
	public function __construct() {

		// setup WordPress and settings hooks.
		$this->hooks();

	}

	/**
	 * Hook into WordPress and other plugins as needed.
	 */
	private function hooks() {

		add_filter( 'sstk_settings_tabs', array( &$this, 'settings_tabs' ) );  // add a new tab to the settings page.

		add_filter( 'sstk_settings_metaboxes', array( &$this, 'settings_metaboxes' ) );  // add new metaboxes to our new tab on the settings pages.

		// Enable filter on the private key to encrypt it when its being stored.
		add_filter( 'rwmb_wordpress_app_aws_secret_key_value', array( &$this, 'encrypt_aws_secret_key' ), 10, 3 );

		// Enable filter on the private key field to decrypt it when its being retrieved.
		add_filter( 'rwmb_wordpress_app_aws_secret_key_field_meta', array( &$this, 'decrypt_aws_secret_key' ), 10, 3 );

		// Filter for change the button text.
		add_filter( 'rwmb_media_add_string', array( &$this, 'sstk_upload_logo_change_add_string' ) );

		// Filter for change the header logo image.
		add_filter( 'sstk_popup_header_logo', array( &$this, 'sstk_change_popup_header_logo' ) );

		// Enable filter on the default wp password field to encrypt it when its being stored.
		add_filter( 'rwmb_wordpress_app_default_wp_password_value', array( &$this, 'encrypt' ), 10, 3 );

		// Enable filter on the default wp user id field to encrypt it when its being stored.
		add_filter( 'rwmb_wordpress_app_default_wp_user_id_value', array( &$this, 'encrypt' ), 10, 3 );

		// Enable filter on the solidwp account user name to encrypt it when it's being stored.
		add_filter( 'rwmb_wordpress_app_solidwp_user_name_value', array( &$this, 'encrypt' ), 10, 3 );

		// Enable filter on the solidwp account password to encrypt it when it's being stored.
		add_filter( 'rwmb_wordpress_app_solidwp_password_value', array( &$this, 'encrypt' ), 10, 3 );

		// Enable filter on the default wp password field to decrypt it when its being retrieved.
		add_filter( 'rwmb_wordpress_app_default_wp_password_field_meta', array( &$this, 'decrypt' ), 10, 3 );

		// Enable filter on the default wp user id field to decrypt it when its being retrieved.
		add_filter( 'rwmb_wordpress_app_default_wp_user_id_field_meta', array( &$this, 'decrypt' ), 10, 3 );

		// Enable filter on the solidwp account user name field to decrypt it when its being retrieved.
		add_filter( 'rwmb_wordpress_app_solidwp_user_name_field_meta', array( &$this, 'decrypt' ), 10, 3 );

		// Enable filter on the solidwp account password field to decrypt it when its being retrieved.
		add_filter( 'rwmb_wordpress_app_solidwp_password_field_meta', array( &$this, 'decrypt' ), 10, 3 );

		// Encrypt the default SMTP password before it reaches the options table.
		add_filter( 'rwmb_sstk_email_gateway_smtp_password_value', array( &$this, 'encrypt_smtp_password' ), 10, 3 );

		// Decrypt the default SMTP password for the administrator settings field.
		add_filter( 'rwmb_sstk_email_gateway_smtp_password_field_meta', array( &$this, 'decrypt_smtp_password' ), 10, 3 );

	}

	/**
	 * Change a button text to say "Upload Logo" on the white label settings tab.
	 *
	 * Filter Hook: rwmb_media_add_string
	 *
	 * @See: https://docs.metabox.io/fields/file-advanced/ (scroll down to the FILTERS section.)
	 */
	public function sstk_upload_logo_change_add_string() {
		return __( 'Upload Logo', 'simple-stack' );
	}

	/**
	 * Set the popup header logo image.
	 *
	 * Filter Hook: sstk_popup_header_logo
	 *
	 * @param string $image_url header logo image url.
	 *
	 * @return string $image_url return the header logo image url.
	 */
	public function sstk_change_popup_header_logo( $image_url ) {

		$sstk_options = get_option( 'sstk_settings' );

		// Check to see if we're not allowed to load up a logo.
		if ( ! empty( $sstk_options['wordpress_app_noshow_logo'] ) ) {
			return '';
		}

		// We're allowed to use a logo so check to see if one is defined.
		$uploaded_logo = '';
		if ( ! empty( $sstk_options['wordpress_app_upload_logo'] ) ) {
			$uploaded_logo = $sstk_options['wordpress_app_upload_logo'];
		}

		// If a logo has been defined, set the variable to it so it can be returned.
		if ( ! empty( $uploaded_logo ) ) {
			$image_url = wp_get_attachment_image_url( $uploaded_logo[0], 'full' );
		}

		return $image_url;
	}

	/**
	 * Encrypt the aws secret key before it is saved in the database.
	 *
	 * @param string $new new.
	 * @param string $field field.
	 * @param string $old old.
	 */
	public function encrypt_aws_secret_key( $new, $field, $old ) {
		if ( ! empty( $new ) ) {
			return SSTK()->encrypt( $new );
		}
		return $new;
	}

	/**
	 * Decrypt the aws secret key before it is shown on the screen.
	 *
	 * @param string $meta meta.
	 * @param string $field field.
	 * @param string $saved saved.
	 */
	public function decrypt_aws_secret_key( $meta, $field, $saved ) {
		if ( ! empty( $meta ) ) {
			return SSTK()->decrypt( $meta );
		}
		return $meta;
	}

	/**
	 * Add a new tab to the settings page
	 *
	 * Filter hook: sstk_settings_tabs
	 *
	 * @param array $tabs Array of tabs on the settings page.
	 *
	 * @return array $tabs New array of tabs on the settings page
	 */
	public function settings_tabs( $tabs ) {
		$new_tab = array(
			'app-wordpress-app'          => __( 'APP: WordPress - Settings', 'simple-stack' ),
			'app-wordpress-app-security' => __( 'APP: WordPress - Security', 'simple-stack' ),
		);
		$tabs    = $tabs + $new_tab;
		return $tabs;
	}


	/**
	 * Add a new metaboxes to the settings page
	 *
	 * See the Metabox.IO website for documentation on
	 * the structure of the metabox settings array.
	 * https://docs.metabox.io/extensions/mb-settings-page/
	 *
	 * Filter hook: sstk_settings_metaboxes
	 *
	 * @param array $metaboxes Array of metaboxes on the settings page.
	 *
	 * @return array $metaboxes New array of metaboxes on the settings page
	 */
	public function settings_metaboxes( $metaboxes ) {

		$metaboxes[] = array(
			'id'             => 'wordpress-app',
			'title'          => __( 'General WordPress App Settings', 'simple-stack' ),
			'settings_pages' => 'sstk_settings',
			'tab'            => 'app-wordpress-app',  // this is the top level tab on the setttings screen, not to be confused with the tabs inside a metabox as we're defining below!
		// List of tabs in the metabox, in one of the following formats:
		// 1) key => label.
		// 2) key => array( 'label' => Tab label, 'icon' => Tab icon ).
			'tabs'           => $this->wordpress_app_metabox_tabs(),
			'tab_style'      => 'left',
			'tab_wrapper'    => true,
			'fields'         => apply_filters( 'sstk_wordpress-app_settings_fields', $this->all_fields() ),

		);

		$metaboxes[] = array(
			'id'             => 'wordpress-app-security',
			'title'          => __( 'General WordPress App Security', 'simple-stack' ),
			'settings_pages' => 'sstk_settings',
			'tab'            => 'app-wordpress-app-security',  // this is the top level tab on the settings screen, not to be confused with the tabs inside a metabox as we're defining below!
		// List of tabs in the metabox, in one of the following formats:
		// 1) key => label.
		// 2) key => array( 'label' => Tab label, 'icon' => Tab icon ).
			'tabs'           => $this->wordpress_app_metabox_security_tabs(),
			'tab_style'      => 'left',
			'tab_wrapper'    => true,
			'fields'         => apply_filters( 'sstk_wordpress-app_security_settings_fields', $this->all_security_fields() ),

		);

		return $metaboxes;
	}

	/**
	 * Return a list of tabs that will go inside the WordPress App metabox.
	 */
	public function wordpress_app_metabox_tabs() {
		$tabs = array(
			'wordpress-app-general-wpadmin'      => array(
				'label' => __( 'General', 'simple-stack' ),
				'icon'  => 'dashicons-text',
			),
			'wordpress-app-servers'              => array(
				'label' => __( 'Servers', 'simple-stack' ),
				'icon'  => 'dashicons-align-full-width',
			),
			'wordpress-app-sites'                => array(
				'label' => __( 'Sites', 'simple-stack' ),
				'icon'  => 'dashicons-admin-multisite',
			),
			'wordpress-app-backup'               => array(
				'label' => __( 'Backup and Restore', 'simple-stack' ),
				'icon'  => 'dashicons-images-alt2',
			),
			'wordpress-app-fields-and-links'     => array(
				'label' => __( 'Fields & Links', 'simple-stack' ),
				'icon'  => 'dashicons-editor-unlink',
			),
			'wordpress-app-plugin-theme-updates' => array(
				'label' => __( 'Theme & Plugin Updates', 'simple-stack' ),
				'icon'  => 'dashicons-admin-plugins',
			),
			'wordpress-app-dns-cloudflare'       => array(
				'label' => __( 'DNS: Cloudflare', 'simple-stack' ),
				'icon'  => 'dashicons-cloud',
			),
			'wordpress-app-alert-config'         => array(
				'label' => __( 'Alerts & Notices', 'simple-stack' ),
				'icon'  => 'dashicons-bell',
			),
			'wordpress-app-notifications'        => array(
				'label' => __( 'Notifications', 'simple-stack' ),
				'icon'  => 'dashicons-email',
			),
			'wordpress-app-integrations'         => array(
				'label' => __( 'Integrations', 'simple-stack' ),
				'icon'  => 'dashicons-welcome-write-blog',
			),
			'wordpress-app-color-settings'       => array(
				'label' => __( 'Styles', 'simple-stack' ),
				'icon'  => 'dashicons-color-picker',
			),
			'wordpress-app-email-gateway'        => array(
				'label' => __( 'Email Gateway', 'simple-stack' ),
				'icon'  => 'dashicons-email-alt2',
			),
			'wordpress-app-front-end-fields'     => array(
				'label' => __( 'Front-end Fields', 'simple-stack' ),
				'icon'  => 'dashicons-editor-kitchensink',
			),
			'wordpress-app-rest-api'             => array(
				'label' => __( 'Rest API', 'simple-stack' ),
				'icon'  => 'dashicons-rest-api',
			),
			'wordpress-app-white-label'          => array(
				'label' => __( 'White Label', 'simple-stack' ),
				'icon'  => 'dashicons-randomize',
			),
			'wordpress-app-custom-scripts'       => array(
				'label' => __( 'Custom Scripts', 'simple-stack' ),
				'icon'  => 'dashicons-shortcode',
			),
			'wordpress-app-developers'           => array(
				'label' => __( 'Developer Options', 'simple-stack' ),
				'icon'  => 'dashicons-editor-code',
			),      /*
		/*
		'wordpress-app-scripts' => array(
			'label' => 'Scripts',
			'icon'  => 'dashicons-format-aside',
		),
		*/
		);

		/* Add in the GIT array if GIT is enabled. */
		if ( true === sstk_is_git_enabled() ) {
			$git_tab = array(
				'wordpress-app-git' => array(
					'label' => 'Git',
					'icon'  => 'dashicons-share',
				),
			);

			$tabs = array_merge( $tabs, $git_tab );
		}

		return apply_filters( 'sstk_wordpress-app_settings_tabs', $tabs );
	}

	/**
	 * Return a list of tabs that will go inside the WordPress App Security metabox.
	 */
	public function wordpress_app_metabox_security_tabs() {
		$tabs = array(
			'wordpress-app-security-live-sites'          => array(
				'label' => 'Tabs - Production Sites',
				'icon'  => 'dashicons-lock',
			),
			'wordpress-app-security-staging-sites'       => array(
				'label' => 'Tabs - Staging Sites',
				'icon'  => 'dashicons-lock',
			),
			'wordpress-app-security-live-servers'        => array(
				'label' => 'Tabs - Servers',
				'icon'  => 'dashicons-lock',
			),
			'wordpress-app-security-features-live-sites' => array(
				'label' => 'Features - Production Sites',
				'icon'  => 'dashicons-lock',
			),
			'wordpress-app-security-features-staging-sites' => array(
				'label' => 'Features - Staging Sites',
				'icon'  => 'dashicons-lock',
			),
			'wordpress-app-security-features-servers'    => array(
				'label' => 'Features - Servers',
				'icon'  => 'dashicons-lock',
			),
		);
		return $tabs;
	}

	/**
	 * Return an array that combines all fields that will go on all tabs.
	 */
	public function all_fields() {
		$general_fields = $this->general_fields();
		// Removing script fields for now since they're not being used.
		/* $script_fields	= $this->scripts_fields(); */
		$server_fields                = $this->server_fields();
		$site_fields                  = $this->site_fields();
		$backup_fields                = $this->backup_fields();
		$fields_and_links             = $this->fields_and_links();
		$theme_and_plugin_updates     = $this->theme_and_plugin_updates();
		$alert_config                 = $this->alert_config();
		$email_notification_fields    = $this->email_notification_fields();
		$slack_notification_fields    = $this->slack_notification_fields();
		$zapier_notification_fields   = $this->zapier_notification_fields();
		$integrations_fields          = $this->integrations_fields();
		$button_color_settings_fields = $this->button_color_settings_fields();
		$email_gateway_load_defaults  = $this->email_gateway_load_defaults();
		$cf_dns_fields                = $this->cf_dns_fields();
		$rest_api_fields              = $this->rest_api_fields();
		$white_label_fields           = $this->white_label_fields();
		$custom_scripts               = $this->custom_script_fields();
		$front_end_fields             = $this->front_end_fields();
		$developer_fields             = $this->developer_fields();
		$git_fields                   = $this->git_fields();
		$all_fields                   = array_merge( $general_fields, $server_fields, $site_fields, $backup_fields, $fields_and_links, $theme_and_plugin_updates, $alert_config, $email_notification_fields, $slack_notification_fields, $zapier_notification_fields, $integrations_fields, $button_color_settings_fields, $email_gateway_load_defaults, $cf_dns_fields, $rest_api_fields, $white_label_fields, $custom_scripts, $front_end_fields, $git_fields, $developer_fields );
		return $all_fields;
	}

	/**
	 * Return an array that combines all fields that will go in the WordPress App security tabs.
	 */
	public function all_security_fields() {
		return array_merge( $this->all_site_tabs_security_fields(), $this->all_server_tabs_security_fields(), $this->all_site_features_security_fields(), $this->all_server_features_security_fields() );
	}

	/**
	 * Return an array that combines all fields that will go in the WordPress App security tabs for TABS - PRODUCTION SITES & TABS - STAGING SITES
	 */
	public function all_site_tabs_security_fields() {

		$fields = array();

		// An array of tabs where we'll be creating dynamic settings.  Should be the IDS of existing tabs defined in the 'wordpress_app_metabox_security_tabs' function earlier in this class.
		// We're going to use the second part of the associative array as a short-id because the tab id itself is too long.
		$context_tabs = array(
			'wordpress-app-security-live-sites'    => 'live-sites',
			'wordpress-app-security-staging-sites' => 'staging-sites',
		);

		// The owner types we'll be handling.
		$context_owners = array(
			'site-owner'            => __( 'Site Owners', 'simple-stack' ),
			'site-and-server-owner' => __( 'Site & Server Owners', 'simple-stack' ),
		);

		// The site tabs we'll be collecting security exceptions for. This must be unique across all items in the APP:WordPress - SECURITY tab!
		$tabs = array(
			'7g_waf'                   => __( '7G Firewall', 'simple-stack' ),
			'backup'                   => __( 'Backup', 'simple-stack' ),
			'cache'                    => __( 'Cache', 'simple-stack' ),
			'change-domain'            => __( 'Change Domain', 'simple-stack' ),
			'clone-site'               => __( 'Clone Site', 'simple-stack' ),
			'copy-to-existing-site'    => __( 'Copy To Existing Site', 'simple-stack' ),
			'crons'                    => __( 'Crons', 'simple-stack' ),
			'general'                  => __( 'General', 'simple-stack' ),
			'site-logs'                => __( 'Logs', 'simple-stack' ),
			'site-security'            => __( 'Security Plugin', 'simple-stack' ),
			'misc'                     => __( 'Misc', 'simple-stack' ),
			'database'                 => __( 'Database', 'simple-stack' ),
			'php-options'              => __( 'PHP', 'simple-stack' ),
			'redirect-rules'           => __( 'Redirect Rules', 'simple-stack' ),
			'sftp'                     => __( 'sFTP', 'simple-stack' ),
			'site-sync'                => __( 'Copy To Server', 'simple-stack' ),
			'ssl'                      => __( 'SSL', 'simple-stack' ),
			'staging'                  => __( 'Staging', 'simple-stack' ),
			'statistics'               => __( 'Statistics', 'simple-stack' ),
			'theme-and-plugin-updates' => __( 'Site Updates', 'simple-stack' ),
			'tools'                    => __( 'Tools', 'simple-stack' ),
			'tweaks'                   => __( 'Tweaks', 'simple-stack' ),
			'wp-site-users'            => __( 'WP Site Users', 'simple-stack' ),
			'wpconfig'                 => __( 'WPConfig', 'simple-stack' ),
			'file-manager'             => __( 'File Manager', 'simple-stack' ),
			'multisite'                => __( 'Multisite', 'simple-stack' ),
			'git-site-control'         => __( 'Git', 'simple-stack' ),
			'multitenant-site'         => __( 'Multi-tenant', 'simple-stack' ),
		);

		// Let developers hook into the array here.
		$tabs = apply_filters( 'sstk_wordpress-app_site_tab_permissions_list', $tabs );

		// Loop through the settings tabs...
		$sstk_id_prefix = 'sstk_wpapp_site_security_exception';
		foreach ( $context_tabs as $context_tab => $context_tab_short_id ) {
			// Heading.
			$desc     = __( 'Which tabs should be hidden from site owners?', 'simple-stack' );
			$desc    .= '<br />' . __( 'There are two options: 1. Hide a tab from a site owner.  2. Hide a tab from a site owner who is also the owner of a server.', 'simple-stack' );
			$desc    .= '<br />' . __( 'If you only choose an option in the first column but leave the second column disabled then an owner of a site that is also the owner of a server will NOT have the selected tab / option hidden.', 'simple-stack' );
			$fields[] = array(
				'name' => __( 'Hide Site Tabs from Site Owners', 'simple-stack' ),
				'id'   => "{$sstk_id_prefix}_{$context_tab_short_id}_site_owner_header",
				'type' => 'heading',
				'std'  => '',
				'desc' => $desc,
				'tab'  => $context_tab,
			);
			// Three columns at the top of each settings tab.
			$fields[] = array(
				'name'    => __( 'Tab', 'simple-stack' ),
				'id'      => "{$sstk_id_prefix}_{$context_tab_short_id}_tab_header_column",
				'type'    => 'custom_html',
				'std'     => '',
				'columns' => 4,
				'tab'     => $context_tab,
			);
			$fields[] = array(
				'name'    => __( 'Site Owners', 'simple-stack' ),
				'id'      => "{$sstk_id_prefix}_{$context_tab_short_id}_site_owner_header_column",
				'type'    => 'custom_html',
				'std'     => '',
				'columns' => 4,
				'tab'     => $context_tab,
				'tooltip' => __( 'Disable site owner access to these items.', 'simple-stack' ),
			);
			$fields[] = array(
				'name'    => __( 'Site & Server Owners', 'simple-stack' ),
				'id'      => "{$sstk_id_prefix}_{$context_tab_short_id}_site_server_owner_header_column",
				'type'    => 'custom_html',
				'std'     => '',
				'columns' => 4,
				'tab'     => $context_tab,
				'tooltip' => __( 'Disable site owner access to these items even if they are also the server owner.', 'simple-stack' ),
			);
			// Loop through the array of site tabs.
			foreach ( $tabs as $tab_key => $tab_desc ) {
				// First column is just the label with the tab name.
				$fields[] = array(
					'name'    => "{$tab_desc}",
					'id'      => "{$sstk_id_prefix}_{$context_tab_short_id}_column1_{$tab_key}_label",
					'type'    => 'custom_html',
					'tab'     => $context_tab,
					'columns' => 4,
				);
				// The next two columns are for the owner types.
				foreach ( $context_owners as $owner_key => $owner_label ) {
					$fields[] = array(
						'name'      => '',
						'id'        => "{$sstk_id_prefix}_{$context_tab_short_id}_{$owner_key}_{$tab_key}",
						'type'      => 'switch',
						'on_label'  => __( 'Tab Hidden', 'simple-stack' ),
						'off_label' => __( 'Tab Visible', 'simple-stack' ),
						'tab'       => $context_tab,
						'columns'   => 4,
					);
				}
			}

			// Heading - for roles.
			$fields[] = array(
				'name' => __( 'Hide Site Tabs from Roles', 'simple-stack' ),
				'id'   => "{$sstk_id_prefix}_{$context_tab_short_id}_site_owner_header_roles",
				'type' => 'heading',
				'std'  => '',
				'desc' => __( 'Which tabs should be hidden from site owners with these roles?', 'simple-stack' ),
				'tab'  => $context_tab,
			);
			// Loop through the array of site tabs, again.
			foreach ( $tabs as $tab_key => $tab_desc ) {
				// First column is just the label with the tab name.
				$fields[] = array(
					'name'    => "{$tab_desc}",
					'id'      => "{$sstk_id_prefix}_{$context_tab_short_id}_{$owner_key}_{$tab_key}_role_label",
					'type'    => 'custom_html',
					'tab'     => $context_tab,
					'columns' => 6,
				);
				// Collect the roles.
				$fields[] = array(
					'name'            => '',
					'id'              => "{$sstk_id_prefix}_{$context_tab_short_id}_{$tab_key}_roles",
					'type'            => 'select_advanced',
					'options'         => sstk_get_roles(),
					'select_all_none' => true,
					'multiple'        => true,
					'placeholder'     => __( 'Select list of roles that should not see this tab.', 'simple-stack' ),
					'tab'             => $context_tab,
					'columns'         => 6,
				);

			}
		}

		$context_tabs = array(
			'wordpress-app-security-live-servers'    => 'live-servers',
			'wordpress-app-security-staging-servers' => 'staging-servers',
		);

		return $fields;

	}

	/**
	 * Return an array that combines all fields that will go in the WordPress App security tabs for TABS - SERVERS.
	 */
	public function all_server_tabs_security_fields() {

		$fields = array();

		// An array of tabs where we'll be creating dynamic settings.  Should be the IDS of existing tabs defined in the 'wordpress_app_metabox_security_tabs' function earlier in this class.
		// We're going to use the second part of the associative array as a short-id because the tab id itself is too long.
		$context_tabs = array(
			'wordpress-app-security-live-servers' => 'live-servers',
		);

		// The owner types we'll be handling.
		$context_owners = array(
			'server-owner' => __( 'Server Owners', 'simple-stack' ),
		);

		// The server tabs we'll be collecting security exceptions for. This must be unique across all items in the APP:WordPress - SECURITY tab!
		$tabs = array(
			'server_backup'      => __( 'Backup', 'simple-stack' ),
			'callbacks'          => __( 'Callbacks', 'simple-stack' ),
			'fail2ban'           => __( 'Fail2ban', 'simple-stack' ),
			'server-logs'        => __( 'Server Logs', 'simple-stack' ),
			'monit-healing'      => __( 'Healing', 'simple-stack' ),
			'svr_power'          => __( 'Power', 'simple-stack' ),
			'services'           => __( 'Services', 'simple-stack' ),
			'sites_on_server'    => __( 'Sites', 'simple-stack' ),
			'ssh_console'        => __( 'SSH Console', 'simple-stack' ),
			'server-ssh-keys'    => __( 'SSH Keys', 'simple-stack' ),
			'svr_statistics'     => __( 'Statistics', 'simple-stack' ),
			'svr_tools'          => __( 'Tools', 'simple-stack' ),
			'svr_tweaks'         => __( 'Tweaks', 'simple-stack' ),
			'firewall'           => __( 'Firewall', 'simple-stack' ),
			'ols_console'        => __( 'OpenLiteSpeed Web Console Manager', 'simple-stack' ),
			'server_upgrade'     => __( 'Upgrades', 'simple-stack' ),
			'server-users'       => __( 'Users', 'simple-stack' ),
			'serversync'         => __( 'Server Sync', 'simple-stack' ),
			'resize'             => __( 'Resize Server', 'simple-stack' ),
			'git-server-control' => __( 'Git', 'simple-stack' ),
			'Multitenant-server' => __( 'Multi-tenant', 'simple-stack' ),
		);

		// Let developers hook into the array here.
		$tabs = apply_filters( 'sstk_wordpress-app_server_tab_permissions_list', $tabs );

		// Loop through the settings tabs...
		$sstk_id_prefix = 'sstk_wpapp_server_security_exception';
		foreach ( $context_tabs as $context_tab => $context_tab_short_id ) {
			// Heading.
			$fields[] = array(
				'name' => __( 'Hide Server Tabs from Server Owners', 'simple-stack' ),
				'id'   => "{$sstk_id_prefix}_{$context_tab_short_id}_server_owner_header",
				'type' => 'heading',
				'std'  => '',
				'desc' => __( 'Which tabs should be hidden from server owners?', 'simple-stack' ),
				'tab'  => $context_tab,
			);
			// Two columns at the top of each settings tab.
			$fields[] = array(
				'name'    => __( 'Tab', 'simple-stack' ),
				'id'      => "{$sstk_id_prefix}_{$context_tab_short_id}_tab_header_column",
				'type'    => 'custom_html',
				'std'     => '',
				'columns' => 6,
				'tab'     => $context_tab,
			);
			$fields[] = array(
				'name'    => __( 'Server Owners', 'simple-stack' ),
				'id'      => "{$sstk_id_prefix}_{$context_tab_short_id}_server_owner_header_column",
				'type'    => 'custom_html',
				'std'     => '',
				'columns' => 6,
				'tab'     => $context_tab,
				'tooltip' => __( 'Disable server owner access to these items.', 'simple-stack' ),
			);

			// Loop through the array of server tabs.
			foreach ( $tabs as $tab_key => $tab_desc ) {
				// First column is just the label with the tab name.
				$fields[] = array(
					'name'    => "{$tab_desc}",
					'id'      => "{$sstk_id_prefix}_{$context_tab_short_id}_column1_{$tab_key}_label",
					'type'    => 'custom_html',
					'tab'     => $context_tab,
					'columns' => 6,
				);
				// The next ONE columns are for the owner types.  Only one element in the array but keeping it as a loop to match the pattern for the sites function.
				foreach ( $context_owners as $owner_key => $owner_label ) {
					$fields[] = array(
						'name'      => '',
						'id'        => "{$sstk_id_prefix}_{$context_tab_short_id}_{$owner_key}_{$tab_key}",
						'type'      => 'switch',
						'on_label'  => __( 'Tab Hidden', 'simple-stack' ),
						'off_label' => __( 'Tab Visible', 'simple-stack' ),
						'tab'       => $context_tab,
						'columns'   => 6,
					);
				}
			}

			// Heading - for roles.
			$fields[] = array(
				'name' => __( 'Hide Server Tabs from Roles', 'simple-stack' ),
				'id'   => "{$sstk_id_prefix}_{$context_tab_short_id}_server_owner_header_roles",
				'type' => 'heading',
				'std'  => '',
				'desc' => __( 'Which tabs should be hidden from server owners with these roles?', 'simple-stack' ),
				'tab'  => $context_tab,
			);
			// Loop through the array of server tabs, again.
			foreach ( $tabs as $tab_key => $tab_desc ) {
				// First column is just the label with the tab name.
				$fields[] = array(
					'name'    => "{$tab_desc}",
					'id'      => "{$sstk_id_prefix}_{$context_tab_short_id}_{$owner_key}_{$tab_key}_role_label",
					'type'    => 'custom_html',
					'tab'     => $context_tab,
					'columns' => 6,
				);
				// Collect the roles.
				$fields[] = array(
					'name'            => '',
					'id'              => "{$sstk_id_prefix}_{$context_tab_short_id}_{$tab_key}_roles",
					'type'            => 'select_advanced',
					'options'         => sstk_get_roles(),
					'select_all_none' => true,
					'multiple'        => true,
					'placeholder'     => __( 'Select list of roles that should not see this tab.', 'simple-stack' ),
					'tab'             => $context_tab,
					'columns'         => 6,
				);

			}
		}

		return $fields;

	}

	/**
	 * Return an array that combines all fields that will go in the WordPress App security tabs for subtabs: FEATURES - PRODUCTION SITES & TABS - STAGING SITES
	 */
	public function all_site_features_security_fields() {

		$fields = array();

		// An array of tabs where we'll be creating dynamic settings.  Should be the IDS of existing tabs defined in the 'wordpress_app_metabox_security_tabs' function earlier in this class.
		// We're going to use the second part of the associative array as a short-id because the tab id itself is too long.
		$context_tabs = array(
			'wordpress-app-security-features-live-sites' => 'live-sites',
			'wordpress-app-security-features-staging-sites' => 'staging-sites',
		);

		// The owner types we'll be handling.
		$context_owners = array(
			'site-owner'            => __( 'Site Owners', 'simple-stack' ),
			'site-and-server-owner' => __( 'Site & Server Owners', 'simple-stack' ),
		);

		// The site features we'll be collecting security exceptions for.  This must be unique across all items in the APP:WordPress - SECURITY tab!
		$site_features = array(
			'email_metabox'      => __( 'Email Metabox', 'simple-stack' ),
			'desc_notes_metabox' => __( 'Descriptions Metabox', 'simple-stack' ),
		);

		// Let developers hook into the array here.
		$site_features = apply_filters( 'sstk_wordpress-app_site_features_permissions_list', $site_features );

		// Loop through the settings feature array...
		$sstk_id_prefix = 'sstk_wpapp_site_security_exception';
		foreach ( $context_tabs as $context_tab => $context_tab_short_id ) {
			// Heading.
			$desc     = __( 'Which features should be hidden from site owners?', 'simple-stack' );
			$desc    .= '<br />' . __( 'There are two options: 1. Hide a feature from a site owner.  2. Hide a feature from a site owner who is also the owner of a server.', 'simple-stack' );
			$desc    .= '<br />' . __( 'If you only choose an option in the first column but leave the second column disabled then an owner of a site that is also the owner of a server will NOT have the selected feature hidden.', 'simple-stack' );
			$fields[] = array(
				'name' => __( 'Hide Feature from Site Owners', 'simple-stack' ),
				'id'   => "{$sstk_id_prefix}_{$context_tab_short_id}_site_owner_header",
				'type' => 'heading',
				'std'  => '',
				'desc' => $desc,
				'tab'  => $context_tab,
			);
			// Three columns at the top of each settings tab.
			$fields[] = array(
				'name'    => __( 'Feature', 'simple-stack' ),
				'id'      => "{$sstk_id_prefix}_{$context_tab_short_id}_tab_header_column",
				'type'    => 'custom_html',
				'std'     => '',
				'columns' => 6,
				'tab'     => $context_tab,
			);
			$fields[] = array(
				'name'    => __( 'Site Owners', 'simple-stack' ),
				'id'      => "{$sstk_id_prefix}_{$context_tab_short_id}_site_owner_header_column",
				'type'    => 'custom_html',
				'std'     => '',
				'columns' => 3,
				'tab'     => $context_tab,
				'tooltip' => __( 'Disable site owner access to these features.', 'simple-stack' ),
			);
			$fields[] = array(
				'name'    => __( 'Site & Server Owners', 'simple-stack' ),
				'id'      => "{$sstk_id_prefix}_{$context_tab_short_id}_site_server_owner_header_column",
				'type'    => 'custom_html',
				'std'     => '',
				'columns' => 3,
				'tab'     => $context_tab,
				'tooltip' => __( 'Disable site owner access to these features even if they are also the server owner.', 'simple-stack' ),
			);
			// Loop through the array of site features.
			foreach ( $site_features as $feature_key => $feature_desc ) {
				// First column is just the label with the tab name.
				$fields[] = array(
					'name'    => "{$feature_desc}",
					'id'      => "{$sstk_id_prefix}_{$context_tab_short_id}_column1_{$feature_key}_label",
					'type'    => 'custom_html',
					'tab'     => $context_tab,
					'columns' => 6,
				);
				// The next two columns are for the owner types.
				foreach ( $context_owners as $owner_key => $owner_label ) {
					$fields[] = array(
						'name'      => '',
						'id'        => "{$sstk_id_prefix}_{$context_tab_short_id}_{$owner_key}_{$feature_key}",
						'type'      => 'switch',
						'on_label'  => __( 'Hidden', 'simple-stack' ),
						'off_label' => __( 'Visible', 'simple-stack' ),
						'tab'       => $context_tab,
						'columns'   => 3,
					);
				}
			}

			// Heading - for roles.
			$fields[] = array(
				'name' => __( 'Hide Site Features from Roles', 'simple-stack' ),
				'id'   => "{$sstk_id_prefix}_{$context_tab_short_id}_site_owner_header_roles",
				'type' => 'heading',
				'std'  => '',
				'desc' => __( 'Which features should be hidden from site owners with these roles?', 'simple-stack' ),
				'tab'  => $context_tab,
			);
			// Loop through the array of site features, again.
			foreach ( $site_features as $feature_key => $feature_desc ) {
				// First column is just the label with the tab name.
				$fields[] = array(
					'name'    => "{$feature_desc}",
					'id'      => "{$sstk_id_prefix}_{$context_tab_short_id}_{$owner_key}_{$feature_key}_role_label",
					'type'    => 'custom_html',
					'tab'     => $context_tab,
					'columns' => 6,
				);
				// Collect the roles.
				$fields[] = array(
					'name'            => '',
					'id'              => "{$sstk_id_prefix}_{$context_tab_short_id}_{$feature_key}_roles",
					'type'            => 'select_advanced',
					'options'         => sstk_get_roles(),
					'select_all_none' => true,
					'multiple'        => true,
					'placeholder'     => __( 'Select list of roles that should not see this feature.', 'simple-stack' ),
					'tab'             => $context_tab,
					'columns'         => 6,
				);

			}
		}

		$context_tabs = array(
			'wordpress-app-security-live-servers'    => 'live-servers',
			'wordpress-app-security-staging-servers' => 'staging-servers',
		);

		return $fields;

	}

	/**
	 * Return an array that combines all fields that will go in the WordPress App security tabs, subtab: FEATURES - SERVERS.
	 */
	public function all_server_features_security_fields() {

		$fields = array();

		// An array of tabs where we'll be creating dynamic settings.  Should be the IDS of existing tabs defined in the 'wordpress_app_metabox_security_tabs' function earlier in this class.
		// We're going to use the second part of the associative array as a short-id because the tab id itself is too long.
		$context_tabs = array(
			'wordpress-app-security-features-servers' => 'live-servers',
		);

		// The owner types we'll be handling.
		$context_owners = array(
			'server-owner' => __( 'Server Owners', 'simple-stack' ),
		);

		// The server features we'll be collecting security exceptions for.  This must be unique across all items in the APP:WordPress - SECURITY tab!
		$svrfeatures = array(
			'email_metabox'      => __( 'Email Metabox', 'simple-stack' ),
			'desc_notes_metabox' => __( 'Descriptions Metabox', 'simple-stack' ),
		);

		// Let developers hook into the array here.
		$svrfeatures = apply_filters( 'sstk_wordpress-app_server_features_permissions_list', $svrfeatures );

		// Loop through the settings tabs...
		$sstk_id_prefix = 'sstk_wpapp_server_security_exception';
		foreach ( $context_tabs as $context_tab => $context_tab_short_id ) {
			// Heading.
			$fields[] = array(
				'name' => __( 'Hide Certain Features From Server Owners', 'simple-stack' ),
				'id'   => "{$sstk_id_prefix}_{$context_tab_short_id}_server_owner_header",
				'type' => 'heading',
				'std'  => '',
				'desc' => __( 'Which features should be hidden from server owners?', 'simple-stack' ),
				'tab'  => $context_tab,
			);
			// Two columns at the top of each settings tab.
			$fields[] = array(
				'name'    => __( 'Feature', 'simple-stack' ),
				'id'      => "{$sstk_id_prefix}_{$context_tab_short_id}_tab_header_column",
				'type'    => 'custom_html',
				'std'     => '',
				'columns' => 6,
				'tab'     => $context_tab,
			);
			$fields[] = array(
				'name'    => __( 'Server Owners', 'simple-stack' ),
				'id'      => "{$sstk_id_prefix}_{$context_tab_short_id}_server_owner_header_column",
				'type'    => 'custom_html',
				'std'     => '',
				'columns' => 6,
				'tab'     => $context_tab,
				'tooltip' => __( 'Disable or enable server owner access to these features.', 'simple-stack' ),
			);

			// Loop through the array of server features.
			foreach ( $svrfeatures as $feature_key => $feature_desc ) {
				// First column is just the label with the tab name.
				$fields[] = array(
					'name'    => "{$feature_desc}",
					'id'      => "{$sstk_id_prefix}_{$context_tab_short_id}_column1_{$feature_key}_label",
					'type'    => 'custom_html',
					'tab'     => $context_tab,
					'columns' => 6,
				);
				// The next ONE columns are for the owner types.  Only one element in the array but keeping it as a loop to match the pattern for the sites function.
				foreach ( $context_owners as $owner_key => $owner_label ) {
					$fields[] = array(
						'name'      => '',
						'id'        => "{$sstk_id_prefix}_{$context_tab_short_id}_{$owner_key}_{$feature_key}",
						'type'      => 'switch',
						'on_label'  => __( 'Hidden', 'simple-stack' ),
						'off_label' => __( 'Visible', 'simple-stack' ),
						'tab'       => $context_tab,
						'columns'   => 6,
					);
				}
			}

			// Heading - for roles.
			$fields[] = array(
				'name' => __( 'Hide Server Features from Roles', 'simple-stack' ),
				'id'   => "{$sstk_id_prefix}_{$context_tab_short_id}_server_owner_header_roles",
				'type' => 'heading',
				'std'  => '',
				'desc' => __( 'Which features should be hidden from server owners with these roles?', 'simple-stack' ),
				'tab'  => $context_tab,
			);
			// Loop through the array of server tabs, again.
			foreach ( $svrfeatures as $feature_key => $feature_desc ) {
				// First column is just the label with the tab name.
				$fields[] = array(
					'name'    => "{$feature_desc}",
					'id'      => "{$sstk_id_prefix}_{$context_tab_short_id}_{$owner_key}_{$feature_key}_role_label",
					'type'    => 'custom_html',
					'tab'     => $context_tab,
					'columns' => 6,
				);
				// Collect the roles.
				$fields[] = array(
					'name'            => '',
					'id'              => "{$sstk_id_prefix}_{$context_tab_short_id}_{$feature_key}_roles",
					'type'            => 'select_advanced',
					'options'         => sstk_get_roles(),
					'select_all_none' => true,
					'multiple'        => true,
					'placeholder'     => __( 'Select list of roles that should not see this feature.', 'simple-stack' ),
					'tab'             => $context_tab,
					'columns'         => 6,
				);

			}
		}

		return $fields;

	}

	/**
	 * Return array portion of field settings for use in the script fields tab.
	 */
	public function scripts_fields() {

		$fields = array(
			array(
				'id'   => 'sstk_provisioning_scripts_heading',
				'type' => 'heading',
				'name' => __( 'Provisioning scripts', 'simple-stack' ),
				'desc' => __( 'Simple Stack uses version 2 scripts exclusively: OpenLiteSpeed on Ubuntu 24.04 or 26.04 with PHP 8.3, 8.4 or 8.5. The script version cannot be changed.', 'simple-stack' ),
				'tab'  => 'wordpress-app-scripts',
			),
			array(
				'id'   => 'sstk_admin_ip',
				'type' => 'text',
				'name' => __( 'Admin IP for the OLS WebAdmin console', 'simple-stack' ),
				'desc' => __( 'Optional. A single IPv4 or IPv6 address (or CIDR block) allowed to reach the OpenLiteSpeed WebAdmin console on port 7080. <b>Leave blank to bind the console to 127.0.0.1</b>, which is the safe default: you then reach it through an SSH tunnel (<code>ssh -L 7080:127.0.0.1:7080 root@server</code>). Port 7080 is never opened to 0.0.0.0/0.', 'simple-stack' ),
				'tab'  => 'wordpress-app-scripts',
			),
			array(
				'id'   => 'wordpress_app_commands_after_server_install',
				'type' => 'textbox',
				'name' => __( 'After provisioning commands', 'simple-stack' ),
				'desc' => __( '<b>For future use - not yet active</b> Run these commands after the server has been provisioned.', 'simple-stack' ),
				'tab'  => 'wordpress-app-scripts',
			),
		);

		return $fields;

	}

	/**
	 * Return array portion of field settings for use in the git fields tab.
	 */
	public function git_fields() {

		$fields = array(
			array(
				'type' => 'heading',
				'name' => __( 'Advanced Git Actions', 'simple-stack' ),
				'desc' => __( 'Enable Advanced Git Actions.  If you do not check this box only push-to-deploy options will be available.', 'simple-stack' ),
				'tab'  => 'wordpress-app-git',
			),
			array(
				'id'      => 'wordpress_app_git_enable_advanced',
				'type'    => 'checkbox',
				'name'    => __( 'Enable', 'simple-stack' ),
				'tooltip' => __( 'Show advanced Git Actions such as sync, init etc.', 'simple-stack' ),
				'tab'     => 'wordpress-app-git',
			),
			array(
				'type' => 'heading',
				'name' => __( 'Global Git Defaults', 'simple-stack' ),
				'desc' => __( 'These defaults are used if no similiar values are defined on a server or site.', 'simple-stack' ),
				'tab'  => 'wordpress-app-git',
			),
			array(
				'id'   => 'wordpress_app_git_email_address',
				'type' => 'email',
				'name' => __( 'Email Address', 'simple-stack' ),
				'desc' => __( 'Email address used by your git provider.', 'simple-stack' ),
				'tab'  => 'wordpress-app-git',
			),
			array(
				'id'          => 'wordpress_app_git_display_name',
				'type'        => 'text',
				'name'        => __( 'Display Name', 'simple-stack' ),
				'placeholder' => __( 'The display name used for your user account at your git provider.', 'simple-stack' ),
				'desc'        => __( 'eg: john smith', 'simple-stack' ),
				'tab'         => 'wordpress-app-git',
			),
			array(
				'id'          => 'wordpress_app_git_user_name',
				'type'        => 'text',
				'name'        => __( 'User Name', 'simple-stack' ),
				'placeholder' => __( 'Your user name for your account at your git provider.', 'simple-stack' ),
				'desc'        => __( 'eg: janesmith (no spaces or special chars).', 'simple-stack' ),
				'tab'         => 'wordpress-app-git',
			),
			array(
				'id'      => 'wordpress_app_git_token',
				'type'    => 'text',
				'name'    => __( 'API Token', 'simple-stack' ),
				'desc'    => __( 'API Token for your git account at your git provider.', 'simple-stack' ),
				'tooltip' => __( 'API tokens must provide read-write priveldges for your repos. Generate one on github under the settings area of your account.', 'simple-stack' ),
				'tab'     => 'wordpress-app-git',
			),
			array(
				'id'   => 'wordpress_app_git_branch',
				'type' => 'text',
				'name' => __( 'Branch', 'simple-stack' ),
				'desc' => __( 'The default branch for your repos - eg: main or master.', 'simple-stack' ),
				'tab'  => 'wordpress-app-git',
			),
			array(
				'id'      => 'wordpress_app_git_pre_processing_script_link',
				'type'    => 'url',
				'name'    => __( 'Pre-Processing Script Link', 'simple-stack' ),
				'desc'    => __( 'Link to bash script that will execute before initializing a site with git.', 'simple-stack' ),
				'tooltip' => __( 'A raw gist is a good place to locate this file as long as it does not have any private data.', 'simple-stack' ),
				'tab'     => 'wordpress-app-git',
			),
			array(
				'id'      => 'wordpress_app_git_post_processing_script_link',
				'type'    => 'url',
				'name'    => __( 'Post-Processing Script Link', 'simple-stack' ),
				'desc'    => __( 'Link to bash script that will execute after initializing a site with git.', 'simple-stack' ),
				'tooltip' => __( 'A raw gist is a good place to locate this file as long as it does not have any private data.', 'simple-stack' ),
				'tab'     => 'wordpress-app-git',
			),
			array(
				'id'      => 'wordpress_app_git_ignore_link',
				'type'    => 'url',
				'name'    => __( 'Default GitIgnore File Link', 'simple-stack' ),
				'desc'    => __( 'Link to a text file containing git ignore contents.', 'simple-stack' ),
				'tooltip' => __( 'A raw gist is a good place to locate this file.', 'simple-stack' ),
				'tab'     => 'wordpress-app-git',
			),
			array(
				'id'   => 'wordpress_app_git_ignore_folders',
				'type' => 'text',
				'name' => __( 'Ignore Folders', 'simple-stack' ),
				'desc' => __( 'A comma-separated list of folders to add to git ignore.', 'simple-stack' ),
				'tab'  => 'wordpress-app-git',
			),
			array(
				'id'      => 'wordpress_app_git_ignore_files',
				'type'    => 'text',
				'name'    => __( 'Ignore Files', 'simple-stack' ),
				'desc'    => __( 'A comma-separated list of files to add to git ignore.', 'simple-stack' ),
				'tooltip' => __( 'This list is in addition to our default list of gitignore files - see our documentation for our default list.', 'simple-stack' ),
				'tab'     => 'wordpress-app-git',
			),
			array(
				'id'   => 'wordpress_app_git_footer_note',
				'type' => 'custom_html',
				'name' => '',
				'std'  => __( 'Note: Only GITHUB is supported as a git provider at this time.', 'simple-stack' ),
				'tab'  => 'wordpress-app-git',
			),
		);

			return $fields;

	}

	/**
	 * Return array portion of field settings for use in the general fields tab.
	 */
	public function general_fields() {

		$fields = array(
			array(
				'type' => 'heading',
				'name' => __( 'Operating Systems', 'simple-stack' ),
				'tab'  => 'wordpress-app-general-wpadmin',
			),
			array(
				'id'      => 'wordpress_app_default_os',
				'type'    => 'select',
				'name'    => __( 'Default OS', 'simple-stack' ),
				'tooltip' => __( 'Select the default OS to be used when deploying a new WordPress server.', 'simple-stack' ),
				'tab'     => 'wordpress-app-general-wpadmin',
				'std'     => 'ubuntu2404lts',
				'options' => SSTK()->get_os_list(),
			),
			array(
				'type' => 'heading',
				'name' => __( 'Server Options', 'simple-stack' ),
				'desc' => __( 'Server options specific to the WordPress app.', 'simple-stack' ),
				'tab'  => 'wordpress-app-general-wpadmin',
			),
			array(
				'id'      => 'wordpress_app_default_webserver',
				'type'    => 'select',
				'name'    => __( 'Default Web Server', 'simple-stack' ),
				'tooltip' => __( 'Simple Stack only supports OpenLiteSpeed, so this is fixed.', 'simple-stack' ),
				'tab'     => 'wordpress-app-general-wpadmin',
				'std'     => 'ols',
				'options' => SSTK()->get_webserver_list(),
			),
			array(
				'id'      => 'wordpress_app_use_extended_server_name',
				'type'    => 'checkbox',
				'name'    => __( 'Override the server name?', 'simple-stack' ),
				'tooltip' => __( 'Set the server name to a system generated name that includes the name entered by the admin. If unchecked, always use just the admin defined server name - admin MUST ensure that the server name is unique for certain cloud providers or server creation will fail!', 'simple-stack' ),
				'tab'     => 'wordpress-app-general-wpadmin',
			),
			array(
				'id'      => 'wordpress_app_server_tab_style',
				'type'    => 'select',
				'options' => array(
					'left'    => __( 'Vertical', 'simple-stack' ),
					'default' => __( 'Horizontal', 'simple-stack' ),
					'box'     => __( 'Boxed', 'simple-stack' ),
				),
				'name'    => __( 'Tab Style For Server Detail Screen', 'simple-stack' ),
				'tooltip' => __( 'The tabs on the server detail screen are vertical but you can switch them to a horizontal style.', 'simple-stack' ),
				'tab'     => 'wordpress-app-general-wpadmin',
			),
			array(
				'id'      => 'wordpress_app_enable_bulk_delete_on_server_when_delete_protected',
				'type'    => 'checkbox',
				'name'    => __( 'Enable Bulk Trash Action for Deleted-protected Servers [Danger]', 'simple-stack' ),
				'tooltip' => __( 'Enable the bulk trash option on the server list screen for those items that are delete protected. Usually the checkbox for the server is disabled for delete protected items - which means that other bulk options will not be available for DELETE PROTECTED servers either. Enable to overide this logic and allow bulk actions on DELETE PROTECTED items as well. However, doing so can allow your servers to be inadvertently deleted via the BULK TRASH operation.', 'simple-stack' ),
				'tab'     => 'wordpress-app-general-wpadmin',
			),
			array(
				'id'      => 'wordpress_app_disable_bulk_delete_on_full_server_list',
				'type'    => 'checkbox',
				'name'    => __( 'Disable Bulk Trash Action', 'simple-stack' ),
				'tooltip' => __( 'Disable the bulk delete option for all servers.', 'simple-stack' ),
				'tab'     => 'wordpress-app-general-wpadmin',
			),
			array(
				'id'      => 'wordpress_app_enable_server_delete_record',
				'type'    => 'checkbox',
				'name'    => __( 'Show The DELETE RECORD Link', 'simple-stack' ),
				'tooltip' => __( 'Show the DELETE RECORD link in the server list.', 'simple-stack' ),
				'tab'     => 'wordpress-app-general-wpadmin',
			),
			array(
				'id'      => 'wordpress_app_app_limit_server_list',
				'type'    => 'number',
				'name'    => __( 'App Limit In Server List', 'simple-stack' ),
				'tooltip' => __( 'How many apps or sites should we list in the APPS column on the server list screen?', 'simple-stack' ),
				'min'     => 0,
				'std'     => 4,
				'size'    => 10,
				'tab'     => 'wordpress-app-general-wpadmin',
			),
			array(
				'type' => 'heading',
				'name' => __( 'App Options', 'simple-stack' ),
				'desc' => __( 'Options specific to the WordPress app.', 'simple-stack' ),
				'tab'  => 'wordpress-app-general-wpadmin',
			),
			array(
				'id'      => 'wordpress_app_tab_style',
				'type'    => 'select',
				'options' => array(
					'left'    => __( 'Vertical', 'simple-stack' ),
					'default' => __( 'Horizontal', 'simple-stack' ),
					'box'     => __( 'Boxed', 'simple-stack' ),
				),
				'name'    => __( 'Tab Style For Site Detail Screen', 'simple-stack' ),
				'tooltip' => __( 'The tabs on the site detail screen are vertical but you can switch them to a horizontal style.', 'simple-stack' ),
				'tab'     => 'wordpress-app-general-wpadmin',
			),
			array(
				'id'      => 'wordpress_app_show_vnstat_in_app',
				'type'    => 'checkbox',
				'name'    => __( 'Show VNSTAT in the app screen?', 'simple-stack' ),
				'tooltip' => __( 'VNSTAT provides network statistics data for the entire server. You can choose to show this data in the statistics tab on each site - if you do not have a need to secure sites between users on the same server.', 'simple-stack' ),
				'tab'     => 'wordpress-app-general-wpadmin',
			),
			array(
				'id'      => 'wordpress_app_enable_bulk_delete_on_app_when_delete_protected',
				'type'    => 'checkbox',
				'name'    => __( 'Enable Bulk Trash Action for Delete-protected Sites [Danger]', 'simple-stack' ),
				'tooltip' => __( 'Enable the bulk trash option on the app list screen for those items that are delete protected. Usually the checkbox for the app is disabled for delete protected items - which means that other bulk options will not be available for DELETE PROTECTED apps either. Enable to overide this logic and allow bulk actions on DELETE PROTECTED items as well. However, doing so can allow your apps to be inadvertently deleted via the BULK TRASH operation.', 'simple-stack' ),
				'tab'     => 'wordpress-app-general-wpadmin',
			),
			array(
				'id'      => 'wordpress_app_disable_bulk_delete_on_full_app_list',
				'type'    => 'checkbox',
				'name'    => __( 'Disable Bulk Trash Action', 'simple-stack' ),
				'tooltip' => __( 'Disable the bulk delete option for all apps.', 'simple-stack' ),
				'tab'     => 'wordpress-app-general-wpadmin',
			),
			array(
				'id'      => 'wordpress_app_enable_bulk_site_delete_on_full_app_list',
				'type'    => 'checkbox',
				'name'    => __( 'Enable Bulk Site Delete Action', 'simple-stack' ),
				'tooltip' => __( 'Enable the bulk site delete option for the sites list. This deletes the selected sites on your servers unlike the TRASH option which just removes the records.', 'simple-stack' ),
				'tab'     => 'wordpress-app-general-wpadmin',
			),
			array(
				'type' => 'heading',
				'name' => __( 'Labels', 'simple-stack' ),
				'desc' => __( 'Label options specific to the WordPress app.', 'simple-stack' ),
				'tab'  => 'wordpress-app-general-wpadmin',
			),
			array(
				'id'      => 'wordpress_app_show_label_in_lists',
				'type'    => 'checkbox',
				'name'    => __( 'Show the \'WordPress\' Label?', 'simple-stack' ),
				'tooltip' => __( 'If you are running multiple apps you will likely want to know which servers and apps are WordPress related.  If so, turn this on. It will show a WORDPRESS label next to the title in the server and site lists.', 'simple-stack' ),
				'tab'     => 'wordpress-app-general-wpadmin',
			),
			array(
				'id'      => 'wordpress_app_show_object_server_label_in_lists',
				'type'    => 'checkbox',
				'name'    => __( 'Show the Object Server Label?', 'simple-stack' ),
				'tooltip' => __( 'Use this to show the REDIS and MEMCACHED label next to the title in the server and site lists.', 'simple-stack' ),
				'tab'     => 'wordpress-app-general-wpadmin',
			),
			array(
				'type' => 'heading',
				'name' => __( 'Overrides', 'simple-stack' ),
				'desc' => '',
				'tab'  => 'wordpress-app-general-wpadmin',
			),
			array(
				'id'                => 'wordpress_app_allowed_wp_versions',
				'type'              => 'text',
				'clone'             => true,
				'size'              => 10,
				'name'              => __( 'Allowed WP Versions', 'simple-stack' ),
				'tooltip'           => __( 'If you have your own list of WP versions you would like to see on the install screen enter it here. Otherwise we will use our own master list. One version per row. Do not use a 2-digit version - use \'latest\' instead.', 'simple-stack' ),
				'label_description' => __( 'One version per row.', 'simple-stack' ),
				'tab'               => 'wordpress-app-general-wpadmin',
			),
			array(
				'id'      => 'wordpress_app_versions_show_nightly',
				'type'    => 'checkbox',
				'name'    => __( 'Show \'Nightly\' as a Version Option?', 'simple-stack' ),
				'tooltip' => __( 'Allow the user to specify the nightly development version of WordPress.', 'simple-stack' ),
				'tab'     => 'wordpress-app-general-wpadmin',
			),
			array(
				'type' => 'heading',
				'name' => __( 'Misc', 'simple-stack' ),
				'desc' => '',
				'tab'  => 'wordpress-app-general-wpadmin',
			),
			array(
				'id'      => 'wordpress_app_ignore_journalctl_xe',
				'type'    => 'checkbox',
				'name'    => __( 'Do not treat Journalctl -xe messages as errors', 'simple-stack' ),
				'tooltip' => __( 'Some bash scripts might trigger journal xe warnings.  Check this box to ignore them.', 'simple-stack' ),
				'tab'     => 'wordpress-app-general-wpadmin',
			),
		);
			return $fields;

	}

	/**
	 * Array of fields used in the servers.
	 */
	public function server_fields() {

		$server_setup_desc  = __( 'Services and actions to perform immediately after a server has been deployed.', 'simple-stack' );
		$server_setup_desc .= '<br/>' . __( 'These actions will occur in the background via our scheduler AFTER the server is provisioned.', 'simple-stack' );
		$server_setup_desc .= '<br/>' . __( 'This means that there is a delay before some of these items are completed even though the server is ready and available.', 'simple-stack' );

		$fields = array(
			array(
				'type' => 'heading',
				'name' => __( 'Server Setup Options', 'simple-stack' ),
				'desc' => $server_setup_desc,
				'tab'  => 'wordpress-app-servers',
			),
			array(
				'id'      => 'wordpress_app_servers_add_delete_protection',
				'type'    => 'checkbox',
				'name'    => __( 'Delete Protect New Servers?', 'simple-stack' ),
				'tooltip' => __( 'Should deletion protection automatically be enabled on new servers?', 'simple-stack' ),
				'tab'     => 'wordpress-app-servers',
			),
			array(
				'id'      => 'wordpress_app_servers_activate_callbacks',
				'type'    => 'checkbox',
				'name'    => __( 'Install Callbacks?', 'simple-stack' ),
				'tooltip' => __( 'Turn this on to automatically install callbacks on all new servers - this is recommended.', 'simple-stack' ),
				'tab'     => 'wordpress-app-servers',
			),
			array(
				'id'      => 'wordpress_app_servers_activate_backups',
				'type'    => 'checkbox',
				'name'    => __( 'Setup Backups?', 'simple-stack' ),
				'tooltip' => __( 'Turn this on to automatically setup backups for all sites on new servers - this is recommended if you have configured AWS S3 defaults.', 'simple-stack' ),
				'tab'     => 'wordpress-app-servers',
			),
			array(
				'id'      => 'wordpress_app_servers_activate_config_backups',
				'type'    => 'checkbox',
				'name'    => __( 'Setup Local Configuration Backups?', 'simple-stack' ),
				'tooltip' => __( 'Turn this on to automatically setup 90 days of local backups for all critical configuration files on new servers - this is recommended.', 'simple-stack' ),
				'tab'     => 'wordpress-app-servers',
			),
			array(
				'id'      => 'wordpress_app_servers_refresh_services',
				'type'    => 'checkbox',
				'name'    => __( 'Refresh Services Status?', 'simple-stack' ),
				'tooltip' => __( 'Refresh the status of services shown on the SERVICES tab of your new server.', 'simple-stack' ),
				'tab'     => 'wordpress-app-servers',
			),
			array(
				'type' => 'heading',
				'name' => __( 'Linux Updates', 'simple-stack' ),
				'desc' => __( 'Run updates immediately after server is deployed - NOT RECOMMENDED if any of the options above are also enabled.', 'simple-stack' ),
				'tab'  => 'wordpress-app-servers',
			),
			array(
				'id'      => 'wordpress_app_servers_run_all_linux_updates',
				'type'    => 'checkbox',
				'name'    => __( 'Run All Linux Updates?', 'simple-stack' ),
				'tooltip' => __( 'Most new servers have a lot of updates that need to be run overnight. You can turn this on to force the updates to run asap.  Note that this will chew up CPU cycles and cause your server to be slow for a bit. If you need to use your servers immediately do not enable this.', 'simple-stack' ),
				'tab'     => 'wordpress-app-servers',
			),
			array(
				'type' => 'heading',
				'name' => __( 'Server Health Warnings: PHP', 'simple-stack' ),
				'desc' => __( 'We set a default server wide version for PHP under which wp-cli is run. You can get warnings on which versions are NOT allowed to be the default.', 'simple-stack' ),
				'tab'  => 'wordpress-app-servers',
			),
			array(
				'id'              => 'wordpress_app_servers_default_php_warn_versions',
				'type'            => 'checkbox_list',
				'name'            => __( 'Show Warning In Health Column For These PHP Versions', 'simple-stack' ),
				'tooltip'         => __( 'If we detect that the default version of PHP for a server is set to any of the selected versions above, we will show a warning in the HEALTH column in the server list.', 'simple-stack' ),
				'options'         => array(
					'5.6' => '5.6',
					'7.1' => '7.1',
					'7.2' => '7.2',
					'7.3' => '7.3',
					'7.4' => '7.4',
					'8.0' => '8.0',
					'8.1' => '8.1',
					'8.2' => '8.2',
					'8.3' => '8.3',
				),
				'std'             => ( array( '5.6', '7.1', '7.2', '7.3', '8.0' ) ),
				'select_all_none' => true,
				'tab'             => 'wordpress-app-servers',
			),
			array(
				'type' => 'heading',
				'name' => __( 'Bulk Copy To Server', 'simple-stack' ),
				'desc' => __( 'Setup target servers to which you can copy multiple sites using the BULK ACTIONS drop-down on the apps/sites list.', 'simple-stack' ),
				'tab'  => 'wordpress-app-servers',
			),
			array(
				'name'        => __( 'Target Servers', 'simple-stack' ),
				'id'          => 'wordpress_app_servers_target_servers',
				'type'        => 'post',
				'post_type'   => 'sstk_app_server',
				'query_args'  => array(
					'post_status'    => 'private',
					'posts_per_page' => - 1,
				),
				'field_type'  => 'select_advanced',
				'multiple'    => true,
				'placeholder' => __( 'Select one or more Cloud Servers.', 'simple-stack' ),
				'tab'         => 'wordpress-app-servers',
			),
			array(
				'type' => 'custom_html',
				'std'  => __( 'Note: This function requires the premium SERVER SYNC add-on. Please check our documentation on how to use these servers as targets for bulk copies.', 'simple-stack' ),
				'tab'  => 'wordpress-app-servers',
			),

		);

		return $fields;
	}

	/**
	 * Array of fields used in the sites tab.
	 */
	public function site_fields() {

		$site_setup_desc  = __( 'Services and actions to perform immediately after a site has been deployed.', 'simple-stack' );
		$site_setup_desc .= '<br/>' . __( 'These actions will occur in the background via our scheduler AFTER the site is provisioned.', 'simple-stack' );
		$site_setup_desc .= '<br/>' . __( 'This means that there is a short delay before these items are completed even though the site is ready and available.', 'simple-stack' );

		$fields = array(
			array(
				'type' => 'heading',
				'name' => __( 'Site Setup Options', 'simple-stack' ),
				'desc' => $site_setup_desc,
				'tab'  => 'wordpress-app-sites',
			),
			array(
				'id'      => 'wordpress_app_sites_add_delete_protection',
				'type'    => 'checkbox',
				'name'    => __( 'Delete Protect New Sites?', 'simple-stack' ),
				'tooltip' => __( 'Should deletion protection automatically be enabled on new sites?', 'simple-stack' ),
				'tab'     => 'wordpress-app-sites',
			),
			array(
				'id'      => 'wordpress_app_sites_disable_page_cache',
				'type'    => 'checkbox',
				'name'    => __( 'Disable The Page Cache?', 'simple-stack' ),
				'tooltip' => __( 'We automatically install and enable a page cache on all new sites. Use this option to disable it.', 'simple-stack' ),
				'tab'     => 'wordpress-app-sites',
			),
			array(
				'id'      => 'wordpress_app_sites_disable_redis_cache',
				'type'    => 'checkbox',
				'name'    => __( 'Disable The Redis Object Cache?', 'simple-stack' ),
				'tooltip' => __( 'We automatically install and enable the REDIS object cache on all new sites. Use this option to disable it.', 'simple-stack' ),
				'tab'     => 'wordpress-app-sites',
			),
			array(
				'id'      => 'wordpress_app_sites_activate_solidwp_security',
				'type'    => 'checkbox',
				'name'    => __( 'Activate SolidWP Security?', 'simple-stack' ),
				'tooltip' => __( 'Activate SolidWP Security Pro on New Sites?', 'simple-stack' ),
				'tab'     => 'wordpress-app-sites',
			),
			array(
				'id'      => 'wordpress_app_sites_set_php_version',
				'type'    => 'select',
				'name'    => __( 'Switch PHP Version For New Sites?', 'simple-stack' ),
				'tooltip' => __( 'After a site is installed, switch the PHP version to the one specified here.', 'simple-stack' ),
				'options' => array(
					'8.3' => '8.3',
					'8.4' => '8.4',
					'8.5' => '8.5',
				),
				'tab'     => 'wordpress-app-sites',
			),

			array(
				'type' => 'heading',
				'name' => __( 'Site Expiration', 'simple-stack' ),
				'desc' => __( 'What should we do when a site is expired?', 'simple-stack' ),
				'tab'  => 'wordpress-app-sites',
			),
			array(
				'id'      => 'wordpress_app_sites_expired_delete_site',
				'type'    => 'checkbox',
				'name'    => __( 'Delete Site?', 'simple-stack' ),
				'tooltip' => __( 'Delete the site when it is expired?  This will not be reversible!', 'simple-stack' ),
				'tab'     => 'wordpress-app-sites',
			),
			array(
				'id'      => 'wordpress_app_sites_expired_enable_http_auth',
				'type'    => 'checkbox',
				'name'    => __( 'Password Protect Site (HTTP AUTH)?', 'simple-stack' ),
				'tooltip' => __( 'This option will place a password popup before visitors can view it. Only an admin will be able to remove this protection.  Do not enable this option with the DISABLE option below - they do not work well together!', 'simple-stack' ),
				'hidden'  => array( 'wordpress_app_sites_expired_delete_site', '=', '1' ),
				'tab'     => 'wordpress-app-sites',
			),
			array(
				'id'      => 'wordpress_app_sites_expired_disable_site',
				'type'    => 'checkbox',
				'name'    => __( 'Disable Site?', 'simple-stack' ),
				'tooltip' => __( 'Disabling a site will prevent visitors from viewing it. This option is slightly different from the HTTP AUTH option above because it makes the site completely invisible to web traffic - not even a password popup.  Do not enable this option with the HTTP AUTH option above - they do not work well together!', 'simple-stack' ),
				'hidden'  => array( 'wordpress_app_sites_expired_delete_site', '=', '1' ),
				'tab'     => 'wordpress-app-sites',
			),
			array(
				'id'      => 'wordpress_app_sites_expired_admin_lock_site',
				'type'    => 'checkbox',
				'name'    => __( 'Apply Admin Lock ?', 'simple-stack' ),
				'tooltip' => __( 'The admin lock will disable all tabs for the site. A the customer cannot manage it or reactivate it. Only an admin will be able to remove the lock.', 'simple-stack' ),
				'hidden'  => array( 'wordpress_app_sites_expired_delete_site', '=', '1' ),
				'tab'     => 'wordpress-app-sites',
			),
			array(
				'type' => 'custom_html',
				'std'  => __( 'Important Note: The options above are only implemented when a site is not yet expired and hits the expiration date. If an admin manually expires a site, none of these options are implemented.', 'simple-stack' ),
				'tab'  => 'wordpress-app-sites',
			),
			array(
				'id'      => 'wordpress_app_sites_enable_expiration_bulk_actions',
				'type'    => 'checkbox',
				'name'    => __( 'Enable Bullk Actions ?', 'simple-stack' ),
				'tooltip' => __( 'Add one or more expiration related bulk action options to the sites list', 'simple-stack' ),
				'hidden'  => array( 'wordpress_app_sites_expired_delete_site', '=', '1' ),
				'tab'     => 'wordpress-app-sites',
			),
			array(
				'type' => 'heading',
				'name' => __( 'Admin Lock', 'simple-stack' ),
				'desc' => '',
				'tab'  => 'wordpress-app-sites',
			),
			array(
				'id'      => 'wordpress_app_sites_default_admin_lock_msg',
				'type'    => 'wysiwyg',
				'options' => array(
					'textarea_rows' => 8,
				),
				'name'    => __( 'Message To Display When Admin Lock Is Applied', 'simple-stack' ),
				'tooltip' => __( 'This message will display on the GENERAL tab of a site when an admin lock is applied.', 'simple-stack' ),
				'tab'     => 'wordpress-app-sites',
			),

			array(
				'type' => 'heading',
				'name' => __( 'Misc', 'simple-stack' ),
				'desc' => '',
				'tab'  => 'wordpress-app-sites',
			),
			array(
				'id'      => 'wordpress_app_do_not_delete_sftp_users_on_site_delete',
				'type'    => 'checkbox',
				'name'    => __( 'Do not delete sFTP users when a site is removed.', 'simple-stack' ),
				'tooltip' => __( 'If checked, sFTP users will remain behind as regular Linux users on the Linux server after a site is deleted.  You will not be able to reuse these user names on new sites', 'simple-stack' ),
				'tab'     => 'wordpress-app-sites',
			),
			array(
				'id'   => 'wordpress_app_disable_passwordless_login',
				'type' => 'checkbox',
				'name' => __( 'Disable the One-click Login Buttons.', 'simple-stack' ),
				'tab'  => 'wordpress-app-sites',
			),
			array(
				'id'      => 'wordpress_app_show_install_button_with_apt_get_running',
				'type'    => 'checkbox',
				'name'    => __( 'Show The INSTALL WORDPRESS Button When Background Updates are Running.', 'simple-stack' ),
				'tooltip' => __( 'If checked, the INSTALL WORDPRESS button will show up even if Linux updates are being run in the background.', 'simple-stack' ),
				'tab'     => 'wordpress-app-sites',
			),
		);

		return $fields;
	}


	/**
	 * Array of fields used in the fields and links tab.
	 */
	public function fields_and_links() {

		$fields = array(
			array(
				'id'   => 'wordpress_fields_and_links_heading_01',
				'type' => 'heading',
				'name' => __( 'Servers', 'simple-stack' ),
				'desc' => __( 'Show or hide certain fields in the server list and server screens.', 'simple-stack' ),
				'tab'  => 'wordpress-app-fields-and-links',
			),
			array(
				'id'      => 'wordpress_app_show_install_wp_link_in_server_list',
				'type'    => 'checkbox',
				'name'    => __( 'Show Install WP Link', 'simple-stack' ),
				'tooltip' => __( 'Show an INSTALL WORDPRESS link under the Title column in the Server List', 'simple-stack' ),
				'tab'     => 'wordpress-app-fields-and-links',
			),
			array(
				'id'      => 'wordpress_app_show_logs_dropdown_in_server_list',
				'type'    => 'checkbox',
				'name'    => __( 'Show Logs Drop-down', 'simple-stack' ),
				'tooltip' => __( 'Show a dropdown of WordPress installation log attempts under the Title column in the Server List', 'simple-stack' ),
				'tab'     => 'wordpress-app-fields-and-links',
			),
			array(
				'id'      => 'wordpress_app_show_web_server_type_column_in_server_list',
				'type'    => 'checkbox',
				'name'    => __( 'Show Web Server Column', 'simple-stack' ),
				'tooltip' => __( 'Show the webserver type as a separate column in the servers list.  Usually it is shown underneath the INSTALL WORDPRESS button in the server actions column.', 'simple-stack' ),
				'tab'     => 'wordpress-app-fields-and-links',
			),
			array(
				'id'      => 'wordpress_app_hide_web_server_element_in_server_list',
				'type'    => 'checkbox',
				'name'    => __( 'Hide Web Server Element', 'simple-stack' ),
				'tooltip' => __( 'Hide the webserver type from the server actions column.', 'simple-stack' ),
				'tab'     => 'wordpress-app-fields-and-links',
			),
			array(
				'id'      => 'wordpress_app_show_server_instance_id_element_in_server_list',
				'type'    => 'checkbox',
				'name'    => __( 'Show Instance Id Element', 'simple-stack' ),
				'tooltip' => __( 'Show the provider server instance id in the PROVIDER DETAILS column in the server list.', 'simple-stack' ),
				'tab'     => 'wordpress-app-fields-and-links',
			),
			array(
				'id'   => 'wordpress_fields_and_links_heading_02',
				'type' => 'heading',
				'name' => __( 'Sites', 'simple-stack' ),
				'desc' => __( 'Show or hide certain fields in the site list and site screens.', 'simple-stack' ),
				'tab'  => 'wordpress-app-fields-and-links',
			),
			array(
				'id'      => 'wordpress_app_show_staging_column_in_site_list',
				'type'    => 'checkbox',
				'name'    => __( 'Show Staging Column', 'simple-stack' ),
				'tooltip' => __( 'Show the staging column in the site list', 'simple-stack' ),
				'tab'     => 'wordpress-app-fields-and-links',
			),
			array(
				'id'      => 'wordpress_app_show_web_server_type_column_in_site_list',
				'type'    => 'checkbox',
				'name'    => __( 'Show Web Server Column', 'simple-stack' ),
				'tooltip' => __( 'Show the webserver type as a separate column in the site list. Usually this data is shown in the server column', 'simple-stack' ),
				'tab'     => 'wordpress-app-fields-and-links',
			),
			array(
				'id'      => 'wordpress_app_hide_web_server_element_in_site_list',
				'type'    => 'checkbox',
				'name'    => __( 'Hide Web Server Element', 'simple-stack' ),
				'tooltip' => __( 'Hide the webserver type from the server column.', 'simple-stack' ),
				'tab'     => 'wordpress-app-fields-and-links',
			),
			array(
				'id'      => 'wordpress_app_hide_chicklet_area_in_site_detail',
				'type'    => 'checkbox',
				'name'    => __( 'Hide Chicklet Area', 'simple-stack' ),
				'tooltip' => __( 'Hides the three labels that show the web server type, SSL status and Cache status beneath the domain name in the site detail screen.', 'simple-stack' ),
				'tab'     => 'wordpress-app-fields-and-links',
			),
			array(
				'id'   => 'wordpress_fields_and_links_heading_03',
				'type' => 'heading',
				'name' => __( 'Sites: Compound Fields', 'simple-stack' ),
				'desc' => __( 'Show or hide certain fields in the site list and site screens.', 'simple-stack' ),
				'tab'  => 'wordpress-app-fields-and-links',
			),
			array(
				'id'      => 'wordpress_app_hide_domain_site_summary_column_in_site_list',
				'type'    => 'checkbox',
				'name'    => __( 'Hide Domain in Site Summary Column', 'simple-stack' ),
				'tooltip' => __( 'Hide the domain in the site summary column from non-admins in the site list. This is useful because it is sometimes redundant with the title column.', 'simple-stack' ),
				'tab'     => 'wordpress-app-fields-and-links',
			),
			array(
				'id'      => 'wordpress_app_hide_login_user_site_summary_column_in_site_list',
				'type'    => 'checkbox',
				'name'    => __( 'Hide Login User in Site Summary Column', 'simple-stack' ),
				'tooltip' => __( 'Hide the login user in the site summary column from non-admins in the site list', 'simple-stack' ),
				'tab'     => 'wordpress-app-fields-and-links',
			),
			array(
				'id'      => 'wordpress_app_hide_initial_wp_version_site_summary_column_in_site_list',
				'type'    => 'checkbox',
				'name'    => __( 'Hide Initial WP Version in Site Summary Column', 'simple-stack' ),
				'tooltip' => __( 'Hide the initial WP version data in the site summary column from non-admins in the site list', 'simple-stack' ),
				'tab'     => 'wordpress-app-fields-and-links',
			),
			array(
				'id'   => 'wordpress_fields_and_links_heading_04',
				'type' => 'heading',
				'name' => __( 'Sites: Other', 'simple-stack' ),
				'desc' => __( 'Show or hide certain information in the site list and site screens.', 'simple-stack' ),
				'tab'  => 'wordpress-app-fields-and-links',
			),
			array(
				'id'      => 'wordpress_app_hide_about_caches_text',
				'type'    => 'checkbox',
				'name'    => __( 'Hide the About Caches text on the WordPress Site Cache Tab', 'simple-stack' ),
				'tooltip' => __( 'Hide the very long explanation about caches on the WordPress Site cache tab', 'simple-stack' ),
				'tab'     => 'wordpress-app-fields-and-links',
			),
			array(
				'id'      => 'wordpress_app_hide_change_domain_explanatory_text',
				'type'    => 'checkbox',
				'name'    => __( 'Hide Explanatory Text on the WordPress Site Change Domain Tab', 'simple-stack' ),
				'tooltip' => __( 'Hide the very long explanations on the WordPress Site Change Domain tab.', 'simple-stack' ),
				'tab'     => 'wordpress-app-fields-and-links',
			),
			array(
				'id'      => 'wordpress_app_simplify_change_domain_for_non_admin',
				'type'    => 'checkbox',
				'name'    => __( 'Show Simplified Change Domain Options For Non-Admin Users', 'simple-stack' ),
				'tooltip' => __( 'If a user is not an admin, remove almost everything from the CHANGE DOMAIN tab, except the options to run a full-live change domain operation.', 'simple-stack' ),
				'tab'     => 'wordpress-app-fields-and-links',
			),
			array(
				'id'      => 'wordpress_app_hide_addl_stats_explanatory_text',
				'type'    => 'checkbox',
				'name'    => __( 'Hide Additional Statistics box on the WordPress Site Statistics Tab', 'simple-stack' ),
				'tooltip' => __( 'Hide the additional statisics box on the WordPress Site statistics tab from non-admin users.', 'simple-stack' ),
				'tab'     => 'wordpress-app-fields-and-links',
			),
			array(
				'id'      => 'wordpress_app_hide_view_apps_on_server_link',
				'type'    => 'checkbox',
				'name'    => __( 'Hide View Apps On Server link From Non-Admin Users', 'simple-stack' ),
				'tooltip' => __( 'If a user is not an admin, hide the VIEW APPS ON SERVER link at the top of the site details page.', 'simple-stack' ),
				'tab'     => 'wordpress-app-fields-and-links',
			),
		);

		return $fields;
	}

	/**
	 * Array of fields used in the front-end fields tab.
	 */
	public function front_end_fields() {

		$fields = array(
			array(
				'id'   => 'wordpress_front_end_fields_heading_01',
				'type' => 'heading',
				'name' => __( 'Servers - Show Cards', 'simple-stack' ),
				'desc' => __( 'Show fields in the server list and server screens when displaying data on the front-end.  These fields are usually HIDDEN by default.', 'simple-stack' ),
				'tab'  => 'wordpress-app-front-end-fields',
			),
			array(
				'id'      => 'wordpress_app_fe_show_description_in_server_list',
				'type'    => 'checkbox',
				'name'    => __( 'Show Description', 'simple-stack' ),
				'tooltip' => __( 'Show the description field/card in the server list on the front-end.', 'simple-stack' ),
				'tab'     => 'wordpress-app-front-end-fields',
			),
			array(
				'id'      => 'wordpress_app_fe_show_server_group_in_server_list',
				'type'    => 'checkbox',
				'name'    => __( 'Show Server Group', 'simple-stack' ),
				'tooltip' => __( 'Show the server group field/card in the server list on the front-end.', 'simple-stack' ),
				'tab'     => 'wordpress-app-front-end-fields',
			),
			array(
				'id'      => 'wordpress_app_fe_show_owner_in_server_list',
				'type'    => 'checkbox',
				'name'    => __( 'Show Owner', 'simple-stack' ),
				'tooltip' => __( 'Show the owner field/card in the server list on the front-end.', 'simple-stack' ),
				'tab'     => 'wordpress-app-front-end-fields',
			),
			array(
				'id'      => 'wordpress_app_fe_show_teams_in_server_list',
				'type'    => 'checkbox',
				'name'    => __( 'Show Teams', 'simple-stack' ),
				'tooltip' => __( 'Show the teams list field/card in the server list on the front-end.', 'simple-stack' ),
				'tab'     => 'wordpress-app-front-end-fields',
			),
			array(
				'id'      => 'wordpress_app_fe_show_web_server_type_column_in_server_list',
				'type'    => 'checkbox',
				'name'    => __( 'Show Web Server Type', 'simple-stack' ),
				'tooltip' => __( 'Show the web server field/card in the server list on the front-end. This column can usually be shown even if it is hidden in wp-admin.', 'simple-stack' ),
				'tab'     => 'wordpress-app-front-end-fields',
			),
			array(
				'id'      => 'wordpress_app_fe_show_sync_status_in_server_list',
				'type'    => 'checkbox',
				'name'    => __( 'Show Server Sync Data', 'simple-stack' ),
				'tooltip' => __( 'Show the server sync field/card in the server list on the front-end.', 'simple-stack' ),
				'tab'     => 'wordpress-app-front-end-fields',
			),

			array(
				'id'   => 'wordpress_front_end_fields_heading_02',
				'type' => 'heading',
				'name' => __( 'Servers - Hide Cards', 'simple-stack' ),
				'desc' => __( 'Hide cards in the server list and related screens when displaying data on the front-end. These cards are usually shown by default.', 'simple-stack' ),
				'tab'  => 'wordpress-app-front-end-fields',
			),

			array(
				'id'      => 'wordpress_app_fe_hide_title_in_server_list',
				'type'    => 'checkbox',
				'name'    => __( 'Hide Title', 'simple-stack' ),
				'tooltip' => __( 'Hide the title field/card in the server list on the front-end.', 'simple-stack' ),
				'tab'     => 'wordpress-app-front-end-fields',
			),
			array(
				'id'      => 'wordpress_app_fe_hide_server_actions_in_server_list',
				'type'    => 'checkbox',
				'name'    => __( 'Hide Server Actions', 'simple-stack' ),
				'tooltip' => __( 'Hide the server actions field/card in the server list on the front-end.', 'simple-stack' ),
				'tab'     => 'wordpress-app-front-end-fields',
			),
			array(
				'id'      => 'wordpress_app_fe_hide_provider_details_in_server_list',
				'type'    => 'checkbox',
				'name'    => __( 'Hide Provider Details', 'simple-stack' ),
				'tooltip' => __( 'Hide the provider details field/card in the server list on the front-end.', 'simple-stack' ),
				'tab'     => 'wordpress-app-front-end-fields',
			),
			array(
				'id'      => 'wordpress_app_fe_hide_local_status_in_server_list',
				'type'    => 'checkbox',
				'name'    => __( 'Hide Local Status', 'simple-stack' ),
				'tooltip' => __( 'Hide the local status field/card in the server list on the front-end.', 'simple-stack' ),
				'tab'     => 'wordpress-app-front-end-fields',
			),
			array(
				'id'      => 'wordpress_app_fe_hide_app_count_in_server_list',
				'type'    => 'checkbox',
				'name'    => __( 'Hide App Count', 'simple-stack' ),
				'tooltip' => __( 'Hide the app count field/card in the server list on the front-end.', 'simple-stack' ),
				'tab'     => 'wordpress-app-front-end-fields',
			),
			array(
				'id'      => 'wordpress_app_fe_hide_web_server_type_element_in_server_list',
				'type'    => 'checkbox',
				'name'    => __( 'Hide Web Server Type (Element)', 'simple-stack' ),
				'tooltip' => __( 'Hide the web server field on the SERVER ACTIONS field/card in the server list on the front-end.  This element can usually be hidden even if it is visible in wp-admin.', 'simple-stack' ),
				'tab'     => 'wordpress-app-front-end-fields',
			),
			array(
				'id'      => 'wordpress_app_fe_hide_health_in_server_list',
				'type'    => 'checkbox',
				'name'    => __( 'Hide Health', 'simple-stack' ),
				'tooltip' => __( 'Hide the health field/card in the server list on the front-end.', 'simple-stack' ),
				'tab'     => 'wordpress-app-front-end-fields',
			),

			array(
				'id'   => 'wordpress_front_end_fields_heading_03',
				'type' => 'heading',
				'name' => __( 'Sites - Show Cards', 'simple-stack' ),
				'desc' => __( 'Show cards in the app/site list and related screens when displaying data on the front-end. These cards are usually HIDDEN by default.', 'simple-stack' ),
				'tab'  => 'wordpress-app-front-end-fields',
			),
			array(
				'id'      => 'wordpress_app_fe_show_description_in_app_list',
				'type'    => 'checkbox',
				'name'    => __( 'Show Description', 'simple-stack' ),
				'tooltip' => __( 'Show the description field/card in the app list on the front-end.', 'simple-stack' ),
				'tab'     => 'wordpress-app-front-end-fields',
			),
			array(
				'id'      => 'wordpress_app_fe_show_app_group_in_app_list',
				'type'    => 'checkbox',
				'name'    => __( 'Show App Group', 'simple-stack' ),
				'tooltip' => __( 'Show the application group field/card in the app list on the front-end.', 'simple-stack' ),
				'tab'     => 'wordpress-app-front-end-fields',
			),
			array(
				'id'      => 'wordpress_app_fe_show_cache_in_app_list',
				'type'    => 'checkbox',
				'name'    => __( 'Show Cache', 'simple-stack' ),
				'tooltip' => __( 'Show the cache field/card in the app list on the front-end. This data is already shown in the APP FEATURES card but you can choose to also show it in a separate card.', 'simple-stack' ),
				'tab'     => 'wordpress-app-front-end-fields',
			),
			array(
				'id'      => 'wordpress_app_fe_show_php_in_app_list',
				'type'    => 'checkbox',
				'name'    => __( 'Show PHP', 'simple-stack' ),
				'tooltip' => __( 'Show the php field/card in the app list on the front-end. This data is already shown in the APP FEATURES card but you can choose to also show it in a separate card.', 'simple-stack' ),
				'tab'     => 'wordpress-app-front-end-fields',
			),
			array(
				'id'      => 'wordpress_app_fe_show_ssl_in_app_list',
				'type'    => 'checkbox',
				'name'    => __( 'Show SSL', 'simple-stack' ),
				'tooltip' => __( 'Show the SSL field/card in the app list on the front-end. This data is already shown in the APP FEATURES card but you can choose to also show it in a separate card.', 'simple-stack' ),
				'tab'     => 'wordpress-app-front-end-fields',
			),
			array(
				'id'      => 'wordpress_app_fe_show_web_server_type_column_in_app_list',
				'type'    => 'checkbox',
				'name'    => __( 'Show Web Server Type', 'simple-stack' ),
				'tooltip' => __( 'Show the web server field/card in the app list on the front-end.', 'simple-stack' ),
				'tab'     => 'wordpress-app-front-end-fields',
			),
			array(
				'id'      => 'wordpress_app_fe_show_owner_in_app_list',
				'type'    => 'checkbox',
				'name'    => __( 'Show Owner', 'simple-stack' ),
				'tooltip' => __( 'Show the owner list field/card in the app list on the front-end.', 'simple-stack' ),
				'tab'     => 'wordpress-app-front-end-fields',
			),
			array(
				'id'      => 'wordpress_app_fe_show_teams_in_app_list',
				'type'    => 'checkbox',
				'name'    => __( 'Show Teams', 'simple-stack' ),
				'tooltip' => __( 'Show the teams list field/card in the app list on the front-end.', 'simple-stack' ),
				'tab'     => 'wordpress-app-front-end-fields',
			),

			array(
				'id'   => 'wordpress_front_end_fields_heading_04',
				'type' => 'heading',
				'name' => __( 'Sites - Hide Cards', 'simple-stack' ),
				'desc' => __( 'Hide cards in the app/site list and related screens when displaying data on the front-end. These cards are usually shown by default.', 'simple-stack' ),
				'tab'  => 'wordpress-app-front-end-fields',
			),
			array(
				'id'      => 'wordpress_app_fe_hide_app_title_in_app_list',
				'type'    => 'checkbox',
				'name'    => __( 'Hide Title', 'simple-stack' ),
				'tooltip' => __( 'Hide the title field/card in the app list on the front-end.', 'simple-stack' ),
				'tab'     => 'wordpress-app-front-end-fields',
			),
			array(
				'id'      => 'wordpress_app_fe_hide_app_summary_in_app_list',
				'type'    => 'checkbox',
				'name'    => __( 'Hide App Summary', 'simple-stack' ),
				'tooltip' => __( 'Hide the app summary field/card in the app list on the front-end.', 'simple-stack' ),
				'tab'     => 'wordpress-app-front-end-fields',
			),
			array(
				'id'      => 'wordpress_app_fe_hide_app_features_in_app_list',
				'type'    => 'checkbox',
				'name'    => __( 'Hide App Features', 'simple-stack' ),
				'tooltip' => __( 'Hide the app features field/card in the app list on the front-end.', 'simple-stack' ),
				'tab'     => 'wordpress-app-front-end-fields',
			),
			array(
				'id'      => 'wordpress_app_fe_hide_app_health_in_app_list',
				'type'    => 'checkbox',
				'name'    => __( 'Hide App Health', 'simple-stack' ),
				'tooltip' => __( 'Hide the app health field/card in the app list on the front-end.', 'simple-stack' ),
				'tab'     => 'wordpress-app-front-end-fields',
			),
			array(
				'id'      => 'wordpress_app_fe_hide_server_in_app_list',
				'type'    => 'checkbox',
				'name'    => __( 'Hide Server', 'simple-stack' ),
				'tooltip' => __( 'Hide the server field/card in the app list on the front-end.', 'simple-stack' ),
				'tab'     => 'wordpress-app-front-end-fields',
			),
			array(
				'id'      => 'wordpress_app_fe_hide_staging_in_app_list',
				'type'    => 'checkbox',
				'name'    => __( 'Hide Staging', 'simple-stack' ),
				'tooltip' => __( 'Hide the staging field/card in the app list on the front-end.', 'simple-stack' ),
				'tab'     => 'wordpress-app-front-end-fields',
			),
			array(
				'id'      => 'wordpress_app_fe_hide_web_server_type_element_in_app_list',
				'type'    => 'checkbox',
				'name'    => __( 'Hide Web Server Type (Element)', 'simple-stack' ),
				'tooltip' => __( 'Hide the web server field on the SERVER field/card in the app list on the front-end.  This element can usually be hidden even if it is visible in wp-admin.', 'simple-stack' ),
				'tab'     => 'wordpress-app-front-end-fields',
			),
			array(
				'id'   => 'wordpress_front_end_fields_heading_notes',
				'type' => 'heading',
				'desc' => __( 'Note: Using the options above to turn fields and cards on/off on the front-end has a relationship with the visibility of the fields in wp-admin.  In most instances, fields that are not shown in wp-admin cannot be shown on the front-end even if enabled here - and vice-versa. If a toggle here does not work please double-check whether the field is visible or hidden in wp-admin.', 'simple-stack' ),
				'tab'  => 'wordpress-app-front-end-fields',
			),
			array(
				'id'   => 'wordpress_front_end_fields_heading_filter_bar',
				'type' => 'heading',
				'name' => __( 'Filter Bar', 'simple-stack' ),
				'desc' => __( 'Show/Hide Filter Bar.', 'simple-stack' ),
				'tab'  => 'wordpress-app-front-end-fields',
			),
			array(
				'id'      => 'wordpress_app_fe_show_filter_bar',
				'type'    => 'checkbox',
				'name'    => __( 'Show Filter Bar To Non-Admins', 'simple-stack' ),
				'tooltip' => __( 'Show the filter bar at the top of the server and site list to non-admin users. This is usually hidden from non-admins.', 'simple-stack' ),
				'tab'     => 'wordpress-app-front-end-fields',
			),
			array(
				'id'      => 'wordpress_app_fe_hide_filter_bar_from_admin',
				'type'    => 'checkbox',
				'name'    => __( 'Hide Filter Bar From Admin', 'simple-stack' ),
				'tooltip' => __( 'HIDE the filter bar at the top of the server and site list from the admin. Usually this is shown to admins.', 'simple-stack' ),
				'tab'     => 'wordpress-app-front-end-fields',
			),
			array(
				'id'   => 'wordpress_front_end_fields_install_wp_button',
				'type' => 'heading',
				'name' => __( 'Install WP Button', 'simple-stack' ),
				'tab'  => 'wordpress-app-front-end-fields',
			),
			array(
				'id'      => 'wordpress_app_fe_show_install_wp_button_top_of_list_page',
				'type'    => 'checkbox',
				'name'    => __( 'Show the Install WordPress Button', 'simple-stack' ),
				'tooltip' => __( 'Show the install WordPress button at the top of the list page?', 'simple-stack' ),
				'tab'     => 'wordpress-app-front-end-fields',
			),

		);

		return $fields;
	}


	/**
	 * Array of fields used to store the default s3 backup settings
	 * as well as other backup related options.
	 */
	public function backup_fields() {

		$fields = array(
			array(
				'id'   => 'wordpress_app_backup_heading_01',
				'type' => 'heading',
				'name' => __( 'AWS S3 Credentials', 'simple-stack' ),
				'desc' => __( 'Sites can be backed up to AWS S3.  These are the AWS credentials that will be used for all sites when backing up and restoring data.  If you need to, you can set different credentials for each server - you can do that in the CLOUD SERVERS screens.', 'simple-stack' ),
				'tab'  => 'wordpress-app-backup',
			),
			array(
				'id'         => 'wordpress_app_aws_access_key',
				'type'       => 'text',
				'name'       => __( 'AWS Access Key ID', 'simple-stack' ),
				'tooltip'    => __( 'AWS Access Key ID', 'simple-stack' ),
				'tab'        => 'wordpress-app-backup',
				'std'        => sstk_get_option( 'wordpress_app_aws_access_key' ),
				'size'       => 60,
				'attributes' => array(
					'spellcheck' => 'false',
				),
			),
			array(
				'id'         => 'wordpress_app_aws_secret_key',
				'type'       => 'text',
				'name'       => __( 'AWS Secret Key', 'simple-stack' ),
				'tooltip'    => __( 'AWS Secret Key', 'simple-stack' ),
				'tab'        => 'wordpress-app-backup',
				'size'       => 60,
				'attributes' => array(
					'spellcheck' => 'false',
				),
			),
			array(
				'id'      => 'wordpress_app_aws_default_region',
				'type'    => 'text',
				'name'    => __( 'Default Region', 'simple-stack' ),
				'tooltip' => __( 'The region where your default bucket is located. If this is incorrect and does not match your bucket location, backups will not be uploaded to AWS!', 'simple-stack' ),
				'desc'    => sprintf( __( '<a href="%s" target="_blank" >Valid Regions</a>', 'simple-stack' ), 'https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/using-regions-availability-zones.html#concepts-available-regions' ),
				'tab'     => 'wordpress-app-backup',
				'size'    => 60,
			),
			array(
				'id'         => 'wordpress_app_aws_bucket',
				'type'       => 'text',
				'name'       => __( 'AWS Bucket Name', 'simple-stack' ),
				'tooltip'    => __( 'AWS Bucket Name', 'simple-stack' ),
				'tab'        => 'wordpress-app-backup',
				'std'        => sstk_get_option( 'wordpress_app_aws_bucket' ),
				'size'       => 60,
				'attributes' => array(
					'spellcheck' => 'false',
				),
			),
			array(
				'id'                => 'wordpress_app_s3_endpoint',
				'type'              => 'text',
				'name'              => __( 'S3 Endpoint URL', 'simple-stack' ),
				'label_description' => __( 'Optional', 'simple-stack' ),
				'placeholder'       => __( 'https://endpoint.com', 'simple-stack' ),
				'tooltip'           => __( 'Only set this if you want to use an alternative S3-compatible service. MUST start with "https://"!', 'simple-stack' ),
				'tab'               => 'wordpress-app-backup',
				'std'               => sstk_get_option( 'wordpress_app_s3_endpoint' ),
				'size'              => 60,
			),
			array(
				'id'   => 'wordpress_app_backup_warning',
				'type' => 'custom_html',
				'std'  => __( 'Warning: Credentials configured here are available to all servers unless overridden on an individual server. Users who can access a server may be able to view its backup credentials. Set credentials on each server instead when users should not see them.', 'simple-stack' ),
				'tab'  => 'wordpress-app-backup',
			),

			// Fields that will change how the backup & restore options appear on the sites tab.
			array(
				'id'   => 'wordpress_app_backup_heading_02',
				'type' => 'heading',
				'name' => __( 'Simplify Backup Options on Sites', 'simple-stack' ),
				'desc' => __( 'For sites, you can remove certain fields to simplify the screen for your users. If you do this, then the value of those fields will default to either the server value or the global values. Please note that admins will always be able to see these fields.', 'simple-stack' ),
				'tab'  => 'wordpress-app-backup',
			),
			array(
				'id'      => 'wordpress_app_site_backup_hide_aws_bucket_name',
				'type'    => 'checkbox',
				'name'    => __( 'Hide the AWS S3 bucket name', 'simple-stack' ),
				'tooltip' => __( 'Hide the AWS S3 bucket name field on the backup tab on the sites screen.', 'simple-stack' ),
				'tab'     => 'wordpress-app-backup',
			),
			array(
				'id'      => 'wordpress_app_site_backup_hide_retention_days',
				'type'    => 'checkbox',
				'name'    => __( 'Hide Retention Days', 'simple-stack' ),
				'tooltip' => __( 'Hide the RETENTION DAYS field on the backup tab on the sites screen.', 'simple-stack' ),
				'tab'     => 'wordpress-app-backup',
			),
			array(
				'id'      => 'wordpress_app_site_backup_hide_del_remote_backups',
				'type'    => 'checkbox',
				'name'    => __( 'Hide Delete Remote Backups', 'simple-stack' ),
				'tooltip' => __( 'Hide the DELETE REMOTE BACKUPS option field on the backup tab on the sites screen.', 'simple-stack' ),
				'tab'     => 'wordpress-app-backup',
			),
			array(
				// Note: If we ever decide to create action options on teams or for owners, this conditional should be removed in favor of those.
				'id'      => 'wordpress_app_site_backup_hide_prune_backups_section',
				'type'    => 'checkbox',
				'name'    => __( 'Hide Prune Backups Section', 'simple-stack' ),
				'tooltip' => __( 'Hide the entire PRUNE BACKUPS section on the backup tab on the sites screen.', 'simple-stack' ),
				'tab'     => 'wordpress-app-backup',
			),

		);

		return $fields;

	}

	/**
	 * Array of fields used to get the theme & plugin update settings.
	 */
	public function theme_and_plugin_updates() {

		$fields = array(

			array(
				'id'   => 'wordpress_app_t_and_p_updates_heading_01',
				'type' => 'heading',
				'name' => __( 'Image Comparison', 'simple-stack' ),
				'desc' => __( 'We use the https://htmlcsstoimage.com/ service to create and compare images taken of the site before and after the update. This helps us to determine if there were any significant changes to the site after the update is complete. And, if there were, automatically roll back the changes. Enter your API credentials for the https://htmlcsstoimage.com/ in the INTEGRATIONS tab.', 'simple-stack' ),
				'tab'  => 'wordpress-app-plugin-theme-updates',
			),

			array(
				'id'      => 'wordpress_app_tandc_updates_pixel_threshold',
				'type'    => 'number',
				'name'    => __( 'Max Pixel Variance', 'simple-stack' ),
				'tooltip' => __( 'What is maximum number of pixels that are allowed to be different between before and after images? If the pixel variance exceeds this value the updates will be rolled back. ', 'simple-stack' ),
				'tab'     => 'wordpress-app-plugin-theme-updates',
				'std'     => Max( 1, (int) sstk_get_option( 'wordpress_app_tandc_updates_pixel_threshold' ) ),
				'size'    => 10,
				'min'     => -1,
				'max'     => 1000000,
			),

			array(
				'id'   => 'wordpress_app_t_and_p_updates_heading_02',
				'type' => 'heading',
				'name' => __( 'Excluded Plugins', 'simple-stack' ),
				'desc' => __( 'Never update these plugins', 'simple-stack' ),
				'tab'  => 'wordpress-app-plugin-theme-updates',
			),
			array(
				'id'      => 'wordpress_app_plugin_updates_excluded_list',
				'type'    => 'text',
				'name'    => __( 'Exclude These Plugins From Updates', 'simple-stack' ),
				'desc'    => __( 'Enter the list of plugins separated by commas - eg: akismet,awesome-suppport, woocommerce', 'simple-stack' ),
				'tooltip' => __( 'The name is usually the FOLDER in which the plugin is installed - you will not find this at the top of the main plugin file. ', 'simple-stack' ),
				'tab'     => 'wordpress-app-plugin-theme-updates',
			),

			array(
				'id'   => 'wordpress_app_t_and_p_updates_heading_03',
				'type' => 'heading',
				'name' => __( 'Excluded Themes', 'simple-stack' ),
				'desc' => __( 'Never update these Themes', 'simple-stack' ),
				'tab'  => 'wordpress-app-plugin-theme-updates',
			),
			array(
				'id'      => 'wordpress_app_theme_updates_excluded_list',
				'type'    => 'text',
				'name'    => __( 'Exclude These Themes From Updates', 'simple-stack' ),
				'desc'    => __( 'Enter the list of themes separated by commas - eg: ocean,beaver-builder,divi', 'simple-stack' ),
				'tooltip' => __( 'The name is usually the FOLDER in which the theme is installed - you will not find this at the top of the main theme css file. ', 'simple-stack' ),
				'tab'     => 'wordpress-app-plugin-theme-updates',
			),

		);

		return $fields;

	}

	/**
	 * Thresholds to trigger certain alerts / notifications.
	 */
	public function alert_config() {

		$fields = array(
			array(
				'id'   => 'wordpress_app_alert_config_heading',
				'type' => 'heading',
				'name' => __( 'Thresholds For Certain Notifications', 'simple-stack' ),
				'tab'  => 'wordpress-app-alert-config',
			),
			array(
				'id'      => 'wordpress_app_low_diskspace_notification_threshold',
				'type'    => 'number',
				'name'    => __( 'Low Disk Space %', 'simple-stack' ),
				'desc'    => __( 'If diskspace falls below this limit, a record will be written to the notification log.', 'simple-stack' ),
				'tab'     => 'wordpress-app-alert-config',
				'size'    => 60,
				'std'     => 15,
				'min'     => 0,
				'max'     => 100,
				'tooltip' => array(
					'content'  => __( 'Default is 15%.  Note that diskspace limits are only evaluated once every day when callbacks are run on the server.  It is not a real-time alert.', 'simple-stack' ),
					'position' => 'right',
				),
			),
			array(
				'id'   => 'wordpress_app_alert_footer_notice',
				'type' => 'custom_html',
				'std'  => __( 'Note: You can configure the format and contents of your notifications in the EMAIL / SLACK / ZAPIER NOTIFICATIONS tabs below. Setup your personalized alert profiles in SERVER ALERTS → NOTIFICATIONS.', 'simple-stack' ),
				'tab'  => 'wordpress-app-alert-config',
			),
		);

		return $fields;

	}

	/**
	 * Array of fields used to store the email notification text.
	 */
	public function email_notification_fields() {

		$fields = array(
			array(
				'id'   => 'wordpress_app_email_notify_heading',
				'type' => 'heading',
				'name' => __( 'Email Notification for user', 'simple-stack' ),
				'desc' => __( 'This message is sent to a user when the user has configured a notification profile and a notification event matches the profile.', 'simple-stack' ),
				'tab'  => 'wordpress-app-notifications',
			),
			array(
				'id'   => 'wordpress_app_email_notify_subject',
				'type' => 'text',
				'name' => __( 'Subject', 'simple-stack' ),
				'tab'  => 'wordpress-app-notifications',
				'size' => 60,
			),
			array(
				'id'      => 'wordpress_app_email_notify_body',
				'type'    => 'wysiwyg',
				'name'    => __( 'Body', 'simple-stack' ),
				'desc'    => __( 'Valid substitutions are: ##USERNAME##, ##FIRST_NAME##, ##LAST_NAME##, ##TYPE##, ##MESSAGE##, ##REFERENCE##, ##SERVERNAME## ##DOMAIN##, ##DATE##, ##TIME##, ##SERVERID##, ##SITEID##, ##IPV4##, ##PROVIDER##.', 'simple-stack' ),
				'options' => array(
					'textarea_rows' => 12,
				),
				'tab'     => 'wordpress-app-notifications',
				'size'    => 60,
			),
		);

		return $fields;

	}

	/**
	 * Array of fields used to store the slack notification text.
	 */
	public function slack_notification_fields() {

		$fields = array(
			array(
				'id'   => 'wordpress_app_slack_notify_heading',
				'type' => 'heading',
				'name' => __( 'Slack Notification for user', 'simple-stack' ),
				'desc' => __( 'This message is pushed to a slack channel when the user has configured a notification profile and a notification event matches the profile.', 'simple-stack' ),
				'tab'  => 'wordpress-app-notifications',
			),
			array(
				'id'      => 'wordpress_app_slack_notify_message',
				'type'    => 'wysiwyg',
				'name'    => __( 'Message', 'simple-stack' ),
				'desc'    => __( 'Valid substitutions are: ##USERNAME##, ##FIRST_NAME##, ##LAST_NAME##, ##TYPE##, ##MESSAGE##, ##REFERENCE##, ##SERVERNAME## ##DOMAIN##, ##DATE##, ##TIME##, ##SERVERID##, ##SITEID##, ##IPV4##, ##PROVIDER##.', 'simple-stack' ),
				'options' => array(
					'textarea_rows' => 12,
				),
				'tab'     => 'wordpress-app-notifications',
				'size'    => 60,
			),
		);

		return $fields;

	}

	/**
	 * Array of fields used to store the zapier notification text.
	 */
	public function zapier_notification_fields() {

		$fields = array(
			array(
				'id'   => 'wordpress_app_zapier_notify_heading',
				'type' => 'heading',
				'name' => __( 'Zapier Notification for user', 'simple-stack' ),
				'desc' => __( 'This message is sent to Zapier when the user has configured a notification profile and a notification event matches the profile.', 'simple-stack' ),
				'tab'  => 'wordpress-app-notifications',
			),
			array(
				'id'      => 'wordpress_app_zapier_notify_message',
				'type'    => 'wysiwyg',
				'name'    => __( 'Message', 'simple-stack' ),
				'desc'    => __( 'Valid substitutions are: ##USERNAME##, ##USERID##, ##USEREMAIL##, ##FIRST_NAME##, ##LAST_NAME##, ##TYPE##, ##MESSAGE##, ##REFERENCE##, ##SERVERNAME##, ##DOMAIN##, ##SERVERID##, ##SITEID#, ##IPV4##, ##PROVIDER##, ##DATE##, ##TIME##.', 'simple-stack' ),
				'options' => array(
					'textarea_rows' => 12,
				),
				'tab'     => 'wordpress-app-notifications',
				'size'    => 60,
			),
		);

		return $fields;

	}

	/**
	 * Array of fields used to store information about our integrations include:
	 * - htmlcsstoimage service
	 * - solidwp security
	 * - etc.
	 */
	public function integrations_fields() {

		$fields = array(

			// htmlcsstoapi.
			array(
				'id'   => 'wordpress_app_hcti_heading_01',
				'type' => 'heading',
				'name' => __( 'Image Compare Service API', 'simple-stack' ),
				'desc' => __( 'We use the https://htmlcsstoimage.com/ service to create home page images as well ascompare before-and-after images taken of sites during theme & plugin updates. Enter your API credentials for the https://htmlcsstoimage.com/ service in this section.', 'simple-stack' ),
				'tab'  => 'wordpress-app-integrations',
			),
			array(
				'id'         => 'wordpress_app_hcti_api_user_id',
				'type'       => 'text',
				'name'       => __( 'API User Id', 'simple-stack' ),
				'tooltip'    => __( 'htmlcsstoimage.com API User Id - this is not your htmlcsstoimage.com login or email address.', 'simple-stack' ),
				'tab'        => 'wordpress-app-integrations',
				'std'        => sstk_get_option( 'wordpress_app_hcti_api_user_id' ),
				'size'       => 60,
				'attributes' => array(
					'spellcheck' => 'false',
				),
			),
			array(
				'id'         => 'wordpress_app_hcti_api_key',
				'type'       => 'text',
				'name'       => __( 'API Key', 'simple-stack' ),
				'tooltip'    => __( 'htmlcsstoimage.com API Key', 'simple-stack' ),
				'tab'        => 'wordpress-app-integrations',
				'std'        => sstk_get_option( 'wordpress_app_hcti_api_key' ),
				'size'       => 60,
				'attributes' => array(
					'spellcheck' => 'false',
				),
			),

			// Solidwp Security.
			array(
				'id'   => 'wordpress_app_solidwp_security_heading',
				'type' => 'heading',
				'name' => __( 'SolidWP Security', 'simple-stack' ),
				'desc' => __( 'Configure your SolidWP account information.', 'simple-stack' ),
				'tab'  => 'wordpress-app-integrations',
			),
			array(
				'id'   => 'wordpress_app_solidwp_user_name',
				'type' => 'text',
				'name' => __( 'SolidWP Account User Name', 'simple-stack' ),
				'tab'  => 'wordpress-app-integrations',
				'size' => 60,
			),
			array(
				'id'   => 'wordpress_app_solidwp_password',
				'type' => 'text',
				'name' => __( 'SolidWP Account Password', 'simple-stack' ),
				'tab'  => 'wordpress-app-integrations',
				'size' => 60,
			),
			array(
				'id'      => 'wordpress_app_solidwp_file_url',
				'type'    => 'text',
				'name'    => __( 'SolidWP File URL', 'simple-stack' ),
				'desc'    => __( 'Where is the zip file for SolidWP Security Pro?', 'simple-stack' ),
				'tooltip' => __( 'This should be a fully formed url that starts with https:// and that results in a .zip file.', 'simple-stack' ),
				'tab'     => 'wordpress-app-integrations',
				'size'    => 60,
			),
			array(
				'id'      => 'wordpress_app_solidwp_enable_bulk_actions',
				'type'    => 'checkbox',
				'name'    => __( 'Enable Bulk Actions?', 'simple-stack' ),
				'tooltip' => __( 'Add SolidWP Security actions to the BULK ACTIONS menu in the site list.', 'simple-stack' ),
				'tab'     => 'wordpress-app-integrations',
			),

		);

		return $fields;

	}

	/**
	 * Array of fields used to store the color settings text.
	 */
	public function button_color_settings_fields() {

		$fields = array(
			array(
				'id'   => 'wordpress_app_button_color_heading',
				'type' => 'heading',
				'name' => __( 'Colors for the user notification shortcode', 'simple-stack' ),
				'desc' => __( 'These settings are used to manage the color of the buttons shown when the user notification shortcode is used.', 'simple-stack' ),
				'tab'  => 'wordpress-app-color-settings',
			),
			array(
				'name'          => 'Add New Button',
				'id'            => 'wordpress_app_add_new_button_color',
				'type'          => 'color',
				'alpha_channel' => true,
				'tab'           => 'wordpress-app-color-settings',
			),
			array(
				'name'          => 'Submit Button',
				'id'            => 'wordpress_app_submit_button_color',
				'type'          => 'color',
				'alpha_channel' => true,
				'tab'           => 'wordpress-app-color-settings',
			),
			array(
				'name'          => 'Update Button',
				'id'            => 'wordpress_app_update_button_color',
				'type'          => 'color',
				'alpha_channel' => true,
				'tab'           => 'wordpress-app-color-settings',
			),
			array(
				'name'          => 'Test Button',
				'id'            => 'wordpress_app_test_button_color',
				'type'          => 'color',
				'alpha_channel' => true,
				'tab'           => 'wordpress-app-color-settings',
			),

		);

		return $fields;

	}

	/**
	 * Array of fields used to store the email gateway load defaults settings.
	 */
	public function email_gateway_load_defaults() {

		/* Email Gateway */
		$eg_desc = __( 'Set default values you can use when setting up server level email gateways.', 'simple-stack' );

		$fields = array(
			array(
				'type' => 'heading',
				'name' => __( 'EMAIL GATEWAY', 'simple-stack' ),
				'desc' => $eg_desc,
				'tab'  => 'wordpress-app-email-gateway',
			),
			array(
				'id'      => 'sstk_email_gateway_smtp_server',
				'type'    => 'text',
				'name'    => __( 'SMTP Server & Port', 'simple-stack' ),
				'tooltip' => __( 'Enter the url/address for your outgoing email server - usually in the form of a subdomain.domain.com:port - eg: <i>smtp.ionos.com:587</i>.', 'simple-stack' ),
				'tab'     => 'wordpress-app-email-gateway',
			),
			array(
				'id'         => 'sstk_email_gateway_smtp_user',
				'type'       => 'text',
				'name'       => __( 'User Name', 'simple-stack' ),
				'tooltip'    => __( 'Your user id for connecting to the smtp server', 'simple-stack' ),
				'attributes' => array(
					'spellcheck' => 'false',
				),
				'tab'        => 'wordpress-app-email-gateway',
			),
			array(
				'id'      => 'sstk_email_gateway_smtp_password',
				'type'    => 'password',
				'name'       => __( 'Password', 'simple-stack' ),
				'tooltip'    => __( 'Your password for connecting to the smtp server', 'simple-stack' ),
				'attributes' => array(
					'spellcheck' => 'false',
				),
				'tab'        => 'wordpress-app-email-gateway',
			),
			array(
				'id'      => 'sstk_email_gateway_smtp_domain',
				'type'    => 'text',
				'name'    => __( 'From Domain', 'simple-stack' ),
				'tooltip' => __( 'The default domain for sending messages', 'simple-stack' ),
				'tab'     => 'wordpress-app-email-gateway',
			),
			array(
				'id'      => 'sstk_email_gateway_smtp_hostname',
				'type'    => 'text',
				'name'    => __( 'FQDN Hostname', 'simple-stack' ),
				'tooltip' => __( 'FQDN for the server. Some SMTP servers will require this to be a working domain name (example: server1.myblog.com)', 'simple-stack' ),
				'tab'     => 'wordpress-app-email-gateway',
			),
			array(
				'id'      => 'sstk_email_gateway_smtp_usetls',
				'type'    => 'select',
				'options' => array(
					'YES' => 'Yes',
					'NO'  => 'No',
				),
				'name'    => __( 'Use TLS', 'simple-stack' ),
				'tooltip' => __( 'Use TLS to connect to the SMTP server. Warning! Turning this off has Security implications!', 'simple-stack' ),
				'tab'     => 'wordpress-app-email-gateway',
			),
			array(
				'id'      => 'sstk_email_gateway_smtp_usestarttls',
				'type'    => 'select',
				'options' => array(
					'YES' => 'Yes',
					'NO'  => 'No',
				),
				'name'    => __( 'Use STARTTLS', 'simple-stack' ),
				'tooltip' => __( 'Use STARTTLS to connect to the SMTP server. Warning! Turning this off has Security implications!', 'simple-stack' ),
				'tab'     => 'wordpress-app-email-gateway',
			),
			array(
				'id'      => 'sstk_email_gateway_smtp_note',
				'type'    => 'textarea',
				'name'    => __( 'Brief Note', 'simple-stack' ),
				'tooltip' => __( 'Just a note in case you need a reminder about the details of this email gateway setup.', 'simple-stack' ),
				'tab'     => 'wordpress-app-email-gateway',
			),
		);

		return $fields;

	}

	/**
	 * Return array portion of field settings for use in the Cloudflare DNS section of the wc sites tab.
	 */
	public function cf_dns_fields() {

		$fields = array(
			array(
				'type' => 'heading',
				'name' => __( 'Automatic DNS via CloudFlare', 'simple-stack' ),
				'desc' => __( 'When a site is provisioned it can be automatically assigned a subdomain based on the domain specified below. If this domain is setup in cloudflare, we can automatically point the IP address to the newly created subdomain.', 'simple-stack' ),
				'tab'  => 'wordpress-app-dns-cloudflare',
			),
			array(
				'id'         => 'wordpress_app_dns_cf_temp_domain',
				'type'       => 'text',
				'name'       => __( 'Temporary Domain', 'simple-stack' ),
				'tooltip'    => __( 'The domain under which a new site\'s temporary sub-domain will be created.', 'simple-stack' ),
				'desc'       => __( 'This needs to be a short domain - max 19 chars.' ),
				'size'       => '20',
				'attributes' => array(
					'maxlength' => '19',
				),
				'tab'        => 'wordpress-app-dns-cloudflare',
			),
			array(
				'id'      => 'wordpress_app_dns_cf_enable',
				'type'    => 'checkbox',
				'name'    => __( 'Enable Cloudflare Auto DNS', 'simple-stack' ),
				'tooltip' => __( 'Turn this on so that when a new site is being created, the newly created subdomain can be automatically added to your CloudFlare configuration.', 'simple-stack' ),
				'tab'     => 'wordpress-app-dns-cloudflare',
			),
			array(
				'id'         => 'wordpress_app_dns_cf_zone_id',
				'type'       => 'text',
				'name'       => __( 'Zone ID', 'simple-stack' ),
				'desc'       => __( 'Your zone id can be found in the lower right of the CloudFlare overview page for your domain', 'simple-stack' ),
				'size'       => 35,
				'attributes' => array(
					'spellcheck' => 'false',
				),
				'tab'        => 'wordpress-app-dns-cloudflare',
			),
			array(
				'id'         => 'wordpress_app_dns_cf_token',
				'type'       => 'text',
				'name'       => __( 'API Security Token', 'simple-stack' ),
				'desc'       => __( 'Generate a new token for your zone by using the GET YOUR API TOKEN link located in the lower right of the CloudFlare overview page for your domain.  This should use the EDIT ZONE DNS api token template.', 'simple-stack' ),
				'size'       => 35,
				'attributes' => array(
					'spellcheck' => 'false',
				),
				'tab'        => 'wordpress-app-dns-cloudflare',
			),
			array(
				'id'      => 'wordpress_app_dns_cf_disable_proxy',
				'type'    => 'checkbox',
				'name'    => __( 'Disable Cloudflare Proxy', 'simple-stack' ),
				'tooltip' => __( 'All new subdomains added to CloudFlare will automatically be proxied (orange flag turned on.) Check this box to turn off this behavior.', 'simple-stack' ),
				'tab'     => 'wordpress-app-dns-cloudflare',
			),
			array(
				'id'      => 'wordpress_app_dns_cf_auto_delete',
				'type'    => 'checkbox',
				'name'    => __( 'Auto Delete DNS Entry', 'simple-stack' ),
				'tooltip' => __( 'Should we attempt to delete the DNS entry for the domain at cloudflare when a site is deleted?', 'simple-stack' ),
				'tab'     => 'wordpress-app-dns-cloudflare',
			),          // This one probably should be moved to it's own tab once we get more than on DNS provider.
			array(
				'id'      => 'wordpress_app_auto_issue_ssl',
				'type'    => 'checkbox',
				'name'    => __( 'Automatically Issue SSL', 'simple-stack' ),
				'tooltip' => __( 'If DNS was automatically updated after a new site is provisioned, attempt to get an SSL certificate from LETSENCRYPT?', 'simple-stack' ),
				'tab'     => 'wordpress-app-dns-cloudflare',
			),
			array(
				'id'      => 'wordpress_app_auto_add_aaaa',
				'type'    => 'checkbox',
				'name'    => __( 'Add AAAA Record for IPv6?', 'simple-stack' ),
				'tooltip' => __( 'Add an AAAA DNS entry if the server has an IPv6 address?', 'simple-stack' ),
				'tab'     => 'wordpress-app-dns-cloudflare',
			),
		);
		return $fields;
	}

	/**
	 * Return array portion of field settings for use in rest API tab.
	 */
	public function rest_api_fields() {

		$fields = array(
			array(
				'type' => 'heading',
				'name' => __( 'REST API [Beta]', 'simple-stack' ),
				'desc' => __( 'Activate the REST API', 'simple-stack' ),
				'tab'  => 'wordpress-app-rest-api',
			),
			array(
				'id'   => 'wordpress_app_rest_api_enable',
				'type' => 'checkbox',
				'name' => __( 'Enable the REST API', 'simple-stack' ),
				'tab'  => 'wordpress-app-rest-api',
			),
		);
		return $fields;
	}

	/**
	 * Return an array that combines all fields that will go in white label fields tab.
	 */
	public function white_label_fields() {

		$fields = array();

		// Header.
		$fields[] = array(
			'name' => __( 'Overview', 'simple-stack' ),
			'id'   => 'wordpress-app-white-label-basics-heading',
			'type' => 'heading',
			'std'  => '',
			'desc' => __( 'We have provided the most popular white label options here.  But, when you want to go further you still have the vast open sea of WP style hooks and filters as well as 3rd party plugins to help you completely customize your Simple Stack dashboard.', 'simple-stack' ),
			'tab'  => 'wordpress-app-white-label',
		);

		// Logo Header.
		$fields[] = array(
			'name' => __( 'Logo', 'simple-stack' ),
			'id'   => 'wordpress-app-logo-overides-heading',
			'type' => 'heading',
			'std'  => '',
			'desc' => __( 'Use your own logo on the server and site creation screen.  You can also completely remove the logo with an entry in wp-config - check out the docs on our website for more information about that option.', 'simple-stack' ),
			'tab'  => 'wordpress-app-white-label',
		);

		// Checkbox to not show logo.
		$fields[] = array(
			'name'    => 'Do Not Show Logo',
			'id'      => 'wordpress_app_noshow_logo',
			'type'    => 'checkbox',
			'tooltip' => __( 'Remove the Simple Stack logo from popups and other locations.', 'simple-stack' ),
			'tab'     => 'wordpress-app-white-label',
		);

		// Upload Logo.
		$fields[] = array(
			'name'             => 'Upload Logo',
			'id'               => 'wordpress_app_upload_logo',
			'type'             => 'image_advanced',
			'max_file_uploads' => 1,
			'max_status'       => false,
			'image_size'       => 'thumbnail',
			'max_file_size'    => '2mb',
			'tab'              => 'wordpress-app-white-label',
			'hidden'           => array( 'wordpress_app_noshow_logo', '=', '1' ),
		);

		// Brand Colors - wp-admin.
		$fields[] = array(
			'name' => __( 'Brand Colors- WP-Admin', 'simple-stack' ),
			'id'   => 'wordpress-app-brand-colors-heading',
			'type' => 'heading',
			'std'  => '',
			'desc' => __( 'These settings are used to manage your brand colors in the wp-admin area.', 'simple-stack' ),
			'tab'  => 'wordpress-app-white-label',
		);

		/**
		 * Overrides Brand Colors for wp-admin.
		 */
		// An array of ids and labels for color fields that overide brand colors.
		$brand_colors = array(
			'wordpress_app_primary_brand_color'            => array(
				'label' => __( 'Primary Brand Color', 'simple-stack' ),
				'desc'  => '',
				'std'   => SSTK_PRIMARY_BRAND_COLOR,
			),
			'wordpress_app_secondary_brand_color'          => array(
				'label' => __( 'Secondary Brand Color', 'simple-stack' ),
				'desc'  => '',
				'std'   => SSTK_SECONDARY_BRAND_COLOR,
			),
			'wordpress_app_tertiary_brand_color'           => array(
				'label' => __( 'Tertiary Brand Color', 'simple-stack' ),
				'desc'  => '',
				'std'   => SSTK_TERTIARY_BRAND_COLOR,
			),
			'wordpress_app_accent_background_color'        => array(
				'label' => __( 'Accent Background Color', 'simple-stack' ),
				'desc'  => '',
				'std'   => SSTK_ACCENT_BG_COLOR,
			),
			'wordpress_app_medium_accent_background_color' => array(
				'label' => __( 'Medium Accent Background Color', 'simple-stack' ),
				'desc'  => '',
				'std'   => SSTK_MEDIUM_ACCENT_BG_COLOR,
			),
			'wordpress_app_medium_background_color'        => array(
				'label' => __( 'Medium Background Color', 'simple-stack' ),
				'desc'  => '',
				'std'   => SSTK_MEDIUM_BG_COLOR,
			),
			'wordpress_app_light_background_color'         => array(
				'label' => __( 'Light Background Color', 'simple-stack' ),
				'desc'  => '',
				'std'   => SSTK_LIGHT_BG_COLOR,
			),
			'wordpress_app_alternate_accent_background_color' => array(
				'label' => __( 'Alternate Accent Background Color', 'simple-stack' ),
				'desc'  => '',
				'std'   => SSTK_ALTERNATE_ACCENT_BG_COLOR,
			),
			'wordpress_app_positive_color'                 => array(
				'label' => __( 'Positive Color', 'simple-stack' ),
				'desc'  => __( 'Accent color used to indicate something is turned on or a good thing has occurred.', 'simple-stack' ),
				'std'   => SSTK_POSITIVE_COLOR,
			),
			'wordpress_app_negative_color'                 => array(
				'label' => __( 'Negative Color', 'simple-stack' ),
				'desc'  => __( 'Accent color used to indicate something is turned off or a bad thing has occurred.', 'simple-stack' ),
				'std'   => SSTK_NEGATIVE_COLOR,
			),
			'wordpress_app_alt_negative_color'             => array(
				'label' => __( 'Alternative Negative Color', 'simple-stack' ),
				'desc'  => __( 'Alternative Accent color used to indicate something is turned off or a bad thing has occurred.', 'simple-stack' ),
				'std'   => SSTK_ALT_NEGATIVE_COLOR,
			),
			'wordpress_app_terminal_background_color'      => array(
				'label' => __( 'Background Color for Terminal', 'simple-stack' ),
				'desc'  => __( 'Background color for our terminal display.', 'simple-stack' ),
				'std'   => SSTK_TERMINAL_BG_COLOR,
			),
			'wordpress_app_terminal_foreground_color'      => array(
				'label' => __( 'Foreground Color for Terminal', 'simple-stack' ),
				'desc'  => __( 'Color of the text used in our terminal display.', 'simple-stack' ),
				'std'   => SSTK_TERMINAL_FG_COLOR,
			),
			'wordpress_app_white_color'                    => array(
				'label' => __( 'White', 'simple-stack' ),
				'desc'  => __( 'White', 'simple-stack' ),
				'std'   => SSTK_WHITE_COLOR,
			),
		);

		// Loop through the wp-admin brand colors array and generate settings fields.
		foreach ( $brand_colors as $brand_key => $brand_value ) {
			// First column is just the label with the tab name.
			$fields[] = array(
				'name'          => "{$brand_value['label']}",
				'id'            => "{$brand_key}",
				'type'          => 'color',
				'alpha_channel' => true,
				'tooltip'       => "{$brand_value['desc']}",
				'tab'           => 'wordpress-app-white-label',
				'std'           => "{$brand_value['std']}",
			);
		}

		// Brand Colors - front-end.
		$fields[] = array(
			'name' => __( 'Brand Colors - Front-End Lists', 'simple-stack' ),
			'id'   => 'wordpress-app-brand-colors-heading',
			'type' => 'heading',
			'std'  => '',
			'desc' => __( 'These settings are used to manage your brand colors shown on the front-end of your site.', 'simple-stack' ),
			'tab'  => 'wordpress-app-white-label',
		);

		/**
		 * Overrides Brand Colors the front-end.
		 */
		// An array of ids and labels for color fields that overide brand colors.
		$brand_colors = array(
			'wordpress_app_fe_primary_brand_color'     => array(
				'label' => __( 'Primary Brand Color', 'simple-stack' ),
				'desc'  => __( 'Used for the background hover color on most buttons as well as color of larger text.', 'simple-stack' ),
				'std'   => SSTK_FE_PRIMARY_BRAND_COLOR,
			),
			'wordpress_app_fe_secondary_brand_color'   => array(
				'label' => __( 'Secondary Brand Color', 'simple-stack' ),
				'desc'  => '',
				'std'   => SSTK_FE_SECONDARY_BRAND_COLOR,
			),
			'wordpress_app_fe_tertiary_brand_color'    => array(
				'label' => __( 'Tertiary Brand Color', 'simple-stack' ),
				'desc'  => '',
				'std'   => SSTK_FE_TERTIARY_BRAND_COLOR,
			),
			'wordpress_app_fe_accent_background_color' => array(
				'label' => __( 'Accent Background Color', 'simple-stack' ),
				'desc'  => __( 'Used for the background color on most buttons.', 'simple-stack' ),
				'std'   => SSTK_FE_ACCENT_BG_COLOR,
			),
			'wordpress_app_fe_medium_accent_background_color' => array(
				'label' => __( 'Medium Accent Background Color', 'simple-stack' ),
				'desc'  => '',
				'std'   => SSTK_FE_MEDIUM_ACCENT_BG_COLOR,
			),
			'wordpress_app_fe_medium_background_color' => array(
				'label' => __( 'Medium Background Color', 'simple-stack' ),
				'desc'  => '',
				'std'   => SSTK_FE_MEDIUM_BG_COLOR,
			),
			'wordpress_app_fe_light_background_color'  => array(
				'label' => __( 'Light Background Color', 'simple-stack' ),
				'desc'  => __( 'Used for the background color on cards in the server and app list.', 'simple-stack' ),
				'std'   => SSTK_FE_LIGHT_BG_COLOR,
			),
			'wordpress_app_fe_alternate_accent_background_color' => array(
				'label' => __( 'Alternate Accent Background Color', 'simple-stack' ),
				'desc'  => '',
				'std'   => SSTK_FE_ALTERNATE_ACCENT_BG_COLOR,
			),
			'wordpress_app_fe_positive_color'          => array(
				'label' => __( 'Positive Color', 'simple-stack' ),
				'desc'  => __( 'Accent color used to indicate something is turned on or a good thing has occurred.', 'simple-stack' ),
				'std'   => SSTK_FE_POSITIVE_COLOR,
			),
			'wordpress_app_fe_negative_color'          => array(
				'label' => __( 'Negative Color', 'simple-stack' ),
				'desc'  => __( 'Accent color used to indicate something is turned off or a bad thing has occurred.', 'simple-stack' ),
				'std'   => SSTK_FE_NEGATIVE_COLOR,
			),
			'wordpress_app_fe_alt_negative_color'      => array(
				'label' => __( 'Alt Negative Color', 'simple-stack' ),
				'desc'  => __( 'Alternative Accent color used to indicate something is turned off or a bad thing has occurred.', 'simple-stack' ),
				'std'   => SSTK_FE_ALT_NEGATIVE_COLOR,
			),
			'wordpress_app_fe_white_color'             => array(
				'label' => __( 'White', 'simple-stack' ),
				'desc'  => __( 'Wite.', 'simple-stack' ),
				'std'   => SSTK_FE_WHITE_COLOR,
			),
		);

		// Loop through the front-end brand colors array and generate settings fields.
		foreach ( $brand_colors as $brand_key => $brand_value ) {
			// First column is just the label with the tab name.
			$fields[] = array(
				'name'          => "{$brand_value['label']}",
				'id'            => "{$brand_key}",
				'type'          => 'color',
				'alpha_channel' => true,
				'tooltip'       => "{$brand_value['desc']}",
				'tab'           => 'wordpress-app-white-label',
				'std'           => "{$brand_value['std']}",
			);
		}

		// RESET DEFAULT BRAND COLORS.
		$fields[] = array(
			'name'       => '',
			'type'       => 'button',
			'std'        => __( 'Reset Defaults', 'simple-stack' ),
			'attributes' => array(
				'id'               => 'wordpress_app_reset_brand_colors',
				'data-action'      => 'sstk_reset_defaults_brand_colors',
				'data-nonce'       => wp_create_nonce( 'sstk-reset-brand-colors' ),
				'data-loading_msg' => __( 'Please wait...', 'simple-stack' ),
				'data-confirm'     => __( 'Are you sure you would like to reset the brand colors with defaults?  This will reset both the front-end and back-end colors to the Simple Stack defaults.', 'simple-stack' ),
			),
			'tab'        => 'wordpress-app-white-label',
		);

		// DISABLE FONTAWESOME.
		$fields[] = array(
			'name' => __( 'Front-end Icons', 'simple-stack' ),
			'id'   => 'wordpress-app-front-end-icons-heading',
			'type' => 'heading',
			'std'  => '',
			'tab'  => 'wordpress-app-white-label',
		);
		$fields[] = array(
			'name'    => 'Disable Tab Icons on the Front-end',
			'id'      => 'wordpress_app_disable_front_end_icons',
			'type'    => 'checkbox',
			'tooltip' => __( 'This will prevent the Fontawesome icon files from loading. The css markup will still be present in the html but without the fontawesome css file the markup will not do anything.  You can use the same markup to apply your own icons if you prefer as long as no other plugin or theme loads the Fontawesome files.', 'simple-stack' ),
			'tab'     => 'wordpress-app-white-label',
		);

		/**
		 * Documentation Link Overrides Fields
		 */
		// An array of ids and labels for text fields that overide documentation links.
		$doc_links = array(
			'wordpress-app-doc-link-theme-plugin-updates' => array(
				'label' => __( 'Site Updates', 'simple-stack' ),
				'desc'  => __( 'The documentation link in the SITE UPDATES tab.', 'simple-stack' ),
			),
			'wordpress-app-doc-change-domain'             => array(
				'label' => __( 'Change Domains', 'simple-stack' ),
				'desc'  => __( 'The documentation link in the CHANGE DOMAIN tab.', 'simple-stack' ),
			),
			'wordpress-app-doc-link-page-cache'           => array(
				'label' => __( 'Cache Information', 'simple-stack' ),
				'desc'  => __( 'The documentation link for more information about caching on the CACHE tab.', 'simple-stack' ),
			),
			'wordpress-app-doc-link-memcached-info'       => array(
				'label' => __( 'Memcached Information', 'simple-stack' ),
				'desc'  => __( 'The documentation link for more information about Memcached on the CACHE tab.', 'simple-stack' ),
			),
		);

		// Documentation Overrides Header.
		$fields[] = array(
			'name' => __( 'Documentation Link Overrides', 'simple-stack' ),
			'id'   => 'wordpress-app-doc-link-overides-heading',
			'type' => 'heading',
			'std'  => '',
			'desc' => __( 'Point users to your own documentation instead of the standard Simple Stack documentation.', 'simple-stack' ),
			'tab'  => 'wordpress-app-white-label',
		);

		// Loop through the doc links array and generate settings fields.
		foreach ( $doc_links as $doc_key => $doc_link ) {
			// First column is just the label with the tab name.
			$fields[] = array(
				'name' => "{$doc_link['label']}",
				'id'   => "{$doc_key}",
				'type' => 'url',
				'desc' => "{$doc_link['desc']}",
				'tab'  => 'wordpress-app-white-label',
			);
		}

		// Custom CSS.
		$fields[] = array(
			'name' => __( 'Custom CSS', 'simple-stack' ),
			'id'   => 'wordpress-app-custom-css-override-header',
			'type' => 'heading',
			'desc' => 'Custom CSS is loaded in-line on every Simple Stack page on both the front-end and back-end.',
			'tab'  => 'wordpress-app-white-label',
		);
		$fields[] = array(
			'name' => __( 'Custom CSS', 'simple-stack' ),
			'id'   => 'wordpress-app-custom-css-override',
			'type' => 'textarea',
			'desc' => '',
			'rows' => 10,
			'tab'  => 'wordpress-app-white-label',
		);

		return $fields;

	}

	/**
	 * Return an array of fields for the custom script fields subtab in the APP:WordPress SETTINGS tab.
	 */
	public function custom_script_fields() {

		$fields = array();

		// The list of scripts we'll be handling.
		$scripts = array(
			'after_server_create' => array(
				'label'   => __( 'After Server Provisioning', 'simple-stack' ),
				'tooltip' => __( 'The custom script you would like to run AFTER we successfully complete installing our core stack.', 'simple-stack' ),
			),
			'after_site_create'   => array(
				'label'   => __( 'After Site Provisioning', 'simple-stack' ),
				'tooltip' => __( 'The custom script to run after a new WordPress site is installed and the core provisioning steps have completed.', 'simple-stack' ),
			),
		);

		// Let developers hook into the array here.
		$scripts = apply_filters( 'sstk_wordpress-app_custom_script_fields_list', $scripts );

		// Field id prefix.
		$sstk_id_prefix = 'sstk_wpapp_custom_script';

		// Subtab that the fields will display on.
		$subtab = 'wordpress-app-custom-scripts';

		// Heading.
		$fields[] = array(
			'name' => __( 'Custom Bash Scripts', 'simple-stack' ),
			'id'   => $sstk_id_prefix . '_heading',
			'type' => 'heading',
			'std'  => '',
			'desc' => __( 'Setup the location of BASH custom scripts that are run after certain processes are complete. Using these scripts are easier that writing custom add-ons - but only if you have no need to update data/metas on the Simple Stack site.', 'simple-stack' ),
			'tab'  => $subtab,
		);

		// Loop through the scripts array and render TEXT fields to collect the names of scripts.
		// We'd love to do two columns here like we do on the owner security tab.
		// Unfortunately METABOX.io messes things up on the other subtabs when we try.
		foreach ( $scripts as $script_key => $script_data ) {

			// Pull some data out of the $script_data var.
			$script_label   = $script_data['label'];
			$script_tooltip = $script_data['tooltip'];

			$fields[] = array(
				'name'    => "{$script_label}",
				'id'      => "{$sstk_id_prefix}_{$script_key}",
				'type'    => 'url',
				'tab'     => $subtab,
				'tooltip' => $script_tooltip,
			);

		}

		// Secrets Manager API Key.
		$secrets_mgr_desc  = __( 'You can enter an api key for a secrets manager here.  It will then be available in your environment for use within your custom scripts.  An example of a secrets manager is doppler.com but you can use any manager that only uses a singlet token.', 'simple-stack' );
		$secrets_mgr_desc .= '<br />';
		$secrets_mgr_desc .= __( 'With a secrets manager you can safely pull in other api keys such as private GitHub keys needed to access private resources.', 'simple-stack' );

		$fields[] = array(
			'name' => __( 'Secrets Manager API Key', 'simple-stack' ),
			'id'   => $sstk_id_prefix . 'secrets_manager_heading',
			'type' => 'heading',
			'std'  => '',
			'desc' => $secrets_mgr_desc,
			'tab'  => $subtab,
		);
		$fields[] = array(
			'name'       => __( 'Secrets Manager API Key', 'simple-stack' ),
			'id'         => "{$sstk_id_prefix}_secrets_manager_api_key",
			'type'       => 'text',
			'attributes' => array(
				'spellcheck' => 'false',
			),
			'tab'        => $subtab,
		);

		return $fields;

	}

	/**
	 * Return an array of fields for the DEVELOPER OPTIONS subtab in the APP:WordPress SETTINGS tab.
	 */
	public function developer_fields() {

		$fields = array(
			array(
				'type' => 'heading',
				'name' => __( 'New Site Defaults', 'simple-stack' ),
				'desc' => __( 'Defaults to be used on the INSTALL WORDPRESS popup.', 'simple-stack' ),
				'tab'  => 'wordpress-app-developers',
			),
			array(
				'id'      => 'wordpress_app_default_wp_email',
				'type'    => 'text',
				'size'    => '60',
				'name'    => __( 'Default Admin Email New Sites.', 'simple-stack' ),
				'tooltip' => __( 'The default email address when an admin uses the INSTALL WORDPRESS button.', 'simple-stack' ),
				'tab'     => 'wordpress-app-developers',
			),
			array(
				'id'      => 'wordpress_app_default_wp_user_id',
				'type'    => 'text',
				'size'    => '60',
				'name'    => __( 'Default User Id For New Sites.', 'simple-stack' ),
				'tooltip' => __( 'The default user id when an admin uses the INSTALL WORDPRESS button.', 'simple-stack' ),
				'tab'     => 'wordpress-app-developers',
				'class'   => 'sstk_settings_pass_toggle',
			),
			array(
				'id'      => 'wordpress_app_default_wp_password',
				'type'    => 'text',
				'size'    => '60',
				'name'    => __( 'Default Password For New Sites.', 'simple-stack' ),
				'tooltip' => __( 'The default password when an admin uses the INSTALL WORDPRESS button.', 'simple-stack' ),
				'tab'     => 'wordpress-app-developers',
				'class'   => 'sstk_settings_pass_toggle',
			),
			array(
				'id'      => 'wordpress_app_auto_gen_password',
				'type'    => 'checkbox',
				'name'    => __( 'Auto-generate Password.', 'simple-stack' ),
				'tooltip' => __( 'If checked, a random password will be generated when an admin uses the INSTALL WORDPRESS button. Checking this box overrides the default password option above.', 'simple-stack' ),
				'tab'     => 'wordpress-app-developers',
			),
			array(
				'id'         => 'wordpress_app_sites_default_site_package',
				'type'       => 'post',
				'name'       => __( 'Default Site Package', 'simple-stack' ),
				'tooltip'    => __( 'Automatically select this site package on the INSTALL WORDPRESS pop-up.  It does NOT apply to other WordPress installation vectors such as the RESTAPI, BULK INSTALLS etc.', 'simple-stack' ),
				'post_type'  => 'sstk_site_package',
				'query_args' => array(
					'posts_per_page' => - 1,
				),
				'field_type' => 'select_advanced',
				'tab'        => 'wordpress-app-developers',
				'size'       => 60,
			),
		);

		return $fields;

	}

	/**
	 * Encrypt data before it is saved in the database
	 *
	 * Filter Hook: rwmb_wordpress_app_default_wp_password_value
	 *
	 * @param string $new new value being saved.
	 * @param string $field name of being field saved.
	 * @param string $old old value of the field.
	 *
	 * @return string $new the encrypted value of the field.
	 */
	public function encrypt( $new, $field, $old ) {
		return SSTK()->encrypt( $new );
	}

	/**
	 * Decrypt data before it is shown on the screen
	 *
	 * Filter Hook: rwmb_wordpress_app_default_wp_password_field_meta
	 *
	 * @param string $meta the value in the field being decrypted.
	 * @param string $field the name of the field.
	 * @param string $saved the original saved value of the field.
	 *
	 * @return string $meta the decrypted value of the field.
	 */
	public function decrypt( $meta, $field, $saved ) {
		return SSTK()->decrypt( $meta );
	}

	/**
	 * Encrypt the SMTP password while preserving the stored value on a blank submission.
	 *
	 * Filter Hook: rwmb_sstk_email_gateway_smtp_password_value
	 *
	 * @param string $new   New value being saved.
	 * @param string $field Name of the field being saved.
	 * @param string $old   Previously stored value.
	 *
	 * @return string
	 */
	public function encrypt_smtp_password( $new, $field, $old ) {
		if ( '' === trim( (string) $new ) ) {
			return $old;
		}

		return SSTK()->encrypt( $new );
	}

	/**
	 * Decrypt the SMTP password for the administrator settings field.
	 *
	 * Filter Hook: rwmb_sstk_email_gateway_smtp_password_field_meta
	 *
	 * @param string $meta   Value in the field being decrypted.
	 * @param string $field  Name of the field.
	 * @param string $saved  Original saved value of the field.
	 *
	 * @return string
	 */
	public function decrypt_smtp_password( $meta, $field, $saved ) {
		return SSTK()->decrypt( $meta );
	}

}
