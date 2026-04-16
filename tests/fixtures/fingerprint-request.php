<?php
/**
 * Fingerprint Request Test Vectors
 *
 * Expected values computed using raw PHP hash() + base64_encode()
 * to avoid circular testing with the Vinti4_Fingerprint class.
 *
 * @since 1.0.0
 */

return array(

	// Vector 1: Basic request — no optional fields (entity_code, reference_number, token all empty).
	'request_basic' => array(
		'pos_auth_code'    => 'TESTAUTH123',
		'timestamp'        => '2026-04-16 14:30:22',
		'amount'           => '100',
		'merchant_ref'     => 'WC42-20260416143022',
		'merchant_session' => 'Saaaaaaaaaaaa',
		'pos_id'           => '900001',
		'currency'         => '132',
		'transaction_code' => '1',
		'entity_code'      => '',
		'reference_number' => '',
		'token'            => '',
		'expected'         => 'TCaMUTQQopfDrCvexenSY27lStvC2gUdJZqzoOMHLMgqJcdCDb41VvPrvlawHwb9JsvInKzrdg4Y+P4ZPaRJCw==',
	),

	// Vector 2: Request with all optional fields populated.
	'request_with_optional' => array(
		'pos_auth_code'    => 'TESTAUTH123',
		'timestamp'        => '2026-04-16 14:30:22',
		'amount'           => '100',
		'merchant_ref'     => 'WC42-20260416143022',
		'merchant_session' => 'Saaaaaaaaaaaa',
		'pos_id'           => '900001',
		'currency'         => '132',
		'transaction_code' => '1',
		'entity_code'      => '54321',
		'reference_number' => '67890',
		'token'            => 'tokenXYZ',
		'expected'         => 'I7rohlkr9G8l13uBYA2hLskt2JHi9Uthb1YExjV+gALmRmTNPX5h/E+82Z+SUIbz7WtOoTnaszBVXDcXwHzzoQ==',
	),

	// Vector 3: Entity code with leading zeros — stripped to "42".
	'request_leading_zero_entity' => array(
		'pos_auth_code'    => 'TESTAUTH123',
		'timestamp'        => '2026-04-16 14:30:22',
		'amount'           => '100',
		'merchant_ref'     => 'WC42-20260416143022',
		'merchant_session' => 'Saaaaaaaaaaaa',
		'pos_id'           => '900001',
		'currency'         => '132',
		'transaction_code' => '1',
		'entity_code'      => '00042',
		'reference_number' => '',
		'token'            => '',
		'expected'         => 'uOWhz42B4gANT1BkguDme0A+ILxEYe3dAqs9IfcGQtdWNx5t7yN3NJqbHp8OtWn2Z7JW041ypcV92WIx8QnPZg==',
	),
);
