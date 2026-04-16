# Roadmap: Vinti4 for WooCommerce

## Overview

Complete rewrite of the Vinti4 WooCommerce payment gateway from a brittle legacy adaptation into a modern WooCommerce payment extension. The plugin integrates with SISP to process card payments via a hosted 3DS redirect flow. This v1 focuses on stability, standards-alignment, and fingerprint reliability — creating a clean base for v2 tokenization.

## Phases

- [x] **Phase 1: Safe Bootstrap** ✓ - Plugin activates safely on modern WooCommerce with dependency guards and proper gateway registration
- [x] **Phase 2: Gateway Settings** ✓ - Gateway class with WooCommerce-native settings UI and configurable fields
- [x] **Phase 3: Fingerprint & Request Builder** ✓ - Canonical fingerprint generation and payment request building with SISP compliance
- [x] **Phase 4: Payment Redirect Flow** ✓ - Complete hosted redirect flow from checkout to SISP and back
- [x] **Phase 5: Callback & Idempotency** ✓ - WooCommerce-native callback endpoint with validation and duplicate protection
- [x] **Phase 6: Checkout Block Support** ✓ - Gateway registration and rendering in WooCommerce Cart/Checkout Blocks
- [x] **Phase 7: Logging & Diagnostics** ✓ - Structured debug logging with secret redaction for support
- [x] **Phase 8: Testing & Certification Prep** ✓ - Unit tests for fingerprint and callback, certification checklist mapping

## Phase Details

### Phase 1: Safe Bootstrap
**Goal**: Plugin activates without fatal errors and registers cleanly with WooCommerce
**Depends on**: Nothing (first phase)
**Requirements**: BOOT-01, BOOT-02, BOOT-03, BOOT-04
**Success Criteria** (what must be TRUE):
  1. Activating the plugin with WooCommerce active produces no critical error
  2. Activating without WooCommerce shows an admin notice and the plugin stays dormant
  3. Vinti4 appears in the WooCommerce payment methods list
  4. Deactivating the plugin does not delete any orders, pages, or posts
**Plans**: 1 plan (complete)

Plans:
- [x] 01-01-PLAN.md — Complete plugin bootstrap with dependency guards, gateway registration, admin notices, and safe uninstall

### Phase 2: Gateway Settings
**Goal**: All payment settings live inside WooCommerce → Settings → Payments with proper field types
**Depends on**: Phase 1
**Requirements**: SETT-01, SETT-02, SETT-03, SETT-04
**Success Criteria** (what must be TRUE):
  1. Merchant can configure POS ID, Auth Code, and SISP URL in WooCommerce payment settings
  2. POS Auth Code field preserves special characters like `%` (no aggressive sanitization)
  3. Currency setting defaults to CVE and auto-detects from WooCommerce order currency
  4. Language setting switches between Portuguese and English
**Plans**: 2 plans

Plans:
- [x] 02-01-PLAN.md — Add 6 gateway settings fields (POS ID, Auth Code, SISP URL, language, debug, currency_default) with custom sanitization for Auth Code
- [x] 02-02-PLAN.md — Add get_currency_code() method with order auto-detect and ISO 4217 numeric code mapping

### Phase 3: Fingerprint & Request Builder
**Goal**: Single canonical code path generates SISP-compliant fingerprints and payment request payloads
**Depends on**: Phase 2
**Requirements**: PAY-02, PAY-04, PAY-06, FP-01, FP-02
**Success Criteria** (what must be TRUE):
  1. Request fingerprint is generated using SHA-512 + Base64 with exact SISP field ordering
  2. Amount in fingerprint hash is integer amount × 1000
  3. Each payment attempt generates a unique merchantRef (e.g., `WC{order_id}-{timestamp}`) and merchantSession
  4. purchaseRequest JSON does not include the deprecated `purchaseDate` field
**Plans**: 2 plans (complete)

Plans:
- [x] 03-01-PLAN.md — Create Vinti4_Fingerprint class (SHA-512 + Base64) and formatting helper functions
- [x] 03-02-PLAN.md — Create Vinti4_Request_Builder class (canonical payment attempt builder) and wire Phase 3 requires

### Phase 4: Payment Redirect Flow
**Goal**: Shopper can complete checkout via SISP hosted redirect and return to a correctly-processed order
**Depends on**: Phase 3
**Requirements**: PAY-01, PAY-03, PAY-05
**Success Criteria** (what must be TRUE):
  1. Clicking "Place order" with Vinti4 selected redirects to a receipt/start page
  2. The receipt page auto-posts the canonical payment data to SISP
 3. All request fields (fingerprint, timestamp, merchantRef, etc.) are stored on the order before redirect
  4. Invalid configuration (missing POS ID/Auth Code/URL) shows an error instead of crashing
**Plans**: 2 plans (complete)

Plans:
- [x] 04-01-PLAN.md — Implement process_payment() with config validation, attempt creation via Request Builder, order meta storage, and WordPress rewrite endpoint registration
- [x] 04-02-PLAN.md — Create Vinti4_Redirect_Form class (auto-posting HTML form to SISP) and wire into parse_request handler

### Phase 5: Callback & Idempotency
**Goal**: SISP callbacks are handled safely via WooCommerce API endpoint with full validation and duplicate protection
**Depends on**: Phase 4
**Requirements**: CB-01, CB-02, CB-03, CB-04, CB-05, FP-03
**Success Criteria** (what must be TRUE):
  1. A valid success callback completes the order exactly once via `payment_complete()`
  2. An invalid fingerprint or mismatched merchantRef never completes the order
 3. A duplicate callback (second POST with same data) is safely rejected without mutating the order
 4. A failed callback marks the order failed and redirects the shopper back to checkout
  5. No manual stock reduction or cart emptying occurs in the callback path
**Plans**: 3 plans (complete)

Plans:
- [x] 05-01-PLAN.md — Response fingerprint builder method + success message type checker
- [x] 05-02-PLAN.md — Callback handler class with full validation, idempotency, and outcome handling
- [x] 05-03-PLAN.md — Gateway wiring (hook activation + bootstrap include)

### Phase 6: Checkout Block Support
**Goal**: Gateway appears and works in WooCommerce Cart and Checkout Blocks
**Depends on**: Phase 2
**Requirements**: BLK-01, BLK-02, BLK-03
**Success Criteria** (what must be TRUE):
  1. Vinti4 appears as a payment option in Checkout Block
  2. Title and description render correctly from WooCommerce settings
  3. Selecting Vinti4 in Checkout Block routes through the same `process_payment()` as classic checkout
**Plans**: 1 plan (complete)

Plans:
- [x] 06-01-PLAN.md — Create block integration class, JS registration script, and bootstrap wiring

### Phase 7: Logging & Diagnostics
**Goal**: Support can diagnose payment issues from logs without exposing sensitive data
**Depends on**: Phase 3, Phase 5
**Requirements**: LOG-01, LOG-02, LOG-03, FP-04
**Success Criteria** (what must be TRUE):
  1. Logs capture attempt ID, merchantRef, timestamp, and fingerprint input fields for every payment attempt
  2. Logs capture callback receipt, validation result, and duplicate callback detection
  3. Full POS auth code never appears in any log entry
  4. Logs can distinguish between: request formation issue, fingerprint mismatch, duplicate callback, invalid amount, invalid reference
**Plans**: 2 plans (complete)

Plans:
- [x] 07-01-PLAN.md — Create Vinti4_Logger class with auth code masking and wire into bootstrap + gateway constructor
- [x] 07-02-PLAN.md — Add structured logging calls to request builder, gateway process_payment, and callback handler

### Phase 8: Testing & Certification Prep
**Goal**: Unit tests verify fingerprint correctness and callback handling; certification checklist is mapped
**Depends on**: Phase 3, Phase 5, Phase 7
**Requirements**: (validation — no new functional requirements, but verifies existing ones)
**Success Criteria** (what must be TRUE):
  1. Request fingerprint unit test passes against known expected output
  2. Response fingerprint unit test passes against known expected output
  3. Duplicate callback test passes (second callback rejected safely)
 4. Invalid fingerprint callback test passes (order not completed)
 5. Certification checklist maps all SISP-required behaviors to test cases
**Plans:** 2 plans (complete)

Plans:
- [x] 08-01-PLAN.md — PHPUnit infrastructure + pure PHP unit tests (fingerprint, formatting, logger mask)
- [x] 08-02-PLAN.md — Admin diagnostic panel + certification checklist

## Progress

**Execution Order:**
Phases execute in numeric order: 1 → 2 → 3 → 4 → 5 → 6 → 7 → 8

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Safe Bootstrap | 1/1 | ✓ Complete | 2026-04-16 |
| 2. Gateway Settings | 2/2 | ✓ Complete | 2026-04-16 |
| 3. Fingerprint & Request Builder | 2/2 | ✓ Complete | 2026-04-16 |
| 4. Payment Redirect Flow | 2/2 | ✓ Complete | 2026-04-16 |
| 5. Callback & Idempotency | 3/3 | ✓ Complete | 2026-04-16 |
| 6. Checkout Block Support | 1/1 | ✓ Complete | 2026-04-16 |
| 7. Logging & Diagnostics | 2/2 | ✓ Complete | 2026-04-16 |
| 8. Testing & Certification Prep | 2/2 | ✓ Complete | 2026-04-16 |
