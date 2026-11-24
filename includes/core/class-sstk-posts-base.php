<?php
/** Modified for Simple Stack by One Dog Solutions on 2026-11-23. */
/**
 * This class is used as a base class for any class
 * that creates and manages post types.
 *
 * @package simple-stack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SSTK_Posts_Base
 */
class SSTK_Posts_Base {

	/**
	 * SSTK_Posts_Base constructor.
	 */
	public function __construct() {
		// nothing.
	}

	/**
	 * Remove PRIVATE state label from certain custom post types.
	 * Add in any labels defined in the descriptions/notes/labels metabox.
	 *
	 * This function is called from filter hooks defined in descendant classes.
	 *
	 * Filter Hook: display_post_states
	 *
	 * @param array  $states The current states for the CPT record.
	 * @param object $post The post object.
	 *
	 * @return array $states
	 */
	public function display_post_states( $states, $post ) {

		/* Remove PRIVATE state label from certain custom post types */
		if ( 'sstk_app' === get_post_type( $post ) || 'sstk_app_server' === get_post_type( $post ) ) {
			if ( ! boolval( sstk_get_option( 'sstk_show_private_state' ) ) ) {
				if ( isset( $states['private'] ) ) {
					unset( $states['private'] );
				}
			}
		}

		/* Add in any labels defined in the descriptions/notes/labels metabox. */
		if ( 'sstk_app' === get_post_type( $post ) || 'sstk_app_server' === get_post_type( $post ) ) {

			$labels = get_post_meta( $post->ID, 'sstk_labels', true );

			if ( ! empty( $labels ) ) {
				$counter = 0;
				foreach ( $labels as $label ) {
					$counter++;
					$index            = 'sstk_labels_' . (string) $counter; // create a valid array index for the $states array.
					$cssclass         = 'sstk_' . preg_replace( '/\W+/', '', strtolower( wp_strip_all_tags( $label ) ) );  // create a valid css classname to be used with the label.
					$states[ $index ] = '<span class="sstk_custom_state_label ' . $cssclass . '">' . $label . '</span>';
				}
			}
		}

		/* Add in labels if delete protection is on */
		if ( 'sstk_app_server' === get_post_type( $post ) ) {

			$server_delete_protection_status = get_post_meta( $post->ID, 'sstk_server_delete_protection', true );
			if ( ! empty( $server_delete_protection_status ) && '1' === $server_delete_protection_status ) {
				$states['server_delete_protected'] = __( 'Delete Protected', 'simple-stack' );
			}
		}
		if ( 'sstk_app' === get_post_type( $post ) ) {
			$app_delete_protection_status = get_post_meta( $post->ID, 'sstk_app_delete_protection', true );
			if ( ! empty( $app_delete_protection_status ) && '1' === $app_delete_protection_status ) {
				$states['app_delete_protected'] = __( 'Delete Protected', 'simple-stack' );
			}
		}

		return $states;

	}

}
