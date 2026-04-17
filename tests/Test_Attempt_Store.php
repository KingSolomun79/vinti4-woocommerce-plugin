<?php

use PHPUnit\Framework\TestCase;

class Test_Attempt_Store extends TestCase {

	private function create_order(): WC_Order {
		return new class extends WC_Order {
			private array $meta = array();

			public function get_meta( $key, $single = true ) {
				return $this->meta[ $key ] ?? '';
			}

			public function update_meta_data( $key, $value ) {
				$this->meta[ $key ] = $value;
			}

			public function save() {}
		};
	}

	private function build_attempt( string $attempt_id, string $merchant_ref, string $created_at_gmt ): array {
		return array(
			'attempt_id'           => $attempt_id,
			'timestamp'            => $created_at_gmt,
			'merchant_ref'         => $merchant_ref,
			'merchant_session'     => 'S' . $attempt_id,
			'transaction_code'     => '1',
			'amount'               => '1000',
			'currency'             => '132',
			'languageMessages'     => 'pt',
			'urlMerchantResponse'  => 'http://example.com/wc-api/vinti4',
			'is3DSec'              => '1',
			'FingerPrintVersion'   => '1',
			'purchase_request_b64' => 'e30=',
			'fingerprint'          => 'fingerprint-' . $attempt_id,
			'created_at_gmt'       => $created_at_gmt,
		);
	}

	public function test_append_attempt_preserves_existing_attempt_history(): void {
		$order = $this->create_order();

		$first = $this->build_attempt( 'attempt-1', 'WC42-1', '2026-04-17 10:00:00' );
		$second = $this->build_attempt( 'attempt-2', 'WC42-2', '2026-04-17 11:00:00' );

		Vinti4_Attempt_Store::append_attempt( $order, $first );
		Vinti4_Attempt_Store::append_attempt( $order, $second );

		$history = Vinti4_Attempt_Store::get_attempts( $order );

		$this->assertCount( 2, $history );
		$this->assertSame( 'attempt-1', $history[0]['attempt_id'] );
		$this->assertSame( 'WC42-1', $history[0]['merchant_ref'] );
		$this->assertSame( 'attempt-2', $history[1]['attempt_id'] );
		$this->assertSame( 1, $history[0]['sequence'] );
		$this->assertSame( 2, $history[1]['sequence'] );
	}

	public function test_get_attempts_returns_deterministic_chronological_order(): void {
		$order = $this->create_order();

		$later_attempt = $this->build_attempt( 'attempt-later', 'WC42-later', '2026-04-17 12:00:00' );
		$earlier_attempt = $this->build_attempt( 'attempt-earlier', 'WC42-earlier', '2026-04-17 09:00:00' );

		Vinti4_Attempt_Store::append_attempt( $order, $later_attempt, false );
		Vinti4_Attempt_Store::append_attempt( $order, $earlier_attempt, false );

		$history = Vinti4_Attempt_Store::get_attempts( $order );

		$this->assertCount( 2, $history );
		$this->assertSame( 'attempt-earlier', $history[0]['attempt_id'] );
		$this->assertSame( 'attempt-later', $history[1]['attempt_id'] );
	}

	public function test_append_attempt_updates_legacy_projection_without_truncating_history(): void {
		$order = $this->create_order();

		$first = $this->build_attempt( 'attempt-1', 'WC42-1', '2026-04-17 10:00:00' );
		$second = $this->build_attempt( 'attempt-2', 'WC42-2', '2026-04-17 11:00:00' );

		Vinti4_Attempt_Store::append_attempt( $order, $first );
		Vinti4_Attempt_Store::append_attempt( $order, $second );

		$history = Vinti4_Attempt_Store::get_attempts( $order );

		$this->assertCount( 2, $history );
		$this->assertSame( 'attempt-1', $history[0]['attempt_id'] );
		$this->assertSame( 'attempt-2', $order->get_meta( '_vinti4_attempt_id', true ) );
		$this->assertSame( 'WC42-2', $order->get_meta( '_vinti4_merchant_ref', true ) );
		$this->assertSame( 'fingerprint-attempt-2', $order->get_meta( '_vinti4_fingerprint', true ) );
	}
}
