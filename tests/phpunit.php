<?php
/**
 * PHPUnit bootstrap.
 *
 * The WordPress test library comes from `wp-phpunit/wp-phpunit`, and WordPress core itself is
 * installed by `johnpbloch/wordpress` into `vendor/wordpress/wordpress` — the ABSPATH declared
 * in `wp-tests-config.php`. Both arrive via `composer install`.
 *
 * @package wpcd
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = dirname( __DIR__ ) . '/vendor/wp-phpunit/wp-phpunit';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	fwrite(
		STDERR,
		"Could not find the WordPress test library at {$_tests_dir}.\n" .
		"Run `composer install` in the plugin directory first.\n"
	);
	exit( 1 );
}

/*
 * Resolve the config file here rather than through a `<const>` in phpunit.xml: a relative path
 * there would be resolved against whatever directory PHPUnit happened to be started from.
 */
if ( ! defined( 'WP_TESTS_CONFIG_FILE_PATH' ) ) {
	define( 'WP_TESTS_CONFIG_FILE_PATH', dirname( __DIR__ ) . '/wp-tests-config.php' );
}

require_once $_tests_dir . '/includes/functions.php';

/*
 * Load SimpleStack the way the WordPress test suite documents for a single plugin: as if it
 * were an mu-plugin. That keeps the plugin out of the test site's `active_plugins` option,
 * which is rebuilt from scratch on every run.
 */
tests_add_filter(
	'muplugins_loaded',
	function () {
		require dirname( __DIR__ ) . '/wpcd.php';
	}
);

/*
 * The legacy `wpcd/v1` controllers register only when this setting is on, and the tests under
 * `tests/phpunit/rest-api/` exercise them. `plugins_loaded` fires while the test library's
 * bootstrap runs below — after the plugin has been loaded and long before `rest_api_init`.
 */
tests_add_filter(
	'plugins_loaded',
	function () {
		$wpcd_settings = get_option( 'wpcd_settings' );
		$wpcd_settings = is_array( $wpcd_settings ) ? $wpcd_settings : array();

		$wpcd_settings['wordpress_app_rest_api_enable'] = true;

		update_option( 'wpcd_settings', $wpcd_settings );
	}
);

require $_tests_dir . '/includes/bootstrap.php';
