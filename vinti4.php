<?php
/**
 * Plugin Name: Vinti4 for WooCommerce
 * Plugin URI:  https://github.com/vinti4/vinti4-woocommerce-plugin
 * Description: Accept payments via Vinti4 / SISP hosted payment page on your WooCommerce store.
 * Version:     1.0.0
 * Author:      Vinti4
 * Author URI:  https://www.vinti4.cv
 * License:     GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: vinti4
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.8
 * Requires PHP: 8.1
 * WC requires at least: 8.0
 * WC tested up to: 9.7
 */

defined( 'ABSPATH' ) || exit;

// Constants.
define( 'VINTI4_VERSION', '1.0.0' );
define( 'VINTI4_PLUGIN_FILE', __FILE__ );
define( 'VINTI4_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'VINTI4_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load admin notices unconditionally — must work even without WooCommerce.
require_once VINTI4_PLUGIN_DIR . 'includes/class-vinti4-admin-notices.php';

/**
 * Bootstrap the plugin after all other plugins are loaded.
 *
 * @return void
 */
function vinti4_init() {
	// Check for WooCommerce dependency.
	if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'WC_Payment_Gateway' ) ) {
		Vinti4_Admin_Notices::register_missing_wc_notice();
		return;
	}

	// Include gateway class — created in Phase 1.
	require_once VINTI4_PLUGIN_DIR . 'includes/class-wc-gateway-vinti4.php';

	require_once VINTI4_PLUGIN_DIR . 'includes/functions-vinti4-formatting.php';
	require_once VINTI4_PLUGIN_DIR . 'includes/class-vinti4-fingerprint.php';
	require_once VINTI4_PLUGIN_DIR . 'includes/class-vinti4-request-builder.php';
	// Phase 7: require_once VINTI4_PLUGIN_DIR . 'includes/class-vinti4-logger.php';
	// Phase 6: require_once VINTI4_PLUGIN_DIR . 'includes/class-wc-vinti4-blocks-support.php';
}
add_action( 'plugins_loaded', 'vinti4_init', 20 );

/**
 * Register the Vinti4 gateway with WooCommerce.
 *
 * @param array $methods Array of payment gateway class names.
 * @return array
 */
function vinti4_add_gateway( $methods ) {
	if ( class_exists( 'WC_Gateway_Vinti4' ) ) {
		$methods[] = 'WC_Gateway_Vinti4';
	}
	return $methods;
}
add_filter( 'woocommerce_payment_gateways', 'vinti4_add_gateway' );

// Phase 6: Block support registration.
// add_action(
//     'woocommerce_blocks_payment_method_type_registration',
//     function ( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $registry ) {
//         if ( class_exists( 'WC_Vinti4_Blocks_Support' ) ) {
//             $registry->register( new WC_Vinti4_Blocks_Support() );
//         }
//     }
// );

// Phase: i18n — Load plugin text domain.
// add_action( 'plugins_loaded', function () {
//     load_plugin_textdomain( 'vinti4', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
// } );
