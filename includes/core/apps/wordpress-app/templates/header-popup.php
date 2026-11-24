<?php
/** Modified for Simple Stack by One Dog Solutions on 2026-11-23. */
/**
 * Header Popup
 *
 * @package simple-stack
 */

$no_logo = (bool) sstk_get_option( 'wordpress_app_noshow_logo' );
if ( ( ( ! defined( 'SSTK_WPAPP_SKIP_POPUP_HEADER' ) ) || ( defined( 'SSTK_WPAPP_SKIP_POPUP_HEADER' ) && ! SSTK_WPAPP_SKIP_POPUP_HEADER ) ) && ! $no_logo ) {
	?>	
	<div class="sstk-header">
		<div class="sstk-header-wrap">
			<div class="sstk-header-title"><img src=" <?php echo esc_url( apply_filters( 'sstk_popup_header_logo', SSTK_URL . '/assets/images/sstk-logo.svg' ) ); ?>" alt="Simple Stack Logo" width="300" height="75"></div>
		</div>
	</div>
	<?php
}
?>
