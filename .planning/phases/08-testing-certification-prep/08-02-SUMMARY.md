---
phase: 08-testing-certification-prep
plan: 02
subsystem: admin-diagnostics,certification
tags:
  - admin-panel
  - diagnostic-tests
  - certification-checklist
  - SISP
  - WooCommerce
---

# Phase 08 Plan 02: Admin Diagnostic Panel & Certification Checklist Summary

**One-liner:** Admin diagnostic panel with 12 self-tests (including callback simulation) and SISP certification checklist covering all 27 v1 requirements.

## Tasks Completed

| # | Task | Status | Commit | Files |
|---|------|--------|--------|-------|
| 1 | Create admin diagnostic panel + wire into gateway | ✅ | `aa9ac0e` | `includes/class-vinti4-admin-test-panel.php` (new), `includes/class-wc-gateway-vinti4.php` (edited) |
| 2 | Create SISP certification checklist | ✅ | `ed92eaa` | `docs/certification-checklist.md` (new) |

## What Was Built

### Task 1: Admin Diagnostic Panel (`Vinti4_Admin_Test_Panel`)

A WooCommerce admin submenu page ("Vinti4 Tests") that runs 12 self-diagnostic tests:

1. **Config Present** — Validates pos_id, pos_auth_code, vbv2_url are non-empty
2. **Logger Available** — Verifies Vinti4_Logger class and log() method exist
3. **Fingerprint Hash** — Confirms sha512_base64() returns valid 64-byte SHA-512 hash
4. **Currency Map** — CVE → 132 mapping via gateway get_currency_code()
5. **Success Types** — 8, 10, M, P → true; 0, 1 → false
6. **Logger Mask** — ABCDEFGHYZ → ABC***YZ (10-char code masking)
7. **Logger Mask Empty** — '' → '' (empty string passthrough)
8. **Callback Endpoint** — Rewrite rule ^vinti4-payment/?$ registered
9. **Gateway Registered** — WC_Gateway_Vinti4 in WooCommerce gateways
10. **Blocks Support** — WC_Vinti4_Blocks_Support extends AbstractPaymentMethodType
11. **Callback: Duplicate Detection** — Verifies _vinti4_callback_processed idempotency pattern
12. **Callback: Invalid Fingerprint** — Verifies fingerprint comparison logic in callback handler

Wired into gateway constructor with `is_admin()` guard. Uses nonce-protected form submission with transient-based result display.

### Task 2: Certification Checklist

`docs/certification-checklist.md` — 8-section checklist mapping all 27 v1 requirements to verification methods:
- **14 PHPUnit test references** (from Plan 08-01)
- **12 Admin panel test references** (from Task 1)
- **27 Code review references** with file and line-level specificity

CB-04 and CB-05 explicitly reference PHPUnit callback handler tests.

## Decisions Made

| Decision | Rationale |
|----------|-----------|
| Admin panel loaded in gateway constructor | Gateway is the central initialization point; avoids touching main plugin bootstrap file which is also being modified by Plan 08-01 in parallel |
| Callback simulation via source inspection | Can't execute wp_die()-using code in admin panel safely; reflection + source scan verifies patterns exist |
| Transient for result display | Avoids re-running tests on page refresh; 60-second expiry keeps results fresh |
| Nonce + redirect pattern | Standard WordPress form handling: POST processes, then redirects to display results |

## Deviations from Plan

None — plan executed exactly as written.

## Next Phase Readiness

Phase 8 Plan 02 is complete. Remaining work:
- **Plan 08-01** (running in parallel): PHPUnit test suite — when complete, all verification methods in the certification checklist will be executable
- **Certification**: The checklist sign-off table is ready for QA/SISP review once tests pass

## Dependency Graph

```
requires:
  - Phase 01–07 (all complete)
  - Plan 08-01 (parallel — PHPUnit tests referenced in checklist)

provides:
  - Admin diagnostic panel (12 self-tests)
  - Certification checklist (27 requirements mapped)

affects:
  - Final certification sign-off
```

## Tech Stack

- **patterns:** Admin submenu registration, nonce-protected form, transient-based results, reflection-based source inspection

## Key Files

### Created
- `includes/class-vinti4-admin-test-panel.php` — Vinti4_Admin_Test_Panel class (480 lines)
- `docs/certification-checklist.md` — SISP certification checklist (119 lines)

### Modified
- `includes/class-wc-gateway-vinti4.php` — Added admin panel loading in constructor (4 lines)

## Metrics

- **Duration:** ~2 min
- **Completed:** 2026-04-16

---

*SUMMARY created: 2026-04-16*
