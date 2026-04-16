---
status: testing
phase: 01-safe-bootstrap
source: 01-01-SUMMARY.md
started: 2026-04-16T12:00:00Z
updated: 2026-04-16T12:00:00Z
---

## Current Test

number: 1
name: Plugin activates without errors (WooCommerce active)
expected: |
  With WooCommerce installed and active, activating the Vinti4 plugin produces no PHP errors, warnings, or fatal errors. The plugin appears in the WordPress Plugins list as active.
awaiting: user response

## Tests

### 1. Plugin activates without errors (WooCommerce active)
expected: With WooCommerce installed and active, activating the Vinti4 plugin produces no PHP errors, warnings, or fatal errors. The plugin appears in the WordPress Plugins list as active.
result: [pending]

### 2. Missing WooCommerce shows admin notice
expected: With WooCommerce deactivated (or uninstalled), activating or running the Vinti4 plugin shows a dismissible admin error notice in the WordPress dashboard indicating WooCommerce is required. The plugin stays dormant — no gateway registered.
result: [pending]

### 3. Vinti4 appears in WooCommerce payment methods
expected: In WooCommerce → Settings → Payments, "Vinti4" appears as an available payment method with its title and description. It can be enabled/disabled and its settings accessed.
result: [pending]

### 4. Plugin deactivation is clean
expected: Deactivating the Vinti4 plugin removes it from active plugins without deleting any orders, pages, or posts. No orphaned data or errors.
result: [pending]

### 5. Uninstall only removes gateway settings
expected: After uninstalling the plugin (not just deactivating), only the `woocommerce_vinti4_settings` option is removed. No orders, pages, posts, or other plugin data is deleted.
result: [pending]

## Summary

total: 5
passed: 0
issues: 0
pending: 5
skipped: 0

## Gaps

[none yet]
