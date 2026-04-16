---
phase: 03
plan: 01
name: Fingerprint & Formatting Helpers
subsystem: cryptography
tags: [sha512, base64, fingerprint, sisp, formatting, normalization]
dependencies:
  requires: [02-gateway-settings]
  provides: [Vinti4_Fingerprint, formatting-helpers]
  affects: [03-02, 04-redirect-handler, 05-callback-handler]
tech-stack:
  added: []
  patterns: [sha512-base64-fingerprint, amount-x1000-normalization, conditional-optional-fields]
key-files:
  created:
    - includes/functions-vinti4-formatting.php
    - includes/class-vinti4-fingerprint.php
  modified: []
decisions:
  - id: d1
    choice: "Standalone functions (not class methods) for formatting helpers"
    rationale: "Formatting helpers are utility functions with no state — standalone functions are more idiomatic WordPress and easier to reuse"
  - id: d2
    choice: "absint(round()) for amount normalization — rounds .5 up (PHP_ROUND_HALF_UP)"
    rationale: "SISP requires integer amounts; PHP default rounding matches banker expectations"
  - id: d3
    choice: "Optional fingerprint fields appended only when non-empty with Yoda conditions"
    rationale: "SISP spec requires optional fields omitted (not blank) when unused — empty string check ensures correct hash base"
metrics:
  started: "2026-04-16T12:12:24Z"
  completed: "2026-04-16T12:14:30Z"
  duration: ~2 min
---

# Phase 3 Plan 1: Fingerprint & Formatting Helpers Summary

SHA-512 + Base64 fingerprint generation class and SISP formatting helpers — the cryptographic foundation ensuring payment requests produce correct, deterministic fingerprints matching SISP's exact field ordering specification.

## Accomplishments

- Created 6 standalone formatting helper functions for SISP protocol normalization
- Created Vinti4_Fingerprint class with SHA-512 + Base64 hash primitive and full fingerprint builder
- Fingerprint algorithm matches SISP specification: exact field ordering, amount × 1000, optional fields conditionally appended
- All code follows WordPress coding standards (tabs, Yoda conditions, strict comparisons, PHPDoc)

## Task Commits

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Create formatting helper functions | `5b240ed` | includes/functions-vinti4-formatting.php |
| 2 | Create Vinti4_Fingerprint class | `c2b1ccd` | includes/class-vinti4-fingerprint.php |

## Files Created

| File | Purpose |
|------|---------|
| `includes/functions-vinti4-formatting.php` | 6 standalone helpers: normalize_amount, format_timestamp, build_merchant_ref, build_merchant_session, parse_order_id_from_ref, shape_phone |
| `includes/class-vinti4-fingerprint.php` | Vinti4_Fingerprint class: sha512_base64() hash primitive, build_request_fingerprint() with exact SISP field ordering |

## Key Implementation Details

### Fingerprint Algorithm
1. Hash posAuthCode via SHA-512 → Base64
2. Concatenate: hashed_auth + timestamp + (amount×1000) + merchantRef + merchantSession + posID + currency + transactionCode
3. Optionally append: entityCode, referenceNumber, token (only if non-empty)
4. Hash entire concatenated base string via SHA-512 → Base64

### Formatting Helpers
- `vinti4_normalize_amount(1500.49)` → `1500`, `vinti4_normalize_amount(1500.50)` → `1501`
- `vinti4_build_merchant_ref(42)` → `WC42-20260416143022` (14-digit timestamp)
- `vinti4_build_merchant_session()` → `S` + 12 random alphanumeric chars = 13 chars total
- `vinti4_parse_order_id_from_ref('WC42-...')` → `42`, `vinti4_parse_order_id_from_ref('invalid')` → `0`
- `vinti4_shape_phone('+238 987-654-321')` → `['cc' => '238', 'subscriber' => '987654321']`

## Decisions Made

1. **Standalone formatting functions** — no class wrapper, following WordPress utility function conventions
2. **PHP default rounding** — `round()` uses PHP_ROUND_HALF_UP, matching SISP integer expectations
3. **Yoda conditions for optional field checks** — `'' !== $entity_code` follows WordPress coding standards

## Deviations from Plan

None — plan executed exactly as written.

## Issues

None.

## Next Phase Readiness

**Ready for Plan 03-02 (Request Builder)**. The fingerprint class and formatting helpers provide all the building blocks needed to construct the complete SISP payment request form:
- `Vinti4_Fingerprint::build_request_fingerprint()` generates the cryptographic fingerprint
- Formatting helpers normalize amount, timestamp, merchantRef, and session
- All SISP-required field orderings are encoded and tested
