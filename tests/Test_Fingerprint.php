<?php
/**
 * Tests for Vinti4_Fingerprint class
 *
 * Verifies SHA-512 + Base64 fingerprint computation for request and response.
 * Uses pre-computed fixture values derived from raw hash() + base64_encode().
 *
 * @since 1.0.0
 */

use PHPUnit\Framework\TestCase;

class Test_Fingerprint extends TestCase {

	/**
	 * Test the sha512_base64 primitive directly.
	 */
	public function test_sha512_base64(): void {
		$expected = base64_encode( hash( 'sha512', 'hello', true ) );
		$this->assertSame( $expected, Vinti4_Fingerprint::sha512_base64( 'hello' ) );
	}

	/**
	 * Test request fingerprint with no optional fields.
	 */
	public function test_request_fingerprint_basic(): void {
		$vectors = require __DIR__ . '/fixtures/fingerprint-request.php';
		$v       = $vectors['request_basic'];

		$result = Vinti4_Fingerprint::build_request_fingerprint(
			$v['pos_auth_code'],
			$v['timestamp'],
			$v['amount'],
			$v['merchant_ref'],
			$v['merchant_session'],
			$v['pos_id'],
			$v['currency'],
			$v['transaction_code']
		);

		$this->assertSame( $v['expected'], $result );
	}

	/**
	 * Test request fingerprint with optional entity_code, reference_number, and token.
	 */
	public function test_request_fingerprint_with_optional_fields(): void {
		$vectors = require __DIR__ . '/fixtures/fingerprint-request.php';
		$v       = $vectors['request_with_optional'];

		$result = Vinti4_Fingerprint::build_request_fingerprint(
			$v['pos_auth_code'],
			$v['timestamp'],
			$v['amount'],
			$v['merchant_ref'],
			$v['merchant_session'],
			$v['pos_id'],
			$v['currency'],
			$v['transaction_code'],
			1000,
			$v['entity_code'],
			$v['reference_number'],
			$v['token']
		);

		$this->assertSame( $v['expected'], $result );
	}

	/**
	 * Test that entity_code with leading zeros is stripped correctly.
	 * '00042' should be treated as '42' in the fingerprint base string.
	 */
	public function test_request_fingerprint_leading_zero_entity(): void {
		$vectors = require __DIR__ . '/fixtures/fingerprint-request.php';
		$v       = $vectors['request_leading_zero_entity'];

		$result = Vinti4_Fingerprint::build_request_fingerprint(
			$v['pos_auth_code'],
			$v['timestamp'],
			$v['amount'],
			$v['merchant_ref'],
			$v['merchant_session'],
			$v['pos_id'],
			$v['currency'],
			$v['transaction_code'],
			1000,
			$v['entity_code']
		);

		$this->assertSame( $v['expected'], $result );
	}

	/**
	 * Test response fingerprint computation.
	 */
	public function test_response_fingerprint(): void {
		$vectors = require __DIR__ . '/fixtures/fingerprint-response.php';
		$v       = $vectors['response_success'];

		$result = Vinti4_Fingerprint::build_response_fingerprint(
			$v['pos_auth_code'],
			$v['message_type'],
			$v['clearing_period'],
			$v['transaction_id'],
			$v['merchant_ref'],
			$v['merchant_session'],
			$v['purchase_amount'],
			$v['message_id'],
			$v['pan'],
			$v['merchant_response'],
			$v['timestamp'],
			$v['reference_number'],
			$v['entity_code'],
			$v['client_receipt'],
			$v['additional_error_message'],
			$v['reload_code']
		);

		$this->assertSame( $v['expected'], $result );
	}

	/**
	 * Test that changing the amount produces a different fingerprint.
	 */
	public function test_fingerprint_changes_with_amount(): void {
		$vectors = require __DIR__ . '/fixtures/fingerprint-request.php';
		$v       = $vectors['request_basic'];

		$fingerprint_100 = Vinti4_Fingerprint::build_request_fingerprint(
			$v['pos_auth_code'],
			$v['timestamp'],
			$v['amount'],
			$v['merchant_ref'],
			$v['merchant_session'],
			$v['pos_id'],
			$v['currency'],
			$v['transaction_code']
		);

		$fingerprint_200 = Vinti4_Fingerprint::build_request_fingerprint(
			$v['pos_auth_code'],
			$v['timestamp'],
			'200',
			$v['merchant_ref'],
			$v['merchant_session'],
			$v['pos_id'],
			$v['currency'],
			$v['transaction_code']
		);

		$this->assertNotSame( $fingerprint_100, $fingerprint_200 );
	}

	/**
	 * Test debug snapshot emits safe canonical request fields.
	 */
	public function test_request_fingerprint_debug_snapshot_is_safe_and_structured(): void {
		$snapshot = Vinti4_Fingerprint::build_request_fingerprint_debug_snapshot(
			'SECRET-AUTH-CODE-123',
			'2026-04-17 08:15:11',
			'422',
			'WC817-20260417081511',
			'ShtUNgPR7H6z6',
			'90000414',
			'132',
			'1'
		);

		$this->assertSame( '1000', $snapshot['fields']['amount']['multiplier'] );
		$this->assertSame( '422000', $snapshot['fields']['amount']['normalized'] );
		$this->assertSame( 'WC817-20260417081511', $snapshot['fields']['merchantRef'] );
		$this->assertSame( 8, $snapshot['segment_count'] );
		$this->assertFalse( $snapshot['sensitive']['posAuthCode']['raw_exposed'] );
		$this->assertStringEndsWith( '...', $snapshot['sensitive']['posAuthCode']['sha512_b64_preview'] );
		$this->assertSame( '[SHA512_B64(posAuthCode)]', $snapshot['ordered_segments'][0] );
	}

	/**
	 * Test request fingerprint supports explicit amount scale overrides.
	 */
	public function test_request_fingerprint_changes_with_amount_scale_override(): void {
		$fingerprint_scale_1000 = Vinti4_Fingerprint::build_request_fingerprint(
			'TESTAUTH123',
			'2026-04-17 08:15:11',
			'422',
			'WC817-20260417081511',
			'ShtUNgPR7H6z6',
			'90000414',
			'132',
			'1',
			1000
		);

		$fingerprint_scale_1 = Vinti4_Fingerprint::build_request_fingerprint(
			'TESTAUTH123',
			'2026-04-17 08:15:11',
			'422',
			'WC817-20260417081511',
			'ShtUNgPR7H6z6',
			'90000414',
			'132',
			'1',
			1
		);

		$this->assertNotSame( $fingerprint_scale_1000, $fingerprint_scale_1 );
	}
}
