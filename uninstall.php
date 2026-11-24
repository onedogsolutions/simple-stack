<?php
/** Modified for Simple Stack by One Dog Solutions on 2026-11-23. */
/**
 * Uninstall Simple Stack.
 *
 * Removes the control-plane data from every site in the network. This hard-cutover
 * fork has no legacy installs to preserve, so there is no data-retention or
 * compatibility gate. Cloud servers and sites themselves are not modified; only
 * their Simple Stack records, credentials, schedules and tables are removed.
 *
 * @package simple-stack
 */

// Exit if accessed directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! defined( 'SSTK_PATH' ) ) {
	define( 'SSTK_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'SSTK_URL' ) ) {
	define( 'SSTK_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'SSTK_PLUGIN' ) ) {
	define( 'SSTK_PLUGIN', plugin_basename( __FILE__ ) );
}

/**
 * Delete all option rows owned by Simple Stack, including its transients.
 */
function sstk_uninstall_delete_options(): void {
	global $wpdb;

	$prefixes = array(
		'sstk_',
		'_transient_sstk_',
		'_transient_timeout_sstk_',
		'_site_transient_sstk_',
		'_site_transient_timeout_sstk_',
	);

	foreach ( $prefixes as $prefix ) {
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( $prefix ) . '%'
			)
		);
	}
}

/**
 * Delete Simple Stack network options when running on multisite.
 */
function sstk_uninstall_delete_network_options(): void {
	global $wpdb;

	if ( ! is_multisite() ) {
		return;
	}

	$prefixes = array(
		'sstk_',
		'_site_transient_sstk_',
		'_site_transient_timeout_sstk_',
	);

	foreach ( $prefixes as $prefix ) {
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s",
				$wpdb->esc_like( $prefix ) . '%'
			)
		);
	}
}

/**
 * Delete all Simple Stack post types and their dependent rows.
 */
function sstk_uninstall_delete_posts(): void {
	global $wpdb;

	$post_ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type LIKE %s",
			$wpdb->esc_like( 'sstk_' ) . '%'
		)
	);

	foreach ( array_chunk( (array) $post_ids, 100 ) as $chunk ) {
		$ids = implode( ',', array_map( 'intval', $chunk ) );

		$comment_ids = $wpdb->get_col( "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_post_ID IN ({$ids})" );
		if ( ! empty( $comment_ids ) ) {
			$comment_id_list = implode( ',', array_map( 'intval', $comment_ids ) );
			$wpdb->query( "DELETE FROM {$wpdb->commentmeta} WHERE comment_id IN ({$comment_id_list})" );
			$wpdb->query( "DELETE FROM {$wpdb->comments} WHERE comment_ID IN ({$comment_id_list})" );
		}

		$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE post_id IN ({$ids})" );
		$wpdb->query( "DELETE FROM {$wpdb->term_relationships} WHERE object_id IN ({$ids})" );
		$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE ID IN ({$ids})" );
	}

	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
			$wpdb->esc_like( 'sstk_' ) . '%'
		)
	);
}

/**
 * Delete Simple Stack taxonomies, terms and term metadata.
 */
function sstk_uninstall_delete_terms(): void {
	global $wpdb;

	$taxonomy_like = $wpdb->esc_like( 'sstk_' ) . '%';
	$term_ids      = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT DISTINCT term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy LIKE %s",
			$taxonomy_like
		)
	);

	$wpdb->query(
		$wpdb->prepare(
			"DELETE relationships FROM {$wpdb->term_relationships} AS relationships
			INNER JOIN {$wpdb->term_taxonomy} AS taxonomy ON taxonomy.term_taxonomy_id = relationships.term_taxonomy_id
			WHERE taxonomy.taxonomy LIKE %s",
			$taxonomy_like
		)
	);
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->term_taxonomy} WHERE taxonomy LIKE %s",
			$taxonomy_like
		)
	);
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE %s",
			$wpdb->esc_like( 'sstk_' ) . '%'
		)
	);

	foreach ( array_chunk( (array) $term_ids, 100 ) as $chunk ) {
		$ids               = implode( ',', array_map( 'intval', $chunk ) );
		$remaining_ids     = $wpdb->get_col( "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE term_id IN ({$ids})" );
		$orphaned_term_ids = array_diff( array_map( 'intval', $chunk ), array_map( 'intval', (array) $remaining_ids ) );

		if ( empty( $orphaned_term_ids ) ) {
			continue;
		}

		$orphaned_ids = implode( ',', array_map( 'intval', $orphaned_term_ids ) );
		$wpdb->query( "DELETE FROM {$wpdb->termmeta} WHERE term_id IN ({$orphaned_ids})" );
		$wpdb->query( "DELETE FROM {$wpdb->terms} WHERE term_id IN ({$orphaned_ids})" );
	}
}

/**
 * Delete prefixed metadata that might sit outside plugin post types.
 */
function sstk_uninstall_delete_metadata(): void {
	global $wpdb;

	$meta_like = $wpdb->esc_like( 'sstk_' ) . '%';
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $meta_like ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", $meta_like ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE %s", $meta_like ) );
}

/**
 * Clear every cron hook scheduled by Simple Stack.
 */
function sstk_uninstall_clear_cron(): void {
	$cron = get_option( 'cron' );
	if ( ! is_array( $cron ) ) {
		return;
	}

	foreach ( $cron as $hooks ) {
		if ( ! is_array( $hooks ) ) {
			continue;
		}

		foreach ( array_keys( $hooks ) as $hook ) {
			if ( str_starts_with( $hook, 'sstk_' ) ) {
				wp_clear_scheduled_hook( $hook );
			}
		}
	}
}

/**
 * Drop every custom table using the reserved Simple Stack table prefix.
 */
function sstk_uninstall_drop_tables(): void {
	global $wpdb;

	$table_prefix = $wpdb->prefix . 'sstk_';
	$tables       = $wpdb->get_col(
		$wpdb->prepare(
			'SHOW TABLES LIKE %s',
			$wpdb->esc_like( $table_prefix ) . '%'
		)
	);

	foreach ( (array) $tables as $table ) {
		if ( ! str_starts_with( $table, $table_prefix ) ) {
			continue;
		}

		$safe_table = str_replace( '`', '``', $table );
		$wpdb->query( "DROP TABLE IF EXISTS `{$safe_table}`" );
	}
}

/**
 * Remove all Simple Stack data for the current blog.
 */
function sstk_uninstall_current_blog(): void {
	sstk_uninstall_clear_cron();
	sstk_uninstall_delete_posts();
	sstk_uninstall_delete_terms();
	sstk_uninstall_delete_metadata();
	sstk_uninstall_delete_options();
	sstk_uninstall_drop_tables();

	require_once SSTK_PATH . 'includes/core/class-sstk-roles-capabilities.php';
	SSTK_ROLES_CAPABILITIES::sstk_delete_roles_capabilities();

	wp_cache_flush();
}

sstk_uninstall_delete_network_options();

$blog_ids = is_multisite()
	? get_sites( array( 'fields' => 'ids', 'number' => 0 ) )
	: array( get_current_blog_id() );

foreach ( (array) $blog_ids as $blog_id ) {
	$switched = is_multisite() ? switch_to_blog( (int) $blog_id ) : false;

	try {
		sstk_uninstall_current_blog();
	} finally {
		if ( $switched ) {
			restore_current_blog();
		}
	}
}

wp_cache_flush();
