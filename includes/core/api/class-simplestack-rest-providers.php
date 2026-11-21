<?php
/**
 * SimpleStack REST controller for cloud provider settings.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleStack_REST_Providers
 *
 * The provider settings screen is the one place where the browser talks about
 * credentials, so this controller never returns a credential value. It returns
 * booleans ("is it set"), feature flags and counts, and it encrypts anything the
 * client writes before it reaches the options table.
 */
class SimpleStack_REST_Providers extends SimpleStack_REST_Controller_Base {

	/**
	 * Route segment owned by this controller.
	 *
	 * @var string
	 */
	protected $name = 'settings/providers';

	/**
	 * Path fragment matching a provider slug such as vultr-v2 or digital-ocean.
	 */
	const SLUG_PATH = '/(?P<slug>[a-z0-9-]+)';

	/**
	 * How long region and size counts stay cached, in seconds.
	 */
	const COUNT_CACHE_TTL = 300;

	/**
	 * Feature flags reported for each provider.
	 *
	 * @var array
	 */
	protected $reported_features = array(
		'snapshots',
		'snapshot-delete',
		'snapshot-list',
		'resize',
		'ssh_create',
		'test_connection',
	);

	/**
	 * Register the provider routes.
	 *
	 * @return void
	 */
	public function register_routes() {

		$this->register_get_route(
			$this->name,
			'list_providers',
			array( 'refresh' => $this->arg_int( __( 'Set to 1 to bypass the cached region and size counts.', 'wpcd' ), false, 0, 1, 0 ) )
		);

		$this->register_post_route(
			$this->name,
			'save_provider',
			array(
				'slug'          => $this->arg_string( __( 'Provider slug.', 'wpcd' ), true ),
				'api_key'       => $this->arg_text( __( 'Provider API key. Encrypted before it is stored.', 'wpcd' ) ),
				'user_name'     => $this->arg_string( __( 'Provider account user name, for providers that authenticate with a user name and password instead of an api key. Encrypted before it is stored.', 'wpcd' ) ),
				'user_password' => $this->arg_text( __( 'Provider account password. Encrypted before it is stored.', 'wpcd' ) ),
				'ssh_key_id'    => $this->arg_string( __( 'Provider side id of the registered SSH key.', 'wpcd' ) ),
				'ssh_key'       => $this->arg_text( __( 'Private SSH key. Encrypted before it is stored.', 'wpcd' ) ),
				'notes'         => $this->arg_string( __( 'Free text notes for this credential.', 'wpcd' ) ),
			)
		);

		$this->register_post_route(
			$this->name . self::SLUG_PATH . '/test',
			'test_provider',
			array( 'slug' => $this->arg_string( __( 'Provider slug.', 'wpcd' ), true ) )
		);
	}

	/**
	 * List every configured provider with redacted credential state.
	 *
	 * GET /settings/providers
	 *
	 * @param WP_REST_Request $request The incoming request.
	 *
	 * @return WP_REST_Response
	 */
	public function list_providers( WP_REST_Request $request ) {

		$refresh = (int) $request->get_param( 'refresh' );
		$items   = array();

		foreach ( WPCD()->get_cloud_providers() as $slug => $label ) {
			$items[] = $this->describe_provider( (string) $slug, (string) $label, 1 === $refresh );
		}

		return $this->respond(
			array(
				'items'  => $items,
				'regions' => $this->region_summary(),
			)
		);
	}

	/**
	 * Persist provider credentials.
	 *
	 * POST /settings/providers
	 *
	 * @param WP_REST_Request $request The incoming request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_provider( WP_REST_Request $request ) {

		$slug = (string) $request->get_param( 'slug' );

		if ( ! array_key_exists( $slug, WPCD()->get_cloud_providers() ) ) {
			return $this->error( 'simplestack_invalid_provider', __( 'Unknown provider.', 'wpcd' ), 400 );
		}

		$api          = WPCD()->get_provider_api( $slug );
		$uses_api_key = empty( $api ) ? true : $api->uses_api_key();

		/*
		 * Not every provider authenticates with an api key - UpCloud uses an account user
		 * name and password.  Storing a supplied api_key under `vpn_upcloud_apikey` would
		 * put the password somewhere no part of the provider ever reads again, so write
		 * only the keys that match the provider's credential shape.
		 */
		$api_key = (string) $request->get_param( 'api_key' );
		$ssh_key = (string) $request->get_param( 'ssh_key' );

		/* An empty field means "leave what is stored alone" so a masked value is never written back. */
		if ( $uses_api_key ) {
			if ( ! empty( $api_key ) ) {
				wpcd_set_option( "vpn_{$slug}_apikey", WPCD()->encrypt( $api_key ) );
			}
		} else {
			$user_name = (string) $request->get_param( 'user_name' );
			if ( ! empty( $user_name ) ) {
				wpcd_set_option( "vpn_{$slug}_user_name", WPCD()->encrypt( $user_name ) );
			}

			$user_password = (string) $request->get_param( 'user_password' );
			if ( ! empty( $user_password ) ) {
				wpcd_set_option( "vpn_{$slug}_user_password", WPCD()->encrypt( $user_password ) );
			}
		}

		if ( ! empty( $ssh_key ) ) {
			wpcd_set_option( "vpn_{$slug}_sshkey", WPCD()->encrypt( $ssh_key ) );
		}

		$ssh_key_id = (string) $request->get_param( 'ssh_key_id' );
		if ( ! empty( $ssh_key_id ) ) {
			wpcd_set_option( "vpn_{$slug}_sshkey_id", $ssh_key_id );
		}

		$notes = (string) $request->get_param( 'notes' );
		if ( '' !== $notes ) {
			wpcd_set_option( "vpn_{$slug}_keynotes", $notes );
		}

		return $this->respond( $this->describe_provider( $slug, (string) WPCD()->get_cloud_providers()[ $slug ], true ) );
	}

	/**
	 * Test the connection to a provider.
	 *
	 * POST /settings/providers/{slug}/test
	 *
	 * @param WP_REST_Request $request The incoming request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function test_provider( WP_REST_Request $request ) {

		$slug = (string) $request->get_param( 'slug' );
		$api  = WPCD()->get_provider_api( $slug );

		if ( empty( $api ) ) {
			return $this->error( 'simplestack_provider_unavailable', __( 'That provider is not installed in this build.', 'wpcd' ), 400 );
		}

		if ( ! $api->credentials_available() ) {
			return $this->error( 'simplestack_provider_credentials_missing', __( 'The provider credentials are incomplete. Add the API key and SSH key first.', 'wpcd' ), 400 );
		}

		$result = $api->call( 'test_connection', array() );

		if ( is_wp_error( $result ) ) {
			return $this->respond(
				array(
					'slug'      => $slug,
					'connected' => false,
					'message'   => $result->get_error_message(),
				)
			);
		}

		return $this->respond(
			array(
				'slug'      => $slug,
				'connected' => true,
				'message'   => __( 'Connection succeeded.', 'wpcd' ),
			)
		);
	}

	/**
	 * Build the redacted description of one provider.
	 *
	 * @param string $slug    Provider slug.
	 * @param string $label   Display label.
	 * @param bool   $refresh Whether to bypass the cached counts.
	 *
	 * @return array
	 */
	protected function describe_provider( string $slug, string $label, bool $refresh ): array {

		$api = WPCD()->get_provider_api( $slug );

		$data = array(
			'slug'                  => $slug,
			'label'                 => $label,
			'available'             => ! empty( $api ),
			'credentials_available' => false,
			'uses_api_key'          => true,
			'has_credentials'       => false,
			'has_api_key'           => false,
			'has_user_password'     => false,
			'has_ssh_key'           => false,
			'has_ssh_key_id'        => false,
			'features'              => array_fill_keys( $this->reported_features, false ),
			'regions_count'         => 0,
			'sizes_count'           => 0,
			'counts_cached'         => false,
		);

		if ( empty( $api ) ) {
			return $data;
		}

		$data['credentials_available'] = (bool) $api->credentials_available();
		$data['uses_api_key']          = (bool) $api->uses_api_key();
		$data['has_credentials']       = (bool) $api->has_credentials();
		$data['has_api_key']           = ! empty( wpcd_get_early_option( "vpn_{$slug}_apikey" ) );
		$data['has_user_password']     = ( ! empty( wpcd_get_early_option( "vpn_{$slug}_user_name" ) ) && ! empty( wpcd_get_early_option( "vpn_{$slug}_user_password" ) ) );
		$data['has_ssh_key']           = ! empty( wpcd_get_early_option( "vpn_{$slug}_sshkey" ) );
		$data['has_ssh_key_id']        = ! empty( wpcd_get_early_option( "vpn_{$slug}_sshkey_id" ) );

		foreach ( $this->reported_features as $feature ) {
			$data['features'][ $feature ] = (bool) $api->get_feature_flag( $feature );
		}

		if ( ! $data['credentials_available'] ) {
			return $data;
		}

		$counts = $this->provider_counts( $slug, $api, $refresh );

		$data['regions_count'] = $counts['regions'];
		$data['sizes_count']   = $counts['sizes'];
		$data['counts_cached'] = $counts['cached'];

		return $data;
	}

	/**
	 * Read region and size counts, using a short lived transient.
	 *
	 * A provider outage must never break the settings screen, so failures are
	 * swallowed and reported as zero counts.
	 *
	 * @param string $slug    Provider slug.
	 * @param object $api     Provider API instance.
	 * @param bool   $refresh Whether to bypass the cache.
	 *
	 * @return array{regions: int, sizes: int, cached: bool}
	 */
	protected function provider_counts( string $slug, $api, bool $refresh ): array {

		$cache_key = 'simplestack_provider_counts_' . $slug;

		if ( ! $refresh ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				return array(
					'regions' => (int) $cached['regions'],
					'sizes'   => (int) $cached['sizes'],
					'cached'  => true,
				);
			}
		}

		$counts = array(
			'regions' => 0,
			'sizes'   => 0,
			'cached'  => false,
		);

		try {
			$regions = $api->call( 'regions', array() );
			if ( is_array( $regions ) ) {
				$counts['regions'] = count( $regions );
			}

			$sizes = $api->call( 'sizes', array() );
			if ( is_array( $sizes ) ) {
				$counts['sizes'] = count( $sizes );
			}
		} catch ( Exception $e ) {
			/* Region and size discovery is advisory; never fail the settings screen. */
			unset( $e );
		}

		set_transient(
			$cache_key,
			array(
				'regions' => $counts['regions'],
				'sizes'   => $counts['sizes'],
			),
			self::COUNT_CACHE_TTL
		);

		return $counts;
	}

	/**
	 * Summarise the stored region cache without exposing provider payloads.
	 *
	 * @return array
	 */
	protected function region_summary(): array {

		$summary = array();

		foreach ( array_keys( WPCD()->get_cloud_providers() ) as $slug ) {
			$cached = get_transient( 'simplestack_provider_counts_' . $slug );

			$summary[ $slug ] = array(
				'regions' => is_array( $cached ) ? (int) $cached['regions'] : 0,
				'sizes'   => is_array( $cached ) ? (int) $cached['sizes'] : 0,
			);
		}

		return $summary;
	}
}
