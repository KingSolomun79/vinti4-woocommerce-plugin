<?php
/**
 * Vinti4 WooCommerce Payment Gateway
 *
 * Provides a hosted payment page integration with Vinti4 / SISP.
 *
 * @package Vinti4ForWooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Gateway_Vinti4
 *
 * WooCommerce payment gateway for Vinti4 / SISP hosted payment page.
 */
class WC_Gateway_Vinti4 extends WC_Payment_Gateway {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id                 = 'vinti4';
		$this->method_title       = __( 'Vinti4', 'vinti4' );
		$this->method_description = __( 'Pay via Vinti4 / SISP hosted payment page.', 'vinti4' );
		$this->has_fields         = false;
		$this->supports           = array( 'products' );
		$this->icon               = '';

		// Load form fields and settings.
		$this->init_form_fields();
		$this->init_settings();

		// Load saved settings into properties.
		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->enabled     = $this->get_option( 'enabled' );

		// Save settings on admin update.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		// Phase 5: Callback handler for SISP response.
		// add_action( 'woocommerce_api_' . $this->id, array( $this, 'handle_callback' ) );
	}

	/**
	 * Define admin-facing settings fields.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'     => array(
				'title'   => __( 'Enable/Disable', 'vinti4' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Vinti4', 'vinti4' ),
				'default' => 'no',
			),
			'title'       => array(
				'title'       => __( 'Title', 'vinti4' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'vinti4' ),
				'default'     => __( 'Pay with Vinti4', 'vinti4' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Description', 'vinti4' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'vinti4' ),
				'default'     => __( 'You will be redirected to Vinti4 to complete payment.', 'vinti4' ),
				'desc_tip'    => true,
			),
			'pos_id'          => array(
				'title'       => __( 'POS ID', 'vinti4' ),
				'type'        => 'text',
				'description' => __( 'Your POS identifier provided by SISP.', 'vinti4' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'pos_auth_code'   => array(
				'title'             => __( 'POS Auth Code', 'vinti4' ),
				'type'              => 'text',
				'description'       => __( 'Authentication code from SISP. Special characters are preserved exactly as entered.', 'vinti4' ),
				'default'           => '',
				'desc_tip'          => true,
				'custom_attributes' => array( 'autocomplete' => 'off' ),
			),
			'vbv2_url'        => array(
				'title'       => __( 'SISP Payment URL', 'vinti4' ),
				'type'        => 'text',
				'description' => __( 'URL of the SISP 3DS payment page. Use the test URL for sandbox mode.', 'vinti4' ),
				'default'     => 'https://3dsteste.vinti4net.cv/3ds_middleware_php/public/3ds_init.php',
				'desc_tip'    => true,
			),
			'language'        => array(
				'title'       => __( 'Language', 'vinti4' ),
				'type'        => 'select',
				'description' => __( 'Language for the SISP payment page.', 'vinti4' ),
				'default'     => 'pt',
				'options'     => array(
					'pt' => __( 'Portuguese', 'vinti4' ),
					'en' => __( 'English', 'vinti4' ),
				),
				'desc_tip'    => true,
			),
			'debug'           => array(
				'title'       => __( 'Debug Mode', 'vinti4' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable logging', 'vinti4' ),
				'description' => __( 'Log payment events for debugging. Do not enable in production.', 'vinti4' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'currency_default' => array(
				'title'       => __( 'Default Currency', 'vinti4' ),
				'type'        => 'select',
				'description' => __( 'Currency used when auto-detection from the order is not possible. Auto-detection uses the WooCommerce order currency.', 'vinti4' ),
				'default'     => 'CVE',
				'options'     => array(
					'CVE' => __( 'CVE — Cape Verdean Escudo', 'vinti4' ),
					'EUR' => __( 'EUR — Euro', 'vinti4' ),
					'USD' => __( 'USD — US Dollar', 'vinti4' ),
				),
				'desc_tip'    => true,
			),
		);
	}

	/**
	 * Process the payment for a given order.
	 *
	 * Phase 4: Full payment redirect flow will be implemented here.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		// Phase 4: Implement full payment redirect flow.
		return array(
			'result'   => 'failure',
			'redirect' => '',
		);
	}
}
