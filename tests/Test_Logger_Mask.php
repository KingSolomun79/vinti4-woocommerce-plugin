<?php
/**
 * Tests for Vinti4_Logger::mask_auth_code()
 *
 * Verifies that the POS auth code is partially masked for safe logging,
 * never revealing the full value.
 *
 * @since 1.0.0
 */

use PHPUnit\Framework\TestCase;

class Test_Logger_Mask extends TestCase {

	/**
	 * Long code: first 3 + asterisks + last 2.
	 * 'ABCDEFGHYZ' (10 chars) → str_repeat('*', 10-5) = 5 asterisks → 'ABC*****YZ'
	 */
	public function test_mask_long_code(): void {
		$this->assertSame( 'ABC*****YZ', Vinti4_Logger::mask_auth_code( 'ABCDEFGHYZ' ) );
	}

	/**
	 * Short code (< 6 chars): first char + asterisks for remaining.
	 * 'Abcd' (4 chars) → 'A***'
	 */
	public function test_mask_short_code(): void {
		$this->assertSame( 'A***', Vinti4_Logger::mask_auth_code( 'Abcd' ) );
	}

	/**
	 * Empty string returns empty string.
	 */
	public function test_mask_empty(): void {
		$this->assertSame( '', Vinti4_Logger::mask_auth_code( '' ) );
	}

	/**
	 * Exactly 6 chars: first 3 + 1 asterisk + last 2.
	 * 'ABCDEF' → 'ABC*EF'
	 */
	public function test_mask_six_chars(): void {
		$this->assertSame( 'ABC*EF', Vinti4_Logger::mask_auth_code( 'ABCDEF' ) );
	}

	/**
	 * Special characters are preserved in the visible portions.
	 * 'AB%CD=12' (8 chars) → 'AB%***12'
	 */
	public function test_mask_special_chars(): void {
		$this->assertSame( 'AB%***12', Vinti4_Logger::mask_auth_code( 'AB%CD=12' ) );
	}
}
