# Phase 1 Plan 1: Safe Bootstrap Summary

---
phase: 01
plan: 01
subsystem: bootstrap
tags: [wordpress, woocommerce, payment-gateway, php]
completed: 2026-04-16
---

## One-liner

Plugin bootstrap with WooCommerce dependency guards, filter-based gateway registration, admin notices for missing WooCommerce, minimal gateway class, safe uninstall, and WordPress readme.

## What was built

Created the complete plugin foundation — 5 files that form the structural base for all subsequent phases:

1. **`vinti4.php`** — Main plugin file with constants, ABSPATH guard, unconditional admin notices loading, `plugins_loaded` (priority 20) hook with WooCommerce dependency check, filter-based gateway registration, and commented-out require stubs for future phases.

2. **`includes/class-vinti4-admin-notices.php`** — Static class that registers an `admin_notices` hook to display a dismissible error notice when WooCommerce is missing, with a runtime double-check to avoid showing stale notices.

3. **`includes/class-wc-gateway-vinti4.php`** — Minimal `WC_Payment_Gateway` subclass with string ID `vinti4`, constructor that calls `init_form_fields`/`init_settings`, three basic form fields (enabled, title, description), admin options save hook, and a `process_payment()` stub returning failure.

4. **`uninstall.php`** — Safe uninstall with `WP_UNINSTALL_PLUGIN` guard that only deletes `woocommerce_vinti4_settings` option. No order/page/post deletion.

5. **`readme.txt`** — Standard WordPress plugin readme with metadata, description, installation instructions, FAQ, and changelog.

## Dependency Graph

```
requires:
  - WordPress 6.0+
  - WooCommerce 8.0+
  - PHP 8.1+

provides:
  - Plugin bootstrap with dependency guards
  - Gateway class skeleton (WC_Gateway_Vinti4)
  - Admin notice system for missing WooCommerce
  - Safe uninstall behavior

affects:
  - Phase 2 (Settings) will expand init_form_fields()
  - Phase 3 (Fingerprint) will add formatting/fingerprint/request classes
  - Phase 4 (Redirect) will implement process_payment()
  - Phase 5 (Callback) will add handle_callback() method
  - Phase 6 (Blocks) will add blocks support class
  - Phase 7 (Logging) will add logger class
```

## Tech Stack

```
added:
  - (none — pure PHP, no dependencies)

patterns:
  - WordPress plugin bootstrap with ABSPATH guard
  - WooCommerce gateway registration via woocommerce_payment_gateways filter
  - Dependency guard pattern (class_exists before loading)
  - Static admin notice class with runtime re-check
```

## Key Files

```
created:
  - vinti4.php                          # Plugin entry point
  - includes/class-vinti4-admin-notices.php   # Missing WooCommerce notice
  - includes/class-wc-gateway-vinti4.php      # Gateway class skeleton
  - uninstall.php                       # Safe uninstall
  - readme.txt                          # WordPress plugin readme

modified: []
```

## Decisions Made

1. **Filter-based registration only** — Gateway registered via `woocommerce_payment_gateways` filter with `class_exists` guard. No direct instantiation (`new WC_Gateway_Vinti4`) anywhere.

2. **Unconditional admin notices loading** — The admin notices class is loaded outside `plugins_loaded` so it can register its hook even when WooCommerce is absent.

3. **Commented-out require stubs** — Future phase files are pre-mapped as commented `require_once` lines with phase annotations, making it clear what gets loaded when.

4. **process_payment() returns failure** — Stub returns `'result' => 'failure'` to prevent accidental order processing before the real redirect is implemented.

5. **WP_UNINSTALL_PLUGIN guard** — uninstall.php uses the WordPress-standard guard rather than ABSPATH, only deletes the gateway settings option.

## Task Results

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Plugin bootstrap and admin notices | 5f7dba6 | vinti4.php, includes/class-vinti4-admin-notices.php |
| 2 | Minimal gateway class, uninstall, readme | deb53d6 | includes/class-wc-gateway-vinti4.php, uninstall.php, readme.txt |

## Deviations from Plan

None — plan executed exactly as written.

## Next Phase Readiness

**Ready for Phase 2 (Gateway Settings):**
- Gateway class has `init_form_fields()` with minimal fields ready for expansion
- Constructor loads settings into properties and has admin save hook
- No blockers or concerns
