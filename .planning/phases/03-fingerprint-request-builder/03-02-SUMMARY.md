---
phase: 03-fingerprint-request-builder
plan: 02
subsystem: request-builder
tags: [php, woocommerce, sisp, 3ds, fingerprint, payment-request]
completed: 2026-04-16
duration: ~2 min
---

# Phase 3 Plan 2: Request Builder & Bootstrap Wiring Summary

One-liner: Created Vinti4_Request_Builder with canonical build_payment_attempt() entry point that orchestrates fingerprint, formatting helpers, and gateway settings into a single consistent SISP payment request output; wired all Phase 3 files into the bootstrap.

## Dependency Graph

```
requires:
  - 03-01 (Fingerprint class + formatting helpers)

provides:
  - Vinti4_Request_Builder class with build_payment_attempt() and build_purchase_request_json()
  - Single canonical code path for SISP redirect data generation (PAY-02)

affects:
  - Phase 4 (redirect flow will call Vinti4_Request_Builder::build_payment_attempt())
  - Phase 5 (callback handler may parse merchantRef via vinti4_parse_order_id_from_ref)
```

## Tech Stack

```
added: none (uses existing WP/WC APIs)

patterns:
  - Static builder class pattern (single entry point)
  - Canonical code path for request generation (PAY-02)
  - 3DS purchaseRequest JSON assembly from WC_Order data
```

## Key Files

```
created:
  - includes/class-vinti4-request-builder.php

modified:
  - vinti4.php (uncommented Phase 3 require lines)
```

## Tasks Completed

| # | Name | Commit | Files |
|---|------|--------|-------|
| 1 | Create Vinti4_Request_Builder class | 45a877c | includes/class-vinti4-request-builder.php |
| 2 | Wire Phase 3 requires in bootstrap | bcae6af | vinti4.php |

## Decisions Made

1. **Static builder methods** — Both `build_payment_attempt()` and `build_purchase_request_json()` are static since they operate on injected `$order` and `$gateway` with no instance state needed.
2. **UUID4 for attempt_id** — Uses `wp_generate_uuid4()` for attempt tracking uniqueness without coupling to any external storage.
3. **Transaction code hardcoded to '1'** — SISP authorization code; future phases may parameterize if needed.
4. **Phone block reuses billing phone** — WooCommerce doesn't distinguish work/mobile phones, so both fields get the same shaped number.
5. **addrMatch compares 4 fields** — address_1, city, postcode, and country are compared; state is excluded from match since WC state codes may differ in format.
6. **Account info best-effort** — chAccDate left empty (WC doesn't track account creation in order context); chAccAgeInd uses '05' for registered, '01' for guest.

## Deviations from Plan

None — plan executed exactly as written.

## Verification Results

| Check | Status |
|-------|--------|
| PHP syntax valid (code review) | ✅ |
| Vinti4_Request_Builder class with build_payment_attempt() public static | ✅ |
| build_purchase_request_json() private static | ✅ |
| Single canonical fingerprint path (Vinti4_Fingerprint::build_request_fingerprint) | ✅ |
| No purchaseDate field in purchaseRequest JSON | ✅ |
| merchantRef format WC{id}-YYYYMMDDHHmmss | ✅ |
| merchantSession starts with 'S' | ✅ |
| transaction_code hardcoded to '1' | ✅ |
| addrMatch compares billing/shipping addresses | ✅ |
| Uses vinti4_format_timestamp, vinti4_build_merchant_ref, vinti4_build_merchant_session, vinti4_normalize_amount | ✅ |
| Uses $gateway->get_currency_code($order) | ✅ |
| vinti4.php loads all Phase 3 files unconditionally | ✅ |
| Phase 6 & 7 require lines remain commented | ✅ |
| No duplicate require lines | ✅ |

## Next Phase Readiness

Phase 3 is now **complete**. All building blocks for the redirect flow are in place:
- Formatting helpers (amount, timestamp, merchantRef, merchantSession, phone)
- Fingerprint builder (SHA-512 + Base64 with exact SISP field ordering)
- Request builder (canonical payment attempt assembly)

**Phase 4** (Redirect Flow) can begin — it will call `Vinti4_Request_Builder::build_payment_attempt()` from `process_payment()` and render the redirect form.
