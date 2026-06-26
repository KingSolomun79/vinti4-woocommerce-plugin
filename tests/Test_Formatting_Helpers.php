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

	/**
	 * Fixed-length merchant refs do not embed order IDs.
	 */
	public function test_parse_order_id_fixed_length_ref_returns_zero(): void {
		$this->assertSame( 0, vinti4_parse_order_id_from_ref( 'MM2604170944241' ) );
	}

	/**
	 * merchantRef is generated as 15 chars with MM prefix.
	 */
	public function test_build_merchant_ref_has_mm_prefix_and_fixed_length(): void {
		$ref = vinti4_build_merchant_ref( 42 );

		$this->assertSame( 15, strlen( $ref ) );
		$this->assertSame( 'MM', substr( $ref, 0, 2 ) );
	}

	/**
	 * merchantSession is generated as 15 chars with MS prefix.
	 */
	public function test_build_merchant_session_has_ms_prefix_and_fixed_length(): void {
		$session = vinti4_build_merchant_session();

		$this->assertSame( 15, strlen( $session ) );
		$this->assertSame( 'MS', substr( $session, 0, 2 ) );
	}

	/**
	 * Order lookup by merchantRef resolves when wc_get_orders can find a match.
	 */
	public function test_find_order_id_by_merchant_ref_uses_meta_lookup(): void {
		$GLOBALS['mock_wc_orders_by_ref'] = array(
			'MM2604170944241' => 819,
		);

		$this->assertSame( 819, vinti4_find_order_id_by_merchant_ref( 'MM2604170944241' ) );
		$this->assertSame( 0, vinti4_find_order_id_by_merchant_ref( 'MM2604170944242' ) );

		unset( $GLOBALS['mock_wc_orders_by_ref'] );
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

	// ─── vinti4_config_value ─────────────────────────────────────────────────

	public function test_config_value_constant_has_priority(): void {
		define( 'VINTI4_TEST_CONST_A', 'from_constant' );
		putenv( 'VINTI4_TEST_CONST_A=from_env' );

		$this->assertSame( 'from_constant', vinti4_config_value( 'VINTI4_TEST_CONST_A', 'default' ) );
	}

	public function test_config_value_env_fallback(): void {
		putenv( 'VINTI4_TEST_CONST_B=from_env' );

		$this->assertSame( 'from_env', vinti4_config_value( 'VINTI4_TEST_CONST_B', 'default' ) );
	}

	public function test_config_value_default(): void {
		$this->assertSame( 'default', vinti4_config_value( 'VINTI4_TEST_CONST_C', 'default' ) );
	}

	// ─── vinti4_shape_phone_with_fallback ────────────────────────────────────

	public function test_shape_phone_with_fallback_woo_overrides(): void {
		$result = vinti4_shape_phone_with_fallback( '+238 991 23 45', 'CV' );
		$this->assertSame( '238', $result['cc'] );
		$this->assertSame( '9912345', $result['subscriber'] );
	}

	public function test_shape_phone_with_fallback_missing_cc_uses_default(): void {
		putenv( 'VINTI4_DEFAULT_PHONE_CC=238' );
		
		$result = vinti4_shape_phone_with_fallback( '12345', 'CV' );
		$this->assertSame( '238', $result['cc'] );
		$this->assertSame( '12345', $result['subscriber'] );
	}

	public function test_shape_phone_with_fallback_empty_phone_uses_both_defaults(): void {
		putenv( 'VINTI4_DEFAULT_PHONE_CC=238' );
		putenv( 'VINTI4_DEFAULT_PHONE_SUBSCRIBER=9884189' );

		$result = vinti4_shape_phone_with_fallback( '', '' );
		$this->assertSame( '238', $result['cc'] );
		$this->assertSame( '9884189', $result['subscriber'] );
	}
}
