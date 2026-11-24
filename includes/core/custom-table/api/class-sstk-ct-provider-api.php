<?php
/** Modified for Simple Stack by One Dog Solutions on 2026-11-23. */

/**
 * Class Provider API
 */
class SSTK_CT_Provider_API extends SSTK_Custom_Table_API {
	
	/**
	 * Model Name
	 * 
	 * @var string
	 */
	public $model_name = 'provider';
	
	/**
	 * Table Name
	 * 
	 * @var string 
	 */
	public $table_name = 'providers';
	
	
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
	
	
}