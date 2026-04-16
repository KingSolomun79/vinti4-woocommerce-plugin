# Roadmap: Vinti4 for WooCommerce

## Milestones

- ✅ **v1.0 MVP** — Phases 1-8 (shipped 2026-04-16) → [archived roadmap](milestones/v1.0-ROADMAP.md) | [archived requirements](milestones/v1.0-REQUIREMENTS.md)
- 🚧 **v1.1 Checkout Compatibility Fixes** — Phases 9-14 (planned)

## Current Milestone: v1.1 Checkout Compatibility Fixes

Focused follow-up milestone to resolve the two live blockers found during real WordPress testing before resuming broader PRD-compliance work.

### Phase 9: SISP Request Language and Required Fields

**Goal:** Fix the hosted redirect request so SISP receives the required language and full request-contract fields expected by the middleware, without exposing secrets client-side
**Depends on:** Phase 8
**Plans:** 3 plans

Plans:
- [x] 09-01-PLAN.md - Canonicalize the full SISP middleware request contract in the server-side payment attempt
- [x] 09-02-PLAN.md - Render the corrected browser payload and add regression coverage for request shape and secret exposure
- [ ] 09-03-PLAN.md - Verify the fix in a real WooCommerce sandbox checkout flow

**Details:**
Investigate and fix the checkout error `languageMessages é obrigatório para o funcionamento do Middleware`. Align the outbound redirect form with the SISP field contract, inherit the storefront language from WordPress/WooCommerce where appropriate, remove incorrect field naming, add missing required request fields, and resolve the security/protocol mismatch where `posAuthCode` is currently exposed in the browser. Explicitly verify required SISP fields such as `urlMerchantResponse`, `is3DSec`, `languageMessages`, `timeStamp`, and `fingerprintversion`/`FingerPrintVersion`, plus any other request-shape requirements confirmed by the legacy implementation and SISP docs.

### Phase 10: WooCommerce Feature Compatibility Declarations

**Goal:** Remove the WooCommerce incompatibility warning by declaring and validating support for the currently enabled WooCommerce features
**Depends on:** Phase 9
**Plans:** 0 plans

Plans:
- [ ] TBD (run /gsd-plan-phase 10 to break down)

**Details:**
Investigate the admin warning `You are viewing active plugins that are incompatible with currently enabled WooCommerce features.` Add the required WooCommerce feature compatibility declarations and any related bootstrap adjustments so the plugin is recognized as compatible with the enabled WooCommerce feature set.

### Phase 11: Callback Fingerprint Validation Hardening

**Goal:** Make callback fingerprint validation protocol-safe by hashing the raw callback payload values exactly as received from SISP
**Depends on:** Phase 9
**Plans:** 0 plans

Plans:
- [ ] TBD (run /gsd-plan-phase 11 to break down)

**Details:**
Fix the callback validation risk where incoming response fields are sanitized before fingerprint recomputation. Preserve raw callback values for fingerprint verification, apply sanitization only where safe after protocol validation, and eliminate the risk of random fingerprint mismatches caused by transformed whitespace or altered payload content.

### Phase 12: Currency and Amount Handling Correction

**Goal:** Make amount handling consistent with the currencies the plugin supports, or explicitly narrow the supported currency model
**Depends on:** Phase 11
**Plans:** 0 plans

Plans:
- [ ] TBD (run /gsd-plan-phase 12 to break down)

**Details:**
Resolve the mismatch between whole-integer amount normalization and the plugin's exposed support for EUR and USD. Decide whether v1 is CVE-only or truly multi-currency, then update request building, callback validation, settings, and docs so amount handling is correct and support claims are honest.

### Phase 13: Verification Coverage and Test Truthfulness

**Goal:** Make certification/testing claims accurate and add the missing automated coverage for real payment-critical flows
**Depends on:** Phase 12
**Plans:** 0 plans

Plans:
- [ ] TBD (run /gsd-plan-phase 13 to break down)

**Details:**
Fix overstated verification coverage in the certification checklist and add the missing tests. Cover the success callback path, `process_payment()`, redirect-form payload generation, and any other payment-critical behavior currently claimed but not actually tested. Update docs so checklist references match real test classes and methods.

### Phase 14: Packaging and Production Polish

**Goal:** Make the plugin packaging and presentation production-ready for distribution and review
**Depends on:** Phase 13
**Plans:** 0 plans

Plans:
- [ ] TBD (run /gsd-plan-phase 14 to break down)

**Details:**
Clean up stray files, restore proper WordPress packaging metadata, re-enable or correctly implement textdomain loading, and fix mojibake/encoding issues in code comments and docs so the plugin looks finished and review-ready.

## Progress

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 1. Safe Bootstrap | v1.0 | 1/1 | ✓ Complete | 2026-04-16 |
| 2. Gateway Settings | v1.0 | 2/2 | ✓ Complete | 2026-04-16 |
| 3. Fingerprint & Request Builder | v1.0 | 2/2 | ✓ Complete | 2026-04-16 |
| 4. Payment Redirect Flow | v1.0 | 2/2 | ✓ Complete | 2026-04-16 |
| 5. Callback & Idempotency | v1.0 | 3/3 | ✓ Complete | 2026-04-16 |
| 6. Checkout Block Support | v1.0 | 1/1 | ✓ Complete | 2026-04-16 |
| 7. Logging & Diagnostics | v1.0 | 2/2 | ✓ Complete | 2026-04-16 |
| 8. Testing & Certification Prep | v1.0 | 2/2 | ✓ Complete | 2026-04-16 |
| 9. SISP Request Language and Required Fields | v1.1 | 2/3 | In progress | - |
| 10. WooCommerce Feature Compatibility Declarations | v1.1 | 0/0 | Planned | - |
| 11. Callback Fingerprint Validation Hardening | v1.1 | 0/0 | Planned | - |
| 12. Currency and Amount Handling Correction | v1.1 | 0/0 | Planned | - |
| 13. Verification Coverage and Test Truthfulness | v1.1 | 0/0 | Planned | - |
| 14. Packaging and Production Polish | v1.1 | 0/0 | Planned | - |
