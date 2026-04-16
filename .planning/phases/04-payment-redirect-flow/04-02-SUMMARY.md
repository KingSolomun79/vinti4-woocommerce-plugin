---
phase: 04
plan: 02
name: Redirect form renderer
subsystem: payment-redirect-flow
tags:
  - php
  - wordpress
  - woocommerce
  - sisp
  - html-form
  - auto-submit
  - redirect
---

# Phase 4 Plan 2: Redirect Form Renderer Summary

Vinti4_Redirect_Form class with auto-submit HTML form that POSTs all SISP payment fields, wired into WordPress parse_request handler for /vinti4-payment/ endpoint.

## Dependency Graph

- **requires:** 04-01 (process_payment meta storage + rewrite endpoint)
- **provides:** Vinti4_Redirect_Form::render() — complete redirect page
- **affects:** 05-callback-handler (returns from SISP to complete/fail order)

## Tech Tracking

### tech-stack.added
None (pure PHP + WordPress APIs)

### tech-stack.patterns
- Standalone HTML document output via echo (bypasses WordPress theming)
- `exit` after render to short-circuit WordPress template loading
- Gateway instance lookup via `WC()->payment_gateways()->get_available_payment_gateways()`
- Order key validation for security (prevents unauthorized payment form access)

## Key Files

### key-files.created
- `includes/class-vinti4-redirect-form.php` — Vinti4_Redirect_Form class with render() method

### key-files.modified
- `vinti4.php` — Added require_once for redirect form, updated parse_request handler

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Create Vinti4_Redirect_Form class | 594d385 | includes/class-vinti4-redirect-form.php |
| 2 | Wire redirect form into parse_request handler | 8fd370d | vinti4.php |

## Decisions Made

1. **posAuthCode sent raw in form** — SISP expects the raw auth code in the POST body, not a hash. The fingerprint field already contains the pre-computed SHA-512+Base64 hash from process_payment().
2. **Standalone HTML document** — render() outputs a complete HTML page because it replaces normal WordPress theming. Uses `exit` to prevent any further WordPress processing.
3. **Dual query param validation** — Both `order` and `key` are required; missing either triggers wp_die() with 400 status.
4. **Order key security check** — Validates `$_GET['key']` against `$order->get_order_key()` to prevent unauthorized access to the payment form.
5. **Gateway null guard** — If the Vinti4 gateway is not available in the gateways collection, renders an error rather than crashing.

## Deviations from Plan

None — plan executed exactly as written.

## Next Phase Readiness

**Ready for:** Phase 5 — Callback Handler
**Why:** The redirect form is complete and will POST shoppers to SISP. Phase 5 needs to handle the SISP response callback (fingerprint verification, order completion/failure).

## Metrics

- **duration:** ~4 min
- **completed:** 2026-04-16
