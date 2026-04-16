---
phase: 09-sisp-request-language-fix
plan: 01
subsystem: payments
tags: [php, wordpress, woocommerce, sisp, redirect, fingerprint, locale]
requires:
  - phase: 03-fingerprint-request-builder
    provides: Canonical payment attempt assembly and request fingerprint generation
  - phase: 04-payment-redirect-flow
    provides: Order-meta redirect handoff and hosted payment endpoint
provides:
  - Canonical SISP middleware contract fields in `build_payment_attempt()`
  - Gateway helpers for locale-based language resolution and callback URL generation
  - Persisted browser-safe redirect handoff meta for language, callback URL, 3DS flag, timestamp, and fingerprint version
affects: [09-02, 09-03, 11-callback-fingerprint-validation-hardening]
tech-stack:
  added: []
  patterns: [Canonical transport-field aliases in payment attempts, locale-to-middleware language mapping, browser-safe redirect handoff meta]
key-files:
  created: []
  modified:
    - includes/class-vinti4-request-builder.php
    - includes/class-wc-gateway-vinti4.php
key-decisions:
  - "Use locale-first resolution for `languageMessages`, with gateway setting and `pt` fallbacks."
  - "Keep `timeStamp`, `FingerPrint`, and `FingerPrintVersion` as canonical transport aliases in the server-side attempt."
  - "Persist redirect-only middleware fields on the order so rendering does not reconstruct them later."
patterns-established:
  - "Canonical attempt builder returns both internal identifiers and transport-named SISP fields."
  - "Gateway owns locale mapping and callback URL derivation for outbound middleware requests."
duration: 6 min
completed: 2026-04-16
---

# Phase 9 Plan 1: SISP Request Contract Summary

**Canonical SISP attempt data now carries locale-resolved middleware fields, redirect callback metadata, and persisted fingerprint transport aliases before checkout leaves WooCommerce.**

## Performance

- **Duration:** 6 min
- **Started:** 2026-04-16T20:58:24Z
- **Completed:** 2026-04-16T21:05:20Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- Expanded `Vinti4_Request_Builder::build_payment_attempt()` so one canonical array now includes `languageMessages`, `urlMerchantResponse`, `is3DSec`, `timeStamp`, `FingerPrint`, and `FingerPrintVersion` alongside the existing payment identifiers.
- Added gateway helpers to resolve `languageMessages` from the active locale first, then the saved gateway setting, and to centralize the SISP callback URL, 3DS flag, and fingerprint-version defaults.
- Updated `process_payment()` to persist the browser-safe redirect handoff contract directly on the order under explicit `_vinti4_*` keys, including the stored fingerprint version and callback URL.

## Task Commits

Each task was committed atomically:

1. **Task 1: Codify the full SISP request contract in the canonical attempt builder** - `9d2585a` (fix)
2. **Task 2: Persist the expanded attempt contract on the order for redirect rendering** - `593542e` (fix)

**Plan metadata:** Pending

## Files Created/Modified
- `includes/class-vinti4-request-builder.php` - Returns the expanded middleware contract from the canonical attempt builder and logs only masked/non-secret request context.
- `includes/class-wc-gateway-vinti4.php` - Resolves locale-aware language fields, exposes callback/3DS helpers, and persists the redirect handoff fields as order meta.

## Decisions Made
- Prefer real runtime locale mapping for `languageMessages` when it cleanly resolves to `pt` or `en`, then fall back to the saved gateway language, then `pt`.
- Keep transport-oriented field names explicit in the attempt payload so later redirect rendering can use stored values without reconstructing SISP field names.
- Persist only browser-safe redirect fields on the order; raw `posAuthCode` remains out of the attempt array and out of order meta.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

- `php -l` could not run in this environment because no local `php` executable is available and Docker's Linux engine was not running. Verification fell back to code review plus legacy-contract comparison for this session.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Ready for `09-02-PLAN.md` to switch `Vinti4_Redirect_Form::render()` to the persisted `_vinti4_*` handoff keys created here.
- Redirect rendering still needs to stop exposing `posAuthCode` client-side and align the outbound browser payload with the legacy middleware transport pattern.

---
*Phase: 09-sisp-request-language-fix*
*Completed: 2026-04-16*
