<?php
/**
 * SimpleStack REST API registrar.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleStack_REST_API
 *
 * Loads the SimpleStack controllers and registers the simple-stack/v1 namespace.
 *
 * This is the single API surface the React admin client talks to. It exists
 * alongside the legacy wpcd/v1 controllers, which stay untouched for remote
 * server callbacks and add-on compatibility.
 */
class SimpleStack_REST_API {

	/**
	 * Controllers that make up the namespace.
	 *
	 * Order matters only for readability; each controller registers its own routes.
	 *
	 * @var array
	 */
	protected $controllers = array(
		'SimpleStack_REST_Servers',
		'SimpleStack_REST_Sites',
		'SimpleStack_REST_Logs',
		'SimpleStack_REST_Tabs',
		'SimpleStack_REST_Providers',
		'SimpleStack_REST_Security',
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

		$path = wpcd_path . 'includes/core/api/';

		require_once $path . 'class-simplestack-secret-redactor.php';
		require_once $path . 'class-simplestack-rest-controller-base.php';
		require_once $path . 'class-simplestack-rest-servers.php';
		require_once $path . 'class-simplestack-rest-sites.php';
		require_once $path . 'class-simplestack-rest-logs.php';
		require_once $path . 'class-simplestack-rest-tabs.php';
		require_once $path . 'class-simplestack-rest-providers.php';
		require_once $path . 'class-simplestack-rest-security.php';
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

			if ( ! $controller instanceof SimpleStack_REST_Controller_Base ) {
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
		return SimpleStack_REST_Controller_Base::NAMESPACE;
	}
}

/**
 * Return the shared SimpleStack REST API instance.
 *
 * @return SimpleStack_REST_API
 */
function simplestack_rest_api() {

	static $instance = null;

	if ( null === $instance ) {
		$instance = new SimpleStack_REST_API();
	}

	return $instance;
}
