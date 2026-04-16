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
 * Combines the WooCommerce order ID with a timestamp to produce a
 * unique-per-attempt reference string in the pattern WC{id}-YYYYMMDDHHmmss.
 *
 * @since 1.0.0
 *
 * @param int $order_id The WooCommerce order ID.
 * @return string Merchant reference e.g. 'WC42-20260416143022'.
 */
function vinti4_build_merchant_ref( int $order_id ): string {
	return 'WC' . $order_id . '-' . gmdate( 'YmdHis' );
}

/**
 * Generate a random merchant session identifier.
 *
 * Produces a 13-character alphanumeric string prefixed with 'S',
 * suitable for use as the merchantSession field in SISP requests.
 *
 * @since 1.0.0
 *
 * @return string Session identifier e.g. 'Sabc123def456'.
 */
function vinti4_build_merchant_session(): string {
	return 'S' . wp_generate_password( 12, false, false );
}

/**
 * Parse the order ID from a merchant reference string.
 *
 * Extracts the numeric order ID from the merchantRef pattern WC{id}-...
 * using a regex match. Returns 0 if the pattern doesn't match.
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
