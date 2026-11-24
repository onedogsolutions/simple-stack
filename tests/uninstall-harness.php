<?php
/**
 * Focused uninstall lifecycle harness for Simple Stack.
 *
 * This standalone harness stubs the WordPress APIs used by uninstall.php so the
 * cleanup SQL, multisite loop, cron discovery and role/capability removal can be
 * verified without a MySQL server. It is not a substitute for the WordPress
 * integration suite.
 *
 * @package simple-stack
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'WP_UNINSTALL_PLUGIN', true );
define( 'SSTK_PATH', dirname( __DIR__ ) . '/' );
define( 'SSTK_URL', 'https://example.test/wp-content/plugins/simple-stack/' );
define( 'SSTK_PLUGIN', 'simple-stack/simple-stack.php' );

$GLOBALS['sstk_test_blog_id'] = 1;
$GLOBALS['sstk_test_queries'] = array();
$GLOBALS['sstk_test_cleared_hooks'] = array();
$GLOBALS['sstk_test_removed_roles'] = array();
$GLOBALS['sstk_test_roles']       = array();
$GLOBALS['sstk_test_users']       = array();
$GLOBALS['sstk_test_cache_flushes'] = 0;

class SSTK_Uninstall_Harness_WPDB {
	public $prefix = 'wp_';
	public $options = 'wp_options';
	public $sitemeta = 'wp_sitemeta';
	public $posts = 'wp_posts';
	public $comments = 'wp_comments';
	public $commentmeta = 'wp_commentmeta';
	public $postmeta = 'wp_postmeta';
	public $term_relationships = 'wp_term_relationships';
	public $term_taxonomy = 'wp_term_taxonomy';
	public $termmeta = 'wp_termmeta';
	public $usermeta = 'wp_usermeta';
	public $terms = 'wp_terms';

	public function esc_like( $value ) {
		return addcslashes( $value, '_%\\' );
	}

	public function prepare( $query, ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}

		foreach ( $args as $value ) {
			$replacement = '%d' === substr( $query, strpos( $query, '%' ), 2 )
				? (int) $value
				: "'" . addslashes( (string) $value ) . "'";
			$query       = preg_replace( '/%[sd]/', $replacement, $query, 1 );
		}

		return $query;
	}

	public function query( $query ) {
		$GLOBALS['sstk_test_queries'][] = array(
			'blog'  => $GLOBALS['sstk_test_blog_id'],
			'query' => $query,
		);

		return 1;
	}

	public function get_col( $query ) {
		if ( str_starts_with( $query, 'SELECT ID FROM ' ) ) {
			return array( 101, 102 );
		}

		if ( str_starts_with( $query, 'SELECT comment_ID FROM ' ) ) {
			return array( 201 );
		}

		if ( str_starts_with( $query, 'SELECT DISTINCT term_id FROM ' ) ) {
			return array( 301 );
		}

		if ( str_starts_with( $query, 'SELECT term_id FROM ' ) ) {
			return array();
		}

		if ( str_starts_with( $query, 'SHOW TABLES LIKE ' ) ) {
			return array(
				$this->prefix . 'sstk_restore_files',
				$this->prefix . 'sstk_permission_types',
				$this->prefix . 'other_sstk_table',
			);
		}

		return array();
	}
}

class SSTK_Uninstall_Harness_Role {
	public $name;
	public $capabilities;

	public function __construct( $name, $capabilities ) {
		$this->name         = $name;
		$this->capabilities = $capabilities;
	}

	public function remove_cap( $capability ) {
		unset( $this->capabilities[ $capability ] );
	}
}

class SSTK_Uninstall_Harness_User {
	public $caps;
	public $roles;

	public function __construct() {
		$this->caps  = array(
			'sstkadmin'       => true,
			'sstk_manage_all' => true,
			'edit_posts'      => true,
		);
		$this->roles = array( 'sstkadmin' => true );
	}

	public function remove_role( $role ) {
		unset( $this->roles[ $role ], $this->caps[ $role ] );
	}

	public function remove_cap( $capability ) {
		unset( $this->caps[ $capability ] );
	}
}

$GLOBALS['wpdb'] = new SSTK_Uninstall_Harness_WPDB();

function is_multisite() {
	return true;
}

function get_current_blog_id() {
	return $GLOBALS['sstk_test_blog_id'];
}

function get_sites( $args ) {
	return array( 1, 2 );
}

function switch_to_blog( $blog_id ) {
	$GLOBALS['sstk_test_blog_id'] = $blog_id;
	$GLOBALS['wpdb']->prefix      = 'wp_' . $blog_id . '_';

	foreach ( array( 'options', 'posts', 'comments', 'commentmeta', 'postmeta', 'term_relationships', 'term_taxonomy', 'termmeta', 'usermeta', 'terms' ) as $table ) {
		$GLOBALS['wpdb']->$table = $GLOBALS['wpdb']->prefix . $table;
	}

	$GLOBALS['wpdb']->options  = $GLOBALS['wpdb']->prefix . 'options';
	$GLOBALS['wpdb']->sitemeta = 'wp_sitemeta';

	return true;
}

function restore_current_blog() {
	switch_to_blog( 1 );
}

function get_option( $name, $default = false ) {
	if ( 'cron' !== $name ) {
		return $default;
	}

	return array(
		time() => array(
			'sstk_wordpress_file_watcher' => array(),
			'sstk_future_hook'            => array(),
			'other_plugin_hook'           => array(),
		),
	);
}

function wp_clear_scheduled_hook( $hook, ...$args ) {
	$GLOBALS['sstk_test_cleared_hooks'][] = $hook;
}

function wp_cache_flush() {
	++$GLOBALS['sstk_test_cache_flushes'];
}

function remove_role( $role ) {
	$GLOBALS['sstk_test_removed_roles'][] = $role;
}

function get_users( $args ) {
	return array( 11, 12 );
}

function get_user_by( $field, $value ) {
	if ( ! isset( $GLOBALS['sstk_test_users'][ $value ] ) ) {
		$GLOBALS['sstk_test_users'][ $value ] = new SSTK_Uninstall_Harness_User();
	}

	return $GLOBALS['sstk_test_users'][ $value ];
}

function wp_roles() {
	return (object) array(
		'roles' => array(
			'administrator' => array(),
			'custom_role'   => array(),
			'other_role'    => array(),
		),
	);
}

function get_role( $name ) {
	if ( isset( $GLOBALS['sstk_test_roles'][ $name ] ) ) {
		return $GLOBALS['sstk_test_roles'][ $name ];
	}

	$roles = array(
		'administrator' => array(
			'sstk_manage_all' => true,
			'edit_posts'      => true,
		),
		'custom_role' => array(
			'sstk_custom_cap' => true,
			'read'           => true,
		),
		'other_role' => array(
			'read' => true,
		),
	);

	$GLOBALS['sstk_test_roles'][ $name ] = new SSTK_Uninstall_Harness_Role( $name, $roles[ $name ] );

	return $GLOBALS['sstk_test_roles'][ $name ];
}

function sstk_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

require dirname( __DIR__ ) . '/uninstall.php';

$queries = $GLOBALS['sstk_test_queries'];
$all_sql = implode( "\n", array_column( $queries, 'query' ) );

sstk_test_assert( str_contains( $all_sql, 'DELETE FROM wp_sitemeta' ), 'Network options were not cleaned.' );
sstk_test_assert( in_array( 'sstk_wordpress_file_watcher', $GLOBALS['sstk_test_cleared_hooks'], true ), 'Known cron hook was not cleared.' );
sstk_test_assert( in_array( 'sstk_future_hook', $GLOBALS['sstk_test_cleared_hooks'], true ), 'Unknown prefixed cron hook was not cleared.' );
sstk_test_assert( ! in_array( 'other_plugin_hook', $GLOBALS['sstk_test_cleared_hooks'], true ), 'Unrelated cron hook was cleared.' );

foreach ( array( 'sstkadmin', 'sstkmanager', 'sstksysadmin', 'sstkappmanager' ) as $role ) {
	sstk_test_assert( in_array( $role, $GLOBALS['sstk_test_removed_roles'], true ), "Role {$role} was not removed." );
}

foreach ( array( 1, 2 ) as $blog_id ) {
	$blog_queries = array_filter(
		$queries,
		static function ( $entry ) use ( $blog_id ) {
			return $blog_id === $entry['blog'];
		}
	);
	$blog_sql     = implode( "\n", array_column( $blog_queries, 'query' ) );
	$prefix       = 'wp_' . $blog_id . '_';

	sstk_test_assert( str_contains( $blog_sql, "DELETE FROM {$prefix}options" ), "Options were not cleaned for blog {$blog_id}." );
	sstk_test_assert( str_contains( $blog_sql, "DELETE FROM {$prefix}posts" ), "Posts were not cleaned for blog {$blog_id}." );
	sstk_test_assert( str_contains( $blog_sql, "DELETE FROM {$prefix}postmeta" ), "Post metadata was not cleaned for blog {$blog_id}." );
	sstk_test_assert( str_contains( $blog_sql, "DELETE FROM {$prefix}usermeta" ), "User metadata was not cleaned for blog {$blog_id}." );
	sstk_test_assert( str_contains( $blog_sql, "DELETE FROM {$prefix}comments" ), "Comments were not cleaned for blog {$blog_id}." );
	sstk_test_assert( str_contains( $blog_sql, "DELETE FROM {$prefix}commentmeta" ), "Comment metadata was not cleaned for blog {$blog_id}." );
	sstk_test_assert( str_contains( $blog_sql, "DELETE FROM {$prefix}term_relationships" ), "Term relationships were not cleaned for blog {$blog_id}." );
	sstk_test_assert( str_contains( $blog_sql, "DELETE FROM {$prefix}term_taxonomy" ), "Taxonomies were not cleaned for blog {$blog_id}." );
	sstk_test_assert( str_contains( $blog_sql, "DELETE FROM {$prefix}termmeta" ), "Term metadata was not cleaned for blog {$blog_id}." );
	sstk_test_assert( str_contains( $blog_sql, "DELETE FROM {$prefix}terms" ), "Orphaned terms were not cleaned for blog {$blog_id}." );
	sstk_test_assert( str_contains( $blog_sql, "DROP TABLE IF EXISTS `{$prefix}sstk_restore_files`" ), "Reserved table was not dropped for blog {$blog_id}." );
	sstk_test_assert( str_contains( $blog_sql, "DROP TABLE IF EXISTS `{$prefix}sstk_permission_types`" ), "Permission table was not dropped for blog {$blog_id}." );
	sstk_test_assert( ! str_contains( $blog_sql, "DROP TABLE IF EXISTS `{$prefix}other_sstk_table`" ), "Unrelated table was dropped for blog {$blog_id}." );
}

$administrator = get_role( 'administrator' );
$custom_role   = get_role( 'custom_role' );
$other_role    = get_role( 'other_role' );

sstk_test_assert( array( 'edit_posts' => true ) === $administrator->capabilities, 'Administrator retained a reserved capability or lost an unrelated capability.' );
sstk_test_assert( array( 'read' => true ) === $custom_role->capabilities, 'Custom role retained a reserved capability or lost an unrelated capability.' );
sstk_test_assert( array( 'read' => true ) === $other_role->capabilities, 'Unrelated role changed.' );

foreach ( array( 11, 12 ) as $user_id ) {
	$user = $GLOBALS['sstk_test_users'][ $user_id ];
	sstk_test_assert( empty( $user->roles ), "User {$user_id} retained a Simple Stack role." );
	sstk_test_assert( array( 'edit_posts' => true ) === $user->caps, "User {$user_id} retained a reserved role or capability." );
}
sstk_test_assert( 3 === $GLOBALS['sstk_test_cache_flushes'], 'Multisite cleanup did not flush caches after each blog and at completion.' );

echo "Simple Stack uninstall lifecycle harness: OK\n";
