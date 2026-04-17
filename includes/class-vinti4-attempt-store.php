<?php
/**
 * Attempt persistence boundary for Vinti4 payment attempts.
 *
 * @since 1.1.0
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'Vinti4_Attempt_Store' ) ) {
	return;
}

class Vinti4_Attempt_Store {

	/**
	 * Canonical order meta key containing append-only attempt history.
	 */
	public const HISTORY_META_KEY = '_vinti4_attempt_history';

	/**
	 * Append a new immutable attempt record to order history.
	 *
	 * @param WC_Order $order          WooCommerce order.
	 * @param array    $attempt        Attempt payload.
	 * @param bool     $project_latest When true, mirrors latest attempt to legacy keys.
	 * @return array The stored attempt record including sequence and timestamp.
	 */
	public static function append_attempt( WC_Order $order, array $attempt, bool $project_latest = true ): array {
		$history = self::get_attempts( $order );

		$next_sequence = 1;
		if ( ! empty( $history ) ) {
			$sequences = array_column( $history, 'sequence' );
			$sequences = array_map( 'intval', $sequences );
			$next_sequence = max( $sequences ) + 1;
		}

		$stored_attempt = $attempt;
		$stored_attempt['sequence'] = $next_sequence;
		$stored_attempt['created_at_gmt'] = self::resolve_created_at_gmt( $attempt['created_at_gmt'] ?? null );

		$history[] = $stored_attempt;
		$history = self::sort_attempts_chronologically( $history );

		$order->update_meta_data( self::HISTORY_META_KEY, $history );

		if ( $project_latest ) {
			self::project_latest_attempt_to_legacy_meta( $order, $stored_attempt );
		}

		$order->save();

		return $stored_attempt;
	}

	/**
	 * Return attempts in deterministic oldest-to-newest order.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return array
	 */
	public static function get_attempts( WC_Order $order ): array {
		$history = $order->get_meta( self::HISTORY_META_KEY, true );

		if ( ! is_array( $history ) ) {
			return array();
		}

		$normalized = array();
		foreach ( $history as $attempt ) {
			if ( ! is_array( $attempt ) ) {
				continue;
			}

			$attempt['sequence'] = isset( $attempt['sequence'] ) ? (int) $attempt['sequence'] : 0;
			$attempt['created_at_gmt'] = self::resolve_created_at_gmt( $attempt['created_at_gmt'] ?? null );
			$normalized[] = $attempt;
		}

		return self::sort_attempts_chronologically( $normalized );
	}

	/**
	 * Find an attempt in order history by its merchant reference.
	 *
	 * Iterates attempt history to find the attempt whose merchant_ref
	 * matches the given reference. Returns null if not found.
	 *
	 * @since 1.1.0
	 *
	 * @param WC_Order $order       WooCommerce order.
	 * @param string   $merchant_ref Merchant reference to search for.
	 * @return array|null Attempt array or null if not found.
	 */
	public static function find_attempt_by_merchant_ref( WC_Order $order, string $merchant_ref ): ?array {
		$attempts = self::get_attempts( $order );

		foreach ( $attempts as $attempt ) {
			if ( isset( $attempt['merchant_ref'] ) && $attempt['merchant_ref'] === $merchant_ref ) {
				return $attempt;
			}
		}

		Vinti4_Logger::log(
			sprintf(
				'Attempt lookup failed: merchantRef %s not in order %d history.',
				$merchant_ref,
				$order->get_id()
			),
			'warning'
		);

		return null;
	}

	/**
	 * Calculate the total paid amount from completed attempts.
	 *
	 * Sums amounts from attempts where status is 'completed' or
	 * callback_received is true. Caches result in order meta.
	 *
	 * @since 1.1.0
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return float Total paid amount.
	 */
	public static function get_paid_total( WC_Order $order ): float {
		$attempts = self::get_attempts( $order );
		$paid     = 0.0;

		foreach ( $attempts as $attempt ) {
			$is_completed = isset( $attempt['status'] ) && 'completed' === $attempt['status'];
			$is_callback  = ! empty( $attempt['callback_received'] );

			if ( $is_completed || $is_callback ) {
				$amount = isset( $attempt['amount'] ) ? (float) $attempt['amount'] : 0.0;
				$paid  += $amount;
			}
		}

		$order->update_meta_data( '_vinti4_paid_total', $paid );
		$order->save();

		return $paid;
	}

	/**
	 * Calculate the outstanding (unpaid) balance for an order.
	 *
	 * Returns the difference between the order total and paid total,
	 * floored at zero to avoid negative balances.
	 *
	 * @since 1.1.0
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return float Outstanding amount (never negative).
	 */
	public static function get_outstanding_total( WC_Order $order ): float {
		$order_total = (float) $order->get_total();
		$paid_total  = self::get_paid_total( $order );

		return max( 0.0, $order_total - $paid_total );
	}

	/**
	 * Mark an attempt as completed with its transaction ID.
	 *
	 * Updates the attempt status in append-only history and refreshes
	 * the paid total cache. Does not overwrite existing completed state.
	 *
	 * @since 1.1.0
	 *
	 * @param WC_Order $order          WooCommerce order.
	 * @param string   $attempt_id     Attempt identifier.
	 * @param string   $transaction_id SISP transaction ID.
	 * @return void
	 */
	public static function mark_attempt_completed( WC_Order $order, string $attempt_id, string $transaction_id ): void {
		$history  = self::get_attempts( $order );
		$modified = false;

		foreach ( $history as $index => $attempt ) {
			if ( isset( $attempt['attempt_id'] ) && $attempt['attempt_id'] === $attempt_id ) {
				$history[ $index ]['status']          = 'completed';
				$history[ $index ]['transaction_id']  = $transaction_id;
				$history[ $index ]['callback_received'] = true;
				$modified = true;
				break;
			}
		}

		if ( $modified ) {
			$order->update_meta_data( self::HISTORY_META_KEY, $history );
			self::get_paid_total( $order ); // Refreshes _vinti4_paid_total cache.
		}
	}

	/**
	 * Mark an attempt as failed with an error message.
	 *
	 * Updates the attempt status in append-only history.
	 *
	 * @since 1.1.0
	 *
	 * @param WC_Order $order         WooCommerce order.
	 * @param string   $attempt_id    Attempt identifier.
	 * @param string   $error_message Failure reason.
	 * @return void
	 */
	public static function mark_attempt_failed( WC_Order $order, string $attempt_id, string $error_message ): void {
		$history  = self::get_attempts( $order );
		$modified = false;

		foreach ( $history as $index => $attempt ) {
			if ( isset( $attempt['attempt_id'] ) && $attempt['attempt_id'] === $attempt_id ) {
				$history[ $index ]['status'] = 'failed';
				$history[ $index ]['error']  = $error_message;
				$modified = true;
				break;
			}
		}

		if ( $modified ) {
			$order->update_meta_data( self::HISTORY_META_KEY, $history );
			$order->save();
		}
	}

	/**
	 * Mirror the newest attempt into legacy single-attempt meta keys.
	 *
	 * This compatibility projection preserves existing redirect/callback paths
	 * while the append-only history remains the source of truth.
	 *
	 * @param WC_Order $order   WooCommerce order.
	 * @param array    $attempt Attempt payload.
	 * @return void
	 */
	public static function project_latest_attempt_to_legacy_meta( WC_Order $order, array $attempt ): void {
		$legacy_map = array(
			'_vinti4_attempt_id'            => 'attempt_id',
			'_vinti4_timestamp'             => 'timestamp',
			'_vinti4_merchant_ref'          => 'merchant_ref',
			'_vinti4_merchant_session'      => 'merchant_session',
			'_vinti4_transaction_code'      => 'transaction_code',
			'_vinti4_amount'                => 'amount',
			'_vinti4_currency'              => 'currency',
			'_vinti4_language_messages'     => 'languageMessages',
			'_vinti4_url_merchant_response' => 'urlMerchantResponse',
			'_vinti4_is_3dsec'              => 'is3DSec',
			'_vinti4_fingerprint_version'   => 'FingerPrintVersion',
			'_vinti4_purchase_request_b64'  => 'purchase_request_b64',
			'_vinti4_fingerprint'           => 'fingerprint',
		);

		foreach ( $legacy_map as $meta_key => $attempt_key ) {
			if ( array_key_exists( $attempt_key, $attempt ) ) {
				$order->update_meta_data( $meta_key, $attempt[ $attempt_key ] );
			}
		}
	}

	/**
	 * Normalize created_at_gmt values to a deterministic UTC string.
	 *
	 * @param mixed $raw Created-at candidate value.
	 * @return string
	 */
	private static function resolve_created_at_gmt( $raw ): string {
		if ( is_string( $raw ) && '' !== trim( $raw ) ) {
			return trim( $raw );
		}

		return gmdate( 'Y-m-d H:i:s' );
	}

	/**
	 * Sort attempts oldest-to-newest with deterministic tie-breaking.
	 *
	 * @param array $attempts Attempt list.
	 * @return array
	 */
	private static function sort_attempts_chronologically( array $attempts ): array {
		usort(
			$attempts,
			static function ( array $left, array $right ): int {
				$left_time  = strtotime( (string) ( $left['created_at_gmt'] ?? '' ) );
				$right_time = strtotime( (string) ( $right['created_at_gmt'] ?? '' ) );

				if ( false === $left_time ) {
					$left_time = 0;
				}
				if ( false === $right_time ) {
					$right_time = 0;
				}

				if ( $left_time === $right_time ) {
					$left_sequence  = isset( $left['sequence'] ) ? (int) $left['sequence'] : 0;
					$right_sequence = isset( $right['sequence'] ) ? (int) $right['sequence'] : 0;
					return $left_sequence <=> $right_sequence;
				}

				return $left_time <=> $right_time;
			}
		);

		return $attempts;
	}
}
