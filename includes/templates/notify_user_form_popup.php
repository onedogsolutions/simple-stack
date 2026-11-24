<?php
/** Modified for Simple Stack by One Dog Solutions on 2026-11-23. */
/**
 * This template file used for load alert form html
 *
 * @package simple-stack
 */

// Check if user logged in or not.
if ( is_user_logged_in() ) {

	// Get array of types and references.
	$sstk_notification_types      = array();
	$sstk_notification_references = array();
	$sstk_notification_types      = $this->get_user_notify_types();
	$sstk_notification_references = $this->get_user_notify_references();

	// Get current logged in user id.
	$current_user_id = get_current_user_id();

	// Get servers and sites that can be accessible by the user.
	$user_servers = array();
	$user_sites   = array();
	$user_servers = $this->get_user_notify_servers( $current_user_id );
	$user_sites   = $this->get_user_notify_sites( $current_user_id );

	$email_address       = '';
	$slack_webhook       = '';
	$zapier_webhook      = '';
	$send_to_zapier      = 0;
	$selected_servers    = array();
	$selected_sites      = array();
	$selected_types      = array();
	$selected_references = array();

	// Check if data need to add or update the existing.
	if ( '0' !== (string) $post_id ) {
		// Check post_id in sstk_notify_user.
		$notify_args = array(
			'post_type'      => 'sstk_notify_user',
			'post_status'    => 'private',
			'posts_per_page' => -1,
			'p'              => $post_id,
			'author'         => $current_user_id,
		);

		$alert_found = get_posts( $notify_args );

		if ( ! empty( $alert_found ) ) {
			$profile_name        = get_post_meta( $post_id, 'sstk_notify_user_profile_name', true );
			$email_address       = get_post_meta( $post_id, 'sstk_notify_user_email_addresses', true );
			$slack_webhook       = get_post_meta( $post_id, 'sstk_notify_user_slack_webhooks', true );
			$zapier_webhook      = get_post_meta( $post_id, 'sstk_notify_user_zapier_webhooks', true );
			$send_to_zapier      = get_post_meta( $post_id, 'sstk_notify_user_zapier_send', true );
			$selected_servers    = get_metadata( 'post', $post_id, 'sstk_notify_user_servers', false );
			$selected_sites      = get_metadata( 'post', $post_id, 'sstk_notify_user_sites', false );
			$selected_types      = get_metadata( 'post', $post_id, 'sstk_notify_user_type', false );
			$selected_references = get_metadata( 'post', $post_id, 'sstk_notify_user_reference', false );
		}
	}

	$submit_color = sstk_get_option( 'wordpress_app_submit_button_color' );
	$submit_color = empty( $submit_color ) ? '#0d6efd' : $submit_color;

	$update_color = sstk_get_option( 'wordpress_app_update_button_color' );
	$update_color = empty( $update_color ) ? '#0d6efd' : $update_color;

	$test_color = sstk_get_option( 'wordpress_app_test_button_color' );
	$test_color = empty( $test_color ) ? '#0d6efd' : $test_color;
	?>

<!-- Model -->
<div id="sstk_user_notify_popup_sec" class="sstk_user_notify_modal">
	<div class="sstk_popup_header_sec">
		<span class="sstk_user_notify_close sstk_close_custom_popup" title="close">×</span>
	</div>
	<div class="sstk_user_notify_modal_content">
		<?php include SSTK_PATH . 'includes/core/apps/wordpress-app/templates/header-popup.php'; ?>
		<div id="sstk_user_notify_form" class="sstk_notifications_form">
			<div class="mb-3 form-group row sstk_notify_row">
				<label class="col-sm-2 form-label" for="sstk_notify_profile_name"><?php echo esc_html( __( 'Name / Description', 'simple-stack' ) ); ?></label>
				<div class="col-sm-10">
					<input type="text" class="form-control" name="sstk_notify_profile_name" id="sstk_notify_profile_name" placeholder="<?php echo esc_html( __( 'Enter notification profile name/description', 'simple-stack' ) ); ?>" value="<?php echo esc_attr( $profile_name ); ?>">
				</div>
			</div>
			<div class="mb-3 form-group row sstk_notify_row">
				<label class="col-sm-2 form-label" for="sstk_notify_email_addresses"><?php echo esc_html( __( 'Email Addresses', 'simple-stack' ) ); ?></label>
				<div class="col-sm-10">
					<input type="text" class="form-control" name="sstk_notify_email_addresses" id="sstk_notify_email_addresses" aria-describedby="sstk_email_address_help" placeholder="<?php echo esc_html( __( 'Enter email addresses', 'simple-stack' ) ); ?>" value="<?php echo esc_attr( $email_address ); ?>">
					<small id="sstk_email_address_help" class="sstk_notify_help_text form-text text-muted"><?php echo esc_html( __( 'Add comma separated email addresses.', 'simple-stack' ) ); ?></small>
					<div class="sstk_user_notify_error"><?php echo esc_html( __( 'Please enter valid email addresses.', 'simple-stack' ) ); ?></div>
				</div>
			</div>
			<div class="form-group row sstk_notify_row">
				<label class="col-sm-2 form-label" for="sstk_notify_slack_webhooks"><?php echo esc_html( __( 'Slack Webhooks', 'simple-stack' ) ); ?></label>
				<div class="col-sm-10">
					<input type="text" class="form-control" name="sstk_notify_slack_webhooks" id="sstk_notify_slack_webhooks" aria-describedby="sstk_slack_webhook_help" placeholder="<?php echo esc_html( __( 'Enter slack webhooks', 'simple-stack' ) ); ?>" value="<?php echo esc_attr( $slack_webhook ); ?>">
					<small id="sstk_slack_webhook_help" class="sstk_notify_help_text form-text text-muted">
					<?php
					echo esc_html(
						__(
							'Add comma separated
                    slack webhooks.',
							'simple-stack'
						)
					);
					?>
					</small>
					<div class="sstk_user_notify_error"><?php echo esc_html( __( 'Please enter valid slack webhooks.', 'simple-stack' ) ); ?></div>
				</div>
			</div>
			<div class="form-group row sstk_notify_row">
				<label class="col-sm-2 form-label" for=""><?php echo esc_html( __( 'Send to Zapier', 'simple-stack' ) ); ?></label>
				<div class="col-sm-10">
					<input type="checkbox" class="form-check-input" name="sstk_notify_send_to_zapier" id="sstk_notify_send_to_zapier" <?php echo ( '1' === $send_to_zapier ) ? 'checked' : ''; ?> >
					<div class="sstk_user_notify_error"><?php echo esc_html( __( 'Please check the checkbox.', 'simple-stack' ) ); ?></div>
					<div class="sstk_zapier_webhook_section" style="display:<?php echo ( '1' === $send_to_zapier ) ? 'block' : 'none'; ?>" >
						<input type="text" class="form-control" name="sstk_notify_zapier_webhooks" id="sstk_notify_zapier_webhooks" aria-describedby="sstk_zapier_webhook_help" placeholder="<?php echo esc_html( __( 'Enter zapier webhooks', 'simple-stack' ) ); ?>" value="<?php echo esc_attr( $zapier_webhook ); ?>">
						<small id="sstk_zapier_webhook_help" class="sstk_notify_help_text form-text text-muted">
						<?php
						echo esc_html(
							__(
								'Add comma separated
                        zapier webhooks.',
								'simple-stack'
							)
						);
						?>
						</small>
						<div class="sstk_user_notify_error"><?php echo esc_html( __( 'Please enter valid zapier webhooks.', 'simple-stack' ) ); ?></div>
						<div class="sstk_test_zapier_btn">
							<button class="sstk_zapier_webhook_test btn btn-primary" data-nonce="<?php echo esc_html( wp_create_nonce( 'sstk-zapier-webhook-test' ) ); ?>" data-action="sstk_user_zapier_webhook_test" style="border-color:<?php echo esc_html( $test_color ); ?>; background-color:<?php echo esc_html( $test_color ); ?>;" ><?php echo esc_html( __( 'TEST', 'simple-stack' ) ); ?></button>
							<small id="sstk_zapier_webhook_help" class="sstk_notify_help_text form-text text-muted"><?php echo esc_html( __( ' (Test zapier webhooks by sending dummy data.)', 'simple-stack' ) ); ?></small>
							<span class="zapier_test_wait_msg"><?php echo esc_html( __( 'Please wait...', 'simple-stack' ) ); ?></span>
						</div>
					</div>
				</div>
			</div>

			<div class="col-sm-12">
				<div class="form-group row sstk_notify_row">
					<label class="col-sm-2 form-label" for="sstk_notify_user_servers"><?php echo esc_html( __( 'Select Servers', 'simple-stack' ) ); ?></label>
					<div class="col-sm-4">
						<select name="sstk_notify_user_servers" id="sstk_notify_user_servers" class="custom-select custom-select-sm" size="5" multiple>
						<?php
						if ( ! empty( $user_servers ) ) {
							foreach ( $user_servers as $server_key => $server_value ) {
								$selected   = '';
								$server_key = (string) $server_key;
								if ( ! empty( $selected_servers ) && in_array( $server_key, $selected_servers, true ) ) {
									$selected = 'selected="selected"';
								}
								echo '<option ' . esc_attr( $selected ) . ' value="' . esc_attr( $server_key ) . '">' . esc_html( $server_value ) . '</option>';
							}
						}
						?>
						</select>
						<div class="sstk-select-all-none"><?php echo esc_html( __( 'Select: ', 'simple-stack' ) ); ?><a data-type="all" href="#"><?php echo esc_html( __( 'All', 'simple-stack' ) ); ?></a> | <a data-type="none" href="#"><?php echo esc_html( __( 'None', 'simple-stack' ) ); ?></a></div>
					</div>
					<label class="col-sm-2 form-label" for="sstk_notify_sites"><?php echo esc_html( __( 'Select Sites', 'simple-stack' ) ); ?></label>
					<div class="col-sm-4">
						<select name="sstk_notify_sites" id="sstk_notify_sites" class="custom-select custom-select-sm" size="5" multiple>
						<?php
						if ( ! empty( $user_sites ) ) {
							foreach ( $user_sites as $site_key => $site_value ) {
								$selected = '';
								$site_key = (string) $site_key;
								if ( ! empty( $selected_sites ) && in_array( $site_key, $selected_sites, true ) ) {
									$selected = 'selected="selected"';
								}
								echo '<option ' . esc_attr( $selected ) . ' value="' . esc_attr( $site_key ) . '">' . esc_html( $site_value ) . '</option>';
							}
						}
						?>
						</select>
						<div class="sstk-select-all-none"><?php echo esc_html( __( 'Select: ', 'simple-stack' ) ); ?><a data-type="all" href="#"><?php echo esc_html( __( 'All', 'simple-stack' ) ); ?></a> | <a data-type="none" href="#"><?php echo esc_html( __( 'None', 'simple-stack' ) ); ?></a></div>
					</div>
				</div>
			</div>

			<div class="col-sm-12">
				<div class="form-group row sstk_notify_row">
					<label class="col-sm-2 form-label" for="sstk_notify_types"><?php echo esc_html( __( 'Select Notification Types', 'simple-stack' ) ); ?></label>
					<div class="col-sm-4">
						<select name="sstk_notify_types" id="sstk_notify_types" class="custom-select custom-select-sm" multiple>
						<?php
						if ( ! empty( $sstk_notification_types ) ) {
							foreach ( $sstk_notification_types as $type_key => $type_value ) {
								$selected = '';
								$type_key = (string) $type_key;
								if ( ! empty( $selected_types ) && in_array( $type_key, $selected_types, true ) ) {
									$selected = 'selected="selected"';
								}
								echo '<option ' . esc_attr( $selected ) . ' value="' . esc_attr( $type_key ) . '">' . esc_html( $type_value ) . '</option>';
							}
						}
						?>
						</select>
						<div class="sstk-select-all-none"><?php echo esc_html( __( 'Select: ', 'simple-stack' ) ); ?><a data-type="all" href="#"><?php echo esc_html( __( 'All', 'simple-stack' ) ); ?></a> | <a data-type="none" href="#"><?php echo esc_html( __( 'None', 'simple-stack' ) ); ?></a></div>
					</div>
					<label class="col-sm-2 form-label" for="sstk_notify_references"><?php echo esc_html( __( 'Select Notification References', 'simple-stack' ) ); ?></label>
					<div class="col-sm-4">
						<select name="sstk_notify_references" id="sstk_notify_references" class="custom-select custom-select-sm" size="5" multiple>
						<?php
						if ( ! empty( $sstk_notification_references ) ) {
							foreach ( $sstk_notification_references as $ref_key => $ref_value ) {
								$selected = '';
								$ref_key  = (string) $ref_key;
								if ( ! empty( $selected_references ) && in_array( $ref_key, $selected_references, true ) ) {
									$selected = 'selected="selected"';
								}
								echo '<option ' . esc_attr( $selected ) . ' value="' . esc_attr( $ref_key ) . '">' . esc_html( $ref_value ) . '</option>';
							}
						}
						?>
						</select>
						<div class="sstk-select-all-none"><?php echo esc_html( __( 'Select: ', 'simple-stack' ) ); ?><a data-type="all" href="#"><?php echo esc_html( __( 'All', 'simple-stack' ) ); ?></a> | <a data-type="none" href="#"><?php echo esc_html( __( 'None', 'simple-stack' ) ); ?></a></div>
					</div>
				</div>
			</div>

			<div class="col-sm-12">
				<div class="form-group row sstk_notify_row">
					<div class="col-sm-2"></div>
					<div class="col-sm-10">
						<?php
						$button_text  = '';
						$button_color = '';
						if ( '0' === $post_id ) {
							$button_text  = __( 'Submit', 'simple-stack' );
							$button_color = $submit_color;
						} else {
							$button_text  = __( 'Update', 'simple-stack' );
							$button_color = $update_color;
						}
						?>
						<button type="submit" class="sstk_user_notify_submit btn btn-primary" data-nonce="<?php echo esc_html( wp_create_nonce( 'sstk-user-notify' ) ); ?>" data-action="sstk_user_notification_data_save" data-post_id="<?php echo esc_attr( $post_id ); ?>" style="border-color:<?php echo esc_html( $button_color ); ?>; background-color:<?php echo esc_html( $button_color ); ?>;"><?php echo esc_html( $button_text ); ?></button>
						<span class="user_notify_wait_msg"><?php echo esc_html( __( 'Please wait...', 'simple-stack' ) ); ?></span>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<?php } else { ?>
	<p><?php echo esc_html( __( 'Sorry, you are not allowed to access this form. Please login to the system to access it.', 'simple-stack' ) ); ?></p>
<?php } ?>
