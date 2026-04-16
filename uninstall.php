<?php
/**
 * Vinti4 for WooCommerce — Uninstall
 *
 * Runs when the plugin is deleted via WordPress admin.
 * Only removes plugin-specific settings. Does NOT delete:
 * - Orders
 * - Pages
 * - Posts
 * - Any other WooCommerce data
 *
 * @package Vinti4ForWooCommerce
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/*
 * Remove plugin settings only.
 *
 * This is the only option created by the gateway. WooCommerce order data,
 * pages, and all other content are left untouched.
 */
delete_option( 'woocommerce_vinti4_settings' );
