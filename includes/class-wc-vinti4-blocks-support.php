<?php
/**
 * Vinti4 WooCommerce Blocks Support
 *
 * Integrates the Vinti4 payment gateway with the WooCommerce Checkout Block.
 * Extends AbstractPaymentMethodType to provide block-based checkout support.
 *
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Class WC_Vinti4_Blocks_Support
 *
 * Payment method integration for WooCommerce Checkout Block.
 */
class WC_Vinti4_Blocks_Support extends AbstractPaymentMethodType {

	/**
	 * Payment method name — must match the gateway ID.
	 *
	 * @var string
	 */
	protected $name = 'vinti4';

	/**
	 * Initialize the payment method type — loads settings from the gateway option.
	 *
	 * @return void
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_vinti4_settings', [] );
	}

	/**
	 * Whether the payment method is active (enabled in settings).
	 *
	 * @return bool
	 */
	public function is_active() {
		return 'yes' === ( $this->settings['enabled'] ?? 'no' );
	}

	/**
	 * Register and return the JavaScript handles needed for the checkout block.
	 *
	 * @return string[]
	 */
	public function get_payment_method_script_handles() {
		wp_register_script(
			'wc-vinti4-blocks',
			VINTI4_PLUGIN_URL . 'assets/js/blocks.js',
			[ 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities' ],
			VINTI4_VERSION,
			true
		);

		return [ 'wc-vinti4-blocks' ];
	}

	/**
	 * Return data passed to the JavaScript payment method via wcSettings.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		return [
			'title'       => $this->settings['title'] ?? '',
			'description' => $this->settings['description'] ?? '',
			'supports'    => [ 'products' ],
		];
	}
}
