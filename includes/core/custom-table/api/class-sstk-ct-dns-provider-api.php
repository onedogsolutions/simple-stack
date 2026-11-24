<?php
/** Modified for Simple Stack by One Dog Solutions on 2026-11-23. */

/**
 * Class DNS Provider API
 */
class SSTK_CT_DNS_Provider_API extends SSTK_Custom_Table_API {
	
	/**
	 * Model Name
	 * 
	 * @var string
	 */
	public $model_name = 'dns_provider';
	
	/**
	 * Table Name
	 * 
	 * @var string 
	 */
	public $table_name = 'dns_providers';
	
	/**
	 * Holds a reference to this class
	 *
	 * @var $instance instance.
	 */
	private static $instance;
	
	/**
	 * Return instance of self.
	 */
	public static function instance() {
		$class = get_called_class();
		if ( is_null( self::$instance ) ) {
			self::$instance = new $class();
		}
		return self::$instance;
	}
	
	/**
	 * Return DNS Name
	 * 
	 * @param int $item_id
	 * 
	 * @return string
	 */
	public function get_item_display_name( $item_id ) {
		
		$item = $this->get_by_id( $item_id );
		
		$name = '';
		if( $item ) {
			$name = $item->dns_name;
		}
		
		return $name;
	}
}