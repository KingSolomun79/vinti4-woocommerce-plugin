# Roadmap: Vinti4 for WooCommerce

## Overview

Milestone v1.1 extends the shipped v1.0 hosted-card flow with partial deposits and multi-attempt payment requests for existing orders. The key change is moving from single-attempt order-level references to attempt-scoped references and reconciliation.

## Milestone Scope

- Version: v1.1
- Focus: Partial deposits and admin-triggered payment requests
- Starting phase number: 9 (continues after v1.0 phase 8)

## Phases

- [x] **Phase 9: Attempt Foundation and Persistence** - Add attempt data model, generation, and append-only history on orders
- [ ] **Phase 10: Admin Partial Request Flow** - Add dashboard/admin flow for selecting partial amount and sending new payment request attempts
- [x] **Phase 11: Callback Reconciliation and Compatibility Verification** - Resolve callback by attempt context, enforce per-attempt idempotency, and verify card-flow compatibility

## Phase Details

### Phase 9: Attempt Foundation and Persistence
**Goal**: Multiple payment attempts can exist for one WooCommerce order, each with unique reference/session/fingerprint context.
**Depends on**: Existing v1.0 callback and request builder foundations
**Requirements**: ATT-01, ATT-02, ATT-03, ATT-04
**Success Criteria** (what must be TRUE):
  1. Admin-triggered attempt creation does not mutate or overwrite prior attempt data
  2. Every attempt has unique `merchantRef` and `merchantSession`
  3. Fingerprint generation uses the attempt amount and attempt-specific context
  4. Attempt history can be enumerated for an order in chronological order
**Plans**: 2 plans

Plans:
- [x] 09-01-PLAN.md — Introduce attempt storage model and append-only history helpers on orders
- [x] 09-02-PLAN.md — Add attempt factory service to generate unique merchantRef/session/fingerprint from partial attempt input

### Phase 10: Admin Partial Request Flow
**Goal**: Admin can send payment requests for partial amounts safely and predictably.
**Depends on**: Phase 9
**Requirements**: PART-01, PART-02, PART-03, REQ-01
**Success Criteria** (what must be TRUE):
  1. Admin can choose percentage or fixed amount and create a request attempt
  2. Invalid partial amounts are blocked with clear validation errors
  3. Outstanding balance calculation reflects already-paid successful attempts
  4. Payment request dispatch uses the newly created attempt context
**Plans**: 2 plans

Plans:
- [ ] 10-01-PLAN.md — Build admin partial amount form + validation and outstanding balance computation
- [ ] 10-02-PLAN.md — Wire request dispatch path to new attempt context and admin action flow

### Phase 11: Callback Reconciliation and Compatibility Verification
**Goal**: Callbacks resolve and mutate state at attempt granularity while preserving existing card redirect behavior.
**Depends on**: Phase 9, Phase 10
**Requirements**: PART-04, REQ-02, REQ-03, REQ-04, CARD-01, CARD-02, OBS-01, OBS-02
**Success Criteria** (what must be TRUE):
  1. Callback resolves the exact attempt before order-level mutation
  2. Duplicate callback protection works per attempt, not only per order
  3. Invalid reference/session/fingerprint paths fail safely and are distinguishable in logs
  4. Successful partial callbacks update paid/outstanding totals accurately
  5. Existing hosted SISP card flow remains functional in classic checkout and block checkout
**Plans**: 2 plans

Plans:
- [x] 11-01-PLAN.md — Refactor callback resolution and idempotency to attempt-level reconciliation
- [x] 11-02-PLAN.md — Add compatibility verification, sandbox card flow checks, and multi-attempt diagnostics logging

## Progress

**Execution Order:**
Phases execute in numeric order: 9 -> 10 -> 11

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 9. Attempt Foundation and Persistence | 2/2 | Complete | 2026-04-17 |
| 10. Admin Partial Request Flow | 0/2 | Not started | - |
| 11. Callback Reconciliation and Compatibility Verification | 2/2 | Complete | 2026-04-18 |
