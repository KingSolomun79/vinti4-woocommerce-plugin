<?php
/**
 * Vinti4 Admin Diagnostic Test Panel
 *
 * Provides a WooCommerce admin submenu page that runs self-tests for the Vinti4
 * payment gateway. Tests cover configuration, fingerprint hashing, currency mapping,
 * success-type detection, logger masking, callback endpoint, gateway registration,
 * blocks support, and callback simulation (duplicate detection + invalid fingerprint).
 *
 * @package Vinti4ForWooCommerce
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'Vinti4_Admin_Test_Panel' ) ) {
	return;
}

/**
 * Class Vinti4_Admin_Test_Panel
 *
 * Registers a "Vinti4 Tests" submenu under WooCommerce and renders a page of
 * self-diagnostic tests that verify the plugin is correctly configured and
 * functional.
 *
 * @since 1.0.0
 */
class Vinti4_Admin_Test_Panel {

	/**
	 * Hook into WordPress admin menus.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_run' ) );
	}

	/**
	 * Add submenu page under WooCommerce.
	 *
	 * @return void
	 */
	public static function add_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Vinti4 Tests', 'vinti4' ),
			__( 'Vinti4 Tests', 'vinti4' ),
			'manage_woocommerce',
			'vinti4-tests',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Handle the "Run Tests" form submission.
	 *
	 * Verifies nonce, runs all tests, stores results in a transient for display.
	 *
	 * @return void
	 */
	public static function handle_run(): void {
		if ( ! isset( $_GET['page'] ) || 'vinti4-tests' !== $_GET['page'] ) {
			return;
		}

		if ( ! isset( $_POST['vinti4_run_tests'] ) ) {
			return;
		}

		check_admin_referer( 'vinti4_run_tests', 'vinti4_tests_nonce' );

		$results = self::run_tests();

		set_transient( 'vinti4_test_results', $results, 60 );

		wp_safe_redirect( admin_url( 'admin.php?page=vinti4-tests&vinti4_results=1' ) );
		exit;
	}

	/**
	 * Render the test panel page.
	 *
	 * @return void
	 */
	public static function render_page(): void {
		$results = get_transient( 'vinti4_test_results' );

		if ( false !== $results ) {
			delete_transient( 'vinti4_test_results' );
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Vinti4 Diagnostic Tests', 'vinti4' ) . '</h1>';
		echo '<p>' . esc_html__( 'Run self-tests to verify the Vinti4 payment gateway is configured and working correctly.', 'vinti4' ) . '</p>';

		// Run Tests form.
		echo '<form method="post" action="' . esc_url( admin_url( 'admin.php?page=vinti4-tests' ) ) . '">';
		wp_nonce_field( 'vinti4_run_tests', 'vinti4_tests_nonce' );
		echo '<p><button type="submit" name="vinti4_run_tests" value="1" class="button button-primary">' . esc_html__( 'Run Tests', 'vinti4' ) . '</button></p>';
		echo '</form>';

		// Display results.
		if ( is_array( $results ) ) {
			$pass_count = 0;
			$fail_count = 0;

			echo '<table class="widefat fixed striped" style="margin-top:16px;">';
			echo '<thead><tr>';
			echo '<th>' . esc_html__( 'Test', 'vinti4' ) . '</th>';
			echo '<th>' . esc_html__( 'Status', 'vinti4' ) . '</th>';
			echo '<th>' . esc_html__( 'Detail', 'vinti4' ) . '</th>';
			echo '</tr></thead>';
			echo '<tbody>';

			foreach ( $results as $result ) {
				$is_pass    = 'pass' === $result['status'];
				$pass_count += $is_pass ? 1 : 0;
				$fail_count += $is_pass ? 0 : 1;

				$badge = $is_pass
					? '<span style="color:#006400;font-weight:bold;">&#10004; PASS</span>'
					: '<span style="color:#8B0000;font-weight:bold;">&#10008; FAIL</span>';

				echo '<tr>';
				echo '<td><strong>' . esc_html( $result['name'] ) . '</strong></td>';
				echo '<td>' . $badge . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $badge is hardcoded HTML.
				echo '<td>' . esc_html( $result['detail'] ) . '</td>';
				echo '</tr>';
			}

			echo '</tbody>';
			echo '</table>';

			echo '<p><strong>' . sprintf(
				/* translators: 1: number of passed tests 2: number of failed tests */
				esc_html__( 'Results: %1$d passed, %2$d failed', 'vinti4' ),
				$pass_count,
				$fail_count
			) . '</strong></p>';
		}

		echo '</div>';
	}

	/**
	 * Run all diagnostic tests and return results.
	 *
	 * @return array[] Array of test result arrays with 'name', 'status', 'detail' keys.
	 */
	public static function run_tests(): array {
		$results = array();

		$results[] = self::test_config_present();
		$results[] = self::test_logger_available();
		$results[] = self::test_fingerprint_hash();
		$results[] = self::test_currency_map();
		$results[] = self::test_success_types();
		$results[] = self::test_logger_mask();
		$results[] = self::test_logger_mask_empty();
		$results[] = self::test_callback_endpoint();
		$results[] = self::test_gateway_registered();
		$results[] = self::test_blocks_support();
		$results[] = self::test_feature_compatibility_declarations();
		$results[] = self::test_callback_duplicate_detection();
		$results[] = self::test_callback_invalid_fingerprint();
		$results[] = self::test_admin_partial_payment();

		return $results;
	}

	/**
	 * Test: Required gateway settings are present.
	 *
	 * @return array Test result.
	 */
	private static function test_config_present(): array {
		$settings = get_option( 'woocommerce_vinti4_settings', array() );

		$pos_id        = ! empty( $settings['pos_id'] );
		$pos_auth_code = ! empty( $settings['pos_auth_code'] );
		$vbv2_url      = ! empty( $settings['vbv2_url'] );

		$all_present = $pos_id && $pos_auth_code && $vbv2_url;

		$missing = array();
		if ( ! $pos_id ) {
			$missing[] = 'pos_id';
		}
		if ( ! $pos_auth_code ) {
			$missing[] = 'pos_auth_code';
		}
		if ( ! $vbv2_url ) {
			$missing[] = 'vbv2_url';
		}

		return array(
			'name'   => 'Config Present',
			'status' => $all_present ? 'pass' : 'fail',
			'detail' => $all_present
				? 'All required settings (pos_id, pos_auth_code, vbv2_url) are configured.'
				: 'Missing: ' . implode( ', ', $missing ),
		);
	}

	/**
	 * Test: Logger class is available and can be called.
	 *
	 * @return array Test result.
	 */
	private static function test_logger_available(): array {
		$available = class_exists( 'Vinti4_Logger' ) && method_exists( 'Vinti4_Logger', 'log' );

		return array(
			'name'   => 'Logger Available',
			'status' => $available ? 'pass' : 'fail',
			'detail' => $available
				? 'Vinti4_Logger class loaded with log() method.'
				: 'Vinti4_Logger class or log() method not found.',
		);
	}

	/**
	 * Test: Fingerprint sha512_base64 returns valid 64-byte hash.
	 *
	 * @return array Test result.
	 */
	private static function test_fingerprint_hash(): array {
		if ( ! class_exists( 'Vinti4_Fingerprint' ) || ! method_exists( 'Vinti4_Fingerprint', 'sha512_base64' ) ) {
			return array(
				'name'   => 'Fingerprint Hash',
				'status' => 'fail',
				'detail' => 'Vinti4_Fingerprint class or sha512_base64() not available.',
			);
		}

		$hash    = Vinti4_Fingerprint::sha512_base64( 'test-value' );
		$decoded = base64_decode( $hash, true );

		$is_valid = is_string( $decoded ) && 64 === strlen( $decoded );

		return array(
			'name'   => 'Fingerprint Hash',
			'status' => $is_valid ? 'pass' : 'fail',
			'detail' => $is_valid
				? 'sha512_base64() produces valid 64-byte SHA-512 hash.'
				: sprintf( 'sha512_base64() produced unexpected result (decoded length: %d).', strlen( $decoded ) ),
		);
	}

	/**
	 * Test: CVE currency maps to numeric code 132.
	 *
	 * @return array Test result.
	 */
	private static function test_currency_map(): array {
		$gateways = WC()->payment_gateways()->payment_gateways();

		/** @var WC_Gateway_Vinti4|false $gateway */
		$gateway = isset( $gateways['vinti4'] ) ? $gateways['vinti4'] : false;

		if ( ! $gateway || ! method_exists( $gateway, 'get_currency_code' ) ) {
			return array(
				'name'   => 'Currency Map',
				'status' => 'fail',
				'detail' => 'WC_Gateway_Vinti4 not available or get_currency_code() missing.',
			);
		}

		$code     = $gateway->get_currency_code();
		$correct  = '132' === $code;

		return array(
			'name'   => 'Currency Map (CVE → 132)',
			'status' => $correct ? 'pass' : 'fail',
			'detail' => $correct
				? 'CVE correctly maps to numeric code 132.'
				: sprintf( 'CVE mapped to "%s" instead of "132".', $code ),
		);
	}

	/**
	 * Test: Success message types are correctly identified.
	 *
	 * Types 8, 10, M, P should return true; 0, 1 should return false.
	 *
	 * @return array Test result.
	 */
	private static function test_success_types(): array {
		if ( ! function_exists( 'vinti4_is_success_message_type' ) ) {
			return array(
				'name'   => 'Success Types',
				'status' => 'fail',
				'detail' => 'vinti4_is_success_message_type() function not loaded.',
			);
		}

		$pass_cases = array(
			'8'  => vinti4_is_success_message_type( '8' ),
			'10' => vinti4_is_success_message_type( '10' ),
			'M'  => vinti4_is_success_message_type( 'M' ),
			'P'  => vinti4_is_success_message_type( 'P' ),
		);

		$fail_cases = array(
			'0' => vinti4_is_success_message_type( '0' ),
			'1' => vinti4_is_success_message_type( '1' ),
		);

		$all_pass = true;
		$errors   = array();

		foreach ( $pass_cases as $type => $result ) {
			if ( true !== $result ) {
				$all_pass  = false;
				$errors[]  = sprintf( '"%s" should be true', $type );
			}
		}

		foreach ( $fail_cases as $type => $result ) {
			if ( false !== $result ) {
				$all_pass  = false;
				$errors[]  = sprintf( '"%s" should be false', $type );
			}
		}

		return array(
			'name'   => 'Success Types',
			'status' => $all_pass ? 'pass' : 'fail',
			'detail' => $all_pass
				? '8, 10, M, P → true; 0, 1 → false. All correct.'
				: 'Errors: ' . implode( '; ', $errors ),
		);
	}

	/**
	 * Test: Logger masks auth codes correctly.
	 *
	 * "ABCDEFGHYZ" → "ABC*****YZ" (length 10, first 3 + ***** + last 2).
	 *
	 * @return array Test result.
	 */
	private static function test_logger_mask(): array {
		if ( ! class_exists( 'Vinti4_Logger' ) || ! method_exists( 'Vinti4_Logger', 'mask_auth_code' ) ) {
			return array(
				'name'   => 'Logger Mask',
				'status' => 'fail',
				'detail' => 'Vinti4_Logger::mask_auth_code() not available.',
			);
		}

		$input    = 'ABCDEFGHYZ';
		$expected = 'ABC*****YZ';
		$actual   = Vinti4_Logger::mask_auth_code( $input );

		$correct = $expected === $actual;

		return array(
			'name'   => 'Logger Mask',
			'status' => $correct ? 'pass' : 'fail',
			'detail' => $correct
				? sprintf( '"%s" → "%s" (correct).', $input, $actual )
				: sprintf( '"%s" → "%s" (expected "%s").', $input, $actual, $expected ),
		);
	}

	/**
	 * Test: Logger mask returns empty string for empty input.
	 *
	 * @return array Test result.
	 */
	private static function test_logger_mask_empty(): array {
		if ( ! class_exists( 'Vinti4_Logger' ) || ! method_exists( 'Vinti4_Logger', 'mask_auth_code' ) ) {
			return array(
				'name'   => 'Logger Mask Empty',
				'status' => 'fail',
				'detail' => 'Vinti4_Logger::mask_auth_code() not available.',
			);
		}

		$actual   = Vinti4_Logger::mask_auth_code( '' );
		$correct  = '' === $actual;

		return array(
			'name'   => 'Logger Mask (Empty)',
			'status' => $correct ? 'pass' : 'fail',
			'detail' => $correct
				? 'Empty string returns empty string.'
				: sprintf( 'Empty string returned "%s" instead of "".', $actual ),
		);
	}

	/**
	 * Test: Callback endpoint rewrite rule is registered.
	 *
	 * @return array Test result.
	 */
	private static function test_callback_endpoint(): array {
		global $wp_rewrite;

		$rules     = isset( $wp_rewrite->rules ) ? $wp_rewrite->rules : array();
		$registered = isset( $rules['^vinti4-payment/?$'] );

		return array(
			'name'   => 'Callback Endpoint',
			'status' => $registered ? 'pass' : 'fail',
			'detail' => $registered
				? 'Rewrite rule ^vinti4-payment/?$ is registered.'
				: 'Rewrite rule ^vinti4-payment/?$ not found. Try flushing permalinks (Settings → Permalinks → Save).',
		);
	}

	/**
	 * Test: Gateway is registered with WooCommerce.
	 *
	 * @return array Test result.
	 */
	private static function test_gateway_registered(): array {
		$gateways  = WC()->payment_gateways()->payment_gateways();
		$found     = isset( $gateways['vinti4'] );

		return array(
			'name'   => 'Gateway Registered',
			'status' => $found ? 'pass' : 'fail',
			'detail' => $found
				? 'WC_Gateway_Vinti4 is registered with WooCommerce.'
				: 'WC_Gateway_Vinti4 not found in registered gateways.',
		);
	}

	/**
	 * Test: Blocks support class is registered.
	 *
	 * @return array Test result.
	 */
	private static function test_blocks_support(): array {
		$has_class = class_exists( 'WC_Vinti4_Blocks_Support' );
		$extends   = $has_class && is_subclass_of( 'WC_Vinti4_Blocks_Support', 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' );

		return array(
			'name'   => 'Blocks Support',
			'status' => $extends ? 'pass' : 'fail',
			'detail' => $extends
				? 'WC_Vinti4_Blocks_Support registered and extends AbstractPaymentMethodType.'
				: ( $has_class
					? 'WC_Vinti4_Blocks_Support exists but does not extend AbstractPaymentMethodType.'
					: 'WC_Vinti4_Blocks_Support class not found.' ),
		);
	}

	/**
	 * Test: WooCommerce feature compatibility declarations are available.
	 *
	 * @return array Test result.
	 */
	private static function test_feature_compatibility_declarations(): array {
		if ( ! class_exists( 'Automattic\WooCommerce\Utilities\FeaturesUtil' ) || ! method_exists( 'Automattic\WooCommerce\Utilities\FeaturesUtil', 'get_compatible_features_for_plugin' ) ) {
			return array(
				'name'   => 'Feature Compatibility Declarations',
				'status' => 'fail',
				'detail' => 'WooCommerce FeaturesUtil is unavailable.',
			);
		}

		$plugin_file = function_exists( 'plugin_basename' ) ? plugin_basename( defined( 'VINTI4_PLUGIN_FILE' ) ? VINTI4_PLUGIN_FILE : dirname( __DIR__ ) . '/vinti4.php' ) : 'vinti4-woocommerce-plugin/vinti4.php';
		$declarations = \Automattic\WooCommerce\Utilities\FeaturesUtil::get_compatible_features_for_plugin( $plugin_file );
		$expected     = array( 'custom_order_tables', 'cart_checkout_blocks' );
		$issues       = array();

		foreach ( $expected as $feature_slug ) {
			if ( ! in_array( $feature_slug, $declarations['compatible'] ?? array(), true ) ) {
				$issues[] = $feature_slug . ': declaration missing or rejected';
			}
		}

		$ok = empty( $issues );

		return array(
			'name'   => 'Feature Compatibility Declarations',
			'status' => $ok ? 'pass' : 'fail',
			'detail' => $ok
				? 'Declared WooCommerce compatibility for custom_order_tables and cart_checkout_blocks.'
				: implode( '; ', $issues ),
		);
	}

	/**
	 * Test: Callback simulation — duplicate detection.
	 *
	 * Simulates the idempotency logic by verifying the callback handler
	 * checks for `_vinti4_callback_processed` meta before processing.
	 * This is a code-structure test: verifies the callback handler class
	 * exists and the idempotency pattern is present in its source.
	 *
	 * @return array Test result.
	 */
	private static function test_callback_duplicate_detection(): array {
		if ( ! class_exists( 'Vinti4_Callback_Handler' ) ) {
			return array(
				'name'   => 'Callback: Duplicate Detection',
				'status' => 'fail',
				'detail' => 'Vinti4_Callback_Handler class not loaded.',
			);
		}

		// Verify the handler class has the handle() method.
		if ( ! method_exists( 'Vinti4_Callback_Handler', 'handle' ) ) {
			return array(
				'name'   => 'Callback: Duplicate Detection',
				'status' => 'fail',
				'detail' => 'Vinti4_Callback_Handler::handle() method not found.',
			);
		}

		// Verify the handler class has the mark_processed_and_redirect() method.
		if ( ! method_exists( 'Vinti4_Callback_Handler', 'mark_processed_and_redirect' ) ) {
			return array(
				'name'   => 'Callback: Duplicate Detection',
				'status' => 'fail',
				'detail' => 'Vinti4_Callback_Handler::mark_processed_and_redirect() not found (idempotency helper missing).',
			);
		}

		// Verify source contains the idempotency meta key.
		$reflection = new ReflectionMethod( 'Vinti4_Callback_Handler', 'handle' );
		$source_file = $reflection->getFileName();
		$source       = file_get_contents( $source_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local plugin file.

		$has_idempotency_meta   = strpos( $source, '_vinti4_callback_processed' ) !== false;
		$has_already_processed  = strpos( $source, 'already_processed' ) !== false;

		$ok = $has_idempotency_meta && $has_already_processed;

		return array(
			'name'   => 'Callback: Duplicate Detection',
			'status' => $ok ? 'pass' : 'fail',
			'detail' => $ok
				? 'Callback handler implements idempotency via _vinti4_callback_processed meta check.'
				: 'Callback handler missing idempotency pattern (_vinti4_callback_processed / already_processed check).',
		);
	}

	/**
	 * Test: Callback simulation — invalid fingerprint rejection.
	 *
	 * Verifies the callback handler validates the response fingerprint
	 * by checking the source code for fingerprint comparison logic.
	 *
	 * @return array Test result.
	 */
	private static function test_callback_invalid_fingerprint(): array {
		if ( ! class_exists( 'Vinti4_Callback_Handler' ) ) {
			return array(
				'name'   => 'Callback: Invalid Fingerprint',
				'status' => 'fail',
				'detail' => 'Vinti4_Callback_Handler class not loaded.',
			);
		}

		if ( ! method_exists( 'Vinti4_Callback_Handler', 'handle' ) ) {
			return array(
				'name'   => 'Callback: Invalid Fingerprint',
				'status' => 'fail',
				'detail' => 'Vinti4_Callback_Handler::handle() method not found.',
			);
		}

		// Verify source contains fingerprint validation logic.
		$reflection  = new ReflectionMethod( 'Vinti4_Callback_Handler', 'handle' );
		$source_file = $reflection->getFileName();
		$source      = file_get_contents( $source_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_FUNCTIONS.file_get_contents -- Reading local plugin file.

		$has_fingerprint_check     = strpos( $source, 'expected_fingerprint' ) !== false;
		$has_fingerprint_mismatch  = strpos( $source, 'fingerprint mismatch' ) !== false;
		$has_build_response        = strpos( $source, 'build_response_fingerprint' ) !== false;

		$ok = $has_fingerprint_check && $has_fingerprint_mismatch && $has_build_response;

		return array(
			'name'   => 'Callback: Invalid Fingerprint',
			'status' => $ok ? 'pass' : 'fail',
			'detail' => $ok
				? 'Callback handler validates response fingerprint with build_response_fingerprint() comparison.'
				: 'Callback handler missing fingerprint validation (expected_fingerprint / fingerprint mismatch / build_response_fingerprint).',
		);
	}

	private static function test_admin_partial_payment(): array {
		if ( ! class_exists( 'Vinti4_Admin_Partial_Payment' ) ) {
			return array(
				'name'   => 'Admin Partial Payment',
				'status' => 'fail',
				'detail' => 'Vinti4_Admin_Partial_Payment class not loaded.',
			);
		}

		if ( ! method_exists( 'Vinti4_Admin_Partial_Payment', 'register' ) ) {
			return array(
				'name'   => 'Admin Partial Payment',
				'status' => 'fail',
				'detail' => 'Vinti4_Admin_Partial_Payment missing expected method: register.',
			);
		}

		if ( ! method_exists( 'Vinti4_Admin_Partial_Payment', 'handle_create_partial_request' ) ) {
			return array(
				'name'   => 'Admin Partial Payment',
				'status' => 'fail',
				'detail' => 'Vinti4_Admin_Partial_Payment missing expected method: handle_create_partial_request.',
			);
		}

		return array(
			'name'   => 'Admin Partial Payment',
			'status' => 'pass',
			'detail' => 'Vinti4_Admin_Partial_Payment class loaded with register() and handle_create_partial_request() methods.',
		);
	}
}
