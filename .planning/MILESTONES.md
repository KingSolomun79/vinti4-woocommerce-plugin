# Project Milestones: Vinti4 for WooCommerce

## v1.1 Partial Deposits and Multi-Attempt Payments (Shipped: 2026-04-18)

**Delivered:** Partial payment requests with attempt-scoped references, admin meta box UX, and per-attempt callback reconciliation while preserving full backward compatibility with pre-v1.1 orders.

**Phases completed:** 9-11 (6 plans total)

**Key accomplishments:**

- Attempt-scoped payment model — append-only history with factory-driven creation enabling multiple independent payment requests per order
- Admin partial payment UX — meta box with fixed/percentage amount modes, AJAX-driven attempt creation, payment progress bar, and copy-to-clipboard payment links
- Attempt-level callback reconciliation — callbacks resolve exact attempt by merchantRef, per-attempt idempotency, partial-paid to fully-paid progression
- Full backward compatibility — legacy card flow preserved for pre-v1.1 orders via explicit fallback path with diagnostic marking
- Structured observability — attempt-scoped logging with failure type classification for diagnostics

**Stats:**

- 3 new production classes, ~15 files modified
- ~6,871 total PHP lines (project-wide, production + tests)
- 3 phases, 6 plans, 16 tasks
- 2 days from Phase 9 start to ship (2026-04-17 → 2026-04-18)

**Git range:** Phase 9 start → v1.1 audit commit

**Tech debt:** 7 minor items (0 blockers) — see v1.1-MILESTONE-AUDIT.md

**What's next:** Scheduled installments, admin capture/void/refund, tokenization (v2)

---

## v1.0 MVP (Shipped: 2026-04-16)

**Delivered:** Complete rewrite of the Vinti4 WooCommerce payment gateway — SISP card payments via hosted 3DS redirect flow with fingerprint reliability, idempotent callbacks, and modern WooCommerce standards.

**Phases completed:** 1-8 (15 plans total)

**Key accomplishments:**

- Complete plugin rewrite from brittle legacy (8 PHP files, no tests, raw SQL) to modern WooCommerce gateway (13 production classes, HPOS-safe, zero Composer dependencies)
- Cryptographic fingerprint foundation with SHA-512+Base64 and exact SISP field ordering from single canonical code path
- Idempotent callback handler with 9-step validation chain and duplicate protection
- WooCommerce Checkout Block support with plain JS (no build step)
- Structured debug logging with auth code masking — zero secret exposure
- 27 unit tests + 12 admin self-tests + SISP certification checklist covering all 27 requirements

**Stats:**

- 72 files created/modified
- ~2,069 lines production PHP, ~869 lines test PHP, ~112 lines JS
- 8 phases, 15 plans, 72 commits
- 1 day development (2026-04-16)

**Git range:** `bb60bd8` → `f5544c4`

**What's next:** Tokenization (v2), admin capture/void/refund, subscriptions support

---
