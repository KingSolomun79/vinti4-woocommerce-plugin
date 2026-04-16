<?php
/**
 * Vinti4 Admin Notices
 *
 * Displays admin notices when plugin requirements are not met.
 *
 * @package Vinti4ForWooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Vinti4_Admin_Notices
 *
 * Handles admin notice rendering for missing dependencies.
 */
class Vinti4_Admin_Notices {

	/**
	 * Register the admin notice hook for missing WooCommerce.
	 *
	 * @return void
	 */
	public static function register_missing_wc_notice() {
		add_action( 'admin_notices', array( __CLASS__, 'render_missing_wc_notice' ) );
	}

	/**
	 * Render the missing WooCommerce admin notice.
	 *
	 * Only displays if WooCommerce is still not active when the notice fires.
	 *
	 * @return void
	 */
	public static function render_missing_wc_notice() {
		// Double-check — if WooCommerce was activated after our hook was registered, bail.
		if ( class_exists( 'WooCommerce' ) ) {
			return;
		}

		$message = esc_html__( 'Vinti4 for WooCommerce requires WooCommerce to be installed and active.', 'vinti4' );

		printf(
			'<div class="notice notice-error is-dismissible"><p><strong>%s</strong></p></div>',
			$message // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already escaped above.
		);
	}
}
