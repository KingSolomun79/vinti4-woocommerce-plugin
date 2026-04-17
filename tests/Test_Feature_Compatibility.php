<?php

use PHPUnit\Framework\TestCase;

class Test_Feature_Compatibility extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Mock_Vinti4_FeaturesUtil::$calls = array();
		Mock_Vinti4_FeaturesUtil::$return_value = true;
		unset( $GLOBALS['mock_wp_actions'] );
	}

	public function test_declare_compatibility_declares_expected_features_with_plugin_file(): void {
		$results = Vinti4_Feature_Compatibility::declare_compatibility();

		$this->assertCount( 2, Mock_Vinti4_FeaturesUtil::$calls );
		$this->assertSame( 'custom_order_tables', Mock_Vinti4_FeaturesUtil::$calls[0]['feature'] );
		$this->assertSame( 'cart_checkout_blocks', Mock_Vinti4_FeaturesUtil::$calls[1]['feature'] );

		$expected_plugin_file = defined( 'VINTI4_PLUGIN_FILE' )
			? VINTI4_PLUGIN_FILE
			: dirname( __DIR__ ) . '/vinti4.php';

		$this->assertSame( $expected_plugin_file, Mock_Vinti4_FeaturesUtil::$calls[0]['plugin_file'] );
		$this->assertSame( $expected_plugin_file, Mock_Vinti4_FeaturesUtil::$calls[1]['plugin_file'] );
		$this->assertTrue( Mock_Vinti4_FeaturesUtil::$calls[0]['compatible'] );
		$this->assertTrue( Mock_Vinti4_FeaturesUtil::$calls[1]['compatible'] );

		$this->assertSame( 'declared', $results[0]['status'] );
		$this->assertSame( 'declared', $results[1]['status'] );
	}

	public function test_declare_compatibility_returns_safely_when_features_util_is_unavailable(): void {
		$results = Vinti4_Feature_Compatibility::declare_compatibility( 'Vendor\\Missing\\FeaturesUtil' );

		$this->assertCount( 2, $results );
		$this->assertSame( 'custom_order_tables', $results[0]['feature'] );
		$this->assertSame( 'cart_checkout_blocks', $results[1]['feature'] );
		$this->assertSame( 'skipped', $results[0]['status'] );
		$this->assertSame( 'skipped', $results[1]['status'] );
		$this->assertStringContainsString( 'unavailable', $results[0]['detail'] );
		$this->assertStringContainsString( 'unavailable', $results[1]['detail'] );
		$this->assertSame( array(), Mock_Vinti4_FeaturesUtil::$calls );
	}

	public function test_bootstrap_registers_declaration_hook_and_executes_at_before_woocommerce_init(): void {
		require_once dirname( __DIR__ ) . '/vinti4.php';

		$hooks = $GLOBALS['mock_wp_actions']['before_woocommerce_init'] ?? array();
		$this->assertCount( 1, $hooks );
		$this->assertSame( 'vinti4_declare_woocommerce_compatibility', $hooks[0]['callback'] );
		$this->assertSame( array(), Mock_Vinti4_FeaturesUtil::$calls );

		do_action( 'before_woocommerce_init' );

		$this->assertCount( 2, Mock_Vinti4_FeaturesUtil::$calls );
		$this->assertSame( 'custom_order_tables', Mock_Vinti4_FeaturesUtil::$calls[0]['feature'] );
		$this->assertSame( 'cart_checkout_blocks', Mock_Vinti4_FeaturesUtil::$calls[1]['feature'] );
	}
}
