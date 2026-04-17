---
phase: 09-attempt-foundation-and-persistence
plan: 02
subsystem: payments
tags: [php, woocommerce, attempt-factory, fingerprint, append-only]

# Dependency graph
requires:
  - phase: 09-attempt-foundation-and-persistence
    provides: Append-only attempt persistence boundary and latest-attempt projection helpers
provides:
  - Canonical attempt creation service with explicit amount/context inputs
  - Request builder support for attempt-scoped amount fingerprinting
  - Gateway append-only checkout persistence plus reusable attempt creation entrypoint
affects: [10 admin partial request flow, 11 callback reconciliation]

# Tech tracking
tech-stack:
  added: []
  patterns: [factory-driven attempt creation, amount-context fingerprint binding, append-only persistence integration]

key-files:
  created: [includes/class-vinti4-attempt-factory.php, tests/Test_Attempt_Factory.php]
  modified: [includes/class-vinti4-request-builder.php, includes/class-wc-gateway-vinti4.php, vinti4.php, tests/bootstrap.php, tests/Test_Request_Builder.php]

key-decisions:
  - "Attempt creation is centralized in Vinti4_Attempt_Factory with injectable providers for deterministic tests"
  - "Request builder accepts explicit attempt context so fingerprint amount is sourced from attempt input instead of implicit order totals"
  - "Gateway checkout path appends attempts through Vinti4_Attempt_Store and never overwrites history directly"

patterns-established:
  - "Factory + Store split: factory creates immutable attempts, store owns persistence/projection concerns"
  - "Attempt-scoped fingerprinting: amount/timestamp/reference/session are explicit context inputs"

# Metrics
duration: 6 min
completed: 2026-04-17
---

# Phase 9 Plan 2: Attempt Factory and Persistence Summary

**Attempt-scoped factory generation now creates unique merchant identifiers and amount-bound fingerprints while checkout appends immutable attempt history for future admin-triggered retries.**

## Performance

- **Duration:** 6 min
- **Started:** 2026-04-17T14:36:36Z
- **Completed:** 2026-04-17T14:42:22Z
- **Tasks:** 3
- **Files modified:** 7

## Accomplishments
- Added `Vinti4_Attempt_Factory` as the canonical service that builds complete attempts from explicit order/amount/context input.
- Updated `Vinti4_Request_Builder::build_payment_attempt()` to accept explicit attempt context and bind fingerprint amount to that context.
- Refactored gateway checkout flow to create attempts through factory and persist via append-only `Vinti4_Attempt_Store::append_attempt()`.
- Added regression tests validating unique merchant identifiers, amount-bound fingerprint behavior, and append/enumerate history safety.

## Task Commits

Each task was committed atomically:

1. **Task 1: Build canonical attempt factory for order + amount context** - `913451f` (feat)
2. **Task 2: Integrate process_payment with append-only attempt persistence** - `e90f0a6` (feat)
3. **Task 3: Add regression tests for uniqueness and amount-bound fingerprint context** - `98686ff` (test)

**Plan metadata:** `TBD` (docs: complete plan)

## Files Created/Modified
- `includes/class-vinti4-attempt-factory.php` - Canonical attempt generation service with explicit amount/context and unique merchant identifiers.
- `includes/class-vinti4-request-builder.php` - Attempt-context-aware payload builder using explicit amount for fingerprint input.
- `includes/class-wc-gateway-vinti4.php` - Checkout integration now appends attempts via store and exposes reusable `create_payment_attempt()`.
- `vinti4.php` - Runtime include wiring for the new attempt factory service.
- `tests/bootstrap.php` - Test bootstrap wiring updated to include attempt factory and deterministic password queueing.
- `tests/Test_Attempt_Factory.php` - New regression suite for uniqueness, amount context, and append-only history behavior.
- `tests/Test_Request_Builder.php` - Extended with focused amount-context fingerprint regression coverage.

## Decisions Made
- Introduced a dedicated factory boundary (`Vinti4_Attempt_Factory`) instead of creating attempts directly in gateway logic to support future admin-triggered attempt creation.
- Kept merchant reference compatibility with `WC{order_id}-...` while adding entropy to prevent collisions for near-simultaneous attempts.
- Preserved checkout redirect contract by continuing to rely on store projection to `_vinti4_*` latest-attempt keys.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] PHP CLI unavailable for lint and PHPUnit verification commands**
- **Found during:** Task 1, Task 2, and Task 3 verification
- **Issue:** `php -l` and `vendor/bin/phpunit` could not execute because `php` is unavailable in PATH
- **Fix:** Completed implementation and test authoring; performed static code review verification for required behaviors
- **Files modified:** None
- **Verification:** Command failures observed (`php: command not found`, `/usr/bin/env: 'php': No such file or directory`)
- **Committed in:** N/A (environment limitation)

---

**Total deviations:** 1 auto-fixed (1 blocking)
**Impact on plan:** Implementation and test coverage changes completed as planned; command-based verification must be rerun in a PHP-enabled environment.

## Issues Encountered
- Local execution environment does not include `php`, preventing command-based lint/test execution.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- Phase 10 can invoke `WC_Gateway_Vinti4::create_payment_attempt()` for admin-driven attempt generation without re-running checkout internals.
- Before release confidence gates, rerun `php -l` and `vendor/bin/phpunit --filter "Test_Attempt_Factory|Test_Request_Builder"` in a PHP-enabled environment.

---
*Phase: 09-attempt-foundation-and-persistence*
*Completed: 2026-04-17*
