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
	 * @param WC_Order          $order           The WooCommerce order.
	 * @param WC_Gateway_Vinti4 $gateway         The gateway instance with settings.
	 * @param array             $attempt_context Optional explicit attempt context.
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
	 *     @type string $timeStamp            Transport timestamp alias.
	 *     @type string $FingerPrint          Transport fingerprint alias.
	 *     @type string $FingerPrintVersion   Fingerprint protocol version.
	 *     @type string $purchase_request_b64 Base64-encoded purchaseRequest JSON.
	 *     @type string $fingerprint          SHA-512 + Base64 SISP fingerprint.
	 * }
	 */
	public static function build_payment_attempt( WC_Order $order, WC_Gateway_Vinti4 $gateway, array $attempt_context = array() ): array {
		$timestamp        = self::resolve_context_string( $attempt_context, 'timestamp', vinti4_format_timestamp() );
		$attempt_id       = self::resolve_context_string( $attempt_context, 'attempt_id', wp_generate_uuid4() );
		$merchant_ref     = self::resolve_context_string( $attempt_context, 'merchant_ref', vinti4_build_merchant_ref( $order->get_id() ) );
		$merchant_session = self::resolve_context_string( $attempt_context, 'merchant_session', vinti4_build_merchant_session() );
		$transaction_code = '1'; // Authorization.
		$amount           = (string) vinti4_normalize_amount( self::resolve_attempt_amount( $order, $attempt_context ) );
		$currency         = $gateway->get_currency_code( $order );
		$language_messages = self::resolve_language_messages( (string) $gateway->language );
		$url_merchant_response = self::build_url_merchant_response();
		$is_3dsec = '1';
		$fingerprint_version = '1';

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
			'', '', ''
		);

		return array(
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
	}

	/**
	 * Resolve a scalar context value to a normalized string.
	 *
	 * @param array  $context Attempt context.
	 * @param string $key     Context key.
	 * @param string $default Fallback value.
	 * @return string
	 */
	private static function resolve_context_string( array $context, string $key, string $default ): string {
		if ( ! array_key_exists( $key, $context ) ) {
			return $default;
		}

		$value = $context[ $key ];

		if ( ! is_scalar( $value ) ) {
			return $default;
		}

		$normalized = trim( (string) $value );

		return '' === $normalized ? $default : $normalized;
	}

	/**
	 * Resolve attempt amount from explicit context or order fallback.
	 *
	 * @param WC_Order $order   WooCommerce order.
	 * @param array    $context Attempt context.
	 * @return float
	 */
	private static function resolve_attempt_amount( WC_Order $order, array $context ): float {
		if ( array_key_exists( 'amount', $context ) && is_scalar( $context['amount'] ) ) {
			return (float) $context['amount'];
		}

		return (float) $order->get_total();
	}

	/**
	 * Resolve middleware language value.
	 *
	 * @param string $gateway_language Configured gateway language value.
	 * @return string
	 */
	private static function resolve_language_messages( string $gateway_language ): string {
		$normalized = strtolower( trim( $gateway_language ) );

		if ( 'en' === $normalized ) {
			return 'en';
		}

		return 'pt';
	}

	/**
	 * Build and normalize callback URL sent to middleware.
	 *
	 * @return string
	 */
	private static function build_url_merchant_response(): string {
		$url = '';

		if ( function_exists( 'WC' ) ) {
			$wc = WC();

			if ( null !== $wc && method_exists( $wc, 'api_request_url' ) ) {
				$generated = $wc->api_request_url( 'vinti4' );

				if ( is_string( $generated ) ) {
					$url = $generated;
				}
			}
		}

		if ( '' === $url ) {
			$url = home_url( '/wc-api/vinti4/' );
		}

		return self::normalize_merchant_response_url( $url );
	}

	/**
	 * Normalize callback URL values to absolute URL.
	 *
	 * @param string $url Callback URL candidate.
	 * @return string
	 */
	private static function normalize_merchant_response_url( string $url ): string {
		$trimmed_url = trim( $url );

		if ( '' === $trimmed_url ) {
			return home_url( '/wc-api/vinti4/' );
		}

		$parts = parse_url( $trimmed_url );

		if ( false === $parts ) {
			return home_url( '/wc-api/vinti4/' );
		}

		if ( ! empty( $parts['scheme'] ) && ! empty( $parts['host'] ) ) {
			return $trimmed_url;
		}

		if ( 0 === strpos( $trimmed_url, '//' ) ) {
			$home_scheme = parse_url( home_url( '/' ), PHP_URL_SCHEME );

			if ( ! is_string( $home_scheme ) || '' === $home_scheme ) {
				$home_scheme = 'https';
			}

			return $home_scheme . ':' . $trimmed_url;
		}

		if ( 0 !== strpos( $trimmed_url, '/' ) ) {
			$trimmed_url = '/' . $trimmed_url;
		}

		return home_url( $trimmed_url );
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
