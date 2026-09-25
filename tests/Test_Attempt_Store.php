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

	private function create_order_with_total( float $total ): WC_Order {
		return new class( $total ) extends WC_Order {
			private array $meta = array();
			private float $total;

			public function __construct( float $total ) {
				$this->total = $total;
			}

			public function get_meta( $key, $single = true ) {
				return $this->meta[ $key ] ?? '';
			}

			public function update_meta_data( $key, $value ) {
				$this->meta[ $key ] = $value;
			}

			public function save() {}

			public function get_total() {
				return $this->total;
			}
		};
	}

	private function build_paid_attempt( string $attempt_id, float $amount, string $created_at_gmt, array $overrides = array() ): array {
		return array_merge(
			array(
				'attempt_id'     => $attempt_id,
				'amount'         => $amount,
				'status'         => 'completed',
				'created_at_gmt' => $created_at_gmt,
			),
			$overrides
		);
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

	/**
	 * An order can accumulate any number of payments over time — an online
	 * deposit, a further online top-up, and a manually recorded cash/wire
	 * payment. get_paid_total()/get_outstanding_total() must sum every
	 * completed/callback_received attempt regardless of source, and must
	 * ignore abandoned (no status) and failed attempts.
	 */
	public function test_paid_and_outstanding_totals_accumulate_across_multiple_mixed_attempts(): void {
		$order = $this->create_order_with_total( 1000.0 );

		// Abandoned checkout — no status key, must not count.
		Vinti4_Attempt_Store::append_attempt(
			$order,
			array( 'attempt_id' => 'abandoned-1', 'amount' => 1000.0, 'created_at_gmt' => '2026-09-01 09:00:00' ),
			false
		);

		// First online deposit — 30%, completed.
		Vinti4_Attempt_Store::append_attempt(
			$order,
			$this->build_paid_attempt( 'online-1', 300.0, '2026-09-01 10:00:00', array( 'metadata' => array( 'source' => 'checkout' ) ) ),
			false
		);

		// A failed retry for the remaining balance — must not count.
		Vinti4_Attempt_Store::append_attempt(
			$order,
			array( 'attempt_id' => 'failed-1', 'amount' => 700.0, 'status' => 'failed', 'created_at_gmt' => '2026-09-02 09:00:00' ),
			false
		);

		$this->assertSame( 300.0, Vinti4_Attempt_Store::get_paid_total( $order ) );
		$this->assertSame( 700.0, Vinti4_Attempt_Store::get_outstanding_total( $order ) );

		// A manual cash payment for part of the remainder, marked completed
		// via callback_received rather than status (as Vinti4_Manual_Payment writes it).
		Vinti4_Attempt_Store::append_attempt(
			$order,
			array(
				'attempt_id'        => 'manual-1',
				'amount'            => 400.0,
				'callback_received' => true,
				'created_at_gmt'    => '2026-09-03 09:00:00',
				'metadata'          => array( 'source' => 'manual', 'method' => 'cash' ),
			),
			false
		);

		$this->assertSame( 700.0, Vinti4_Attempt_Store::get_paid_total( $order ) );
		$this->assertSame( 300.0, Vinti4_Attempt_Store::get_outstanding_total( $order ) );

		// Final online payment closes out the balance exactly.
		Vinti4_Attempt_Store::append_attempt(
			$order,
			$this->build_paid_attempt( 'online-2', 300.0, '2026-09-04 09:00:00', array( 'metadata' => array( 'source' => 'checkout' ) ) ),
			false
		);

		$this->assertSame( 1000.0, Vinti4_Attempt_Store::get_paid_total( $order ) );
		$this->assertSame( 0.0, Vinti4_Attempt_Store::get_outstanding_total( $order ) );
		$this->assertCount( 5, Vinti4_Attempt_Store::get_attempts( $order ) );
	}

	/**
	 * Outstanding must floor at zero even if attempts somehow sum to more
	 * than the order total (e.g. a race between two staff members sending
	 * requests at once) — negative "owed" balances must never surface.
	 */
	public function test_outstanding_total_never_goes_negative(): void {
		$order = $this->create_order_with_total( 500.0 );

		Vinti4_Attempt_Store::append_attempt( $order, $this->build_paid_attempt( 'a1', 400.0, '2026-09-01 09:00:00' ), false );
		Vinti4_Attempt_Store::append_attempt( $order, $this->build_paid_attempt( 'a2', 400.0, '2026-09-01 09:05:00' ), false );

		$this->assertSame( 800.0, Vinti4_Attempt_Store::get_paid_total( $order ) );
		$this->assertSame( 0.0, Vinti4_Attempt_Store::get_outstanding_total( $order ) );
	}
}
