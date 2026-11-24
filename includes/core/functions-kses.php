<?php
/** Modified for Simple Stack by One Dog Solutions on 2026-11-23. */
/**
 * This file contains general or helper functions for SSTK
 * that return data filtered for various KSES parameters.
 *
 * @package simple-stack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns a string with array appropriate for filtering SELECT input fields.
 *
 * @since 5.7.
 */
function sstk_kses_allowed_select() {

	$allowed_html['select'] = array(
		'class'    => true,
		'id'       => true,
		'name'     => true,
		'multiple' => true,
		'size'     => true,
		'disabled' => true,
		'readonly' => true,
		'required' => true,
	);
	$allowed_html['option'] = array(
		'value'    => true,
		'selected' => true,
		'disabled' => true,
		'label'    => true,
	);

	return $allowed_html;

}

/**
 * Returns a string filtered by wp_kses for allowed SELECT input fields.
 *
 * This is generally used by routines that paint the filter bars.
 *
 * @since 5.7.
 *
 * @param string $data String to filter.
 */
function sstk_kses_select( $data ) {

	return wp_kses( $data, sstk_kses_allowed_select() );

}
