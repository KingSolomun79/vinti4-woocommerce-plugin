---
phase: 09-sisp-request-language-fix
plan: 02
subsystem: payments
tags: [php, wordpress, woocommerce, sisp, redirect, phpunit, request-contract]
requires:
  - phase: 04-payment-redirect-flow
    provides: Hosted redirect form endpoint and standalone payment page renderer
  - phase: 09-sisp-request-language-fix
    provides: Persisted `_vinti4_*` middleware handoff fields on the order
provides:
  - Redirect form markup driven directly from persisted canonical `_vinti4_*` attempt meta
  - Browser payload with `languageMessages`, callback URL, and 3DS flag in the POST body
  - Regression tests for request-builder output, redirect payload shape, and secret non-exposure
affects: [09-03, 10-woocommerce-feature-compatibility-declarations, 13-verification-coverage-and-test-truthfulness]
tech-stack:
  added: []
  patterns: [Query-string transport for fingerprint fields, PHPUnit-safe redirect rendering, explicit redirect contract assertions]
key-files:
  created:
    - tests/Test_Request_Builder.php
    - tests/Test_Redirect_Form.php
  modified:
    - includes/class-vinti4-redirect-form.php
    - tests/bootstrap.php
    - tests/Test_Callback_Handler.php
key-decisions:
  - "Send `FingerPrint`, `TimeStamp`, and `FingerPrintVersion` via the redirect action query string while keeping the browser POST body limited to browser-safe fields."
  - "Render only from persisted `_vinti4_*` handoff meta so the redirect page cannot drift from the canonical attempt built in `process_payment()`."
  - "Short-circuit `exit` under PHPUnit to capture redirect HTML directly in regression tests."
patterns-established:
  - "Redirect renderer consumes saved order-meta contract instead of recomputing transport fields."
  - "Payment redirect tests assert exact field names and reject raw secret leakage in both markup and query params."
duration: 5 min
completed: 2026-04-16
---

# Phase 9 Plan 2: SISP Redirect Payload Summary

**The hosted redirect page now submits the canonical SISP middleware payload with `languageMessages`, callback metadata, and query-string fingerprint fields, backed by PHPUnit checks that block field-name drift and client-side secret exposure.**

## Performance

- **Duration:** 5 min
- **Started:** 2026-04-16T21:11:00Z
- **Completed:** 2026-04-16T21:15:43Z
- **Tasks:** 3
- **Files modified:** 5

## Accomplishments
- Refactored `Vinti4_Redirect_Form::render()` to read the persisted `_vinti4_*` handoff contract and emit the corrected browser-safe SISP POST fields.
- Moved `FingerPrint`, `TimeStamp`, and `FingerPrintVersion` into the redirect action query string, added `languageMessages`, `urlMerchantResponse`, and `is3DSec` hidden inputs, and removed the old `lang` field plus raw `posAuthCode` exposure.
- Added PHPUnit coverage for both canonical attempt-building and final redirect markup, then verified the targeted request-shape tests and the full suite both pass cleanly.

## Task Commits

Each task was committed atomically:

1. **Task 1: Render the corrected SISP browser request from stored canonical meta** - `1cd30c9` (fix)
2. **Task 2: Add PHPUnit coverage for attempt-building and rendered redirect payload** - `191d0c6` (test)
3. **Task 3: Run the phase-level automated regression checks** - `f743c31` (test)

**Plan metadata:** Pending (created in the docs commit that includes this summary)

## Files Created/Modified
- `includes/class-vinti4-redirect-form.php` - Reads only persisted redirect-contract meta, moves fingerprint transport fields into the action URL, and removes client-side secret output.
- `tests/bootstrap.php` - Adds pure-PHP WordPress/WooCommerce stubs needed to render the redirect form and exercise locale-aware request building.
- `tests/Test_Request_Builder.php` - Verifies `build_payment_attempt()` returns the expanded browser-safe handoff contract and language fallback behavior without leaking `posAuthCode`.
- `tests/Test_Redirect_Form.php` - Asserts exact redirect field names, action query params, `_vinti4_*` meta consumption, and secret non-exposure in rendered HTML.
- `tests/Test_Callback_Handler.php` - Declares the callback test-double auth-code property so the full PHPUnit suite stays clean on PHP 8.2.

## Decisions Made
- Keep the SISP redirect query/body split explicit: browser-safe identifiers stay in hidden inputs, while `FingerPrint`, `TimeStamp`, and `FingerPrintVersion` ride on the action URL.
- Treat the `_vinti4_*` meta written by `process_payment()` as the only render-time source of truth for redirect payload generation.
- Add a PHPUnit-only `exit` bypass in the renderer so redirect HTML can be asserted directly without weakening production behavior.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Removed a PHP 8.2 deprecation from the existing callback test double**
- **Found during:** Task 3 (Run the phase-level automated regression checks)
- **Issue:** The full PHPUnit suite still emitted a dynamic-property deprecation from `tests/Test_Callback_Handler.php`, which meant the verification loop did not finish cleanly.
- **Fix:** Declared `pos_auth_code` on the anonymous callback gateway stub.
- **Files modified:** `tests/Test_Callback_Handler.php`
- **Verification:** `C:/tools/php/php.exe vendor/bin/phpunit --display-deprecations` returned `OK (31 tests, 79 assertions)` with no remaining deprecations.
- **Committed in:** `f743c31` (part of Task 3 commit)

---

**Total deviations:** 1 auto-fixed (1 blocking)
**Impact on plan:** The fix was required to complete the requested verification loop cleanly on PHP 8.2. No scope creep.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Ready for `09-03-PLAN.md` to verify the corrected request shape in a real WooCommerce sandbox checkout.
- The live checkout blocker should now be resolved in code, but Phase 9 remains open until real browser/SISP verification confirms the middleware no longer rejects missing `languageMessages`.

---
*Phase: 09-sisp-request-language-fix*
*Completed: 2026-04-16*
