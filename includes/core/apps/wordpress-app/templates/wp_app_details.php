<?php
/** Modified for Simple Stack by One Dog Solutions on 2026-11-23. */
/**
 * Template for showing data in the metabox when viewing the APP CPT in wp-admin
 *
 * @package simple-stack
 */

?>
<style>
	/*******************************************************************/
	/* CSS for WPAPP post meta                                           */
	/*******************************************************************/
	.wpapp_meta_field{
		margin-bottom: 10px;
	}
	.wpapp_meta_field label{
		width: 150px;
		display: inline-block;
	}
	.wpapp_meta_field input[type="text"],
	.wpapp_meta_field select{
		width: 60%;
	}
</style>
<?php
// Add nonce for security and authentication.
wp_nonce_field( 'sstk_wp_app_nonce_meta_action', 'wpapp_meta' );
?>
<div class="sstk_app_meta_warning">
	<?php echo esc_html( __( 'The data shown here is the details of the site at the time it was initially installed. Anything you change here does NOT have any effect on the site. However, it is a good idea to update these values if they have changed on your site.', 'simple-stack' ) ); ?>
</div>
<div class="wpapp_meta_field">
	<label for="sstk_wpapp_domain"><?php echo esc_html( __( 'Domain', 'simple-stack' ) ); ?></label>
	<input type="text" name="sstk_wpapp_domain" id="sstk_wpapp_domain" value="<?php echo esc_attr( $sstk_wpapp_domain ); ?>" />
	<br />
	<small><?php echo esc_html( __( 'Be very very careful about changing this - please consider using our built-in change-domain function instead!  If you do change this here, you migh also need to update the post title field in the APPLICATIONS DETAIL metabox to avoid confusion.', 'simple-stack' ) ); ?></small>
</div>
<div class="wpapp_meta_field">
	<label for="sstk_wpapp_userid"><?php echo esc_html( __( 'Initial Admin User ID', 'simple-stack' ) ); ?></label>
	<input type="text" name="sstk_wpapp_userid" id="sstk_wpapp_userid" value="<?php echo esc_attr( $sstk_wpapp_userid ); ?>" />
	<br />
	<small><?php echo esc_html( __( 'You can change this to match the value you set after the initial install.', 'simple-stack' ) ); ?></small>
</div>
<div class="wpapp_meta_field">
	<label for="sstk_wpapp_email"><?php echo esc_html( __( 'Initial Email Address', 'simple-stack' ) ); ?></label>
	<input type="text" name="sstk_wpapp_email" id="sstk_wpapp_email" value="<?php echo esc_attr( $sstk_wpapp_email ); ?>" />
	<br />
	<small><?php echo esc_html( __( 'You can change this to match the value you set after the initial install.', 'simple-stack' ) ); ?></small>
</div>
<div class="wpapp_meta_field">
	<label for="sstk_wpapp_password"><?php echo esc_html( __( 'Initial Password', 'simple-stack' ) ); ?></label>
	<input type="text" name="sstk_wpapp_password" id="sstk_wpapp_password" spellcheck="false" value="<?php echo esc_attr( $sstk_wpapp_password ); ?>" />
	<br />
	<small><?php echo esc_html( __( 'You can change this to match the value you set after the initial install.', 'simple-stack' ) ); ?></small>
</div>
<div class="wpapp_meta_field">
	<label for="sstk_wpapp_initial_version"><?php echo esc_html( __( 'Initial Installed Version', 'simple-stack' ) ); ?></label>
	<input type="text" name="sstk_wpapp_initial_version" id="sstk_wpapp_initial_version" value="<?php echo esc_attr( $sstk_wpapp_initial_version ); ?>" />
	<br />
	<small><?php echo esc_html( __( 'This is the initial version of WordPress that was installed - it might have been upgraded after that. You can set the current version here to match your installed version.', 'simple-stack' ) ); ?></small>
</div>
<div class="wpapp_meta_field">
	<label for="sstk_wpapp_staging_domain"><?php echo esc_html( __( 'Companion Staging Domain', 'simple-stack' ) ); ?></label>
	<input type="text" name="sstk_wpapp_staging_domain" id="sstk_wpapp_staging_domain" value="<?php echo esc_attr( $sstk_wpapp_staging_domain ); ?>" />
	<br />
	<small><?php echo esc_html( __( 'If a staging domain was created for this site, this is the domain name for it.', 'simple-stack' ) ); ?></small>
</div>
<div class="wpapp_meta_field">
	<label for="sstk_wpapp_staging_domain_id"><?php echo esc_html( __( 'Companion Staging Domain Post ID', 'simple-stack' ) ); ?></label>
	<input type="text" name="sstk_wpapp_staging_domain_id" id="sstk_wpapp_staging_domain_id" value="<?php echo esc_attr( $sstk_wpapp_staging_domain_id ); ?>" />
	<br />
	<small><?php echo esc_html( __( 'If a staging domain was created for this site, this is the post ID where its located.', 'simple-stack' ) ); ?></small>
</div>
