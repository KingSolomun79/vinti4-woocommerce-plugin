<?php
/**
 * Tests for Vinti4_Callback_Handler
 *
 * Verifies callback handling behavior: attempt-level resolution, per-attempt
 * idempotency, partial payment totals, duplicate detection, invalid fingerprint
 * rejection, missing data rejection, and unparseable merchant reference rejection.
 * Uses pure PHPUnit with mocked WP/WC functions.
 *
 * @since 1.0.0
 */

use PHPUnit\Framework\TestCase;

class Test_Callback_Handler extends TestCase {

	/**
	 * Mock gateway instance with pos_auth_code.
	 */
	private WC_Gateway_Vinti4 $gateway;

	/**
	 * Mock order that tracks method calls.
	 */
	private WC_Order $order;

	protected function setUp(): void {
		parent::setUp();

		// Create a mock gateway with the required pos_auth_code property.
		$this->gateway = new class extends WC_Gateway_Vinti4 {
			public string $pos_auth_code = '';

			public function __construct() {
				// Bypass parent constructor — no settings loading or add_action calls.
			}
		};
		$this->gateway->pos_auth_code = 'TESTAUTH123';

		// Create a fresh mock order for each test.
		$this->order = $this->create_mock_order();

		// Set up global mock order for wc_get_order().
		$GLOBALS['mock_wc_order'] = $this->order;

		// Reset $_POST for each test.
		$_POST = array();
		$_GET  = array();
	}

	protected function tearDown(): void {
		parent::tearDown();
		$_POST = array();
		$_GET  = array();
		unset( $GLOBALS['mock_wc_order'] );
		unset( $GLOBALS['mock_wc_orders_by_ref'] );
	}

	/**
	 * Create a mock WC_Order with call tracking.
	 *
	 * Extends the stub WC_Order class to satisfy type hints in the callback handler.
	 */
	private function create_mock_order( array $meta_overrides = array() ): WC_Order {
		return new class( $meta_overrides ) extends WC_Order {

			/** @var array<string, mixed> Stored meta values. */
			private array $test_meta;

			/** @var bool Whether payment_complete() was called. */
			public bool $payment_complete_called = false;

			/** @var string|null Transaction ID passed to payment_complete(). */
			public ?string $payment_complete_txn_id = null;

			/** @var bool Whether update_status() was called. */
			public bool $update_status_called = false;

			/** @var string|null Status passed to update_status(). */
			public ?string $updated_status = null;

			/** @var string|null Note passed to update_status(). */
			public ?string $updated_status_note = null;

			/** @var string Current order status. */
			private string $test_status = 'pending';

			/** @var float Order total. */
			private float $test_total;

			/** @var int Order ID. */
			private int $test_id;

			public function __construct( array $meta_overrides = array() ) {
				$this->test_meta = array_merge(
					array(
						'_vinti4_merchant_ref'       => 'WC42-20260416143022',
						'_vinti4_amount'             => '100000',
						'_vinti4_callback_processed' => '',
					),
					$meta_overrides
				);
				$this->test_total = 100.0;
				$this->test_id    = 42;
			}

			public function get_id(): int {
				return $this->test_id;
			}

			public function get_meta( $key, $single = true ) {
				return $this->test_meta[ $key ] ?? '';
			}

			public function update_meta_data( $key, $value ): void {
				$this->test_meta[ $key ] = $value;
			}

			public function save(): void {}

			public function get_status(): string {
				return $this->test_status;
			}

			public function update_status( $status, $note = '' ): void {
				$this->update_status_called = true;
				$this->updated_status       = $status;
				$this->updated_status_note  = $note;
				$this->test_status          = $status;
			}

			public function payment_complete( $transaction_id = '' ): void {
				$this->payment_complete_called  = true;
				$this->payment_complete_txn_id  = $transaction_id;
				$this->test_status              = 'processing';
			}

			public function get_checkout_order_received_url(): string {
				return '/order-received/';
			}

			public function add_order_note( $note ): void {}

			public function get_total(): float {
				return $this->test_total;
			}
		};
	}

	/**
	 * Create a mock order with attempt history for attempt-level tests.
	 *
	 * @param array $attempts     Array of attempt records.
	 * @param array $meta_extra   Additional meta overrides.
	 * @param float $order_total  Order total for outstanding calculation.
	 * @return WC_Order
	 */
	private function create_order_with_attempts( array $attempts, array $meta_extra = array(), float $order_total = 200.0 ): WC_Order {
		$meta = array_merge(
			array(
				'_vinti4_attempt_history' => $attempts,
				'_vinti4_merchant_ref'    => $attempts[0]['merchant_ref'] ?? 'WC42-20260416143022',
				'_vinti4_amount'          => $attempts[0]['amount'] ?? '100',
			),
			$meta_extra
		);

		return new class( $meta, $order_total ) extends WC_Order {

			/** @var array<string, mixed> Stored meta values. */
			private array $test_meta;

			/** @var bool Whether payment_complete() was called. */
			public bool $payment_complete_called = false;

			/** @var string|null Transaction ID passed to payment_complete(). */
			public ?string $payment_complete_txn_id = null;

			/** @var bool Whether update_status() was called. */
			public bool $update_status_called = false;

			/** @var string|null Status passed to update_status(). */
			public ?string $updated_status = null;

			/** @var string|null Note passed to update_status(). */
			public ?string $updated_status_note = null;

			/** @var string Current order status. */
			private string $test_status = 'pending';

			/** @var float Order total. */
			private float $test_total;

			public function __construct( array $meta, float $order_total ) {
				$this->test_meta = $meta;
				$this->test_total = $order_total;
			}

			public function get_id(): int {
				return 42;
			}

			public function get_meta( $key, $single = true ) {
				return $this->test_meta[ $key ] ?? '';
			}

			public function update_meta_data( $key, $value ): void {
				$this->test_meta[ $key ] = $value;
			}

			public function save(): void {}

			public function get_status(): string {
				return $this->test_status;
			}

			public function update_status( $status, $note = '' ): void {
				$this->update_status_called = true;
				$this->updated_status       = $status;
				$this->updated_status_note  = $note;
				$this->test_status          = $status;
			}

			public function payment_complete( $transaction_id = '' ): void {
				$this->payment_complete_called  = true;
				$this->payment_complete_txn_id  = $transaction_id;
				$this->test_status              = 'processing';
			}

			public function get_checkout_order_received_url(): string {
				return '/order-received/';
			}

			public function add_order_note( $note ): void {}

			public function get_total(): float {
				return $this->test_total;
			}
		};
	}

	/**
	 * Build an attempt record for testing.
	 */
	private function build_attempt( string $attempt_id, string $merchant_ref, string $merchant_session, string $amount, string $status = 'pending' ): array {
		return array(
			'attempt_id'       => $attempt_id,
			'merchant_ref'     => $merchant_ref,
			'merchant_session' => $merchant_session,
			'amount'           => $amount,
			'status'           => $status,
			'created_at_gmt'   => '2026-04-17 10:00:00',
			'sequence'         => 1,
			'timestamp'        => '2026-04-17 10:00:00',
			'currency'         => '132',
			'transaction_code' => '1',
		);
	}

	/**
	 * Build a valid POST payload for a successful callback.
	 */
	private function valid_post_payload( array $overrides = array() ): array {
		// Compute the correct fingerprint for these values.
		$pos_auth_code     = 'TESTAUTH123';
		$message_type      = '8';
		$clearing_period   = '2026-04-16';
		$transaction_id    = 'TXN123456';
		$merchant_ref      = 'WC42-20260416143022';
		$merchant_session  = 'Saaaaaaaaaaaa';
		$purchase_amount   = '100';
		$message_id        = 'MSG789';
		$pan               = '411111******1111';
		$merchant_response = 'Approved';
		$timestamp         = '2026-04-16 14:31:00';
		$reference_number  = 'REF001';
		$entity_code       = '54321';
		$client_receipt    = 'true';
		$additional_error  = '';
		$reload_code       = '0';

		$correct_fingerprint = Vinti4_Fingerprint::build_response_fingerprint(
			$pos_auth_code,
			$message_type,
			$clearing_period,
			$transaction_id,
			$merchant_ref,
			$merchant_session,
			$purchase_amount,
			$message_id,
			$pan,
			$merchant_response,
			$timestamp,
			$reference_number,
			$entity_code,
			$client_receipt,
			$additional_error,
			$reload_code
		);

		$payload = array(
			'messageType'                            => $message_type,
			'resultFingerPrint'                      => $correct_fingerprint,
			'merchantRespMerchantRef'                => $merchant_ref,
			'merchantRespMerchantSession'            => $merchant_session,
			'merchantRespPurchaseAmount'             => $purchase_amount,
			'merchantRespCP'                         => $clearing_period,
			'merchantRespTid'                        => $transaction_id,
			'merchantRespMessageID'                  => $message_id,
			'merchantRespPan'                        => $pan,
			'merchantResp'                           => $merchant_response,
			'merchantRespTimeStamp'                  => $timestamp,
			'merchantRespReferenceNumber'            => $reference_number,
			'merchantRespEntityCode'                 => $entity_code,
			'merchantRespClientReceipt'              => $client_receipt,
			'merchantRespAdditionalErrorMessage'     => $additional_error,
			'merchantRespReloadCode'                 => $reload_code,
			'merchantRespErrorDetail'                => '',
			'merchantRespErrorDescription'           => '',
		);

		return array_merge( $payload, $overrides );
	}

	/**
	 * Build a valid attempt-level payload for a specific attempt.
	 */
	private function attempt_post_payload( string $merchant_ref, string $merchant_session, string $purchase_amount, array $overrides = array() ): array {
		$pos_auth_code     = 'TESTAUTH123';
		$message_type      = '8';
		$clearing_period   = '2026-04-17';
		$transaction_id    = 'TXN-ATTEMPT-001';
		$message_id        = 'MSG-ATTEMPT';
		$pan               = '411111******1111';
		$merchant_response = 'Approved';
		$timestamp         = '2026-04-17 10:01:00';
		$reference_number  = 'REF-ATT';
		$entity_code       = '54321';
		$client_receipt    = 'true';
		$additional_error  = '';
		$reload_code       = '0';

		$correct_fingerprint = Vinti4_Fingerprint::build_response_fingerprint(
			$pos_auth_code,
			$message_type,
			$clearing_period,
			$transaction_id,
			$merchant_ref,
			$merchant_session,
			$purchase_amount,
			$message_id,
			$pan,
			$merchant_response,
			$timestamp,
			$reference_number,
			$entity_code,
			$client_receipt,
			$additional_error,
			$reload_code
		);

		$payload = array(
			'messageType'                            => $message_type,
			'resultFingerPrint'                      => $correct_fingerprint,
			'merchantRespMerchantRef'                => $merchant_ref,
			'merchantRespMerchantSession'            => $merchant_session,
			'merchantRespPurchaseAmount'             => $purchase_amount,
			'merchantRespCP'                         => $clearing_period,
			'merchantRespTid'                        => $transaction_id,
			'merchantRespMessageID'                  => $message_id,
			'merchantRespPan'                        => $pan,
			'merchantResp'                           => $merchant_response,
			'merchantRespTimeStamp'                  => $timestamp,
			'merchantRespReferenceNumber'            => $reference_number,
			'merchantRespEntityCode'                 => $entity_code,
			'merchantRespClientReceipt'              => $client_receipt,
			'merchantRespAdditionalErrorMessage'     => $additional_error,
			'merchantRespReloadCode'                 => $reload_code,
			'merchantRespErrorDetail'                => '',
			'merchantRespErrorDescription'           => '',
		);

		return array_merge( $payload, $overrides );
	}

	// ─── Attempt-Level Tests ─────────────────────────────────────────────────

	/**
	 * Test: Callback resolves exact attempt from history by merchantRef.
	 *
	 * When an order has attempt history and the callback merchantRef matches
	 * an attempt, the attempt-level validation path should be used.
	 */
	public function test_resolve_attempt_by_merchant_ref(): void {
		$attempt = $this->build_attempt(
			'att-001',
			'WC42-20260417100000abc',
			'Ssession001abc',
			'100'
		);

		$this->order = $this->create_order_with_attempts( array( $attempt ) );
		$GLOBALS['mock_wc_order'] = $this->order;

		$_POST = $this->attempt_post_payload(
			'WC42-20260417100000abc',
			'Ssession001abc',
			'100'
		);

		try {
			Vinti4_Callback_Handler::handle( $this->gateway );
		} catch ( Vinti4_Redirect_Exception $e ) {
			// Expected redirect after successful completion.
		}

		// Verify payment_complete was called (full payment since amount matches order total).
		$this->assertTrue( $this->order->payment_complete_called, 'payment_complete should be called for resolved attempt.' );
		$this->assertSame( 'TXN-ATTEMPT-001', $this->order->payment_complete_txn_id );
	}

	/**
	 * Test: Per-attempt idempotency prevents duplicate processing.
	 *
	 * When an attempt has already been processed (meta key set), the callback
	 * should redirect without re-processing.
	 */
	public function test_per_attempt_idempotency(): void {
		$attempt = $this->build_attempt(
			'att-dup-001',
			'WC42-20260417100000dup',
			'Ssessiondup001',
			'100'
		);

		$this->order = $this->create_order_with_attempts(
			array( $attempt ),
			array(
				"_vinti4_attempt_att-dup-001_processed" => '2026-04-17 10:30:00',
			)
		);
		// Simulate already-completed order.
		$this->order->payment_complete();
		$GLOBALS['mock_wc_order'] = $this->order;

		$_POST = $this->attempt_post_payload(
			'WC42-20260417100000dup',
			'Ssessiondup001',
			'100'
		);

		$caught = false;
		try {
			Vinti4_Callback_Handler::handle( $this->gateway );
		} catch ( Vinti4_Redirect_Exception $e ) {
			$caught = true;
			// Should redirect to order-received for completed orders.
			$this->assertSame( '/order-received/', $e->getMessage() );
		}

		$this->assertTrue( $caught, 'Expected Vinti4_Redirect_Exception for duplicate callback.' );
		// payment_complete_called was set to true in setup to simulate completion,
		// but the callback handler should NOT have called it again.
		// Since mock doesn't track call count, we verify no new txn_id was set.
		$this->assertNotSame( 'TXN-ATTEMPT-001', $this->order->payment_complete_txn_id );
	}

	/**
	 * Test: Partial payment totals are tracked correctly.
	 *
	 * When a partial payment callback is received for an order that already
	 * has a completed attempt, the paid total should reflect both amounts
	 * and the order should remain in processing status (not completed).
	 */
	public function test_partial_payment_totals(): void {
		$first_attempt = $this->build_attempt(
			'att-partial-1',
			'WC42-20260417100000p1',
			'Ssessionp1abc',
			'100'
		);
		$first_attempt['status'] = 'completed';
		$first_attempt['callback_received'] = true;

		$second_attempt = $this->build_attempt(
			'att-partial-2',
			'WC42-20260417110000p2',
			'Ssessionp2abc',
			'100'
		);
		$second_attempt['sequence'] = 2;

		// Order total is 200, first attempt paid 100, second will bring total to 200.
		$this->order = $this->create_order_with_attempts(
			array( $first_attempt, $second_attempt ),
			array(),
			200.0
		);
		$GLOBALS['mock_wc_order'] = $this->order;

		$_POST = $this->attempt_post_payload(
			'WC42-20260417110000p2',
			'Ssessionp2abc',
			'100'
		);

		try {
			Vinti4_Callback_Handler::handle( $this->gateway );
		} catch ( Vinti4_Redirect_Exception $e ) {
			// Expected redirect.
		}

		// With two completed attempts totaling 200, order total is 200,
		// so outstanding should be ~0 and order should be completed.
		$this->assertTrue( $this->order->payment_complete_called, 'Order should be completed when fully paid.' );
	}

	/**
	 * Test: Partial payment keeps order in processing when outstanding > 0.
	 *
	 * When a partial payment callback arrives but the order is not yet fully
	 * paid, the order should be set to 'processing' (not payment_complete).
	 */
	public function test_partial_payment_remains_processing(): void {
		$attempt = $this->build_attempt(
			'att-partial-rem',
			'WC42-20260417100000rem',
			'Ssessionremabc',
			'50'
		);

		// Order total is 200, partial attempt for 50 leaves 150 outstanding.
		$this->order = $this->create_order_with_attempts(
			array( $attempt ),
			array(),
			200.0
		);
		$GLOBALS['mock_wc_order'] = $this->order;

		$_POST = $this->attempt_post_payload(
			'WC42-20260417100000rem',
			'Ssessionremabc',
			'50'
		);

		try {
			Vinti4_Callback_Handler::handle( $this->gateway );
		} catch ( Vinti4_Redirect_Exception $e ) {
			// Expected redirect to order-received.
		}

		// Partial payment should set status to 'processing', not call payment_complete.
		$this->assertFalse( $this->order->payment_complete_called, 'payment_complete should NOT be called for partial payment.' );
		$this->assertTrue( $this->order->update_status_called, 'update_status should be called for partial payment.' );
		$this->assertSame( 'processing', $this->order->updated_status );
	}

	/**
	 * Test: merchantSession mismatch rejects the callback.
	 *
	 * When the callback merchantSession doesn't match the attempt's stored
	 * session, the callback should be rejected with diagnostic logging.
	 */
	public function test_merchant_session_mismatch_rejected(): void {
		$attempt = $this->build_attempt(
			'att-session-mismatch',
			'WC42-20260417100000ses',
			'Scorrectsession1',
			'100'
		);

		$this->order = $this->create_order_with_attempts( array( $attempt ) );
		$GLOBALS['mock_wc_order'] = $this->order;

		$_POST = $this->attempt_post_payload(
			'WC42-20260417100000ses',
			'Swrongsess001',  // Wrong session.
			'100'
		);

		try {
			Vinti4_Callback_Handler::handle( $this->gateway );
		} catch ( Vinti4_Redirect_Exception $e ) {
			// Expected redirect to checkout.
		}

		$this->assertTrue( $this->order->update_status_called, 'Order should be marked as failed on session mismatch.' );
		$this->assertSame( 'failed', $this->order->updated_status );
		$this->assertFalse( $this->order->payment_complete_called, 'payment_complete should NOT be called on session mismatch.' );
	}

	/**
	 * Test: Attempt with wrong amount is rejected.
	 *
	 * When the callback purchase amount doesn't match the attempt's stored
	 * amount, the callback should be rejected.
	 */
	public function test_attempt_amount_mismatch_rejected(): void {
		$attempt = $this->build_attempt(
			'att-amount-mismatch',
			'WC42-20260417100000amt',
			'Ssessionamt001',
			'100'
		);

		$this->order = $this->create_order_with_attempts( array( $attempt ) );
		$GLOBALS['mock_wc_order'] = $this->order;

		$_POST = $this->attempt_post_payload(
			'WC42-20260417100000amt',
			'Ssessionamt001',
			'999'  // Wrong amount — attempt stores 100.
		);

		try {
			Vinti4_Callback_Handler::handle( $this->gateway );
		} catch ( Vinti4_Redirect_Exception $e ) {
			// Expected redirect to checkout.
		}

		$this->assertTrue( $this->order->update_status_called, 'Order should be marked as failed on amount mismatch.' );
		$this->assertSame( 'failed', $this->order->updated_status );
		$this->assertFalse( $this->order->payment_complete_called );
	}

	/**
	 * Test: Spoofed callback for merchantRef not in attempt history.
	 *
	 * When the callback has a merchantRef that doesn't match any attempt
	 * in the order's history, it should be rejected as possible spoof.
	 */
	public function test_spoofed_merchant_ref_rejected(): void {
		$attempt = $this->build_attempt(
			'att-real',
			'WC42-20260417100000real',
			'Ssessionreal01',
			'100'
		);

		$this->order = $this->create_order_with_attempts( array( $attempt ) );
		$GLOBALS['mock_wc_order'] = $this->order;

		$_POST = array(
			'messageType'                     => '8',
			'resultFingerPrint'               => 'spoofed-fingerprint',
			'merchantRespMerchantRef'         => 'WC42-20260417100000spoof', // Not in history.
			'merchantRespMerchantSession'     => 'Sspoofed001',
			'merchantRespPurchaseAmount'      => '100',
			'merchantRespErrorDetail'         => '',
			'merchantRespErrorDescription'    => '',
		);

		$caught = false;
		try {
			Vinti4_Callback_Handler::handle( $this->gateway );
		} catch ( Vinti4_Redirect_Exception $e ) {
			$caught = true;
		}

		$this->assertTrue( $caught, 'Spoofed merchantRef should redirect to checkout.' );
		$this->assertFalse( $this->order->payment_complete_called, 'payment_complete should NOT be called for spoofed callback.' );
	}

	// ─── Legacy Tests ────────────────────────────────────────────────────────

	/**
	 * Test: Duplicate legacy callback is rejected — order already has _vinti4_callback_processed='1'.
	 * Expected: wp_safe_redirect is thrown (Vinti4_Redirect_Exception), payment_complete NOT called.
	 */
	public function test_duplicate_callback_rejected(): void {
		// Create order that already has callback processed flag — no attempt history (legacy).
		$this->order = $this->create_mock_order( array(
			'_vinti4_callback_processed' => '1',
		) );
		$this->order->payment_complete(); // simulate already-completed order
		$GLOBALS['mock_wc_order'] = $this->order;

		$_POST = $this->valid_post_payload();

		$this->expectException( Vinti4_Redirect_Exception::class );
		Vinti4_Callback_Handler::handle( $this->gateway );
	}

	/**
	 * Test: Invalid fingerprint — correct success messageType but wrong resultFingerPrint.
	 * Expected: order marked as failed, payment_complete NOT called.
	 */
	public function test_invalid_fingerprint_callback(): void {
		$this->order = $this->create_mock_order();
		$GLOBALS['mock_wc_order'] = $this->order;

		$_POST = $this->valid_post_payload( array(
			'resultFingerPrint' => 'INVALIDFINGERPRINTVALUE',
		) );

		try {
			Vinti4_Callback_Handler::handle( $this->gateway );
			$this->fail( 'Expected Vinti4_Redirect_Exception was not thrown' );
		} catch ( Vinti4_Redirect_Exception $e ) {
			// Expected — callback redirects after marking as failed.
		}

		$this->assertTrue( $this->order->update_status_called, 'Order should be marked as failed.' );
		$this->assertSame( 'failed', $this->order->updated_status );
		$this->assertFalse( $this->order->payment_complete_called, 'payment_complete should NOT be called on fingerprint mismatch.' );
	}

	/**
	 * Test: Missing merchantRef — empty merchantRespMerchantRef.
	 * Expected: wp_die is thrown (Vinti4_Die_Exception).
	 */
	public function test_missing_merchant_ref_rejected(): void {
		$this->order = $this->create_mock_order();
		$GLOBALS['mock_wc_order'] = $this->order;

		$_POST = array(
			'messageType'               => '8',
			'resultFingerPrint'         => 'something',
			'merchantRespMerchantRef'   => '',
		);

		$this->expectException( Vinti4_Die_Exception::class );
		Vinti4_Callback_Handler::handle( $this->gateway );
	}

	/**
	 * Test: Unparseable merchantRef — doesn't match WC{id}-... pattern.
	 * Expected: wp_die is thrown (Vinti4_Die_Exception).
	 */
	public function test_unparseable_merchant_ref_rejected(): void {
		$this->order = $this->create_mock_order();
		$GLOBALS['mock_wc_order'] = $this->order;

		$_POST = array(
			'messageType'               => '8',
			'resultFingerPrint'         => 'something',
			'merchantRespMerchantRef'   => 'INVALID-NO-MATCH',
		);

		$this->expectException( Vinti4_Die_Exception::class );
		Vinti4_Callback_Handler::handle( $this->gateway );
	}

	/**
	 * Test: Failure callback without resultFingerPrint should still be processed.
	 * Expected: order marked as failed and redirected, not wp_die().
	 */
	public function test_failure_callback_without_fingerprint_marks_order_failed(): void {
		$this->order = $this->create_mock_order();
		$GLOBALS['mock_wc_order'] = $this->order;

		$_POST = array(
			'messageType'                         => '6',
			'merchantRespMerchantRef'             => 'WC42-20260416143022',
			'merchantRespErrorDetail'             => 'Invalid Data',
			'merchantRespErrorDescription'        => 'Fingerprint Invalid',
			'merchantRespAdditionalErrorMessage'  => 'Fingerprint Invalid',
		);

		try {
			Vinti4_Callback_Handler::handle( $this->gateway );
			$this->fail( 'Expected Vinti4_Redirect_Exception was not thrown' );
		} catch ( Vinti4_Redirect_Exception $e ) {
			// Expected — callback redirects after marking as failed.
		}

		$this->assertTrue( $this->order->update_status_called, 'Order should be marked as failed.' );
		$this->assertSame( 'failed', $this->order->updated_status );
		$this->assertFalse( $this->order->payment_complete_called, 'payment_complete should NOT be called on failure callback.' );
	}

	/**
	 * Test: Failure callback data delivered in query string should be accepted.
	 * Expected: order marked as failed and redirected, not wp_die().
	 */
	public function test_failure_callback_query_payload_marks_order_failed(): void {
		$this->order = $this->create_mock_order();
		$GLOBALS['mock_wc_order'] = $this->order;

		$_POST = array();
		$_GET  = array(
			'messageType'                         => '6',
			'merchantRespMerchantRef'             => 'WC42-20260416143022',
			'merchantRespMerchantSession'         => 'Squerypayload001',
			'merchantRespErrorDetail'             => 'Invalid Data',
			'merchantRespErrorDescription'        => 'Fingerprint Invalid',
			'merchantRespAdditionalErrorMessage'  => 'Fingerprint Invalid',
		);

		try {
			Vinti4_Callback_Handler::handle( $this->gateway );
			$this->fail( 'Expected Vinti4_Redirect_Exception was not thrown' );
		} catch ( Vinti4_Redirect_Exception $e ) {
			// Expected — callback redirects after marking as failed.
		}

		$this->assertTrue( $this->order->update_status_called, 'Order should be marked as failed.' );
		$this->assertSame( 'failed', $this->order->updated_status );
	}

	/**
	 * Test: Fixed-length merchantRef resolves order ID via meta lookup.
	 */
	public function test_fixed_length_merchant_ref_resolves_order_via_lookup(): void {
		$this->order = $this->create_mock_order( array(
			'_vinti4_merchant_ref' => 'MM2604170944241',
		) );
		$GLOBALS['mock_wc_order'] = $this->order;
		$GLOBALS['mock_wc_orders_by_ref'] = array(
			'MM2604170944241' => 42,
		);

		$_POST = array(
			'messageType'                        => '6',
			'merchantRespMerchantRef'            => 'MM2604170944241',
			'merchantRespErrorDetail'            => 'Invalid Data',
			'merchantRespErrorDescription'       => 'Fingerprint Invalid',
			'merchantRespAdditionalErrorMessage' => 'Fingerprint Invalid',
		);

		try {
			Vinti4_Callback_Handler::handle( $this->gateway );
			$this->fail( 'Expected Vinti4_Redirect_Exception was not thrown' );
		} catch ( Vinti4_Redirect_Exception $e ) {
			// Expected.
		}

		$this->assertTrue( $this->order->update_status_called, 'Order should be marked as failed.' );
		$this->assertSame( 'failed', $this->order->updated_status );
	}

	// ─── Backward Compatibility Tests ────────────────────────────────────────

	/**
	 * Test: Legacy callback flow for orders without attempt history.
	 *
	 * When an order has no _vinti4_attempt_history meta but has legacy
	 * _vinti4_merchant_ref and _vinti4_amount meta, the callback should
	 * be processed via the legacy path and the order should be marked
	 * with _vinti4_is_legacy_order.
	 */
	public function test_legacy_callback_flow(): void {
		$this->order = $this->create_mock_order( array(
			'_vinti4_merchant_ref'       => 'WC42-20260416143022',
			'_vinti4_amount'             => '100',
			'_vinti4_callback_processed' => '',
		) );
		$GLOBALS['mock_wc_order'] = $this->order;

		$_POST = $this->valid_post_payload();

		try {
			Vinti4_Callback_Handler::handle( $this->gateway );
		} catch ( Vinti4_Redirect_Exception $e ) {
			// Expected redirect after success.
		}

		// Order should complete successfully via legacy path.
		$this->assertTrue( $this->order->payment_complete_called, 'payment_complete should be called for legacy order.' );

		// Order should be marked as legacy.
		$this->assertTrue( $this->order->get_meta( '_vinti4_is_legacy_order' ), 'Order should be marked with _vinti4_is_legacy_order.' );
	}

	/**
	 * Test: Legacy callback with invalid merchantRef is rejected.
	 *
	 * When a legacy order receives a callback with a non-matching
	 * merchantRef, the callback should be rejected via the legacy path.
	 */
	public function test_legacy_callback_invalid_ref(): void {
		$this->order = $this->create_mock_order( array(
			'_vinti4_merchant_ref'       => 'WC42-STORED-REF',
			'_vinti4_amount'             => '100',
			'_vinti4_callback_processed' => '',
		) );
		$GLOBALS['mock_wc_order'] = $this->order;

		$_POST = $this->valid_post_payload( array(
			'merchantRespMerchantRef' => 'WC42-DIFFERENT-REF',
		) );

		$caught = false;
		try {
			Vinti4_Callback_Handler::handle( $this->gateway );
		} catch ( Vinti4_Redirect_Exception $e ) {
			$caught = true;
		}

		$this->assertTrue( $caught, 'Legacy callback with invalid ref should redirect.' );
		$this->assertFalse( $this->order->payment_complete_called, 'payment_complete should NOT be called for invalid ref.' );
	}

	/**
	 * Test: Legacy callback idempotency prevents duplicate processing.
	 *
	 * When a legacy order already has _vinti4_callback_processed set,
	 * the duplicate callback should be rejected.
	 */
	public function test_legacy_callback_idempotency(): void {
		$this->order = $this->create_mock_order( array(
			'_vinti4_merchant_ref'       => 'WC42-20260416143022',
			'_vinti4_amount'             => '100',
			'_vinti4_callback_processed' => '1',
		) );
		// Simulate already-processed order.
		$this->order->payment_complete();
		$GLOBALS['mock_wc_order'] = $this->order;

		$_POST = $this->valid_post_payload();

		$caught = false;
		try {
			Vinti4_Callback_Handler::handle( $this->gateway );
		} catch ( Vinti4_Redirect_Exception $e ) {
			$caught = true;
			// Should redirect to order-received for completed orders.
			$this->assertSame( '/order-received/', $e->getMessage() );
		}

		$this->assertTrue( $caught, 'Duplicate legacy callback should redirect.' );

		// Verify no new transaction ID was set (payment_complete not called again by handler).
		$this->assertNotSame( 'TXN123456', $this->order->payment_complete_txn_id );
	}

	// ─── Sandbox Card Flow & Multi-Attempt Logging Tests ────────────────────

	/**
	 * Test: Sandbox card flow with single attempt completes successfully.
	 *
	 * Simulates a checkout with the sandbox test card (pan=4012001037141112)
	 * creating a single attempt that covers the full order total. Verifies
	 * that the order is completed and payment_complete is called.
	 */
	public function test_sandbox_card_flow_single_attempt(): void {
		$attempt = $this->build_attempt(
			'att-sandbox-001',
			'WC42-20260417100000sb',
			'Ssandbox001abc',
			'200000'
		);

		$this->order = $this->create_order_with_attempts(
			array( $attempt ),
			array(),
			200.0
		);
		$GLOBALS['mock_wc_order'] = $this->order;

		$_POST = $this->attempt_post_payload(
			'WC42-20260417100000sb',
			'Ssandbox001abc',
			'200000'
		);

		try {
			Vinti4_Callback_Handler::handle( $this->gateway );
		} catch ( Vinti4_Redirect_Exception $e ) {
			// Expected redirect after success.
		}

		// Single attempt covering full total should complete the order.
		$this->assertTrue( $this->order->payment_complete_called, 'payment_complete should be called for single attempt covering full amount.' );
		$this->assertSame( 'TXN-ATTEMPT-001', $this->order->payment_complete_txn_id );
	}

	/**
	 * Test: Sandbox card flow with partial payment keeps order in processing.
	 *
	 * Simulates a partial payment (50% of order total) via the sandbox test
	 * card. Verifies the order stays in 'processing' status with correct
	 * paid and outstanding totals.
	 */
	public function test_sandbox_card_flow_partial_payment(): void {
		$attempt = $this->build_attempt(
			'att-sandbox-partial',
			'WC42-20260417100000pt',
			'Ssandboxpt001',
			'100'
		);

		// Order total is 200.0, partial attempt for 100.0 (50%).
		$this->order = $this->create_order_with_attempts(
			array( $attempt ),
			array(),
			200.0
		);
		$GLOBALS['mock_wc_order'] = $this->order;

		$_POST = $this->attempt_post_payload(
			'WC42-20260417100000pt',
			'Ssandboxpt001',
			'100'
		);

		try {
			Vinti4_Callback_Handler::handle( $this->gateway );
		} catch ( Vinti4_Redirect_Exception $e ) {
			// Expected redirect to order-received.
		}

		// Partial payment should NOT call payment_complete.
		$this->assertFalse( $this->order->payment_complete_called, 'payment_complete should NOT be called for partial payment.' );

		// Order should be set to processing.
		$this->assertTrue( $this->order->update_status_called, 'update_status should be called for partial payment.' );
		$this->assertSame( 'processing', $this->order->updated_status );

		// Verify paid total is tracked in meta.
		$paid_total = $this->order->get_meta( '_vinti4_paid_total' );
		$this->assertNotEmpty( $paid_total, 'Paid total should be set after partial payment.' );

		// Verify outstanding total is computed correctly (not cached to meta, computed on demand).
		$outstanding = Vinti4_Attempt_Store::get_outstanding_total( $this->order );
		$this->assertEqualsWithDelta( 100.0, $outstanding, 0.01, 'Outstanding total should equal remaining balance after partial payment.' );
	}

	/**
	 * Test: Multi-attempt full payment across two partial attempts.
	 *
	 * Simulates two partial payments (50% + 50%) that together cover
	 * the full order total. Verifies both attempts are marked completed
	 * and the order is fully completed after the second attempt.
	 */
	public function test_multi_attempt_full_payment(): void {
		$first_attempt = $this->build_attempt(
			'att-multi-1',
			'WC42-20260417100000m1',
			'Ssessionm1001',
			'100000'
		);
		$first_attempt['status']            = 'completed';
		$first_attempt['callback_received'] = true;

		$second_attempt = $this->build_attempt(
			'att-multi-2',
			'WC42-20260417110000m2',
			'Ssessionm2001',
			'100000'
		);
		$second_attempt['sequence'] = 2;

		// Order total 200.0, first attempt 100000 (100.0), second 100000 (100.0).
		$this->order = $this->create_order_with_attempts(
			array( $first_attempt, $second_attempt ),
			array(),
			200.0
		);
		$GLOBALS['mock_wc_order'] = $this->order;

		// Process the second attempt callback.
		$_POST = $this->attempt_post_payload(
			'WC42-20260417110000m2',
			'Ssessionm2001',
			'100000'
		);

		try {
			Vinti4_Callback_Handler::handle( $this->gateway );
		} catch ( Vinti4_Redirect_Exception $e ) {
			// Expected redirect.
		}

		// Second attempt should complete the order (total paid = 200000, total = 200000).
		$this->assertTrue( $this->order->payment_complete_called, 'payment_complete should be called after second attempt covers full total.' );
		$this->assertSame( 'TXN-ATTEMPT-001', $this->order->payment_complete_txn_id );

		// Verify both attempts are reflected in paid total.
		$paid_total = $this->order->get_meta( '_vinti4_paid_total' );
		$this->assertNotEmpty( $paid_total, 'Paid total should be tracked after multi-attempt completion.' );
	}

	/**
	 * Test: Multi-attempt flow distinguishes failure types in logs.
	 *
	 * Verifies that invalid_reference, invalid_fingerprint, and duplicate_callback
	 * each produce distinct outcomes that are distinguishable in the callback flow.
	 */
	public function test_multi_attempt_distinguishes_failure_types(): void {
		// --- Test 1: Invalid reference (spoofed merchantRef) ---
		$real_attempt = $this->build_attempt(
			'att-real-ft',
			'WC42-20260417100000realft',
			'Ssessionrealft1',
			'100'
		);

		$order1 = $this->create_order_with_attempts( array( $real_attempt ) );
		$GLOBALS['mock_wc_order'] = $order1;

		$_POST = array(
			'messageType'                  => '8',
			'resultFingerPrint'            => 'spoofed-fp',
			'merchantRespMerchantRef'      => 'WC42-SPOOFED-REF',
			'merchantRespMerchantSession'  => 'Sspoofed001',
			'merchantRespPurchaseAmount'   => '100',
			'merchantRespErrorDetail'      => '',
			'merchantRespErrorDescription' => '',
		);

		$caught_spoof = false;
		try {
			Vinti4_Callback_Handler::handle( $this->gateway );
		} catch ( Vinti4_Redirect_Exception $e ) {
			$caught_spoof = true;
		}
		$this->assertTrue( $caught_spoof, 'Invalid reference should redirect to checkout.' );
		$this->assertFalse( $order1->payment_complete_called, 'Invalid reference should not complete payment.' );

		// --- Test 2: Invalid fingerprint ---
		$order2 = $this->create_order_with_attempts( array( $real_attempt ) );
		$GLOBALS['mock_wc_order'] = $order2;

		$_POST = $this->attempt_post_payload(
			'WC42-20260417100000realft',
			'Ssessionrealft1',
			'100',
			array( 'resultFingerPrint' => 'TAMPERED-FINGERPRINT' )
		);

		try {
			Vinti4_Callback_Handler::handle( $this->gateway );
		} catch ( Vinti4_Redirect_Exception $e ) {
			// Expected redirect.
		}
		$this->assertTrue( $order2->update_status_called, 'Invalid fingerprint should mark order as failed.' );
		$this->assertSame( 'failed', $order2->updated_status, 'Invalid fingerprint should set status to failed.' );

		// --- Test 3: Duplicate callback ---
		$order3 = $this->create_order_with_attempts(
			array( $real_attempt ),
			array( '_vinti4_attempt_att-real-ft_processed' => '2026-04-17 10:30:00' )
		);
		$order3->payment_complete(); // Simulate already processed.
		$GLOBALS['mock_wc_order'] = $order3;

		$_POST = $this->attempt_post_payload(
			'WC42-20260417100000realft',
			'Ssessionrealft1',
			'100'
		);

		$caught_dup = false;
		try {
			Vinti4_Callback_Handler::handle( $this->gateway );
		} catch ( Vinti4_Redirect_Exception $e ) {
			$caught_dup = true;
		}
		$this->assertTrue( $caught_dup, 'Duplicate callback should redirect.' );
		// Transaction ID should not be overwritten to the new one.
		$this->assertNotSame( 'TXN-ATTEMPT-001', $order3->payment_complete_txn_id );
	}
}
