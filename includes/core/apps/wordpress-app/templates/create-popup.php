<?php
/** Modified for Simple Stack by One Dog Solutions on 2026-11-23. */
/**
 * Create Popup
 *
 * @package simple-stack
 */

?>
<div class="sstk-install-popup-container">
	<!-- Before Logo/header action hook -->
	<?php do_action( "sstk_{$this->get_app_name()}_create_popup_before_header", $providers, $oslist ); ?>

	<?php require SSTK_PATH . 'includes/core/apps/wordpress-app/templates/header-popup.php'; ?>

	<!-- After Logo/header action hook -->
	<?php do_action( "sstk_{$this->get_app_name()}_create_popup_after_header", $providers, $oslist ); ?>


	<div class="sstk-pre-install sstk-popup">

		<?php
		/* Show error message if there are no providers! */
		if ( empty( $providers ) ) {
			?>
			<div class="sstk-no-providers"><?php echo esc_html( __( 'Oops! Unfortunately it seems as if you have not configured a server provider yet. You need at least one server provider before you can deploy a server. You can do this on the settings screen.', 'simple-stack' ) ); ?></div>
			<?php
		} else {
			?>

			<!-- Before title action hook -->
			<?php do_action( "sstk_{$this->get_app_name()}_create_popup_before_title", $providers, $oslist ); ?>

			<div class="sstk-action-title-wrap">
				<div class="sstk-action-title"><?php echo esc_html( __( 'Deploy A New WordPress Server', 'simple-stack' ) ); ?></div>
			</div>

			<!-- After title action hook -->
			<?php do_action( "sstk_{$this->get_app_name()}_create_popup_after_title", $providers, $oslist ); ?>

			<div class="sstk-create-popup-grid-wrap">
				<div class="sstk-create-popup-fields-wrap">

					<!-- Before form open action hook -->
					<?php do_action( "sstk_{$this->get_app_name()}_create_popup_before_form_open", $providers, $oslist ); ?>

					<form id="sstk-install">
						<!-- After form open action hook -->
						<?php do_action( "sstk_{$this->get_app_name()}_create_popup_after_form_open", $providers, $oslist ); ?>

						<!-- Webserver type dropdown -->
						<?php if ( ! DEFINED( 'SSTK_WPAPP_HIDE_WEBSERVER_OPTIONS' ) || ( DEFINED( 'SSTK_WPAPP_HIDE_WEBSERVER_OPTIONS' ) && ! SSTK_WPAPP_HIDE_WEBSERVER_OPTIONS ) ) { ?>
						<div class="sstk-create-popup-label-wrap"><label class="sstk-create-popup-label" for="webserver-type"> <?php echo esc_html( __( 'Web Server', 'simple-stack' ) ); ?>  </label></div>
						<div class="sstk-create-popup-input-wrap">
							<select name="webserver-type" class="sstk_app_webserver">
							<?php
							$default_web_server = SSTK_WORDPRESS_APP()->get_default_webserver();

							foreach ( $webserver_list as $webserver => $webserver_name ) {
								$is_selected_webserver = '';
								if ( $webserver === $default_web_server ) {
									$is_selected_webserver = 'selected';
								}
								?>
									<option value="<?php echo esc_attr( $webserver ); ?>" <?php echo esc_attr( $is_selected_webserver ); ?> ><?php echo esc_html( $webserver_name ); ?></option>
									<?php
							}
							?>
							</select>
						</div>
						<?php } ?>

						<!-- OS dropdown -->
						<div class="sstk-create-popup-label-wrap"><label class="sstk-create-popup-label" for="os"> <?php echo esc_html( __( 'OS', 'simple-stack' ) ); ?>  </label></div>
						<div class="sstk-create-popup-input-wrap">
							<select name="os" class="sstk_app_os">
							<?php
							$default_os = SSTK_WORDPRESS_APP()->get_default_os();
							foreach ( $oslist as $os => $osname ) {
								$is_selected_os = '';
								if ( $os == $default_os ) {
									$is_selected_os = 'selected';
								}
								?>
									<option value="<?php echo esc_attr( $os ); ?>" <?php echo esc_attr( $is_selected_os ); ?> ><?php echo esc_html( $osname ); ?></option>
									<?php
							}
							?>
							</select>
						</div>

						<!-- Provider dropdown -->
						<div class="sstk-create-popup-label-wrap"><label class="sstk-create-popup-label" for="provider"> <?php echo esc_html( __( 'Provider', 'simple-stack' ) ); ?>  </label></div>
						<div class="sstk-create-popup-input-wrap">
							<select name="provider" class="sstk_app_provider">
							<?php
							foreach ( $provider_regions['providers'] as $slug => $name ) {
								?>
								<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></option>
								<?php
							}
							?>
							</select>
						</div>

						<!-- Region dropdown -->
						<div class="sstk-create-popup-label-wrap"><label class="sstk-create-popup-label" for="region"> <?php echo esc_html( __( 'Region', 'simple-stack' ) ); ?>  </label></div>
						<div class="sstk-create-popup-input-wrap">
							<select name="region" class="sstk_app_region">
							<?php
							foreach ( $provider_regions['regions'] as $slug => $name ) {
								?>
								<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></option>
								<?php
							}
							?>
							</select>
						</div>

						<!-- Server Size dropdown -->
						<div class="sstk-create-popup-label-wrap"><label class="sstk-create-popup-label" for="size"> <?php echo esc_html( __( 'Size', 'simple-stack' ) ); ?>  </label></div>
						<div class="sstk-create-popup-input-wrap">
							<select name="size" class="sstk_app_size">
							<?php
							foreach ( $provider_regions['sizes'] as $slug => $name ) {
								?>
								<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></option>
								<?php
							}
							?>
							</select>
						</div>

						<div class="sstk-create-popup-label-wrap sstk-create-popup-label-wrap-for-server-type"><label class="sstk-create-popup-label" for="server_type"> <?php echo esc_html( __( 'Server Type', 'simple-stack' ) ); ?>  </label></div>
						<div class="sstk-create-popup-input-wrap">
							<select name="server_type" class="server_type">
								<option value="wordpress-app">WordPress</option>
								<option value="something-else">Something Else</option>
							</select>
						</div>

						<input type="hidden" name="script_version" value="<?php echo esc_attr( $scripts_version ); ?>">

						<div class="sstk-create-popup-label-wrap"><label class="sstk-create-popup-label" for="name"> <?php echo esc_html( __( 'Name of Server', 'simple-stack' ) ); ?>  </label></div>
						<div class="sstk-create-popup-input-wrap">
							<input type="text" name="name" placeholder="<?php echo esc_html( __( 'Name of server', 'simple-stack' ) ); ?>" class="sstk_server_name">
							<div class='wp_field_error wp_server_name_error_msg'><?php _e( 'The following characters are invalid in all fields:  \' " ; \ | < > ` @ $ & ( ) / and spaces, carriage returns, linefeeds.', 'simple-stack' ); ?></div>
						</div>

						<input type="hidden" id="wp_user_id" name="wp_user_id" value="<?php echo esc_attr( get_current_user_id() ); ?>" >

						<!-- Before install button action hook -->
						<?php do_action( "sstk_{$this->get_app_name()}_create_popup_before_install_button", $providers, $oslist ); ?>

						<div class="sstk-create-popup-input-wrap">
							<button class="sstk-install-button sstk-install-server"><?php echo esc_html( __( 'Deploy', 'simple-stack' ) ); ?></button>
						</div>

						<!-- Before form close action hook -->
						<?php do_action( "sstk_{$this->get_app_name()}_create_popup_before_form_close", $providers, $oslist ); ?>

					</form>

					<!-- After form close action hook -->
					<?php do_action( "sstk_{$this->get_app_name()}_create_popup_after_form_close", $providers, $oslist ); ?>

					<div class="sstk-action-instructions sstk_custom_tooltip">
						<?php echo esc_html( __( 'Instructions', 'simple-stack' ) ); ?><span class="dashicons dashicons-info"></span>
						<span class="sstk_custom_tooltiptext">
							<?php echo esc_html( __( 'Fill out the fields above and then click the DEPLOY button to start the provisioning the server.', 'simple-stack' ) ); ?><br />
							<?php echo esc_html( __( 'After this step is complete and the server has been deployed you will be able to install one or more WordPress sites from the ALL CLOUD SERVERS list.', 'simple-stack' ) ); ?><br /><br />
							<?php echo esc_html( __( 'Instructions and notes:', 'simple-stack' ) ); ?><br />
							<?php echo esc_html( __( '1. Do not change the version number of the scripts unless advised to by support. The default version of scripts is v1.', 'simple-stack' ) ); ?><br />
							<?php echo esc_html( __( '2. If you leave the server name blank, a random numeric name will be generated for you.', 'simple-stack' ) ); ?><br /><br />
							<?php echo esc_html( __( 'When the server has been deployed and all software installed you will see a message in the black terminal window that states "Installation Completed". You will also get a popup confirmation.', 'simple-stack' ) ); ?>
						</span>
					</div>
				</div>

				<!-- Before console wrap action hook -->
				<?php do_action( "sstk_{$this->get_app_name()}_create_popup_before_console_wrap", $providers, $oslist ); ?>

				<div class = "sstk-create-popup-console-wrap">
					<?php include SSTK_PATH . 'includes/core/apps/wordpress-app/templates/log-console.php'; ?>
				</div>

				<!-- After console wrap action hook -->
				<?php do_action( "sstk_{$this->get_app_name()}_create_popup_after_console_wrap", $providers, $oslist ); ?>

			</div>
			<?php
		}
		?>
	</div>
</div>