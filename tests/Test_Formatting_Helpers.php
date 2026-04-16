<?php
/**
 * Tests for Vinti4 formatting helper functions
 *
 * Verifies amount normalization, order ID parsing from merchant references,
 * phone number shaping, and success message type detection.
 *
 * @since 1.0.0
 */

use PHPUnit\Framework\TestCase;

class Test_Formatting_Helpers extends TestCase {

	// ─── vinti4_normalize_amount ────────────────────────────────────────────

	/**
	 * 1500.49 rounds to 1500 (rounds down).
	 */
	public function test_normalize_amount_rounds_down(): void {
		$this->assertSame( 1500, vinti4_normalize_amount( 1500.49 ) );
	}

	/**
	 * 1500.5 rounds to 1501 (rounds half up — PHP default).
	 */
	public function test_normalize_amount_rounds_half_up(): void {
		$this->assertSame( 1501, vinti4_normalize_amount( 1500.5 ) );
	}

	/**
	 * Negative amounts become positive via absint.
	 */
	public function test_normalize_amount_negative(): void {
		$this->assertSame( 100, vinti4_normalize_amount( -100.0 ) );
	}

	/**
	 * Zero stays zero.
	 */
	public function test_normalize_amount_zero(): void {
		$this->assertSame( 0, vinti4_normalize_amount( 0.0 ) );
	}

	// ─── vinti4_parse_order_id_from_ref ────────────────────────────────────

	/**
	 * Valid merchant reference extracts order ID correctly.
	 */
	public function test_parse_order_id_valid(): void {
		$this->assertSame( 42, vinti4_parse_order_id_from_ref( 'WC42-20260416143022' ) );
	}

	/**
	 * Invalid format returns 0.
	 */
	public function test_parse_order_id_invalid(): void {
		$this->assertSame( 0, vinti4_parse_order_id_from_ref( 'INVALID' ) );
	}

	/**
	 * Empty string returns 0.
	 */
	public function test_parse_order_id_empty(): void {
		$this->assertSame( 0, vinti4_parse_order_id_from_ref( '' ) );
	}

	// ─── vinti4_shape_phone ────────────────────────────────────────────────

	/**
	 * International phone number splits into cc and subscriber.
	 * '+238 991 234 567' → digits: '238991234567' (12 digits)
	 * subscriber: last 9 = '991234567', cc: ltrim('238', '0') = '238'
	 */
	public function test_shape_phone_international(): void {
		$result = vinti4_shape_phone( '+238 991 234 567' );
		$this->assertSame( '238', $result['cc'] );
		$this->assertSame( '991234567', $result['subscriber'] );
	}

	/**
	 * Short phone number gets empty country code.
	 * '12345' (5 digits < 9) → cc: '', subscriber: '12345'
	 */
	public function test_shape_phone_short(): void {
		$result = vinti4_shape_phone( '12345' );
		$this->assertSame( '', $result['cc'] );
		$this->assertSame( '12345', $result['subscriber'] );
	}

	/**
	 * Phone with leading zeros has them stripped from country code.
	 * '00238991234567' → digits: '00238991234567' (14 digits)
	 * subscriber: last 9 = '991234567', cc: ltrim('00238', '0') = '238'
	 */
	public function test_shape_phone_with_leading_zeros(): void {
		$result = vinti4_shape_phone( '00238991234567' );
		$this->assertSame( '238', $result['cc'] );
		$this->assertSame( '991234567', $result['subscriber'] );
	}

	// ─── vinti4_is_success_message_type ────────────────────────────────────

	/**
	 * All valid success message types return true.
	 */
	public function test_is_success_message_type_true(): void {
		$this->assertTrue( vinti4_is_success_message_type( '8' ) );
		$this->assertTrue( vinti4_is_success_message_type( '10' ) );
		$this->assertTrue( vinti4_is_success_message_type( 'M' ) );
		$this->assertTrue( vinti4_is_success_message_type( 'P' ) );
	}

	/**
	 * Invalid success message types return false.
	 * Uses strict comparison — integer 8 must not match string '8'.
	 */
	public function test_is_success_message_type_false(): void {
		$this->assertFalse( vinti4_is_success_message_type( '0' ) );
		$this->assertFalse( vinti4_is_success_message_type( '1' ) );
		$this->assertFalse( vinti4_is_success_message_type( 'N' ) );
		$this->assertFalse( vinti4_is_success_message_type( '' ) );
		$this->assertFalse( vinti4_is_success_message_type( '8.0' ) );
	}
}
