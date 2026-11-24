<?php
/**
 * Modified for Simple Stack by One Dog Solutions on 2026-11-23.
 *
 * Simple Stack REST API registrar.
 *
 * @package simple-stack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SSTK_REST_API
 *
 * Loads and registers the six admin SPA controllers under simple-stack/v1.
 *
 * The WordPress App controller family and the independently authenticated command
 * and webhook callbacks register their own routes under the same namespace.
 */
class SSTK_REST_API {

	/**
	 * Admin SPA controllers loaded by this registrar.
	 *
	 * Order matters only for readability; each controller registers its own routes.
	 *
	 * @var array
	 */
	protected $controllers = array(
		'SSTK_REST_Servers',
		'SSTK_REST_Sites',
		'SSTK_REST_Logs',
		'SSTK_REST_Tabs',
		'SSTK_REST_Providers',
		'SSTK_REST_Security',
	);

	/**
	 * Instantiated controllers, keyed by class name.
	 *
	 * @var array
	 */
	protected $instances = array();

	/**
	 * Wire the namespace up.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Include the controller files.
	 *
	 * Kept separate from construction so the file list can be loaded during
	 * required_files() without instantiating anything before WordPress is ready.
	 *
	 * @return void
	 */
	public static function load_files() {

		$path = SSTK_PATH . 'includes/core/api/';

		require_once $path . 'class-sstk-secret-redactor.php';
		require_once $path . 'class-sstk-rest-controller-base.php';
		require_once $path . 'class-sstk-rest-servers.php';
		require_once $path . 'class-sstk-rest-sites.php';
		require_once $path . 'class-sstk-rest-logs.php';
		require_once $path . 'class-sstk-rest-tabs.php';
		require_once $path . 'class-sstk-rest-providers.php';
		require_once $path . 'class-sstk-rest-security.php';
	}

	/**
	 * Register every controller's routes.
	 *
	 * Action hook: rest_api_init
	 *
	 * @return void
	 */
	public function register_routes() {

		foreach ( $this->controllers as $class_name ) {
			if ( ! class_exists( $class_name ) ) {
				continue;
			}

			$controller = new $class_name();

			if ( ! $controller instanceof SSTK_REST_Controller_Base ) {
				continue;
			}

			$this->instances[ $class_name ] = $controller;
			$controller->register_routes();
		}
	}

	/**
	 * Expose the instantiated controllers for tests and diagnostics.
	 *
	 * @return array
	 */
	public function get_controllers(): array {
		return $this->instances;
	}

	/**
	 * Return the namespace without instantiating anything.
	 *
	 * @return string
	 */
	public static function get_namespace(): string {
		return SSTK_REST_Controller_Base::NAMESPACE;
	}
}

/**
 * Return the shared Simple Stack REST API instance.
 *
 * @return SSTK_REST_API
 */
function sstk_rest_api() {

	static $instance = null;

	if ( null === $instance ) {
		$instance = new SSTK_REST_API();
	}

	return $instance;
}
