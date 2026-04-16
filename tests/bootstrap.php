<?php
/**
 * PHPUnit Bootstrap for Vinti4 WooCommerce Plugin
 *
 * Defines WordPress and WooCommerce stubs so pure PHP unit tests can run
 * without a full WordPress installation.
 *
 * @since 1.0.0 */

// ─── Define ABSPATH (required by `defined('ABSPATH') || exit;` guards) ───────
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'VINTI4_PHPUNIT', true );

// ─── WordPress function stubs ────────────────────────────────────────────────

if ( ! function_exists( 'absint' ) ) {
	/**
	 * WordPress absint() — absolute integer.
	 */
	function absint( $maybeint ) {
		return abs( (int) $maybeint );
	}
}

if ( ! function_exists( 'wp_generate_password' ) ) {
	/**
	 * Deterministic password for testing.
	 */
	function wp_generate_password( $length = 12, $special_chars = true, $extra_special_chars = false ) {
		return str_repeat( 'a', $length );
	}
}

if ( ! function_exists( 'wp_generate_uuid4' ) ) {
	/**
	 * Fixed UUID for deterministic testing.
	 */
	function wp_generate_uuid4() {
		return '550e8400-e29b-41d4-a716-446655440000';
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return strip_tags( $str );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return (string) $url;
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		if ( is_array( $value ) ) {
			return array_map( 'wp_unslash', $value );
		}
		return stripslashes( $value );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'determine_locale' ) ) {
	function determine_locale() {
		return $GLOBALS['mock_wp_determine_locale'] ?? 'pt_PT';
	}
}

if ( ! function_exists( 'get_locale' ) ) {
	function get_locale() {
		return $GLOBALS['mock_wp_get_locale'] ?? 'pt_PT';
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() {
		return false;
	}
}

if ( ! function_exists( 'wc_get_logger' ) ) {
	function wc_get_logger() {
		throw new \RuntimeException( 'wc_get_logger() should not be called in pure PHP tests.' );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}

if ( ! function_exists( 'wc_get_order' ) ) {
	function wc_get_order( $order_id ) {
		if ( isset( $GLOBALS['mock_wc_order'] ) ) {
			return $GLOBALS['mock_wc_order'];
		}
		return null;
	}
}

if ( ! function_exists( 'wc_get_checkout_url' ) ) {
	function wc_get_checkout_url() {
		return '/checkout/';
	}
}

if ( ! function_exists( 'wp_safe_redirect' ) ) {
	function wp_safe_redirect( $url ) {
		throw new \Vinti4_Redirect_Exception( $url );
	}
}

if ( ! function_exists( 'wp_die' ) ) {
	function wp_die( $message = '', $title = '', $args = array() ) {
		throw new \Vinti4_Die_Exception( $message );
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( ...$args ) {
		$params = array();
		$url    = '';

		if ( 3 === count( $args ) ) {
			$params = array( (string) $args[0] => $args[1] );
			$url    = (string) $args[2];
		} elseif ( 2 === count( $args ) ) {
			$params = is_array( $args[0] ) ? $args[0] : array();
			$url    = (string) $args[1];
		}

		$parts = parse_url( $url );
		$query = array();

		if ( isset( $parts['query'] ) ) {
			parse_str( $parts['query'], $query );
		}

		foreach ( $params as $key => $value ) {
			$query[ $key ] = $value;
		}

		$scheme   = isset( $parts['scheme'] ) ? $parts['scheme'] . '://' : '';
		$host     = $parts['host'] ?? '';
		$port     = isset( $parts['port'] ) ? ':' . $parts['port'] : '';
		$user     = $parts['user'] ?? '';
		$pass     = isset( $parts['pass'] ) ? ':' . $parts['pass'] : '';
		$auth     = '' !== $user ? $user . $pass . '@' : '';
		$path     = $parts['path'] ?? '';
		$fragment = isset( $parts['fragment'] ) ? '#' . $parts['fragment'] : '';

		return $scheme . $auth . $host . $port . $path . '?' . http_build_query( $query ) . $fragment;
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) {
		return 'http://example.com' . $path;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		// No-op stub.
	}
}

if ( ! function_exists( 'status_header' ) ) {
	function status_header( $code ) {
		$GLOBALS['mock_status_header'] = $code;
	}
}

if ( ! function_exists( 'nocache_headers' ) ) {
	function nocache_headers() {
		$GLOBALS['mock_nocache_headers_called'] = true;
	}
}

if ( ! function_exists( 'WC' ) ) {
	function WC() {
		return $GLOBALS['mock_wc_instance'] ?? null;
	}
}

// ─── Exception classes for redirect/die stubs ────────────────────────────────

class Vinti4_Redirect_Exception extends \Exception {}
class Vinti4_Die_Exception extends \Exception {}

// ─── WooCommerce class stubs ────────────────────────────────────────────────

if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
	class WC_Payment_Gateway {
		public function __construct() {}

		public function init_form_fields() {}

		public function init_settings() {}

		public function get_option( $key, $default = '' ) {
			return $default;
		}

		public function update_option( $key, $value ) {}

		public function process_admin_options() {}
	}
}

if ( ! class_exists( 'WC_Order' ) ) {
	class WC_Order {
		public function get_id() { return 0; }
		public function get_meta( $key ) { return ''; }
		public function update_meta_data( $key, $value ) {}
		public function save() {}
		public function get_status() { return 'pending'; }
		public function update_status( $status, $note = '' ) {}
		public function payment_complete( $transaction_id = '' ) {}
		public function get_checkout_order_received_url() { return '/order-received/'; }
		public function add_order_note( $note ) {}
		public function get_total() { return 0.0; }
		public function get_currency() { return 'CVE'; }
		public function get_order_key() { return 'wc_order_key'; }
		public function get_billing_phone() { return ''; }
		public function get_billing_email() { return ''; }
		public function get_billing_city() { return ''; }
		public function get_billing_country() { return ''; }
		public function get_billing_address_1() { return ''; }
		public function get_billing_address_2() { return ''; }
		public function get_billing_postcode() { return ''; }
		public function get_billing_state() { return ''; }
		public function get_shipping_city() { return ''; }
		public function get_shipping_country() { return ''; }
		public function get_shipping_address_1() { return ''; }
		public function get_shipping_postcode() { return ''; }
		public function get_shipping_state() { return ''; }
		public function get_customer_id() { return 0; }
	}
}

// ─── Require source files ───────────────────────────────────────────────────

require_once __DIR__ . '/../includes/functions-vinti4-formatting.php';
require_once __DIR__ . '/../includes/class-vinti4-fingerprint.php';
require_once __DIR__ . '/../includes/class-vinti4-logger.php';
require_once __DIR__ . '/../includes/class-wc-gateway-vinti4.php';
require_once __DIR__ . '/../includes/class-vinti4-request-builder.php';
require_once __DIR__ . '/../includes/class-vinti4-callback-handler.php';
require_once __DIR__ . '/../includes/class-vinti4-redirect-form.php';
