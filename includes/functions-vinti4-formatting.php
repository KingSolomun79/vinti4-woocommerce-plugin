<?php
/**
 * Vinti4 Formatting Helpers
 *
 * Standalone functions for normalizing payment data to SISP protocol format.
 * Covers amount normalization, timestamp formatting, merchant reference/session
 * generation, reference parsing, and phone number shaping.
 *
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Normalize a float amount to an integer for SISP protocol.
 *
 * Rounds the amount to the nearest integer and returns the absolute value.
 * This is the base integer amount BEFORE the ×1000 multiplication that
 * happens inside the fingerprint builder.
 *
 * @since 1.0.0
 *
 * @param float $amount The order total as a float (e.g. 1500.49).
 * @return int The rounded absolute integer (e.g. 1500 or 1501).
 */
function vinti4_normalize_amount( float $amount ): int {
	return absint( round( $amount ) );
}

/**
 * Generate a UTC timestamp in SISP-required format.
 *
 * Returns current UTC time as 'Y-m-d H:i:s' which matches the SISP
 * purchase request timestamp format exactly.
 *
 * @since 1.0.0
 *
 * @return string UTC timestamp formatted as 'yyyy-MM-dd HH:mm:ss'.
 */
function vinti4_format_timestamp(): string {
	return gmdate( 'Y-m-d H:i:s' );
}

/**
 * Build a unique merchant reference for a payment attempt.
 *
 * Builds a 15-character reference in the format MM + yymmddHHMMSS + suffix.
 *
 * SISP integrations may enforce fixed-length merchantRef/merchantSession
 * values. This helper keeps merchantRef at exactly 15 characters.
 *
 * @since 1.0.0
 *
 * @param int $order_id Optional order ID (kept for backward compatibility).
 * @return string Merchant reference e.g. 'MM2604170944241'.
 */
function vinti4_build_merchant_ref( int $order_id ): string {
	unset( $order_id );

	return 'MM' . gmdate( 'ymdHis' ) . (string) mt_rand( 0, 9 );
}

/**
 * Generate a random merchant session identifier.
 *
 * Produces a 15-character value in the format MS + yymmddHHMMSS + suffix.
 *
 * @since 1.0.0
 *
 * @return string Session identifier e.g. 'MS2604170944242'.
 */
function vinti4_build_merchant_session(): string {
	return 'MS' . gmdate( 'ymdHis' ) . (string) mt_rand( 0, 9 );
}

/**
 * Parse the order ID from a merchant reference string.
 *
 * Extracts the numeric order ID from legacy merchantRef pattern WC{id}-...
 * using a regex match. Returns 0 for fixed-length MM/MS style references.
 *
 * @since 1.0.0
 *
 * @param string $merchant_ref The merchant reference string (e.g. 'WC42-20260416143022').
 * @return int The extracted order ID, or 0 on failure.
 */
function vinti4_parse_order_id_from_ref( string $merchant_ref ): int {
	if ( preg_match( '/^WC(\d+)-/', $merchant_ref, $matches ) ) {
		return (int) $matches[1];
	}
	return 0;
}

/**
 * Find order ID by stored merchantRef meta.
 *
 * Used for fixed-length merchantRef formats that do not embed order IDs.
 *
 * @param string $merchant_ref Merchant reference received from callback.
 * @return int Order ID or 0 when not found.
 */
function vinti4_find_order_id_by_merchant_ref( string $merchant_ref ): int {
	if ( '' === trim( $merchant_ref ) || ! function_exists( 'wc_get_orders' ) ) {
		return 0;
	}

	$orders = wc_get_orders(
		array(
			'limit'  => 1,
			'return' => 'ids',
			'meta_query' => array(
				array(
					'key'   => '_vinti4_merchant_ref',
					'value' => $merchant_ref,
				),
			),
		)
	);

	if ( is_array( $orders ) && ! empty( $orders[0] ) ) {
		return (int) $orders[0];
	}

	return 0;
}

/**
 * Shape a phone number into country code and subscriber components.
 *
 * Strips all non-digit characters, then splits into country code (cc)
 * and subscriber number. If the cleaned number has 9+ digits, the last
 * 9 digits become the subscriber and the rest become the country code
 * (with leading zeros stripped). If fewer than 9 digits, all digits
 * become the subscriber with an empty country code.
 *
 * @since 1.0.0
 *
 * @param string $phone Raw phone number string.
 * @return array{cc: string, subscriber: string} Associative array with 'cc' and 'subscriber' keys.
 */
function vinti4_shape_phone( string $phone ): array {
	$digits = preg_replace( '/\D/', '', $phone );

	$cc         = '';
	$subscriber = '';

	if ( strlen( $digits ) >= 9 ) {
		$subscriber = substr( $digits, -9 );
		$cc         = ltrim( substr( $digits, 0, -9 ), '0' );
	} else {
		$subscriber = $digits;
	}

	return array(
		'cc'         => $cc,
		'subscriber' => $subscriber,
	);
}

/**
 * Check whether a SISP callback messageType indicates a successful payment.
 *
 * Success types are '8' (authorization), '10' (capture), 'M' and 'P'
 * (SISP-specific success codes). Uses strict comparison via the third
 * parameter to prevent type coercion (e.g. integer 8 matching '8').
 *
 * @since 1.0.0
 *
 * @param string $message_type The messageType field from the SISP callback response.
 * @return bool True if the message type indicates a successful payment, false otherwise.
 */
function vinti4_is_success_message_type( string $message_type ): bool {
	return in_array( $message_type, array( '8', '10', 'M', 'P' ), true );
}
