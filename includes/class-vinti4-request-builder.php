<?php
/**
 * Vinti4 Request Builder
 *
 * Assembles complete payment attempts for the SISP hosted payment page.
 * This is the single canonical code path (PAY-02) that generates every field
 * needed for a SISP payment redirect — merchantRef, merchantSession, fingerprint,
 * and the Base64-encoded purchaseRequest JSON.
 *
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'Vinti4_Request_Builder' ) ) {
	return;
}

/**
 * Class Vinti4_Request_Builder
 *
 * Orchestrates the fingerprint class, formatting helpers, and gateway settings
 * into one consistent payment attempt output. Every SISP redirect originates here.
 *
 * @since 1.0.0
 */
class Vinti4_Request_Builder {

	/**
	 * Build a complete payment attempt for a SISP redirect.
	 *
	 * This is the canonical entry point that produces all data needed for a
	 * SISP payment redirect in a single call. Each invocation generates unique
	 * merchantRef and merchantSession values.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order          $order   The WooCommerce order.
	 * @param WC_Gateway_Vinti4 $gateway The gateway instance with settings.
	 * @return array {
	 *     Payment attempt data.
	 *
	 *     @type string $attempt_id           Unique UUID for this attempt.
	 *     @type string $timestamp            Formatted UTC timestamp.
	 *     @type string $merchant_ref         Unique reference (WC{id}-YYYYMMDDHHmmss).
	 *     @type string $merchant_session     Session identifier (S + 12 random chars).
	 *     @type string $transaction_code     Transaction code ('1' = Authorization).
	 *     @type string $amount               Normalized integer amount.
	 *     @type string $currency             ISO 4217 numeric currency code.
	 *     @type string $languageMessages     Middleware language field ('pt' or 'en').
	 *     @type string $urlMerchantResponse  Callback URL for the SISP response.
	 *     @type string $is3DSec              Hosted 3DS flag expected by SISP.
	 *     @type string $timeStamp            Transport timestamp for outbound middleware fields.
	 *     @type string $FingerPrint          Transport fingerprint value for redirect handoff.
	 *     @type string $FingerPrintVersion   Transport fingerprint version for redirect handoff.
	 *     @type string $purchase_request_b64 Base64-encoded purchaseRequest JSON.
	 *     @type string $fingerprint          SHA-512 + Base64 SISP fingerprint.
	 * }
	 */
	public static function build_payment_attempt( WC_Order $order, WC_Gateway_Vinti4 $gateway ): array {
		$timestamp             = vinti4_format_timestamp();
		$attempt_id            = wp_generate_uuid4();
		$merchant_ref          = vinti4_build_merchant_ref( $order->get_id() );
		$merchant_session      = vinti4_build_merchant_session();
		$transaction_code      = '1'; // Authorization.
		$amount                = (string) vinti4_normalize_amount( (float) $order->get_total() );
		$currency              = $gateway->get_currency_code( $order );
		$language_messages     = $gateway->resolve_language_messages();
		$url_merchant_response = $gateway->get_url_merchant_response();
		$is_3dsec              = $gateway->get_is_3dsec_flag();
		$fingerprint_version   = $gateway->get_fingerprint_version();
		$fingerprint_scale     = $gateway->get_fingerprint_amount_scale();

		$purchase_request_json = self::build_purchase_request_json( $order );
		$purchase_request_b64  = base64_encode(
			wp_json_encode( $purchase_request_json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
		);

		$fingerprint = Vinti4_Fingerprint::build_request_fingerprint(
			$gateway->pos_auth_code,
			$timestamp,
			$amount,
			$merchant_ref,
			$merchant_session,
			$gateway->pos_id,
			$currency,
			$transaction_code,
			$fingerprint_scale,
			'', '', ''
		);

		$fingerprint_debug_snapshot = Vinti4_Fingerprint::build_request_fingerprint_debug_snapshot(
			$gateway->pos_auth_code,
			$timestamp,
			$amount,
			$merchant_ref,
			$merchant_session,
			$gateway->pos_id,
			$currency,
			$transaction_code,
			$fingerprint_scale,
			'', '', ''
		);

		$result = array(
			'attempt_id'           => $attempt_id,
			'timestamp'            => $timestamp,
			'timeStamp'            => $timestamp,
			'merchant_ref'         => $merchant_ref,
			'merchant_session'     => $merchant_session,
			'transaction_code'     => $transaction_code,
			'amount'               => $amount,
			'currency'             => $currency,
			'languageMessages'     => $language_messages,
			'urlMerchantResponse'  => $url_merchant_response,
			'is3DSec'              => $is_3dsec,
			'FingerPrint'          => $fingerprint,
			'FingerPrintVersion'   => $fingerprint_version,
			'purchase_request_b64' => $purchase_request_b64,
			'fingerprint'          => $fingerprint,
		);

		Vinti4_Logger::log( sprintf(
			"Payment attempt built:\n  attempt_id: %s\n  order_id: %d\n  merchantRef: %s\n  merchantSession: %s\n  timeStamp: %s\n  amount: %s\n  currency: %s\n  transaction_code: %s\n  languageMessages: %s\n  urlMerchantResponse: %s\n  is3DSec: %s\n  posAuthCode (masked): %s\n  FingerPrintVersion: %s\n  FingerPrintAmountScale: %d\n  FingerPrint: %s",
			$attempt_id,
			$order->get_id(),
			$merchant_ref,
			$merchant_session,
			$timestamp,
			$amount,
			$currency,
			$transaction_code,
			$language_messages,
			$url_merchant_response,
			$is_3dsec,
			Vinti4_Logger::mask_auth_code( $gateway->pos_auth_code ),
			$fingerprint_version,
			$fingerprint_scale,
			$fingerprint
		) );

		Vinti4_Logger::log(
			'Fingerprint request canonical snapshot: ' . wp_json_encode(
				$fingerprint_debug_snapshot,
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			)
		);

		return $result;
	}

	/**
	 * Build the 3DS purchaseRequest JSON from order and customer data.
	 *
	 * Produces a plain PHP array that the caller Base64-encodes and sends to
	 * SISP as part of the redirect POST. This data supports the 3DS frictionless
	 * flow and is NOT part of the fingerprint computation.
	 *
	 * Note: The deprecated `purchaseDate` field is intentionally excluded per
	 * SISP's current specification.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $order The WooCommerce order.
	 * @return array Associative array ready for JSON encoding.
	 */
	private static function build_purchase_request_json( WC_Order $order ): array {
		$billing_phone  = vinti4_shape_phone( $order->get_billing_phone() );

		// Determine address match: billing vs shipping.
		$addr_match = (
			trim( $order->get_billing_address_1() ) === trim( $order->get_shipping_address_1() )
			&& trim( $order->get_billing_city() ) === trim( $order->get_shipping_city() )
			&& trim( $order->get_billing_postcode() ) === trim( $order->get_shipping_postcode() )
			&& trim( $order->get_billing_country() ) === trim( $order->get_shipping_country() )
		) ? 'Y' : 'N';

		// Account info — best-effort from WC data.
		$customer_id      = $order->get_customer_id();
		$ch_acc_age_ind   = ( $customer_id > 0 ) ? '05' : '01';

		return array(
			'acctID'          => (string) $customer_id,
			'email'           => trim( $order->get_billing_email() ),
			'addrMatch'       => $addr_match,
			// Billing address block.
			'billAddrCity'    => trim( $order->get_billing_city() ),
			'billAddrCountry' => trim( $order->get_billing_country() ),
			'billAddrLine1'   => trim( $order->get_billing_address_1() ),
			'billAddrLine2'   => trim( $order->get_billing_address_2() ),
			'billAddrLine3'   => '',
			'billAddrPostCode'=> trim( $order->get_billing_postcode() ),
			'billAddrState'   => trim( $order->get_billing_state() ),
			// Shipping address block.
			'shipAddrCity'    => trim( $order->get_shipping_city() ),
			'shipAddrCountry' => trim( $order->get_shipping_country() ),
			'shipAddrLine1'   => trim( $order->get_shipping_address_1() ),
			'shipAddrPostCode'=> trim( $order->get_shipping_postcode() ),
			'shipAddrState'   => trim( $order->get_shipping_state() ),
			// Phone block.
			'workPhone'       => array(
				'cc'         => $billing_phone['cc'],
				'subscriber' => $billing_phone['subscriber'],
			),
			'mobilePhone'     => array(
				'cc'         => $billing_phone['cc'],
				'subscriber' => $billing_phone['subscriber'],
			),
			// Account info block.
			'acctInfo'        => array(
				'chAccAgeInd' => $ch_acc_age_ind,
				'chAccDate'   => '',
			),
		);
	}
}
