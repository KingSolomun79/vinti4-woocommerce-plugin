<?php
/**
 * Fingerprint Response Test Vectors
 *
 * Expected values computed using raw PHP hash() + base64_encode()
 * to avoid circular testing with the Vinti4_Fingerprint class.
 *
 * @since 1.0.0
 */

return array(

	// Vector 1: Successful payment callback response with all fields populated.
	'response_success' => array(
		'pos_auth_code'             => 'TESTAUTH123',
		'message_type'              => '8',
		'clearing_period'           => '2026-04-16',
		'transaction_id'            => 'TXN123456',
		'merchant_ref'              => 'WC42-20260416143022',
		'merchant_session'          => 'Saaaaaaaaaaaa',
		'purchase_amount'           => '100',
		'message_id'                => 'MSG789',
		'pan'                       => '411111******1111',
		'merchant_response'         => 'Approved',
		'timestamp'                 => '2026-04-16 14:31:00',
		'reference_number'          => 'REF001',
		'entity_code'               => '54321',
		'client_receipt'            => 'true',
		'additional_error_message'  => '',
		'reload_code'               => '0',
		'expected'                  => 'YpCvqRRmoyFeid3rtzH835bNZVr2O0D5kGn4MtRsXYZTLsxREVfmy5V2v8AtfpTDJ26KfLwbN68p73ki0JI/Aw==',
	),
);
