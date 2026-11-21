<?php
/**
 * Class WP Cloud Delpoy
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_CLOUD_DEPLOY
 */
class WP_CLOUD_DEPLOY {

	/**
	 * Holds a reference to this class.
	 *
	 * @var $instance
	 */
	private static $instance;

	/**
	 * Array of providers.
	 * Add more providers here and ensure a class called class-<provider>.php exists in providers folder.
	 * Or, if adding providers via another plugin, use the filter 'wpcd_get_cloud_providers'
	 * that's defined in the getter function later in this class.
	 *
	 * @var $providers
	 */
	private static $providers = array(
		'vultr-v2'      => 'Vultr',
		'upcloud'       => 'UpCloud',
		'digital-ocean' => 'Digital Ocean',
		'custom-server' => 'Custom Server',
	);

	/**
	 * Array of applications in the format array( 'appid' => 'App Desc1', 'appid2' => 'App Desc2' );
	 * This will initially be blank until an app-specific routine sets it upon initialization of its class.
	 *
	 * @var $app_list
	 */
	private $app_list = array();

	/**
	 * Holds a list of classes..
	 *
	 * @var $classes
	 */
	public $classes = array();

	/**
	 * Parsed Vite build manifest, or false once a lookup has failed.
	 *
	 * Initialized to null so a genuinely empty manifest stays distinguishable from
	 * "not read yet".
	 *
	 * @var array|false|null $simplestack_manifest
	 */
	private $simplestack_manifest = null;


	/**
	 * Default encryption key if one is not set in wp-config.php
	 */
	const ENCRYPTION_KEY = '*274C07F32B66FBE3A8CD06718CD6297ADDC156A7';

	/**
	 * Create and return an instance of this class.
	 */
	public static function instance() {
		self::$instance = new self();
		return self::$instance;
	}

	/**
	 * WP_CLOUD_DEPLOY constructor.
	 */
	public function __construct() {
		// Global for backwards compatibility.
		$GLOBALS['WP_CLOUD_DEPLOY'] = $this;

		// Setup custom post types.
		$this->setup_post_types();

		// Load roles and capabilities.
		$this->load_roles_capabilities();

		// Setup WordPress hooks.
		$this->hooks();
	}

	/**
	 * Return an instance of self.
	 *
	 * @return WP_CLOUD_DEPLOY
	 */
	public function get_this() {
		return $this;
	}

	/**
	 * Hook into WordPress and other plugins as needed.
	 */
	private function hooks() {

		// Handle proper admin menu subpages highlighting (current menu).
		add_filter( 'parent_file', array( &$this, 'handle_admin_current_main_menu' ) );
		add_filter( 'submenu_file', array( &$this, 'handle_admin_current_submenu_item' ), 10, 2 );

		// Send legacy CPT bookmarks to the matching React route.
		add_action( 'load-edit.php', array( &$this, 'redirect_legacy_cpt_screens' ) );
		add_action( 'load-post.php', array( &$this, 'redirect_legacy_cpt_screens' ) );

		// Order the SimpleStack submenus once every other component has registered its own.
		add_action( 'admin_menu', array( &$this, 'ensure_submenu_items_order' ), 999 );

		/* Add a global error log handler */
		add_action( 'wpcd_log_error', array( &$this, 'log_error' ), 10, 6 );

		/* Add a global notifications handler */
		add_action( 'wpcd_log_notification', array( &$this, 'log_notification' ), 10, 5 );

		/* Load admin scripts */
		add_action( 'admin_enqueue_scripts', array( &$this, 'wpcd_admin_scripts' ), 10, 1 );

		/* SimpleStack admin app: container page + Vite bundle. */
		add_action( 'admin_menu', array( &$this, 'add_simplestack_menu_page' ), 100 );
		add_action( 'admin_enqueue_scripts', array( &$this, 'enqueue_simplestack_app' ), 20, 1 );

		/* Hide the Digital Ocean provider unless it has been explicitly re-enabled. */
		add_filter( 'wpcd_get_cloud_providers', array( &$this, 'disable_digital_ocean_provider_by_default' ), 10, 1 );

		/* Replace the provider descriptions with white label descriptions */
		add_filter( 'wpcd_get_cloud_providers', array( &$this, 'wpcd_get_cloud_providers_white_label_desc' ), 10, 1 );
	}

	/**
	 * Order the SimpleStack submenus.
	 *
	 * The React screens are registered by this class, but third party components (and the
	 * bundled Meta Box settings page) append their own submenus in between. WordPress has
	 * no ordering argument for submenus, so the list is written back explicitly.
	 *
	 * Hooked to `admin_menu` at a late priority so it runs after every registration and
	 * before WordPress renders the menu.
	 *
	 * Note: This function uses the $submenu global variable directly and therefore might
	 * break in a future version of WP.
	 *
	 * @return void
	 */
	public function ensure_submenu_items_order() {
		global $submenu;

		if ( empty( $submenu['simplestack'] ) || ! is_array( $submenu['simplestack'] ) ) {
			return;
		}

		/*
		 * The order these submenu slugs should appear in. Anything registered by another
		 * component is kept and appended after these, in its existing relative order.
		 */
		$submenus_order = apply_filters(
			'wpcd_main_menu_submenus_order',
			array(
				'simplestack'                                              => 10,
				'simplestack-sites'                                        => 20,
				'simplestack-providers'                                    => 30,
				'simplestack-activity'                                     => 40,
				'edit-tags.php?taxonomy=wpcd_app_group&post_type=wpcd_app'  => 45,
				'edit.php?post_type=wpcd_notify_user'                      => 47,
				'wpcd_settings'                                            => 50,
				'wpcd_faq_and_help'                                        => 60,
			)
		);

		asort( $submenus_order, SORT_NUMERIC );

		$existing  = $submenu['simplestack'];
		$by_slug   = array();
		$ordered   = array();

		foreach ( $existing as $entry ) {
			if ( isset( $entry[2] ) ) {
				$by_slug[ $entry[2] ] = $entry;
			}
		}

		foreach ( array_keys( $submenus_order ) as $slug ) {
			if ( isset( $by_slug[ $slug ] ) ) {
				$ordered[] = $by_slug[ $slug ];
				unset( $by_slug[ $slug ] );
			}
		}

		/* Append anything the filter did not mention. */
		foreach ( $by_slug as $entry ) {
			$ordered[] = $entry;
		}

		$submenu['simplestack'] = $ordered; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	/**
	 * Register the SimpleStack admin menu and its screens.
	 *
	 * Phase 4 replaced the CPT-driven menu: the server and site post types are no longer
	 * `show_in_menu`, so the whole UI lives under one top-level entry whose screens all
	 * mount the React client. Each submenu maps onto a hash route (see
	 * `simplestack_screen_routes()`), which is how a top-level click lands on the right
	 * section without a second routing layer in PHP.
	 *
	 * The bundled Meta Box settings page (`wpcd_settings`) is re-parented here so it stays
	 * reachable once the server CPT menu is hidden.
	 *
	 * Action hook: admin_menu
	 */
	public function add_simplestack_menu_page() {

		add_menu_page(
			'SimpleStack',
			'SimpleStack',
			'wpcd_manage_servers',
			'simplestack',
			array( &$this, 'render_simplestack_app' ),
			'dashicons-cloud',
			3
		);

		$sites_label = defined( 'WPCD_WPAPP_MENU_NAME' ) ? WPCD_WPAPP_MENU_NAME : __( 'Sites', 'wpcd' );

		$pages = array(
			array( 'simplestack', __( 'Servers', 'wpcd' ), 'wpcd_manage_servers' ),
			array( 'simplestack-sites', $sites_label, 'wpcd_manage_apps' ),
			array( 'simplestack-providers', __( 'Providers', 'wpcd' ), 'manage_options' ),
			array( 'simplestack-activity', __( 'Activity', 'wpcd' ), 'wpcd_manage_logs' ),
		);

		foreach ( $pages as $page ) {
			add_submenu_page(
				'simplestack',
				$page[1],
				$page[1],
				$page[2],
				$page[0],
				array( &$this, 'render_simplestack_app' )
			);
		}

		/*
		 * Two native admin screens used to hang off the server post type and the log post
		 * type. Both parents are hidden now, so without an explicit entry site grouping and
		 * alert-recipient configuration would become unreachable. Slugs containing `.php`
		 * are used verbatim by wp-admin, which is how core links taxonomy screens.
		 */
		add_submenu_page(
			'simplestack',
			__( 'App Groups', 'wpcd' ),
			__( 'App Groups', 'wpcd' ),
			'wpcd_manage_groups',
			'edit-tags.php?taxonomy=wpcd_app_group&post_type=wpcd_app'
		);

		add_submenu_page(
			'simplestack',
			__( 'Notifications', 'wpcd' ),
			__( 'Notifications', 'wpcd' ),
			'wpcd_manage_servers',
			'edit.php?post_type=wpcd_notify_user'
		);

		if ( ! defined( 'WPCD_HIDE_HELP_TAB' ) || ! WPCD_HIDE_HELP_TAB ) {
			add_submenu_page(
				'simplestack',
				__( 'Help', 'wpcd' ),
				__( 'Help', 'wpcd' ),
				'manage_options',
				'wpcd_faq_and_help',
				array( &$this, 'wpcd_get_faq_and_help_text_page_callback' )
			);
		}
	}

	/**
	 * Map each React screen slug onto its initial hash route.
	 *
	 * @return array Page slug => hash route.
	 */
	public function simplestack_screen_routes() {
		return array(
			'simplestack'           => '/servers',
			'simplestack-sites'     => '/sites',
			'simplestack-providers' => '/settings/providers',
			'simplestack-activity'  => '/activity',
		);
	}

	/**
	 * Resolve the hash route for the screen currently being rendered.
	 *
	 * Both the container's `data-route` and the inline bootstrap object carry this so the
	 * client can seed an empty hash on first load without re-reading the query string.
	 *
	 * @return string Hash route, always starting with a slash.
	 */
	public function current_simplestack_route() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen lookup.
		$slug   = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'simplestack';
		$routes = $this->simplestack_screen_routes();

		return isset( $routes[ $slug ] ) ? $routes[ $slug ] : '/servers';
	}

	/**
	 * Print the mount point the React client attaches to.
	 *
	 * The bundle is only enqueued when the Vite manifest is present, so when the
	 * build output is missing we would otherwise render a permanently blank page.
	 * Emit an operator-facing message instead of failing silently.
	 */
	public function render_simplestack_app() {
		if ( ! current_user_can( 'wpcd_manage_servers' ) && ! current_user_can( 'wpcd_manage_apps' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wpcd' ) );
		}

		echo '<div id="simplestack-admin" class="simplestack-root" data-route="' . esc_attr( $this->current_simplestack_route() ) . '"></div>';

		if ( false === $this->get_simplestack_manifest() ) {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'The SimpleStack admin bundle is missing. Run `npm ci && npm run build` in the plugin directory, then reload this page.', 'wpcd' );
			echo '</p></div>';
		}
	}

	/**
	 * Decide whether a given admin page hook belongs to the SimpleStack app.
	 *
	 * Only the screens that print the React container count. The bundled settings and help
	 * pages live under the same parent menu but render their own markup, so the bundle must
	 * not be enqueued for them.
	 *
	 * @param string $hook The admin page hook suffix.
	 *
	 * @return bool True when the current screen is rendered by the React client.
	 */
	public function is_simplestack_screen( $hook ) {
		if ( ! is_string( $hook ) || '' === $hook ) {
			return false;
		}

		static $react_screens = null;

		if ( null === $react_screens ) {
			$react_screens = array( 'toplevel_page_simplestack' );

			foreach ( array_keys( $this->simplestack_screen_routes() ) as $slug ) {
				$react_screens[] = 'simplestack_page_' . $slug;
			}
		}

		return in_array( $hook, $react_screens, true );
	}

	/**
	 * Read and cache the Vite build manifest for this request.
	 *
	 * Returns false when the build output is absent or unreadable. Callers must
	 * treat that as "no bundle" rather than an error: a dev checkout without Node
	 * must never fatal the admin.
	 *
	 * @return array|false Manifest array keyed by source path, or false.
	 */
	protected function get_simplestack_manifest() {
		if ( null !== $this->simplestack_manifest ) {
			return $this->simplestack_manifest;
		}

		$this->simplestack_manifest = false;

		$manifest_path = wpcd_path . 'assets/dist/.vite/manifest.json';

		if ( ! file_exists( $manifest_path ) || ! is_readable( $manifest_path ) ) {
			return false;
		}

		$raw      = file_get_contents( $manifest_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin asset.
		$manifest = ( false === $raw ) ? null : json_decode( $raw, true );

		if ( ! is_array( $manifest ) || empty( $manifest ) ) {
			return false;
		}

		$this->simplestack_manifest = $manifest;

		return $this->simplestack_manifest;
	}

	/**
	 * Extract the cache-busting token Vite embedded in an emitted file name.
	 *
	 * @param string $file Manifest `file` value, e.g. assets/admin-BzxjS_p_.js.
	 *
	 * @return string Hash token, or the plugin version when none is found.
	 */
	protected function get_simplestack_asset_version( $file ) {
		if ( preg_match( '/-([A-Za-z0-9_-]{8,})\.[a-z]+$/', (string) $file, $matches ) ) {
			return $matches[1];
		}

		return wpcd_version;
	}

	/**
	 * Resolve the entry chunk plus every stylesheet the manifest references.
	 *
	 * With `cssCodeSplit: false` Vite publishes the stylesheet as its own manifest
	 * chunk instead of listing it on the entry, so both shapes have to be handled.
	 *
	 * @param array $manifest Parsed manifest.
	 *
	 * @return array{script: string, styles: string[]}|false Entry asset paths, or false.
	 */
	protected function get_simplestack_entry_assets( array $manifest ) {
		$entry_key = '';

		foreach ( array_keys( $manifest ) as $key ) {
			if ( false !== strpos( (string) $key, 'src/admin/index.jsx' ) ) {
				$entry_key = $key;
				break;
			}
		}

		if ( '' === $entry_key || empty( $manifest[ $entry_key ]['file'] ) ) {
			return false;
		}

		$styles = array();

		if ( ! empty( $manifest[ $entry_key ]['css'] ) && is_array( $manifest[ $entry_key ]['css'] ) ) {
			$styles = $manifest[ $entry_key ]['css'];
		}

		if ( empty( $styles ) ) {
			foreach ( $manifest as $chunk ) {
				if ( ! empty( $chunk['file'] ) && '.css' === substr( (string) $chunk['file'], -4 ) ) {
					$styles[] = $chunk['file'];
				}
			}
		}

		return array(
			'script' => $manifest[ $entry_key ]['file'],
			'styles' => array_values( array_unique( $styles ) ),
		);
	}

	/**
	 * Enqueue the compiled SimpleStack admin client on SimpleStack screens only.
	 *
	 * Action hook: admin_enqueue_scripts
	 *
	 * @param string $hook The admin page hook suffix.
	 */
	public function enqueue_simplestack_app( $hook ) {
		if ( ! $this->is_simplestack_screen( $hook ) ) {
			return;
		}

		$manifest = $this->get_simplestack_manifest();

		if ( false === $manifest ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'SimpleStack: assets/dist/.vite/manifest.json is missing; the admin app was not enqueued.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}

			return;
		}

		$assets = $this->get_simplestack_entry_assets( $manifest );

		if ( false === $assets ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'SimpleStack: the build manifest has no src/admin/index.jsx entry.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}

			return;
		}

		$base_url = wpcd_url . 'assets/dist/';

		foreach ( $assets['styles'] as $index => $style ) {
			wp_enqueue_style(
				( 0 === $index ) ? 'simplestack-admin' : 'simplestack-admin-' . $index,
				$base_url . $style,
				array(),
				$this->get_simplestack_asset_version( $style )
			);
		}

		wp_enqueue_script(
			'simplestack-admin',
			$base_url . $assets['script'],
			array( 'wp-i18n' ),
			$this->get_simplestack_asset_version( $assets['script'] ),
			true
		);

		$bootstrap = array(
			'root'      => '#simplestack-admin',
			'restUrl'   => esc_url_raw( rest_url( 'simple-stack/v1/' ) ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'namespace' => 'simple-stack/v1',
			'version'   => wpcd_version,
			'userId'    => get_current_user_id(),
			'route'     => $this->current_simplestack_route(),
		);

		wp_add_inline_script(
			'simplestack-admin',
			'window.simplestackAdmin = ' . wp_json_encode( $bootstrap ) . ';',
			'before'
		);
	}

	/**
	 * Handle proper highlighting of current menu item.
	 *
	 * Filter Hook: parent_file
	 *
	 * @param string $parent_file The parent file.
	 *
	 * @return string The new parent file.
	 *
	 * @see https://developer.wordpress.org/reference/hooks/parent_file/.
	 */
	public function handle_admin_current_main_menu( $parent_file ) {
		global $current_screen;

		if ( ! is_object( $current_screen ) ) {
			return $parent_file;
		}

		/*
		 * The server, site and alert-recipient post types are no longer top level menus, but
		 * their screens still exist for direct links. Keep the SimpleStack menu highlighted
		 * while they are being viewed so the operator never loses their place.
		 */
		if ( in_array( $current_screen->post_type, array( 'wpcd_app', 'wpcd_app_server', 'wpcd_notify_user' ), true ) ) {
			$parent_file = 'simplestack';
		}

		return $parent_file;
	}

	/**
	 * Handle proper highlighting of current menu item.
	 *
	 * Filter Hook: submenu_file
	 *
	 * @param string $submenu_file The submenu file.
	 * @param string $parent_file  The submenu item's parent file.
	 *
	 * @return string The new submenu file.
	 *
	 * @see https://developer.wordpress.org/reference/hooks/submenu_file/.
	 */
	public function handle_admin_current_submenu_item( $submenu_file, $parent_file ) {
		global $current_screen;

		if ( 'simplestack' !== $parent_file || ! is_object( $current_screen ) ) {
			return $submenu_file;
		}

		if ( 'wpcd_app_server' === $current_screen->post_type ) {
			$submenu_file = 'simplestack';
		} elseif ( 'wpcd_app' === $current_screen->post_type ) {
			$submenu_file = 'simplestack-sites';
		} elseif ( 'edit-tags' === $current_screen->base ) {
			/* The app-group taxonomy is its own submenu; keep it highlighted, not "Sites". */
			$submenu_file = 'edit-tags.php?taxonomy=wpcd_app_group&post_type=wpcd_app';
		} elseif ( 'wpcd_notify_user' === $current_screen->post_type ) {
			$submenu_file = 'edit.php?post_type=wpcd_notify_user';
		}

		return $submenu_file;
	}

	/**
	 * Send legacy CPT list and edit bookmarks to the matching React route.
	 *
	 * The server and site post types are no longer menu entries, but their screens are
	 * still routable by URL. Rather than leave an operator on a bare wp-admin list table
	 * with no way back, redirect them to the hash route that replaces it.
	 *
	 * Action hook: load-edit.php, load-post.php
	 */
	public function redirect_legacy_cpt_screens() {

		if ( ! is_admin() || wp_doing_ajax() ) {
			return;
		}

		/* phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only routing decision. */
		$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
		$post_id   = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;
		/* phpcs:enable WordPress.Security.NonceVerification.Recommended */

		if ( '' === $post_type && $post_id > 0 ) {
			$post_type = (string) get_post_type( $post_id );
		}

		$map = array(
			'wpcd_app_server' => array( 'simplestack', '/servers' ),
			'wpcd_app'        => array( 'simplestack-sites', '/sites' ),
		);

		if ( ! isset( $map[ $post_type ] ) ) {
			return;
		}

		$page  = $map[ $post_type ][0];
		$route = $map[ $post_type ][1];

		if ( $post_id > 0 ) {
			$route .= '/' . $post_id;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . $page . '#' . $route ) );
		exit;
	}

	/**
	 * Render the Help screen.
	 *
	 * The upstream page was a directory of wpclouddeploy.com marketing links. SimpleStack
	 * is internal-only, so this is a short operator reference instead: where the screens
	 * live, what the REST layer guarantees, and how to run the build.
	 */
	public function wpcd_get_faq_and_help_text_page_callback() {
		$help = '<div class="wpcd_faq_help_sec"><h1 class="wp-heading-inline">SimpleStack Help</h1>';

		$help .= '<p>' . esc_html__( 'SimpleStack is the One Dog Solutions hosting control plane. Every screen is rendered by the bundled React client inside wp-admin; nothing is hosted externally.', 'wpcd' ) . '</p>';

		$help .= '<h2>' . esc_html__( 'Sections', 'wpcd' ) . '</h2>';
		$help .= '<ul>';
		$help .= '<li>' . esc_html__( 'Servers - provision, inspect and operate cloud instances.', 'wpcd' ) . '</li>';
		$help .= '<li>' . esc_html__( 'Sites - deploy and manage WordPress installations on those servers.', 'wpcd' ) . '</li>';
		$help .= '<li>' . esc_html__( 'Providers - store Vultr, UpCloud or custom server credentials.', 'wpcd' ) . '</li>';
		$help .= '<li>' . esc_html__( 'Activity - merged command, SSH and error log across every server.', 'wpcd' ) . '</li>';
		$help .= '</ul>';

		$help .= '<h2>' . esc_html__( 'Credentials', 'wpcd' ) . '</h2>';
		$help .= '<p>' . esc_html__( 'API keys and SSH keys are encrypted before they are stored and are never returned by the REST API. The provider screen reports whether a credential is present, not what it is. Rotating a key means entering the new value; leaving the field blank keeps the stored one.', 'wpcd' ) . '</p>';

		$help .= '<h2>' . esc_html__( 'Stack', 'wpcd' ) . '</h2>';
		$help .= '<p>' . esc_html__( 'OpenLiteSpeed only, on Ubuntu 24.04 or 26.04. Default PHP is lsphp84 with lsphp83 and lsphp85 selectable per server. The OpenLiteSpeed web console on port 7080 is restricted to the admin IP configured in Settings and is never opened to the internet.', 'wpcd' ) . '</p>';

		$help .= '<h2>' . esc_html__( 'Rebuilding the admin bundle', 'wpcd' ) . '</h2>';
		$help .= '<p><code>npm ci &amp;&amp; npm run build &amp;&amp; npm run verify:css</code></p>';
		$help .= '<p>' . esc_html__( 'The build is committed to assets/dist so the plugin runs without Node. If this message is visible on the Servers screen, the bundle is missing or stale.', 'wpcd' ) . '</p>';

		$help .= '<h2>' . esc_html__( 'REST namespace', 'wpcd' ) . '</h2>';
		$help .= '<p><code>simple-stack/v1/</code></p>';
		$help .= '<p>' . esc_html__( 'Every route requires an authenticated user with the manage_options capability. There are no public endpoints.', 'wpcd' ) . '</p>';

		$help .= '</div>';

	echo apply_filters( 'wpcd_settings_help_tab_text', $help );
	}


	/**
	 * Set up post types...
	 */
	public function setup_post_types() {
		if ( empty( WPCD()->classes['wpcd_posts_app_server'] ) ) {
			WPCD()->classes['wpcd_posts_app_server'] = new WPCD_POSTS_APP_SERVER();
		}

		if ( empty( WPCD()->classes['wpcd_posts_app'] ) ) {
			WPCD()->classes['wpcd_posts_app'] = new WPCD_POSTS_APP();
		}

		if ( empty( WPCD()->classes['wpcd_posts_cloud_provider'] ) ) {
			WPCD()->classes['wpcd_posts_cloud_provider'] = new WPCD_POSTS_CLOUD_PROVIDER();
		}

		if ( empty( WPCD()->classes['wpcd_posts_ssh_log'] ) ) {
			WPCD()->classes['wpcd_posts_ssh_log'] = new WPCD_SSH_LOG();
		}

		if ( empty( WPCD()->classes['wpcd_posts_command_log'] ) ) {
			WPCD()->classes['wpcd_posts_command_log'] = new WPCD_COMMAND_LOG();
		}

		if ( empty( WPCD()->classes['wpcd_posts_pending_tasks_log'] ) ) {
			WPCD()->classes['wpcd_posts_pending_tasks_log'] = new WPCD_PENDING_TASKS_LOG();
		}

		if ( empty( WPCD()->classes['wpcd_posts_notify_log'] ) ) {
			WPCD()->classes['wpcd_posts_notify_log'] = new WPCD_NOTIFY_LOG();
		}

		if ( empty( WPCD()->classes['wpcd_posts_notify_user'] ) ) {
			WPCD()->classes['wpcd_posts_notify_user'] = new WPCD_NOTIFY_USER();
		}

		if ( empty( WPCD()->classes['wpcd_posts_notify_sent'] ) ) {
			WPCD()->classes['wpcd_posts_notify_sent'] = new WPCD_NOTIFY_SENT();
		}

		if ( empty( WPCD()->classes['wpcd_posts_error_log'] ) ) {
			WPCD()->classes['wpcd_posts_error_log'] = new WPCD_ERROR_LOG();
		}

		if ( empty( WPCD()->classes['wpcd_posts_team'] ) ) {
			WPCD()->classes['wpcd_posts_team'] = new WPCD_POSTS_TEAM();
		}

		if ( empty( WPCD()->classes['wpcd_posts_permission_type'] ) ) {
			WPCD()->classes['wpcd_posts_permission_type'] = new WPCD_POSTS_PERMISSION_TYPE();
		}
	}

	/**
	 * Load css and js scripts
	 *
	 * Action hook: admin_enqueue_scripts
	 *
	 * Note: Even though this is loaded on an admin hook, the PUBLIC class
	 * will manually call this function to get these scripts loaded up on the
	 * frontend as well!
	 *
	 * @param string $hook hook.
	 */
	public function wpcd_admin_scripts( $hook ) {

		// What screen are we on?
		$screen = get_current_screen();

		/* Inject brand colors into global style sheet. */
		$this->wpcd_inject_brand_color_styles();

		/* Inject custom css. */
		$this->wpcd_inject_custom_css();

		/* Do not allow auto-saves on any wpcd-screen. */
		if ( is_object( $screen ) && wpcd_str_starts_with( $screen->post_type, 'wpcd_' ) ) {
			wp_dequeue_script( 'autosave' );
		}

		/* Cloud Providers Screen - Uses the settings screen style sheet for now. */
		if ( is_object( $screen ) && in_array( $screen->post_type, array( 'wpcd_cloud_provider' ) ) ) {
			wp_enqueue_style( 'wpcd-admin-settings', wpcd_url . 'assets/css/wpcd-admin-settings.css', array(), wpcd_scripts_version );
		}

		/* CSS common to server and app screens. */
		if ( is_object( $screen ) && in_array( $screen->post_type, array( 'wpcd_app_server', 'wpcd_app' ) ) ) {
			wp_enqueue_style( 'wpcd-common-admin', wpcd_url . 'assets/css/wpcd-common-admin.css', array(), wpcd_scripts_version );
		}

		/* Style sheet for the settings and help screens, now nested under SimpleStack. */
		if ( 'simplestack_page_wpcd_settings' === $hook ||
			'simplestack_page_wpcd_faq_and_help' === $hook ) {
			wp_enqueue_style( 'wpcd-admin-settings', wpcd_url . 'assets/css/wpcd-admin-settings.css', array(), wpcd_scripts_version );
			if ( defined( 'WPCD_SKIP_SERVER_SIZES_SETTING' ) && WPCD_SKIP_SERVER_SIZES_SETTING ) {
				wp_enqueue_style( 'wpcd-admin-settings-server-sizes', wpcd_url . 'assets/css/wpcd-admin-settings-server-sizes.css', array(), wpcd_scripts_version );
			}
		}

	}

	/**
	 * Add the wp-admin and front-end brand color styles
	 * as inline styles to the global style sheet.
	 *
	 * Called from function wpcd_admin_scripts which is hooked into action admin_enqueue_scripts
	 */
	public function wpcd_inject_brand_color_styles() {

		// Get brand color settings for the wp-admin area.
		$primary_brand_color = wpcd_get_option( 'wordpress_app_primary_brand_color' );
		$primary_brand_color = empty( $primary_brand_color ) ? WPCD_PRIMARY_BRAND_COLOR : $primary_brand_color;

		$secondary_brand_color = wpcd_get_option( 'wordpress_app_secondary_brand_color' );
		$secondary_brand_color = empty( $secondary_brand_color ) ? WPCD_SECONDARY_BRAND_COLOR : $secondary_brand_color;

		$tertiary_brand_color = wpcd_get_option( 'wordpress_app_tertiary_brand_color' );
		$tertiary_brand_color = empty( $tertiary_brand_color ) ? WPCD_TERTIARY_BRAND_COLOR : $tertiary_brand_color;

		$accent_bg_color = wpcd_get_option( 'wordpress_app_accent_background_color' );
		$accent_bg_color = empty( $accent_bg_color ) ? WPCD_ACCENT_BG_COLOR : $accent_bg_color;

		$medium_accent_bg_color = wpcd_get_option( 'wordpress_app_medium_accent_background_color' );
		$medium_accent_bg_color = empty( $medium_accent_bg_color ) ? WPCD_MEDIUM_ACCENT_BG_COLOR : $medium_accent_bg_color;

		$medium_bg_color = wpcd_get_option( 'wordpress_app_medium_background_color' );
		$medium_bg_color = empty( $medium_bg_color ) ? WPCD_MEDIUM_BG_COLOR : $medium_bg_color;

		$light_bg_color = wpcd_get_option( 'wordpress_app_light_background_color' );
		$light_bg_color = empty( $light_bg_color ) ? WPCD_LIGHT_BG_COLOR : $light_bg_color;

		$alternate_accent_bg_color = wpcd_get_option( 'wordpress_app_alternate_accent_background_color' );
		$alternate_accent_bg_color = empty( $alternate_accent_bg_color ) ? WPCD_ALTERNATE_ACCENT_BG_COLOR : $alternate_accent_bg_color;

		$positive_color = wpcd_get_option( 'wordpress_app_positive_color' );
		$positive_color = empty( $positive_color ) ? WPCD_POSITIVE_COLOR : $positive_color;

		$negative_color = wpcd_get_option( 'wordpress_app_negative_color' );
		$negative_color = empty( $negative_color ) ? WPCD_NEGATIVE_COLOR : $negative_color;

		$alt_negative_color = wpcd_get_option( 'wordpress_app_alt_negative_color' );
		$alt_negative_color = empty( $alt_negative_color ) ? WPCD_ALT_NEGATIVE_COLOR : $alt_negative_color;

		$white_color = wpcd_get_option( 'wordpress_app_white_color' );
		$white_color = empty( $white_color ) ? WPCD_WHITE_COLOR : $white_color;

		$terminal_bg_color = wpcd_get_option( 'wordpress_app_terminal_background_color' );
		$terminal_bg_color = empty( $terminal_bg_color ) ? WPCD_TERMINAL_BG_COLOR : $terminal_bg_color;

		$terminal_fg_color = wpcd_get_option( 'wordpress_app_terminal_foreground_color' );
		$terminal_fg_color = empty( $terminal_fg_color ) ? WPCD_TERMINAL_FG_COLOR : $terminal_fg_color;

		/* Global style sheet. */
		wp_enqueue_style( 'wpcd-global-css', wpcd_url . 'assets/css/wpcd-global.css', array(), wpcd_scripts_version );

		/* Define brand colors */
		$global_css = ":root {
			--wpcd-primary-brand-color: {$primary_brand_color};
			--wpcd-secondary-brand-color: {$secondary_brand_color};
			--wpcd-tertiary-brand-color: {$tertiary_brand_color};
			--wpcd-accent-background-color: {$accent_bg_color};
			--wpcd-medium-accent-background-color: {$medium_accent_bg_color};
			--wpcd-medium-background-color: {$medium_bg_color};
			--wpcd-light-background-color: {$light_bg_color};
			--wpcd-alternate-accent-background-color: {$alternate_accent_bg_color};
			--wpcd-positive-color: {$positive_color};			
			--wpcd-negative-color: {$negative_color};
			--wpcd-alt-negative-color: {$alt_negative_color};
			--wpcd-white-color: {$white_color};
			--wpcd-terminal-background-color: {$terminal_bg_color};			
			--wpcd-terminal-foreground-color: {$terminal_fg_color};
		}";

		/* Add some global css. */
		wp_add_inline_style( 'wpcd-global-css', $global_css );

	}

	/**
	 * Add custom css defined in settings
	 * as inline styles to the global style sheet.
	 *
	 * Called from function wpcd_admin_scripts which is hooked into action admin_enqueue_scripts
	 */
	public function wpcd_inject_custom_css() {

		/* Global style sheet. */
		wp_enqueue_style( 'wpcd-global-css', wpcd_url . 'assets/css/wpcd-global.css', array(), wpcd_scripts_version );

		$global_css = wpcd_get_early_option( 'wordpress-app-custom-css-override' );

		/* Add to global css. */
		if ( ! empty( $global_css ) ) {
			wp_add_inline_style( 'wpcd-global-css', $global_css );
		}
	}


	/**
	 * Get the list of operating systems that we can install on a server.
	 *
	 * @since 4.2.5
	 *
	 * @return array
	 */
	public static function get_os_list() {
		$oslist = array(
			'ubuntu2204lts' => __( 'Ubuntu 22.04 LTS', 'wpcd' ),
			'ubuntu2004lts' => __( 'Ubuntu 20.04 LTS', 'wpcd' ),
			'ubuntu2404lts' => __( 'Ubuntu 24.04 LTS (Important Restrictions - See Docs!)', 'wpcd' ),
		);

		// Remove some entries based on settings.
		if ( (bool) wpcd_get_option( 'wordpress_app_disable_ubuntu_lts_2004' ) ) {
			unset( $oslist['ubuntu2004lts'] );
		}
		if ( (bool) wpcd_get_option( 'wordpress_app_disable_ubuntu_lts_2204' ) ) {
			unset( $oslist['ubuntu2204lts'] );
		}

		if ( (bool) wpcd_get_option( 'wordpress_app_disable_ubuntu_lts_2404' ) ) {
			unset( $oslist['ubuntu2404lts'] );
		}

		// Add in some optional entries if necessary.
		if ( (bool) wpcd_get_option( 'wordpress_app_enable_ubuntu_lts_1804' ) ) {
			$oslist['ubuntu1804lts'] = __( 'Ubuntu 18.04 LTS (Deprecated, EOL April 2023)', 'wpcd' );
		}

		// Return filtered array.
		return apply_filters( 'wpcd_os_list', $oslist );
	}

	/**
	 * Get the list of web servers that we can install on a server.
	 *
	 * @since 4.8.2
	 *
	 * @return array
	 */
	public static function get_webserver_list() {
		// SimpleStack is an OpenLiteSpeed-only stack. nginx is no longer supported.
		$webserver_list = array(
			'ols' => __( 'OpenLiteSpeed', 'wpcd' ),
		);
		return apply_filters( 'wpcd_webserver_list', $webserver_list );
	}


	/**
	 * Get the operating system name (full name) initially installed on a server
	 *
	 * @since 4.2.5
	 *
	 * @param string $os The os id / key.
	 *
	 * @return string
	 */
	public static function get_os_description( $os ) {

		$return = $os;

		switch ( $os ) {
			case 'ubuntu1804lts':
				$return = __( 'Ubuntu 18.04 LTS', 'wpcd' );
				break;

			case 'ubuntu2004lts':
				$return = __( 'Ubuntu 20.04 LTS', 'wpcd' );
				break;

			case 'ubuntu2204lts':
				$return = __( 'Ubuntu 22.04 LTS', 'wpcd' );
				break;

			case 'ubuntu2404lts':
				$return = __( 'Ubuntu 24.04 LTS', 'wpcd' );
				break;

		}

		return $return;

	}


	/**
	 * Encrypt a string.
	 *
	 * @since 4.1.0
	 *
	 * @param string $plainText The string to encrypt.
	 * @param string $inkey A key to use to encrypt the incoming text.  If none is supplied use the pre-stored key in wpconfig or the database.
	 *
	 * @return string
	 */
	public static function encrypt( $plainText, $inkey = '' ) {
		/* Get the encryption key */
		$key = $inkey;
		if ( empty( $key ) ) {
			// No key passed into the function so see if one was defined globally - usually via wp-config.php.
			if ( defined( 'WPCD_ENCRYPTION_KEY' ) ) {
				$key = WPCD_ENCRYPTION_KEY;
			}
		}
		if ( empty( $key ) ) {
			// still no key so check for option value...
			$key = get_option( 'wpcd_encryption_key_v2' );
		}
		if ( empty( $key ) ) {
			// ok, we don't have a key at all anywhere.
			// so generate one and add it to wp options.
			$len = openssl_cipher_iv_length( $cipher = 'AES-128-CBC' );
			$key = $key = wpcd_random_str( $len );  // Do NOT use openssl_random_pseudo_bytes($len) since WordPress is not retrieving this value from options properly! We store it but WP retrieves blank so do not use it.
			update_option( 'wpcd_encryption_key_v2', $key );
		}

		/* Get an initialization vector */
		$ivlen = openssl_cipher_iv_length( $cipher = 'AES-128-CBC' );
		$iv    = openssl_random_pseudo_bytes( $ivlen );

		/* Generate the raw cipher text */
		$secret_key     = md5( $key );
		$ciphertext_raw = openssl_encrypt( $plainText, 'AES-128-CBC', $secret_key, OPENSSL_RAW_DATA, $iv );

		/* Add hmac */
		$hmac = hash_hmac( 'sha256', $ciphertext_raw, $secret_key, $as_binary = true );

		/* final return value - this value concatenates the encrypted text with the iv and the hmac */
		$ciphertext = 'wpcd-encrypt-v2:' . base64_encode( $iv . $hmac . $ciphertext_raw );

		return $ciphertext;
	}

	/**
	 * Encrypt a string - version 1
	 *
	 * This was the original version before we changed it
	 * to use a random initialization vector.
	 *
	 * @since 1.0.0
	 *
	 * @param string $plainText The string to encrypt.
	 *
	 * @return string
	 */
	public static function encrypt_v1( $plainText ) {
		$key = self::ENCRYPTION_KEY;
		if ( defined( 'WPCD_ENCRYPTION_KEY' ) ) {
			$key = WPCD_ENCRYPTION_KEY;
		}
		$secret_key = md5( $key );
		$iv         = substr( hash( 'sha256', 'aaaabbbbcccccddddeweee' ), 0, 16 );
		return base64_encode( openssl_encrypt( $plainText, 'AES-128-CBC', $secret_key, OPENSSL_RAW_DATA, $iv ) );
	}

	/**
	 * Decrypt a string.
	 *
	 * @since 4.1.0
	 *
	 * @param string $encryptedText The string to decrypt.
	 * @param string $inkey A key to use to encrypt the incoming text.  If none is supplied use the pre-stored key in wpconfig or the database.
	 *
	 * @return string
	 */
	public static function decrypt( $encryptedText, $inkey = '' ) {

		// Make sure we have something to encrypt.
		if ( empty( $encryptedText ) ) {
			return $encryptedText;
		}

		// Check to see if encryption used is v1 or v2.
		if ( strpos( $encryptedText, 'wpcd-encrypt-v2:' ) !== false ) {
			$version = 'v2';
		} else {
			$version = 'v1';
		}

		switch ( $version ) {
			case 'v1':
				return self::decrypt_v1( $encryptedText );
				break;
			case 'v2':
				return self::decrypt_v2( $encryptedText, $inkey );
				break;
			default:
				return '';
		}

		return '';

	}

	/**
	 * Decrypt a string - v1
	 *
	 * Decrypt a string using the Version 1 algorithm with a fixed initialization vector.
	 *
	 * @since 1.0.0
	 *
	 * @param string $encryptedText The string to decrypt.
	 *
	 * @return string
	 */
	public static function decrypt_v1( $encryptedText ) {
		$key = self::ENCRYPTION_KEY;
		if ( defined( 'WPCD_ENCRYPTION_KEY' ) ) {
			$key = WPCD_ENCRYPTION_KEY;
		}

		$secret_key = md5( $key );
		$iv         = substr( hash( 'sha256', 'aaaabbbbcccccddddeweee' ), 0, 16 );
		return openssl_decrypt( base64_decode( $encryptedText ), 'AES-128-CBC', $secret_key, OPENSSL_RAW_DATA, $iv );
	}

	/**
	 * Decrypt a string - v2
	 *
	 * Decrypt a string using the Version 2 algorithm with a variable initialization vector.
	 *
	 * @since 4.1.0
	 *
	 * @param string $encryptedText The string to decrypt.
	 * @param string $inkey A key to use to encrypt the incoming text.  If none is supplied use the pre-stored key in wpconfig or the database.
	 *
	 * @return string
	 */
	public static function decrypt_v2( $encryptedText, $inkey = '' ) {

		/* Get the encryption key */
		$key = $inkey;
		if ( empty( $key ) ) {
			// No key passed into the function so see if one was defined globally - usually via wp-config.php.
			if ( defined( 'WPCD_ENCRYPTION_KEY' ) ) {
				$key = WPCD_ENCRYPTION_KEY;
			}
		}
		if ( empty( $key ) ) {
			// Still no key so check for option value.
			$key = get_option( 'wpcd_encryption_key_v2' );
		}

		/* Remove the v2 prefix from the encrypted string */
		$encryptedText = str_replace( 'wpcd-encrypt-v2:', '', $encryptedText );

		/* get the raw cipher after removing iv and such  */
		$c              = base64_decode( $encryptedText );
		$ivlen          = openssl_cipher_iv_length( $cipher = 'AES-128-CBC' );
		$iv             = substr( $c, 0, $ivlen );
		$hmac           = substr( $c, $ivlen, $sha2len = 32 );
		$ciphertext_raw = substr( $c, $ivlen + $sha2len );

		/* Decrypt the text */
		$secret_key         = md5( $key );
		$original_plaintext = openssl_decrypt( $ciphertext_raw, $cipher, $secret_key, $options = OPENSSL_RAW_DATA, $iv );

		/* Check hmac */
		$calcmac = hash_hmac( 'sha256', $ciphertext_raw, $secret_key, $as_binary = true );
		if ( hash_equals( $hmac, $calcmac ) ) {
			return $original_plaintext;
		} else {
			return '';
		}

	}

	/**
	 * Return an array of sensitive values
	 * and what to replace them with.
	 *
	 * @return array
	 */
	public function wpcd_get_pw_terms_to_clean() {

		$terms = apply_filters(
			'wpcd_get_pw_terms_to_clean',
			array(
				'wp_password='                => '(***private***)',
				'wps_new_password='           => '(***private***)',
				'aws_access_key_id='          => '(***private***)',
				'aws_secret_access_key='      => '(***private***)',
				'--admin_password='           => '(***private***)',
				'pass='                       => '(***private***)',
				'remote_dbpass='              => '(***private***)',
				'local_dbpass='               => '(***private***)',

				'dns_cloudflare_api_token='   => '(***private***)',
				'dns_cloudflare_api_key='     => '(***private***)',
				'secret_key_manager_api_key=' => '(***private***)',
				'git_token='                  => '(***private***)',

				'dns_cloudflare_api_token'    => '(***private***)',
				'dns_cloudflare_api_key'      => '(***private***)',
				'secret_key_manager_api_key'  => '(***private***)',
				'git_token'                   => '(***private***)',

				'logtivity_teams_api_key='    => '(***private***)',
				'ubuntu_pro_token='           => '(***private***)',
				"Updated the constant 'DB_PASSWORD' in the 'wp-config.php' file with the value " => '(***private***)' . PHP_EOL,

			)
		);

		return $terms;

	}

	/**
	 * Write errors out to the error log
	 *
	 * @param string $msg message to write.
	 * @param string $type type of message.
	 * @param string $file filename generating message.
	 * @param string $line linenumber related to the message being written.
	 * @param string $data optional data that will be logged to a separate field when writing to the database.
	 * @param bool   $force If this is true, it will log ALWAYS. Useful for temporary debugging.
	 */
	public function log_error( $msg, $type, $file, $line, $data = '', $force = false ) {

		// Remove known password strings.
		$pwarray = $this->wpcd_get_pw_terms_to_clean();
		$msg     = wpcd_replace_key_value_paired_strings( $pwarray, $msg );

		// Log to debug.log file.
		$debug_flag            = defined( 'WPCD_DEBUG' ) ? WPCD_DEBUG : false;
		$log_options_debug_log = wpcd_get_early_option( 'logging_and_tracing_types_debug_log' );
		if ( $force || ( in_array( $type, array( 'debug', 'trace' ), true ) && $debug_flag ) || in_array( $type, array( 'error', 'warn' ), true ) || ( $log_options_debug_log && in_array( $type, $log_options_debug_log ) ) ) {
			error_log( sprintf( '%s:%d:(%s) - %s', basename( $file ), $line, $type, $msg ) );
		}

		// Log to database?  Note that you can log to the database and NOT log to the error log file.  They are independent!
		$log_options = wpcd_get_early_option( 'logging_and_tracing_types' );
		if ( $log_options && in_array( $type, $log_options ) ) {
			WPCD_POSTS_ERROR_LOG()->add_error_log_entry( $msg, $type, $file, $line, $data );
		}

	}

	/**
	 * Add a new Notification Log record
	 *
	 * @param int    $parent_post_id The post id that represents the item this log is being done against.
	 * @param string $notification_type The type of notification.
	 * @param string $message The notification message itself.
	 * @param string $notification_reference any additional or third party reference.
	 * @param int    $post_id The ID of an existing log, if it exists.
	 */
	public function log_notification( $parent_post_id, $notification_type, $message, $notification_reference, $post_id = null ) {
		WPCD_POSTS_NOTIFY_LOG()->add_notify_log_entry( $parent_post_id, $notification_type, $message, $notification_reference, $post_id );
	}


	/**
	 * Return a requested provider object
	 *
	 * @param string $provider name of provider.
	 *
	 * @return VPN_API_Provider_{provider}()
	 *
	 * This will allow additional providers to be added via plugins.
	 */
	public function get_provider_api( $provider ) {
		if ( empty( $provider ) ) {
			return null;
		}

		/* Folders of array of locations to search for an api class */
		$provider_paths = apply_filters( 'wpcd_cloud_provider_class_path', array( wpcd_path . 'includes/core/providers/' ) );

		if ( empty( WPCD()->classes[ 'wpcd_vpn_api_provider_' . $provider ] ) ) {

			/* Search through folders looking for a provider class file */
			foreach ( $provider_paths as $provider_path ) {
				$provider_file = $provider_path . 'class-' . $provider . '.php';
				if ( file_exists( $provider_file ) ) {
					require_once $provider_file;
				}
			}
			$class = 'CLOUD_PROVIDER_API_' . str_replace( ' ', '', ucwords( str_replace( array( '-', '_' ), ' ', $provider ) ) );
			if ( class_exists( $class ) ) {
				WPCD()->classes[ 'wpcd_vpn_api_provider_' . $provider ] = new $class();
			} else {
				// This might be a virtual cloud provider stored in the wpcd_cloud_provider custom post type...
				if ( ! $this->setup_virtual_cloud_provider( $provider, $provider_paths ) ) {
					return null;
				}
			}
		}

		return WPCD()->classes[ 'wpcd_vpn_api_provider_' . $provider ];

	}

	/**
	 * Given a provider and a list of paths to search, see if the provider
	 * exists in the wpcd_cloud_provider post type.  If it does, grab
	 * its real api data (aka "cloud provider type") and instantiate a
	 * new instance of the class with data that matches the data in the
	 * wpcd_cloud_provider post type.
	 *
	 * @param string $provider name of provider (slug).
	 * @param string $provider_paths provider_paths.
	 *
	 * @return boolean true if class could be instantiated for the provider, false otherwise.
	 */
	public function setup_virtual_cloud_provider( $provider, $provider_paths ) {

		$args = array(
			'post_type'      => 'wpcd_cloud_provider',
			'posts_per_page' => -1,
			'meta_key'       => 'wpcd_cloud_provider_slug',
			'meta_value'     => $provider,
		);

		$post = get_posts( $args );

		// if we have a match to the provider...
		if ( $post ) {

			// grab some data off the post record...
			$provider_type = get_post_meta( $post[0]->ID, 'wpcd_cloud_provider_type', true );
			$inactive      = boolval( get_post_meta( $post[0]->ID, 'wpcd_cloud_provider_inactive', true ) );

			// $args is used to pass the newly instantiated class some data into its constructor.
			$args = array(
				'provider_name'           => $post[0]->post_title,
				'provider_slug'           => get_post_meta( $post[0]->ID, 'wpcd_cloud_provider_slug', true ),
				'provider_region_prefix'  => get_post_meta( $post[0]->ID, 'wpcd_cloud_provider_region_prefix', true ),
				'provider_dashboard_link' => get_post_meta( $post[0]->ID, 'wpcd_cloud_provider_dashboard_link', true ),
			);

			// See if we can find a class with the provider type - logic is very very similar to that of the get_provider_api function.
			/* Search through folders looking for a provider class file */
			foreach ( $provider_paths as $provider_path ) {
				$provider_file = $provider_path . 'class-' . $provider_type . '.php';
				if ( file_exists( $provider_file ) ) {
					require_once $provider_file;
				}
			}

			$class = 'CLOUD_PROVIDER_API_' . str_replace( ' ', '', ucwords( str_replace( array( '-', '_' ), ' ', $provider_type ) ) );
			if ( class_exists( $class ) ) {
				WPCD()->classes[ 'wpcd_vpn_api_provider_' . $provider ] = new $class( $args );
				return true;
			}
		}
		return false;
	}

	/**
	 * Return the array of providers
	 */
	public function get_cloud_providers() {
		return apply_filters( 'wpcd_get_cloud_providers', self::$providers );
	}

	/**
	 * Hide the Digital Ocean provider unless it has been explicitly re-enabled in settings.
	 *
	 * SimpleStack ships Vultr and UpCloud as its supported providers. The Digital Ocean
	 * class is kept in the tree for legacy servers but is not offered to new ones.
	 *
	 * Filter hook: wpcd_get_cloud_providers
	 *
	 * @param array $providers_list providers_list.
	 *
	 * @return array
	 */
	public function disable_digital_ocean_provider_by_default( $providers_list ) {

		if ( ! wpcd_get_early_option( 'enable-do-provider' ) ) {
			unset( $providers_list['digital-ocean'] );
		}

		return $providers_list;

	}

	/**
	 * Replace the provider descriptions with white label descriptions
	 *
	 * Filter hook: wpcd_get_cloud_providers
	 *
	 * @param array $providers_list Key-value array of providers.
	 *
	 * @return array
	 */
	public function wpcd_get_cloud_providers_white_label_desc( $providers_list ) {

		foreach ( $providers_list as $provider => $desc ) {
			$label = wpcd_get_early_option( "vpn_{$provider}_alt_desc" );
			if ( ! empty( $label ) ) {
				$providers_list[ $provider ] = $label;
			}
		}

		return $providers_list;

	}

	/**
	 * Return the description for a provider.
	 * It takes into account any white-label settings.
	 *
	 * @param string $provider   Provider id.
	 *
	 * @return string
	 */
	public function wpcd_get_cloud_provider_desc( $provider ) {

		$providers = WPCD()->get_cloud_providers();

		if ( isset( $providers[ $provider ] ) ) {
			$provider_desc = $providers[ $provider ];
			if ( empty( $provider_desc ) ) {
				$provider_desc = $provider;
			}
		} else {
			$provider_desc = $provider;
		}

		return $provider_desc;

	}

	/**
	 * Return the array of ACTIVE providers
	 *
	 * An active provider is one whose credentials have been entered.
	 * Later we might be more strict with the definition.
	 */
	public function get_active_cloud_providers() {

		$providers = $this->get_cloud_providers();

		foreach ( $providers as $provider => $provider_name ) {
			// Can you get a valid api?  If not unset.
			if ( empty( $this->get_provider_api( $provider ) ) ) {
				unset( $providers[ $provider ] );
				continue;
			}

			// If we got here, we have a valid api so check to see if credentials provided.
			if ( ! $this->get_provider_api( $provider )->credentials_available() ) {
				unset( $providers[ $provider ] );
			}
		}

		return apply_filters( 'wpcd_get_active_cloud_providers', $providers );

	}

	/**
	 * Return default encryption key
	 */
	public function get_default_encryption_key() {
		return self::ENCRYPTION_KEY;
	}

	/**
	 * Adds an app id and description to the application list var.
	 *
	 * Array format: array( 'appid' => 'App Desc1', 'appid2' => 'App Desc2' )
	 *
	 * @param array $app_id keyed array of app id and a description.
	 */
	public function set_app_id( $app_id ) {
		$this->app_list[] = $app_id;
	}

	/**
	 * Return a list of applications
	 * This list is actually filled out by other app-specific routines
	 */
	public function get_app_list() {
		return apply_filters( 'wpcd_app_list', $this->app_list );
	}

	/**
	 * Loads roles and capabilites
	 *
	 * @return void
	 */
	public function load_roles_capabilities() {
		if ( empty( WPCD()->classes['wpcd_roles_capabilities'] ) ) {
			WPCD()->classes['wpcd_roles_capabilities'] = new WPCD_ROLES_CAPABILITIES();
		}
	}

	/**
	 * Returns a server post object using the postid of an app.
	 *
	 * @param int $app_id  The app for which to locate the server post.
	 *
	 * @return array|boolean Server post or false or error message
	 */
	public function get_server_by_app_id( $app_id ) {

		// If for some reason the $app_id is actually a server id return the server data right away.
		if ( 'wpcd_app_server' == get_post_type( $app_id ) ) {
			return get_post( $app_id );
		}

		// Get the app post.
		$app_post = get_post( $app_id );

		if ( ! empty( $app_post ) && ! is_wp_error( $app_post ) ) {

			$server_post = get_post( get_post_meta( $app_post->ID, 'parent_post_id', true ) );

			return $server_post;

		} else {

			return false;

		}

		return false;
	}

	/**
	 * Returns whether a site is a staging site.
	 *
	 * Note: We're commingling some things here because
	 * only the wordpres-app has the concept of "staging".
	 * So this should be in that app class but putting
	 * it here because we need it in some global functions.
	 * And there is always the possibility that a future app
	 * will have both staging and production types.
	 *
	 * @param int $app_id ID of app being interrogated.
	 *
	 * @return boolean
	 */
	public function is_staging_site( $app_id ) {

		$is_staging = (int) get_post_meta( $app_id, 'wpapp_is_staging', true );

		if ( 1 === $is_staging ) {
			return true;
		} else {
			return false;
		}

	}

}
