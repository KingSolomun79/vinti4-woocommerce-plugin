<?php

use PHPUnit\Framework\TestCase;

class Test_Attempt_Factory extends TestCase {

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
			private array $meta = array();

			public function get_id() { return 42; }
			public function get_total() { return 123.45; }
			public function get_currency() { return 'CVE'; }
			public function get_order_key() { return 'wc_order_key_42'; }
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

			public function get_meta( $key, $single = true ) {
				return $this->meta[ $key ] ?? '';
			}

			public function update_meta_data( $key, $value ) {
				$this->meta[ $key ] = $value;
			}

			public function save() {}
		};
	}

	public function test_create_attempt_generates_unique_merchant_fields_for_same_order(): void {
		$order = $this->create_order();
		$gateway = $this->create_gateway();

		$entropy_values = array( 'abc12345', 'def67890' );
		$uuid_values = array( 'attempt-1', 'attempt-2' );

		$factory = new Vinti4_Attempt_Factory(
			array(
				'timestamp_provider' => static function (): string {
					return '2026-04-17 14:00:00';
				},
				'created_at_provider' => static function (): string {
					return '2026-04-17 14:00:00';
				},
				'entropy_provider' => static function () use ( &$entropy_values ): string {
					return array_shift( $entropy_values ) ?: 'fallback';
				},
				'uuid_generator' => static function () use ( &$uuid_values ): string {
					return array_shift( $uuid_values ) ?: 'attempt-fallback';
				},
			)
		);

		$first_attempt  = $factory->create_attempt( $order, $gateway, 123.45 );
		$second_attempt = $factory->create_attempt( $order, $gateway, 123.45 );

		$this->assertNotSame( $first_attempt['merchant_ref'], $second_attempt['merchant_ref'] );
		$this->assertNotSame( $first_attempt['merchant_session'], $second_attempt['merchant_session'] );
		$this->assertSame( 1, preg_match( '/^WC42-/', $first_attempt['merchant_ref'] ) );
		$this->assertSame( 1, preg_match( '/^WC42-/', $second_attempt['merchant_ref'] ) );
	}

	public function test_create_attempt_binds_fingerprint_to_requested_amount_context(): void {
		$order = $this->create_order();
		$gateway = $this->create_gateway();

		$factory = new Vinti4_Attempt_Factory(
			array(
				'timestamp_provider' => static function (): string {
					return '2026-04-17 14:00:00';
				},
				'created_at_provider' => static function (): string {
					return '2026-04-17 14:00:00';
				},
				'entropy_provider' => static function (): string {
					return 'contextentropy';
				},
				'uuid_generator' => static function (): string {
					return 'attempt-context';
				},
			)
		);

		$attempt_low_amount = $factory->create_attempt(
			$order,
			$gateway,
			50.0,
			array(
				'timestamp'        => '2026-04-17 14:00:00',
				'merchant_ref'     => 'WC42-20260417140000abc12345',
				'merchant_session' => 'S14000000abc12345',
			)
		);

		$attempt_high_amount = $factory->create_attempt(
			$order,
			$gateway,
			75.0,
			array(
				'timestamp'        => '2026-04-17 14:00:00',
				'merchant_ref'     => 'WC42-20260417140000abc12345',
				'merchant_session' => 'S14000000abc12345',
			)
		);

		$this->assertSame( '50', $attempt_low_amount['amount'] );
		$this->assertSame( '75', $attempt_high_amount['amount'] );
		$this->assertNotSame( $attempt_low_amount['fingerprint'], $attempt_high_amount['fingerprint'] );
	}

	public function test_generated_attempts_append_and_enumerate_without_data_loss(): void {
		$order = $this->create_order();
		$gateway = $this->create_gateway();

		$entropy_values = array( 'appendone', 'appendtwo' );
		$uuid_values = array( 'attempt-append-1', 'attempt-append-2' );

		$factory = new Vinti4_Attempt_Factory(
			array(
				'timestamp_provider' => static function (): string {
					return '2026-04-17 14:00:00';
				},
				'created_at_provider' => static function (): string {
					return '2026-04-17 14:00:00';
				},
				'entropy_provider' => static function () use ( &$entropy_values ): string {
					return array_shift( $entropy_values ) ?: 'fallback';
				},
				'uuid_generator' => static function () use ( &$uuid_values ): string {
					return array_shift( $uuid_values ) ?: 'attempt-fallback';
				},
			)
		);

		$first_attempt  = $factory->create_attempt( $order, $gateway, 100.0 );
		$second_attempt = $factory->create_attempt( $order, $gateway, 200.0 );

		Vinti4_Attempt_Store::append_attempt( $order, $first_attempt );
		Vinti4_Attempt_Store::append_attempt( $order, $second_attempt );

		$history = Vinti4_Attempt_Store::get_attempts( $order );

		$this->assertCount( 2, $history );
		$this->assertSame( 'attempt-append-1', $history[0]['attempt_id'] );
		$this->assertSame( 'attempt-append-2', $history[1]['attempt_id'] );
		$this->assertSame( 1, $history[0]['sequence'] );
		$this->assertSame( 2, $history[1]['sequence'] );
		$this->assertSame( 'attempt-append-2', $order->get_meta( '_vinti4_attempt_id', true ) );
	}
}
