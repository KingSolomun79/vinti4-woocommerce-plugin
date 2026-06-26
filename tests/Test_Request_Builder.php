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
			public function get_date_created() { return null; }
			public function get_date_modified() { return null; }
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
		$this->assertSame( 'en', $attempt['languageMessages'] );
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

	public function test_build_payment_attempt_defaults_language_messages_to_english(): void {
		$GLOBALS['mock_wp_determine_locale'] = 'fr_FR';
		$GLOBALS['mock_wp_get_locale']       = 'es_ES';

		$attempt = Vinti4_Request_Builder::build_payment_attempt( $this->create_order(), $this->create_gateway( 'invalid' ) );

		$this->assertSame( 'en', $attempt['languageMessages'] );
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

	public function test_build_payment_attempt_uses_explicit_context_amount_for_fingerprint(): void {
		$gateway = $this->create_gateway( 'pt' );
		$order   = $this->create_order();

		$common_context = array(
			'attempt_id'       => 'attempt-context-1',
			'timestamp'        => '2026-04-17 14:00:00',
			'merchant_ref'     => 'WC42-20260417140000abcd1234',
			'merchant_session' => 'S14000000abcd1234',
		);

		$attempt_low_amount  = Vinti4_Request_Builder::build_payment_attempt( $order, $gateway, $common_context + array( 'amount' => 50.0 ) );
		$attempt_high_amount = Vinti4_Request_Builder::build_payment_attempt( $order, $gateway, $common_context + array( 'amount' => 75.0 ) );

		$this->assertSame( '50', $attempt_low_amount['amount'] );
		$this->assertSame( '75', $attempt_high_amount['amount'] );
		$this->assertNotSame( $attempt_low_amount['fingerprint'], $attempt_high_amount['fingerprint'] );
	}

	public function test_build_payment_attempt_generates_valid_purchase_request(): void {
		$attempt = Vinti4_Request_Builder::build_payment_attempt( $this->create_order(), $this->create_gateway( 'pt' ) );
		
		$this->assertArrayHasKey( 'purchase_request_b64', $attempt );
		$json = base64_decode( $attempt['purchase_request_b64'] );
		$data = json_decode( $json, true );
		
		$this->assertIsArray( $data );
		$this->assertSame( 'shopper@example.com', $data['email'] );
		$this->assertSame( 'shopper@example.com', $data['acctID'] );
		$this->assertSame( 'Praia', $data['billAddrCity'] );
		$this->assertSame( '132', $data['billAddrCountry'] );
		$this->assertSame( 'Palmarejo', $data['billAddrLine1'] );
		$this->assertSame( 'Apt 2', $data['billAddrLine2'] );
		$this->assertSame( '7600', $data['billAddrPostCode'] );
		$this->assertSame( '238', $data['mobilePhone']['cc'] );
		$this->assertSame( '9911223', $data['mobilePhone']['subscriber'] );
		$this->assertArrayNotHasKey( 'purchaseDate', $data );
		$this->assertArrayNotHasKey( 'entityCode', $data );
		$this->assertArrayNotHasKey( 'referenceNumber', $data );
	}

	public function test_build_payment_attempt_throws_exception_on_missing_email(): void {
		$order = new class extends WC_Order {
			public function get_id() { return 42; }
			public function get_total() { return 123.45; }
			public function get_billing_email() { return ''; }
			public function get_billing_phone() { return ''; }
			public function get_billing_city() { return ''; }
			public function get_billing_country() { return ''; }
			public function get_billing_address_1() { return ''; }
			public function get_billing_address_2() { return ''; }
			public function get_billing_postcode() { return ''; }
			public function get_date_created() { return null; }
			public function get_date_modified() { return null; }
		};

		$this->expectException( RuntimeException::class );
		Vinti4_Request_Builder::build_payment_attempt( $order, $this->create_gateway( 'pt' ) );
	}
}
