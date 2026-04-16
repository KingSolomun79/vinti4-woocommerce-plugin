---
phase: 07-logging-diagnostics
plan: 02
subsystem: infra
tags: [logging, diagnostics, callback, request-builder, security]

requires:
  - phase: 07-logging-diagnostics
    provides: "Vinti4_Logger class with log(), mask_auth_code(), init(), is_enabled()"
provides:
  - "Structured logging in request builder with masked auth code"
  - "Config validation error logging in gateway"
  - "10 validation checkpoint logs in callback handler"
affects: [08-testing]

tech-stack:
  added: []
  patterns: ["Multi-point logging at every validation branch", "Structured multi-line log messages with sprintf"]

key-files:
  created: []
  modified: [includes/class-vinti4-request-builder.php, includes/class-vinti4-callback-handler.php, includes/class-wc-gateway-vinti4.php]

key-decisions:
  - "Request builder assigns return array to $result variable before logging, then returns $result — keeps log+return separate"
  - "Callback handler logs BEFORE each terminal action (wp_die, redirect, status update) so the log entry is guaranteed to fire"
  - "Config validation error mentions setting names (pos_id, pos_auth_code, vbv2_url) in message text but never logs actual values"

patterns-established:
  - "Log-before-terminal-action: every validation branch logs immediately before its wp_die/redirect/status_update"
  - "Context-rich sprintf logging: order_id, merchantRef, messageType included for cross-reference"

duration: 2min
completed: 2026-04-16
---

# Phase 7 Plan 02: Logging Calls Summary

**Structured logging at all payment touchpoints: request builder (masked auth), gateway (config errors), callback handler (10 validation checkpoints)**

## Performance
- **Duration:** ~2 min
- **Started:** 2026-04-16T15:54:30Z
- **Completed:** 2026-04-16T15:56:36Z
- **Tasks:** 2
- **Files modified:** 3

## Accomplishments
- Request builder logs every payment attempt with attempt_id, order_id, merchantRef, timestamp, amount, currency, masked auth code, fingerprint
- Gateway logs config validation failures at 'error' level
- Callback handler logs at all 10 decision points: receipt, invalid data, invalid ref, order not found, ref mismatch, duplicate, fingerprint mismatch, amount mismatch, success, failure
- Full POS auth code never appears in any log entry

## Task Commits
1. **Task 1: Add logging to request builder and gateway process_payment** - `3f88860` (feat)
2. **Task 2: Add logging to callback handler at each validation step** - `4c5fe20` (feat)

**Plan metadata:** (pending)

## Files Created/Modified
- `includes/class-vinti4-request-builder.php` - Attempt data logging with masked auth code
- `includes/class-wc-gateway-vinti4.php` - Config validation error logging
- `includes/class-vinti4-callback-handler.php` - 10 validation checkpoint logs at every branch

## Decisions Made
- Request builder assigns return array to `$result` variable before logging, then returns `$result` — keeps log call and return statement cleanly separated
- Callback handler logs BEFORE each terminal action (wp_die, redirect, status_update) so the log entry is guaranteed to fire even if the terminal action fails
- Config validation error message mentions setting names (pos_id, pos_auth_code, vbv2_url) in text but never logs actual credential values

## Deviations from Plan
None - plan executed exactly as written.

## Issues Encountered
None

## Next Phase Readiness
- All logging calls wired across request builder, gateway, and callback handler
- Phase 7 complete, ready for Phase 8 (Testing & Certification Prep)

---
*Phase: 07-logging-diagnostics*
*Completed: 2026-04-16*
