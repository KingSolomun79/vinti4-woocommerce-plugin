<?php
/**
 * Vinti4 Fingerprint Builder
 *
 * Generates SISP-compliant SHA-512 + Base64 fingerprints for payment requests.
 * The fingerprint algorithm must match SISP's exact specification — any mismatch
 * means payment requests are rejected.
 *
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'Vinti4_Fingerprint' ) ) {
	return;
}

/**
 * Class Vinti4_Fingerprint
 *
 * Provides static methods for generating cryptographic fingerprints required
 * by the SISP Vinti4 payment protocol. Uses SHA-512 hashing with Base64 encoding.
 *
 * Field ordering in the fingerprint concatenation:
 * 1. SHA-512 of posAuthCode
 * 2. Timestamp
 * 3. Amount × 1000
 * 4. Merchant reference
 * 5. Merchant session
 * 6. POS ID
 * 7. Currency code
 * 8. Transaction code
 * 9. Entity code (optional)
 * 10. Reference number (optional)
 * 11. Token (optional)
 *
 * @since 1.0.0
 */
class Vinti4_Fingerprint {

	/**
	 * Compute SHA-512 hash and return as Base64-encoded string.
	 *
	 * This is the core hash primitive used in the SISP fingerprint algorithm.
	 * It is called twice: once to hash the posAuthCode, and once to hash the
	 * final concatenated base string.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value The input string to hash.
	 * @return string Base64-encoded SHA-512 hash.
	 */
	public static function sha512_base64( string $value ): string {
		return base64_encode( hash( 'sha512', $value, true ) );
	}

	/**
	 * Build the full SISP request fingerprint.
	 *
	 * Concatenates fields in SISP-specified order, with the posAuthCode
	 * hashed first via SHA-512+Base64, then the entire base string hashed
	 * again to produce the final fingerprint.
	 *
	 * The amount is multiplied by 1000 as required by the SISP protocol
	 * (e.g. 100 → 100000 in the hash input).
	 *
	 * Optional fields (entity_code, reference_number, token) are only
	 * appended to the base string when they are non-empty.
	 *
	 * @since 1.0.0
	 *
	 * @param string $pos_auth_code   POS auth code (hashed first).
	 * @param string $timestamp       Formatted timestamp (yyyy-MM-dd HH:mm:ss).
	 * @param string $amount          Integer amount (multiplied by 1000).
	 * @param string $merchant_ref    Unique merchant reference.
	 * @param string $merchant_session Session identifier.
	 * @param string $pos_id          POS identifier.
	 * @param string $currency        ISO 4217 numeric currency code.
	 * @param string $transaction_code Transaction code (e.g. '1').
	 * @param string $entity_code     Optional entity code. Default ''.
	 * @param string $reference_number Optional reference number. Default ''.
	 * @param string $token           Optional token. Default ''.
	 * @return string Base64-encoded SHA-512 fingerprint.
	 */
	public static function build_request_fingerprint(
		string $pos_auth_code,
		string $timestamp,
		string $amount,
		string $merchant_ref,
		string $merchant_session,
		string $pos_id,
		string $currency,
		string $transaction_code,
		string $entity_code = '',
		string $reference_number = '',
		string $token = ''
	): string {
		$base = self::sha512_base64( $pos_auth_code )
			. trim( $timestamp )
			. (string) ( absint( $amount ) * 1000 )
			. trim( $merchant_ref )
			. trim( $merchant_session )
			. trim( $pos_id )
			. trim( $currency )
			. trim( $transaction_code );

		if ( '' !== $entity_code ) {
			$base .= (string) absint( ltrim( $entity_code, '0' ) ?: '0' );
		}
		if ( '' !== $reference_number ) {
			$base .= (string) absint( ltrim( $reference_number, '0' ) ?: '0' );
		}
		if ( '' !== $token ) {
			$base .= trim( $token );
		}

		return self::sha512_base64( $base );
	}
}
