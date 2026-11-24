<?php
/** Modified for Simple Stack by One Dog Solutions on 2026-11-23. */

if ( ! class_exists( 'SSTK_Public_List_Table' ) ) {
	require_once SSTK_PATH . 'includes/core/apps/wordpress-app/public/class-public-list-table.php';
}

require_once SSTK_PATH . 'includes/core/apps/wordpress-app/public/traits/grid_table.php';
require_once SSTK_PATH . 'includes/core/apps/wordpress-app/public/traits/table_pagination.php';

/**
 * Class to display tables on front-end for custom table
 */
class SSTK_CT_Public_List_Table extends SSTK_CT_List_Table {
	
	use sstk_grid_table;
	use table_pagination;
	
	/**
	 * Base url for table page
	 * 
	 * @var string
	 */
	protected $base_url;
	
	/**
	 * Model name
	 * 
	 * @var string
	 */
	private $model;
	
	/**
	 * DB Table name
	 * 
	 * @var string
	 */
	private $table;
	
	/**
	 * Post type
	 * 
	 * @var string
	 */
	public $post_type;

	public function __construct( $args ) {
		
		parent::__construct( $args );
		$this->model    = $args['model'];
		$this->table    = $this->model->table;
		$this->base_url = admin_url( "admin-ajax.php?page=model-{$this->model->name}" );
		$this->model_id = isset( $args['model_id'] ) ? $args['model_id'] : '';
		
	}
	
	
	/**
	 * Return Table columns
	 * 
	 * @return array
	 */
	public function get_columns() {
		$columns = [
			'id' => __( 'ID', 'mb-custom-table' ),
		];

		return apply_filters( "mbct_{$this->model->name}_columns", $columns );
	}
	
	
	/**
	 * Primary column name
	 * 
	 * @return string
	 */
	public function getPrimaryColumn() {
		return 'id';
	}
	
	/**
	 * Return Views
	 * 
	 * @return string
	 */
	public function views() {
		return '';
	}
}
