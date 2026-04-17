<?php
/**
 * Vinti4 Callback Handler
 *
 * Handles SISP payment callback (POST from SISP back to WooCommerce).
 * Validates the response fingerprint, checks idempotency, verifies amount,
 * and completes or fails the order accordingly.
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
 * 3. Verify merchantRef matches stored meta (prevents replay across attempts)
 * 4. Idempotency check via _vinti4_callback_processed meta
 * 5. Determine success vs failure from messageType
 * 6. On success: validate response fingerprint
 * 7. On success: validate amount matches stored value
 * 8. On success: call payment_complete()
 * 9. On failure: mark order as failed
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
	 * Idempotency is guaranteed via the `_vinti4_callback_processed` order meta:
	 * if a second callback arrives for the same order it is safely rejected
	 * without mutating the order.
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
			Vinti4_Logger::log( sprintf( 'Callback rejected: could not parse order ID from merchantRef "%s".', $merchant_ref ), 'warning' );
			wp_die( esc_html__( 'Invalid merchant reference.', 'vinti4' ), '', array( 'response' => 400 ) );
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			Vinti4_Logger::log( sprintf( 'Callback rejected: order %d not found.', $order_id ), 'error' );
			wp_die( esc_html__( 'Order not found.', 'vinti4' ), '', array( 'response' => 404 ) );
		}

		// Step 3 — Validate merchantRef matches stored meta.
		$stored_ref = $order->get_meta( '_vinti4_merchant_ref' );

		if ( $stored_ref !== $merchant_ref ) {
			// Callback is for a different payment attempt.
			Vinti4_Logger::log( sprintf( 'Callback rejected: merchantRef mismatch. Stored: "%s", Received: "%s".', $stored_ref, $merchant_ref ), 'warning' );
			wp_safe_redirect( wc_get_checkout_url() );
			exit;
		}

		// Step 4 — Idempotency check.
		$already_processed = $order->get_meta( '_vinti4_callback_processed' );

		if ( $already_processed ) {
			// Already handled — redirect to the appropriate page without mutating the order.
			Vinti4_Logger::log( sprintf( 'Duplicate callback detected for order %d (already processed). Redirecting.', $order_id ) );
			if ( in_array( $order->get_status(), array( 'processing', 'completed' ), true ) ) {
				wp_safe_redirect( $order->get_checkout_order_received_url() );
				exit;
			}
			wp_safe_redirect( wc_get_checkout_url() );
			exit;
		}

		// Step 5 — Determine success vs failure.
		$is_success = vinti4_is_success_message_type( $message_type );

		if ( $is_success ) {
			if ( empty( $result_fingerprint ) ) {
				Vinti4_Logger::log( sprintf( 'Callback rejected for order %d: missing resultFingerprint on success messageType %s.', $order_id, $message_type ), 'warning' );
				$order->update_status( 'failed', __( 'Vinti4 callback missing result fingerprint.', 'vinti4' ) );
				self::mark_processed_and_redirect( $order, wc_get_checkout_url() );
			}

			// Step 6 — Validate response fingerprint.
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
				Vinti4_Logger::log( sprintf( 'Callback rejected for order %d: fingerprint mismatch. merchantRef: %s, messageType: %s.', $order_id, $merchant_ref, $message_type ), 'error' );
				$order->update_status( 'failed', __( 'Vinti4 fingerprint validation failed.', 'vinti4' ) );
				self::mark_processed_and_redirect( $order, wc_get_checkout_url() );
			}

			// Step 7 — Validate amount.
			$stored_amount  = (int) $order->get_meta( '_vinti4_amount' );
			$response_amount = (int) $purchase_amount;

			if ( $stored_amount !== $response_amount ) {
				Vinti4_Logger::log( sprintf( 'Callback rejected for order %d: amount mismatch. Stored: %d, Response: %d.', $order_id, $stored_amount, $response_amount ), 'error' );
				$order->update_status( 'failed', __( 'Vinti4 amount mismatch.', 'vinti4' ) );
				self::mark_processed_and_redirect( $order, wc_get_checkout_url() );
			}

			// Step 8 — Complete the order.
			// payment_complete() handles stock reduction, cart emptying, and status transition.
			// Do NOT call reduce_order_stock(), empty_cart(), or update_status('completed').
			$order->payment_complete( $transaction_id );
			Vinti4_Logger::log( sprintf( 'Payment completed for order %d. TID: %s, merchantRef: %s, messageType: %s.', $order_id, $transaction_id, $merchant_ref, $message_type ) );
			$order->add_order_note(
				sprintf(
					/* translators: %s: SISP transaction ID */
					__( 'Vinti4 payment authorized. TID: %s', 'vinti4' ),
					$transaction_id
				)
			);
			self::mark_processed_and_redirect( $order, $order->get_checkout_order_received_url() );
		}

		// Step 9 — Mark order failed.
		Vinti4_Logger::log( sprintf( 'Payment failed for order %d. messageType: %s, errorDetail: %s, errorDescription: %s.', $order_id, $message_type, $error_detail, $error_description ), 'warning' );
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
	 * Mark the callback as processed and redirect.
	 *
	 * Sets the `_vinti4_callback_processed` meta on the order, persists,
	 * then redirects to the given URL. Used by all terminal paths in handle()
	 * to ensure idempotency on repeated callbacks.
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
}
