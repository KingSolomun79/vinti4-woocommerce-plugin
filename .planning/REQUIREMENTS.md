# Requirements: Vinti4 for WooCommerce

**Defined:** 2026-04-17
**Core Value:** A shopper can select Vinti4 at WooCommerce checkout, be redirected securely to SISP's 3DS payment page, and return to a correctly-completed or correctly-failed order every time.

## v1.1 Requirements

Requirements for this milestone only (partial deposits and multi-attempt payments).

### Multi-Attempt Generation

- [x] **ATT-01**: Admin can create a new payment attempt from an existing WooCommerce order without re-running checkout
- [x] **ATT-02**: Each new attempt creates a unique `merchantRef` and `merchantSession`
- [x] **ATT-03**: Request fingerprint is generated from attempt-scoped data, with amount bound to that attempt
- [x] **ATT-04**: Attempt metadata is stored as append-only history (no overwrite of previous attempt records)

### Partial Amount Rules

- [ ] **PART-01**: Admin can request a partial payment using percentage and fixed amount modes
- [ ] **PART-02**: Partial amount validation blocks invalid values (<= 0, > outstanding, malformed input)
- [ ] **PART-03**: Outstanding balance is computed from successful paid attempts, not from last attempt only
- [ ] **PART-04**: A successful attempt updates paid/outstanding totals correctly at order level

### Request Delivery and Callback Resolution

- [ ] **REQ-01**: Admin can send a payment request link/form for a specific attempt
- [ ] **REQ-02**: Callback lookup resolves the exact attempt by attempt reference context before order mutation
- [ ] **REQ-03**: Idempotency guard is enforced per attempt (duplicate callback does not re-apply payment)
- [ ] **REQ-04**: Invalid reference/session/fingerprint for an attempt fails safely with diagnostic reason

### Card Flow Compatibility

- [ ] **CARD-01**: Existing hosted 3DS redirect flow for SISP card payments remains functional after multi-attempt changes
- [ ] **CARD-02**: Sandbox 3DS test card can complete a partial-attempt payment path in test mode

### Observability

- [ ] **OBS-01**: Logs include attempt ID, amount, merchantRef, and callback outcome per attempt
- [ ] **OBS-02**: Logs clearly distinguish invalid reference vs invalid fingerprint vs duplicate callback in multi-attempt flows

## Future Requirements

Deferred to later milestones.

### Deposits and Collections

- **COLL-01**: Scheduled installment plans (automatic follow-up requests)
- **COLL-02**: Automatic reminders and dunning for unpaid remaining balance
- **COLL-03**: Customer self-service portal for remaining balance payment links

### Advanced Operations

- **OPS-01**: Admin capture/void/refund for each attempt
- **OPS-02**: Attempt replay tool for support workflows

### Tokenization and Recurring

- **TOK-01**: Saved card tokenization UX
- **TOK-02**: Subscription billing via saved payment method

## Out of Scope

Explicit exclusions for this milestone.

| Feature | Reason |
|---------|--------|
| Direct card capture in Woo checkout (non-hosted) | Current integration is hosted SISP 3DS redirect; changing PCI scope is out of milestone |
| Subscription renewals | Depends on tokenization and recurring orchestration |
| Full collections automation | Manual partial request flow must stabilize first |
| Multi-gateway settlement orchestration | Not required for this milestone's partial attempt fix |

## Traceability

Which phase covers which requirement. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| ATT-01 | Phase 9 | Complete |
| ATT-02 | Phase 9 | Complete |
| ATT-03 | Phase 9 | Complete |
| ATT-04 | Phase 9 | Complete |
| PART-01 | Phase 10 | Pending |
| PART-02 | Phase 10 | Pending |
| PART-03 | Phase 10 | Pending |
| PART-04 | Phase 11 | Pending |
| REQ-01 | Phase 10 | Pending |
| REQ-02 | Phase 11 | Pending |
| REQ-03 | Phase 11 | Pending |
| REQ-04 | Phase 11 | Pending |
| CARD-01 | Phase 11 | Pending |
| CARD-02 | Phase 11 | Pending |
| OBS-01 | Phase 11 | Pending |
| OBS-02 | Phase 11 | Pending |

**Coverage:**
- v1.1 requirements: 16 total
- Mapped to phases: 16
- Unmapped: 0

---
*Requirements defined: 2026-04-17*
*Last updated: 2026-04-17 after Phase 9 completion and verification*
