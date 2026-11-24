<?php
/**
 * Recursive secret redaction for the SimpleStack REST layer.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleStack_Secret_Redactor
 *
 * Every SimpleStack REST response passes through this class before it leaves the
 * process. It masks array values whose key looks like a credential and scrubs PEM
 * private key blocks that appear inside free text such as command logs, SSH
 * transcripts and provider exception messages.
 *
 * The redactor is deliberately blunt: it replaces whole values rather than trying
 * to figure out which characters inside a value are the secret. A false positive
 * costs a round trip; a false negative leaks a root credential.
 */
class SimpleStack_Secret_Redactor {

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
	const SECRET_KEY_PATTERN = '/pass|secret|token|private|authorization|_key$|api_?key/i';

	/**
	 * Free text patterns that are scrubbed in place.
	 *
	 * The first entry removes whole PEM blocks including the body. The remaining
	 * entries collapse credential-looking shell assignments down to the key name.
	 */
	const SECRET_TEXT_PATTERNS = array(
		'/-----BEGIN [A-Z ]*PRIVATE KEY-----.*?-----END [A-Z ]*PRIVATE KEY-----/s' => '[redacted private key]',
		'/\b(password|passwd|pass|secret|token|api[_-]?key)(\s*[=:]\s*)("[^"]*"|\'[^\']*\'|\S+)/i' => '$1$2' . self::MASK,
		'/\b(authorization)\s*:\s*(bearer|basic)\s+\S+/i' => '$1: $2 ' . self::MASK,
	);

	/**
	 * Recursively redact a value.
	 *
	 * Scalars are scrubbed for embedded PEM blocks and credential assignments.
	 * Arrays are walked and any entry whose key matches SECRET_KEY_PATTERN is
	 * replaced with MASK. Objects are converted to arrays first because the REST
	 * layer only ever serialises arrays and scalars.
	 *
	 * @param mixed $data The value to redact.
	 * @param int   $depth Current recursion depth, used as a runaway guard.
	 *
	 * @return mixed The redacted value.
	 */
	public static function redact( $data, $depth = 0 ) {

		/* Guard against pathological or circular structures. */
		if ( $depth > 25 ) {
			return self::MASK;
		}

		if ( is_object( $data ) ) {
			$data = get_object_vars( $data );
		}

		if ( is_array( $data ) ) {
			$clean = array();

			foreach ( $data as $key => $value ) {
				if ( is_string( $key ) && self::key_is_secret( $key ) ) {
					$clean[ $key ] = self::MASK;
					continue;
				}

				$clean[ $key ] = self::redact( $value, $depth + 1 );
			}

			return $clean;
		}

		if ( is_string( $data ) ) {
			return self::scrub_text( $data );
		}

		return $data;
	}

	/**
	 * Determine whether an array key names a credential.
	 *
	 * @param string $key The array key.
	 *
	 * @return bool
	 */
	public static function key_is_secret( $key ) {
		return 1 === preg_match( self::SECRET_KEY_PATTERN, $key );
	}

	/**
	 * Remove credential material embedded in a free-text string.
	 *
	 * @param string $text The string to scrub.
	 *
	 * @return string
	 */
	public static function scrub_text( $text ) {

		if ( '' === $text ) {
			return $text;
		}

		foreach ( self::SECRET_TEXT_PATTERNS as $pattern => $replacement ) {
			$text = preg_replace( $pattern, $replacement, $text );
		}

		return $text;
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
