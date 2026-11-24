<?php
/** Modified for Simple Stack by One Dog Solutions on 2026-11-23. */
/**
 * Trait:
 * Contains functions related to server maintenance history.
 *
 * @package simple-stack
 */

/**
 * Trait sstk_wpapp_upgrade_functions
 */
trait sstk_wpapp_upgrade_functions {

	/**
	 * Add data to an option that keeps track of server maintenance actions.
	 *
	 * @param int    $id               Post id of server being updated.
	 * @param string $history_key_type A key used to indicate the history being added.
	 * @param string $desc             Description of history element being added.
	 */
	public function update_history( $id, $history_key_type, $desc ) {

		// Get history array.
		$history = $this->get_update_history( $id );

		// Add to array.
		$history[] = array(
			'type' => $history_key_type,
			'time' => time(),
			'desc' => $desc,
		);

		// Write it back to post meta.
		update_post_meta( $id, 'sstk_server_update_history', $history );

	}

	/**
	 * Returns an array with update history items.
	 *
	 * @param int $id Post id of server being handled.
	 */
	public function get_update_history( $id ) {

		$history = sstk_maybe_unserialize( get_post_meta( $id, 'sstk_server_update_history', true ) );

		// Make sure we have a value and that it's an array.
		if ( empty( $history ) || ! is_array( $history ) ) {
			$history = array();
		}

		return $history;

	}

	/**
	 * Returns an HTML formatted string with update history.
	 *
	 * @param int $id Post id of server being handled.
	 */
	public function get_formatted_update_history( $id ) {

		$history = $this->get_update_history( $id );

		// Return right away if we have no history.
		if ( empty( $history ) ) {
			return '';
		}

		// Initialize return value.
		$return = '';

		foreach ( $history as $key => $value ) {

			$return .= '<div class="sstk_update_history_item">';
			$return .= '<div class="sstk_update_history_item_datetime">';
			$return .= gmdate( 'Y/m/d g:i A', $value['time'] );
			$return .= '</div>';
			$return .= '<div class="sstk_update_history_desc">';
			$return .= $value['desc'];
			$return .= '</div>';
			$return .= '</div>';

		}

		$return = '<div class="sstk_update_history_wrap">' . $return . '</div>';

		return $return;

	}

}
