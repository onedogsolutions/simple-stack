<?php
/** Modified for Simple Stack by One Dog Solutions on 2026-11-23. */
/**
 * Monit Healing Tab
 *
 * @package simple-stack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SSTK_WORDPRESS_TABS_SERVER_MONIT
 */
class SSTK_WORDPRESS_TABS_SERVER_MONIT extends SSTK_WORDPRESS_TABS {

	/**
	 * SSTK_WORDPRESS_TABS_PHP constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_filter( "sstk_server_{$this->get_app_name()}_get_tabnames", array( $this, 'get_tab' ), 10, 2 );
		add_filter( "sstk_server_{$this->get_app_name()}_get_tabs", array( $this, 'get_tab_fields' ), 10, 2 );
		add_filter( "sstk_server_{$this->get_app_name()}_tab_action", array( $this, 'tab_action_server' ), 10, 3 );  // This filter has not been defined and called yet in classs-wordpress-app and might never be because we're using the one below.
		add_filter( "sstk_app_{$this->get_app_name()}_tab_action", array( $this, 'tab_action' ), 10, 3 );  // This filter says 'sstk_app' because we're using the same functions for server details ajax tabs and app details ajax tabs.

		add_action( "sstk_command_{$this->get_app_name()}_completed", array( $this, 'command_completed' ), 10, 2 );
	}

	/**
	 * Called when a command completes.
	 *
	 * Action Hook: sstk_command_{$this->get_app_name()}_completed
	 *
	 * @param int    $id     The postID of the server cpt.
	 * @param string $name   The name of the command.
	 */
	public function command_completed( $id, $name ) {

		if ( get_post_type( $id ) !== 'sstk_app_server' ) {
			return;
		}

		// remove the 'temporary' meta so that another attempt will run if necessary.
		delete_post_meta( $id, "sstk_app_{$this->get_app_name()}_action_status" );  // Should really only exist on an app.
		delete_post_meta( $id, "sstk_app_{$this->get_app_name()}_action" );  // Should really only exist on an app.
		delete_post_meta( $id, "sstk_app_{$this->get_app_name()}_action_args" );  // Should really only exist on an app.
		delete_post_meta( $id, "sstk_server_{$this->get_app_name()}_action_status" );  // Should really only exist on a server.
		delete_post_meta( $id, "sstk_server_{$this->get_app_name()}_action" );  // Should really only exist on a server.
		delete_post_meta( $id, "sstk_server_{$this->get_app_name()}_action_args" );  // Should really only exist on a server.

	}

	/**
	 * Returns a string that can be used as the unique name for this tab.
	 */
	public function get_tab_slug() {
		return 'monit-healing';
	}

	/**
	 * Returns a string that is the name of a view TEAM permission required to view this tab.
	 */
	public function get_view_tab_team_permission_slug() {
		return 'view_wpapp_server_monit_tab';
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
				'label' => __( 'Healing', 'simple-stack' ),
				'icon'  => 'fas fa-heart-rate',
			);
		}
		return $tabs;
	}

	/**
	 * Checks whether or not the user can view the current tab.
	 *
	 * @param int $id The post ID of the server.
	 *
	 * @return boolean
	 */
	public function get_tab_security( $id ) {
		return ( true === $this->sstk_wpapp_server_user_can( $this->get_view_tab_team_permission_slug(), $id ) && true === $this->sstk_can_author_view_server_tab( $id, $this->get_tab_slug() ) );
	}

	/**
	 * Gets the fields to be shown in the MONIT/HEALING tab.
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
	 * @param int    $id The post ID of the server.
	 *
	 * @return mixed    $result The default value of the result.
	 */
	public function tab_action( $result, $action, $id ) {

		/* Verify that the user is even allowed to view the server before proceeding to do anything else */
		if ( ! $this->sstk_user_can_view_wp_server( $id ) ) {
			return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'simple-stack' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
		}

		/* Now verify that the user can perform actions on this screen, assuming that they can view the server */
		$valid_actions = array( 'monit-email-alerts-load-defaults', 'monit-install', 'monit-toggle-ssl', 'monit-remove', 'monit-metas-add', 'monit-metas-remove', 'monit-toggle-webserver', 'monit-toggle-mysql', 'monit-toggle-memcached', 'monit-toggle-redis', 'monit-toggle-filesys', 'monit-toggle-all-on', 'monit-toggle-all-off', 'monit-update-email', 'monit-toggle-status' );
		if ( ! in_array( $action, $valid_actions, true ) && str_starts_with( $action, 'monit-' ) ) {
			return new \WP_Error( __( 'Unsupported Monit action.', 'simple-stack' ) );
		}
		if ( in_array( $action, $valid_actions, true ) && ! $this->get_tab_security( $id ) ) {
			return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'simple-stack' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
		}

		if ( $this->get_tab_security( $id ) ) {
			switch ( $action ) {
				case 'monit-email-alerts-load-defaults':
					$action = 'monit_email_alerts_load_defaults';
					$result = $this->sstk_monit_email_alerts_load_defaults( $id, $action );
					break;
				case 'monit-install':
					$action = 'install_monit';
					$result = $this->manage_monit( $id, $action );
					break;
				case 'monit-toggle-ssl':
					$result = $this->manage_monit( $id, $action );
					break;
				case 'monit-remove':
					$action = 'remove_monit';
					$result = $this->manage_monit( $id, $action );
					break;
				case 'monit-metas-add':
					$result = $this->manage_monit( $id, $action );
					break;
				case 'monit-metas-remove':
					$result = $this->manage_monit( $id, $action );
					break;
				case 'monit-toggle-webserver':
				case 'monit-toggle-mysql':
				case 'monit-toggle-memcached':
				case 'monit-toggle-redis':
				case 'monit-toggle-filesys':
				case 'monit-toggle-all-on':
				case 'monit-toggle-all-off':
					$result = $this->manage_monit( $id, $action );
					break;
				case 'monit-update-email':
					$result = $this->manage_monit( $id, $action );
					break;
				case 'monit-toggle-status':
					$result = $this->manage_monit( $id, $action );
					break;
			}
		}

		return $result;
	}

	/**
	 * Gets the actions to be shown in the MONIT/HEALING tab.
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	public function get_actions( $id ) {

		return $this->get_monit_fields( $id );

	}

	/**
	 * Gets the fields for the services to be shown in the MONIT/HEALING tab in the server details screen.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_monit_fields( $id ) {

		// Set up metabox items.
		$actions = array();

		// is monit installed?
		$monit_status = get_post_meta( $id, 'sstk_wpapp_monit_installed', true );

		if ( empty( $monit_status ) ) {
			// Monit is not installed.
			$desc  = __( 'Monit is a lightweight program that can monitor your server and take simple actions to automatically heal your server and keep it running.  However, it is not currently installed on the server.<br />  To install it, fill out the domain name and email settings below and then click the install button.', 'simple-stack' );
			$desc .= '<br />' . '<a href=" https://github.com/onedogsolutions/simple-stack/blob/main/docs/SIMPLESTACK.md" target="_blank">' . __( 'View Documentation', 'simple-stack' ) . '</a>';

			$actions['monit-header'] = array(
				'label'          => __( 'Monit', 'simple-stack' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => $desc,
				),
			);

			// Since Monit is not installed show only install button and collect information related to the installation.
			$actions['monit-domain']          = array(
				'label'          => __( 'Domain', 'simple-stack' ),
				'type'           => 'text',
				'raw_attributes' => array(
					'std'            => '',
					'desc'           => sprintf( __( 'Monit needs to be accessed via a domain or subdomain that points to the server - <b>%1$s</b> on ip <b>%2$s</b>.', 'simple-stack' ), $this->get_server_name( $id ), $this->get_ipv4_address( $id ) ),
					// the key of the field (the key goes in the request).
					'data-sstk-name' => 'monit_domain',
				),
			);
			$actions['monit-basic-auth-user'] = array(
				'label'          => __( 'User Name', 'simple-stack' ),
				'type'           => 'text',
				'raw_attributes' => array(
					'desc'           => __( 'User name to use to log into the Monit dashboard', 'simple-stack' ),
					'size'           => 60,
					// the key of the field (the key goes in the request).
					'data-sstk-name' => 'monit_auth_user',
					'spellcheck'     => 'false',
				),

			);

			$actions['monit-basic-auth-pw'] = array(
				'label'          => __( 'Password', 'simple-stack' ),
				'type'           => 'password',
				'raw_attributes' => array(
					'desc'           => __( 'Password to use when accessing the Monit dashboard', 'simple-stack' ),
					'tooltip'        => __( 'Please use alphanumeric characters only - otherwise Monit will likely fail to start with a silent syntax error.', 'simple-stack' ),
					'size'           => 60,
					// the key of the field (the key goes in the request).
					'data-sstk-name' => 'monit_auth_pass',
					'spellcheck'     => 'false',
				),
			);

			// get any existing email gateway data stored and use those as the defaults for the monit email stuff.
			$gateway_data = sstk_maybe_unserialize( get_post_meta( $id, 'sstk_wpapp_email_gateway', true ) );
			$gateway_data = is_array( $gateway_data ) ? $gateway_data : array();
			if ( ! empty( $gateway_data ) ) {
				$monit_smtp_server = (string) ( $gateway_data['smtp_server'] ?? '' );
				$monit_smtp_port   = ''; // port isn't stored separately for the gateway so default is blank.
				$monit_smtp_user      = (string) ( $gateway_data['smtp_user'] ?? '' );
				$monit_smtp_pass_set  = ! empty( $gateway_data['smtp_pass'] );
				$monit_alert_email    = '';  // email address where alerts will be sent - not available from gateway data...
			} else {
				$monit_smtp_server = '';
				$monit_smtp_port   = ''; // port isn't stored separately for the gateway so default is blank.
				$monit_smtp_user     = '';
				$monit_smtp_pass_set = false;
				$monit_alert_email   = '';
			}

			// Get email fields - we need a separate function because we'll be using the same fields elsewhere later after monit is installed.
			$email_actions = $this->get_email_fields( $id, $monit_smtp_server, $monit_smtp_port, $monit_smtp_user, $monit_smtp_pass_set, $monit_alert_email );
			$actions       = array_merge( $actions, $email_actions );

			// m/monit url/address.
			$actions['monit-mmonit-header'] = array(
				'label'          => __( 'M/Monit', 'simple-stack' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => __( 'M/Monit is a premium offering of Monit that allow you to connect all your servers to a central Monit Monitoring dashboard with full support for mobile displays. If you have such a server, enter its url below.', 'simple-stack' ),
				),
			);
			$actions['monit-mmonit-domain'] = array(
				'label'          => __( 'M/Monit Server URL (Optional)', 'simple-stack' ),
				'type'           => 'password',
				'raw_attributes' => array(
					'std'            => '',
					'desc'           => __( 'Enter the complete M/Monit collector URL, including its credentials. It is stored encrypted and is never displayed again.', 'simple-stack' ),
					// the key of the field (the key goes in the request).
					'data-sstk-name' => 'monit_mmonit_server',
				),
			);

			// The install button.
			$actions['monit-install-header'] = array(
				'label'          => __( 'Install Monit', 'simple-stack' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => __( 'After filling out the fields in the three sections above, just click the button below to get the MONIT installation started.', 'simple-stack' ),
				),
			);

			$actions['monit-install'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'                 => __( 'Install Monit', 'simple-stack' ),
					// make sure we give the user a confirmation prompt.
					'confirmation_prompt' => __( 'Are you sure you would like to install the Monit service?', 'simple-stack' ),
					'data-sstk-fields'    => json_encode( array( '#sstk_app_action_monit-domain', '#sstk_app_action_monit-basic-auth-user', '#sstk_app_action_monit-basic-auth-pw', '#sstk_app_action_monit-smtp-server', '#sstk_app_action_monit-smtp-port', '#sstk_app_action_monit-smtp-user', '#sstk_app_action_monit-smtp-password', '#sstk_app_action_monit-alert-email', '#sstk_app_action_monit-mmonit-domain' ) ),
				),
				'type'           => 'button',
			);

		}

		if ( 'yes' === $monit_status ) {
			/* Monit is installed and active */
			$desc  = __( 'Monit is installed and monitoring your server health.', 'simple-stack' );
			$desc .= '<br />' . '<a href=" https://github.com/onedogsolutions/simple-stack/blob/main/docs/SIMPLESTACK.md" target="_blank">' . __( 'View Documentation', 'simple-stack' ) . '</a>';

			$actions['monit-header'] = array(
				'label'          => __( 'Monit', 'simple-stack' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => $desc,
				),
			);

			// Construct a credential-free link; Monit will prompt for its basic-auth credentials.
			$monit_scheme = 'on' === get_post_meta( $id, 'sstk_wpapp_monit_ssl', true ) ? 'https' : 'http';
			$monit_domain = (string) get_post_meta( $id, 'sstk_wpapp_monit_domain', true );
			$monit_url    = esc_url( $monit_scheme . '://' . $monit_domain );
			$launch       = sprintf( '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>', $monit_url, esc_html__( 'Launch Monit', 'simple-stack' ) );

			$actions['monit-launch'] = array(
				'label'          => __( 'Launch Monit', 'simple-stack' ),
				'type'           => 'custom_html',
				'raw_attributes' => array(
					'std'  => $launch,
					'desc' => sprintf( __( 'Launch Monit located at %s. Credentials are never displayed here.', 'simple-stack' ), $monit_url ),
				),
			);

			/* Monit SSL Options */
			$desc = __( 'Activate or deactivate ssl.' );

			$actions['monit-ssl-options'] = array(
				'label'          => __( 'Monit SSL Options', 'simple-stack' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => $desc,
				),
			);

			// What's the current SSL status?
			$monit_ssl = get_post_meta( $id, 'sstk_wpapp_monit_ssl', true );
			if ( empty( $monit_ssl ) ) {
				$monit_ssl = 'off';
			}

			// Set confirmation prompt based on current ssl status.
			$confirmation_prompt = '';
			if ( 'on' === $monit_ssl ) {
				$confirmation_prompt = __( 'Are you sure you would like to disable SSL?', 'simple-stack' );
			} else {
				$confirmation_prompt = __( 'Are you sure you would like to enable SSL?', 'simple-stack' );
			}

			if ( 'on' <> $monit_ssl ) {
				$actions['monit-ssl-email'] = array(
					'label'          => __( 'Email', 'simple-stack' ),
					'type'           => 'text',
					'raw_attributes' => array(
						'desc'           => __( 'Email address to use for SSL renewal notifications', 'simple-stack' ),
						'size'           => 60,
						// the key of the field (the key goes in the request).
						'data-sstk-name' => 'monit_ssl_email',
					),
				);
			}

			$actions['monit-toggle-ssl'] = array(
				'label'          => __( 'SSL Status', 'simple-stack' ),
				'raw_attributes' => array(
					'on_label'            => __( 'Enabled', 'simple-stack' ),
					'off_label'           => __( 'Disabled', 'simple-stack' ),
					'std'                 => $monit_ssl === 'on',
					'desc'                => __( 'Enable or disable SSL.', 'simple-stack' ),
					'tooltip'             => __( 'Turning this on will result in an attempt to obtain a certificate from LETSEncrypt.  For this to be successful your DNS must be pointing to the domain used when you installed Monit. <br />If the LETSEncrypt request fails, check the logs under the SSH LOG menu option. <br />Note that if you attempt to turn on SSL too many times in a row LETSEncrypt will block your domain for a period of time.', 'simple-stack' ),
					// make sure we give the user a confirmation prompt.
					'confirmation_prompt' => $confirmation_prompt,
					'data-sstk-fields'    => json_encode( array( '#sstk_app_action_monit-ssl-email' ) ),
				),
				'type'           => 'switch',
			);
			/* End Monit SSL Options */

			/* Monit is installed and active - provide some options */
			$desc = __( 'Activate or deactivate Monit components.' );

			$actions['monit-options'] = array(
				'label'          => __( 'Monit Options', 'simple-stack' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => $desc,
				),
			);

			/* Enable or disable all components */
			$actions['monit-toggle-all-on']  = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'                 => __( 'Enable Popular', 'simple-stack' ),
					'confirmation_prompt' => __( 'Are you sure you would like to enable the popular Monit components?', 'simple-stack' ),
					'columns'             => 2,
				),
				'type'           => 'button',
			);
			$actions['monit-toggle-all-off'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'                 => __( 'Disable All', 'simple-stack' ),
					'confirmation_prompt' => __( 'Are you sure you would like to disable all MONIT components?', 'simple-stack' ),
					'columns'             => 2,
				),
				'type'           => 'button',

			);

			/**
			 * Monit Webserver Options
			 */

			// Start with what's the current Webserver status?
			$monit_webserver = get_post_meta( $id, 'sstk_wpapp_monit_webserver', true );
			if ( empty( $monit_webserver ) ) {
				$monit_webserver = 'on';
			}

			// Set confirmation prompt based on current webserver status.
			$confirmation_prompt = '';
			if ( 'on' === $monit_webserver ) {
				$confirmation_prompt = __( 'Are you sure you would like to disable Webserver monitoring?', 'simple-stack' );
			} else {
				$confirmation_prompt = __( 'Are you sure you would like to enable Webserver monitoring?', 'simple-stack' );
			}

			$actions['monit-toggle-webserver'] = array(
				'label'          => __( 'Webserver Status', 'simple-stack' ),
				'raw_attributes' => array(
					'on_label'            => __( 'Enabled', 'simple-stack' ),
					'off_label'           => __( 'Disabled', 'simple-stack' ),
					'std'                 => $monit_webserver === 'on',
					'desc'                => __( 'Enable or disable Webserver monitoring.', 'simple-stack' ),
					'confirmation_prompt' => $confirmation_prompt,
				),
				'type'           => 'switch',
			);
			/* End Monit Webserver Options */

			/**
			 * Monit Mariadb/Mysql Options.
			 */

			// Start with What's the current MYSQL status?
			$monit_mysql = get_post_meta( $id, 'sstk_wpapp_monit_mysql', true );
			if ( empty( $monit_mysql ) ) {
				$monit_mysql = 'on';
			}

			// Set confirmation prompt based on current mysql status.
			$confirmation_prompt = '';
			if ( 'on' === $monit_mysql ) {
				$confirmation_prompt = __( 'Are you sure you would like to disable MYSQL monitoring?', 'simple-stack' );
			} else {
				$confirmation_prompt = __( 'Are you sure you would like to enable MYSQL monitoring?', 'simple-stack' );
			}

			$actions['monit-toggle-mysql'] = array(
				'label'          => __( 'MYSQL/MariaDB Status', 'simple-stack' ),
				'raw_attributes' => array(
					'on_label'            => __( 'Enabled', 'simple-stack' ),
					'off_label'           => __( 'Disabled', 'simple-stack' ),
					'std'                 => $monit_mysql === 'on',
					'desc'                => __( 'Enable or disable MYSQL (database) monitoring.', 'simple-stack' ),
					'confirmation_prompt' => $confirmation_prompt,
				),
				'type'           => 'switch',
			);
			/* End Monit MYSQL Options */

			/**
			 * Monit Memcached Options
			 */

			// Start with What's the current Memcached status?
			$monit_memcached = get_post_meta( $id, 'sstk_wpapp_monit_memcached', true );
			if ( empty( $monit_memcached ) ) {
				$monit_memcached = 'off';
			}

			// Set confirmation prompt based on current memcached status.
			$confirmation_prompt = '';
			if ( 'on' === $monit_memcached ) {
				$confirmation_prompt = __( 'Are you sure you would like to disable Memcached monitoring?', 'simple-stack' );
			} else {
				$confirmation_prompt = __( 'Are you sure you would like to enable Memcached monitoring?', 'simple-stack' );
			}

			$actions['monit-toggle-memcached'] = array(
				'label'          => __( 'Memcached Status', 'simple-stack' ),
				'raw_attributes' => array(
					'on_label'            => __( 'Enabled', 'simple-stack' ),
					'off_label'           => __( 'Disabled', 'simple-stack' ),
					'std'                 => $monit_memcached === 'on',
					'desc'                => __( 'Enable or disable Memcached monitoring.', 'simple-stack' ),
					'confirmation_prompt' => $confirmation_prompt,
				),
				'type'           => 'switch',
			);
			/* End Monit Memcached Options */

			/**
			 * Monit Redis Options
			 */

			// Start with What's the current Redis status?
			$monit_redis = get_post_meta( $id, 'sstk_wpapp_monit_redis', true );
			if ( empty( $monit_redis ) ) {
				$monit_redis = 'off';
			}

			// Set confirmation prompt based on current REDIS status.
			$confirmation_prompt = '';
			if ( 'on' === $monit_redis ) {
				$confirmation_prompt = __( 'Are you sure you would like to disable REDIS monitoring?', 'simple-stack' );
			} else {
				$confirmation_prompt = __( 'Are you sure you would like to enable REDIS monitoring?', 'simple-stack' );
			}

			$actions['monit-toggle-redis'] = array(
				'label'          => __( 'Redis Status', 'simple-stack' ),
				'raw_attributes' => array(
					'on_label'            => __( 'Enabled', 'simple-stack' ),
					'off_label'           => __( 'Disabled', 'simple-stack' ),
					'std'                 => $monit_redis === 'on',
					'desc'                => __( 'Enable or disable REDIS monitoring.', 'simple-stack' ),
					'confirmation_prompt' => $confirmation_prompt,
				),
				'type'           => 'switch',
			);
			/* End Monit REDIS Options */

			/**
			 * Monit FileSystem Options
			 */
			// Start with What's the current FileSystem status?
			$monit_filesys = get_post_meta( $id, 'sstk_wpapp_monit_filesys', true );
			if ( empty( $monit_filesys ) ) {
				$monit_filesys = 'on';
			}

			// Set confirmation prompt based on current filesystem status.
			$confirmation_prompt = '';
			if ( 'on' === $monit_filesys ) {
				$confirmation_prompt = __( 'Are you sure you would like to disable FileSystem monitoring?', 'simple-stack' );
			} else {
				$confirmation_prompt = __( 'Are you sure you would like to enable FileSystem monitoring?', 'simple-stack' );
			}

			$actions['monit-toggle-filesys'] = array(
				'label'          => __( 'FileSystem Status', 'simple-stack' ),
				'raw_attributes' => array(
					'on_label'            => __( 'Enabled', 'simple-stack' ),
					'off_label'           => __( 'Disabled', 'simple-stack' ),
					'std'                 => $monit_filesys === 'on',
					'desc'                => __( 'Enable or disable FileSystem monitoring.', 'simple-stack' ),
					'confirmation_prompt' => $confirmation_prompt,
				),
				'type'           => 'switch',
			);
			/* End Monit FileSystem Options */

			/* Update monit email notifications gateway */
			$monit_gateway               = sstk_maybe_unserialize( get_post_meta( $id, 'sstk_wpapp_monit_email_gateway', true ) );
			$monit_gateway               = is_array( $monit_gateway ) ? $monit_gateway : array();
			$monit_gateway['monit_smtp_user'] = empty( $monit_gateway['monit_smtp_user'] ) ? '' : self::decrypt( $monit_gateway['monit_smtp_user'] );
			$monit_smtp_pass_set        = ! empty( $monit_gateway['monit_smtp_pass'] );
			$monit_gateway_action_fields = $this->get_email_fields( $id, (string) ( $monit_gateway['monit_smtp_server'] ?? '' ), (string) ( $monit_gateway['monit_smtp_port'] ?? '' ), (string) $monit_gateway['monit_smtp_user'], $monit_smtp_pass_set, (string) ( $monit_gateway['monit_alert_email'] ?? '' ) );
			$actions                          = array_merge( $actions, $monit_gateway_action_fields );

			$actions['monit-update-email'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'                 => __( 'Update Email', 'simple-stack' ),
					// make sure we give the user a confirmation prompt.
					'confirmation_prompt' => __( 'Are you sure you would like to update the monit notifications email settings?', 'simple-stack' ),
					'data-sstk-fields'    => json_encode( array( '#sstk_app_action_monit-smtp-server', '#sstk_app_action_monit-smtp-port', '#sstk_app_action_monit-smtp-user', '#sstk_app_action_monit-smtp-password', '#sstk_app_action_monit-alert-email' ) ),
				),
				'type'           => 'button',
			);
			/* End update monit email notifications gateway */

			/* Uninstall / Disable Monit */
			$actions['monit-metas-uninstall'] = array(
				'label'          => __( 'Uninstall or Deactivate Monit', 'simple-stack' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => __( 'Remove or temporarily deactivate Monit', 'simple-stack' ),
				),
			);

			$actions['monit-remove'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'                 => __( 'Uninstall Monit', 'simple-stack' ),
					'desc'                => __( 'This option will completely remove Monit from the server.', 'simple-stack' ), // make sure we give the user a confirmation prompt.
					'confirmation_prompt' => __( 'Are you sure you would like to remove Monit from the server?', 'simple-stack' ),
					'columns'             => 3,
				),
				'type'           => 'button',
			);

			/* Monit status toggle - enable/disable monit */
			$monit_active_status = get_post_meta( $id, 'sstk_wpapp_monit_status', true );
			if ( empty( $monit_active_status ) ) {
				$monit_active_status = 'off';
			}

			$confirmation_prompt = '';
			if ( 'on' === $monit_active_status ) {
				$confirmation_prompt = __( 'Are you sure you would like to disable Monit? This is a temporary deactivation and Monit will be re-enabled when the server restarts.', 'simple-stack' );
			} else {
				$confirmation_prompt = __( 'Are you sure you would like to enable monit monitoring?', 'simple-stack' );
			}

			$actions['monit-toggle-status'] = array(
				'label'          => __( 'Toggle Monit On/Off', 'simple-stack' ),
				'raw_attributes' => array(
					'on_label'            => __( 'Enabled', 'simple-stack' ),
					'off_label'           => __( 'Disabled', 'simple-stack' ),
					'std'                 => $monit_active_status === 'on',
					'desc'                => __( 'Enable or disable monit.', 'simple-stack' ),
					'confirmation_prompt' => $confirmation_prompt,
					'tooltip'             => __( 'Disabling monit is a temporary operation - it will be automatically enabled on the next server reboot.  Uninstall it to disable it permanently.', 'simple-stack' ),
				),
				'type'           => 'switch',
			);
			/* End Monit status toggle */

			/* End uninstall / disable Monit */

		}

		if ( 'no' === $monit_status ) {
			/* Monit is installed but not active */
			$desc = __( 'Monit is a lightweight program that can monitor your server and take simple actions to automatically heal your server and keep it running.  However, it is NOT active on your server at this time.', 'simple-stack' );

			$actions['monit-header'] = array(
				'label'          => __( 'Monit', 'simple-stack' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => $desc,
				),
			);
		}

		/* Toggle Metas */
		$actions['monit-metas-header'] = array(
			'label'          => __( 'Manage Monit Metas', 'simple-stack' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'Sometimes things get out of sync between this dashboard and what is actually on the server.  Use these options to reset things', 'simple-stack' ),
			),
		);

		$actions['monit-metas-remove'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Remove Metas', 'simple-stack' ),
				'desc'                => __( 'This option will reset this dashboard so that it appears that Monit is not installed.', 'simple-stack' ), // make sure we give the user a confirmation prompt.
				'confirmation_prompt' => __( 'Are you sure you would like to remove metas?  This would reset this dashboard so that it appears that Monit is not installed.', 'simple-stack' ),
			),
			'type'           => 'button',
		);

		$actions['monit-metas-add'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Add Metas', 'simple-stack' ),
				'desc'                => __( 'This option will reset this dashboard so that it appears that Monit is installed.', 'simple-stack' ), // make sure we give the user a confirmation prompt.
				'confirmation_prompt' => __( 'Are you sure you would like to remove metas?  This would reset this dashboard so that it appears that Monit is installed on the server.', 'simple-stack' ),
			),
			'type'           => 'button',
		);

		return $actions;

	}

	/**
	 * Gets the email fields to be shown in the MONIT/HEALING tab in the server details screen.
	 *
	 * @param int    $id the post id of the app cpt record.
	 * @param string $monit_smtp_server monit_smtp_server.
	 * @param string $monit_smtp_port monit_smtp_port.
	 * @param string $monit_smtp_user monit_smtp_user.
	 * @param bool   $monit_smtp_pass_set Whether an SMTP password is already stored.
	 * @param string $monit_alert_email monit_alert_email.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	public function get_email_fields( $id, $monit_smtp_server, $monit_smtp_port, $monit_smtp_user, $monit_smtp_pass_set, $monit_alert_email ) {

		// Set up metabox items.
		$actions = array();

		$actions['monit-email-alert-header'] = array(
			'label'          => __( 'Monit Email Alerts', 'simple-stack' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'Configure the email address where you will receive your alerts.', 'simple-stack' ),
			),
		);

		$actions['monit-smtp-server']   = array(
			'label'          => __( 'SMTP Server', 'simple-stack' ),
			'type'           => 'text',
			'raw_attributes' => array(
				'std'            => $monit_smtp_server,
				'desc'           => __( 'Enter the url/address for your outgoing email server - usually in the form of a subdomain.domain.com: - eg: <i>smtp.ionos.com</i>.', 'simple-stack' ),
				'columns'        => 4,
				// the key of the field (the key goes in the request).
				'data-sstk-name' => 'monit_smtp_server',
			),
		);
		$actions['monit-smtp-port']     = array(
			'label'          => __( 'SMTP Server Port', 'simple-stack' ),
			'type'           => 'number',
			'raw_attributes' => array(
				'std'            => $monit_smtp_port,
				'desc'           => __( 'Enter the smtp port - usually one of 465, 587 or 25', 'simple-stack' ),
				'columns'        => 4,
				// the key of the field (the key goes in the request).
				'data-sstk-name' => 'monit_smtp_port',
			),
		);
		$actions['monit-smtp-user']     = array(
			'label'          => __( 'User Name', 'simple-stack' ),
			'type'           => 'text',
			'raw_attributes' => array(
				'std'            => $monit_smtp_user,
				'desc'           => __( 'Your user id for connecting to the smtp server', 'simple-stack' ),
				'columns'        => 4,
				// the key of the field (the key goes in the request).
				'data-sstk-name' => 'monit_smtp_user',
				'spellcheck'     => 'false',
			),
		);
		$actions['monit-smtp-password'] = array(
			'label'          => __( 'Password', 'simple-stack' ),
			'type'           => 'password',
			'raw_attributes' => array(
				'std'            => '',
				'desc'           => $monit_smtp_pass_set ? __( 'Leave blank to keep the stored password.', 'simple-stack' ) : __( 'Your password for connecting to the smtp server', 'simple-stack' ),
				'tooltip'        => __( 'Please use alphanumeric characters only - otherwise Monit will likely fail to start with a silent syntax error.', 'simple-stack' ),
				'columns'        => 4,
				// the key of the field (the key goes in the request).
				'data-sstk-name' => 'monit_smtp_pass',
				'spellcheck'     => 'false',
			),
		);
		$actions['monit-alert-email']   = array(
			'label'          => __( 'Alert Email Address', 'simple-stack' ),
			'type'           => 'text',
			'raw_attributes' => array(
				'std'            => $monit_alert_email,
				'desc'           => __( 'The email address where alerts will be sent.', 'simple-stack' ),
				'columns'        => 4,
				// the key of the field (the key goes in the request).
				'data-sstk-name' => 'monit_alert_email',
				'spellcheck'     => 'false',
			),
		);

		if ( sstk_is_admin() ) {
			$actions['monit-email-alerts-load-defaults'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std'                 => __( 'Load Defaults', 'simple-stack' ),
					'columns'             => 4,
					'confirmation_prompt' => __( 'Are you sure you would like to populate these fields with your global defaults from settings?', 'simple-stack' ),
				),
				'type'           => 'button',
			);
		}

		return $actions;

	}

	/**
	 * Install / manage Monit
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean|object|string    success/failure/other
	 */
	public function manage_monit( $id, $action ) {

		// Get data about the server.
		$instance = $this->get_server_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			/* Translators: %s is the action id we're trying to execute. It is usually a string without spaces, not a number. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the server instance details for action %s', 'simple-stack' ), $action ) );
		}

		// Accept only a parameter map and normalize every value before it reaches a shell command.
		$raw_params = isset( $_POST['params'] ) && is_array( $_POST['params'] ) ? wp_unslash( $_POST['params'] ) : array();
		$defaults   = array_fill_keys(
			array(
				'domain',
				'monit_domain',
				'monit_auth_user',
				'monit_auth_pass',
				'user',
				'password',
				'monit_smtp_server',
				'monit_smtp_port',
				'monit_smtp_user',
				'monit_smtp_pass',
				'monit_alert_email',
				'monit_mmonit_server',
				'monit_ssl_email',
				'callback_url',
			),
			''
		);
		$args       = wp_parse_args( $raw_params, $defaults );
		$args       = array_map(
			function ( $value, $key ) {
				if ( ! is_scalar( $value ) ) {
					return '';
				}

				$value = (string) $value;
				if ( 'monit_mmonit_server' === $key ) {
					return esc_url_raw( $value );
				}
				if ( 'monit_ssl_email' === $key ) {
					return sanitize_email( $value );
				}

				return sanitize_text_field( $value );
			},
			$args,
			array_keys( $args )
		);

		// Make sure certain things are present by pulling them from the database.
		if ( ( ! isset( $args['domain'] ) ) || empty( $args['domain'] ) ) {
			if ( ! empty( get_post_meta( $id, 'sstk_wpapp_monit_domain', true ) ) ) {
				$args['domain'] = get_post_meta( $id, 'sstk_wpapp_monit_domain', true );
			}
		}

		// Setup an array of email gateway fields that we'll need in two actions later.
		$email_fields = array( 'monit_smtp_server', 'monit_smtp_port', 'monit_smtp_user', 'monit_smtp_pass', 'monit_alert_email' );

		// Take certain steps based on the type of action.
		switch ( $action ) {
			case 'install_monit':
				// Make sure all three required fields have been provided and return error if not.
				if ( empty( $args['monit_domain'] ) ) {
					return new \WP_Error( __( 'Unable to setup Monit - no domain was provided.', 'simple-stack' ) );
				}
				if ( ! preg_match( '/^[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/i', $args['monit_domain'] ) || false !== strpos( $args['monit_domain'], '..' ) ) {
					return new \WP_Error( __( 'Unable to setup Monit - the domain must be a valid DNS name without a scheme or path.', 'simple-stack' ) );
				}
				$args['domain'] = strtolower( $args['monit_domain'] );

				if ( empty( $args['monit_auth_user'] ) ) {
					return new \WP_Error( __( 'Unable to setup Monit - no user name was provided.', 'simple-stack' ) );
				}
				$args['user'] = $args['monit_auth_user'];

				if ( empty( $args['monit_auth_pass'] ) ) {
					return new \WP_Error( __( 'Unable to setup Monit - no password was provided.', 'simple-stack' ) );
				}
				$args['password'] = $args['monit_auth_pass'];

				// Make sure that the password fields do not contain invalid characters.
				if ( sstk_clean_alpha_numeric_dashes( $args['monit_auth_user'] ) !== $args['monit_auth_user'] ) {
					return new \WP_Error( __( 'Unable to setup monit - the user name must consist of alphanumeric characters only.', 'simple-stack' ) );
				}
				if ( sstk_clean_alpha_numeric_dashes( $args['monit_auth_pass'] ) !== $args['monit_auth_pass'] ) {
					return new \WP_Error( __( 'Unable to setup monit - the password must consist of alphanumeric characters only.', 'simple-stack' ) );
				}
				if ( empty( $args['monit_smtp_pass'] ) ) {
					$gateway_data = sstk_maybe_unserialize( get_post_meta( $id, 'sstk_wpapp_email_gateway', true ) );
					$stored_pass = is_array( $gateway_data ) ? ( $gateway_data['smtp_pass'] ?? '' ) : '';

					if ( empty( $stored_pass ) ) {
						return new \WP_Error( __( 'Unable to setup Monit - no SMTP password was provided.', 'simple-stack' ) );
					}

					$args['monit_smtp_pass'] = self::decrypt( $stored_pass );
					if ( empty( $args['monit_smtp_pass'] ) ) {
						return new \WP_Error( __( 'Unable to setup Monit - the stored SMTP password could not be decrypted. Enter a new password.', 'simple-stack' ) );
					}
				}
				if ( sstk_clean_alpha_numeric_dashes( $args['monit_smtp_pass'] ) !== $args['monit_smtp_pass'] ) {
					return new \WP_Error( __( 'Unable to setup Monit - the email password must consist of alphanumeric characters only.', 'simple-stack' ) );
				}
				if ( ! is_numeric( $args['monit_smtp_port'] ) || (int) $args['monit_smtp_port'] < 1 || (int) $args['monit_smtp_port'] > 65535 ) {
					return new \WP_Error( __( 'Unable to setup Monit - the SMTP port must be between 1 and 65535.', 'simple-stack' ) );
				}
				if ( ! empty( $args['monit_mmonit_server'] ) ) {
					$mmonit_scheme = parse_url( $args['monit_mmonit_server'], PHP_URL_SCHEME );
					if ( ! filter_var( $args['monit_mmonit_server'], FILTER_VALIDATE_URL ) || ! in_array( strtolower( (string) $mmonit_scheme ), array( 'http', 'https' ), true ) ) {
						return new \WP_Error( __( 'Unable to setup Monit - the M/Monit server must be a valid HTTP or HTTPS URL.', 'simple-stack' ) );
					}
				}

				$email_meta = array();
				foreach ( $email_fields as $email_field ) {
					if ( empty( $args[ $email_field ] ) ) {
						return new \WP_Error( __( 'Unable to setup monit - one of the fields required to set up email notifications is blank.', 'simple-stack' ) );
					} else {
						// saving email connection data to an array which we'll then save to the database later.
						$email_meta[ $email_field ] = $args[ $email_field ];
					}
				}

				// callback url...
				$callback_command_name = 'monit_log';
				$args['callback_url']  = $this->get_command_url( $id, $callback_command_name, 'completed' );

				break;

			case 'remove_monit':
				if ( empty( $args['domain'] ) ) {
					return new \WP_Error( __( 'Unable to remove Monit - no domain is stored for this server.', 'simple-stack' ) );
				}
				break;

			case 'monit-toggle-webserver':
				// Action needs to set based on current status of Webserver in the database.
				$monit_webserver = get_post_meta( $id, 'sstk_wpapp_monit_webserver', true );
				if ( empty( $monit_webserver ) || 'on' === $monit_webserver ) {
					$action = 'disable_webserver_monit';
				} else {
					$action = 'enable_webserver_monit';
				}
				break;

			case 'monit-toggle-mysql':
				// Action needs to set based on current status of MYSQL in the database.
				$monit_mysql = get_post_meta( $id, 'sstk_wpapp_monit_mysql', true );
				if ( empty( $monit_mysql ) || 'on' === $monit_mysql ) {
					$action = 'disable_mysql_monit';
				} else {
					$action = 'enable_mysql_monit';
				}
				break;

			case 'monit-toggle-memcached':
				// Action needs to set based on current status of MEMCACHED in the database.
				$monit_memcached = get_post_meta( $id, 'sstk_wpapp_monit_memcached', true );
				if ( 'on' === $monit_memcached ) {
					$action = 'disable_memcached_monit';
				} else {
					$action = 'enable_memcached_monit';
				}
				break;

			case 'monit-toggle-redis':
				// Action needs to set based on current status of REDIS in the database.
				$monit_redis = get_post_meta( $id, 'sstk_wpapp_monit_redis', true );
				if ( 'on' === $monit_redis ) {
					$action = 'disable_redis_monit';
				} else {
					$action = 'enable_redis_monit';
				}
				break;
			case 'monit-toggle-filesys':
				// Action needs to set based on current status of File System in the database.
				$monit_filesys = get_post_meta( $id, 'sstk_wpapp_monit_filesys', true );
				if ( empty( $monit_filesys ) || 'on' === $monit_filesys ) {
					$action = 'disable_filesystem_monit';
				} else {
					$action = 'enable_filesystem_monit';
				}
				break;

			case 'monit-toggle-all-on':
				$action = 'enable_all_monit';
				break;

			case 'monit-toggle-all-off':
				$action = 'disable_all_monit';
				break;

			case 'monit-toggle-ssl':
				// Action needs to set based on current status of SSL in the database.
				$monit_ssl = get_post_meta( $id, 'sstk_wpapp_monit_ssl', true );
				if ( 'on' === $monit_ssl ) {
					$action = 'disable_monit_ssl';
				} else {
					if ( empty( $args['domain'] ) || empty( $args['monit_ssl_email'] ) || ! is_email( $args['monit_ssl_email'] ) ) {
						return new \WP_Error( __( 'Unable to enable SSL for Monit - a valid domain and email address are required.', 'simple-stack' ) );
					}
					$action = 'enable_monit_ssl';
				}
				break;

			case 'monit-update-email':
				$action     = 'update_email_monit';
				$email_meta = array();
				if ( empty( $args['monit_smtp_pass'] ) ) {
					$gateway_data = sstk_maybe_unserialize( get_post_meta( $id, 'sstk_wpapp_monit_email_gateway', true ) );
					$stored_pass = is_array( $gateway_data ) ? ( $gateway_data['monit_smtp_pass'] ?? '' ) : '';

					if ( empty( $stored_pass ) ) {
						return new \WP_Error( __( 'Unable to update the Monit email notifications settings - no SMTP password was provided.', 'simple-stack' ) );
					}

					$args['monit_smtp_pass'] = self::decrypt( $stored_pass );
					if ( empty( $args['monit_smtp_pass'] ) ) {
						return new \WP_Error( __( 'Unable to update the Monit email notifications settings - the stored SMTP password could not be decrypted. Enter a new password.', 'simple-stack' ) );
					}
				}
				foreach ( $email_fields as $email_field ) {
					if ( empty( $args[ $email_field ] ) ) {
						return new \WP_Error( __( 'Unable to update the Monit email notifications settings - one of the required fields is blank.', 'simple-stack' ) );
					}
					$email_meta[ $email_field ] = $args[ $email_field ];
				}
				if ( ! is_numeric( $args['monit_smtp_port'] ) || (int) $args['monit_smtp_port'] < 1 || (int) $args['monit_smtp_port'] > 65535 ) {
					return new \WP_Error( __( 'Unable to update the Monit email notifications settings - the SMTP port must be between 1 and 65535.', 'simple-stack' ) );
				}
				if ( sstk_clean_alpha_numeric_dashes( $args['monit_smtp_pass'] ) !== $args['monit_smtp_pass'] ) {
					return new \WP_Error( __( 'Unable to update the Monit email notifications settings - the email password must consist of alphanumeric characters only.', 'simple-stack' ) );
				}
				break;

			case 'monit-metas-remove':
				// Remove monit metas.
				$this->remove_metas( $id );
				$success = array(
					'msg'     => __( 'Monit metas have been reset. This screen will refresh and you can navigate back to this tab to see new options.', 'simple-stack' ),
					'refresh' => 'yes',
				);
				return $success;
				break;

			case 'monit-metas-add':
				// Add monit metas.
				update_post_meta( $id, 'sstk_wpapp_monit_installed', 'yes' );
				update_post_meta( $id, 'sstk_wpapp_monit_domain', 'no-domain-provided' );
				$success = array(
					'msg'     => __( 'Monit metas have been reset. However, no domain, user or password has been set.  You should now be able to remove Monit if necessary and reinstall to set domain, user id and password. This screen will refresh and you can navigate back to this tab to see new options.', 'simple-stack' ),
					'refresh' => 'yes',
				);
				return $success;
				break;

			case 'monit-toggle-status':
				$monit_active_status = get_post_meta( $id, 'sstk_wpapp_monit_status', true );
				if ( empty( $monit_active_status ) || 'off' === $monit_active_status ) {
					$action = 'activate_monit';
				} else {
					$action = 'deactivate_monit';
				}
				break;

		}

		// Keep exact secrets for log redaction, then escape every argument for the command line.
		$original_args = $args;
		$command_secrets = array_merge(
			sstk_collect_secret_values( $instance ),
			array(
				$original_args['monit_auth_pass'],
				$original_args['monit_smtp_user'],
				$original_args['monit_smtp_pass'],
				$original_args['monit_mmonit_server'],
				$original_args['callback_url'],
			)
		);
		$command_secrets = array_values(
			array_filter(
				array_map(
					function ( $secret ) {
						return is_scalar( $secret ) ? (string) $secret : '';
					},
					$command_secrets
				),
				function ( $secret ) {
					return '' !== $secret;
				}
			)
		);
		$args            = array_map(
			function ( $item ) {
				return escapeshellarg( $item );
			},
			$args
		);

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'monit.txt',
			array_merge(
				$args,
				array(
					'action' => $action,
					'domain' => $args['domain'],
				)
			)
		);

		$log_instance = sstk_redact_secrets( $instance, $command_secrets );
		$log_command  = sstk_redact_secret_text( $run_cmd, $command_secrets );
		do_action( 'sstk_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $log_instance, true ), $log_command ), 'debug', __FILE__, __LINE__, $log_instance, false );

		// Execute the raw command and check only the output belonging to this action.
		$result = $this->execute_ssh(
			'generic',
			$instance,
			array(
				'commands' => $run_cmd,
				'secrets'  => $command_secrets,
			)
		);
		$success = $this->is_ssh_successful( $result, 'monit.txt', $action );
		if ( ! $success ) {
			$error_result = is_wp_error( $result ) ? $result->get_error_message() : (string) $result;
			$error_result = sstk_redact_secret_text( $error_result, $command_secrets );

			return new \WP_Error( sprintf( __( 'Unable to %1$s : %2$s', 'simple-stack' ), $action, $error_result ) );
		} else {

			// Success - update some postmetas and set response message according to action.
			switch ( $action ) {
				case 'install_monit':
					update_post_meta( $id, 'sstk_wpapp_monit_installed', 'yes' );
					update_post_meta( $id, 'sstk_wpapp_monit_status', 'on' );
					update_post_meta( $id, 'sstk_wpapp_monit_webserver', 'on' );
					update_post_meta( $id, 'sstk_wpapp_monit_mysql', 'on' );
					update_post_meta( $id, 'sstk_wpapp_monit_filesys', 'on' );
					update_post_meta( $id, 'sstk_wpapp_monit_domain', $original_args['domain'] );
					update_post_meta( $id, 'sstk_wpapp_monit_user', self::encrypt( $original_args['monit_auth_user'] ) );
					update_post_meta( $id, 'sstk_wpapp_monit_pass', self::encrypt( $original_args['monit_auth_pass'] ) );
					if ( empty( $original_args['monit_mmonit_server'] ) ) {
						delete_post_meta( $id, 'sstk_wpapp_monit_mmonit_server' );
					} else {
						update_post_meta( $id, 'sstk_wpapp_monit_mmonit_server', self::encrypt( $original_args['monit_mmonit_server'] ) );
					}
					$email_meta['monit_smtp_user'] = self::encrypt( $email_meta['monit_smtp_user'] );
					$email_meta['monit_smtp_pass'] = self::encrypt( $email_meta['monit_smtp_pass'] );
					update_post_meta( $id, 'sstk_wpapp_monit_email_gateway', $email_meta );
					$success = array(
						'msg'     => __( 'Monit has been installed. This screen will refresh and you can navigate back to this tab to see new options.', 'simple-stack' ),
						'refresh' => 'yes',
					);
					break;

				case 'enable_webserver_monit':
					update_post_meta( $id, 'sstk_wpapp_monit_webserver', 'on' );
					$success = array(
						'msg'     => __( 'Webserver monitoring has been enabled for Monit.', 'simple-stack' ),
						'refresh' => 'yes',
					);
					break;

				case 'disable_webserver_monit':
					update_post_meta( $id, 'sstk_wpapp_monit_webserver', 'off' );
					$success = array(
						'msg'     => __( 'Webserver monitoring has been disabled for Monit.', 'simple-stack' ),
						'refresh' => 'yes',
					);
					break;

				case 'enable_mysql_monit':
					update_post_meta( $id, 'sstk_wpapp_monit_mysql', 'on' );
					$success = array(
						'msg'     => __( 'MYSQL monitoring has been enabled for Monit.', 'simple-stack' ),
						'refresh' => 'yes',
					);
					break;

				case 'disable_mysql_monit':
					update_post_meta( $id, 'sstk_wpapp_monit_mysql', 'off' );
					$success = array(
						'msg'     => __( 'MYSQL monitoring has been disabled for Monit.', 'simple-stack' ),
						'refresh' => 'yes',
					);
					break;

				case 'enable_memcached_monit':
					update_post_meta( $id, 'sstk_wpapp_monit_memcached', 'on' );
					$success = array(
						'msg'     => __( 'MEMCACHED monitoring has been enabled for Monit.', 'simple-stack' ),
						'refresh' => 'yes',
					);
					break;

				case 'disable_memcached_monit':
					update_post_meta( $id, 'sstk_wpapp_monit_memcached', 'off' );
					$success = array(
						'msg'     => __( 'MEMCACHED monitoring has been disabled for Monit.', 'simple-stack' ),
						'refresh' => 'yes',
					);
					break;

				case 'enable_redis_monit':
					update_post_meta( $id, 'sstk_wpapp_monit_redis', 'on' );
					$success = array(
						'msg'     => __( 'REDIS monitoring has been enabled for Monit.', 'simple-stack' ),
						'refresh' => 'yes',
					);
					break;

				case 'disable_redis_monit':
					update_post_meta( $id, 'sstk_wpapp_monit_redis', 'off' );
					$success = array(
						'msg'     => __( 'REDIS monitoring has been disabled for Monit.', 'simple-stack' ),
						'refresh' => 'yes',
					);
					break;

				case 'enable_filesystem_monit':
					update_post_meta( $id, 'sstk_wpapp_monit_filesys', 'on' );
					$success = array(
						'msg'     => __( 'File System monitoring has been enabled for Monit.', 'simple-stack' ),
						'refresh' => 'yes',
					);
					break;

				case 'disable_filesystem_monit':
					update_post_meta( $id, 'sstk_wpapp_monit_filesys', 'off' );
					$success = array(
						'msg'     => __( 'File system monitoring has been disabled for Monit.', 'simple-stack' ),
						'refresh' => 'yes',
					);
					break;

				case 'enable_all_monit':
					update_post_meta( $id, 'sstk_wpapp_monit_webserver', 'on' );
					update_post_meta( $id, 'sstk_wpapp_monit_mysql', 'on' );
					update_post_meta( $id, 'sstk_wpapp_monit_filesys', 'on' );
					$success = array(
						'msg'     => __( 'Popular components have been enabled.', 'simple-stack' ),
						'refresh' => 'yes',
					);
					break;

				case 'disable_all_monit':
					update_post_meta( $id, 'sstk_wpapp_monit_webserver', 'off' );
					update_post_meta( $id, 'sstk_wpapp_monit_mysql', 'off' );
					update_post_meta( $id, 'sstk_wpapp_monit_memcached', 'off' );
					update_post_meta( $id, 'sstk_wpapp_monit_redis', 'off' );
					update_post_meta( $id, 'sstk_wpapp_monit_filesys', 'off' );
					$success = array(
						'msg'     => __( 'All components have been disabled.', 'simple-stack' ),
						'refresh' => 'yes',
					);
					break;

				case 'enable_monit_ssl':
					update_post_meta( $id, 'sstk_wpapp_monit_ssl', 'on' );
					$success = array(
						'msg'     => __( 'SSL has been enabled for Monit.', 'simple-stack' ),
						'refresh' => 'yes',
					);
					break;

				case 'disable_monit_ssl':
					update_post_meta( $id, 'sstk_wpapp_monit_ssl', 'off' );
					$success = array(
						'msg'     => __( 'SSL has been disabled for Monit.', 'simple-stack' ),
						'refresh' => 'yes',
					);
					break;

				case 'update_email_monit':
					$email_meta['monit_smtp_user'] = self::encrypt( $email_meta['monit_smtp_user'] );
					$email_meta['monit_smtp_pass'] = self::encrypt( $email_meta['monit_smtp_pass'] );
					update_post_meta( $id, 'sstk_wpapp_monit_email_gateway', $email_meta );
					$success = array(
						'msg'     => __( 'The email gateway for Monit notification has been updated.', 'simple-stack' ),
						'refresh' => 'yes',
					);
					break;

				case 'remove_monit':
					$this->remove_metas( $id );
					$success = array(
						'msg'     => __( 'Monit has been removed from the server.', 'simple-stack' ),
						'refresh' => 'yes',
					);
					break;

				case 'activate_monit':
					update_post_meta( $id, 'sstk_wpapp_monit_status', 'on' );
					$success = array(
						'msg'     => __( 'Monit has been activated.', 'simple-stack' ),
						'refresh' => 'yes',
					);
					break;

				case 'deactivate_monit':
					update_post_meta( $id, 'sstk_wpapp_monit_status', 'off' );
					$success = array(
						'msg'     => __( 'Monit has been temporarily deactivated.  It will reactivate the next time the server is restarted.', 'simple-stack' ),
						'refresh' => 'yes',
					);
					break;

			}
		}

		return $success;

	}

	/**
	 * Remove all Monit metas
	 *
	 * @param int $id post id of the server.
	 */
	public function remove_metas( $id ) {
		delete_post_meta( $id, 'sstk_wpapp_monit_installed' );
		delete_post_meta( $id, 'sstk_wpapp_monit_status' );
		delete_post_meta( $id, 'sstk_wpapp_monit_domain' );
		delete_post_meta( $id, 'sstk_wpapp_monit_ssl' );
		delete_post_meta( $id, 'sstk_wpapp_monit_webserver' );
		delete_post_meta( $id, 'sstk_wpapp_monit_mysql' );
		delete_post_meta( $id, 'sstk_wpapp_monit_memcached' );
		delete_post_meta( $id, 'sstk_wpapp_monit_redis' );
		delete_post_meta( $id, 'sstk_wpapp_monit_filesys' );
		delete_post_meta( $id, 'sstk_wpapp_monit_user' );
		delete_post_meta( $id, 'sstk_wpapp_monit_pass' );
		delete_post_meta( $id, 'sstk_wpapp_monit_email_gateway' );
		delete_post_meta( $id, 'sstk_wpapp_monit_mmonit_server' );
	}

	/**
	 * Load defaults the monit email alerts.
	 *
	 * @param int    $id The postID of the server cpt.
	 * @param string $action The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean|object|string    success/failure/other
	 */
	public function sstk_monit_email_alerts_load_defaults( $id, $action ) {

		// Check for admin user.
		if ( ! sstk_is_admin() ) {
			return new \WP_Error( __( 'You are not allowed to perform this action - only admins are permitted here.', 'simple-stack' ) );
		}

		$smtp_port   = '';
		$smtp_server = sstk_get_early_option( 'sstk_email_gateway_smtp_server' );
		$smtp_user   = sstk_get_early_option( 'sstk_email_gateway_smtp_user' );

		// Split the string to get server and port values separately.
		$smtp_server_port = explode( ':', $smtp_server );
		if ( count( $smtp_server_port ) > 1 ) {
			$smtp_server = $smtp_server_port[0];
			$smtp_port   = $smtp_server_port[1];
		}

		$args                = array();
		$args['smtp_server'] = (string) $smtp_server;
		$args['smtp_port']   = (string) $smtp_port;
		$args['smtp_user']   = (string) $smtp_user;

		$success = array(
			'msg'          => __( 'Defaults have been successfully loaded.', 'simple-stack' ),
			'tab_prefix'   => 'sstk_app_action_monit',  // Used by the JS code so it knows which tab we're on.  It needs to know because we are using the same code to load defaults for the EMAIL GATEWAY as well.
			'email_fields' => $args,
			'refresh'      => 'no',
		);

		return $success;

	}

}

new SSTK_WORDPRESS_TABS_SERVER_MONIT();
