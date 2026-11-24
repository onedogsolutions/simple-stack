<?php
/**
 * Modified for Simple Stack by One Dog Solutions on 2026-11-23.
 *
 * Simple Stack REST controller that exposes the tab schema.
 *
 * @package simple-stack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SSTK_REST_Tabs
 *
 * This is the schema half of the action bridge. Every server and site tab already
 * publishes its controls through four filters, so the React client can render all
 * of them generically instead of reimplementing each screen.
 *
 *   sstk_server_wordpress-app_get_tabnames  → tab list
 *   sstk_server_wordpress-app_get_tabs      → Meta Box field arrays
 *   sstk_app_wordpress-app_get_tabnames     → tab list
 *   sstk_app_wordpress-app_get_tabs         → Meta Box field arrays
 *
 * The field arrays contain closures in a few places and encrypted defaults in
 * others, so everything is filtered down to JSON-safe scalars before it leaves.
 */
class SSTK_REST_Tabs extends SSTK_REST_Controller_Base {

	/**
	 * Field types whose current value must never be shipped to the browser.
	 *
	 * @var array
	 */
	protected $masked_types = array( 'password' );

	/**
	 * Register the schema routes.
	 *
	 * @return void
	 */
	public function register_routes() {

		$this->register_get_route(
			'servers' . static::RESOURCE_ID_PATH . '/tabs',
			'server_tabs',
			array( 'id' => $this->arg_id() )
		);

		$this->register_get_route(
			'sites' . static::RESOURCE_ID_PATH . '/tabs',
			'site_tabs',
			array( 'id' => $this->arg_id() )
		);
	}

	/**
	 * Return the tab schema for a server.
	 *
	 * GET /servers/{id}/tabs
	 *
	 * @param WP_REST_Request $request The incoming request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function server_tabs( WP_REST_Request $request ) {

		$id   = (int) $request->get_param( 'id' );
		$post = get_post( $id );

		if ( ! $post || 'sstk_app_server' !== $post->post_type ) {
			return $this->error( 'sstk_server_not_found', __( 'Server not found.', 'simple-stack' ), 404 );
		}

		$app = SSTK_WORDPRESS_APP()->get_app_name();

		$tabs   = apply_filters( "sstk_server_{$app}_get_tabnames", array(), $id );
		$fields = apply_filters( "sstk_server_{$app}_get_tabs", array(), $id );

		return $this->respond(
			array(
				'context' => 'server',
				'id'      => $id,
				'tabs'    => $this->prepare_tabs( $tabs ),
				'fields'  => $this->prepare_fields( $fields ),
			)
		);
	}

	/**
	 * Return the tab schema for a site.
	 *
	 * GET /sites/{id}/tabs
	 *
	 * @param WP_REST_Request $request The incoming request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function site_tabs( WP_REST_Request $request ) {

		$id   = (int) $request->get_param( 'id' );
		$post = get_post( $id );

		if ( ! $post || 'sstk_app' !== $post->post_type ) {
			return $this->error( 'sstk_site_not_found', __( 'Site not found.', 'simple-stack' ), 404 );
		}

		if ( SSTK_WORDPRESS_APP()->get_app_name() !== get_post_meta( $id, 'app_type', true ) ) {
			return $this->error( 'sstk_site_not_found', __( 'Site not found.', 'simple-stack' ), 404 );
		}

		$app = SSTK_WORDPRESS_APP()->get_app_name();

		$tabs   = apply_filters( "sstk_app_{$app}_get_tabnames", array(), $id );
		$fields = apply_filters( "sstk_app_{$app}_get_tabs", array(), $id );

		return $this->respond(
			array(
				'context'    => 'site',
				'id'         => $id,
				'server_id'  => (int) get_post_meta( $id, 'parent_post_id', true ),
				'tabs'       => $this->prepare_tabs( $tabs ),
				'fields'     => $this->prepare_fields( $fields ),
			)
		);
	}

	/**
	 * Normalise the tab list into a JSON-safe array.
	 *
	 * @param mixed $tabs Raw tab list from the get_tabnames filter.
	 *
	 * @return array
	 */
	protected function prepare_tabs( $tabs ): array {

		if ( ! is_array( $tabs ) ) {
			return array();
		}

		$clean = array();

		foreach ( $tabs as $slug => $tab ) {
			$tab  = is_array( $tab ) ? $tab : array();
			$item = array(
				'slug'  => (string) $slug,
				'label' => isset( $tab['label'] ) ? (string) $tab['label'] : (string) $slug,
				'icon'  => isset( $tab['icon'] ) ? (string) $tab['icon'] : '',
			);

			$clean[] = $item;
		}

		return $clean;
	}

	/**
	 * Normalise a list of field definitions into JSON-safe arrays.
	 *
	 * @param mixed $fields Raw field list from the get_tabs filter.
	 *
	 * @return array
	 */
	protected function prepare_fields( $fields ): array {

		if ( ! is_array( $fields ) ) {
			return array();
		}

		$clean = array();

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$clean[] = $this->prepare_field( $field );
		}

		return $clean;
	}

	/**
	 * Normalise one field definition.
	 *
	 * Closures, objects and resources are dropped because they cannot be encoded
	 * as JSON and are never renderable by the client. Password fields have their
	 * current value blanked so a stored credential is never sent to the browser.
	 *
	 * @param array $field The raw field definition.
	 *
	 * @return array
	 */
	protected function prepare_field( array $field ): array {

		$clean = array();

		foreach ( $field as $key => $value ) {
			if ( is_array( $value ) ) {
				$clean[ $key ] = $this->prepare_field( $value );
				continue;
			}

			if ( is_scalar( $value ) || is_null( $value ) ) {
				$clean[ $key ] = $value;
			}
		}

		$type = isset( $clean['type'] ) && is_string( $clean['type'] ) ? $clean['type'] : '';

		if ( in_array( $type, $this->masked_types, true ) ) {
			$clean['std']     = '';
			$clean['value']   = '';
			$clean['default'] = '';

			if ( isset( $clean['attributes'] ) && is_array( $clean['attributes'] ) ) {
				$clean['attributes']['value'] = '';
			}

			if ( isset( $clean['raw_attributes'] ) && is_array( $clean['raw_attributes'] ) ) {
				$clean['raw_attributes']['std']     = '';
				$clean['raw_attributes']['value']   = '';
				$clean['raw_attributes']['default'] = '';
			}
		}

		return $clean;
	}
}
