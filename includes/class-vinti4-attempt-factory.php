<?php
/**
 * Canonical attempt creation service for Vinti4 payment attempts.
 *
 * @since 1.1.0
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'Vinti4_Attempt_Factory' ) ) {
	return;
}

class Vinti4_Attempt_Factory {

	/**
	 * UUID generator callback.
	 *
	 * @var callable
	 */
	private $uuid_generator;

	/**
	 * Timestamp generator callback.
	 *
	 * @var callable
	 */
	private $timestamp_provider;

	/**
	 * Entropy generator callback.
	 *
	 * @var callable
	 */
	private $entropy_provider;

	/**
	 * Created-at timestamp callback.
	 *
	 * @var callable
	 */
	private $created_at_provider;

	/**
	 * Constructor.
	 *
	 * @param array $providers Optional deterministic providers for testing.
	 */
	public function __construct( array $providers = array() ) {
		$this->uuid_generator = $providers['uuid_generator'] ?? static function (): string {
			return wp_generate_uuid4();
		};

		$this->timestamp_provider = $providers['timestamp_provider'] ?? static function (): string {
			return vinti4_format_timestamp();
		};

		$this->entropy_provider = $providers['entropy_provider'] ?? static function (): string {
			return wp_generate_password( 12, false, false );
		};

		$this->created_at_provider = $providers['created_at_provider'] ?? static function (): string {
			return gmdate( 'Y-m-d H:i:s' );
		};
	}

	/**
	 * Build a complete payment attempt for explicit order and amount context.
	 *
	 * @param WC_Order          $order            WooCommerce order.
	 * @param WC_Gateway_Vinti4 $gateway          Gateway settings source.
	 * @param mixed             $requested_amount Requested amount for this attempt.
	 * @param array             $context          Optional explicit attempt context.
	 * @return array
	 */
	public function create_attempt( WC_Order $order, WC_Gateway_Vinti4 $gateway, $requested_amount = null, array $context = array() ): array {
		$amount_float     = $this->resolve_requested_amount( $order, $requested_amount, $context );
		$timestamp        = $this->resolve_context_string( $context, 'timestamp', call_user_func( $this->timestamp_provider ) );
		$attempt_id       = $this->resolve_context_string( $context, 'attempt_id', call_user_func( $this->uuid_generator ) );
		$entropy          = $this->resolve_context_string( $context, 'entropy', call_user_func( $this->entropy_provider ) );
		$merchant_ref     = $this->resolve_context_string( $context, 'merchant_ref', $this->build_merchant_ref( (int) $order->get_id(), $timestamp, $entropy ) );
		$merchant_session = $this->resolve_context_string( $context, 'merchant_session', $this->build_merchant_session( $timestamp, $entropy ) );
		$created_at_gmt   = $this->resolve_context_string( $context, 'created_at_gmt', call_user_func( $this->created_at_provider ) );

		$attempt_context = array_merge(
			$context,
			array(
				'amount'           => $amount_float,
				'timestamp'        => $timestamp,
				'attempt_id'       => $attempt_id,
				'merchant_ref'     => $merchant_ref,
				'merchant_session' => $merchant_session,
			)
		);

		$attempt = Vinti4_Request_Builder::build_payment_attempt( $order, $gateway, $attempt_context );

		$attempt['created_at_gmt'] = $created_at_gmt;
		$attempt['metadata'] = array(
			'requested_amount' => (string) $amount_float,
			'source'           => isset( $context['source'] ) && is_scalar( $context['source'] ) ? (string) $context['source'] : 'checkout',
		);

		return $attempt;
	}

	/**
	 * Build merchantRef exactly 15 characters: MM + yymmddHHMMSS + 1 suffix.
	 *
	 * SISP requires merchantRef to be exactly 15 characters. Format:
	 *   MM + 12-digit timestamp (ymdHis) + 1 alphanumeric suffix = 15 chars.
	 *
	 * @param int    $order_id  WooCommerce order ID (unused, kept for API compat).
	 * @param string $timestamp Attempt timestamp.
	 * @param string $entropy   Attempt entropy token.
	 * @return string Exactly 15 characters.
	 */
	private function build_merchant_ref( int $order_id, string $timestamp, string $entropy ): string {
		unset( $order_id );

		$stamp = preg_replace( '/\D/', '', $timestamp );

		if ( ! is_string( $stamp ) || '' === $stamp ) {
			$stamp = gmdate( 'YmdHis' );
		}

		// Take last 12 digits of timestamp (equivalent to yymmddHHMMSS).
		$stamp12 = substr( $stamp, -12 );

		// 1 alphanumeric suffix from entropy for uniqueness.
		$clean_entropy = preg_replace( '/[^A-Za-z0-9]/', '', $entropy );
		if ( ! is_string( $clean_entropy ) || '' === $clean_entropy ) {
			$clean_entropy = substr( md5( $timestamp ), 0, 10 );
		}
		$suffix = strtolower( substr( $clean_entropy, 0, 1 ) );

		return 'MM' . $stamp12 . $suffix;
	}

	/**
	 * Build merchantSession exactly 15 characters: MS + yymmddHHMMSS + 1 suffix.
	 *
	 * SISP requires merchantSession to be exactly 15 characters. Format:
	 *   MS + 12-digit timestamp (ymdHis) + 1 alphanumeric suffix = 15 chars.
	 *
	 * Always different from merchantRef (different prefix + different suffix char).
	 *
	 * @param string $timestamp Attempt timestamp.
	 * @param string $entropy   Attempt entropy token.
	 * @return string Exactly 15 characters.
	 */
	private function build_merchant_session( string $timestamp, string $entropy ): string {
		$stamp = preg_replace( '/\D/', '', $timestamp );

		if ( ! is_string( $stamp ) || '' === $stamp ) {
			$stamp = gmdate( 'YmdHis' );
		}

		// Take last 12 digits of timestamp (equivalent to yymmddHHMMSS).
		$stamp12 = substr( $stamp, -12 );

		// 1 alphanumeric suffix — use a DIFFERENT position from merchantRef.
		$clean_entropy = preg_replace( '/[^A-Za-z0-9]/', '', $entropy );
		if ( ! is_string( $clean_entropy ) || '' === $clean_entropy ) {
			$clean_entropy = substr( md5( $timestamp ), 0, 12 );
		}
		$suffix = strtolower( substr( $clean_entropy, -1 ) );

		return 'MS' . $stamp12 . $suffix;
	}

	/**
	 * Resolve requested amount from explicit argument/context or order fallback.
	 *
	 * @param WC_Order $order            WooCommerce order.
	 * @param mixed    $requested_amount Requested amount.
	 * @param array    $context          Attempt context.
	 * @return float
	 */
	private function resolve_requested_amount( WC_Order $order, $requested_amount, array $context ): float {
		if ( null !== $requested_amount && is_scalar( $requested_amount ) ) {
			return (float) $requested_amount;
		}

		if ( array_key_exists( 'amount', $context ) && is_scalar( $context['amount'] ) ) {
			return (float) $context['amount'];
		}

		return (float) $order->get_total();
	}

	/**
	 * Resolve scalar context values to non-empty strings.
	 *
	 * @param array  $context Attempt context.
	 * @param string $key     Context key.
	 * @param string $default Default value.
	 * @return string
	 */
	private function resolve_context_string( array $context, string $key, string $default ): string {
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
}
