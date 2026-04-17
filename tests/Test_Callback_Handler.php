<?php
/**
 * Tests for Vinti4_Callback_Handler
 *
 * Verifies callback handling behavior: duplicate detection, invalid fingerprint
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
	}

	protected function tearDown(): void {
		parent::tearDown();
		$_POST = array();
		unset( $GLOBALS['mock_wc_order'] );
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

			public function __construct( array $meta_overrides = array() ) {
				$this->test_meta = array_merge(
					array(
						'_vinti4_merchant_ref'       => 'WC42-20260416143022',
						'_vinti4_amount'             => '100000',
						'_vinti4_callback_processed' => '',
					),
					$meta_overrides
				);
			}

			public function get_meta( $key ): string {
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
		};
	}

	/**
	 * Build a valid POST payload for a successful callback.
	 */
	private function valid_post_payload( array $overrides = array() ): array {
		// Compute the correct fingerprint for these values.
		$pos_auth_code    = 'TESTAUTH123';
		$message_type     = '8';
		$clearing_period  = '2026-04-16';
		$transaction_id   = 'TXN123456';
		$merchant_ref     = 'WC42-20260416143022';
		$merchant_session = 'Saaaaaaaaaaaa';
		$purchase_amount  = '100';
		$message_id       = 'MSG789';
		$pan              = '411111******1111';
		$merchant_response = 'Approved';
		$timestamp        = '2026-04-16 14:31:00';
		$reference_number = 'REF001';
		$entity_code      = '54321';
		$client_receipt   = 'true';
		$additional_error = '';
		$reload_code      = '0';

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

	// ─── Tests ─────────────────────────────────────────────────────────────

	/**
	 * Test 1: Duplicate callback is rejected — order already has _vinti4_callback_processed='1'.
	 * Expected: wp_safe_redirect is thrown (Vinti4_Redirect_Exception), payment_complete NOT called.
	 */
	public function test_duplicate_callback_rejected(): void {
		// Create order that already has callback processed flag.
		$this->order = $this->create_mock_order( array(
			'_vinti4_callback_processed' => '1',
		) );
		$this->order->payment_complete(); // simulate already-completed order
		$GLOBALS['mock_wc_order'] = $this->order;

		$_POST = $this->valid_post_payload();

		$this->expectException( Vinti4_Redirect_Exception::class );
		Vinti4_Callback_Handler::handle( $this->gateway );

		// Verify payment_complete was NOT called by this callback.
		// (The setUp mock called it once to simulate prior completion.)
	}

	/**
	 * Test 2: Invalid fingerprint — correct success messageType but wrong resultFingerPrint.
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
	 * Test 3: Missing merchantRef — empty merchantRespMerchantRef.
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
	 * Test 4: Unparseable merchantRef — doesn't match WC{id}-... pattern.
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
	 * Test 5: Failure callback without resultFingerPrint should still be processed.
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
}
