<?php
/** Modified for Simple Stack by One Dog Solutions on 2026-11-23. */
/**
 * This class handles settings for the main
 * plugin and includes hooks for
 * allowing apps to create their own
 * settings.
 *
 * This core class writes all options
 * to 'sstk_settings' using metabox.io
 * which in turn just calls the
 * WordPress options api.
 *
 * Add-ons/apps can create their own
 * options page and setting variable
 * but we don't recommend that unless
 * absolutely necessary.
 *
 * @package simple-stack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Setup settings screen
 *
 * @package simple-stack
 * @version 1.0.0 / sstk
 * @since 1.0.0
 */
class SSTK_Settings {

	/**
	 * Return instance of self.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * SSTK_Settings constructor.
	 */
	public function __construct() {

		// Action hook to clear provider cache.
		add_action( 'wp_ajax_sstk_provider_clear_cache', array( $this, 'sstk_provider_clear_cache' ) );

		// Action hook to automatically create ssh keys.
		add_action( 'wp_ajax_sstk_provider_auto_create_ssh_key', array( $this, 'sstk_provider_auto_create_ssh_key' ) );

		// Action hook to automatically create ssh keys.
		add_action( 'wp_ajax_sstk_provider_auto_create_ssh_key', array( $this, 'sstk_provider_auto_create_ssh_key' ) );

		// Action hook to test provider connection.
		add_action( 'wp_ajax_sstk_provider_test_provider_connection', array( $this, 'sstk_provider_test_provider_connection' ) );

		// Action hook to reset defaults brand colors.
		add_action( 'wp_ajax_sstk_reset_defaults_brand_colors', array( $this, 'sstk_reset_defaults_brand_colors' ) );

		$this->display_settings();

	}


	/**
	 * Main function for creating the settings page.
	 */
	public function display_settings() {

		// Load up css and js scripts used for this screen.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10, 1 );

		// Register an options page.
		add_filter( 'mb_settings_pages', array( &$this, 'register_options_page' ) );

		// Register meta boxes and fields for settings page.
		add_filter(
			'rwmb_meta_boxes',
			function ( $meta_boxes ) {

				// Set some long text used for the timeout description.
				$ssh_timeout_desc  = __( 'If you are experiencing unusual behavior when running commands, such as server deployment hanging or not completing, it might be due to one or more timeout settings on the WordPress server where this plugin is running.', 'simple-stack' );
				$ssh_timeout_desc .= '<br />';
				$ssh_timeout_desc .= '<br />';
				$ssh_timeout_desc .= __( 'PHP scripts have a timeout associated with them that is set in your PHP.INI file.  Your webserver has a timeout too. The length of time that an SSH command has to run is limited by these two factors.', 'simple-stack' );
				$ssh_timeout_desc .= '<br />';
				$ssh_timeout_desc .= __( 'The load on your target WordPress server and type of command being executed are the main factors in determining how long it will take a command to run. You want to give it as long as possible before timing out.', 'simple-stack' );
				$ssh_timeout_desc .= '<br />';
				$ssh_timeout_desc .= '<br />';
				$ssh_timeout_desc .= __( 'We therefore recommend that you increase the timeouts used on this server to 180 seconds or more. ', 'simple-stack' );
				$ssh_timeout_desc .= '<br />';
				if ( (int) ini_get( 'max_execution_time' ) >= 180 ) {
					/* translators: %s: The max excution time limit for php. */
					$ssh_timeout_desc = sprintf( __( 'It looks like your <b>php_max_execution_time</b> in your php.ini file is already set to 180 seconds or more. It is currently set to:  %s.  So, no change to this is necessary at this time.', 'simple-stack' ), (string) ini_get( 'max_execution_time' ) );
				} else {
					$ssh_timeout_desc .= '<br />';
					/* translators: %s: The max excution time limit for php. */
					$ssh_timeout_desc .= sprintf( __( 'Set your <b>php_max_execution_time</b> in your php.ini file to 180 seconds or more. It is currently set to only: %s', 'simple-stack' ), (string) ini_get( 'max_execution_time' ) );
				}

				$webserver_timeout_desc  = __( 'Webservers also have timeouts. Unfortunately we are unable to read their current values, so please check the OpenLiteSpeed configuration and change them as necessary.', 'simple-stack' );
				$webserver_timeout_desc .= __( '<br />When you are done you can change the SSH timeout in the field below.', 'simple-stack' );
				$webserver_timeout_desc .= '<br />';
				$webserver_timeout_desc .= __( '<br /><b>FOR OPENLITESPEED:</b>', 'simple-stack' );
				$webserver_timeout_desc .= __( '<br />&nbsp;&nbsp;&nbsp;&nbsp;Set the <b>connTimeout</b> value in the <b>tuning</b> block of <b>/usr/local/lsws/conf/httpd_config.conf</b> to 600 seconds or more.', 'simple-stack' );
				$webserver_timeout_desc .= '<br />';
				$webserver_timeout_desc .= __( '<br />After these settings are complete, you can set the SSH TIMEOUT value below to something higher such as 300.', 'simple-stack' );

				// Set some long text used for the introduction and welcome message.
				$ssh_welcome_text = '';
				if ( ! defined( 'SSTK_SKIP_WELCOME_MESSAGE' ) || ( defined( 'SSTK_SKIP_WELCOME_MESSAGE' ) && ! SSTK_SKIP_WELCOME_MESSAGE ) ) {
					$ssh_welcome_text  = __( 'Hello - thanks for choosing Simple Stack.  We make it easy for you to deploy new servers and websites from right here inside your WordPress Admin screen.', 'simple-stack' );
					$ssh_welcome_text .= '<br />';
					/* translators: %s: The link to the SSTK website. */
					$ssh_welcome_text .= sprintf( __( 'Learn more on our site at <a href="%s">onedogsolutions.com</a>.', 'simple-stack' ), 'https://github.com/onedogsolutions/simple-stack' );
					$ssh_welcome_text  = apply_filters( 'sstk_settings_welcome_text_initial', $ssh_welcome_text );
				}

				// Allow apps to append their own welcome message.
				$ssh_welcome_text = apply_filters( 'sstk_settings_welcome_message', $ssh_welcome_text );

				$meta_boxes[] = array(
					'id'             => 'general',
					'title'          => __( 'General', 'simple-stack' ),
					'settings_pages' => 'sstk_settings',
					'tab'            => 'general',
					'fields'         => array(
						array(
							'name' => __( 'Welcome Message', 'simple-stack' ),
							'id'   => 'sstk_general',
							'type' => 'heading',
							'desc' => $ssh_welcome_text,
						),
					),
				);

				// Allow apps to add  their own sections just after the welcome message.
				$meta_boxes = apply_filters( 'sstk_general_settings_after_welcome_message', $meta_boxes );

				// Show timeout and other instructions - if not disabled in wp-config.php.
				if ( ! defined( 'SSTK_SKIP_SSH_TIMEOUT_SETTINGS' ) || ( defined( 'SSTK_SKIP_SSH_TIMEOUT_SETTINGS' ) && ! SSTK_SKIP_SSH_TIMEOUT_SETTINGS ) ) {

					$meta_boxes[] = array(
						'id'             => 'ssh-and-webserver-timeouts',
						'title'          => __( 'SSH And Webserver Timeouts', 'simple-stack' ),
						'settings_pages' => 'sstk_settings',
						'tab'            => 'general',
						'fields'         => array(
							array(
								'type' => 'heading',
								'name' => 'SSH and Webserver Timeouts',
								'desc' => $ssh_timeout_desc,
							),

							array(
								'type' => 'heading',
								'name' => 'WebServer Timeouts',
								'desc' => $webserver_timeout_desc,
							),

							array(
								'type' => 'divider',
							),

							array(
								'name'        => __( 'SSH Timeout', 'simple-stack' ),
								'id'          => 'ssh_timeout',
								'type'        => 'number',
								'min'         => 30,
								'std'         => 30,
								'placeholder' => 30,
								'size'        => 30,
								'desc'        => __( 'The default value is 30 seconds which is the default value set when PHP is installed.', 'simple-stack' ),
							),
						),
					);

				}

				$logging_desc  = __( 'You can log various error types to the database. Be careful because if you turn on all items below, you will end up with thousands of entries in your database.', 'simple-stack' );
				$logging_desc .= '<br />' . __( 'To prevent this, we set the log limit to 100 records but you can adjust this number as well.', 'simple-stack' );
				$logging_desc .= '<br /><b>' . __( 'DO NOT TURN THIS ON UNLESS OUR SUPPORT TEAM TELLS YOU TO DO SO!  We know you want to do it but please do not do so without our support guidance!', 'simple-stack' ) . '</b>';
				$logging_desc .= '<br />' . __( 'If you do decide to turn it on without our support team\'s guidance, please do not then open a support ticket for anything you find in the logs. Why? Because lots of stuff that is normal will look scary.', 'simple-stack' );
				$meta_boxes[]  = array(
					'id'             => 'logging',
					'title'          => __( 'Logging and Tracing', 'simple-stack' ),
					'settings_pages' => 'sstk_settings',
					'tab'            => 'logging',
					'fields'         => array(
						array(
							'type' => 'heading',
							'name' => 'Logging and Tracing',
							'desc' => $logging_desc,
						),

						array(
							'name'            => __( 'Log these types', 'simple-stack' ),
							'id'              => 'logging_and_tracing_types',
							'type'            => 'checkbox_list',
							'options'         => array(
								'error'          => __( 'Error', 'simple-stack' ),
								'trace'          => __( 'Trace', 'simple-stack' ),
								'warning'        => __( 'Warnings', 'simple-stack' ),
								'debug'          => __( 'Debug', 'simple-stack' ),
								'security'       => __( 'Security', 'simple-stack' ),
								'provider-cache' => __( 'Provider Cache', 'simple-stack' ),
								'better_cron'    => __( 'Better Core Crons', 'simple-stack' ),
								'other'          => __( 'Other', 'simple-stack' ),
							),
							'select_all_none' => true,
						),
						array(
							'name'    => __( 'Only include messages with the following text', 'simple-stack' ),
							'id'      => 'include_msg_txt',
							'type'    => 'fieldset_text',
							'options' => array(
								'include_msg' => __( 'Include Text', 'simple-stack' ),
							),
							'clone'   => true,
						),
						array(
							'name'    => __( 'Only include messages from the following files', 'simple-stack' ),
							'id'      => 'include_file_txt',
							'type'    => 'fieldset_text',
							'options' => array(
								'include_files' => __( 'Include Files', 'simple-stack' ),
							),
							'clone'   => true,
						),
						array(
							'name'    => __( 'Always exclude messages with the following text', 'simple-stack' ),
							'id'      => 'exclude_msg_txt',
							'type'    => 'fieldset_text',
							'options' => array(
								'exclude_msg' => __( 'Exclude Text', 'simple-stack' ),
							),
							'clone'   => true,
						),

						array(
							'type' => 'heading',
							'name' => 'Log Limits',
							'desc' => __( 'Limit the number of entries in logs. If set to zero or nothing is entered, we are going to leave 999 entries at all times and delete everything else. Note that some of these logs are used to calculate statistics for certain screens in the Power Tools add-on. If you use those screens, you will want to keep the limits here on the higher side.', 'simple-stack' ),
						),
						array(
							'name' => __( 'Auto Trim Notification Log', 'simple-stack' ),
							'id'   => 'auto_trim_notification_log_limit',
							'type' => 'number',
							'size' => 10,
						),
						array(
							'name' => __( 'Auto Trim Notification Sent Log', 'simple-stack' ),
							'id'   => 'auto_trim_notification_sent_log_limit',
							'type' => 'number',
							'size' => 10,
						),
						array(
							'name' => __( 'Auto Trim SSH Log', 'simple-stack' ),
							'id'   => 'auto_trim_ssh_log_limit',
							'type' => 'number',
							'size' => 10,
						),
						array(
							'name' => __( 'Auto Trim Command Log', 'simple-stack' ),
							'id'   => 'auto_trim_command_log_limit',
							'type' => 'number',
							'size' => 10,
						),
						array(
							'name' => __( 'Auto Trim Pending Tasks', 'simple-stack' ),
							'id'   => 'auto_trim_pending_log_limit',
							'type' => 'number',
							'size' => 10,
						),
						array(
							'name' => __( 'Auto Trim Error Log', 'simple-stack' ),
							'id'   => 'auto_trim_error_log_limit',
							'type' => 'number',
							'size' => 10,
						),
						array(
							'name' => __( 'Delete At Most', 'simple-stack' ),
							'id'   => 'most_items_to_delete',
							'type' => 'number',
							'desc' => __( 'To prevent timeouts we limit the number of records we will delete in any one shot.  If set to zero, we will delete 100 records at a time.', 'simple-stack' ),
						),

						array(
							'type' => 'heading',
							'name' => 'Debug.log',
							'desc' => __( 'Errors and warnings will always be logged to the debug.log file.  But you can also choose to log the following types.  Be careful though because turning these items on can write sensitive information to the debug.log file!' ),
						),
						array(
							'name'            => __( 'Log these types to debug.log', 'simple-stack' ),
							'id'              => 'logging_and_tracing_types_debug_log',
							'type'            => 'checkbox_list',
							'options'         => array(
								'trace'          => __( 'Trace', 'simple-stack' ),
								'debug'          => __( 'Debug', 'simple-stack' ),
								'security'       => __( 'Security', 'simple-stack' ),
								'provider-cache' => __( 'Provider Cache', 'simple-stack' ),
								'other'          => __( 'Other', 'simple-stack' ),
							),
							'select_all_none' => true,
						),

					),
				);

				$meta_boxes[] = array(
					'id'             => 'provider-cache-settings',
					'title'          => __( 'Cache settings for providers', 'simple-stack' ),
					'settings_pages' => 'sstk_settings',
					'tab'            => 'misc',
					'fields'         => $this->get_provider_cache_limit_fields(),
				);

				$meta_boxes[] = array(
					'id'             => 'show-advanced-metaboxes',
					'title'          => __( 'Show Advanced Details Metaboxes', 'simple-stack' ),
					'settings_pages' => 'sstk_settings',
					'tab'            => 'misc',
					'fields'         => array(
						array(
							'type' => 'heading',
							'name' => 'Show Advanced Metaboxes',
							'desc' => __( 'The server and site detail screens can show some advanced metaboxes with gory details about the data we store internally. Turn this on when advised by support.', 'simple-stack' ),
						),

						array(
							'name' => __( 'Show Advanced Metaboxes', 'simple-stack' ),
							'id'   => 'show-advanced-metaboxes',
							'type' => 'checkbox',
						),
					),
				);

				$meta_boxes[] = array(
					'id'             => 'hide-sstk-warnings',
					'title'          => __( 'Hide Warnings', 'simple-stack' ),
					'settings_pages' => 'sstk_settings',
					'tab'            => 'misc',
					'fields'         => array(
						array(
							'type' => 'heading',
							'name' => 'Hide Warnings',
							'desc' => __( 'Turn off certain warnings at the top of the admin screen.', 'simple-stack' ),
						),

						array(
							'name' => __( 'Hide The Local Host Warning', 'simple-stack' ),
							'id'   => 'hide-local-host-warning',
							'type' => 'checkbox',
							'desc' => __( 'You cannot run Simple Stack on a local host or a server that is not accessible from the public internet. We usually show a non-dismissible warning about this.  Sometimes, though, this is a false error so you might want to hide the message if you think you know better.', 'simple-stack' ),
						),
					),
				);


				$meta_boxes[] = array(
					'id'             => 'long-command-timeout-mb',
					'title'          => __( 'Timeout for Long Running Commands', 'simple-stack' ),
					'settings_pages' => 'sstk_settings',
					'tab'            => 'misc',
					'fields'         => array(
						array(
							'type' => 'heading',
							'name' => 'Timeout for Long Running Commands',
							'desc' => __( 'When running commands, they are terminated after 25 minutes if not complete. This option allows you to increase that timeout. It is useful when provisioning very slow VMs at certain providers.', 'simple-stack' ),
						),

						array(
							'name'        => __( 'Timeout', 'simple-stack' ),
							'id'          => 'long-command-timeout',
							'type'        => 'number',
							'min'         => 15,
							'max'         => 120,
							'std'         => 25,
							'placeholder' => 25,
							'size'        => 10,
						),
					),
				);

				$meta_boxes[] = array(
					'id'             => 'backup',
					'title'          => __( 'Backup Settings', 'simple-stack' ),
					'settings_pages' => 'sstk_settings',
					'tab'            => 'misc',
					'fields'         => array(
						array(
							'type' => 'heading',
							'name' => 'Backup your settings',
							'desc' => __( 'Use the items below to backup your settings offline.', 'simple-stack' ),
						),

						array(
							'name'       => __( 'Backup', 'simple-stack' ),
							'id'         => 'backup-settings',
							'type'       => 'backup',
							'attributes' => array(
								'spellcheck' => 'false',
							),
						),
					),
				);

				$meta_boxes[] = array(
					'id'             => 'hide-general-option',
					'title'          => __( 'Hide General Tab', 'simple-stack' ),
					'settings_pages' => 'sstk_settings',
					'tab'            => 'misc',
					'fields'         => array(
						array(
							'type' => 'heading',
							'name' => 'Hide the general tab',
							'desc' => __( 'After you\'ve used the data on the GENERAL tab a few times you might not need it anymore.  Use this option to hide it.', 'simple-stack' ),
						),

						array(
							'name' => __( 'Hide The General Tab', 'simple-stack' ),
							'id'   => 'hide-general-settings-tab',
							'type' => 'checkbox',
							'desc' => __( 'To hide the GENERAL SETTINGS tab, check this box & click the SAVE button below. Then, refresh the screen for this setting to take effect!', 'simple-stack' ),
						),
					),
				);

				$meta_boxes[] = array(
					'id'             => 'sstk-hide-groups-fields',
					'title'          => __( 'Hide Server/App Group Metabox From Non-Admins', 'simple-stack' ),
					'settings_pages' => 'sstk_settings',
					'tab'            => 'misc',
					'fields'         => array(
						array(
							'type' => 'heading',
							'name' => __( 'Hide Server/App Group Metabox From Non-Admins', 'simple-stack' ),
							'desc' => __( 'Hide the server and app group metaboxes from non-admins.', 'simple-stack' ),
						),
						array(
							'name' => __( 'Hide Server Group Metabox From Non-Admins', 'simple-stack' ),
							'id'   => 'sstk_hide_server_group_mb',
							'type' => 'checkbox',
						),
						array(
							'name' => __( 'Hide App Group Metabox From Non-Admins', 'simple-stack' ),
							'id'   => 'sstk_hide_site_group_mb',
							'type' => 'checkbox',
						),
					),
				);

				$meta_boxes[] = array(
					'id'             => 'sstk-email-warning-fields',
					'title'          => __( 'Warning Emails', 'simple-stack' ),
					'settings_pages' => 'sstk_settings',
					'tab'            => 'misc',
					'fields'         => array(
						array(
							'type' => 'heading',
							'name' => __( 'Warning Emails', 'simple-stack' ),
							'desc' => __( 'Control emails sent to Admin when certain actions fail to run', 'simple-stack' ),
						),
						array(
							'name'    => __( 'Do Not Send Cron Warning Emails?', 'simple-stack' ),
							'id'      => 'sstk_do_not_send_cron_warning_emails',
							'type'    => 'checkbox',
							'tooltip' => __( 'Emails are sent when we detect that certain critical processes are not running as scheduled. This flag disables those emails. It is useful to disable these messages if the emails are constantly being sent but are false postitives.', 'simple-stack' ),
						),
						array(
							'name'    => __( 'Do Not Send Pending Log Warning Emails?', 'simple-stack' ),
							'id'      => 'sstk_do_not_send_pending_log_warning_emails',
							'type'    => 'checkbox',
							'tooltip' => __( 'Emails are sent when we detect that there are pending tasks that have started but not completed in a reasonable time. This option disables those emails.', 'simple-stack' ),
						),
					),
				);

				/**
				 * Allow others to add their own settings at the end of the MISC tab.
				 */
				$meta_boxes = apply_filters( 'sstk_settings_after_misc_tab', $meta_boxes );

				$meta_boxes[] = array(
					'id'             => 'cloud-providers',
					'title'          => __( 'All Cloud Providers', 'simple-stack' ),
					'settings_pages' => 'sstk_settings',
					'tab'            => 'cloud-provider',  // this is the top level tab on the setttings screen, not to be confused with the tabs inside a metabox as we're defining below!
					// List of tabs in the metabox, in one of the following formats:
					// 1) key => label
					// 2) key => array( 'label' => Tab label, 'icon' => Tab icon ).
					'tabs'           => $this->provider_metabox_tabs(),
					'tab_style'      => 'left',
					'tab_wrapper'    => true,
					'fields'         => $this->provider_fields(),

				);

				$meta_boxes[] = array(
					'id'             => 'fields',
					'title'          => __( 'Fields', 'simple-stack' ),
					'settings_pages' => 'sstk_settings',
					'tab'            => 'fields',
					'fields'         => array(
						array(
							'type' => 'heading',
							'name' => __( 'Server List Fields', 'simple-stack' ),
							'desc' => __( 'Some columns on the server list are hidden. Use the options below to show them.', 'simple-stack' ),
						),
						array(
							'id'   => 'sstk_show_server_list_short_desc',
							'type' => 'checkbox',
							'name' => __( 'Show the short description column?', 'simple-stack' ),
						),
						array(
							'id'      => 'sstk_server_list_instance_id_column',
							'type'    => 'checkbox',
							'name'    => __( 'Show the instance id as a separate column?', 'simple-stack' ),
							'tooltip' => __( 'The instance id is normally shown under the PROVIDER DETAILS column. If you want it as a separate column, turn this flag on.', 'simple-stack' ),
						),
						array(
							'id'      => 'sstk_server_list_hide_instance_id_column',
							'type'    => 'checkbox',
							'name'    => __( 'HIDE the instance id from the server list?', 'simple-stack' ),
							'tooltip' => __( 'The instance id is normally shown under the PROVIDER DETAILS column. Check this field to not display it at all. Note that this applies only to non-admins.  It will always display for admins.', 'simple-stack' ),
						),
						array(
							'id'      => 'sstk_server_list_region_column',
							'type'    => 'checkbox',
							'name'    => __( 'Show the region as a separate column?', 'simple-stack' ),
							'tooltip' => __( 'The region is normally shown under the PROVIDER DETAILS column. If you want it as a separate column, turn this flag on.', 'simple-stack' ),
						),
						array(
							'id'      => 'sstk_show_server_list_date',
							'type'    => 'checkbox',
							'name'    => __( 'Show the date column?', 'simple-stack' ),
							'tooltip' => __( 'The server creation date column takes up space and is usually not that useful after a while.  If you need it, just turn this flag on.', 'simple-stack' ),
						),
						array(
							'id'      => 'sstk_show_server_list_server_type',
							'type'    => 'checkbox',
							'name'    => __( 'Show the server type column?', 'simple-stack' ),
							'tooltip' => __( 'The server type column takes up space but if you are only using one application type then this isn\'t useful information.  If you need it because you are handling multiple types of application servers, just turn this flag on.', 'simple-stack' ),
						),
						array(
							'id'      => 'sstk_show_server_list_owner',
							'type'    => 'checkbox',
							'name'    => __( 'Show the server owner column?', 'simple-stack' ),
							'tooltip' => __( 'The server type column takes up space but if you are the only owner or server manager then this isn\'t useful information.  If you need it because you indeed have multiple owners for your servers, just turn this flag on.', 'simple-stack' ),
						),
						array(
							'id'      => 'sstk_hide_server_list_owner_non_admins',
							'type'    => 'checkbox',
							'name'    => __( 'Hide owner column from non-admins?', 'simple-stack' ),
							'tooltip' => __( 'Do not show the owner column to non-admins, even if the above option is turned on.', 'simple-stack' ),
						),
						array(
							'id'      => 'sstk_show_server_list_team',
							'type'    => 'checkbox',
							'name'    => __( 'Show the server team column?', 'simple-stack' ),
							'tooltip' => __( 'The server team column takes up space but if you are not using teams then this isn\'t useful information so by default it is turned off. Check this box to turn it on.', 'simple-stack' ),
						),

						// Applist fields.
						array(
							'type' => 'heading',
							'name' => __( 'App List Fields', 'simple-stack' ),
							'desc' => __( 'Some columns on the applications list are hidden. Use the options below to show them.', 'simple-stack' ),
						),
						array(
							'id'   => 'sstk_show_app_list_short_desc',
							'type' => 'checkbox',
							'name' => __( 'Show the short description column?', 'simple-stack' ),
						),
						array(
							'id'      => 'sstk_show_app_list_date',
							'type'    => 'checkbox',
							'name'    => __( 'Show the date column?', 'simple-stack' ),
							'tooltip' => __( 'The app creation date column takes up space and is usually not that useful after a while.  If you need it, just turn this flag on.', 'simple-stack' ),
						),
						array(
							'id'      => 'sstk_show_app_list_app_type',
							'type'    => 'checkbox',
							'name'    => __( 'Show the application type column?', 'simple-stack' ),
							'tooltip' => __( 'The application type column takes up space but if you are only using one application type then this isn\'t useful information.  If you need it because you are handling multiple types of applications, just turn this flag on.', 'simple-stack' ),
						),
						array(
							'id'      => 'sstk_show_app_list_owner',
							'type'    => 'checkbox',
							'name'    => __( 'Show the app owner column?', 'simple-stack' ),
							'tooltip' => __( 'The app owner column takes up space but if you are the only owner then this isn\'t useful information.  If you need it because you indeed have multiple owners for your apps, just turn this flag on.', 'simple-stack' ),
						),
						array(
							'id'      => 'sstk_hide_app_list_owner_non_admins',
							'type'    => 'checkbox',
							'name'    => __( 'Hide owner column from non-admins?', 'simple-stack' ),
							'tooltip' => __( 'Do not show the owner column to non-admins, even if the above option is turned on.', 'simple-stack' ),
						),
						array(
							'id'      => 'sstk_show_app_list_team',
							'type'    => 'checkbox',
							'name'    => __( 'Show the app team column?', 'simple-stack' ),
							'tooltip' => __( 'The app team column takes up space but if you are not using teams then this isn\'t useful information so by default it is turned off. Check this box to turn it on.', 'simple-stack' ),
						),
						array(
							'id'      => 'sstk_show_app_list_provider',
							'type'    => 'checkbox',
							'name'    => __( 'Show the server provider column?', 'simple-stack' ),
							'tooltip' => __( 'The server provider is shown under the SERVER column to save space.  If you would like to sort the list by this field then you need to enable this option so that it is shown in its own column.', 'simple-stack' ),
						),
						array(
							'id'      => 'sstk_show_app_list_region',
							'type'    => 'checkbox',
							'name'    => __( 'Show the server region column?', 'simple-stack' ),
							'tooltip' => __( 'The server region is shown under the SERVER column to save space.  If you would like to sort the list by this field then you need to enable this option so that it is shown in its own column.', 'simple-stack' ),
						),
						array(
							'id'      => 'sstk_show_app_list_ipv4',
							'type'    => 'checkbox',
							'name'    => __( 'Show the server IP column?', 'simple-stack' ),
							'tooltip' => __( 'The server IP is shown under the SERVER column to save space.  If you would like to sort the list by this field then you need to enable this option so that it is shown in its own column.', 'simple-stack' ),
						),

						array(
							'id'      => 'sstk_show_app_list_health',
							'type'    => 'checkbox',
							'name'    => __( 'Show the app health column?', 'simple-stack' ),
							'tooltip' => __( 'The app health data is shown under the APP SUMMARY column to save space.  You can enable this option so that it is shown in its own column.', 'simple-stack' ),
						),

						// Applist compound fields: Server Column.
						array(
							'type' => 'heading',
							'name' => __( 'App List Compound Fields', 'simple-stack' ),
							'desc' => __( 'You can make some of the columns that hold multiple pieces of information shorter for non-admin users.', 'simple-stack' ),
						),
						array(
							'id'      => 'sstk_hide_app_list_server_name_in_server_column',
							'type'    => 'checkbox',
							'name'    => __( 'Hide the server name in the server column?', 'simple-stack' ),
							'tooltip' => __( 'The server name is usually shown in the SERVER column on the sites screen. Check this box to remove it for non-admin users.', 'simple-stack' ),
						),
						array(
							'id'      => 'sstk_hide_app_list_provider_in_server_column',
							'type'    => 'checkbox',
							'name'    => __( 'Hide the provider data in the server column?', 'simple-stack' ),
							'tooltip' => __( 'The provider information is usually shown in the SERVER column on the sites screen. Check this box to remove it for non-admin users.', 'simple-stack' ),
						),
						array(
							'id'      => 'sstk_hide_app_list_region_in_server_column',
							'type'    => 'checkbox',
							'name'    => __( 'Hide the region data in the server column?', 'simple-stack' ),
							'tooltip' => __( 'The region information is usually shown in the SERVER column on the sites screen. Check this box to remove it for non-admin users.', 'simple-stack' ),
						),
						array(
							'id'      => 'sstk_hide_app_list_appslink_in_server_column',
							'type'    => 'checkbox',
							'name'    => __( 'Hide the link to apps in the server column?', 'simple-stack' ),
							'tooltip' => __( 'A link to other apps on the server is usually shown in the SERVER column on the sites screen. Check this box to remove it for non-admin users.', 'simple-stack' ),
						),

						// State labels.
						array(
							'type' => 'heading',
							'name' => __( 'Labels', 'simple-stack' ),
							'desc' => __( 'Labels are additional short pieces of data that appear next to the application title in the application list.', 'simple-stack' ),
						),
						array(
							'id'      => 'sstk_show_private_state',
							'type'    => 'checkbox',
							'name'    => __( 'Show Private State Label?', 'simple-stack' ),
							'tooltip' => __( 'Since all servers and apps are PRIVATE, we do not show this state label. You can force display of the PRIVATE state label with this option.', 'simple-stack' ),
							'desc'    => '',
							'tab'     => 'wordpress-app-general-wpadmin',
						),

						// IPv6.
						array(
							'type' => 'heading',
							'name' => __( 'IPv6', 'simple-stack' ),
							'desc' => __( 'How should we handle IPv6 data?', 'simple-stack' ),
						),
						array(
							'id'      => 'sstk_show_ipv6',
							'type'    => 'checkbox',
							'name'    => __( 'Show IPv6 Fields?', 'simple-stack' ),
							'tooltip' => __( 'Most systems are not set up for IPv6. But we do sometimes collect it when provided by the server provider. Check this box if you want to  display it when it\'s available and to be able to sort and filter by it.', 'simple-stack' ),
							'desc'    => '',
							'tab'     => 'wordpress-app-general-wpadmin',
						),

						// Server sizes.
						array(
							'type' => 'heading',
							'name' => __( 'Server Sizes', 'simple-stack' ),
							'desc' => __( 'Add custom server sizes if the predefined sizes do not match your needs. If nothing is added here, the default sizes remain Large, Medium and Small.', 'simple-stack' ),
						),
						array(
							'name'    => '',
							'id'      => 'sstk_default_server_sizes',
							'type'    => 'fieldset_text',
							'options' => array(
								'size'      => __( 'Size Code - No Spaces', 'simple-stack' ),
								'size_desc' => __( 'Size Description (Customers see this!)', 'simple-stack' ),
							),
							'clone'   => true,
						),

					),
				);

				$meta_boxes[] = array(
					'id'             => 'tools',
					'title'          => __( 'Tools', 'simple-stack' ),
					'settings_pages' => 'sstk_settings',
					'tab'            => 'tools',
					'fields'         => array(
						array(
							'type' => 'heading',
							'name' => 'Clean Up Applications',
							'desc' => __( 'This action resets all in-process metas for all apps. This means that any background process that is "stuck" will stop running and any app deployment that is in-process will be terminated.', 'simple-stack' ),
						),

						array(
							'type'       => 'button',
							'std'        => __( 'Clean Up Apps', 'simple-stack' ),
							'attributes' => array(
								'id'          => 'sstk-cleanup-apps',
								'data-action' => 'sstk_cleanup_apps',
								'data-nonce'  => wp_create_nonce( 'sstk-cleanup-apps' ),
							),
						),

						array(
							'type' => 'heading',
							'name' => 'Clean Up Servers',
							'desc' => __( 'This action resets all in-process metas for all servers. This means that any background process that is "stuck" will stop running and any server deployment that is in-process will be terminated.', 'simple-stack' ),
						),

						array(
							'type'       => 'button',
							'std'        => __( 'Clean Up Servers', 'simple-stack' ),
							'attributes' => array(
								'id'          => 'sstk-cleanup-servers',
								'data-action' => 'sstk_cleanup_servers',
								'data-nonce'  => wp_create_nonce( 'sstk-cleanup-servers' ),
							),
						),

						array(
							'type' => 'heading',
							'name' => __( 'Bulk Email Server Batches', 'simple-stack' ),
							'desc' => __( 'View all email batches sent for servers.', 'simple-stack' ),
						),

						array(
							'type'       => 'button',
							'std'        => __( 'Server Batches', 'simple-stack' ),
							'attributes' => array(
								'onclick' => 'window.location.href="' . admin_url( 'edit.php?post_type=sstk_server_batch' ) . '"',
							),
						),

						array(
							'type' => 'heading',
							'name' => __( 'Bulk Email App Batches', 'simple-stack' ),
							'desc' => __( 'Voew all email batches sent for apps.', 'simple-stack' ),
						),

						array(
							'type'       => 'button',
							'std'        => __( 'App Batches', 'simple-stack' ),
							'attributes' => array(
								'onclick' => 'window.location.href="' . admin_url( 'edit.php?post_type=sstk_app_batch' ) . '"',
							),
						),

					),
				);

				if ( true === sstk_is_custom_dns_provider_tables_enabled() ) {
					$meta_boxes[] = array(
						'id'             => 'wordpress-provider-settings',
						'title'          => __( 'Provider', 'simple-stack' ),
						'settings_pages' => 'sstk_settings',
						'tab'            => 'wordpress-provider-settings',
						'fields'         => array(
							array(
								'name'            => __( 'Roles allowed to add', 'simple-stack' ),
								'id'              => 'provider_add_allowed_roles',
								'type'            => 'select_advanced',
								'options'         => sstk_get_roles(),
								'select_all_none' => true,
								'multiple'        => true,
								'desc'            => '',
								'placeholder'     => __( 'Select allowed roles.', 'simple-stack' ),
							),
						),
					);

					$meta_boxes[] = array(
						'id'             => 'wordpress-dns-settings',
						'title'          => __( 'DNS', 'simple-stack' ),
						'settings_pages' => 'sstk_settings',
						'tab'            => 'wordpress-dns-settings',
						'fields'         => array(

							array(
								'name'            => __( 'Roles allowed to add', 'simple-stack' ),
								'id'              => 'dns_add_allowed_roles',
								'type'            => 'select_advanced',
								'options'         => sstk_get_roles(),
								'select_all_none' => true,
								'multiple'        => true,
								'desc'            => '',
								'placeholder'     => __( 'Select allowed roles.', 'simple-stack' ),
							),
						),
					);
				}

				// Enable saving filter on the a random field so we can clear the provider cache when settings are saved.
				add_filter( 'rwmb_sstk_show_server_list_short_desc_value', array( &$this, 'sstk_clear_all_providers_cache' ), 10, 3 );

				return apply_filters( 'sstk_settings_metaboxes', $meta_boxes );

			},
		);

	}

	/**
	 * Registers an option page using the metabox.io infrastructure
	 *
	 * Filter hook: mb_settings_pages
	 *
	 * @param array $settings_pages Array of current settings pages.
	 *
	 * @return array $settings_pages New array of settings pages.
	 */
	public function register_options_page( $settings_pages ) {

		// Define tabs.
		if ( true === ( boolval( sstk_get_early_option( 'hide-general-settings-tab' ) ) ) ) {
			$tabs = array(
				'cloud-provider' => __( 'Cloud Providers', 'simple-stack' ),
			);
		} else {
			$tabs = array(
				'general'        => __( 'General Settings', 'simple-stack' ),
				'cloud-provider' => __( 'Cloud Providers', 'simple-stack' ),
			);
		}

		// Settings for custom tables for DNS and PROVIDERS.
		if ( true === sstk_is_custom_dns_provider_tables_enabled() ) {
			$tabs['wordpress-provider-settings'] = __( 'Provider', 'simple-stack' );
			$tabs['wordpress-dns-settings']      = __( 'DNS', 'simple-stack' );
		}

		// Allow other components to hook into this and setup their own tabs..
		$tabs = apply_filters( 'sstk_settings_tabs', $tabs );

		// Final set of tabs.
		$tabs['fields'] = __( 'Fields', 'simple-stack' );

		$tabs['misc']    = __( 'Misc', 'simple-stack' );
		$tabs['logging'] = __( 'Logging and Tracing', 'simple-stack' );
		$tabs['tools']   = __( 'Tools', 'simple-stack' );

		// Settings page array with the tabs.
		$settings_pages[] = array(
			'id'          => 'sstk_settings',
			'option_name' => 'sstk_settings',
			'menu_title'  => __( 'Settings', 'simple-stack' ),
			'icon_url'    => 'dashicons-layout',
			'style'       => 'no-boxes',
			// Parented to the Simple Stack menu: the server post type is no longer a top level
			// menu, so this page would otherwise be unreachable.
			'parent'      => 'simplestack',
			'position'    => 80,
			'columns'     => 2,
			'tabs'        => $tabs,
			'capability'  => 'sstk_manage_settings',
		);

		return apply_filters( 'sstk_settings_pages', $settings_pages );
	}

	/**
	 * Generate one tab for each cloud server provider
	 *
	 * @return array
	 */
	public function provider_metabox_tabs() {
		$provider_tabs = array();
		$providers     = SSTK()->get_cloud_providers();
		foreach ( $providers as $provider => $name ) {
			$tab_id        = "sstk-cloud-{$provider}";
			$new_tab       = array(
				$tab_id => array(
					'label' => $name,
					'icon'  => 'dashicons-networking',
				),
			);
			$provider_tabs = $provider_tabs + $new_tab;
		}
		return $provider_tabs;
	}

	/**
	 * Return an array of fields for each cloud server provider
	 * Settings for each cloud server will end up in individual tabs
	 * under a single metabox on the settings screen.
	 *
	 * @return array
	 */
	public function provider_fields() {

		$provider_fields = array();
		$providers       = SSTK()->get_cloud_providers();

		foreach ( $providers as $provider => $name ) {

			/* Make sure we have a valid api reference for the current provider */
			if ( empty( SSTK()->get_provider_api( $provider ) ) ) {
				continue;
			}

			/* Set a var to hold some error messaages */
			$errmsg = '';

			/* Get sizes and keys from the provider */
			$sizes = SSTK()->get_provider_api( $provider )->call( 'sizes' );
			$keys  = SSTK()->get_provider_api( $provider )->call( 'keys' );

			if ( $sizes instanceof \WP_Error ) {
				$errmsg .= $sizes->get_error_code() . '/' . $sizes->get_error_message();
				$sizes   = array();
			}

			if ( $keys instanceof \WP_Error ) {
				$errmsg .= $keys->get_error_code() . '/' . $keys->get_error_message();
				$keys    = array();
			}
			/* End get sizes and keys from the provider */

			/* Fields will show up in this tab in the metabox */
			$tab_id = "sstk-cloud-{$provider}";

			/* Fields for api keys - heading area */
			if ( ( ! defined( 'SSTK_HIDE_PROVIDER_HELP_LINKS' ) ) || ( defined( 'SSTK_HIDE_PROVIDER_HELP_LINKS' ) && ! SSTK_HIDE_PROVIDER_HELP_LINKS ) ) {
				$fields_part0 = array(

					array(
						'type' => 'heading',
						'tab'  => $tab_id,
						'name' => __( 'API Keys', 'simple-stack' ),
					),

					array(
						'id'   => "sstk_{$provider}_help_link",
						'type' => 'custom_html',
						'name' => __( 'Need Help?', 'simple-stack' ),
						/* translators: %s: A link to the help documentation for providers. */
						'std'  => sprintf( __( '%s', 'simple-stack' ), '<a href="' . SSTK()->get_provider_api( $provider )->get_provider_help_link() . '"' . 'target="_blank"' . '>' . __( 'View the documentation', 'simple-stack' ) . '</a>' ),
						'tab'  => $tab_id,
					),

				);
			} else {

				$fields_part0 = array(
					array(
						'type' => 'heading',
						'tab'  => $tab_id,
						'name' => __( 'API Keys', 'simple-stack' ),
					),
				);

			}

			/* Add warning for providers that are in use */
			$args    = array(
				'post_type'   => 'sstk_app_server',
				'post_status' => 'private',
				'meta_key'    => 'sstk_server_provider',
				'meta_value'  => $provider,
				'fields'      => 'ids',
			);
			$servers = get_posts( $args );

			if ( count( $servers ) ) {

				if ( ( ! defined( 'SSTK_HIDE_PROVIDER_HELP_LINKS' ) ) || ( defined( 'SSTK_HIDE_PROVIDER_HELP_LINKS' ) && ! SSTK_HIDE_PROVIDER_HELP_LINKS ) ) {
					$top_warning_help_link = '<a href="https://github.com/onedogsolutions/simple-stack/blob/main/docs/SIMPLESTACK.md#7-providers"  target="_blank">' . __( 'Learn more', 'simple-stack' ) . '</a>';
				} else {
					$top_warning_help_link = '';
				}

				/* Translators: 1. %s - Link to help text for warning */
				$top_warning_text = sprintf( __( 'Simple Stack: Warning - This provider is being used by one or more servers.  Caution should be exercised when making changes otherwise the connection to your server(s) could be broken. %s', 'simple-stack' ), $top_warning_help_link );

				$sstk_provider_top_warning = array(
					'id'    => "sstk_{$provider}_in_use_warning",
					'type'  => 'custom_html',
					'name'  => '',
					'tab'   => $tab_id,
					'std'   => $top_warning_text,
					'class' => "sstk_settings_provider_in_use_warning sstk_settings_{$provider}_provider_in_use_warning",
				);
				array_unshift( $fields_part0, $sstk_provider_top_warning );
			}
			/* End add warning for providers that are in use */

			/**
			 * Button to test provider connection.  This will be added into an array later below.
			 */
			if ( SSTK()->get_provider_api( $provider )->get_feature_flag( 'test_connection' ) && ! $this->is_api_key_empty( $provider ) ) {
				$last_test_connection_status = $this->sstk_get_last_test_connection_status( $provider );
				if ( true === $last_test_connection_status ) {
					$ssh_test_connection_heading_desc = __( 'The last attempt to test your connection to this provider was successful.', 'simple-stack' );
				} else {
					$ssh_test_connection_heading_desc = __( 'The last test was unsuccessful or a test has never been run on this provider.', 'simple-stack' );
				}

				$fields_test_provider_connection = array(
					'id'         => "sstk_{$provider}_test_provider_connection",
					'type'       => 'button',
					'std'        => __( 'Test Connection', 'simple-stack' ),
					'desc'       => $ssh_test_connection_heading_desc,
					'attributes' => array(
						'class'         => 'sstk-provider-test-provider-connection',
						'data-action'   => 'sstk_provider_test_provider_connection',
						'data-nonce'    => wp_create_nonce( 'sstk-test-provider-connection' ),
						'data-provider' => $provider,
					),
					'tab'        => $tab_id,
				);
			} else {
				$fields_test_provider_connection = array(
					'type' => 'hidden',
					'std'  => 'not used - empty because this provider does not support testing a provider connection.',
					'tab'  => $tab_id,
				);
			}

			/* First group of api fields */
			$fields_part1 = array(

				array(
					'id'                => "sstk_{$provider}_apikey",
					'type'              => 'text',
					'name'              => apply_filters( "sstk_cloud_provider_settings_api_key_name_{$provider}", __( 'API Key', 'simple-stack' ) ),
					'desc'              => apply_filters( "sstk_cloud_provider_settings_api_key_desc_{$provider}", __( 'You can get this key from your providers security or API dashboard. It is encrypted before being stored in the database.', 'simple-stack' ) ),
					'label_description' => apply_filters( "sstk_cloud_provider_settings_api_key_label_desc_{$provider}", '' ),
					'size'              => '60',
					'tab'               => $tab_id,
					'class'             => 'sstk_settings_pass_toggle',
					'attributes'        => array(
						'spellcheck' => 'false',
					),
				),

				array(
					'id'   => "sstk_{$provider}_keynotes",
					'type' => 'textarea',
					'name' => __( 'Notes', 'simple-stack' ),
					'rows' => '1',
					'desc' => __( 'Your notes about this api key - optional', 'simple-stack' ),
					'tab'  => $tab_id,
				),

				$fields_test_provider_connection,

				apply_filters(
					"sstk_cloud_provider_settings_after_api_{$provider}",
					array(
						'type' => 'hidden',
						'std'  => 'not used - marker for filter after api key.',
						'tab'  => $tab_id,
					),
					$tab_id
				),

			);

			$fields_part1 = apply_filters( "sstk_cloud_provider_settings_after_part1_{$provider}", $fields_part1, $tab_id );

			/* Basic instructional fields after api keys are filled in. */
			$fields_part2 = array();
			if ( $this->is_api_key_empty( $provider ) ) {
				// Show some instructions when the API keys are empty.
				$fields_part2 = array(
					array(
						'id'    => "sstk_{$provider}_key_not_available_instructions",
						'type'  => 'custom_html',
						'name'  => __( 'Additional Data', 'simple-stack' ),
						'tab'   => $tab_id,
						'std'   => __( 'When you save your API keys you will see additional fields open up below. If you do not see these additional fields, try clicking the SAVE SETTINGS button again.', 'simple-stack' ),
						'class' => "sstk_settings_key_not_available_instructions sstk_settings_{$provider}_key_not_available_instructions",
					),
				);
			}

			$can_connect_to_provider = $this->sstk_can_connect_to_provider( $provider );

			/**
			 * SSH Key fields.
			 */
			/* translators: %1: provider name. %2: provider name again. */
			$ssh_keys_heading_desc = sprintf( __( 'For security, we only use public-private key pairs for server management. You must upload at least one public key to %1$s. Public keys that have been uploaded to %2$s\'s dashboard will show up in the drop-down below once your credentials above are configured and saved.<br /><br />You must click the SAVE SETTINGS at the bottom of this screen at least once after you enter your api key above in order for this list to populate. If the list continues to be blank, double-check that you have added at least one SSH key in your cloud provider\'s dashboard.', 'simple-stack' ), $provider, $provider );
			if ( SSTK()->get_provider_api( $provider )->get_feature_flag( 'ssh_create' ) ) {
				$ssh_keys_heading_desc .= '<br /><br />' . __( 'Note: If you used the button above to automatically create keys and that operation was successful, you new keys should already be setup below. ', 'simple-stack' );
			}
			$ssh_keys_heading_desc = apply_filters( "sstk_cloud_provider_settings_ssh_keys_heading_desc_{$provider}", $ssh_keys_heading_desc );
			$fields_part3          = array();
			if ( ! $this->is_api_key_empty( $provider ) && $can_connect_to_provider ) {
				$fields_part3 = array(
					array(
						'type' => 'heading',
						'tab'  => $tab_id,
						'name' => __( 'SSH Keys', 'simple-stack' ),
						'desc' => $ssh_keys_heading_desc,
					),

					array(
						'id'      => "sstk_{$provider}_sshkey_id",
						'type'    => 'select',
						'name'    => __( 'Public SSH Key', 'simple-stack' ),
						'tooltip' => __( 'For most providers this public key is stored at the provider and will be installed into each server instance when the instance is first created.  If you change this value only FUTURE instances will get the new key. Instances that are already up and running will retain the prior key and any future operations on those instances will FAIL!  Note that some providers such as UPCLOUD and CUSTOM PROVIDERS might not use this key.  Check the documentation for the provider if you are unsure if this is required.', 'simple-stack' ),
						'options' => $keys,
						/* translators: %s: provider name. */
						'desc'    => apply_filters( "sstk_cloud_provider_settings_ssh_keys_select_desc_{$provider}", sprintf( __( 'Public key saved in %s. If you have just uploaded your public key and do not see it here, try clicking the CLEAR CACHE button at the bottom of this page.', 'simple-stack' ), $name ) ),
						'tab'     => $tab_id,
					),
				);

				/**
				 * Button to automatically create ssh keys.
				 */
				if ( SSTK()->get_provider_api( $provider )->get_feature_flag( 'ssh_create' ) && ! $this->is_api_key_empty( $provider ) ) {
					$ssh_auto_create_keys_heading_desc  = __( 'SSH keys are critical for proper operation of this service.', 'simple-stack' );
					$ssh_auto_create_keys_heading_desc .= '<br />' . __( 'This provider can automatically create your SSH keys for you and submit them to your account.', 'simple-stack' );
					$ssh_auto_create_keys_heading_desc .= '<br />' . __( 'If you are not familiar with creating and managing keys or you would like a set of keys created for you, click the button below.', 'simple-stack' );
					$ssh_auto_create_keys_heading_desc .= '<br />' . __( 'However, it is important that you only do this if you have not already created servers with your own keys!', 'simple-stack' );
					$ssh_auto_create_keys_heading_desc .= '<br />' . __( 'Otherwise you could be locked out of your servers and your keys could be lost!', 'simple-stack' );
					$ssh_auto_create_keys_heading_desc .= '<br />' . __( 'If this operation is successful you should immediately make a copy of your private key which will be shown in the text boxes below. Store it in a safe place!', 'simple-stack' );

					$fields_auto_create_ssh_keys = array(
						array(
							'type' => 'heading',
							'tab'  => $tab_id,
							'name' => __( 'Automatically Create SSH Keys: STOP AND READ CAREFULLY!', 'simple-stack' ),
							'desc' => $ssh_auto_create_keys_heading_desc,
						),

						array(
							'id'         => "sstk_{$provider}_auto_create_ssh_key",
							'type'       => 'button',
							'std'        => __( 'Create SSH Key-Pair', 'simple-stack' ),
							'attributes' => array(
								'class'         => 'sstk-provider-auto-create-ssh-key',
								'data-action'   => 'sstk_provider_auto_create_ssh_key',
								'data-nonce'    => wp_create_nonce( 'sstk-auto-create-ssh-key' ),
								'data-provider' => $provider,
							),
							'tab'        => $tab_id,
						),
					);
				} else {
					$fields_auto_create_ssh_keys = array();
				}

				if ( ! empty( $fields_auto_create_ssh_keys ) ) {
					$fields_part3 = array_merge( $fields_auto_create_ssh_keys, $fields_part3 );
				}

				$fields_part3 = apply_filters( "sstk_cloud_provider_settings_after_part3_{$provider}", $fields_part3, $tab_id );
			}

			$fields_part4 = array();
			if ( ! $this->is_api_key_empty( $provider ) && $can_connect_to_provider ) {
				$private_keys_text_note = apply_filters( 'sstk_cloud_provider_settings_important_private_key_notes', '<a href="https://github.com/onedogsolutions/simple-stack/blob/main/docs/SIMPLESTACK.md#7-providers" target="_blank" >' . __( 'View important notes about private keys.', 'simple-stack' ) . '</a>' );
				$fields_part4           = array(
					array(
						'id'         => "sstk_{$provider}_sshkey",
						'type'       => 'textarea',
						'name'       => __( 'Private SSH Key', 'simple-stack' ),
						'rows'       => '10',
						/* Translators: 1. %s - A note about private keys */
						'desc'       => apply_filters( 'sstk_private_ssh_key_settings_desc', sprintf( __( 'Private key corresponding to the selected public key - it will be used to connect to the instance. The key will be encrypted when saved and decrypted when retrieved. Also, make sure that there are no spaces or extra lines entered at the end of the key-text.  %s', 'simple-stack' ), $private_keys_text_note ) ),
						'tab'        => $tab_id,
						'class'      => 'sstk_settings_pass_toggle',
						'attributes' => array(
							'spellcheck' => 'false',
						),
					),

					array(
						'id'         => "sstk_{$provider}_sshkey_passwd",
						'type'       => 'text',
						'name'       => __( 'Private SSH Key Password', 'simple-stack' ),
						'size'       => '90',
						'desc'       => sprintf( __( 'Password for the above private key. It will be encrypted on save and decrypted when retrieved.', 'simple-stack' ), $name ) . '<br />' . __( 'If your private key does not have a password associated with it you can leave this blank.', 'simple-stack' ) . '<br />' . __( 'Note: For keypairs generated on a MAC or on WSL, do NOT apply a password to your private key file!', 'simple-stack' ),
						'tab'        => $tab_id,
						'class'      => 'sstk_settings_pass_toggle',
						'attributes' => array(
							'spellcheck' => 'false',
						),
					),

					array(
						'id'   => "sstk_{$provider}_sshkeynotes",
						'type' => 'textarea',
						'name' => __( 'Notes', 'simple-stack' ),
						'rows' => '1',
						'desc' => __( 'Your notes about this ssh key - optional', 'simple-stack' ),
						'tab'  => $tab_id,
					),

				);
			}

			/* Sizes fields; Other optional Fields; */
			$fields_part5 = array();
			if ( ! $this->is_api_key_empty( $provider ) && $can_connect_to_provider ) {

				// Custom images.
				if ( SSTK()->get_provider_api( $provider )->get_feature_flag( 'custom_images' ) ) {
					$fields_part5 = array_merge(
						$fields_part5,
						array(
							array(
								'type' => 'heading',
								'tab'  => $tab_id,
								'name' => __( 'Custom Snapshots & Images', 'simple-stack' ),
								'desc' => __( 'These snapshots can be used in place of the default OS images.  Please check our documentation for this provider to get more details on the limitations of using custom snapshots & custom images.', 'simple-stack' ),
							),
							array(
								'id'      => "sstk_{$provider}_ubuntu2404lts",
								'type'    => 'text',
								'name'    => __( 'Ubuntu 24.04', 'simple-stack' ),
								'desc'    => __( 'The custom snapshot, image or backup id to be used instead of the default Ubuntu 24.04 image.', 'simple-stack' ),
								'tooltip' => __( 'See our documentation for more information about this item.', 'simple-stack' ),
								'tab'     => $tab_id,
							),
							array(
								'id'      => "sstk_{$provider}_ubuntu2604lts",
								'type'    => 'text',
								'name'    => __( 'Ubuntu 26.04', 'simple-stack' ),
								'desc'    => __( 'The custom snapshot, image or backup id to be used instead of the default Ubuntu 26.04 image.', 'simple-stack' ),
								'tooltip' => __( 'See our documentation for more information about this item.', 'simple-stack' ),
								'tab'     => $tab_id,
							),
						),
					);
				}

				// Backups?
				if ( SSTK()->get_provider_api( $provider )->get_feature_flag( 'enable_backups_on_server_create' ) ) {
					$fields_part5 = array_merge(
						$fields_part5,
						array(
							array(
								'id'   => "sstk_{$provider}_provider_backups",
								'type' => 'heading',
								'name' => __( 'Provider Backups', 'simple-stack' ),
								'tab'  => $tab_id,
								'desc' => __( 'Set provider backup behavior if backups are supported.', 'simple-stack' ),
							),
							array(
								'id'      => "sstk_{$provider}_enable_provider_backups_on_server_create",
								'type'    => 'checkbox',
								'name'    => __( 'Enable Provider Backups', 'simple-stack' ),
								'tab'     => $tab_id,
								'tooltip' => __( 'Enable automatic backups for every new server.', 'simple-stack' ),
							),
						),
					);
				}

				// Tags?
				if ( SSTK()->get_provider_api( $provider )->get_feature_flag( 'enable_dynamic_tags_on_server_create' ) ) {
					$fields_part5 = array_merge(
						$fields_part5,
						array(
							array(
								'id'   => "sstk_{$provider}_provider_tags",
								'type' => 'heading',
								'name' => __( 'Tags', 'simple-stack' ),
								'tab'  => $tab_id,
								'desc' => __( 'Set tags if server tags are supported for this provider.', 'simple-stack' ),
							),
							array(
								'id'      => "sstk_{$provider}_tags_on_server_create",
								'type'    => 'text',
								'name'    => __( 'Tag For New Servers', 'simple-stack' ),
								'size'    => '30',
								'tab'     => $tab_id,
								'tooltip' => __( 'Apply this tag to every new server. If left blank, the tag will default to Simple Stack for providers that allow for dynamic/random tags. Note: Some providers require that you use tags that have already been defined while others allow you to set random/dynamic tags.', 'simple-stack' ),
							),
						),
					);
				}

				// Sizes fields.
				if ( $this->show_server_sizes( $provider ) ) {
					if ( apply_filters( 'sstk_show_server_sizes_in_settings', false ) ) {
						$fields_part5 = array_merge(
							$fields_part5,
							array(
								array(
									'id'    => "sstk_{$provider}_sizes_instructions",
									'type'  => 'heading',
									'name'  => __( 'Server Sizes', 'simple-stack' ),
									'tab'   => $tab_id,
									'desc'  => __( 'These predefined sizes are available when provisioning new servers.', 'simple-stack' ),
									'class' => "sstk_settings_sizes_instructions sstk_settings_{$provider}_sizes_instructions",
								),
							),
							$this->get_generic_size_fields( $tab_id, $provider, $sizes )
						);
					}
				}
			}

			$fields_part6 = array();
			if ( ! $this->is_api_key_empty( $provider ) && $can_connect_to_provider ) {

				$fields_part6 = array(
					array(
						'id'   => "sstk_{$provider}_white_label",
						'type' => 'heading',
						'name' => 'White Label',
						'desc' => __( 'Change provider display name / description.', 'simple-stack' ),
						'tab'  => $tab_id,
					),

					array(
						'id'   => "sstk_{$provider}_alt_desc",
						'type' => 'text',
						'name' => __( 'Alternative Name', 'simple-stack' ),
						'desc' => __( 'Use this as the display name for the provider.', 'simple-stack' ),
						'tab'  => $tab_id,
					),

				);
			}

			$fields_part7 = array();
			if ( ! $this->is_api_key_empty( $provider ) && $can_connect_to_provider ) {

				// Get list of cached transients for this provider.
				$cached_transient_list = SSTK()->get_provider_api( $provider )->get_cached_transient_list();

				$fields_part7 = array(
					array(
						'id'   => "sstk_{$provider}_cache_instructions",
						'type' => 'heading',
						'name' => 'Cache',
						'desc' => __( 'If keys, sizes and other information in the drop-down selects above are not up-to-date, you can clear the cache which will force a refresh from your cloud provider. The cache is usually cleared every 24 hours.', 'simple-stack' ),
						'tab'  => $tab_id,
					),

					array(
						'id'         => "sstk_{$provider}_clear_cache",
						'type'       => 'button',
						'std'        => __( 'Clear Cached Data', 'simple-stack' ),
						'attributes' => array(
							'class'         => 'sstk-provider-clear-cache',
							'data-action'   => 'sstk_provider_clear_cache',
							'data-nonce'    => wp_create_nonce( 'sstk-clear-cache' ),
							'data-provider' => $provider,
						),
						'tab'        => $tab_id,
					),

					array(
						'id'   => "sstk_{$provider}_cached_transients_header",
						'type' => 'heading',
						'name' => 'Cached Transients',
						'desc' => __( 'Any data cached in transients for this provider is listed below. To refresh the cache click the CLEAR CACHE button above.', 'simple-stack' ),
						'tab'  => $tab_id,
					),
					array(
						'id'   => "sstk_{$provider}_cache_transients",
						'type' => 'custom_html',
						'std'  => $cached_transient_list,
						'tab'  => $tab_id,
					),

				);
			}

			/* Show API error messages if any */
			if ( ! empty( $errmsg ) ) {
				$provider_fields[] = array(
					'id'   => "sstk_{$provider}_err_msgs",
					'name' => __( 'Error Messages', 'simple-stack' ),
					'type' => 'custom_html',
					'std'  => '<span class="sstk_settings_err_msg">' . $errmsg . '</span>',
					'tab'  => $tab_id,
				);
			}

			/* Merge arrays */
			$provider_fields = array_merge( $provider_fields, $fields_part1, $fields_part2, $fields_part3, $fields_part4, $fields_part5, $fields_part6, $fields_part7 );

			/**
			 * Allow providers to insert additional configuration items
			 */
			$provider_fields = apply_filters( "sstk_cloud_provider_settings_{$provider}", $provider_fields, $tab_id );

			/**
			 * Add the top header to the settings...
			 */
			$provider_fields = array_merge( $fields_part0, $provider_fields );

			// Enable filter on the private key password field to encrypt it when its being stored.
			add_filter( "rwmb_sstk_{$provider}_sshkey_passwd_value", array( &$this, 'encrypt' ), 10, 3 );

			// Enable filter on the private key to encrypt it when its being stored.
			add_filter( "rwmb_sstk_{$provider}_sshkey_value", array( &$this, 'encrypt' ), 10, 3 );

			// Enable filter on the api key field to encrypt it when its being stored.
			add_filter( "rwmb_sstk_{$provider}_apikey_value", array( &$this, 'encrypt' ), 10, 3 );

			// Enable filter on the user_name field to encrypt it when its being stored. Note that this field is usually added to the settings array by a provider (eg: upcloud) and is not a standard field.
			add_filter( "rwmb_sstk_{$provider}_user_name_value", array( &$this, 'encrypt' ), 10, 3 );

			// Enable filter on the user_password field to encrypt it when its being stored. Note that this field is usually added to the settings array by a provider (eg: upcloud) and is not a standard field.
			add_filter( "rwmb_sstk_{$provider}_user_password_value", array( &$this, 'encrypt' ), 10, 3 );

			// Enable filter on the secret_key field to encrypt it when its being stored. Note that this field is usually added to the settings array by a provider (eg: exoscale) and is not a standard field.
			add_filter( "rwmb_sstk_{$provider}_secret_key_value", array( &$this, 'encrypt' ), 10, 3 );

			// Enable filter on the private key password field to decrypt it when its being retrieved.
			add_filter( "rwmb_sstk_{$provider}_sshkey_passwd_field_meta", array( &$this, 'decrypt' ), 10, 3 );

			// Enable filter on the private key field to decrypt it when its being retrieved.
			add_filter( "rwmb_sstk_{$provider}_sshkey_field_meta", array( &$this, 'decrypt' ), 10, 3 );

			// Enable filter on the api key field to decrypt it when its being retrieved.
			add_filter( "rwmb_sstk_{$provider}_apikey_field_meta", array( &$this, 'decrypt' ), 10, 3 );

			// Enable filter on the user_name field to decrypt it when its being retrieved. Note that this field is usually added to the settings array by a provider (eg: upcloud) and is not a standard field.
			add_filter( "rwmb_sstk_{$provider}_user_name_field_meta", array( &$this, 'decrypt' ), 10, 3 );

			// Enable filter on the user_password field to decrypt it when its being retrieved. Note that this field is usually added to the settings array by a provider (eg: upcloud) and is not a standard field.
			add_filter( "rwmb_sstk_{$provider}_user_password_field_meta", array( &$this, 'decrypt' ), 10, 3 );

			// Enable filter on the secret_key field to decrypt it when its being retrieved. Note that this field is usually added to the settings array by a provider (eg: exoscale) and is not a standard field.
			add_filter( "rwmb_sstk_{$provider}_secret_key_field_meta", array( &$this, 'decrypt' ), 10, 3 );

		}

		return $provider_fields;

	}

	/**
	 * Return the size keys that provider plans are mapped onto.
	 *
	 * Simple Stack has no WooCommerce layer to read product size options from, so the list
	 * lives here.  The keys end up inside option names such as `sstk_vultr-v2_small`, so
	 * they are kept stable and extendable through a filter rather than being rebuilt from
	 * whatever products happen to exist.
	 *
	 * @return array
	 */
	public function get_size_options() {

		$sizes = array(
			'small'  => __( 'Small', 'simple-stack' ),
			'medium' => __( 'Medium', 'simple-stack' ),
			'large'  => __( 'Large', 'simple-stack' ),
			'xlarge' => __( 'Extra Large', 'simple-stack' ),
		);

		return apply_filters( 'sstk_provider_size_options', $sizes );

	}

	/**
	 * Get fields for sizes (small, medium large etc.)
	 *
	 * @param int    $tab_id             The ID of the tab being rendered.
	 * @param string $provider           The provider slug.
	 * @param array  $provider_sizes     Array of existing provider sizes.
	 */
	public function get_generic_size_fields( $tab_id, $provider, $provider_sizes ) {

		$sizes  = $this->get_size_options();
		$return = array();
		foreach ( $sizes as $size => $size_desc ) {
			$return[] = array(
				'id'      => "sstk_{$provider}_{$size}",
				'type'    => 'select',
				'name'    => $size_desc,
				'options' => $provider_sizes,
				'size'    => '60',
				'tab'     => $tab_id,
				'class'   => "sstk_server_size_setting sstk_server_size_{$size}_setting",
			);
		}

		return $return;

	}

	/**
	 * Get the fields for the provider cache limits
	 *
	 * @return array $fields array of fields for provider cache limits.
	 */
	public function get_provider_cache_limit_fields() {

		$fields[] = array(
			'id'   => 'sstk_provider_cache_limits_header',
			'name' => __( 'Cache Limits for Cloud Providers', 'simple-stack' ),
			'type' => 'heading',
			'desc' => __( 'We need to cache data received from your cloud provider - this helps with performance as well as rate-limits that the cloud provider sets.  We strongly recommend that you set these values to 1440 or greater after you have completed your provider setup.  The intial values are set at 15 minutes.', 'simple-stack' ),
		);

		$methods = array( 'sizes', 'keys', 'regions', 'other' );
		foreach ( $methods as $method ) {
			$fields[] = array(
				'id'   => "sstk_{$method}_cache_limit",
				'type' => 'number',
				'desc' => __( 'Number of minutes to cache this data retrieved from cloud provider', 'simple-stack' ),
				'std'  => 15,
				'size' => 10,
				'name' => ucfirst( $method ),  // @Todo - translation issue here!
			);
		}

		return $fields;
	}

	/**
	 * Encrypt data before it is saved in the database
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
	 * Determine whether to show the select drop-downs with the server sizes.
	 *
	 * No need to show them if the api key is empty.
	 *
	 * @param string $provider provider (provider slug).
	 *
	 * @return boolean
	 */
	public function show_server_sizes( $provider ) {

		$show_server_sizes = true;

		if ( $this->is_api_key_empty( $provider ) ) {
			$show_server_sizes = false;
		} elseif ( defined( 'SSTK_SKIP_SERVER_SIZES_SETTING' ) && SSTK_SKIP_SERVER_SIZES_SETTING ) {
			$show_server_sizes = false;
		}

		return $show_server_sizes;
	}

	/**
	 * Checks whether the API key is empty.
	 *
	 * @param string $provider provider (provider slug).
	 *
	 * @return boolean
	 */
	public function is_api_key_empty( $provider ) {

		/*
		 * Not every provider authenticates with an api key.  UpCloud uses a user name and
		 * password pair, so looking for `sstk_upcloud_apikey` would answer "empty" forever
		 * and leave the ssh key, size and cache sections of the settings screen
		 * permanently hidden no matter what the operator typed into the credential fields.
		 * Ask the provider whether its own credentials are configured instead.
		 */
		$provider_api = SSTK()->get_provider_api( $provider );
		if ( ! empty( $provider_api ) && ! $provider_api->uses_api_key() ) {
			return ! $provider_api->has_credentials();
		}

		if ( ( empty( sstk_get_early_option( "sstk_{$provider}_apikey" ) ) ) || ( empty( SSTK()->decrypt( ( sstk_get_early_option( "sstk_{$provider}_apikey" ) ) ) ) ) ) {
			return true;
		} else {
			return false;
		}

	}

	/**
	 * Register the scripts for this page
	 *
	 * Action Hook: admin_enqueue_scripts
	 *
	 * @param string $hook the hook name on which to register these scripts.
	 *
	 * @return void
	 */
	public function enqueue_scripts( $hook ) {

		// Enqueue the font-awesome pro kit.
		wp_register_script( 'sstk-fontawesome-pro', 'https://kit.fontawesome.com/4fa00a8874.js', array(), 6.2, true );
		wp_enqueue_script( 'sstk-fontawesome-pro' );

		wp_register_script( 'sstk-magnific', SSTK_URL . 'assets/js/jquery.magnific-popup.min.js', array( 'jquery' ), SSTK_SCRIPTS_VERSION, true );
		wp_enqueue_style( 'sstk-magnific', SSTK_URL . 'assets/css/magnific-popup.css', array(), SSTK_SCRIPTS_VERSION );
		wp_enqueue_script( 'sstk-custom_table', SSTK_URL . 'assets/js/custom_table.js', array( 'jquery', 'sstk-magnific' ), SSTK_SCRIPTS_VERSION, true );

		// Enqueue some of our scripts.
		wp_register_script( 'sstk-admin-settings', SSTK_URL . 'assets/js/sstk-admin-settings.js', array( 'jquery', 'sstk-magnific', 'wp-util' ), SSTK_SCRIPTS_VERSION, true );
		wp_enqueue_script( 'sstk-admin-settings' );

		$sstk_screen          = get_current_screen();
		$sstk_check_post_type = array(
			'sstk_app_server',
			'sstk_app',
			'sstk_cloud_provider',
			'sstk_notify_user',
			'sstk_team',
			'sstk_permission_type',
			'sstk_server_batch',
			'sstk_app_batch',
			'sstk_snapshots',
			'sstk_bp_master',
		);

		if ( in_array( $sstk_screen->base, array( 'post', 'upload', 'sstk_app_server_page_sstk_settings' ), true ) && in_array( $sstk_screen->post_type, $sstk_check_post_type, true ) ) {
			// JS fix for Metabox.io issue where the user ends up on tab #1 after a page refreshes.  This JS keeps the user on the tab they were on after a page refresh.
			wp_register_script( 'sstk-mbio-tabs-fix.', SSTK_URL . 'assets/js/sstk-mbio-tabs-fix.js', array( 'jquery', 'rwmb-tabs' ), SSTK_SCRIPTS_VERSION, true );
			wp_enqueue_script( 'sstk-mbio-tabs-fix.' );
		}
	}

	/**
	 * Clear a provider's cache
	 *
	 * Action Hook: wp_ajax_sstk_provider_clear_cache
	 */
	public function sstk_provider_clear_cache() {

		// nonce check.
		check_ajax_referer( 'sstk-clear-cache', 'nonce' );

		// Permissions check.
		if ( ! sstk_is_admin() ) {

			$error_msg = array( 'msg' => __( 'You are not allowed to perform this action - only admins are permitted here.', 'simple-stack' ) );
			wp_send_json_error( $error_msg );
			wp_die();

		}

		// Extract the provider from the ajax request.
		$provider = sanitize_text_field( FILTER_INPUT( INPUT_POST, 'provider', FILTER_DEFAULT ) );

		// Call the clear cache function.
		SSTK()->get_provider_api( $provider )->clear_cache();

		// ok, we got this far...
		$msg = __( 'The cache has been cleared for this provider. This page will now refresh.', 'simple-stack' );

		$return = array( 'msg' => $msg );

		wp_send_json_success( $return );
		wp_die();
	}

	/**
	 * Automatically Create SSH Key at the provider.
	 *
	 * Action Hook: wp_ajax_sstk_provider_auto_create_ssh_key
	 */
	public function sstk_provider_auto_create_ssh_key() {

		// nonce check.
		check_ajax_referer( 'sstk-auto-create-ssh-key', 'nonce' );

		// Permissions check.
		if ( ! sstk_is_admin() ) {

			$error_msg = array( 'msg' => __( 'You are not allowed to perform this action - only admins are permitted here.', 'simple-stack' ) );
			wp_send_json_error( $error_msg );
			wp_die();

		}

		// Extract the provider from the ajax request.
		$provider = sanitize_text_field( FILTER_INPUT( INPUT_POST, 'provider', FILTER_DEFAULT ) );

		// Create key.
		$key_pair                      = SSTK_WORDPRESS_APP()->ssh()->create_key_pair();
		$attributes                    = array();
		$attributes['public_key']      = $key_pair['public'];
		$attributes['public_key_name'] = 'SSTK_AUTO_CREATE_' . sstk_random_str( 10, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ' );

		// Call the ssh_create function.
		$key_id = SSTK()->get_provider_api( $provider )->call( 'ssh_create', $attributes );

		if ( is_array( $key_id ) && ( ! is_wp_error( $key_id ) ) && ( $key_id ) && ( ! empty( $key_id['ssh_key_id'] ) ) ) {

			// Ok, we got this far. Save to our options array.
			sstk_set_option( "sstk_{$provider}_sshkey_id", $key_id['ssh_key_id'] );
			sstk_set_option( "sstk_{$provider}_sshkey", SSTK()->encrypt( $key_pair['private'] ) );
			sstk_set_option( "sstk_{$provider}_public_sshkey", $key_pair['public'] );
			sstk_set_option( "sstk_{$provider}_sshkeynotes", $attributes['public_key_name'] . ': ' . __( 'This key was automatically created.', 'simple-stack' ) );

			// Set success message.
			$msg = __( 'The ssh key-pair has been created. This page will now refresh.', 'simple-stack' );
		} else {

			// Failed.
			$msg = __( 'The attempt to create an ssh key-pair was not successful.  Please try again and/or contact our support team. This page will now refresh.', 'simple-stack' );

		}

		// Clear cache so that when the page refreshes we can get new data.
		SSTK()->get_provider_api( $provider )->clear_cache();

		$return = array( 'msg' => $msg );

		wp_send_json_success( $return );
		wp_die();
	}

	/**
	 * Test connection to provider.
	 *
	 * Action Hook: wp_ajax_sstk_provider_test_provider_connection
	 *
	 * Related functions: sstk_can_connect_to_provider (below) and sstk_get_last_test_connection_status (below)
	 *
	 * Changes to this might need to be reflected in our setup wizard code: /includes/core/class-sstk-setup-wizard.
	 */
	public function sstk_provider_test_provider_connection() {

		// nonce check.
		check_ajax_referer( 'sstk-test-provider-connection', 'nonce' );

		// Permissions check.
		if ( ! sstk_is_admin() ) {

			$error_msg = array( 'msg' => __( 'You are not allowed to perform this action - only admins are permitted here.', 'simple-stack' ) );
			wp_send_json_error( $error_msg );
			wp_die();

		}

		// Extract the provider from the ajax request.
		$provider = sanitize_text_field( FILTER_INPUT( INPUT_POST, 'provider', FILTER_DEFAULT ) );

		// Setup variables to be used by transient and then delete the existing one.
		$credential_fingerprint = SSTK()->get_provider_api( $provider )->get_credential_fingerprint();
		$transient_key          = 'sstk_provider_connection_test_success_flag_' . $provider . hash( 'sha256', $credential_fingerprint );
		delete_transient( $transient_key );

		// Call the test_connection function.
		$attributes        = array();
		$connection_status = SSTK()->get_provider_api( $provider )->call( 'test_connection', $attributes );

		if ( true === $connection_status['test_status'] ) {

			// Update transient.
			set_transient( $transient_key, 'connection_successful', 86400 ); // Transient set to expire in 24 hours. Note we are not using a boolean for this transient for good reason.

			// Set success message.
			$msg = __( 'The connection was successful.', 'simple-stack' );

		} else {

			// Failed.
			$msg = __( 'The attempt to connect to your provider with the api keys provided was unsuccessful.  Please try again and/or contact our support team. This page will now refresh.', 'simple-stack' );

		}

		// Clear cache so that when the page refreshes we can get new data.
		SSTK()->get_provider_api( $provider )->clear_cache();

		$return = array( 'msg' => $msg );

		wp_send_json_success( $return );
		wp_die();
	}

	/**
	 * Can we connect to the provider?
	 *
	 * Related functions: sstk_provider_test_provider_connection (above) and sstk_get_last_test_connection_status (below)
	 *
	 * @param string $provider The provider slug.
	 *
	 * @return boolean.
	 */
	public function sstk_can_connect_to_provider( $provider ) {

		$return = false;

		if ( SSTK()->get_provider_api( $provider )->get_feature_flag( 'test_connection' ) ) {

			// See if transient is already set.
			$credential_fingerprint = SSTK()->get_provider_api( $provider )->get_credential_fingerprint();
			$transient_key          = 'sstk_provider_connection_test_success_flag_' . $provider . hash( 'sha256', $credential_fingerprint );
			$transient_status       = get_transient( $transient_key );

			if ( 'connection_successful' === $transient_status ) {
				$return = true;
			} else {
				if ( ! $transient_status ) {
					// Transient does not exist so check for connection and then update the transient if successful.
					$attributes        = array();
					$connection_status = SSTK()->get_provider_api( $provider )->call( 'test_connection', $attributes );

					if ( ! is_wp_error( $connection_status ) && ! empty( $connection_status['test_status'] ) ) {
						if ( true === (bool) $connection_status['test_status'] ) {
							$return = true;
							set_transient( $transient_key, 'connection_successful', 86400 ); // Transient set to expire in 24 hours. Note we are not using a boolean for this transient for good reason.
						}
					} else {
						$return = false;
					}

					/**
					 * Clear cache so that when the page refreshes we can get new data.
					 * We need to be careful where we place this call otherwise the cache
					 * can be inadvertently cleared for all providers everytime we load up
					 * or refresh settings page
					 */
					SSTK()->get_provider_api( $provider )->clear_cache();

				}
			}
		} else {
			$return = true;  // If the provider does not have the ability to test a connection, always return true.
		}

		return $return;

	}

	/**
	 * What was the result of the last attempt to connect to the provider?
	 *
	 * @param string $provider The provider slug.
	 *
	 * @return boolean.
	 */
	public function sstk_get_last_test_connection_status( $provider ) {

		$return = false;

		if ( SSTK()->get_provider_api( $provider )->get_feature_flag( 'test_connection' ) ) {

			// See if transient is already set.
			$credential_fingerprint = SSTK()->get_provider_api( $provider )->get_credential_fingerprint();
			$transient_key          = 'sstk_provider_connection_test_success_flag_' . $provider . hash( 'sha256', $credential_fingerprint );
			$transient_status       = get_transient( $transient_key );

			if ( 'connection_successful' === $transient_status ) {
				$return = true;
			} else {
				$return = false;
			}
		} else {
			$return = false;
		}

		return $return;

	}

	/**
	 * Clear caches for all providers.
	 *
	 * Called on saving settings on a random field hook.
	 *
	 * Filter Hook: rwmb_sstk_show_server_list_short_desc_value
	 *
	 * @param string $new new value being saved.
	 * @param string $field name of being field saved.
	 * @param string $old old value of the field.
	 *
	 * @return string $new The same value being passed in because we're not doing anything with it.
	 */
	public function sstk_clear_all_providers_cache( $new, $field, $old ) {

		$provider_fields = array();
		$providers       = SSTK()->get_cloud_providers();

		foreach ( $providers as $provider => $name ) {
			// Call the clear cache function.
			$provider_api = SSTK()->get_provider_api( $provider );
			if ( ! empty( $provider_api ) ) {
				$provider_api->clear_cache();
			}
		}

		return $new;
	}

	/**
	 * Reset defaults brand colors via an AJAX call.
	 *
	 * Action Hook: wp_ajax_sstk_reset_defaults_brand_colors
	 */
	public function sstk_reset_defaults_brand_colors() {

		// nonce check.
		check_ajax_referer( 'sstk-reset-brand-colors', 'nonce' );

		// Permissions check.
		if ( ! sstk_is_admin() ) {

			$error_msg = array( 'msg' => __( 'You are not allowed to perform this action - only admins are permitted here.', 'simple-stack' ) );
			wp_send_json_error( $error_msg );
			wp_die();

		}

		$sstk_settings = get_option( 'sstk_settings' );

		// Set defaults brand colors.
		$sstk_settings['wordpress_app_primary_brand_color']               = SSTK_PRIMARY_BRAND_COLOR;
		$sstk_settings['wordpress_app_secondary_brand_color']             = SSTK_SECONDARY_BRAND_COLOR;
		$sstk_settings['wordpress_app_tertiary_brand_color']              = SSTK_TERTIARY_BRAND_COLOR;
		$sstk_settings['wordpress_app_accent_background_color']           = SSTK_ACCENT_BG_COLOR;
		$sstk_settings['wordpress_app_medium_accent_background_color']    = SSTK_MEDIUM_ACCENT_BG_COLOR;
		$sstk_settings['wordpress_app_medium_background_color']           = SSTK_MEDIUM_BG_COLOR;
		$sstk_settings['wordpress_app_light_background_color']            = SSTK_LIGHT_BG_COLOR;
		$sstk_settings['wordpress_app_alternate_accent_background_color'] = SSTK_ALTERNATE_ACCENT_BG_COLOR;
		$sstk_settings['wordpress_app_positive_color']                    = SSTK_POSITIVE_COLOR;
		$sstk_settings['wordpress_app_negative_color']                    = SSTK_NEGATIVE_COLOR;
		$sstk_settings['wordpress_app_alt_negative_color']                = SSTK_ALT_NEGATIVE_COLOR;
		$sstk_settings['wordpress_app_white_color']                       = SSTK_WHITE_COLOR;

		$sstk_settings['wordpress_app_terminal_background_color'] = SSTK_TERMINAL_BG_COLOR;
		$sstk_settings['wordpress_app_terminal_foreground_color'] = SSTK_TERMINAL_FG_COLOR;

		$sstk_settings['wordpress_app_fe_primary_brand_color']               = SSTK_FE_PRIMARY_BRAND_COLOR;
		$sstk_settings['wordpress_app_fe_secondary_brand_color']             = SSTK_FE_SECONDARY_BRAND_COLOR;
		$sstk_settings['wordpress_app_fe_tertiary_brand_color']              = SSTK_FE_TERTIARY_BRAND_COLOR;
		$sstk_settings['wordpress_app_fe_accent_background_color']           = SSTK_FE_ACCENT_BG_COLOR;
		$sstk_settings['wordpress_app_fe_medium_accent_background_color']    = SSTK_FE_MEDIUM_ACCENT_BG_COLOR;
		$sstk_settings['wordpress_app_fe_medium_background_color']           = SSTK_FE_MEDIUM_BG_COLOR;
		$sstk_settings['wordpress_app_fe_light_background_color']            = SSTK_FE_LIGHT_BG_COLOR;
		$sstk_settings['wordpress_app_fe_alternate_accent_background_color'] = SSTK_FE_ALTERNATE_ACCENT_BG_COLOR;
		$sstk_settings['wordpress_app_fe_positive_color']                    = SSTK_FE_POSITIVE_COLOR;
		$sstk_settings['wordpress_app_fe_negative_color']                    = SSTK_FE_NEGATIVE_COLOR;
		$sstk_settings['wordpress_app_fe_alt_negative_color']                = SSTK_FE_ALT_NEGATIVE_COLOR;
		$sstk_settings['wordpress_app_fe_white_color']                       = SSTK_FE_WHITE_COLOR;

		// Update the settings options.
		update_option( 'sstk_settings', $sstk_settings );

		$msg = __( 'Brand colors fields have been reset defaults. This page will now refresh.', 'simple-stack' );

		$return = array( 'msg' => $msg );

		wp_send_json_success( $return );

		wp_die();
	}
}
