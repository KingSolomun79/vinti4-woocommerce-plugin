<?php

use PHPUnit\Framework\TestCase;

class Test_Redirect_Form extends TestCase {

	private WC_Order $order;

	protected function setUp(): void {
		parent::setUp();

		$this->order = new class extends WC_Order {
			public array $meta_keys_read = array();

			private array $meta = array(
				'_vinti4_merchant_ref'          => 'WC42-20260416143022',
				'_vinti4_merchant_session'      => 'Saaaaaaaaaaaa',
				'_vinti4_transaction_code'      => '1',
				'_vinti4_amount'                => '123450',
				'_vinti4_currency'              => '132',
				'_vinti4_timestamp'             => '2026-04-16 21:15:00',
				'_vinti4_language_messages'     => 'en',
				'_vinti4_url_merchant_response' => 'http://example.com/wc-api/vinti4',
				'_vinti4_is_3dsec'              => '1',
				'_vinti4_purchase_request_b64'  => 'eyJvcmRlciI6NDJ9',
				'_vinti4_fingerprint'           => 'fingerprint-base64',
				'_vinti4_fingerprint_version'   => '1',
			);

			public function get_order_key() {
				return 'wc_order_key';
			}

			public function get_meta( $key ) {
				$this->meta_keys_read[] = $key;
				return $this->meta[ $key ] ?? '';
			}

			public function set_meta( $key, $value ): void {
				$this->meta[ $key ] = $value;
			}
		};

		$GLOBALS['mock_wc_order'] = $this->order;
		$GLOBALS['mock_wc_instance'] = new class {
			public function payment_gateways() {
				return new class {
					public function get_available_payment_gateways() {
						$gateway = new class extends WC_Gateway_Vinti4 {
							public string $id = '';
							public string $pos_id = '';
							public string $pos_auth_code = '';
							public string $vbv2_url = '';

							public function __construct() {}
						};

						$gateway->id            = 'vinti4';
						$gateway->pos_id        = '90000414';
						$gateway->pos_auth_code = 'TOPSECRET123';
						$gateway->vbv2_url      = 'https://3dsteste.vinti4net.cv/3ds_middleware_php/public/3ds_init.php';

						return array(
							'vinti4' => $gateway,
						);
					}
				};
			}
		};

		$_GET = array(
			'order' => '42',
			'key'   => 'wc_order_key',
		);
	}

	protected function tearDown(): void {
		parent::tearDown();
		$_GET = array();
		unset( $GLOBALS['mock_wc_order'], $GLOBALS['mock_wc_instance'], $GLOBALS['mock_status_header'], $GLOBALS['mock_nocache_headers_called'] );
	}

	private function render_form_html(): string {
		ob_start();
		Vinti4_Redirect_Form::render();
		return (string) ob_get_clean();
	}

	private function extract_form_action( string $html ): string {
		preg_match( '/<form[^>]+action="([^"]+)"/', $html, $matches );
		return $matches[1] ?? '';
	}

	private function extract_hidden_input_values( string $html ): array {
		preg_match_all( '/<input type="hidden" name="([^"]+)" value="([^"]*)">/', $html, $matches, PREG_SET_ORDER );
		$inputs = array();

		foreach ( $matches as $match ) {
			$inputs[ $match[1] ] = html_entity_decode( $match[2], ENT_QUOTES, 'UTF-8' );
		}

		return $inputs;
	}

	public function test_render_uses_persisted_handoff_meta_and_keeps_secrets_out_of_browser_payload(): void {
		$html   = $this->render_form_html();
		$action = $this->extract_form_action( $html );
		$inputs = $this->extract_hidden_input_values( $html );

		$this->assertSame( 200, $GLOBALS['mock_status_header'] ?? null );
		$this->assertTrue( $GLOBALS['mock_nocache_headers_called'] ?? false );
		$this->assertStringContainsString( 'vinti4-payment-form', $html );
		$this->assertSame( '90000414', $inputs['posID'] ?? null );
		$this->assertSame( 'WC42-20260416143022', $inputs['merchantRef'] ?? null );
		$this->assertSame( 'Saaaaaaaaaaaa', $inputs['merchantSession'] ?? null );
		$this->assertSame( '123450', $inputs['amount'] ?? null );
		$this->assertSame( '132', $inputs['currency'] ?? null );
		$this->assertSame( '1', $inputs['transactionCode'] ?? null );
		$this->assertSame( 'eyJvcmRlciI6NDJ9', $inputs['purchaseRequest'] ?? null );
		$this->assertSame( 'en', $inputs['languageMessages'] ?? null );
		$this->assertSame( 'http://example.com/wc-api/vinti4', $inputs['urlMerchantResponse'] ?? null );
		$this->assertSame( '1', $inputs['is3DSec'] ?? null );
		$this->assertSame( 'fingerprint-base64', $inputs['FingerPrint'] ?? null );
		$this->assertSame( '2026-04-16 21:15:00', $inputs['TimeStamp'] ?? null );
		$this->assertSame( '1', $inputs['FingerPrintVersion'] ?? null );
		$this->assertArrayNotHasKey( 'posAuthCode', $inputs );
		$this->assertArrayNotHasKey( 'lang', $inputs );
		$this->assertStringNotContainsString( 'TOPSECRET123', $html );
		$this->assertStringNotContainsString( 'name="posAuthCode"', $html );

		$action_parts = parse_url( html_entity_decode( $action, ENT_QUOTES, 'UTF-8' ) );
		parse_str( $action_parts['query'] ?? '', $query );

		$this->assertSame( 'https', $action_parts['scheme'] ?? null );
		$this->assertSame( '3dsteste.vinti4net.cv', $action_parts['host'] ?? null );
		$this->assertSame( '/3ds_middleware_php/public/3ds_init.php', $action_parts['path'] ?? null );
		$this->assertSame( 'fingerprint-base64', $query['FingerPrint'] ?? null );
		$this->assertSame( '2026-04-16 21:15:00', $query['TimeStamp'] ?? null );
		$this->assertSame( '1', $query['FingerPrintVersion'] ?? null );
		$this->assertArrayNotHasKey( 'posAuthCode', $query );

		$this->assertSame(
			array(
				'_vinti4_merchant_ref',
				'_vinti4_merchant_session',
				'_vinti4_transaction_code',
				'_vinti4_amount',
				'_vinti4_currency',
				'_vinti4_timestamp',
				'_vinti4_language_messages',
				'_vinti4_url_merchant_response',
				'_vinti4_is_3dsec',
				'_vinti4_fingerprint',
				'_vinti4_fingerprint_version',
				'_vinti4_purchase_request_b64',
			),
			$this->order->meta_keys_read
		);
	}

	public function test_render_normalizes_legacy_relative_callback_url_from_order_meta(): void {
		$this->order->set_meta( '_vinti4_url_merchant_response', '/wc-api/vinti4/' );

		$html   = $this->render_form_html();
		$inputs = $this->extract_hidden_input_values( $html );

		$this->assertSame( 'http://example.com/wc-api/vinti4/', $inputs['urlMerchantResponse'] ?? null );
	}
}
