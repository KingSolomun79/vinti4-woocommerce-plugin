<?php
/**
 * Vinti4 Logger Utility
 *
 * Provides logging for Vinti4 payment events using the WooCommerce logger.
 * Gated by the debug checkbox setting. Masks the POS auth code so it never
 * appears in full in any log entry.
 *
 * @package Vinti4ForWooCommerce
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'Vinti4_Logger' ) ) {
	return;
}

/**
 * Class Vinti4_Logger
 *
 * Static logger utility for the Vinti4 payment gateway.
 */
class Vinti4_Logger {

	/**
	 * Whether debug logging is enabled.
	 *
	 * @var bool
	 */
	private static bool $debug = false;

	/**
	 * Initialize the logger with the gateway settings.
	 *
	 * Sets the debug flag based on the gateway's debug option.
	 * Called from the gateway constructor after settings are loaded.
	 *
	 * @param WC_Gateway_Vinti4 $gateway The gateway instance.
	 * @return void
	 */
	public static function init( WC_Gateway_Vinti4 $gateway ): void {
		self::$debug = ( 'yes' === $gateway->debug );
	}

	/**
	 * Log a message to the WooCommerce logger.
	 *
	 * Does nothing when debug logging is disabled. Messages appear under
	 * WooCommerce → Status → Logs with source "vinti4".
	 *
	 * @param string $message The message to log.
	 * @param string $level   Log level: 'debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'.
	 * @return void
	 */
	public static function log( string $message, string $level = 'debug' ): void {
		if ( ! self::$debug ) {
			return;
		}

		wc_get_logger()->log( $level, $message, array( 'source' => 'vinti4' ) );
	}

	/**
	 * Mask a POS auth code for safe logging.
	 *
	 * Returns a partially masked version of the code so the full value
	 * never appears in log entries.
	 *
	 * - Empty string returns empty string.
	 * - Length < 6: first char + asterisks for remaining (e.g. "A***" for "Abcd").
	 * - Length >= 6: first 3 + asterisks + last 2 (e.g. "ABC***YZ" for "ABCDEFGHYZ").
	 *
	 * @param string $code The auth code to mask.
	 * @return string The masked auth code.
	 */
	public static function mask_auth_code( string $code ): string {
		if ( '' === $code ) {
			return '';
		}

		$length = strlen( $code );

		if ( $length < 6 ) {
			return $code[0] . str_repeat( '*', $length - 1 );
		}

		return substr( $code, 0, 3 ) . str_repeat( '*', $length - 5 ) . substr( $code, -2 );
	}

	/**
	 * Check whether debug logging is enabled.
	 *
	 * @return bool True if debug logging is enabled.
	 */
	public static function is_enabled(): bool {
		return self::$debug;
	}
}
