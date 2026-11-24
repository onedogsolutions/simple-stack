<style>
	/* No style defined here - styles should already be loaded by the main application template - see templates/app_details.php */
</style>

<?php
/**
 * Add nonce for security and authentication.
 *
 * @package simple-stack
 */

wp_nonce_field( 'sstk_app_nonce_meta_action', 'app_meta' );
?>
<div class="sstk_app_meta_warning">
	<?php echo esc_html( __( 'Do not change the data below unless instructed by technical support or you know what you are doing! Incorrect data in these fields can break the link to your server and applications at your cloud provider!', 'simple-stack' ) ); ?>
</div>
<div class="sstk_app_metabox_app_name">
	<?php
		/* translators: %s app name */
		echo sprintf( esc_html( __( 'APP Name: %s', 'simple-stack' ) ), esc_html( $this->get_app_name() ) );
	?>
	<br />
</div>

<div class="sstk_app_meta_field">
	<label for="sstk_app_action_status"><?php echo esc_html( __( 'Action Status', 'simple-stack' ) ); ?></label>
	<input type="text" name="sstk_app_action_status" id="sstk_app_action_status" value="<?php echo esc_html( $sstk_app_action_status ); ?>" />
	<br />
	<small><?php echo esc_html( __( 'If a background action is in process for this app, this will not be blank. Generally set to in-progress if something is being processed for this app.', 'simple-stack' ) ); ?></small>
</div>
<div class="sstk_app_meta_field">
	<label for="sstk_app_action"><?php echo esc_html( __( 'Action', 'simple-stack' ) ); ?></label>
	<input type="text" name="sstk_app_action" id="sstk_app_action" value="<?php echo esc_html( $sstk_app_action ); ?>" />
	<br />
	<small><?php echo esc_html( __( 'If a background action is in process for this app, this will not be blank. It will indicate what action needs to be processed next - eg: fetch-logs-from-server', 'simple-stack' ) ); ?></small>
</div>
<div class="sstk_app_meta_field">
	<label for="sstk_app_action_args"><?php echo esc_html( __( 'Action Arguments', 'simple-stack' ) ); ?></label>
	<input type="text" name="sstk_app_action_args" id="sstk_app_action_args" value="<?php echo esc_html( $sstk_app_action_args ); ?>" />
	<br />
	<small><?php echo esc_html( __( 'If a background action is in process for this app, this will not be blank. It will show the arguments for the action - this is a serialized field so if you update it for some reason, separate the elements with commas.', 'simple-stack' ) ); ?></small>
</div>
<div class="sstk_app_meta_field">
	<label for="sstk_app_command_mutex"><?php echo esc_html( __( 'Mutex', 'simple-stack' ) ); ?></label>
	<input type="text" name="sstk_app_command_mutex" id="sstk_app_command_mutex" value="<?php echo esc_html( $sstk_app_command_mutex ); ?>" />
	<br />
	<small><?php echo esc_html( __( 'If a background action is in process for this app, this will not be blank. It is used to make sure other background tasks are not initiated while one is being currently processed.  Example of data here is <em>backup-run-manual:cf08.wpvix.com:873</em>', 'simple-stack' ) ); ?></small>
</div>
<div class="sstk_app_meta_field">
	<label for="sstk_temp_log_id"><?php echo esc_html( __( 'Temp Log Id', 'simple-stack' ) ); ?></label>
	<input type="text" name="sstk_temp_log_id" id="sstk_temp_log_id" value="<?php echo esc_html( $sstk_temp_log_id ); ?>" />
	<br />
	<small><?php echo esc_html( __( 'For some background processes we retrieve the log files from the server in peices. This field helps to keep that straight.', 'simple-stack' ) ); ?></small>
</div>
