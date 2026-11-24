<?php
/** Modified for Simple Stack by One Dog Solutions on 2026-11-23. */
/**
 * This file contains functions that spin up classes that manages post types for the WordPress app.
 *
 * @package simple-stack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create a class var for SSTK_POSTS_Site_Package and
 * add it to the SSTK array of classes for management
 */
add_action( 'init', 'sstk_init_site_package', -10, 1 );
function sstk_init_site_package() {
	if ( function_exists( 'SSTK' ) ) {
		if ( empty( SSTK()->classes['SSTK_SITE_PACKAGE'] ) ) {
			SSTK()->classes['SSTK_SITE_PACKAGE'] = new SSTK_POSTS_Site_Package();
		}
	}
}

/**
 * Return instance of class SSTK_SITE_PACKAGE.
 */
function SSTK_SITE_PACKAGE() {
	if ( empty( SSTK()->classes['SSTK_SITE_PACKAGE'] ) ) {
		sstk_init_site_package();
	}
	return SSTK()->classes['SSTK_SITE_PACKAGE'];
}
