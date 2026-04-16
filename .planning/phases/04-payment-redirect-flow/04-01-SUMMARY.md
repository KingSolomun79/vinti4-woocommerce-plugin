---
phase: "04"
plan: "01"
subsystem: "payment-redirect"
tags: ["process_payment", "order-meta", "rewrite-endpoint", "config-validation", "sisp-redirect"]
---

# Phase 04 Plan 01: Payment Redirect Endpoint Summary

**process_payment() with config validation, attempt storage, and /vinti4-payment/ redirect endpoint**

## One-Liner

Implemented process_payment() that validates gateway config, builds a canonical SISP payment attempt via the request builder, stores all 9 fields as order meta, and redirects to /vinti4-payment/{order_id}/. Registered the WordPress rewrite endpoint that will host the auto-posting payment form.

## Dependency Graph

- **requires:** Phase 03 (Vinti4_Request_Builder, Vinti4_Fingerprint, formatting helpers)
- **provides:** Working process_payment() flow, /vinti4-payment/ rewrite endpoint, order meta storage
- **affects:** Phase 04 Plan 02 (payment form rendering), Phase 05 (callback handler)

## Tech Stack

- **patterns:** WooCommerce process_payment() contract, WordPress rewrite API, order meta storage

## Key Files

### Created

None.

### Modified

- `includes/class-wc-gateway-vinti4.php` — Replaced stub process_payment() with full implementation: config validation (pos_id, pos_auth_code, vbv2_url), Vinti4_Request_Builder::build_payment_attempt() call, 9-field order meta storage, redirect URL generation to /vinti4-payment/
- `vinti4.php` — Added vinti4_add_rewrite_rules() for /vinti4-payment/ endpoint, vinti4_handle_payment_page() parse_request handler, register_activation_hook/register_deactivation_hook for rewrite flush

## Decisions Made

1. **Config validation before attempt building** — Check pos_id, pos_auth_code, vbv2_url are non-empty before calling the request builder. This prevents opaque errors from the fingerprint computation and gives a clear user-facing message.
2. **wc_add_notice() for user errors** — Both invalid-order and incomplete-config cases use wc_add_notice() with 'error' type, which WooCommerce displays naturally at checkout. No wp_die() or exceptions.
3. **Dual query args on redirect URL** — The redirect includes both `order` (ID) and `key` (order key) parameters. The key provides a security check that the request is legitimate (same pattern WooCommerce uses for order-received pages).
4. **parse_request with URI fallback** — The handler checks both $wp->query_vars['vinti4_payment'] and REQUEST_URI as a safety net in case the rewrite rule hasn't been flushed yet.
5. **Placeholder handler for form rendering** — vinti4_handle_payment_page() returns 200 status but defers form rendering to Plan 02. This lets the endpoint be tested without a 404.

## Must-Haves Verification

| # | Truth | Status |
|---|-------|--------|
| 1 | process_payment() returns result=success with valid redirect URL when config is complete | ✅ Verified: lines 244-248 return success with /vinti4-payment/ URL |
| 2 | process_payment() returns result=failure with wc_add_notice error when POS ID, Auth Code, or URL is missing | ✅ Verified: line 210 returns failure after wc_add_notice |
| 3 | All 9 attempt fields are stored on the order as meta before redirect | ✅ Verified: 9 update_meta_data() calls + save() on lines 221-230 |
| 4 | The redirect URL points to a WordPress endpoint that includes the order ID | ✅ Verified: add_query_arg('order' => $order_id, ...) on line 233 |

## Deviations from Plan

None — plan executed exactly as written.

## Authentication Gates

None.

## Next Phase Readiness

- **Phase 04 Plan 02** can proceed: The /vinti4-payment/ endpoint is registered and intercepted. Plan 02 needs to implement vinti4_handle_payment_page() to load the order, verify the key, and render the auto-posting HTML form with all SISP fields.
- **Note:** After installing this plugin, users must visit Settings → Permalinks and click Save (or run `wp rewrite flush`) to activate the rewrite rule. This is standard WordPress behavior.
