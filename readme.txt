=== Vinti4 for WooCommerce ===
Contributors: vinti4
Tags: vinti4, payment, sisp, woocommerce, cape verde, cv
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.1
WC requires at least: 8.0
WC tested up to: 9.7
Stable tag: 1.0.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Accept payments via Vinti4 / SISP hosted payment page on your WooCommerce store, supporting Cape Verdean banks and currencies.

== Description ==

Vinti4 for WooCommerce integrates the SISP (Sociedade Interbancária e de Sistemas de Pagamentos) hosted payment page with your WooCommerce store, allowing customers in Cape Verde to pay using their bank's Vinti4 service.

**Features:**

*   Redirect to SISP 3DS secure payment page
*   Automatic order status updates on payment confirmation
*   Supports Cape Verde Escudo (CVE) and other SISP-supported currencies
*   Configurable via WooCommerce Settings > Payments

= Requirements =

*   WordPress 6.0 or higher
*   WooCommerce 8.0 or higher
*   PHP 8.1 or higher
*   A Vinti4 / SISP merchant account with POS credentials

== Installation ==

1. Upload the `vinti4-woocommerce-plugin` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to WooCommerce > Settings > Payments and enable Vinti4
4. Click "Manage" to configure your POS credentials

== Frequently Asked Questions ==

= Where do I find my POS credentials? =

Your POS ID (posId) and POS Authorization Code (posAuthCode) are provided by your bank when you register for Vinti4 merchant services.

= Which currencies are supported? =

The plugin primarily targets Cape Verde Escudo (CVE) but can work with any currency supported by your SISP merchant agreement. Currency can be auto-detected from the WooCommerce store settings or configured per transaction.

== Changelog ==

= 1.0.0 =

*   Initial release
*   Plugin bootstrap with WooCommerce dependency guards
*   Gateway registration via `woocommerce_payment_gateways` filter
*   Admin notice for missing WooCommerce dependency
*   Minimal gateway settings (enable/disable, title, description)
*   Safe uninstall (only removes plugin settings option)
