# Phase 2 Plan 2: Currency Helper Method Summary

**One-liner:** Added `get_currency_code()` method that auto-detects ISO 4217 currency from WooCommerce orders and maps to SISP numeric codes, with CVE fallback chain.

---
phase: 02-gateway-settings
plan: 02
subsystem: payment-gateway
tags: [php, woocommerce, currency, iso-4217, sisp]
completed: 2026-04-16
duration: ~1 min
---

## Dependency Graph

```
requires:
  - 02-01 (gateway settings fields and currency_default option)
provides:
  - get_currency_code() — order-aware currency detection with numeric code mapping
affects:
  - 03-fingerprint (uses currency code in fingerprint hash)
  - 04-request-builder (includes currency in payment request)
```

## Tech Stack

```
added: []
patterns:
  - ISO 4217 alpha-to-numeric mapping via static array
  - Order-first detection with setting fallback chain
```

## Key Files

```
created: []
modified:
  - includes/class-wc-gateway-vinti4.php (added get_currency_code method, lines 161-188)
```

## Tasks Completed

| Task | Name | Commit | Status |
|------|------|--------|--------|
| 1 | Add get_currency_code() method | 923ecef | Done |

## Decisions Made

1. **Static array for currency mapping** — No external dependency (no Composer). Map includes CVE (primary), EUR, USD, AOA (Angolan Kwanza for secondary market), BRL, GBP. Easily extendable.
2. **Triple fallback chain** — Order currency → currency_default setting → hardcoded CVE ('132'). Ensures SISP always receives a valid numeric code.
3. **String return type** — SISP protocol uses string numeric codes (e.g. '132'), not integers.
4. **is_a() type check** — Uses `is_a($order, 'WC_Order')` rather than type hint to match WooCommerce patterns and allow null parameter.

## Verification Results

- `get_currency_code()` method exists on `WC_Gateway_Vinti4` class
- CVE → '132', EUR → '978', USD → '840', AOA → '973', BRL → '986', GBP → '826'
- Auto-detects from `$order->get_currency()` when WC_Order provided
- Falls back to `currency_default` setting when no order or unmapped currency
- Ultimate fallback is CVE ('132')
- Returns string (not int) for SISP protocol compatibility
- Code style: tabs, WordPress patterns, 'vinti4' text domain

## Deviations from Plan

None — plan executed exactly as written.

## Next Phase Readiness

Phase 2 (Gateway Settings) is now **complete**. Both plans executed:
- 02-01: Settings fields, admin options, property loading
- 02-02: Currency helper method

Phase 3 (Fingerprint) can begin — `get_currency_code()` is available for fingerprint hash generation.
