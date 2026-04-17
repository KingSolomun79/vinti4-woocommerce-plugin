<?php

use PHPUnit\Framework\TestCase;

class Test_Request_Builder extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['mock_wp_determine_locale'] = 'pt_PT';
		$GLOBALS['mock_wp_get_locale']       = 'pt_PT';
		$GLOBALS['mock_wc_instance']         = new class {
			public function api_request_url( $endpoint ) {
				return 'http://example.com/wc-api/' . $endpoint;
			}
		};
	}

	protected function tearDown(): void {
		parent::tearDown();
		unset(
			$GLOBALS['mock_wp_determine_locale'],
			$GLOBALS['mock_wp_get_locale'],
			$GLOBALS['mock_wc_instance']
		);
	}

	private function create_gateway( string $language = 'pt' ): WC_Gateway_Vinti4 {
		$gateway = new class extends WC_Gateway_Vinti4 {
			public string $id = '';
			public string $language = '';
			public string $pos_id = '';
			public string $pos_auth_code = '';

			public function __construct() {}
		};

		$gateway->id            = 'vinti4';
		$gateway->language      = $language;
		$gateway->pos_id        = '90000414';
		$gateway->pos_auth_code = '2XSPcf5fmjXiZ7hA';

		return $gateway;
	}

	private function create_order(): WC_Order {
		return new class extends WC_Order {
			public function get_id() { return 42; }
			public function get_total() { return 123.45; }
			public function get_currency() { return 'CVE'; }
			public function get_billing_phone() { return '+2389911223'; }
			public function get_billing_email() { return 'shopper@example.com'; }
			public function get_billing_city() { return 'Praia'; }
			public function get_billing_country() { return 'CV'; }
			public function get_billing_address_1() { return 'Palmarejo'; }
			public function get_billing_address_2() { return 'Apt 2'; }
			public function get_billing_postcode() { return '7600'; }
			public function get_billing_state() { return 'Santiago'; }
			public function get_shipping_city() { return 'Praia'; }
			public function get_shipping_country() { return 'CV'; }
			public function get_shipping_address_1() { return 'Palmarejo'; }
			public function get_shipping_postcode() { return '7600'; }
			public function get_shipping_state() { return 'Santiago'; }
			public function get_customer_id() { return 77; }
		};
	}

	public function test_build_payment_attempt_returns_expanded_browser_safe_contract(): void {
		$attempt = Vinti4_Request_Builder::build_payment_attempt( $this->create_order(), $this->create_gateway( 'pt' ) );

		$this->assertArrayHasKey( 'languageMessages', $attempt );
		$this->assertArrayHasKey( 'urlMerchantResponse', $attempt );
		$this->assertArrayHasKey( 'is3DSec', $attempt );
		$this->assertArrayHasKey( 'timeStamp', $attempt );
		$this->assertArrayHasKey( 'FingerPrint', $attempt );
		$this->assertArrayHasKey( 'FingerPrintVersion', $attempt );
		$this->assertSame( 'pt', $attempt['languageMessages'] );
		$this->assertSame( 'http://example.com/wc-api/vinti4', $attempt['urlMerchantResponse'] );
		$this->assertSame( '1', $attempt['is3DSec'] );
		$this->assertSame( '1', $attempt['FingerPrintVersion'] );
		$this->assertSame( $attempt['timeStamp'], $attempt['timestamp'] );
		$this->assertSame( $attempt['FingerPrint'], $attempt['fingerprint'] );
		$this->assertArrayNotHasKey( 'posAuthCode', $attempt );
	}

	public function test_build_payment_attempt_falls_back_to_gateway_language_when_locale_is_unsupported(): void {
		$GLOBALS['mock_wp_determine_locale'] = 'fr_FR';
		$GLOBALS['mock_wp_get_locale']       = 'es_ES';

		$attempt = Vinti4_Request_Builder::build_payment_attempt( $this->create_order(), $this->create_gateway( 'en' ) );

		$this->assertSame( 'en', $attempt['languageMessages'] );
	}

	public function test_build_payment_attempt_defaults_language_messages_to_portuguese(): void {
		$GLOBALS['mock_wp_determine_locale'] = 'fr_FR';
		$GLOBALS['mock_wp_get_locale']       = 'es_ES';

		$attempt = Vinti4_Request_Builder::build_payment_attempt( $this->create_order(), $this->create_gateway( 'invalid' ) );

		$this->assertSame( 'pt', $attempt['languageMessages'] );
	}

	public function test_build_payment_attempt_normalizes_relative_callback_url_to_absolute_home_url(): void {
		$GLOBALS['mock_wc_instance'] = new class {
			public function api_request_url( $endpoint ) {
				return '/wc-api/' . $endpoint . '/';
			}
		};

		$attempt = Vinti4_Request_Builder::build_payment_attempt( $this->create_order(), $this->create_gateway( 'pt' ) );

		$this->assertSame( 'http://example.com/wc-api/vinti4/', $attempt['urlMerchantResponse'] );
	}
}
