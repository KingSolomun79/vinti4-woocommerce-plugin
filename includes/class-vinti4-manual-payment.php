<?php
/**
 * Manual (offline) payment recording.
 *
 * Lets staff log a payment that was collected outside the Vinti4 gateway —
 * cash, bank transfer, card taken in person, etc. — so it lands in the same
 * append-only attempt history as online gateway payments and counts toward
 * the same outstanding-balance calculation. An order can accumulate any mix
 * of online and manual attempts; the running total is always the sum of
 * every completed/callback_received attempt in history.
 *
 * @since 1.2.0
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'Vinti4_Manual_Payment' ) ) {
	return;
}

class Vinti4_Manual_Payment {

	/**
	 * Payment methods staff can record, and their display labels.
	 */
	public const METHODS = array(
		'cash'           => 'Cash',
		'wire'           => 'Bank Transfer',
		'card_in_person' => 'Card (in person)',
		'other'          => 'Other',
	);

	public static function register(): void {
		add_action( 'wp_ajax_vinti4_record_manual_payment', array( __CLASS__, 'handle_record_manual_payment' ) );
	}

	public static function handle_record_manual_payment(): void {
		check_ajax_referer( 'vinti4_manual_payment', 'nonce' );

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'vinti4' ) ) );
		}

		$order_id  = absint( $_POST['order_id'] ?? 0 );
		$method    = sanitize_key( wp_unslash( $_POST['method'] ?? '' ) );
		$raw_amount = sanitize_text_field( wp_unslash( $_POST['amount'] ?? '' ) );
		$reference  = sanitize_text_field( wp_unslash( $_POST['reference'] ?? '' ) );

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'Order not found.', 'vinti4' ) ) );
		}

		if ( ! isset( self::METHODS[ $method ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid payment method.', 'vinti4' ) ) );
		}

		if ( ! is_numeric( $raw_amount ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid amount.', 'vinti4' ) ) );
		}

		$amount = round( (float) $raw_amount, 2 );

		if ( $amount <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Amount must be greater than zero.', 'vinti4' ) ) );
		}

		$outstanding = Vinti4_Attempt_Store::get_outstanding_total( $order );

		if ( $outstanding <= 0.01 ) {
			wp_send_json_error( array( 'message' => __( 'This order is already fully paid.', 'vinti4' ) ) );
		}

		if ( $amount > $outstanding + 0.01 ) {
			wp_send_json_error( array(
				'message' => sprintf(
					/* translators: %s: outstanding balance amount */
					__( 'Amount cannot exceed the outstanding balance of %s.', 'vinti4' ),
					wp_strip_all_tags( wc_price( $outstanding ) )
				),
			) );
		}

		$attempt = array(
			'attempt_id'        => 'manual_' . wp_generate_uuid4(),
			'amount'            => $amount,
			'currency'          => $order->get_currency(),
			'status'            => 'completed',
			'callback_received' => true,
			'transaction_id'    => '' !== $reference ? $reference : null,
			'metadata'          => array(
				'source'      => 'manual',
				'method'      => $method,
				'reference'   => $reference,
				'recorded_by' => get_current_user_id(),
			),
		);

		// project_latest = false: legacy _vinti4_* keys mirror SISP gateway
		// fields (fingerprint, transaction_code, ...) that don't apply here.
		Vinti4_Attempt_Store::append_attempt( $order, $attempt, false );

		$paid_total        = Vinti4_Attempt_Store::get_paid_total( $order );
		$outstanding_total = Vinti4_Attempt_Store::get_outstanding_total( $order );
		$method_label       = self::METHODS[ $method ];
		$recorder            = wp_get_current_user();

		if ( $outstanding_total <= 0.01 ) {
			$order->payment_complete( $reference ?: ( 'manual-' . gmdate( 'YmdHis' ) ) );

			$gateways = WC()->payment_gateways()->get_available_payment_gateways();
			$gateway  = $gateways['vinti4'] ?? null;
			if ( $gateway && 'completed' === $gateway->get_option( 'order_status_after_payment', 'processing' ) ) {
				$order->update_status( 'completed' );
			}
		} else {
			$order->update_status( 'processing' );
		}

		$order->add_order_note(
			sprintf(
				/* translators: 1: amount, 2: method, 3: reference, 4: total paid, 5: outstanding, 6: staff display name */
				__( 'Manual payment recorded: %1$s via %2$s%3$s. Total paid: %4$s. Outstanding: %5$s. Recorded by %6$s.', 'vinti4' ),
				wc_price( $amount ),
				$method_label,
				$reference ? ', ref ' . $reference : '',
				wc_price( $paid_total ),
				wc_price( $outstanding_total ),
				$recorder && $recorder->exists() ? $recorder->display_name : __( 'unknown user', 'vinti4' )
			)
		);

		Vinti4_Logger::log(
			sprintf(
				'Manual payment: order %d, amount %.2f, method %s, recorded_by %d',
				$order->get_id(),
				$amount,
				$method,
				get_current_user_id()
			),
			'info'
		);

		wp_send_json_success( array(
			'paid_total'   => $paid_total,
			'outstanding'  => $outstanding_total,
			'status'       => $order->get_status(),
		) );
	}
}
