---
phase: 11-callback-reconciliation-and-compatibility-verification
plan: 01
subsystem: payments
tags: [php, woocommerce, callback, attempt-resolution, idempotency, partial-payments]

# Dependency graph
requires:
  - phase: 09-attempt-foundation-and-persistence
    provides: Append-only attempt history and latest-attempt projection helpers
provides:
  - Attempt-level callback resolution with per-attempt idempotency
  - Paid/outstanding totals tracking across multiple payment attempts
  - Partial payment completion with outstanding balance check
  - Legacy fallback for pre-1.1 orders without attempt history
affects: [11-02 compatibility verification]

# Tech tracking
tech-stack:
  added: []
  patterns: [attempt-level callback resolution, per-attempt idempotency, partial payment totals tracking, legacy fallback path]

key-files:
  created: []
  modified: [includes/class-vinti4-callback-handler.php, includes/class-vinti4-attempt-store.php, tests/Test_Callback_Handler.php]

key-decisions:
  - "Callback resolves attempt by merchantRef before any order mutation — attempt history is the source of truth"
  - "Per-attempt idempotency via _vinti4_attempt_{attempt_id}_processed meta keys replaces order-level _vinti4_callback_processed"
  - "Partial payments keep order in processing status; order completes only when outstanding_total <= 0.01"
  - "Legacy callback path preserved for orders without _vinti4_attempt_history — uses order-level validation"

patterns-established:
  - "Attempt-first resolution: find attempt in history, validate against attempt context, fall back to legacy if no history"
  - "Per-attempt processed flag: each attempt tracks its own processed state independently"

# Metrics
duration: 4 min
completed: 2026-04-17
---

# Phase 11 Plan 1: Callback Reconciliation Summary

**Attempt-level callback resolution with per-attempt idempotency and partial payment tracking, preserving legacy card-flow compatibility via fallback path.**

## Performance

- **Duration:** 4 min
- **Started:** 2026-04-17T21:47:53Z
- **Completed:** 2026-04-17T21:51:40Z
- **Tasks:** 3
- **Files modified:** 3

## Accomplishments
- Added `find_attempt_by_merchant_ref()` to Vinti4_Attempt_Store for exact attempt resolution from order history.
- Added `get_paid_total()` and `get_outstanding_total()` for computing paid and outstanding balances across completed attempts.
- Added `mark_attempt_completed()` and `mark_attempt_failed()` for updating attempt status in append-only history.
- Refactored callback handler to resolve attempts by merchantRef before any order mutation.
- Implemented per-attempt idempotency via `_vinti4_attempt_{attempt_id}_processed` meta keys.
- Added attempt-scoped diagnostic logging for all validation failure paths (session mismatch, fingerprint mismatch, amount mismatch).
- Implemented partial payment handling: order completes only when outstanding balance <= 0.01.
- Added legacy fallback path for pre-1.1 orders without attempt history.
- Added comprehensive regression tests for attempt resolution, per-attempt idempotency, partial payments, and spoofed callback rejection.

## Task Commits

Each task was committed atomically:

1. **Task 2: Add attempt lookup and paid/outstanding totals tracking to Attempt_Store** - `561bf8e` (feat)
2. **Task 1: Add attempt-level resolution and per-attempt idempotency to callback handler** - `334e176` (feat)
3. **Task 3: Add attempt-level reconciliation regression tests** - `2786e5c` (test)

**Plan metadata:** `TBD` (docs: complete plan)

## Files Created/Modified
- `includes/class-vinti4-attempt-store.php` - Added 5 new static methods: find_attempt_by_merchant_ref, get_paid_total, get_outstanding_total, mark_attempt_completed, mark_attempt_failed.
- `includes/class-vinti4-callback-handler.php` - Full refactor: attempt-level resolution, per-attempt idempotency, attempt-scoped validation, partial payment totals, legacy fallback path.
- `tests/Test_Callback_Handler.php` - Added 7 new attempt-level tests plus preserved 7 existing legacy tests.

## Decisions Made
- Attempt resolution happens before any order mutation — the attempt history array is the canonical source of truth for callback matching.
- Per-attempt idempotency uses `_vinti4_attempt_{attempt_id}_processed` meta keys instead of order-level `_vinti4_callback_processed`.
- Partial payments set order to 'processing' status with a detailed order note; `payment_complete()` is only called when `outstanding_total <= 0.01`.
- Legacy orders (no `_vinti4_attempt_history`) fall back to the original order-level validation path.

## Deviations from Plan

None — plan executed exactly as written.

## Issues Encountered
- PHP CLI is unavailable in the current execution environment; PHPUnit tests must be rerun in a PHP-enabled environment.

## User Setup Required

None — no external service configuration required.

## Next Phase Readiness
- Phase 11 Plan 2 (compatibility verification) can verify the callback flow end-to-end using the attempt-level reconciliation.
- Before release confidence gates, rerun `php -l` on modified files and `vendor/bin/phpunit --filter "Test_Callback_Handler|Test_Attempt_Store"` in a PHP-enabled environment.

---
*Phase: 11-callback-reconciliation-and-compatibility-verification*
*Completed: 2026-04-17*
