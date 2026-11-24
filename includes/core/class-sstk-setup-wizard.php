<?php
/** Modified for Simple Stack by One Dog Solutions on 2026-11-23. */
/**
 * SSTK Setup Wizard Class
 *
 * Takes new users through some basic steps to setup their support.
 *
 * @package   Simple Stack/Includes/Core/SSTK_Admin_Setup_Wizard
 * @author    Simple Stack <contact@onedogsolutions.com>
 * @license   GPL-2.0+
 * @link      https://github.com/onedogsolutions/simple-stack
 * @copyright 2019-2022 simple-stack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SSTK_Admin_Setup_Wizard class.
 */
class SSTK_Admin_Setup_Wizard {

	/**
	 * Current step
	 *
	 * @var string
	 */
	private $step = '';

	/**
	 * Steps for the setup wizard
	 *
	 * @var array
	 */
	private $steps = array();

	/**
	 * Hook in tabs.
	 */
	public function __construct() {

		add_action( 'admin_menu', array( $this, 'admin_menus' ) );
		add_action( 'admin_init', array( $this, 'maybe_ask_setup_wizard' ) );
		add_action( 'admin_init', array( $this, 'setup_wizard' ) );

		/* AJAX Function for skipping setup wizard. */
		add_action( 'wp_ajax_sstk_skip_wizard_setup', array( $this, 'sstk_skip_wizard_setup' ) );
	}

	/**
	 * Add admin menus/screens.
	 */
	public function admin_menus() {
		if ( sstk_is_admin() ) {
			add_dashboard_page( '', '', 'manage_options', 'sstk-setup', '' );
		}

		if ( sstk_is_manager() ) {
			add_dashboard_page( '', '', 'sstk_manage_all', 'sstk-setup', '' );
		}
	}

	/**
	 * Maybe show prompt at top of screen to setup wizard.
	 *
	 * Action Hook: admin_init.
	 */
	public function maybe_ask_setup_wizard() {

		$test_mode = false;

		/**
		 * Proceed only if both options 'sstk_plugin_setup' & 'sstk_skip_wizard_setup' = false
		 * 'wpas_plugin_setup' will be added at the end of wizard steps
		 * 'wpas_skip_wizard_setup' will be set to true if user choose to skip wizrd from admin notice
		 */
		if ( ( ! get_option( 'sstk_plugin_setup', false ) && ! get_option( 'sstk_skip_wizard_setup', false ) ) || ( true === $test_mode ) ) {

			/**
			 * If we already have at least one server setup, do not show wizard prompt.
			 */
			$count_servers = wp_count_posts( 'sstk_app_server' )->private;
			if ( $count_servers > 0 ) {
				add_option( 'sstk_skip_wizard_setup', true );
				return;
			}

			/**
			 * If METABOX is not already installed, skip since we can't do anything until that is installed.
			 */
			if ( ! class_exists( 'RWMB_Core' ) ) {
				return;
			}

			// Show Wizard prompt.
			add_action( 'admin_notices', array( $this, 'sstk_ask_setup_wizard' ), 1 );
			add_action( 'admin_enqueue_scripts', array( $this, 'sstk_setup_wizard_scripts' ), 10, 1 );
		}

	}

	/**
	 * Paint a button at the top of the admin area asking the user if they'd like to setup using the wizard.
	 *
	 * Action Hook: admin_notices.
	 */
	public function sstk_ask_setup_wizard() {

		if ( sstk_is_admin() || sstk_is_manager() ) {

			/* Product name that will be shown at various locations in the wizard. */
			$product_name = sstk_get_short_product_name()

			?>
			<div class="updated sstk-wizard-notice">
				<h1 class="wizard-main-heading"><?php _e( sprintf( __( '%s: First Time Install', 'simple-stack' ), $product_name ) ); ?></h1>
				<p class="wizard-first-line"><?php _e( sprintf( __( 'Thank you for installing %s. Please choose an option below to get started.', 'simple-stack' ), $product_name ) ); ?></p>
				<p class="wizard-normal wizard-second-line"><?php _e( sprintf( __( 'If this is not the first time you are using %s or you would like to manually configure your initial settings, then you should choose to skip this process. Otherwise proceed by clicking the button.', 'simple-stack' ), $product_name ) ); ?></p>
				<p><span class="sstk-button-wizard-primary"><?php _e( '<a href="' . admin_url( 'index.php?page=sstk-setup' ) . '">Click here To Get Started Now</a>', 'simple-stack' ); ?></span>
					<span class="sstk-button-wizard-skip"><?php _e( '<a href="#" id="sstk-skip-wizard">Or skip this process</a>', 'simple-stack' ); ?>
				</p>
			</div>
			<?php
		}

	}

	/**
	 * Load Scripts to be used in the setup wizard.
	 *
	 * Action Hook: admin_enqueue_scripts.
	 */
	public function sstk_setup_wizard_scripts() {
		wp_enqueue_style( 'sstk-admin-wizard-notice', SSTK_URL . 'assets/css/sstk-setup-wizard-notice.css', array(), SSTK_SCRIPTS_VERSION );
		wp_enqueue_script( 'sstk-admin-wizard-script', SSTK_URL . 'assets/js/sstk-setup-wizard.js', array( 'jquery' ), SSTK_SCRIPTS_VERSION, true );
		wp_localize_script(
			'sstk-admin-wizard-script',
			'SSTK_Wizard',
			array(
				'ajax_url'   => admin_url( 'admin-ajax.php' ),
				'about_page' => admin_url( 'edit.php?post_type=sstk_app_server' ),
			)
		);
	}

	/**
	 * Skip Setup Wizard
	 *
	 * @since 5.0
	 *
	 * @return void.
	 */
	public function sstk_skip_wizard_setup() {
		add_option( 'sstk_skip_wizard_setup', true );
		wp_die();
	}


	/**
	 * Show the setup wizard.
	 */
	public function setup_wizard() {
		if ( empty( $_GET['page'] ) || 'sstk-setup' !== $_GET['page'] ) { // WPCS: CSRF ok, input var ok.
			return;
		}
		$default_steps = array(
			'general_setup'       => array(
				'name'    => __( 'General', 'simple-stack' ),
				'view'    => array( $this, 'sstk_general_setup' ),
				'handler' => array( $this, 'sstk_general_setup_save' ),
			),
			'select_provier'      => array(
				'name'    => __( 'Select Your Server Provider', 'simple-stack' ),
				'view'    => array( $this, 'sstk_select_provider' ),
				'handler' => array( $this, 'sstk_select_provider_save' ),
			),
			'connect_to_provider' => array(
				'name'    => __( 'Connect To Provider', 'simple-stack' ),
				'view'    => array( $this, 'sstk_connect_to_provider' ),
				'handler' => array( $this, 'sstk_connect_to_provider_save' ),
			),
			'create_ssh_keys'     => array(
				'name'    => __( 'Create SSH Keys', 'simple-stack' ),
				'view'    => array( $this, 'sstk_create_ssh_keys' ),
				'handler' => array( $this, 'sstk_create_ssh_keys_save' ),
			),
			'lets_go'             => array(
				'name'    => __( 'Ready', 'simple-stack' ),
				'view'    => array( $this, 'sstk_setup_lets_go' ),
				'handler' => array( $this, 'sstk_setup_lets_go_save' ),
			),
		);

		// Load styles and scripts.
		wp_enqueue_style( 'sstk-common-admin', SSTK_URL . 'assets/css/sstk-setup-wizard.css', array(), SSTK_SCRIPTS_VERSION );
		wp_enqueue_script( 'sstk-admin-script', SSTK_URL . 'assets/js/sstk-setup-wizard-support.js', array( 'jquery' ), SSTK_SCRIPTS_VERSION, true );
		wp_enqueue_script( 'sstk-setup', SSTK_URL . 'assets/js/sstk-setup-wizard-support.js', array( 'jquery', 'wp-util' ), SSTK_SCRIPTS_VERSION, true );

		// What is the next step?
		$this->steps = apply_filters( 'as_setup_wizard_steps', $default_steps );
		$this->step  = isset( $_GET['step'] ) ? sanitize_key( $_GET['step'] ) : current( array_keys( $this->steps ) ); // WPCS: CSRF ok, input var ok.

		if ( ! empty( $_POST['save_step'] ) && isset( $this->steps[ $this->step ]['handler'] ) ) {
			call_user_func( $this->steps[ $this->step ]['handler'], $this );
		}

		ob_start();
		// call setup view functions here.
		$this->setup_wizard_header();
		$this->setup_wizard_steps();
		$this->setup_wizard_content();
		$this->setup_wizard_footer();
		exit;
	}

	/**
	 * Setup Wizard Header.
	 */
	public function setup_wizard_header() {
		$product_name = sstk_get_short_product_name();
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta name="viewport" content="width=device-width" />
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
			<title><?php esc_html_e( sprintf( __( '%s &rsaquo; Setup Wizard', 'simple-stack' ), $product_name ) ); ?></title>
			<?php wp_print_scripts( 'sstk-setup' ); ?>
			<?php do_action( 'admin_print_styles' ); ?>
			<?php do_action( 'admin_head' ); ?>
		</head>
		<body class="sstk-setup wp-core-ui">
			<div class="sstk-setup-wizard">
			<h1 id="sstk-logo"><a href="https://github.com/onedogsolutions/simple-stack/"><?php esc_html_e( sprintf( __( '%s', 'simple-stack' ), $product_name ) ); ?></a></h1>
		<?php
	}


	/**
	 * Output the steps.
	 */
	public function setup_wizard_steps() {
		$output_steps = $this->steps;
		?>
		<ol class="sstk-setup-steps">
			<?php foreach ( $output_steps as $step_key => $step ) : ?>
				<?php
					/**
					 * Determine step_class
					 * On each steps done, add .done while .active
					 * for the current step state.
					 */
				if ( $step_key === $this->step ) {
					$step_class = 'active';
				} elseif ( array_search( $this->step, array_keys( $this->steps ), true ) > array_search( $step_key, array_keys( $this->steps ), true ) ) {
					$step_class = 'done';
				} else {
					$step_class = '';
				}
				?>
				<li class="<?php echo $step_class; ?>"><div class="hint">
					<?php echo esc_html( $step['name'] ); ?>
				</div></li>
			<?php endforeach; ?>
		</ol>
		<?php
	}

	/**
	 * Output the content for the current step.
	 */
	public function setup_wizard_content() {
		echo '<div class="sstk-setup-content">';
		/*
		printf(
			'<p class="sub-heading">%s</p>',
			__( 'Welcome to Awesome Support! This setup wizard will help you to quickly configure your new support system so that you can start processing customer requests right away.  So lets get started with our first question!', 'simple-stack' )
		);
		*/
		if ( ! empty( $this->steps[ $this->step ]['view'] ) ) {
			call_user_func( $this->steps[ $this->step ]['view'], $this );

		}
		echo '</div>';
	}


	/**
	 * Setup Wizard Footer.
	 */
	public function setup_wizard_footer() {
		$about_us_link = add_query_arg(
			array(
				'post_type' => 'sstk_app_server',
				'page'      => 'sstk_settings#tab-cloud-provider',
			),
			admin_url( 'edit.php' )
		)
		?>
		<?php if ( 'lets_go' !== $this->step ) : ?>
			<a class="not-now" href="<?php echo esc_url( $about_us_link ); ?>"><?php esc_html_e( 'Not right now', 'simple-stack' ); ?></a>
		<?php endif; ?>
				</div><!-- .setup-wizard -->
			</body>
		</html>
		<?php
	}

	/**
	 * General Setup
	 */
	public function sstk_general_setup() {
		$product_name = sstk_get_short_product_name();

		?>
		<form method="post">
			<p><b><?php esc_html_e( sprintf( __( 'Welcome to %s!', 'simple-stack' ), $product_name ) ); ?> </b></p>
			<p><?php esc_html_e( 'This setup wizard will help you configure Simple Stack and connect your cloud provider account.', 'simple-stack' ); ?> </p>
			<p><b><?php esc_html_e( 'Encryption Key', 'simple-stack' ); ?> </b></p>
			<?php
			// See if sstk encryption key is defined.
			if ( ! DEFINED( 'SSTK_ENCRYPTION_KEY' ) ) {
				?>
				<p><?php esc_html_e( 'Setting up an encryption key in your wp-config.php file helps to protect your sensitive data such as your API keys and SSH keys.', 'simple-stack' ); ?> </p>
				<p><?php esc_html_e( 'We strongly recommend that you set this up now. If you set it up later, you will need to re-enter your sensitive data (api keys etc.)', 'simple-stack' ); ?> </p>
				<p><b><?php esc_html_e( 'Please use your sFTP client to add the SSTK_ENCRYPTION_KEY constant into your wp-config.php file.', 'simple-stack' ); ?> </b></p>
				<p><pre><?php esc_html_e( "define( 'SSTK_ENCRYPTION_KEY', 'your very long encryption key goes here' );", 'simple-stack' ); ?> </pre></p>
				<p><?php esc_html_e( 'Click the continue button when this task is completed. You will not be able to move on to the next step until this task is completed.', 'simple-stack' ); ?> </p>
				<?php

			} else {
				?>
				<p><?php esc_html_e( 'Sweet! Your SSTK_ENCRYPTION_KEY constant is defined. You can continue to the next step.', 'simple-stack' ); ?> </p>
				<p><?php esc_html_e( 'Please click the CONTINUE button below.', 'simple-stack' ); ?> </p>
				<?php
			}
			?>

			<input type="submit" name="save_step" value="Continue">
			<?php wp_nonce_field( 'sstk-setup' ); ?>
		</form>
		<?php
	}

	/**
	 * General Setup Save
	 */
	public function sstk_general_setup_save() {
		check_admin_referer( 'sstk-setup' );
		if ( ! DEFINED( 'SSTK_ENCRYPTION_KEY' ) ) {
			// Do not move on from the wizard since the encryption key is not defined in wp-config.php.
			wp_safe_redirect( esc_url_raw( $this->get_this_step_link() ) );
			exit;
		} else {
			wp_safe_redirect( esc_url_raw( $this->get_next_step_link() ) );
			exit;
		}
	}

	/**
	 * Select Provider setup view.
	 */
	public function sstk_select_provider() {

		// Offer only registered providers whose API class is available in this build.
		$providers = array();
		foreach ( SSTK()->get_cloud_providers() as $slug => $label ) {
			if ( ! empty( SSTK()->get_provider_api( $slug ) ) ) {
				$providers[ $slug ] = $label;
			}
		}

		?>
		<form method="post">
			<p><b><?php esc_html_e( 'Please Select Your Cloud Server Provider', 'simple-stack' ); ?> </b></p>
			<p><?php esc_html_e( 'If you do not see your Cloud Server provider in the list below, please make sure you have uploaded and/or activated the provider plugin.', 'simple-stack' ); ?> </p>
			<label for="selected-provider"><?php esc_html_e( 'Choose a provider:', 'simple-stack' ); ?></label>
			<select style="min-width: 200px;" type="text" name="selected-provider">
			<?php
			foreach ( $providers as $provider => $name ) {
				echo '<option value="' . $provider . '">' . $name . '</option>';
			}
			?>
			</select>
			<br /><br />
			<?php
			echo '<input type="submit" name="save_step" value="Continue">';
			// Extract error message from url - this could be set by the save function for this step.
			if ( $this->get_error_message_from_url() ) {
				$error_msg = $this->get_error_message_from_url();
				?>
				<p style="color:red;"><?php esc_html_e( $error_msg ); ?></p>
				<?php
			}

			wp_nonce_field( 'sstk-select-provider' );
			?>
		</form>
		<?php
	}

	/**
	 * Save Selected Provider.
	 */
	public function sstk_select_provider_save() {
		check_admin_referer( 'sstk-select-provider' );

		// Extract the token from the _POST global var.
		$selected_provider = sanitize_text_field( FILTER_INPUT( INPUT_POST, 'selected-provider', FILTER_DEFAULT ) );

		// Empty key?  Stay on the current step.
		if ( empty( $selected_provider ) ) {
			wp_safe_redirect( esc_url_raw( add_query_arg( array( 'error_msg' => __( 'Please select a provider.', 'simple-stack' ) ), $this->get_this_step_link() ) ) );
			exit;
		}

		// Reject providers that are not registered in this build.
		if ( ! array_key_exists( $selected_provider, SSTK()->get_cloud_providers() ) ) {
			wp_safe_redirect( esc_url_raw( add_query_arg( array( 'error_msg' => __( 'Please select a supported provider.', 'simple-stack' ) ), $this->get_this_step_link() ) ) );
			exit;
		}

		// Otherwise, save the selected provider for later use.
		update_option( 'sstk_setup_wizard_selected_provider', $selected_provider );

		// Go to next step.
		wp_safe_redirect( esc_url_raw( $this->get_next_step_link() ) );
		exit;

	}

	/**
	 * Connect To Provider setup view.
	 */
	public function sstk_connect_to_provider() {

		// Get provider from saved option.
		$provider = $this->sstk_get_selected_provider();

		// If no provider, exit - the error message would have been displayed by the call to sstk_get_selected_provider().
		if ( empty( $provider ) ) {
			exit;
		}

		?>
		<form method="post">
			<?php $this->sstk_api_token_instructions( $provider ); ?>

			<?php // Ask for the api key only if the provider is not upcloud - UPCLOUD only needs a user id and password. ?>
			<?php if ( 'upcloud' !== $provider ) { ?>
				<b><label for="api-token"><?php esc_html_e( 'Enter Your API Key or Token:', 'simple-stack' ); ?></label></b>
				<input type="password" name="api-token" size="100" autocomplete="off" />
				<br /><br />
			<?php } ?>

			<?php $this->sstk_get_other_provider_fields( $provider ); ?>

			<?php
			echo '<input type="submit" name="save_step" value="Continue">';
			// Extract error message from url - this could be set by the save function for this step.
			if ( $this->get_error_message_from_url() ) {
				$error_msg = $this->get_error_message_from_url();
				?>
				<p style="color:red;"><?php esc_html_e( $error_msg ); ?></p>
				<?php
			}

			wp_nonce_field( 'sstk-setup-connect-to-provider' );
			?>
		</form>
		<?php
	}

	/**
	 * Return the provider that was selected by the user and stored in options.
	 *
	 * Echo an error to the screen if there's a problem.
	 */
	public function sstk_get_selected_provider() {

		// Get provider from saved option.
		$provider = get_option( 'sstk_setup_wizard_selected_provider' );

		// If not provider, throw error and exit.
		if ( empty( $provider ) ) {
			$error_msg = __( 'We are unable to read the provider from the database. Please exit this wizard and contact our technical support team.', 'simple-stack' );
			?>
			<p style="color:red;"><?php esc_html_e( $error_msg ); ?></p>
			<?php
		}

		return $provider;

	}

	/**
	 * Echo instructions for getting api tokens to the screen.
	 *
	 * @param string $provider The provider slug.
	 */
	public function sstk_api_token_instructions( $provider ) {

		switch ( $provider ) {
			case 'upcloud':
				?>
				<p><b><?php esc_html_e( 'Connect To Your UpCloud Account', 'simple-stack' ); ?> </b></p>
				<?php
				break;

			case 'vultr-v2':
				?>
				<p><b><?php esc_html_e( 'Connect To Your Vultr Account', 'simple-stack' ); ?> </b></p>
				<p><?php esc_html_e( 'Get your API token from your VULTR console and enter it below.', 'simple-stack' ); ?> </p>
				<p><?php esc_html_e( 'You can get the api token by navigating to: https://my.vultr.com/settings/#settingsapi.', 'simple-stack' ); ?></p>
				<p><?php esc_html_e( 'Be sure to modify the ACCESS CONTROL section to allow this WordPress site\'s IP address. Otherwise, creating Vultr servers will fail.', 'simple-stack' ); ?></p>
				<p><?php esc_html_e( 'The easiest thing to do is to allow ANY IPv4 and ANY IPv6. You can tighten them down later.', 'simple-stack' ); ?></p>
				<?php
				break;

		}

	}

	/**
	 * Get provider-specific credential fields.
	 *
	 * @param string $provider The provider slug.
	 */
	public function sstk_get_other_provider_fields( $provider ) {

		if ( 'upcloud' !== $provider ) {
			return;
		}

		?>
		<b><label for="user-name"><?php esc_html_e( 'Enter Your UpCloud User Name:', 'simple-stack' ); ?></label></b>
		<input type="text" name="user-name" size="100" autocomplete="username" />
		<br /><br />
		<b><label for="user-password"><?php esc_html_e( 'Enter Your UpCloud Password:', 'simple-stack' ); ?></label></b>
		<input type="password" name="user-password" size="100" autocomplete="current-password" />
		<br /><br />
		<?php
	}

	/**
	 * Save provider-specific credential fields.
	 *
	 * @param string $provider The provider slug.
	 */
	public function sstk_save_other_provider_fields( $provider ) {

		if ( 'upcloud' !== $provider ) {
			return;
		}

		$user_name = sanitize_text_field( (string) FILTER_INPUT( INPUT_POST, 'user-name', FILTER_DEFAULT ) );
		if ( empty( $user_name ) ) {
			wp_safe_redirect( esc_url_raw( add_query_arg( array( 'error_msg' => __( 'Please provide your UpCloud user name.', 'simple-stack' ) ), $this->get_this_step_link() ) ) );
			exit;
		}

		sstk_set_option( "sstk_{$provider}_user_name", SSTK()->encrypt( $user_name ) );

		$password = wp_unslash( (string) FILTER_INPUT( INPUT_POST, 'user-password', FILTER_UNSAFE_RAW ) );
		if ( '' === $password ) {
			wp_safe_redirect( esc_url_raw( add_query_arg( array( 'error_msg' => __( 'Please provide your UpCloud password.', 'simple-stack' ) ), $this->get_this_step_link() ) ) );
			exit;
		}

		sstk_set_option( "sstk_{$provider}_user_password", SSTK()->encrypt( $password ) );
	}

	/**
	 * Connect To Provider setup on save.
	 *
	 * @NOTE: Portions of this code is a duplicate of what's in the sstk_provider_test_provider_connection() function in file includes/core/class-sstk-settings.php.
	 */
	public function sstk_connect_to_provider_save() {
		check_admin_referer( 'sstk-setup-connect-to-provider' );

		$provider = $this->sstk_get_selected_provider();
		if ( empty( $provider ) ) {
			exit;
		}

		if ( ! array_key_exists( $provider, SSTK()->get_cloud_providers() ) ) {
			wp_safe_redirect( esc_url_raw( add_query_arg( array( 'error_msg' => __( 'Please select a supported provider.', 'simple-stack' ) ), $this->get_this_step_link() ) ) );
			exit;
		}

		if ( 'upcloud' === $provider ) {
			$this->sstk_save_other_provider_fields( $provider );
		} else {
			$api_key = sanitize_text_field( (string) FILTER_INPUT( INPUT_POST, 'api-token', FILTER_DEFAULT ) );
			if ( empty( $api_key ) ) {
				wp_safe_redirect( esc_url_raw( add_query_arg( array( 'error_msg' => __( 'Please provide the API key or token.', 'simple-stack' ) ), $this->get_this_step_link() ) ) );
				exit;
			}

			sstk_set_option( "sstk_{$provider}_apikey", SSTK()->encrypt( $api_key ) );
		}

		// Reload the provider so it reads the credentials saved above.
		unset( SSTK()->classes[ 'sstk_api_provider_' . $provider ] );
		$provider_api = SSTK()->get_provider_api( $provider );
		if ( empty( $provider_api ) ) {
			wp_safe_redirect( esc_url_raw( add_query_arg( array( 'error_msg' => __( 'The selected provider is unavailable.', 'simple-stack' ) ), $this->get_this_step_link() ) ) );
			exit;
		}

		$credential_fingerprint = $provider_api->get_credential_fingerprint();
		$transient_key          = 'sstk_provider_connection_test_success_flag_' . $provider . hash( 'sha256', $credential_fingerprint );
		delete_transient( $transient_key );

		$connection_status = $provider_api->call( 'test_connection', array() );
		if ( ! is_wp_error( $connection_status ) && ! empty( $connection_status['test_status'] ) && true === (bool) $connection_status['test_status'] ) {
			set_transient( $transient_key, 'connection_successful', 86400 );
			wp_safe_redirect( esc_url_raw( $this->get_next_step_link() ) );
			exit;
		}

		wp_safe_redirect( esc_url_raw( add_query_arg( array( 'error_msg' => __( 'We were unable to connect to the selected provider. Check the credentials and try again.', 'simple-stack' ) ), $this->get_this_step_link() ) ) );
		exit;
	}

	/**
	 * Create SSH keys at cloud provider view.
	 */
	public function sstk_create_ssh_keys() {
		?>
		<form method="post">
			<p><b><?php esc_html_e( 'Create SSH Keys', 'simple-stack' ); ?> </b></p>
			<p><?php esc_html_e( 'We use SSH keys, not passwords, to connect to your server.', 'simple-stack' ); ?></p>
			<p><?php esc_html_e( 'We will create a new ssh key-pair for you and submit the public portion to your server provider\'s account.', 'simple-stack' ); ?></p>
			<p><?php esc_html_e( 'Click the CONTINUE button below to do this now.', 'simple-stack' ); ?></p>
			<p><?php esc_html_e( 'If you prefer to use your own keys, you can cancel this assistant using the NOT RIGHT NOW button and enter your own keys in the SETTINGS area.', 'simple-stack' ); ?></p>
			<input type="submit" name="save_step" value="Continue">
			<?php wp_nonce_field( 'sstk-ssh-keys' ); ?>
		</form>
		<?php
	}

	/**
	 * Create SSH keys at cloud provider on save.
	 *
	 * @NOTE: Portions of this code is a duplicate of what's in the sstk_provider_auto_create_ssh_key() function in file includes/core/class-sstk-settings.php.
	 */
	public function sstk_create_ssh_keys_save() {
		check_admin_referer( 'sstk-ssh-keys' );

		// Get previously selected provider.
		$provider = $this->sstk_get_selected_provider();

		// If no provider, exit - the error message would have been displayed by the call to sstk_get_selected_provider().
		if ( empty( $provider ) ) {
			exit;
		}

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

			// Clear caches so the settings screen will have a new list of ssh keys.
			SSTK()->get_provider_api( $provider )->clear_cache();

			wp_safe_redirect( esc_url_raw( $this->get_next_step_link() ) );
			exit;

		} else {
			// Stay on this step.
			wp_safe_redirect( esc_url_raw( add_query_arg( array( 'error_msg' => __( 'It looks like we were unable to create the key for you. You can try again or cancel this wizard and setup your own pair in the settings area.', 'simple-stack' ) ), $this->get_this_step_link() ) ) );
			exit;
		}
	}

	/**
	 * Lets Go page view for think you message and all.
	 */
	public function sstk_setup_lets_go() {
		$product_name = sstk_get_short_product_name();
		?>
		<form method="post">
			<p><b><?php esc_html_e( sprintf( __( '%s is all set up and ready to go!', 'simple-stack' ), $product_name ) ); ?></b></p>
			<p><?php esc_html_e( 'You should now be able to create your first server at your provider.', 'simple-stack' ); ?></p>
			<p>
			<?php
			echo sprintf( __( 'You can go to the server list and use the DEPLOY A NEW WORDPRESS SERVER button at the top of the screen to create your first server.  Or <b><u><a %s>View The Documentation</a></b></u>.', 'simple-stack' ), 'href="https://github.com/onedogsolutions/simple-stack/blob/main/README.md" target="_blank" ' );
			?>
			</p>
			<input type="submit" name="save_step" value="Create First Server">
			<?php wp_nonce_field( 'sstk-setup-complete' ); ?>
		</form>
		<?php
	}

	/**
	 * Lets Go button click
	 */
	public function sstk_setup_lets_go_save() {
		check_admin_referer( 'sstk-setup-complete' );
		add_option( 'sstk_plugin_setup', true );  // Prevents the wizard from showing on the screen.
		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type' => 'sstk_app_server',
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * Extract and return an error message from the URL.
	 */
	public function get_error_message_from_url() {
		$msg = sanitize_text_field( FILTER_INPUT( INPUT_GET, 'error_msg', FILTER_DEFAULT ) );
		return $msg;
	}

	/**
	 * Get the URL for the next step's screen.
	 *
	 * @param string $step  slug (default: current step).
	 * @return string       URL for next step if a next step exists.
	 *                      Admin URL if it's the last step.
	 *                      Empty string on failure.
	 */
	public function get_next_step_link( $step = '' ) {
		if ( ! $step ) {
			$step = $this->step;
		}

		$keys = array_keys( $this->steps );
		if ( end( $keys ) === $step ) {
			return admin_url();
		}

		$step_index = array_search( $step, $keys, true );
		if ( false === $step_index ) {
			return '';
		}

		return remove_query_arg( 'error_msg', add_query_arg( 'step', $keys[ $step_index + 1 ], remove_query_arg( 'activate_error' ) ) );
	}

	/**
	 * Get the URL for the current step's screen.
	 *
	 * @param string $step  slug (default: current step).
	 * @return string       URL for current step.
	 */
	public function get_this_step_link( $step = '' ) {
		if ( ! $step ) {
			$step = $this->step;
		}

		$keys = array_keys( $this->steps );
		if ( end( $keys ) === $step ) {
			return admin_url();
		}

		$step_index = array_search( $step, $keys, true );
		if ( false === $step_index ) {
			return '';
		}

		return add_query_arg( 'step', $keys[ $step_index ], remove_query_arg( 'activate_error' ) );
	}

}
new SSTK_Admin_Setup_Wizard();
