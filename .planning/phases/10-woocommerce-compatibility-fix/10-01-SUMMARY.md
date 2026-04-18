---
phase: 10
plan: 01
subsystem: admin-partial-request
tags:
  - woocommerce
  - admin-meta-box
  - partial-payment
  - ajax
  - hpos-compat
requires:
  - 09-attempt-tracking
  - 11-callback-reconciliation
provides:
  - Vinti4_Admin_Partial_Payment class
  - Admin meta box for partial payment requests
  - AJAX endpoint for creating partial payment attempts
affects:
  - 10-02
tech-stack:
  added: []
  patterns:
    - static-class-meta-box-pattern
    - admin-ajax-with-nonce-verification
    - hpos-dual-registration
key-files:
  created:
    - includes/class-vinti4-admin-partial-payment.php
  modified:
    - vinti4.php
key-decisions:
  - HPOS compatibility via dual add_meta_box registration (shop_order + woocommerce_page_wc-orders)
  - Balance summary uses Vinti4_Attempt_Store static methods as single source of truth
  - Amount validation rejects anything exceeding outstanding + 0.01 tolerance
  - Logger require uncommented to fix missing dependency
patterns-established:
  - Admin meta box with inline JS for AJAX form submission
  - Copy-to-clipboard payment link UX pattern
duration: 3m
completed: 2026-04-18
---

# Phase 10 Plan 01: Admin Partial Payment Meta Box Summary

Admin meta box enabling store managers to request partial payments with fixed or percentage amounts, validated against outstanding balance, creating payment attempts and generating shareable payment links.

## Performance

- **Duration:** 3 minutes
- **Tasks:** 2/2 completed
- **Commits:** 2

## Accomplishments

1. Created `Vinti4_Admin_Partial_Payment` class (262 lines) with complete meta box, AJAX handler, and inline JS
2. Wired bootstrap to load class and register it in admin context
3. Fixed missing `Vinti4_Logger` dependency by uncommenting Phase 7 placeholder

## Task Commits

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Create Vinti4_Admin_Partial_Payment class | cd179df | includes/class-vinti4-admin-partial-payment.php |
| 2 | Register in bootstrap + fix logger dependency | 9495591 | vinti4.php |

## Files Created

- `includes/class-vinti4-admin-partial-payment.php` — Admin meta box class with balance summary, partial payment form (fixed/percentage modes), AJAX attempt creation, payment link generation, and copy-to-clipboard UX

## Files Modified

- `vinti4.php` — Added require for admin partial payment class, admin-guarded `register()` call, uncommented logger require

## Decisions Made

1. **HPOS dual registration:** Meta box registered for both `shop_order` (classic) and `woocommerce_page_wc-orders` (HPOS) screens to ensure compatibility with both order storage backends
2. **Outstanding tolerance:** Used `+ 0.01` tolerance on outstanding comparison to handle floating-point edge cases
3. **Logger dependency fix:** Uncommented the Phase 7 logger require since `Vinti4_Logger` is already used by `Vinti4_Attempt_Store::find_attempt_by_merchant_ref()` and callback handler — was a latent bug

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Fixed missing Vinti4_Logger dependency in bootstrap**

- **Found during:** Task 2 (bootstrap wiring)
- **Issue:** Logger require was commented out as a Phase 7 placeholder, but `Vinti4_Attempt_Store::find_attempt_by_merchant_ref()` already calls `Vinti4_Logger::log()` — this would cause a fatal error when attempt lookup fails
- **Fix:** Uncommented the require line in vinti4.php
- **Files modified:** vinti4.php
- **Commit:** 9495591

## Issues Encountered

- PHP CLI unavailable for `php -l` syntax verification (known environment limitation)
- LSP errors for WordPress/WooCommerce functions are false positives (functions available at runtime only)

## User Setup Required

None — plugin is self-contained. After activation, the meta box appears automatically on WooCommerce order edit pages.

## Next Phase Readiness

Plan 10-02 (if any) can build on this foundation:
- `Vinti4_Admin_Partial_Payment` is loaded and registered
- AJAX endpoint `vinti4_create_partial_request` is active in admin
- Payment links route through `/vinti4-payment/` with order+key params
- All attempt data flows through `Vinti4_Attempt_Store` append-only history
