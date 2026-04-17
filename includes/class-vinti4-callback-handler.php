<?php
/**
 * Vinti4 Callback Handler
 *
 * Handles SISP payment callback (POST from SISP back to WooCommerce).
 * Validates the response fingerprint, checks per-attempt idempotency, verifies amount,
 * and completes or fails the order accordingly.
 *
 * Supports both attempt-level resolution (v1.1+) and legacy order-level validation
 * for orders created before attempt history was introduced.
 *
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'Vinti4_Callback_Handler' ) ) {
	return;
}

/**
 * Class Vinti4_Callback_Handler
 *
 * Provides a single static handle() method that receives the SISP callback,
 * validates every field, and drives the order to the correct final state.
 *
 * Validation chain:
 * 1. Extract and sanitize POST data
 * 2. Parse order ID from merchantRef, load order
 * 3. Resolve attempt from history by merchantRef (v1.1+) or use legacy path
 * 4. Per-attempt idempotency check via _vinti4_attempt_{id}_processed meta
 * 5. Determine success vs failure from messageType
 * 6. On success: validate response fingerprint
 * 7. On success: validate amount against attempt context
 * 8. On success: mark attempt completed and handle partial/full payment
 * 9. On failure: mark attempt failed and order status
 *
 * @since 1.0.0
 */
class Vinti4_Callback_Handler {

	/**
	 * Handle a SISP payment callback.
	 *
	 * Called by the woocommerce_api_{gateway_id} action. Reads POST data from
	 * SISP, validates the response, and completes or fails the WooCommerce order.
	 *
	 * Attempt-level resolution (v1.1+):
	 * - Resolves the exact attempt from order history by merchantRef
	 * - Per-attempt idempotency via _vinti4_attempt_{attempt_id}_processed meta
	 * - Amount validated against attempt context, not order-level meta
	 * - Partial payment tracking via paid/outstanding totals
	 *
	 * Legacy fallback (pre-1.1 orders):
	 * - Falls back to order-level validation when no attempt history exists
	 * - Uses order-level idempotency and stored _vinti4_* meta
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Gateway_Vinti4 $gateway The gateway instance (provides pos_auth_code).
	 * @return void
	 */
	public static function handle( WC_Gateway_Vinti4 $gateway ): void {

		// Step 1 — Extract callback data (prefer POST, fallback to query args).
		// phpcs:ignore WordPress.Security.NonceVerification -- SISP is an external server, no nonce.
		$request = array_merge( $_GET, $_POST );

		Vinti4_Logger::log( 'Callback received from SISP.' );

		$message_type              = isset( $request['messageType'] ) ? sanitize_text_field( wp_unslash( $request['messageType'] ) ) : '';
		$result_fingerprint        = isset( $request['resultFingerPrint'] ) ? sanitize_text_field( wp_unslash( $request['resultFingerPrint'] ) ) : '';
		$merchant_ref              = isset( $request['merchantRespMerchantRef'] ) ? sanitize_text_field( wp_unslash( $request['merchantRespMerchantRef'] ) ) : '';
		$merchant_session          = isset( $request['merchantRespMerchantSession'] ) ? sanitize_text_field( wp_unslash( $request['merchantRespMerchantSession'] ) ) : '';
		$purchase_amount           = isset( $request['merchantRespPurchaseAmount'] ) ? sanitize_text_field( wp_unslash( $request['merchantRespPurchaseAmount'] ) ) : '';
		$clearing_period           = isset( $request['merchantRespCP'] ) ? sanitize_text_field( wp_unslash( $request['merchantRespCP'] ) ) : '';
		$transaction_id            = isset( $request['merchantRespTid'] ) ? sanitize_text_field( wp_unslash( $request['merchantRespTid'] ) ) : '';
		$message_id                = isset( $request['merchantRespMessageID'] ) ? sanitize_text_field( wp_unslash( $request['merchantRespMessageID'] ) ) : '';
		$pan                       = isset( $request['merchantRespPan'] ) ? sanitize_text_field( wp_unslash( $request['merchantRespPan'] ) ) : '';
		$merchant_response         = isset( $request['merchantResp'] ) ? sanitize_text_field( wp_unslash( $request['merchantResp'] ) ) : '';
		$timestamp                 = isset( $request['merchantRespTimeStamp'] ) ? sanitize_text_field( wp_unslash( $request['merchantRespTimeStamp'] ) ) : '';
		$reference_number          = isset( $request['merchantRespReferenceNumber'] ) ? sanitize_text_field( wp_unslash( $request['merchantRespReferenceNumber'] ) ) : '';
		$entity_code               = isset( $request['merchantRespEntityCode'] ) ? sanitize_text_field( wp_unslash( $request['merchantRespEntityCode'] ) ) : '';
		$client_receipt            = isset( $request['merchantRespClientReceipt'] ) ? sanitize_text_field( wp_unslash( $request['merchantRespClientReceipt'] ) ) : '';
		$additional_error_message  = isset( $request['merchantRespAdditionalErrorMessage'] ) ? sanitize_text_field( wp_unslash( $request['merchantRespAdditionalErrorMessage'] ) ) : '';
		$reload_code               = isset( $request['merchantRespReloadCode'] ) ? sanitize_text_field( wp_unslash( $request['merchantRespReloadCode'] ) ) : '';
		$error_detail              = isset( $request['merchantRespErrorDetail'] ) ? sanitize_text_field( wp_unslash( $request['merchantRespErrorDetail'] ) ) : '';
		$error_description         = isset( $request['merchantRespErrorDescription'] ) ? sanitize_text_field( wp_unslash( $request['merchantRespErrorDescription'] ) ) : '';

		// Require merchantRef for all callbacks.
		if ( empty( $merchant_ref ) ) {
			Vinti4_Logger::log( 'Callback rejected: missing merchantRef.', 'warning' );
			wp_die( esc_html__( 'Invalid callback data.', 'vinti4' ) );
		}

		// Step 2 — Parse order ID and load order.
		$order_id = vinti4_parse_order_id_from_ref( $merchant_ref );

		if ( 0 === $order_id ) {
			$order_id = vinti4_find_order_id_by_merchant_ref( $merchant_ref );
		}

		if ( 0 === $order_id ) {
			Vinti4_Logger::log(
				sprintf( 'Callback rejected: could not parse order ID from merchantRef "%s".', $merchant_ref ),
				'warning'
			);
			wp_die( esc_html__( 'Invalid merchant reference.', 'vinti4' ), '', array( 'response' => 400 ) );
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			Vinti4_Logger::log(
				sprintf( 'Callback rejected: order %d not found.', $order_id ),
				'error'
			);
			wp_die( esc_html__( 'Order not found.', 'vinti4' ), '', array( 'response' => 404 ) );
		}

		// Step 3 — Resolve attempt from history (v1.1+) or use legacy fallback.
		$attempt = Vinti4_Attempt_Store::find_attempt_by_merchant_ref( $order, $merchant_ref );

		if ( null !== $attempt ) {
			// Attempt-level path (v1.1+).
			self::handle_attempt_callback(
				$gateway,
				$order,
				$attempt,
				$message_type,
				$result_fingerprint,
				$merchant_session,
				$purchase_amount,
				$clearing_period,
				$transaction_id,
				$message_id,
				$pan,
				$merchant_response,
				$timestamp,
				$reference_number,
				$entity_code,
				$client_receipt,
				$additional_error_message,
				$reload_code,
				$error_detail,
				$error_description
			);
		}

		// Legacy fallback — no attempt history found.
		$attempt_history = Vinti4_Attempt_Store::get_attempts( $order );
		if ( empty( $attempt_history ) ) {
			Vinti4_Logger::log(
				sprintf(
					'Legacy callback path: using order-level validation for order %d, merchantRef %s.',
					$order_id,
					$merchant_ref
				),
				'notice'
			);
			self::handle_legacy_callback(
				$gateway,
				$order,
				$message_type,
				$result_fingerprint,
				$merchant_ref,
				$merchant_session,
				$purchase_amount,
				$clearing_period,
				$transaction_id,
				$message_id,
				$pan,
				$merchant_response,
				$timestamp,
				$reference_number,
				$entity_code,
				$client_receipt,
				$additional_error_message,
				$reload_code,
				$error_detail,
				$error_description
			);
		}

		// Attempt history exists but merchantRef not found — possible spoofed callback.
		Vinti4_Logger::log(
			sprintf(
				'Callback failed: merchantRef %s not found in attempt history for order %d — possible spoofed callback.',
				$merchant_ref,
				$order_id
			),
			'error'
		);
		wp_safe_redirect( wc_get_checkout_url() );
		exit;
	}

	/**
	 * Handle callback with attempt-level resolution (v1.1+).
	 *
	 * Validates per-attempt idempotency, merchantSession, fingerprint, and amount.
	 * On success, marks the attempt completed and handles partial/full payment.
	 *
	 * @since 1.1.0
	 *
	 * @param WC_Gateway_Vinti4 $gateway     Gateway instance.
	 * @param WC_Order          $order       WooCommerce order.
	 * @param array             $attempt     Resolved attempt from history.
	 * @param string            ...$fields   Callback POST fields.
	 * @return void
	 */
	private static function handle_attempt_callback(
		WC_Gateway_Vinti4 $gateway,
		WC_Order $order,
		array $attempt,
		string $message_type,
		string $result_fingerprint,
		string $merchant_session,
		string $purchase_amount,
		string $clearing_period,
		string $transaction_id,
		string $message_id,
		string $pan,
		string $merchant_response,
		string $timestamp,
		string $reference_number,
		string $entity_code,
		string $client_receipt,
		string $additional_error_message,
		string $reload_code,
		string $error_detail,
		string $error_description
	): void {
		$attempt_id  = $attempt['attempt_id'] ?? 'unknown';
		$merchant_ref = $attempt['merchant_ref'] ?? '';

		// Per-attempt idempotency check.
		$processed_meta_key = "_vinti4_attempt_{$attempt_id}_processed";
		$already_processed  = $order->get_meta( $processed_meta_key );

		if ( $already_processed ) {
			Vinti4_Logger::log(
				sprintf(
					'Callback duplicate: attempt %s already processed for order %d.',
					$attempt_id,
					$order->get_id()
				),
				'notice'
			);
			if ( in_array( $order->get_status(), array( 'processing', 'completed' ), true ) ) {
				wp_safe_redirect( $order->get_checkout_order_received_url() );
				exit;
			}
			wp_safe_redirect( wc_get_checkout_url() );
			exit;
		}

		// Validate merchantSession matches attempt.
		$stored_session = $attempt['merchant_session'] ?? '';
		if ( $stored_session !== $merchant_session ) {
			Vinti4_Logger::log(
				sprintf(
					'Callback validation failed for attempt %s: merchantSession mismatch. Expected: %s, Got: %s.',
					$attempt_id,
					$stored_session,
					$merchant_session
				),
				'error'
			);
			Vinti4_Attempt_Store::mark_attempt_failed( $order, $attempt_id, 'merchantSession mismatch' );
			$order->update_status( 'failed', __( 'Vinti4 callback session validation failed.', 'vinti4' ) );
			self::mark_attempt_processed_and_redirect( $order, $attempt_id, wc_get_checkout_url() );
		}

		// Determine success vs failure.
		$is_success = vinti4_is_success_message_type( $message_type );

		if ( $is_success ) {
			// Validate response fingerprint.
			$expected_fingerprint = Vinti4_Fingerprint::build_response_fingerprint(
				$gateway->pos_auth_code,
				$message_type,
				$clearing_period,
				$transaction_id,
				$merchant_ref,
				$merchant_session,
				$purchase_amount,
				$message_id,
				$pan,
				$merchant_response,
				$timestamp,
				$reference_number,
				$entity_code,
				$client_receipt,
				$additional_error_message,
				$reload_code
			);

			if ( $expected_fingerprint !== $result_fingerprint ) {
				Vinti4_Logger::log(
					sprintf(
						'Callback validation failed for attempt %s: fingerprint mismatch. merchantRef: %s, messageType: %s.',
						$attempt_id,
						$merchant_ref,
						$message_type
					),
					'error'
				);
				Vinti4_Attempt_Store::mark_attempt_failed( $order, $attempt_id, 'fingerprint mismatch' );
				$order->update_status( 'failed', __( 'Vinti4 fingerprint validation failed.', 'vinti4' ) );
				self::mark_attempt_processed_and_redirect( $order, $attempt_id, wc_get_checkout_url() );
			}

			// Validate amount against attempt context.
			$stored_amount   = isset( $attempt['amount'] ) ? (int) $attempt['amount'] : 0;
			$response_amount = (int) $purchase_amount;

			if ( $stored_amount !== $response_amount ) {
				Vinti4_Logger::log(
					sprintf(
						'Callback validation failed for attempt %s: amount mismatch. Attempt amount: %d, Response amount: %d.',
						$attempt_id,
						$stored_amount,
						$response_amount
					),
					'error'
				);
				Vinti4_Attempt_Store::mark_attempt_failed( $order, $attempt_id, 'amount mismatch' );
				$order->update_status( 'failed', __( 'Vinti4 amount mismatch.', 'vinti4' ) );
				self::mark_attempt_processed_and_redirect( $order, $attempt_id, wc_get_checkout_url() );
			}

			// Mark attempt completed and compute totals.
			Vinti4_Attempt_Store::mark_attempt_completed( $order, $attempt_id, $transaction_id );
			$paid_total       = Vinti4_Attempt_Store::get_paid_total( $order );
			$outstanding_total = Vinti4_Attempt_Store::get_outstanding_total( $order );

			Vinti4_Logger::log(
				sprintf(
					'Callback success: attempt %s completed for order %d. Paid: %.2f, Outstanding: %.2f.',
					$attempt_id,
					$order->get_id(),
					$paid_total,
					$outstanding_total
				)
			);

			// Handle order completion based on outstanding balance.
			if ( $outstanding_total <= 0.01 ) {
				// Fully paid — complete the order.
				$order->payment_complete( $transaction_id );
				$order->add_order_note(
					sprintf(
						/* translators: %s: SISP transaction ID */
						__( 'Vinti4 payment authorized. TID: %s', 'vinti4' ),
						$transaction_id
					)
				);
				Vinti4_Logger::log(
					sprintf(
						'Order fully paid: order %d completed. TID: %s.',
						$order->get_id(),
						$transaction_id
					)
				);
				self::mark_attempt_processed_and_redirect( $order, $attempt_id, $order->get_checkout_order_received_url() );
			} else {
				// Partial payment — keep order in processing state.
				$attempt_amount = isset( $attempt['amount'] ) ? (float) $attempt['amount'] : 0.0;
				$order->update_status( 'processing' );
				$order->add_order_note(
					sprintf(
						/* translators: 1: attempt amount 2: total paid 3: outstanding 4: transaction ID */
						__( 'Vinti4 partial payment received: %1$.2f. Total paid: %2$.2f. Outstanding: %3$.2f. TID: %4$s', 'vinti4' ),
						$attempt_amount,
						$paid_total,
						$outstanding_total,
						$transaction_id
					)
				);
				Vinti4_Logger::log(
					sprintf(
						'Partial payment: order %d now partially paid. Outstanding: %.2f.',
						$order->get_id(),
						$outstanding_total
					)
				);
				self::mark_attempt_processed_and_redirect( $order, $attempt_id, $order->get_checkout_order_received_url() );
			}
		}

		// Failure callback.
		Vinti4_Attempt_Store::mark_attempt_failed(
			$order,
			$attempt_id,
			trim( $error_detail . ' - ' . $error_description )
		);
		Vinti4_Logger::log(
			sprintf(
				'Payment failed for order %d, attempt %s. messageType: %s, errorDetail: %s, errorDescription: %s.',
				$order->get_id(),
				$attempt_id,
				$message_type,
				$error_detail,
				$error_description
			),
			'warning'
		);
		$order->update_status(
			'failed',
			sprintf(
				/* translators: 1: error detail 2: error description */
				__( 'Vinti4 payment failed: %1$s - %2$s', 'vinti4' ),
				$error_detail,
				$error_description
			)
		);
		self::mark_attempt_processed_and_redirect( $order, $attempt_id, wc_get_checkout_url() );
	}

	/**
	 * Handle callback using legacy order-level validation (pre-1.1 orders).
	 *
	 * Preserves the original v1.0 callback logic for orders that were created
	 * before attempt history was introduced. Uses order-level _vinti4_* meta
	 * for validation and order-level idempotency.
	 *
	 * @since 1.1.0
	 *
	 * @param WC_Gateway_Vinti4 $gateway     Gateway instance.
	 * @param WC_Order          $order       WooCommerce order.
	 * @param string            ...$fields   Callback POST fields.
	 * @return void
	 */
	private static function handle_legacy_callback(
		WC_Gateway_Vinti4 $gateway,
		WC_Order $order,
		string $message_type,
		string $result_fingerprint,
		string $merchant_ref,
		string $merchant_session,
		string $purchase_amount,
		string $clearing_period,
		string $transaction_id,
		string $message_id,
		string $pan,
		string $merchant_response,
		string $timestamp,
		string $reference_number,
		string $entity_code,
		string $client_receipt,
		string $additional_error_message,
		string $reload_code,
		string $error_detail,
		string $error_description
	): void {
		// Validate merchantRef matches stored meta (legacy).
		$stored_ref = $order->get_meta( '_vinti4_merchant_ref' );

		if ( $stored_ref !== $merchant_ref ) {
			Vinti4_Logger::log(
				sprintf(
					'Callback rejected: merchantRef mismatch for legacy order %d. Stored: "%s", Received: "%s".',
					$order->get_id(),
					$stored_ref,
					$merchant_ref
				),
				'warning'
			);
			wp_safe_redirect( wc_get_checkout_url() );
			exit;
		}

		// Legacy order-level idempotency.
		$already_processed = $order->get_meta( '_vinti4_callback_processed' );

		if ( $already_processed ) {
			Vinti4_Logger::log(
				sprintf(
					'Duplicate callback detected for legacy order %d (already processed). Redirecting.',
					$order->get_id()
				),
				'notice'
			);
			if ( in_array( $order->get_status(), array( 'processing', 'completed' ), true ) ) {
				wp_safe_redirect( $order->get_checkout_order_received_url() );
				exit;
			}
			wp_safe_redirect( wc_get_checkout_url() );
			exit;
		}

		// Determine success vs failure.
		$is_success = vinti4_is_success_message_type( $message_type );

		if ( $is_success ) {
			// Validate response fingerprint.
			$expected_fingerprint = Vinti4_Fingerprint::build_response_fingerprint(
				$gateway->pos_auth_code,
				$message_type,
				$clearing_period,
				$transaction_id,
				$merchant_ref,
				$merchant_session,
				$purchase_amount,
				$message_id,
				$pan,
				$merchant_response,
				$timestamp,
				$reference_number,
				$entity_code,
				$client_receipt,
				$additional_error_message,
				$reload_code
			);

			if ( $expected_fingerprint !== $result_fingerprint ) {
				Vinti4_Logger::log(
					sprintf(
						'Callback rejected for legacy order %d: fingerprint mismatch. merchantRef: %s, messageType: %s.',
						$order->get_id(),
						$merchant_ref,
						$message_type
					),
					'error'
				);
				$order->update_status( 'failed', __( 'Vinti4 fingerprint validation failed.', 'vinti4' ) );
				self::mark_processed_and_redirect( $order, wc_get_checkout_url() );
			}

			// Validate amount (legacy — uses order-level meta).
			$stored_amount   = (int) $order->get_meta( '_vinti4_amount' );
			$response_amount = (int) $purchase_amount;

			if ( $stored_amount !== $response_amount ) {
				Vinti4_Logger::log(
					sprintf(
						'Callback rejected for legacy order %d: amount mismatch. Stored: %d, Response: %d.',
						$order->get_id(),
						$stored_amount,
						$response_amount
					),
					'error'
				);
				$order->update_status( 'failed', __( 'Vinti4 amount mismatch.', 'vinti4' ) );
				self::mark_processed_and_redirect( $order, wc_get_checkout_url() );
			}

			// Complete the order (legacy path — always full payment for single-attempt orders).
			$order->payment_complete( $transaction_id );
			Vinti4_Logger::log(
				sprintf(
					'Legacy payment completed for order %d. TID: %s, merchantRef: %s, messageType: %s.',
					$order->get_id(),
					$transaction_id,
					$merchant_ref,
					$message_type
				)
			);
			$order->add_order_note(
				sprintf(
					/* translators: %s: SISP transaction ID */
					__( 'Vinti4 payment authorized. TID: %s', 'vinti4' ),
					$transaction_id
				)
			);
			self::mark_processed_and_redirect( $order, $order->get_checkout_order_received_url() );
		}

		// Legacy failure path.
		Vinti4_Logger::log(
			sprintf(
				'Legacy payment failed for order %d. messageType: %s, errorDetail: %s, errorDescription: %s.',
				$order->get_id(),
				$message_type,
				$error_detail,
				$error_description
			),
			'warning'
		);
		$order->update_status(
			'failed',
			sprintf(
				/* translators: 1: error detail 2: error description */
				__( 'Vinti4 payment failed: %1$s - %2$s', 'vinti4' ),
				$error_detail,
				$error_description
			)
		);
		self::mark_processed_and_redirect( $order, wc_get_checkout_url() );
	}

	/**
	 * Mark a specific attempt as processed and redirect.
	 *
	 * Sets the per-attempt `_vinti4_attempt_{attempt_id}_processed` meta
	 * on the order for per-attempt idempotency.
	 *
	 * @since 1.1.0
	 *
	 * @param WC_Order $order        The order to mark.
	 * @param string   $attempt_id   The attempt identifier.
	 * @param string   $redirect_url URL to redirect to.
	 * @return void
	 */
	private static function mark_attempt_processed_and_redirect( WC_Order $order, string $attempt_id, string $redirect_url ): void {
		$meta_key = "_vinti4_attempt_{$attempt_id}_processed";
		$order->update_meta_data( $meta_key, gmdate( 'Y-m-d H:i:s' ) );
		$order->save();

		Vinti4_Logger::log(
			sprintf(
				'Attempt %s marked as processed for order %d.',
				$attempt_id,
				$order->get_id()
			)
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Mark the callback as processed and redirect (legacy path).
	 *
	 * Sets the `_vinti4_callback_processed` meta on the order, persists,
	 * then redirects to the given URL. Used by the legacy callback path
	 * for pre-1.1 orders without attempt history.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $order        The order to mark.
	 * @param string   $redirect_url URL to redirect to.
	 * @return void
	 */
	private static function mark_processed_and_redirect( WC_Order $order, string $redirect_url ): void {
		$order->update_meta_data( '_vinti4_callback_processed', '1' );
		$order->save();
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Mark a specific attempt as processed (public API for external callers).
	 *
	 * Sets per-attempt processed flag in order meta with current timestamp.
	 *
	 * @since 1.1.0
	 *
	 * @param WC_Order $order      The order to mark.
	 * @param string   $attempt_id The attempt identifier.
	 * @return void
	 */
	public static function mark_attempt_processed( WC_Order $order, string $attempt_id ): void {
		$meta_key = "_vinti4_attempt_{$attempt_id}_processed";
		$order->update_meta_data( $meta_key, gmdate( 'Y-m-d H:i:s' ) );
		$order->save();

		Vinti4_Logger::log(
			sprintf(
				'Attempt %s marked as processed for order %d.',
				$attempt_id,
				$order->get_id()
			)
		);
	}
}
