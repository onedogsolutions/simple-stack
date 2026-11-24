<?php
/**
 * Modified for Simple Stack by One Dog Solutions on 2026-11-23.
 *
 * Base controller for the Simple Stack REST layer.
 *
 * @package simple-stack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SSTK_REST_Controller_Base
 *
 * Provides the admin SPA permission callback, response envelope and argument
 * helpers that keep every registered parameter paired with a sanitize_callback
 * and a validate_callback.
 *
 * Controllers extending this base require manage_options. Other controller families
 * and independently authenticated callbacks share the namespace without extending
 * this class.
 */
abstract class SSTK_REST_Controller_Base {

	/**
	 * REST namespace shared by the admin SPA controller family.
	 */
	const NAMESPACE = 'simple-stack/v1';

	/**
	 * Reusable path fragment for a numeric resource id.
	 */
	const RESOURCE_ID_PATH = '/(?P<id>\d+)';

	/**
	 * Maximum page size accepted by any list route.
	 */
	const MAX_PER_PAGE = 200;

	/**
	 * Route segment owned by the controller, e.g. "servers".
	 *
	 * @var string
	 */
	protected $name = '';

	/**
	 * Register the controller's routes.
	 *
	 * @return void
	 */
	abstract public function register_routes();

	/**
	 * Permission callback shared by every controller extending this base.
	 *
	 * @return bool
	 */
	public function permissions_check() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Register a single route.
	 *
	 * @param string $route  Route pattern relative to the namespace.
	 * @param string $method One of GET, POST, PUT, PATCH, DELETE.
	 * @param string $action Method on this controller that handles the request.
	 * @param array  $args   Registered argument definitions.
	 *
	 * @return bool
	 */
	protected function register_route( string $route, string $method, string $action, array $args = array() ): bool {

		return register_rest_route(
			static::NAMESPACE,
			$route,
			array(
				'methods'             => $method,
				'callback'            => array( $this, $action ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => $args,
			)
		);
	}

	/**
	 * Register a GET route.
	 *
	 * @param string $route  Route pattern.
	 * @param string $action Handler method.
	 * @param array  $args   Registered argument definitions.
	 *
	 * @return bool
	 */
	protected function register_get_route( string $route, string $action, array $args = array() ): bool {
		return $this->register_route( $route, 'GET', $action, $args );
	}

	/**
	 * Register a POST route.
	 *
	 * @param string $route  Route pattern.
	 * @param string $action Handler method.
	 * @param array  $args   Registered argument definitions.
	 *
	 * @return bool
	 */
	protected function register_post_route( string $route, string $action, array $args = array() ): bool {
		return $this->register_route( $route, 'POST', $action, $args );
	}

	/**
	 * Register a DELETE route.
	 *
	 * @param string $route  Route pattern.
	 * @param string $action Handler method.
	 * @param array  $args   Registered argument definitions.
	 *
	 * @return bool
	 */
	protected function register_delete_route( string $route, string $action, array $args = array() ): bool {
		return $this->register_route( $route, 'DELETE', $action, $args );
	}

	/**
	 * Build the standard response envelope.
	 *
	 * Every payload is passed through the secret redactor, so an accidental
	 * credential in a provider payload or a log line never reaches the browser.
	 *
	 * @param mixed $data   The response payload.
	 * @param int   $status HTTP status code.
	 *
	 * @return WP_REST_Response
	 */
	protected function respond( $data, int $status = 200 ): WP_REST_Response {

		return new WP_REST_Response(
			array(
				'ok'   => true,
				'data' => SSTK_Secret_Redactor::redact( $data ),
				'meta' => array(
					'request_date' => gmdate( 'c' ),
					'namespace'    => static::NAMESPACE,
				),
			),
			$status
		);
	}

	/**
	 * Build an error response in the same envelope shape as respond().
	 *
	 * @param string $code    Machine readable error code.
	 * @param string $message Human readable message.
	 * @param int    $status  HTTP status code.
	 *
	 * @return WP_Error
	 */
	protected function error( string $code, string $message, int $status = 400 ): WP_Error {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}

	/**
	 * Argument definition for the numeric resource id in the route path.
	 *
	 * @return array
	 */
	protected function arg_id(): array {
		return array(
			'description'       => __( 'Numeric record id.', 'simple-stack' ),
			'type'              => 'integer',
			'required'          => true,
			'sanitize_callback' => 'absint',
			'validate_callback' => static function ( $value ) {
				return is_numeric( $value ) && (int) $value > 0;
			},
		);
	}

	/**
	 * Argument definition for an integer query or body parameter.
	 *
	 * @param string   $description Description shown in the REST index.
	 * @param bool     $required    Whether the parameter is required.
	 * @param int|null $minimum     Inclusive lower bound.
	 * @param int|null $maximum     Inclusive upper bound.
	 * @param int|null $default     Default value, or null to leave the parameter unset.
	 *
	 * @return array
	 */
	protected function arg_int( string $description, bool $required = false, $minimum = null, $maximum = null, ?int $default = null ): array {
		$arg = array(
			'description'       => $description,
			'type'              => 'integer',
			'required'          => $required,
			'sanitize_callback' => 'absint',
			'validate_callback' => static function ( $value ) use ( $minimum, $maximum, $required ) {
				/*
				 * An omitted optional parameter arrives here as null, and the validator is
				 * still called for it. Rejecting null would make the parameter impossible to
				 * omit whenever a minimum was declared - which is what made every unfiltered
				 * `GET /sites` and `GET /activity` fail with "Invalid parameter(s): server_id".
				 */
				if ( null === $value || '' === $value ) {
					return ! $required;
				}
				if ( ! is_numeric( $value ) ) {
					return false;
				}
				$value = (int) $value;
				if ( null !== $minimum && $value < $minimum ) {
					return false;
				}
				if ( null !== $maximum && $value > $maximum ) {
					return false;
				}
				return true;
			},
		);

		if ( null !== $default ) {
			$arg['default'] = $default;
		}

		return $arg;
	}

	/**
	 * Argument definition for a string parameter, optionally constrained to an enum.
	 *
	 * @param string $description Description shown in the REST index.
	 * @param bool   $required    Whether the parameter is required.
	 * @param array  $enum        Allowed values. Empty means "any string".
	 * @param string $default     Default value.
	 *
	 * @return array
	 */
	protected function arg_string( string $description, bool $required = false, array $enum = array(), string $default = '' ): array {
		return array(
			'description'       => $description,
			'type'              => 'string',
			'required'          => $required,
			'default'           => $default,
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => static function ( $value ) use ( $enum, $required ) {
				// Omitted optional parameters arrive as null; see the note in arg_int().
				if ( null === $value ) {
					return ! $required;
				}
				if ( ! is_string( $value ) ) {
					return false;
				}
				if ( empty( $enum ) ) {
					return true;
				}
				return in_array( $value, $enum, true );
			},
		);
	}

	/**
	 * Argument definition for a free-form text parameter that must keep its characters.
	 *
	 * Used for command output, log bodies and anything else where sanitize_text_field
	 * would destroy legitimate content. Validation still rejects non-strings.
	 *
	 * @param string $description Description shown in the REST index.
	 * @param bool   $required    Whether the parameter is required.
	 *
	 * @return array
	 */
	protected function arg_text( string $description, bool $required = false ): array {
		return array(
			'description'       => $description,
			'type'              => 'string',
			'required'          => $required,
			'sanitize_callback' => static function ( $value ) {
				return is_string( $value ) ? $value : '';
			},
			'validate_callback' => static function ( $value ) {
				return is_string( $value );
			},
		);
	}

	/**
	 * Argument definition for an object/map parameter.
	 *
	 * @param string $description Description shown in the REST index.
	 * @param bool   $required    Whether the parameter is required.
	 *
	 * @return array
	 */
	protected function arg_object( string $description, bool $required = false ): array {
		return array(
			'description'       => $description,
			'type'              => 'object',
			'required'          => $required,
			'sanitize_callback' => static function ( $value ) {
				return is_array( $value ) ? $value : array();
			},
			'validate_callback' => static function ( $value ) {
				return is_array( $value );
			},
		);
	}

	/**
	 * Argument definition for the tab-action parameter map.
	 *
	 * The same map arrives as a JSON object from the app and as a JSON string when the
	 * request is multipart (the sFTP and system-user tabs upload a key file, so the
	 * browser has to send FormData and every member becomes a string). No `type` is
	 * declared so both shapes validate.
	 *
	 * @param string $description Description shown in the REST index.
	 *
	 * @return array
	 */
	protected function arg_params( string $description ): array {
		return array(
			'description'       => $description,
			'required'          => false,
			'sanitize_callback' => array( $this, 'sanitize_params' ),
			'validate_callback' => static function ( $value ) {
				return is_array( $value ) || is_string( $value );
			},
		);
	}

	/**
	 * Normalise a params payload into an array.
	 *
	 * @param mixed $value JSON object, JSON string or query string.
	 *
	 * @return array
	 */
	public function sanitize_params( $value ): array {

		if ( is_array( $value ) ) {
			return $value;
		}

		if ( ! is_string( $value ) || '' === $value ) {
			return array();
		}

		$decoded = json_decode( $value, true );

		if ( is_array( $decoded ) ) {
			return $decoded;
		}

		$parsed = array();
		wp_parse_str( $value, $parsed );

		return $parsed;
	}

	/**
	 * Dispatch a tab action through the existing tab_action filter chain.
	 *
	 * Tab handlers read `$_POST['params']`, so the request body is mapped back onto the
	 * superglobals for the duration of the dispatch and restored immediately afterwards.
	 * Two legacy quirks are preserved deliberately because the handlers depend on them:
	 *
	 *  - the SSH key tab reads `ssh-private-key`, `ssh-public-key` and
	 *    `ssh-private-key-password` as individual top-level fields, because
	 *    `wp_parse_args()` mangles multi-line values carried through `params`;
	 *  - the sFTP and system-user tabs read an uploaded key file from `$_FILES['file']`.
	 *
	 * @param int    $id     Server or site post id.
	 * @param string $action Action slug.
	 * @param array  $params Parameter map addressed to `$_POST['params']`.
	 * @param array  $files  Uploaded files keyed by field name.
	 *
	 * @return mixed Whatever the tab handlers return; null when nothing claimed it.
	 */
	protected function dispatch_tab_action( int $id, string $action, array $params, array $files = array() ) {

		$promoted = array( 'ssh-private-key', 'ssh-public-key', 'ssh-private-key-password' );
		$extra    = array();

		foreach ( $promoted as $key ) {
			if ( isset( $params[ $key ] ) ) {
				$extra[ $key ] = (string) $params[ $key ];
				unset( $params[ $key ] );
			}
		}

		/* Snapshot the superglobals so the request cannot leak into later code. */
		$post_backup    = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$request_backup = $_REQUEST; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$files_backup   = $_FILES; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$_POST = array_merge( // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$extra,
			array(
				'id'      => $id,
				'_action' => $action,
				'params'  => $params,
			)
		);

		$_REQUEST = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$_FILES   = array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		foreach ( $files as $key => $file ) {
			if ( is_array( $file ) && isset( $file['tmp_name'] ) ) {
				$_FILES[ sanitize_key( $key ) ] = $file; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			}
		}

		try {
			$result = apply_filters( 'sstk_app_' . SSTK_WORDPRESS_APP()->get_app_name() . '_tab_action', null, $action, $id );
		} finally {
			$_POST    = $post_backup; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$_REQUEST = $request_backup; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$_FILES   = $files_backup; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		return $result;
	}

	/**
	 * Normalise paging parameters into WordPress query arguments.
	 *
	 * @param WP_REST_Request $request The incoming request.
	 *
	 * @return array{page: int, per_page: int, offset: int}
	 */
	protected function paging( WP_REST_Request $request ): array {

		$per_page = (int) $request->get_param( 'per_page' );
		if ( $per_page <= 0 ) {
			$per_page = 25;
		}
		$per_page = min( $per_page, static::MAX_PER_PAGE );

		$page = (int) $request->get_param( 'page' );
		if ( $page <= 0 ) {
			$page = 1;
		}

		return array(
			'page'     => $page,
			'per_page' => $per_page,
			'offset'   => ( $page - 1 ) * $per_page,
		);
	}

	/**
	 * Paging arguments shared by every list route.
	 *
	 * @return array
	 */
	protected function paging_args(): array {
		return array(
			'page'     => $this->arg_int( __( 'Page number, one based.', 'simple-stack' ), false, 1, null, 1 ),
			'per_page' => $this->arg_int( __( 'Records per page.', 'simple-stack' ), false, 1, static::MAX_PER_PAGE, 25 ),
		);
	}
}
