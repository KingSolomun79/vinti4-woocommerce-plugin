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
	 * Build a safe, field-by-field snapshot of request fingerprint inputs.
	 *
	 * This helper is intended for debug logging only. It never returns the raw
	 * POS auth code nor the full SHA-512 auth hash that participates in the
	 * fingerprint algorithm.
	 *
	 * @param string $pos_auth_code    POS auth code.
	 * @param string $timestamp        Timestamp.
	 * @param string $amount           Amount before x1000 scaling.
	 * @param string $merchant_ref     Merchant reference.
	 * @param string $merchant_session Merchant session.
	 * @param string $pos_id           POS identifier.
	 * @param string $currency         Currency code.
	 * @param string $transaction_code Transaction code.
	 * @param string $entity_code      Optional entity code.
	 * @param string $reference_number Optional reference number.
	 * @param string $token            Optional token.
	 * @return array<string, mixed>
	 */
	public static function build_request_fingerprint_debug_snapshot(
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
	): array {
		$auth_hash_b64      = self::sha512_base64( $pos_auth_code );
		$amount_x1000       = (string) ( absint( $amount ) * 1000 );
		$entity_normalized  = '';
		$reference_normalized = '';
		$token_normalized   = '';

		if ( '' !== $entity_code ) {
			$entity_normalized = (string) absint( ltrim( $entity_code, '0' ) ?: '0' );
		}

		if ( '' !== $reference_number ) {
			$reference_normalized = (string) absint( ltrim( $reference_number, '0' ) ?: '0' );
		}

		if ( '' !== $token ) {
			$token_normalized = trim( $token );
		}

		$segments = array(
			'[SHA512_B64(posAuthCode)]',
			trim( $timestamp ),
			$amount_x1000,
			trim( $merchant_ref ),
			trim( $merchant_session ),
			trim( $pos_id ),
			trim( $currency ),
			trim( $transaction_code ),
		);

		if ( '' !== $entity_normalized ) {
			$segments[] = $entity_normalized;
		}

		if ( '' !== $reference_normalized ) {
			$segments[] = $reference_normalized;
		}

		if ( '' !== $token_normalized ) {
			$segments[] = $token_normalized;
		}

		return array(
			'algorithm' => 'sha512_base64( sha512_base64(posAuthCode) + ordered fields )',
			'sensitive' => array(
				'posAuthCode' => array(
					'length'               => strlen( $pos_auth_code ),
					'sha512_b64_preview'   => substr( $auth_hash_b64, 0, 12 ) . '...',
					'raw_exposed'          => false,
				),
			),
			'fields' => array(
				'timestamp' => trim( $timestamp ),
				'amount' => array(
					'raw' => trim( $amount ),
					'normalized_x1000' => $amount_x1000,
				),
				'merchantRef' => trim( $merchant_ref ),
				'merchantSession' => trim( $merchant_session ),
				'posID' => trim( $pos_id ),
				'currency' => trim( $currency ),
				'transactionCode' => trim( $transaction_code ),
				'entityCode' => '' === $entity_normalized ? null : $entity_normalized,
				'referenceNumber' => '' === $reference_normalized ? null : $reference_normalized,
				'token' => '' === $token_normalized ? null : '[present]',
			),
			'ordered_segments' => $segments,
			'segment_count' => count( $segments ),
		);
	}

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

	/**
	 * Build the SISP response fingerprint for callback verification.
	 *
	 * Recomputes the fingerprint from callback POST fields so it can be
	 * compared against the `resultFingerPrint` value sent by SISP.
	 *
	 * Algorithm mirrors the SISP specification exactly:
	 *   sha512_base64(posAuthCode) is concatenated with the remaining
	 *   response fields in the documented order, then the entire base
	 *   string is hashed again with sha512_base64 to produce the fingerprint.
	 *
	 * The purchase amount follows the same ×1000 convention used in
	 * the request fingerprint.
	 *
	 * @since 1.0.0
	 *
	 * @param string $pos_auth_code           SISP `posAuthCode` field.
	 * @param string $message_type            SISP `messageType` field.
	 * @param string $clearing_period         SISP `clearingPeriod` field.
	 * @param string $transaction_id          SISP `transactionID` field.
	 * @param string $merchant_ref            SISP `merchantReference` field.
	 * @param string $merchant_session        SISP `merchantSession` field.
	 * @param string $purchase_amount         SISP `purchaseAmount` field (integer string, ×1000 applied).
	 * @param string $message_id              SISP `messageID` field.
	 * @param string $pan                     SISP `pan` (masked card number) field.
	 * @param string $merchant_response       SISP `merchantResponse` field.
	 * @param string $timestamp               SISP `timestamp` field.
	 * @param string $reference_number        SISP `referenceNumber` field.
	 * @param string $entity_code             SISP `entityCode` field.
	 * @param string $client_receipt          SISP `clientReceipt` field.
	 * @param string $additional_error_message SISP `additionalErrorMessage` field.
	 * @param string $reload_code             SISP `reloadCode` field.
	 * @return string Base64-encoded SHA-512 response fingerprint.
	 */
	public static function build_response_fingerprint(
		string $pos_auth_code,
		string $message_type,
		string $clearing_period,
		string $transaction_id,
		string $merchant_ref,
		string $merchant_session,
		string $purchase_amount,
		string $message_id,
		string $pan,
		string $merchant_response,
		string $timestamp,
		string $reference_number,
		string $entity_code,
		string $client_receipt,
		string $additional_error_message,
		string $reload_code
	): string {
		$base = self::sha512_base64( $pos_auth_code )
			. trim( $message_type )
			. trim( $clearing_period )
			. trim( $transaction_id )
			. trim( $merchant_ref )
			. trim( $merchant_session )
			. (string) ( absint( $purchase_amount ) * 1000 )
			. trim( $message_id )
			. trim( $pan )
			. trim( $merchant_response )
			. trim( $timestamp )
			. trim( $reference_number )
			. trim( $entity_code )
			. trim( $client_receipt )
			. trim( $additional_error_message )
			. trim( $reload_code );

		return self::sha512_base64( $base );
	}
}
