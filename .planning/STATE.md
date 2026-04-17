# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-04-17)

**Core value:** A shopper can select Vinti4 at WooCommerce checkout, be redirected securely to SISP's 3DS payment page, and return to a correctly-completed or correctly-failed order — every time, without fingerprint mismatches, duplicate completions, or fatal errors.
**Current focus:** Phase 9 attempt foundation complete — ready for Phase 10 compatibility flow work

## Current Position

Phase: 9 of 11 (Attempt Foundation and Persistence)
Plan: 2 of 2 in current phase
Status: Phase complete
Last activity: 2026-04-17 — Completed 09-02-PLAN.md

Progress: █████████░ 96%

## Performance Metrics

**Velocity:**
- Total plans completed: 12
- Average duration: ~4 min
- Total execution time: ~48 min

**By Phase:**

| Phase | Plans | Total | Avg/Plan |
|-------|-------|-------|----------|
| 01-safe-bootstrap | 1 | ~17 min | ~17 min |
| 02-gateway-settings | 2 | ~3 min | ~1.5 min |
| 03-fingerprint-request-builder | 2 | ~4 min | ~2 min |
| 04-payment-redirect-flow | 2 | ~9 min | ~4.5 min |
| 05-callback-idempotency | 3 | ~8 min | ~2.7 min |
| 06-checkout-block-support | 1 | ~1 min | ~1 min |

**Recent Trend:**
- Last 5 plans: 06-01 (~1 min), 07-01 (~5 min), 07-02 (~5 min), 09-01 (~3 min), 09-02 (~6 min)
- Trend: Stable — attempt persistence/factory migration completed with expected complexity

*Updated after each plan completion*

## Accumulated Context

### Decisions

Decisions are logged in PROJECT.md Key Decisions table. Recent decisions affecting current work:

- Init: Gateway ID is `vinti4` (string, not numeric)
- Init: Hosted redirect flow (not API-based)
- Init: Currency auto-detect from order, default CVE
- Init: No Composer dependencies (eliminate sokil/php-isocodes)
- Init: Settings in WooCommerce → Payments (not separate admin menu)
- Init: purchaseRequest without `purchaseDate` (deprecated by SISP)
- Init: Use `payment_complete()` for order completion
- Init: Callback via `woocommerce_api_{gateway_id}`
- Init: Build per PRD milestone order (bootstrap → gateway → fingerprint → redirect → callback → blocks → logging → tests)
- 01-01: Filter-based gateway registration only (no direct instantiation)
- 01-01: Unconditional admin notices loading outside plugins_loaded
- 01-01: Commented-out require stubs with phase annotations for future files
- 01-01: process_payment() stub returns failure (safe default)
- 02-01: Auth code sanitization via wp_unslash() only (preserve % + / =)
- 02-01: Sandbox-first default for vbv2_url (test URL by default)
- 02-02: Static currency map (no Composer) with 6 currencies: CVE, EUR, USD, AOA, BRL, GBP
- 02-02: Triple fallback chain: order currency → currency_default setting → hardcoded CVE ('132')
- 02-02: String return type for SISP protocol compatibility
- 03-01: Standalone functions (not class methods) for formatting helpers
- 03-01: absint(round()) for amount normalization (PHP default rounding)
- 03-01: Optional fingerprint fields appended only when non-empty (Yoda conditions)
- 03-02: Static builder methods — no instance state needed
- 03-02: UUID4 for attempt_id via wp_generate_uuid4()
- 03-02: Transaction code hardcoded to '1' (Authorization)
- 03-02: addrMatch compares address_1, city, postcode, country (not state)
- 03-02: Phone block reuses billing phone for both work and mobile
- 04-01: Config validation checks pos_id, pos_auth_code, vbv2_url before attempt building
- 04-01: wc_add_notice() for user-facing errors (not wp_die or exceptions)
- 04-01: Dual query args on redirect URL (order ID + order key for security)
- 04-01: parse_request with URI fallback for rewrite rule edge cases
- 04-01: Placeholder handler for form rendering (deferred to 04-02)
- 04-02: posAuthCode sent raw in POST form (not hashed — SISP expects raw value)
- 04-02: Standalone HTML document with exit() to bypass WordPress theming
- 04-02: Order key validation prevents unauthorized payment form access
- 04-02: Gateway null guard renders error if Vinti4 gateway unavailable
- 05-01: Response fingerprint uses same sha512_base64() primitive and absint()*1000 pattern as request fingerprint
- 05-01: Success message types hardcoded as strict array: 8, 10, M, P
- 05-02: Idempotency via _vinti4_callback_processed order meta, set before every redirect
- 05-02: Tasks 1+2 merged (helper refactored inline during initial file creation)
- 05-02: payment_complete() is the ONLY order completion mechanism — no manual stock/cart ops
- 05-03: Minimal gateway handle_callback() — one-liner delegating to Vinti4_Callback_Handler::handle()
- 05-03: Include order preserves dependency chain in vinti4_init()
- 06-01: Plain JS IIFE pattern — no build step needed for block registration
- 06-01: canMakePayment always returns true — availability controlled server-side by is_active()
- 06-01: Block registration hook outside vinti4_init() at top level (same pattern as gateway filter)
- 06-01: Settings read from same woocommerce_vinti4_settings option as gateway class
- 09-01: _vinti4_attempt_history is the canonical append-only persistence key for attempt history
- 09-01: Attempt ordering is deterministic oldest-first using created_at_gmt and sequence tie-break
- 09-01: Legacy _vinti4_* keys are compatibility projection outputs, not the history source of truth
- 09-02: Vinti4_Attempt_Factory is the canonical attempt creation service for checkout and future admin flows
- 09-02: Request builder consumes explicit attempt amount context for fingerprint generation
- 09-02: process_payment appends attempts via Vinti4_Attempt_Store and never overwrites history directly

### Pending Todos

- PHP CLI is unavailable in the current execution environment; lint and PHPUnit verification for 09-01/09-02 must be rerun in a PHP-enabled environment.

### Blockers/Concerns

None.

## Session Continuity

Last session: 2026-04-17 14:42 UTC
Stopped at: Completed 09-02-PLAN.md
Resume file: None
