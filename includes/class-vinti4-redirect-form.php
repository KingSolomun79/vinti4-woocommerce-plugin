<?php
/**
 * Redirect form renderer for Vinti4 SISP payment page.
 *
 * Reads stored order meta from a previous process_payment() call and
 * renders a self-submitting HTML form that POSTs all required fields
 * to the SISP 3DS payment page.
 *
 * @package Vinti4
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'Vinti4_Redirect_Form' ) ) {
	return;
}

/**
 * Vinti4_Redirect_Form — renders the auto-submit payment redirect page.
 */
class Vinti4_Redirect_Form {

	/**
	 * Render the payment redirect page.
	 *
	 * Validates the order/key query parameters, loads attempt meta stored
	 * by process_payment(), and outputs a complete HTML page containing a
	 * hidden form that auto-submits to the SISP vbv2_url.
	 *
	 * @return void
	 */
	public static function render() {
		// 1. Read and validate query parameters.
		if ( empty( $_GET['order'] ) || empty( $_GET['key'] ) ) {
			wp_die(
				esc_html__( 'Invalid payment request.', 'vinti4' ),
				esc_html__( 'Payment Error', 'vinti4' ),
				array( 'response' => 400 )
			);
		}

		// 2. Load the order.
		$order = wc_get_order( absint( $_GET['order'] ) );
		if ( false === $order ) {
			wp_die(
				esc_html__( 'Order not found.', 'vinti4' ),
				esc_html__( 'Payment Error', 'vinti4' ),
				array( 'response' => 404 )
			);
		}

		// 3. Validate the order key.
		if ( $order->get_order_key() !== sanitize_text_field( wp_unslash( $_GET['key'] ) ) ) {
			wp_die(
				esc_html__( 'Invalid order key.', 'vinti4' ),
				esc_html__( 'Payment Error', 'vinti4' ),
				array( 'response' => 403 )
			);
		}

		// 4. Read attempt meta from the order.
		$merchant_ref        = $order->get_meta( '_vinti4_merchant_ref' );
		$merchant_session    = $order->get_meta( '_vinti4_merchant_session' );
		$transaction_code    = $order->get_meta( '_vinti4_transaction_code' );
		$amount              = $order->get_meta( '_vinti4_amount' );
		$currency            = $order->get_meta( '_vinti4_currency' );
		$timestamp           = $order->get_meta( '_vinti4_timestamp' );
		$fingerprint         = $order->get_meta( '_vinti4_fingerprint' );
		$purchase_request_b64 = $order->get_meta( '_vinti4_purchase_request_b64' );

		if ( empty( $merchant_ref ) ) {
			wp_die(
				esc_html__( 'Payment data not found. Please try again.', 'vinti4' ),
				esc_html__( 'Payment Error', 'vinti4' ),
				array( 'response' => 400 )
			);
		}

		// 5. Get the gateway instance for settings.
		$gateways = WC()->payment_gateways()->get_available_payment_gateways();
		$gateway  = $gateways['vinti4'] ?? null;

		if ( null === $gateway ) {
			wp_die(
				esc_html__( 'Payment gateway not available.', 'vinti4' ),
				esc_html__( 'Payment Error', 'vinti4' ),
				array( 'response' => 500 )
			);
		}

		// 6. Build the SISP URL.
		$sisp_url = esc_url( $gateway->vbv2_url );

		// Send appropriate headers.
		status_header( 200 );
		nocache_headers();

		// Output complete HTML document.
		echo '<!DOCTYPE html>' . "\n";
		echo '<html><head><meta charset="utf-8">';
		echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
		echo '<title>' . esc_html__( 'Redirecting to payment...', 'vinti4' ) . '</title>';
		echo '<style>';
		echo 'body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;background:#f6f7f7;color:#2c3338}';
		echo '.wrap{text-align:center;padding:2rem;background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.1);max-width:400px}';
		echo '.spinner{border:3px solid #e0e0e0;border-top:3px solid #2271b1;border-radius:50%;width:30px;height:30px;animation:spin .8s linear infinite;margin:0 auto 1rem}';
		echo '@keyframes spin{to{transform:rotate(360deg)}}';
		echo '</style>';
		echo '</head><body>';
		echo '<div class="wrap">';
		echo '<div class="spinner"></div>';
		echo '<p>' . esc_html__( 'Redirecting to the payment page...', 'vinti4' ) . '</p>';
		echo '<noscript><p>' . esc_html__( 'JavaScript is disabled. Please click the button below.', 'vinti4' ) . '</p></noscript>';
		echo '</div>';

		// Hidden form with ALL SISP fields.
		echo '<form id="vinti4-payment-form" method="post" action="' . $sisp_url . '">';

		// Required fields from gateway settings.
		echo '<input type="hidden" name="posID" value="' . esc_attr( $gateway->pos_id ) . '">';
		echo '<input type="hidden" name="posAuthCode" value="' . esc_attr( $gateway->pos_auth_code ) . '">';

		// Required fields from order meta.
		echo '<input type="hidden" name="merchantRef" value="' . esc_attr( $merchant_ref ) . '">';
		echo '<input type="hidden" name="merchantSession" value="' . esc_attr( $merchant_session ) . '">';
		echo '<input type="hidden" name="amount" value="' . esc_attr( $amount ) . '">';
		echo '<input type="hidden" name="currency" value="' . esc_attr( $currency ) . '">';
		echo '<input type="hidden" name="transactionCode" value="' . esc_attr( $transaction_code ) . '">';
		echo '<input type="hidden" name="fingerprint" value="' . esc_attr( $fingerprint ) . '">';
		echo '<input type="hidden" name="timestamp" value="' . esc_attr( $timestamp ) . '">';
		echo '<input type="hidden" name="purchaseRequest" value="' . esc_attr( $purchase_request_b64 ) . '">';

		// Language.
		echo '<input type="hidden" name="lang" value="' . esc_attr( $gateway->language ) . '">';

		// Application identifiers (from SISP spec).
		echo '<input type="hidden" name="appCode" value="VINTI4WOO">';
		echo '<input type="hidden" name="appName" value="Vinti4 WooCommerce">';

		// Noscript fallback submit button.
		echo '<noscript><button type="submit" style="margin-top:1rem;padding:.5rem 1.5rem">' . esc_html__( 'Pay Now', 'vinti4' ) . '</button></noscript>';
		echo '</form>';

		// Auto-submit JavaScript.
		echo '<script>document.getElementById("vinti4-payment-form").submit();</script>';
		echo '</body></html>';
		exit;
	}
}
