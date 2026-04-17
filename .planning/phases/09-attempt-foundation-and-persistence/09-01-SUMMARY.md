---
phase: 09-attempt-foundation-and-persistence
plan: 01
subsystem: payments
tags: [php, woocommerce, order-meta, attempt-history, append-only]

# Dependency graph
requires:
  - phase: 03-fingerprint-request-builder
    provides: Canonical attempt payload generation via request builder
  - phase: 04-payment-redirect-flow
    provides: Legacy single-attempt _vinti4_* runtime keys used by redirect/callback
provides:
  - Append-only attempt history persistence on WooCommerce orders
  - Deterministic chronological retrieval for attempt history
  - Legacy projection helper to keep existing _vinti4_* reads working during migration
affects: [09-02 attempt factory, 10 admin partial request flow, 11 callback reconciliation]

# Tech tracking
tech-stack:
  added: []
  patterns: [append-only order meta history, compatibility projection for legacy order meta keys]

key-files:
  created: [includes/class-vinti4-attempt-store.php, tests/Test_Attempt_Store.php]
  modified: [vinti4.php, tests/bootstrap.php]

key-decisions:
  - "Use _vinti4_attempt_history as the canonical append-only persistence key for order attempts"
  - "Sort attempt enumeration by created_at_gmt with sequence tie-break to keep deterministic oldest-first order"
  - "Keep legacy _vinti4_* meta projection in the store so existing redirect/callback paths remain migration-safe"

patterns-established:
  - "Attempt Store Boundary: all attempt persistence concerns are centralized in Vinti4_Attempt_Store"
  - "Immutable History + Mutable Projection: history is append-only while latest projection updates compatibility keys"

# Metrics
duration: 3 min
completed: 2026-04-17
---

# Phase 9 Plan 1: Attempt Foundation Summary

**Append-only WooCommerce order attempt history with deterministic chronological reads and compatibility projection to legacy _vinti4_* keys**

## Performance

- **Duration:** 3 min
- **Started:** 2026-04-17T14:30:49Z
- **Completed:** 2026-04-17T14:34:09Z
- **Tasks:** 3
- **Files modified:** 4

## Accomplishments
- Added `Vinti4_Attempt_Store` as the single persistence boundary for append-only attempt history
- Added bootstrap wiring so the attempt store is available in runtime and PHPUnit contexts
- Added focused regression coverage for append-only writes, chronological reads, and latest-attempt projection behavior

## Task Commits

Each task was committed atomically:

1. **Task 1: Create append-only order attempt store** - `252aa0b` (feat)
2. **Task 2: Wire attempt store into runtime and test bootstrap** - `6994128` (chore)
3. **Task 3: Add persistence regression tests for append-only and chronological retrieval** - `09e8526` (test)

**Plan metadata:** `TBD` (docs: complete plan)

## Files Created/Modified
- `includes/class-vinti4-attempt-store.php` - Append/read/projection API for immutable order-scoped attempts
- `vinti4.php` - Runtime include wiring for attempt store
- `tests/bootstrap.php` - PHPUnit include wiring for attempt store
- `tests/Test_Attempt_Store.php` - Regression tests for append-only and chronological behavior

## Decisions Made
- Canonical history storage is `_vinti4_attempt_history`; legacy single-attempt keys are projection-only compatibility outputs
- Chronological enumeration is deterministic using `created_at_gmt` plus monotonic `sequence` tie-breaking
- Legacy key projection is implemented inside the attempt store to keep migration behavior explicit and centralized

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Verification commands required PHP CLI which is unavailable in this execution environment**
- **Found during:** Task 1/2/3 verification steps
- **Issue:** `php -l` and `vendor/bin/phpunit` could not run because `php` is not installed in PATH
- **Fix:** Completed implementation and test authoring, then performed static code verification; runtime verification is pending on a PHP-enabled environment
- **Files modified:** None
- **Verification:** Confirmed command failures (`php: command not found`, `/usr/bin/env: 'php': No such file or directory`)
- **Committed in:** N/A (environment limitation)

---

**Total deviations:** 1 blocking issue (environment)
**Impact on plan:** Implementation scope completed as planned; command-based verification must be rerun where PHP CLI is available.

## Issues Encountered
 - Local execution environment does not provide a `php` binary, so lint and PHPUnit commands could not be executed.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- Plan 09-02 can proceed using `Vinti4_Attempt_Store::append_attempt()` and `Vinti4_Attempt_Store::get_attempts()` as the persistence boundary.
- Before release confidence gates, rerun `php -l` and `vendor/bin/phpunit --filter "Test_Attempt_Store"` in a PHP-enabled environment.

---
*Phase: 09-attempt-foundation-and-persistence*
*Completed: 2026-04-17*
