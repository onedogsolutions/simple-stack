<?php
/** Modified for Simple Stack by One Dog Solutions on 2026-11-23. */
/**
 * This file contains general or helper functions for SSTK
 * when handling metabox related things.
 *
 * It primarily helps with creating CARD wrappers in tabs.
 *
 * The Meta Box field-array builders remain because the tab plugins still describe their
 * fields through the get_tabs filters, which SSTK_REST_Tabs serialises to JSON for
 * the React client. The trait layer that turned those arrays into meta boxes
 * (metaboxes-app.php / metaboxes-server.php) was retired in Phase 4.
 *
 * @package simple-stack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return an ARRAY suitable for a metabox field
 * that has a div wrapper with certain classes.
 *
 * @since 5.7.
 *
 * @param string $tab The tab on which the field will go.
 */
function sstk_start_full_card( $tab ) {

	$field = array(
		'tab'               => $tab,
		'type'              => 'sstk_card_container',
		'column_row_before' => '<div class="sstk-card-group sstk-card-full">',
	);

	return $field;

}

/**
 * Return an ARRAY suitable for a metabox field
 * that has a div wrapper with certain classes.
 *
 * @since 5.7.
 *
 * @param string $tab The tab on which the field will go.
 */
function sstk_start_full_card_no_border( $tab ) {

	$field = array(
		'tab'               => $tab,
		'type'              => 'sstk_card_container',
		'column_row_before' => '<div class="sstk-card-group sstk-card-full-no-border">',
	);

	return $field;

}

/**
 * Return an ARRAY suitable for a metabox field
 * that has a div wrapper with certain classes.
 *
 * @since 5.7.
 *
 * @param string $tab The tab on which the field will go.
 */
function sstk_start_full_card_page_heading( $tab ) {

	$field = array(
		'tab'               => $tab,
		'type'              => 'sstk_card_container',
		'column_row_before' => '<div class="sstk-card-group sstk-card-full-no-border sstk-card-full-page-heading">',
	);

	return $field;

}


/**
 * Return an ARRAY suitable for a metabox field
 * that has a div wrapper with certain classes.
 *
 * @since 5.7.
 *
 * @param string $tab The tab on which the field will go.
 */
function sstk_start_half_card( $tab ) {

	$field = array(
		'tab'               => $tab,
		'type'              => 'sstk_card_container',
		'column_row_before' => '<div class="sstk-card-group sstk-card-half">',
	);

	return $field;

}

/**
 * Return an ARRAY suitable for a metabox field
 * that has a div wrapper with certain classes.
 *
 * @since 5.7.
 *
 * @param string $tab The tab on which the field will go.
 */
function sstk_start_one_third_card( $tab ) {

	$field = array(
		'tab'               => $tab,
		'type'              => 'sstk_card_container',
		'column_row_before' => '<div class="sstk-card-group sstk-card-third">',
	);

	return $field;

}

/**
 * Return an ARRAY suitable for a metabox field
 * that has a div wrapper with certain classes.
 *
 * @since 5.7.
 *
 * @param string $tab The tab on which the field will go.
 */
function sstk_start_two_thirds_card( $tab ) {

	$field = array(
		'tab'               => $tab,
		'type'              => 'sstk_card_container',
		'column_row_before' => '<div class="sstk-card-group sstk-card-two-thirds">',
	);

	return $field;

}

/**
 * Return the closing div for a card.
 *
 * @since 5.7.
 *
 * @param string $tab The tab on which the field will go.
 */
function sstk_end_card( $tab ) {

	$field = array(
		'tab'               => $tab,
		'type'              => 'sstk_card_container',
		'column_row_before' => '</div><!-- .sstk-card-group -->',
	);

	return $field;

}


