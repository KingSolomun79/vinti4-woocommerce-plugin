---
phase: 03-fingerprint-request-builder
verified: 2026-04-16T14:30:00Z
status: passed
score: 4/4 must-haves verified
---

# Phase 3: Fingerprint & Request Builder Verification Report

**Phase Goal:** Single canonical code path generates SISP-compliant fingerprints and payment request payloads
**Verified:** 2026-04-16T14:30:00Z
**Status:** ✅ PASSED
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths (ROADMAP Success Criteria)

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | Request fingerprint is generated using SHA-512 + Base64 with exact SISP field ordering | ✓ VERIFIED | `class-vinti4-fingerprint.php` L53-54: `base64_encode(hash('sha512', $value, true))`; L98-105: concatenation in exact SISP order; L117: final hash |
| 2 | Amount in fingerprint hash is integer amount × 1000 | ✓ VERIFIED | `class-vinti4-fingerprint.php` L100: `(string)(absint($amount) * 1000)` — amount pre-normalized via `vinti4_normalize_amount()` then multiplied by 1000 inside fingerprint |
| 3 | Each payment attempt generates a unique merchantRef (WC{id}-timestamp) and merchantSession | ✓ VERIFIED | `functions-vinti4-formatting.php` L55-57: `vinti4_build_merchant_ref()` → `WC{id}-YYYYMMDDHHmmss`; L69-71: `vinti4_build_merchant_session()` → `S` + 12 random chars |
| 4 | purchaseRequest JSON does not include the deprecated purchaseDate field | ✓ VERIFIED | `class-vinti4-request-builder.php` L108-156: `build_purchase_request_json()` returns array with keys: acctID, email, addrMatch, billing/shipping address fields, phone, acctInfo — no `purchaseDate` key. Grep confirms `purchaseDate` only appears in a docblock comment (L100), never as an array key |

**Score:** 4/4 truths verified

### Plan 03-01 Truths (Fingerprint & Formatting Helpers)

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | SHA-512 + Base64 fingerprint with exact SISP field ordering: posAuthCode hash, timestamp, amount×1000, merchantRef, merchantSession, posID, currency, transactionCode, then optional entityCode/referenceNumber/token | ✓ VERIFIED | L98-105 main fields in exact order; L107-115 optional fields conditionally appended |
| 2 | Amount passed into the fingerprint hash is the integer amount multiplied by 1000 | ✓ VERIFIED | Request builder normalizes amount via `vinti4_normalize_amount()` (L60), then fingerprint builder applies `absint($amount) * 1000` (L100) |
| 3 | Formatting helpers normalize timestamp, amount, and merchantRef for SISP protocol compliance | ✓ VERIFIED | 6 helper functions in `functions-vinti4-formatting.php` (122 lines): normalize_amount, format_timestamp, build_merchant_ref, build_merchant_session, parse_order_id_from_ref, shape_phone |

### Plan 03-02 Truths (Request Builder & Bootstrap Wiring)

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | Each payment attempt generates unique merchantRef (WC{id}-timestamp) and merchantSession (S + random) | ✓ VERIFIED | `build_payment_attempt()` L57-58 calls respective helpers |
| 2 | purchaseRequest JSON excludes deprecated purchaseDate field | ✓ VERIFIED | `build_purchase_request_json()` L108-156 — no purchaseDate key |
| 3 | Request builder produces all fields needed for SISP redirect in a single canonical call | ✓ VERIFIED | `build_payment_attempt()` L54-91 returns: attempt_id, timestamp, merchant_ref, merchant_session, transaction_code, amount, currency, purchase_request_b64, fingerprint |
| 4 | Request fingerprint generated from single canonical code path (Vinti4_Fingerprint::build_request_fingerprint) | ✓ VERIFIED | Only call site: `class-vinti4-request-builder.php` L68 — `Vinti4_Fingerprint::build_request_fingerprint(...)` |

### Required Artifacts

| Artifact | Expected | Exists | Substantive | Wired | Status |
|----------|----------|--------|-------------|-------|--------|
| `includes/class-vinti4-fingerprint.php` | Vinti4_Fingerprint class with sha512_base64() and build_request_fingerprint() | ✓ (119 lines) | ✓ No stubs, full implementation | ✓ Called from request builder L68 | ✓ VERIFIED |
| `includes/functions-vinti4-formatting.php` | Formatting helpers: normalize_amount, format_timestamp, build_merchant_ref, build_merchant_session | ✓ (122 lines) | ✓ 6 functions, all with real implementations | ✓ Called from request builder L55-60, 109 | ✓ VERIFIED |
| `includes/class-vinti4-request-builder.php` | Vinti4_Request_Builder class with build_payment_attempt() and build_purchase_request_json() | ✓ (157 lines) | ✓ Full implementation, no stubs | ✓ Loaded in vinti4.php L48 | ✓ VERIFIED |
| `vinti4.php` | Phase 3 require lines uncommented | ✓ | ✓ L46-48: all three require_once lines present and uncommented | ✓ Inside vinti4_init() bootstrap | ✓ VERIFIED |

### Key Link Verification

| From | To | Via | Pattern | Status | Details |
|------|----|-----|---------|--------|---------|
| class-vinti4-request-builder.php | class-vinti4-fingerprint.php | `Vinti4_Fingerprint::build_request_fingerprint()` | L68 | ✓ WIRED | Called with 11 args (8 required + 3 empty optional); result assigned to $fingerprint and returned |
| class-vinti4-request-builder.php | class-wc-gateway-vinti4.php | `$gateway->get_currency_code($order)` | L61 | ✓ WIRED | Gateway method at L161-182, returns ISO 4217 numeric code from order currency or default setting |
| class-vinti4-request-builder.php | functions-vinti4-formatting.php | `vinti4_format_timestamp()` | L55 | ✓ WIRED | Returns `gmdate('Y-m-d H:i:s')` |
| class-vinti4-request-builder.php | functions-vinti4-formatting.php | `vinti4_build_merchant_ref($order->get_id())` | L57 | ✓ WIRED | Returns `WC{id}-YYYYMMDDHHmmss` |
| class-vinti4-request-builder.php | functions-vinti4-formatting.php | `vinti4_build_merchant_session()` | L58 | ✓ WIRED | Returns `S` + 12 random alphanumeric chars |
| class-vinti4-request-builder.php | functions-vinti4-formatting.php | `vinti4_normalize_amount((float)$order->get_total())` | L60 | ✓ WIRED | Returns `absint(round($amount))` |
| class-vinti4-request-builder.php | functions-vinti4-formatting.php | `vinti4_shape_phone($order->get_billing_phone())` | L109 | ✓ WIRED | Returns `['cc' => ..., 'subscriber' => ...]` |
| vinti4.php | functions-vinti4-formatting.php | `require_once` | L46 | ✓ WIRED | Loaded inside vinti4_init() after WC dependency check |
| vinti4.php | class-vinti4-fingerprint.php | `require_once` | L47 | ✓ WIRED | Loaded inside vinti4_init() |
| vinti4.php | class-vinti4-request-builder.php | `require_once` | L48 | ✓ WIRED | Loaded inside vinti4_init() |

### Requirements Coverage

| Requirement | Description | Status | Evidence |
|-------------|-------------|--------|----------|
| PAY-02 | Request fingerprint generated from a single canonical code path only | ✓ SATISFIED | Only code path: `Vinti4_Fingerprint::build_request_fingerprint()` called from `Vinti4_Request_Builder::build_payment_attempt()` |
| PAY-04 | Each payment attempt generates unique merchantRef and merchantSession | ✓ SATISFIED | `vinti4_build_merchant_ref()` includes timestamp, `vinti4_build_merchant_session()` uses random chars |
| PAY-06 | purchaseRequest JSON excludes deprecated purchaseDate field | ✓ SATISFIED | `build_purchase_request_json()` has no purchaseDate key |
| FP-01 | Request fingerprint uses SHA-512 + Base64 with exact SISP field ordering | ✓ SATISFIED | `sha512_base64()` = `base64_encode(hash('sha512', $value, true))`; field order: posAuthCode hash → timestamp → amount×1000 → merchantRef → merchantSession → posID → currency → transactionCode → optional fields |
| FP-02 | Amount in fingerprint hash = integer amount × 1000 | ✓ SATISFIED | `vinti4_normalize_amount()` rounds to integer; fingerprint L100 multiplies by 1000 |

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| — | — | — | — | — |

**No anti-patterns detected.** All Phase 3 files are free of TODOs, FIXMEs, placeholder content, empty returns, stub implementations, and console.log-equivalents.

### Human Verification Required

None — all Phase 3 must-haves are statically verifiable. The fingerprint algorithm, field ordering, amount normalization, and field exclusions can be confirmed by code inspection.

### Gaps Summary

No gaps found. All 4 ROADMAP success criteria are met:
1. ✅ SHA-512 + Base64 fingerprint with exact SISP field ordering
2. ✅ Amount in hash is integer × 1000
3. ✅ Unique merchantRef and merchantSession per attempt
4. ✅ No purchaseDate in purchaseRequest JSON

All 3 artifacts exist, are substantive (119–157 lines each), and are fully wired through the bootstrap and request builder. All 5 requirements (PAY-02, PAY-04, PAY-06, FP-01, FP-02) are satisfied.

---

_Verified: 2026-04-16T14:30:00Z_
_Verifier: Claude (gsd-verifier)_
