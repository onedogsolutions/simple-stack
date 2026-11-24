<style>
	/*******************************************************************/
	/* CSS for server post meta                                       */
	/*******************************************************************/
	.sstk_server_meta_warning {
		padding-top: 1em;
		padding-bottom: 1em;
		font-weight: bold;
		font-size: 1.2 em;
		color: #d50000;
	}
	.sstk_server_meta_field {
		margin-bottom: 10px;
	}
	.sstk_server_meta_field label {
		width: 25%;
		display: inline-block;
	}
	label.sstk_server_meta_field_readonly_field_title {
		width: 100%;
		font-weight: bold;
	}
	.sstk_server_meta_field_readonly label {
		margin-top: 0.2em;
	}
	.sstk_server_meta_field input[type="text"],
	.sstk_server_meta_field select{
		width: 60%;
	}
</style>
<?php
/**
 * Add nonce for security and authentication.
 *
 * @package simple-stack
 */

wp_nonce_field( 'sstk_server_nonce_meta_action', 'sstk_meta' );
?>
<div class="sstk_server_meta_warning">
	<?php echo esc_html( __( 'Do not change the data below unless instructed by technical support or you know what you are doing! Incorrect data in these fields can break the link to your server at your cloud provider!', 'simple-stack' ) ); ?>
</div>

<div class="sstk_server_meta_field">
	<label for="sstk_server_name"><?php echo esc_html( __( 'Name', 'simple-stack' ) ); ?></label>
	<input type="text" name="sstk_server_name" id="sstk_server_name" value="<?php echo esc_html( $sstk_server_name ); ?>" />
</div>
<div class="sstk_server_meta_field">
	<label for="sstk_server_title"><?php echo esc_html( __( 'Post Title', 'simple-stack' ) ); ?></label>
	<input type="text" name="sstk_server_title" id="sstk_server_title" value="<?php echo esc_html( $sstk_server_title ); ?>" />
</div>
<div class="sstk_server_meta_field">
	<label for="sstk_server_provider"><?php echo esc_html( __( 'Provider', 'simple-stack' ) ); ?></label>
	<input type="text" name="sstk_server_provider" id="sstk_server_provider" value="<?php echo esc_html( $sstk_server_provider ); ?>" />
</div>
<div class="sstk_server_meta_field">
	<label for="sstk_server_provider_instance_id"><?php echo esc_html( __( 'Provider Instance ID', 'simple-stack' ) ); ?></label>
	<input type="text" name="sstk_server_provider_instance_id" id="sstk_server_provider_instance_id" value="<?php echo esc_html( $sstk_server_provider_instance_id ); ?>" />
</div>
<div class="sstk_server_meta_field">
	<label for="sstk_server_region"><?php echo esc_html( __( 'Region', 'simple-stack' ) ); ?></label>
	<input type="text" name="sstk_server_region" id="sstk_server_region" value="<?php echo esc_html( $sstk_server_region ); ?>" />
</div>
<div class="sstk_server_meta_field">
	<label for="sstk_server_size"><?php echo esc_html( __( 'Size', 'simple-stack' ) ); ?></label>
	<input type="text" name="sstk_server_size" id="sstk_server_size" value="<?php echo esc_html( $sstk_server_size ); ?>" />
</div>
<div class="sstk_server_meta_field">
	<label for="sstk_server_size_raw"><?php echo esc_html( __( 'Size (Raw or Provider Internal Size ID)', 'simple-stack' ) ); ?></label>
	<input type="text" name="sstk_server_size_raw" id="sstk_server_size_raw" value="<?php echo esc_html( $sstk_server_size_raw ); ?>" />
</div>
<div class="sstk_server_meta_field">
	<label for="sstk_server_ipv4"><?php echo esc_html( __( 'IPv4 Address', 'simple-stack' ) ); ?></label>
	<input type="text" name="sstk_server_ipv4" id="sstk_server_ipv4" value="<?php echo esc_html( $sstk_server_ipv4 ); ?>" />
</div>
<div class="sstk_server_meta_field">
	<label for="sstk_server_ipv6"><?php echo esc_html( __( 'IPv6 Address', 'simple-stack' ) ); ?></label>
	<input type="text" name="sstk_server_ipv6" id="sstk_server_ipv6" value="<?php echo esc_html( $sstk_server_ipv6 ); ?>" />
</div>
<div class="sstk_server_meta_field">
	<label><?php echo esc_html( __( 'Version of app scripts used for this server', 'simple-stack' ) ); ?></label>
	<code><?php echo esc_html( $sstk_server_scripts_version ); ?></code>
</div>
<div class="sstk_server_meta_field">
	<label for="sstk_server_init"><?php echo esc_html( __( 'Server init flag', 'simple-stack' ) ); ?></label>
	<input type="text" name="sstk_server_init" id="sstk_server_init" value="<?php echo esc_html( $sstk_server_init ); ?>" />
</div>
<div class="sstk_server_meta_field">
	<label for="sstk_server_initial_app_name"><?php echo esc_html( __( 'Initial App Name', 'simple-stack' ) ); ?></label>
	<input type="text" name="sstk_server_initial_app_name" id="sstk_server_initial_app_name" value="<?php echo esc_html( $sstk_server_initial_app_name ); ?>" />
</div>
<div class="sstk_server_meta_field">
	<label for="sstk_server_plugin_initial_version"><?php echo esc_html( __( 'Initial Plugin Version', 'simple-stack' ) ); ?></label>
	<input type="text" name="sstk_server_plugin_initial_version" id="sstk_server_plugin_initial_version" value="<?php echo esc_html( $sstk_server_plugin_initial_version ); ?>" />
</div>
<div class="sstk_server_meta_field">
	<label for="sstk_server_plugin_updated_version"><?php echo esc_html( __( 'Last Updated Plugin Version', 'simple-stack' ) ); ?></label>
	<input type="text" name="sstk_server_plugin_updated_version" id="sstk_server_plugin_updated_version" value="<?php echo esc_html( $sstk_server_plugin_updated_version ); ?>" />
</div>
<div class="sstk_server_meta_field">
	<label for="sstk_server_server-type"><?php echo esc_html( __( 'Server Type', 'simple-stack' ) ); ?></label>
	<input type="text" name="sstk_server_server-type" id="sstk_server_server-type" value="<?php echo esc_html( $sstk_server_server_type ); ?>" />
</div>
<div class="sstk_server_meta_field">
	<label for="sstk_server_webserver_type"><?php echo esc_html( __( 'Web Server Type', 'simple-stack' ) ); ?></label>
	<input type="text" name="sstk_server_webserver_type" id="sstk_server_webserver_type" value="<?php echo esc_html( $sstk_server_webserver_type ); ?>" />
</div>
<div class="sstk_server_meta_field">
	<label for="sstk_server_created"><?php echo esc_html( __( 'Create Date', 'simple-stack' ) ); ?></label>
	<input type="text" name="sstk_server_created" id="sstk_server_created" value="<?php echo esc_html( $sstk_server_created ); ?>" />
</div>
<div class="sstk_server_meta_field">
	<label for="sstk_server_parent_post_id"><?php echo esc_html( __( 'Parent ID', 'simple-stack' ) ); ?></label>
	<input type="text" name="sstk_server_parent_post_id" id="sstk_server_parent_post_id" value="<?php echo esc_html( $sstk_server_parent_post_id ); ?>" />
</div>
<div class="sstk_server_meta_field">
	<label for="sstk_server_after_create_action_app_id"><?php echo esc_html( __( 'After Server Create Action APP ID', 'simple-stack' ) ); ?></label>
	<input type="text" name="sstk_server_after_create_action_app_id" id="sstk_server_after_create_action_app_id" value="<?php echo esc_html( $sstk_server_after_create_action_app_id ); ?>" />
</div>
<div class="sstk_server_meta_field">
	<label for="sstk_server_action_status"><?php echo esc_html( __( 'Action Status', 'simple-stack' ) ); ?></label>
	<input type="text" name="sstk_server_action_status" id="sstk_server_action_status" value="<?php echo esc_html( $sstk_server_action_status ); ?>" />
</div>
<div class="sstk_server_meta_field">
	<label for="sstk_server_action"><?php echo esc_html( __( 'Action', 'simple-stack' ) ); ?></label>
	<input type="text" name="sstk_server_action" id="sstk_server_action" value="<?php echo esc_html( $sstk_server_action ); ?>" />
</div>
<div class="sstk_server_meta_field">
	<label for="sstk_server_command_mutex"><?php echo esc_html( __( 'Command Mutex', 'simple-stack' ) ); ?></label>
	<input type="text" name="sstk_server_command_mutex" id="sstk_server_command_mutex" value="<?php echo esc_html( $sstk_server_command_mutex ); ?>" />
</div>
<div class="sstk_server_meta_field">
	<label for="sstk_server_last_upgrade_done"><?php echo esc_html( __( 'Last Upgrade Version', 'simple-stack' ) ); ?></label>
	<input type="text" name="sstk_server_last_upgrade_done" id="sstk_server_last_upgrade_done" value="<?php echo esc_html( $sstk_server_last_upgrade_done ); ?>" />
</div>

<hr />

<!-- These fields are array/serialized read-only fields -->
<!-- Do not wrap them in esc_html and esc_url since they contain valid html and sanitization was done earlier. -->
<div class="sstk_server_meta_field sstk_server_meta_field_readonly">
	<label class='sstk_server_meta_field_readonly_field_title'><?php echo esc_html( __( 'Last Deferred Action Source', 'simple-stack' ) ); ?></label>
	<br />
	<label>
	<?php
	$allowed_html = array(
		'br' => array(),
	);
	echo wp_kses( $sstk_server_last_deferred_action_source, $allowed_html );
	?>
	</label>
</div>
<div class="sstk_server_meta_field sstk_server_meta_field_readonly">
	<label class='sstk_server_meta_field_readonly_field_title'><?php echo esc_html( __( 'Server Actions', 'simple-stack' ) ); ?></label>
	<br />
	<label><?php echo wp_kses( $sstk_server_actions, $allowed_html ); ?></label>
</div>
<!-- End read-only fields -->
