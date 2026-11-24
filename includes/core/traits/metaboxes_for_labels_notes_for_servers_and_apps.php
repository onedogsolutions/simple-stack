<?php
/** Modified for Simple Stack by One Dog Solutions on 2026-11-23. */
/**
 * Trait:
 * Contains some of the metabox related code for the server and apps cpt screens.
 * This particular trait handles the code for labels, descriptions and notes.
 * Used only by the class-sstk-posts-app-server.php and class-sstk-posts-app.php files which define the SSTK_POSTS_APP_SERVER and SSTK_POSTS_APP classes respectively.
 *
 * @package simple-stack
 */

/**
 * Trait sstk_metaboxes_for_labels_notes_for_servers_and_apps.
 */
trait sstk_metaboxes_for_labels_notes_for_servers_and_apps {

	/**
	 * Initialization hook to bootstrap the other hooks need.
	 * This function needs to be called by the class using this
	 * trait.
	 */
	public function init_hooks_for_labels_notes_for_servers_and_apps() {

		// Filter hook to add metabox for team.
		add_filter( 'rwmb_meta_boxes', array( $this, 'sstk_register_labels_notes_metaboxes' ) );

	}

	/**
	 * Register the labels and notes metaboxes - these are metabox.io metaboxes, not standard WP metaboxes.
	 *
	 * Filter hook: rwmb_meta_boxes
	 *
	 * @param array $metaboxes Array of existing metaboxes.
	 *
	 * @return array new array of metaboxes
	 */
	public function sstk_register_labels_notes_metaboxes( $metaboxes ) {

		$post_id   = filter_input( INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT );
		$post_type = $this->get_post_type( $post_id );
		
		// Check to see if this should be displayed.  The id of the permission is 'desc_notes_metabox'.
		if ( ! sstk_can_author_view_server_feature( $post_id, 'desc_notes_metabox' ) ) {
			return $metaboxes;
		}
		if ( ! sstk_can_author_view_site_feature( $post_id, 'desc_notes_metabox' ) ) {
			return $metaboxes;
		}		

		$prefix = 'sstk_';

		// Get the count of all tabs.
		$sstk_note_count            = '';
		$sstk_labels_count          = '';
		$sstk_links_count           = '';
		$sstk_admin_only_note_count = '';

		if ( 'sstk_app' === $post_type || 'sstk_app_server' === $post_type ) {
			$sstk_note   = get_post_meta( $post_id, 'sstk_note', true );
			$sstk_labels = get_post_meta( $post_id, 'sstk_labels', true );
			$sstk_links  = get_post_meta( $post_id, 'sstk_links', true );

			if ( ! empty( $sstk_note ) && count( $sstk_note ) > 0 ) {
				$sstk_note_count = ' (' . count( $sstk_note ) . ')';
			}

			if ( ! empty( $sstk_labels ) && count( $sstk_labels ) > 0 ) {
				$sstk_labels_count = ' (' . count( $sstk_labels ) . ')';
			}

			if ( ! empty( $sstk_links ) && count( $sstk_links ) > 0 ) {
				$sstk_links_count = ' (' . count( $sstk_links ) . ')';
			}

			// Check if admin user.
			if ( sstk_is_admin() ) {
				$sstk_admin_only_note = get_post_meta( $post_id, 'sstk_admin_only_note', true );
				if ( ! empty( $sstk_admin_only_note ) && count( $sstk_admin_only_note ) > 0 ) {
					$sstk_admin_only_note_count = ' (' . count( $sstk_admin_only_note ) . ')';
				}
			}
		}

		// Tabs.
		$tabs = array(
			'sstk_descriptions' => array(
				'label' => 'Descriptions',
				'icon'  => 'dashicons-text-page',
			),
			'sstk_notes'        => array(
				'label' => sprintf( __( 'Notes %s', 'simple-stack' ), $sstk_note_count ),
				'icon'  => 'dashicons-analytics',
			),
			'sstk_labels'       => array(
				'label' => sprintf( __( 'Labels %s', 'simple-stack' ), $sstk_labels_count ),
				'icon'  => 'dashicons-format-quote',
			),
			'sstk_links'        => array(
				'label' => sprintf( __( 'Links %s', 'simple-stack' ), $sstk_links_count ),
				'icon'  => 'dashicons-admin-links',
			),
		);

		// If is admin, add in a tab for admin-only notes.
		if ( sstk_is_admin() ) {
			$tabs['sstk_admin_only_notes'] = array(
				'label' => sprintf( __( 'Notes - Admin Eyes Only %s', 'simple-stack' ), $sstk_admin_only_note_count ),
				'icon'  => 'dashicons-analytics',
			);
		}

		// Fields for tabs.
		$fields = array(
			array(
				'name' => '',
				'id'   => $prefix . 'labels_notes_intro_header',
				'type' => 'custom_html',
				'std'  => __( 'Click the update button in the upper right when you are finished to save your changes.', 'simple-stack' ),
			),

			array(
				'name'        => __( 'Short Description', 'simple-stack' ),
				'id'          => $prefix . 'short_description',
				'type'        => 'text',
				'placeholder' => __( 'Enter a short description', 'simple-stack' ),
				'size'        => 60,
				'desc'        => __( 'This description can sometimes appear in the server or app lists', 'simple-stack' ),
				'tab'         => 'sstk_descriptions',
			),
			array(
				'name' => __( 'Long Description', 'simple-stack' ),
				'id'   => $prefix . 'long_description',
				'type' => 'textarea',
				'cols' => 120,
				'rows' => 10,
				'tab'  => 'sstk_descriptions',
			),
			// Notes tab.
				array(
					'name'        => '',
					'id'          => $prefix . 'note',
					'type'        => 'textarea',
					'placeholder' => __( 'Write your note here', 'simple-stack' ),
					'cols'        => 60,
					'rows'        => 5,
					'clone'       => true,
					'sort_clone'  => true,
					'add_button'  => __( 'Add another note', 'simple-stack' ),
					'tab'         => 'sstk_notes',
				),
			// Labels tab.
				array(
					'name' => '',
					'id'   => $prefix . 'labels_header',
					'type' => 'custom_html',
					'std'  => __( 'These labels appear to the right of the server/app/post-title in the server or app list. Use sparingly to avoid cluttering the server/app/post-title column.', 'simple-stack' ),
					'tab'  => 'sstk_labels',
				),
			array(
				'name'       => __( 'Label(s)', 'simple-stack' ),
				'id'         => $prefix . 'labels',
				'type'       => 'text',
				'size'       => 60,
				'clone'      => true,
				'sort_clone' => true,
				'add_button' => __( 'Add another label', 'simple-stack' ),
				'tab'        => 'sstk_labels',
			),
			// links tab.
				array(
					'name'       => '',
					'id'         => $prefix . 'links',
					'type'       => 'group',
					'clone'      => true,
					'sort_clone' => true,
					'add_button' => __( 'Add another link', 'simple-stack' ),
					'tab'        => 'sstk_links',
					'fields'     => array(
						array(
							'name' => __( 'Link Label', 'simple-stack' ),
							'id'   => 'sstk_link_label',
							'type' => 'text',
							'size' => 120,
						),
						array(
							'name' => __( 'URL', 'simple-stack' ),
							'id'   => 'sstk_link_url',
							'type' => 'url',
							'size' => 120,
						),
						array(
							'name' => __( 'Description', 'simple-stack' ),
							'id'   => 'sstk_link_desc',
							'type' => 'text',
							'size' => 120,
						),
					),
				),

		);

		// If is admin, add in fields for the admin-only notes.
		if ( sstk_is_admin() ) {
			$fields[] = array(
				'name'        => '',
				'id'          => $prefix . 'admin_only_note',
				'type'        => 'textarea',
				'placeholder' => __( 'Write your note here', 'simple-stack' ),
				'cols'        => 60,
				'rows'        => 5,
				'clone'       => true,
				'sort_clone'  => true,
				'add_button'  => __( 'Add another note', 'simple-stack' ),
				'tab'         => 'sstk_admin_only_notes',
			);
		}

		// Register the metabox for labels, notes and descriptions.
		$metaboxes[] = array(
			'id'       => $prefix . 'labels_notes',
			'title'    => __( 'Descriptions, Notes & Labels', 'simple-stack' ),
			'pages'    => array( $post_type ),
			'priority' => 'default',
			'tabs'     => $tabs,
			'fields'   => $fields,
		);

		return $metaboxes;

	}

}
