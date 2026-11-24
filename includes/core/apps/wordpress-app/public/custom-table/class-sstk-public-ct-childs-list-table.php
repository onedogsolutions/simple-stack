<?php
/** Modified for Simple Stack by One Dog Solutions on 2026-11-23. */

require_once SSTK_PATH . 'includes/core/custom-table/class-sstk-ct-childs-list-table.php';
require_once SSTK_PATH . 'includes/core/apps/wordpress-app/public/traits/grid_table.php';

class SSTK_Public_CT_Childs_List_Table extends SSTK_CT_Childs_List_Table {
	
	use sstk_grid_table;
	
	/**
	 * Return default column
	 * 
	 * @return string
	 */
	public function getPrimaryColumn() {
		return 'id';
	}
	
}