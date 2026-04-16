# Requirements: Vinti4 for WooCommerce

**Defined:** 2026-04-16
**Core Value:** A shopper can select Vinti4 at WooCommerce checkout, be redirected securely to SISP's 3DS payment page, and return to a correctly-completed or correctly-failed order — every time, without fingerprint mismatches, duplicate completions, or fatal errors.

## v1 Requirements

Requirements for initial release. Each maps to roadmap phases.

### Plugin Bootstrap

- [x] **BOOT-01**: Plugin activates without fatal errors when WooCommerce is active
- [x] **BOOT-02**: Plugin shows admin notice and stays dormant when WooCommerce is inactive
- [x] **BOOT-03**: Gateway is registered via `woocommerce_payment_gateways` filter without direct instantiation
- [x] **BOOT-04**: No pages created on activation, no raw SQL on deactivation

### Gateway Settings

- [x] **SETT-01**: Gateway configurable in WooCommerce → Settings → Payments (not separate admin menu)
- [x] **SETT-02**: Settings include: enabled, title, description, POS ID, POS Auth Code, SISP URL, language (pt/en), debug toggle
- [x] **SETT-03**: POS Auth Code field preserves valid characters (no aggressive sanitization)
- [x] **SETT-04**: Currency auto-detected from WooCommerce order currency with configurable default (CVE)

### Payment Flow

- [ ] **PAY-01**: `process_payment()` validates config and creates a canonical payment attempt
- [x] **PAY-02**: Request fingerprint generated from a single canonical code path only
- [ ] **PAY-03**: All request fields stored on the order as meta before redirect
- [x] **PAY-04**: Each payment attempt generates unique `merchantRef` and `merchantSession`
- [ ] **PAY-05**: Shopper is redirected to a receipt/start page that auto-posts to SISP
- [x] **PAY-06**: purchaseRequest JSON excludes deprecated `purchaseDate` field

### Fingerprint

- [x] **FP-01**: Request fingerprint uses SHA-512 + Base64 with exact SISP field ordering
- [x] **FP-02**: Amount in fingerprint hash = integer amount × 1000
- [ ] **FP-03**: Response fingerprint validated before order completion (success message types: 8, 10, M, P)
- [ ] **FP-04**: Debug logs capture fingerprint inputs without exposing full POS auth code

### Callback Handling

- [ ] **CB-01**: Callback handled via `woocommerce_api_{gateway_id}` endpoint (no standalone PHP files)
- [ ] **CB-02**: Callback validates order exists, merchantRef matches, fingerprint is valid, amount matches
- [ ] **CB-03**: Successful callback calls `payment_complete()` exactly once (no manual stock reduction)
- [ ] **CB-04**: Duplicate callbacks are safely rejected (idempotency via `_vinti4_callback_processed` meta)
- [ ] **CB-05**: Failed/invalid callback marks order failed and redirects shopper to checkout

### Checkout Block

- [ ] **BLK-01**: Gateway registers block payment-method integration for Cart/Checkout Blocks
- [ ] **BLK-02**: Gateway title and description render correctly in Checkout Block
- [ ] **BLK-03**: Selecting Vinti4 in Checkout Block routes through `process_payment()`

### Logging

- [ ] **LOG-01**: Structured debug logging for request fingerprint inputs, outgoing payload, callback receipt, validation results
- [ ] **LOG-02**: Full POS auth code never appears in logs
- [ ] **LOG-03**: Logs can distinguish: request formation issue, callback fingerprint mismatch, duplicate callback, invalid amount, invalid reference

## v2 Requirements

Deferred to future release. Tracked but not in current roadmap.

### Tokenization

- **TOK-01**: Token request flow via SISP
- **TOK-02**: Token payment (pay with saved card)
- **TOK-03**: Token cancel
- **TOK-04**: Saved payment method UX in WooCommerce My Account

### Admin Tooling

- **ADM-01**: Admin capture/void/refund flows
- **ADM-02**: Callback replay viewer
- **ADM-03**: Manual re-check endpoint via SISP transaction status API

### Subscriptions

- **SUB-01**: WooCommerce Subscriptions compatibility

## Out of Scope

Explicitly excluded. Documented to prevent scope creep.

| Feature | Reason |
|---------|--------|
| Saved cards in My Account | Tokenization UX deferred to v2 |
| Recurring billing / subscriptions | Requires tokenization, deferred to v2 |
| Admin capture / refund / void | v2 scope per PRD |
| Multi-POS orchestration | Not needed for v1 |
| Advanced DCC receipt rendering | Pass-through sufficient for v1 |
| Full SISP tokenization in UI | v2 scope per PRD |
| Composer dependencies | Must use built-in WP/Woo functions only |
| Raw SQL queries | Must use WooCommerce order APIs (HPOS-safe) |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| BOOT-01 | Phase 1 | Complete |
| BOOT-02 | Phase 1 | Complete |
| BOOT-03 | Phase 1 | Complete |
| BOOT-04 | Phase 1 | Complete |
| SETT-01 | Phase 2 | Complete |
| SETT-02 | Phase 2 | Complete |
| SETT-03 | Phase 2 | Complete |
| SETT-04 | Phase 2 | Complete |
| PAY-02 | Phase 3 | Complete |
| PAY-04 | Phase 3 | Complete |
| PAY-06 | Phase 3 | Complete |
| FP-01 | Phase 3 | Complete |
| FP-02 | Phase 3 | Complete |
| PAY-01 | Phase 4 | Pending |
| PAY-03 | Phase 4 | Pending |
| PAY-05 | Phase 4 | Pending |
| CB-01 | Phase 5 | Pending |
| CB-02 | Phase 5 | Pending |
| CB-03 | Phase 5 | Pending |
| CB-04 | Phase 5 | Pending |
| CB-05 | Phase 5 | Pending |
| FP-03 | Phase 5 | Pending |
| BLK-01 | Phase 6 | Pending |
| BLK-02 | Phase 6 | Pending |
| BLK-03 | Phase 6 | Pending |
| LOG-01 | Phase 7 | Pending |
| LOG-02 | Phase 7 | Pending |
| LOG-03 | Phase 7 | Pending |
| FP-04 | Phase 7 | Pending |

**Coverage:**
- v1 requirements: 27 total
- Mapped to phases: 27
- Unmapped: 0 ✓

---
*Requirements defined: 2026-04-16*
*Last updated: 2026-04-16 after roadmap creation*
