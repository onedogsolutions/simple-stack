<style>
	/*******************************************************************/
	/* CSS for APP post meta                                           */
	/*******************************************************************/
	.sstk_app_meta_warning {
		padding-top: 1em;
		padding-bottom: 1em;
		font-weight: bold;
		font-size: 1.2 em;
		color: #d50000;
	}
	.sstk_app_meta_field{
		margin-bottom: 10px;
	}
	.sstk_app_meta_field label{
		width: 150px;
		display: inline-block;
	}
	.sstk_app_meta_field input[type="text"],
	.sstk_app_meta_field select{
		width: 60%;
	}
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
<div class="sstk_app_meta_field">
	<label for="app_post_title"><?php echo esc_html( __( 'Post Title', 'simple-stack' ) ); ?></label>
	<input type="text" name="app_post_title" id="app_post_title" value="<?php echo esc_html( $app_post_title ); ?>" />
</div>
<div class="sstk_app_meta_field">
	<label for="app_type"><?php echo esc_html( __( 'App Type', 'simple-stack' ) ); ?></label>
	<input type="text" name="app_type" id="app_type" value="<?php echo esc_html( $app_type ); ?>" />
</div>
<div class="sstk_app_meta_field">
	<label for="server_name"><?php echo esc_html( __( 'Server Name', 'simple-stack' ) ); ?></label>
	<?php echo esc_html( $server_name ); ?>
</div>
<div class="sstk_app_meta_field">
	<label for="server_provider"><?php echo esc_html( __( 'Provider', 'simple-stack' ) ); ?></label>
	<?php echo esc_html( $server_provider ); ?>
</div>
<div class="sstk_app_meta_field">
	<label for="ipv4"><?php echo esc_html( __( 'IPv4', 'simple-stack' ) ); ?></label>
	<?php echo esc_html( $ipv4 ); ?>
</div>

<div class="sstk_app_meta_field">
	<label for="parent_post_id"><?php echo esc_html( __( 'Parent ID', 'simple-stack' ) ); ?></label>
	<input type="text" name="parent_post_id" id="parent_post_id" value="<?php echo esc_html( $parent_post_id ); ?>" />
	<br />
	<small><?php echo esc_html( __( 'This is the post id of the server record that identifies where this application is installed. Usually it is a link to the server post data.', 'simple-stack' ) ); ?></small>
</div>
