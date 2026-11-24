<?php
/**
 * Modified for Simple Stack by One Dog Solutions on 2026-11-23.
 *
 * Recursive secret redaction for admin SPA REST responses.
 *
 * @package simple-stack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SSTK_Secret_Redactor
 *
 * Payloads returned through SSTK_REST_Controller_Base::respond() pass through this
 * class. It masks array values whose key looks like a credential and scrubs PEM
 * private key blocks that appear inside free text such as command logs, SSH
 * transcripts and provider exception messages. Other REST controller families and
 * public callbacks do not inherit this automatic redaction boundary.
 *
 * The redactor is deliberately blunt: it replaces whole values rather than trying
 * to figure out which characters inside a value are the secret. A false positive
 * costs a round trip; a false negative leaks a root credential.
 */
class SSTK_Secret_Redactor {

	/**
	 * Replacement string used for every masked value.
	 */
	const MASK = '********';

	/**
	 * Array keys whose values are always masked.
	 *
	 * Matches password, secret, token, "private", authorization headers, any key
	 * ending in "_key" and api key spellings in either order.
	 */
	const SECRET_KEY_PATTERN = '/pass|secret|token|private|authorization|credential|^key$|_key$|api_?key/i';

	/**
	 * Recursively redact a value.
	 *
	 * Scalars are scrubbed for embedded PEM blocks and credential assignments.
	 * Arrays are walked and any entry whose key matches SECRET_KEY_PATTERN is
	 * replaced with MASK, except for boolean and null presence signals. Objects are
	 * converted to arrays first because the REST layer only ever serialises arrays
	 * and scalars.
	 *
	 * @param mixed $data The value to redact.
	 * @param int   $depth Current recursion depth, used as a runaway guard.
	 *
	 * @return mixed The redacted value.
	 */
	public static function redact( $data, $depth = 0 ) {
		return sstk_redact_secrets( $data, array(), self::MASK, $depth );
	}

	/**
	 * Determine whether an array key names a credential.
	 *
	 * @param string $key The array key.
	 *
	 * @return bool
	 */
	public static function key_is_secret( $key ) {
		return sstk_is_secret_key( $key );
	}

	/**
	 * Remove credential material embedded in a free-text string.
	 *
	 * @param string $text    The string to scrub.
	 * @param array  $secrets Exact secret values to remove.
	 *
	 * @return string
	 */
	public static function scrub_text( $text, $secrets = array() ) {
		return sstk_redact_secret_text( $text, $secrets, self::MASK );
	}

	/**
	 * Reduce a credential to a boolean "is it set" signal.
	 *
	 * Provider classes must never hand a key value to the REST layer, so callers
	 * use this helper to expose presence instead of content.
	 *
	 * @param mixed $value The raw credential value.
	 *
	 * @return bool
	 */
	public static function is_present( $value ) {
		return ! empty( $value );
	}
}
