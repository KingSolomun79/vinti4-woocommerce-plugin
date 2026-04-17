<?php
/**
 * Vinti4 WooCommerce Payment Gateway
 *
 * Provides a hosted payment page integration with Vinti4 / SISP.
 *
 * @package Vinti4ForWooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Gateway_Vinti4
 *
 * WooCommerce payment gateway for Vinti4 / SISP hosted payment page.
 */
class WC_Gateway_Vinti4 extends WC_Payment_Gateway {
	/**
	 * Default currency setting value.
	 *
	 * Declared explicitly for PHP 8.2+ dynamic property compatibility.
	 *
	 * @var string
	 */
	public string $currency_default = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id                 = 'vinti4';
		$this->method_title       = __( 'Vinti4', 'vinti4' );
		$this->method_description = __( 'Pay via Vinti4 / SISP hosted payment page.', 'vinti4' );
		$this->has_fields         = false;
		$this->supports           = array( 'products' );
		$this->icon               = '';

		// Load form fields and settings.
		$this->init_form_fields();
		$this->init_settings();

		// Load saved settings into properties.
		$this->title            = $this->get_option( 'title' );
		$this->description      = $this->get_option( 'description' );
		$this->enabled          = $this->get_option( 'enabled' );
		$this->pos_id           = $this->get_option( 'pos_id' );
		$this->pos_auth_code    = $this->get_option( 'pos_auth_code' );
		$this->vbv2_url         = $this->get_option( 'vbv2_url' );
		$this->language         = $this->get_option( 'language' );
		$this->debug            = $this->get_option( 'debug' );
		$this->currency_default = $this->get_option( 'currency_default' );

		// Save settings on admin update.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		// Callback handler for SISP response.
		add_action( 'woocommerce_api_' . $this->id, array( $this, 'handle_callback' ) );

		// Initialize logger with current debug setting.
		Vinti4_Logger::init( $this );

		// Load admin test panel in admin context only.
		if ( is_admin() ) {
			require_once VINTI4_PLUGIN_DIR . 'includes/class-vinti4-admin-test-panel.php';
			Vinti4_Admin_Test_Panel::register();
		}
	}

	/**
	 * Define admin-facing settings fields.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'     => array(
				'title'   => __( 'Enable/Disable', 'vinti4' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Vinti4', 'vinti4' ),
				'default' => 'no',
			),
			'title'       => array(
				'title'       => __( 'Title', 'vinti4' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'vinti4' ),
				'default'     => __( 'Pay with Vinti4', 'vinti4' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Description', 'vinti4' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'vinti4' ),
				'default'     => __( 'You will be redirected to Vinti4 to complete payment.', 'vinti4' ),
				'desc_tip'    => true,
			),
			'pos_id'          => array(
				'title'       => __( 'POS ID', 'vinti4' ),
				'type'        => 'text',
				'description' => __( 'Your POS identifier provided by SISP.', 'vinti4' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'pos_auth_code'   => array(
				'title'             => __( 'POS Auth Code', 'vinti4' ),
				'type'              => 'text',
				'description'       => __( 'Authentication code from SISP. Special characters are preserved exactly as entered.', 'vinti4' ),
				'default'           => '',
				'desc_tip'          => true,
				'custom_attributes' => array( 'autocomplete' => 'off' ),
			),
			'vbv2_url'        => array(
				'title'       => __( 'SISP Payment URL', 'vinti4' ),
				'type'        => 'text',
				'description' => __( 'URL of the SISP 3DS payment page. Use the test URL for sandbox mode.', 'vinti4' ),
				'default'     => 'https://3dsteste.vinti4net.cv/3ds_middleware_php/public/3ds_init.php',
				'desc_tip'    => true,
			),
			'language'        => array(
				'title'       => __( 'Language', 'vinti4' ),
				'type'        => 'select',
				'description' => __( 'Language for the SISP payment page.', 'vinti4' ),
				'default'     => 'pt',
				'options'     => array(
					'pt' => __( 'Portuguese', 'vinti4' ),
					'en' => __( 'English', 'vinti4' ),
				),
				'desc_tip'    => true,
			),
			'debug'           => array(
				'title'       => __( 'Debug Mode', 'vinti4' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable logging', 'vinti4' ),
				'description' => __( 'Log payment events for debugging. Do not enable in production.', 'vinti4' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'currency_default' => array(
				'title'       => __( 'Default Currency', 'vinti4' ),
				'type'        => 'select',
				'description' => __( 'Currency used when auto-detection from the order is not possible. Auto-detection uses the WooCommerce order currency.', 'vinti4' ),
				'default'     => 'CVE',
				'options'     => array(
					'CVE' => __( 'CVE — Cape Verdean Escudo', 'vinti4' ),
					'EUR' => __( 'EUR — Euro', 'vinti4' ),
					'USD' => __( 'USD — US Dollar', 'vinti4' ),
				),
				'desc_tip'    => true,
			),
		);
	}

	/**
	 * Save admin options with custom sanitization for POS Auth Code.
	 *
	 * Calls parent first, then overwrites pos_auth_code using wp_unslash()
	 * to preserve special characters (% + / =) that sanitize_text_field() would strip.
	 *
	 * @return void
	 */
	public function process_admin_options() {
		parent::process_admin_options();

		$post_key = 'woocommerce_' . $this->id . '_pos_auth_code';
		if ( isset( $_POST[ $post_key ] ) ) {
			$this->update_option( 'pos_auth_code', wp_unslash( $_POST[ $post_key ] ) );
		}
	}

	/**
	 * Get the ISO 4217 numeric currency code for a payment.
	 *
	 * Auto-detects the currency from the WooCommerce order when provided.
	 * Falls back to the currency_default setting, then ultimately to CVE (132).
	 *
	 * @param WC_Order|null $order Optional. WooCommerce order to detect currency from.
	 * @return string Numeric currency code (e.g. '132' for CVE).
	 */
	public function get_currency_code( $order = null ) {
		$currency_map = array(
			'CVE' => '132',
			'EUR' => '978',
			'USD' => '840',
			'AOA' => '973',
			'BRL' => '986',
			'GBP' => '826',
		);

		$currency = '';

		if ( $order && is_a( $order, 'WC_Order' ) ) {
			$currency = $order->get_currency();
		}

		if ( empty( $currency ) || ! isset( $currency_map[ $currency ] ) ) {
			$currency = $this->get_option( 'currency_default', 'CVE' );
		}

		return isset( $currency_map[ $currency ] ) ? $currency_map[ $currency ] : '132';
	}

	/**
	 * Resolve the SISP middleware language field.
	 *
	 * Prefers the active WordPress locale when it maps cleanly to a supported
	 * SISP language. Falls back to the saved gateway setting, then to Portuguese.
	 *
	 * @return string
	 */
	public function resolve_language_messages(): string {
		$locale_candidates = array();

		if ( function_exists( 'determine_locale' ) ) {
			$locale_candidates[] = determine_locale();
		}

		if ( function_exists( 'get_locale' ) ) {
			$locale_candidates[] = get_locale();
		}

		foreach ( $locale_candidates as $locale_candidate ) {
			$language = $this->map_locale_to_language_messages( $locale_candidate );

			if ( '' !== $language ) {
				return $language;
			}
		}

		$setting_language = $this->map_locale_to_language_messages( $this->language );

		if ( '' !== $setting_language ) {
			return $setting_language;
		}

		return 'pt';
	}

	/**
	 * Build the callback URL SISP should return the shopper to.
	 *
	 * @return string
	 */
	public function get_url_merchant_response(): string {
		$fallback_url = add_query_arg( 'wc-api', $this->id, home_url( '/' ) );

		if ( function_exists( 'WC' ) ) {
			$woocommerce = WC();

			if ( is_object( $woocommerce ) && method_exists( $woocommerce, 'api_request_url' ) ) {
				$api_url = $this->normalize_callback_url( (string) $woocommerce->api_request_url( $this->id ) );

				if ( '' !== $api_url ) {
					return $api_url;
				}
			}
		}

		return $fallback_url;
	}

	/**
	 * Normalize callback URL values to an absolute merchant URL.
	 *
	 * @param string $url Callback URL candidate.
	 * @return string
	 */
	private function normalize_callback_url( string $url ): string {
		$trimmed_url = trim( $url );

		if ( '' === $trimmed_url ) {
			return '';
		}

		$parts = parse_url( $trimmed_url );

		if ( false === $parts ) {
			return '';
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
	 * Get the hosted 3DS flag expected by the SISP middleware.
	 *
	 * @return string
	 */
	public function get_is_3dsec_flag(): string {
		return '1';
	}

	/**
	 * Get the canonical outbound fingerprint version.
	 *
	 * @return string
	 */
	public function get_fingerprint_version(): string {
		return '1';
	}

	/**
	 * Resolve the amount multiplier used in request fingerprint normalization.
	 *
	 * Supported values are 1, 100, and 1000. Default is 1000.
	 * A temporary override can be set via VINTI4_FINGERPRINT_AMOUNT_SCALE.
	 *
	 * @return int
	 */
	public function get_fingerprint_amount_scale(): int {
		$scale = 1000;

		if ( defined( 'VINTI4_FINGERPRINT_AMOUNT_SCALE' ) ) {
			$scale = (int) VINTI4_FINGERPRINT_AMOUNT_SCALE;
		}

		if ( ! in_array( $scale, array( 1, 100, 1000 ), true ) ) {
			return 1000;
		}

		return $scale;
	}

	/**
	 * Resolve auth-segment mode used in request fingerprint generation.
	 *
	 * Supported values:
	 * - prehashed (default): first segment is sha512_base64(posAuthCode)
	 * - raw: first segment is raw posAuthCode
	 *
	 * Override with VINTI4_FINGERPRINT_AUTH_MODE in wp-config.php.
	 *
	 * @return string
	 */
	public function get_fingerprint_auth_mode(): string {
		$mode = 'prehashed';

		if ( defined( 'VINTI4_FINGERPRINT_AUTH_MODE' ) ) {
			$mode = (string) VINTI4_FINGERPRINT_AUTH_MODE;
		}

		if ( ! in_array( $mode, array( 'prehashed', 'raw' ), true ) ) {
			return 'prehashed';
		}

		return $mode;
	}

	/**
	 * Map a locale or gateway setting value to the SISP language field.
	 *
	 * @param mixed $locale Locale-like value.
	 * @return string
	 */
	private function map_locale_to_language_messages( $locale ): string {
		if ( ! is_string( $locale ) || '' === $locale ) {
			return '';
		}

		$normalized = strtolower( trim( $locale ) );

		if ( 'pt' === $normalized || 0 === strpos( $normalized, 'pt_' ) || 0 === strpos( $normalized, 'pt-' ) ) {
			return 'pt';
		}

		if ( 'en' === $normalized || 0 === strpos( $normalized, 'en_' ) || 0 === strpos( $normalized, 'en-' ) ) {
			return 'en';
		}

		return '';
	}

	/**
	 * Process the payment for a given order.
	 *
	 * Validates gateway configuration, builds a payment attempt via the request
	 * builder, stores all attempt fields as order meta, and redirects to the
	 * Vinti4 payment page that will POST the data to SISP.
	 *
	 * @param int $order_id Order ID.
	 * @return array {
	 *     @type string $result   'success' or 'failure'.
	 *     @type string $redirect URL to redirect to on success.
	 * }
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			wc_add_notice( __( 'Invalid order. Please try again.', 'vinti4' ), 'error' );
			return array(
				'result'   => 'failure',
				'redirect' => '',
			);
		}

		// Validate required gateway settings.
		if ( empty( $this->pos_id ) || empty( $this->pos_auth_code ) || empty( $this->vbv2_url ) ) {
			Vinti4_Logger::log( 'Payment attempt blocked: gateway configuration incomplete (missing pos_id, pos_auth_code, or vbv2_url).', 'error' );
			wc_add_notice( __( 'Payment configuration is incomplete. Please contact support.', 'vinti4' ), 'error' );
			return array(
				'result'   => 'failure',
				'redirect' => '',
			);
		}

		// Build the canonical payment attempt.
		$attempt = Vinti4_Request_Builder::build_payment_attempt( $order, $this );

		// Store all attempt fields as order meta.
		$order->update_meta_data( '_vinti4_attempt_id', $attempt['attempt_id'] );
		$order->update_meta_data( '_vinti4_timestamp', $attempt['timeStamp'] );
		$order->update_meta_data( '_vinti4_merchant_ref', $attempt['merchant_ref'] );
		$order->update_meta_data( '_vinti4_merchant_session', $attempt['merchant_session'] );
		$order->update_meta_data( '_vinti4_transaction_code', $attempt['transaction_code'] );
		$order->update_meta_data( '_vinti4_amount', $attempt['amount'] );
		$order->update_meta_data( '_vinti4_currency', $attempt['currency'] );
		$order->update_meta_data( '_vinti4_language_messages', $attempt['languageMessages'] );
		$order->update_meta_data( '_vinti4_url_merchant_response', $attempt['urlMerchantResponse'] );
		$order->update_meta_data( '_vinti4_is_3dsec', $attempt['is3DSec'] );
		$order->update_meta_data( '_vinti4_purchase_request_b64', $attempt['purchase_request_b64'] );
		$order->update_meta_data( '_vinti4_fingerprint', $attempt['FingerPrint'] );
		$order->update_meta_data( '_vinti4_fingerprint_version', $attempt['FingerPrintVersion'] );
		$order->update_meta_data( '_vinti4_callback_processed', '' );
		$order->save();

		// Build redirect URL to the Vinti4 payment page.
		$redirect_url = add_query_arg(
			array(
				'order' => $order_id,
				'key'   => $order->get_order_key(),
			),
			home_url( '/vinti4-payment/' )
		);

		return array(
			'result'   => 'success',
			'redirect' => $redirect_url,
		);
	}

	/**
	 * Handle the SISP callback response.
	 *
	 * Delegates to Vinti4_Callback_Handler for validation and order processing.
	 * Triggered by the woocommerce_api_vinti4 endpoint when SISP redirects
	 * the shopper back after payment.
	 *
	 * @return void
	 */
	public function handle_callback(): void {
		Vinti4_Callback_Handler::handle( $this );
	}
}
