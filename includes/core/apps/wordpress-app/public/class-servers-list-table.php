<?php
/** Modified for Simple Stack by One Dog Solutions on 2026-11-23. */

/**
 * Class to display servers table on front-end
 *
 * @author Tahir Nazir
 */

if ( ! class_exists( 'SSTK_Public_List_Table' ) ) {
	require_once SSTK_PATH . 'includes/core/apps/wordpress-app/public/class-public-list-table.php';
}

class Servers_List_Table extends SSTK_Public_List_Table {

	/**
	 * Constructor
	 *
	 * @param array $args
	 */
	public function __construct( $args = array() ) {

		$this->post_type = 'sstk_app_server';

		parent::__construct( $args );
		$this->prepare_items();
	}

	/**
	 * Id for front end listing query identification
	 *
	 * @return string
	 */
	protected function front_id() {
		return 'sstk_app_server_front';
	}

	/**
	 * @see WP_List_Table::ajax_user_can()
	 */
	public function ajax_user_can() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Print no item message
	 */
	public function no_items() {
		_e( 'We searched high and low but unfortunately we could not find any servers.', 'simple-stack' );
	}

	/**
	 * Return table columns
	 *
	 * @return array
	 */
	public function get_columns() {

		$columns = array(
			'title' => __( 'Title', 'simple-stack' ),
			'date'  => __( 'Date', 'simple-stack' ),
		);

		$columns = apply_filters( 'manage_sstk_app_server_posts_columns', $columns );

		return $columns;
	}

	/**
	 * Row action, view|trash|delete etc
	 *
	 * @param object $post
	 * @param string $column_name
	 * @param string $primary
	 *
	 * @return string|array
	 */
	protected function handle_row_actions( $post, $column_name, $primary ) {
		if ( 'title' !== $column_name ) {
			return '';
		}

		$post_type_object = get_post_type_object( $post->post_type );
		$actions          = array();
		$title            = _draft_or_post_title();

		if ( is_post_type_viewable( $post_type_object ) && 'trash' !== $post->post_status ) {

			$actions['view'] = sprintf(
				'<a href="%s" rel="bookmark" aria-label="%s">%s</a>',
				get_permalink( $post->ID ),
				/* translators: %s: Post title. */
				esc_attr( sprintf( __( 'View &#8220;%s&#8221;' ), $title ) ),
				__( 'View' )
			);
		}

		if ( current_user_can( 'delete_post', $post->ID ) ) {
			if ( 'trash' === $post->post_status ) {
				$actions['sstk_public_server_restore'] = sprintf(
					'<a class="sstk_public_row_del_item_action" data-sstk-action="restore" data-sstk-type="server" data-sstk-id="%d" data-sstk-nonce="%s" href="#">%s</a>',
					$post->ID,
					wp_create_nonce( 'sstk_public_server_restore_' . $post->ID ),
					esc_html( __( 'Restore', 'simple-stack' ) )
				);
			} elseif ( EMPTY_TRASH_DAYS ) {

				$actions['sstk_public_server_trash'] = sprintf(
					'<a class="sstk_public_row_del_item_action" data-sstk-action="trash" data-sstk-type="server" data-sstk-id="%d" data-sstk-nonce="%s" href="#">%s</a>',
					$post->ID,
					wp_create_nonce( 'sstk_public_server_trash_' . $post->ID ),
					_x( 'Trash', 'simple-stack' )
				);
			}

			if ( 'trash' === $post->post_status || ! EMPTY_TRASH_DAYS ) {
				$actions['sstk_public_server_delete'] = sprintf(
					'<a class="sstk_public_row_del_item_action" data-sstk-action="delete" data-sstk-type="server" data-sstk-id="%d" data-sstk-nonce="%s" href="#">%s</a>',
					$post->ID,
					wp_create_nonce( 'sstk_public_server_delete_' . $post->ID ),
					esc_html( __( 'Delete Permanently', 'simple-stack' ) )
				);
			}
		}

		$actions = apply_filters( 'post_row_actions', $actions, $post );

		return $this->row_actions( $actions );
	}


	/**
	 * A single column
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'title':
				return $this->getTitleColumn( $item );
				break;
			default:
				ob_start();
				do_action( 'manage_sstk_app_server_posts_custom_column', $column_name, $item->ID );
				return ob_get_clean();
				break;
		}

	}

}
