<?php
/**
 * WooCommerce feature compatibility declarations for Vinti4.
 *
 * @package Vinti4ForWooCommerce
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'Vinti4_Feature_Compatibility' ) ) {
	return;
}

class Vinti4_Feature_Compatibility {

	/**
	 * Declare WooCommerce feature compatibility for this plugin.
	 *
	 * @param string|null $features_util_class Optional class name override for testing.
	 * @return array<int, array<string, string>>
	 */
	public static function declare_compatibility( ?string $features_util_class = null ): array {
		$features           = self::supported_features();
		$plugin_file        = self::plugin_file();
		$features_util_name = $features_util_class ?: 'Automattic\\WooCommerce\\Utilities\\FeaturesUtil';

		if ( ! class_exists( $features_util_name ) || ! method_exists( $features_util_name, 'declare_compatibility' ) ) {
			return array_map(
				static function ( string $feature ) use ( $plugin_file ): array {
					return array(
						'feature'     => $feature,
						'status'      => 'skipped',
						'detail'      => 'WooCommerce FeaturesUtil::declare_compatibility() unavailable.',
						'plugin_file' => $plugin_file,
					);
				},
				$features
			);
		}

		$results = array();

		foreach ( $features as $feature ) {
			try {
				$declared = $features_util_name::declare_compatibility( $feature, $plugin_file, true );

				$results[] = array(
					'feature'     => $feature,
					'status'      => false === $declared ? 'error' : 'declared',
					'detail'      => false === $declared ? 'WooCommerce rejected compatibility declaration.' : 'Compatibility declared.',
					'plugin_file' => $plugin_file,
				);
			} catch ( \Throwable $throwable ) {
				$results[] = array(
					'feature'     => $feature,
					'status'      => 'error',
					'detail'      => $throwable->getMessage(),
					'plugin_file' => $plugin_file,
				);
			}
		}

		return $results;
	}

	/**
	 * Supported WooCommerce feature slugs.
	 *
	 * @return array<int, string>
	 */
	private static function supported_features(): array {
		return array(
			'custom_order_tables',
			'cart_checkout_blocks',
		);
	}

	/**
	 * Resolve plugin bootstrap file for WooCommerce declarations.
	 *
	 * @return string
	 */
	private static function plugin_file(): string {
		if ( defined( 'VINTI4_PLUGIN_FILE' ) ) {
			return VINTI4_PLUGIN_FILE;
		}

		return dirname( __DIR__ ) . '/vinti4.php';
	}
}
