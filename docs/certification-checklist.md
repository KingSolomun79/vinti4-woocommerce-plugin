# Vinti4 for WooCommerce — SISP Certification Checklist

**Version:** 1.0.0
**Date:** 2026-04-16
**Total v1 Requirements:** 27

This checklist maps every SISP-required behavior to a specific verification method. Each row references either a PHPUnit test case, an admin panel diagnostic test, or a code review with file-level traceability.

---

## 1. Request Formation

| Req ID | Requirement | Verified By | Status |
|--------|-------------|-------------|--------|
| FP-01 | Request fingerprint uses SHA-512 + Base64 with exact SISP field ordering | **Admin Panel:** Fingerprint Hash test (verifies `sha512_base64()` produces valid 64-byte hash); **PHPUnit:** `Vinti4_Fingerprint_Test::test_sha512_base64_returns_base64_encoded_sha512`; **Code Review:** `includes/class-vinti4-fingerprint.php` — `build_request_fingerprint()` concatenates fields in SISP order | ☐ |
| FP-02 | Amount in fingerprint hash = integer amount × 1000 | **PHPUnit:** `Vinti4_Fingerprint_Test::test_build_request_fingerprint_multiplies_amount_by_1000`; **Code Review:** `includes/class-vinti4-fingerprint.php` line 100 — `absint( $amount ) * 1000` | ☐ |
| PAY-02 | Request fingerprint generated from a single canonical code path only | **Code Review:** `includes/class-vinti4-fingerprint.php` — single `build_request_fingerprint()` method; `includes/class-vinti4-request-builder.php` — single call site in `build_payment_attempt()` | ☐ |
| PAY-04 | Each payment attempt generates unique `merchantRef` and `merchantSession` | **PHPUnit:** `Vinti4_Formatting_Test::test_build_merchant_ref_contains_order_id`, `test_build_merchant_session_starts_with_S_and_is_unique`; **Code Review:** `includes/functions-vinti4-formatting.php` — `vinti4_build_merchant_ref()` includes timestamp, `vinti4_build_merchant_session()` uses `wp_generate_password()` | ☐ |
| PAY-06 | purchaseRequest JSON excludes deprecated `purchaseDate` field | **PHPUnit:** `Vinti4_Request_Builder_Test::test_build_payment_attempt_excludes_purchase_date`; **Code Review:** `includes/class-vinti4-request-builder.php` — `build_purchase_request_b64()` builds JSON without `purchaseDate` | ☐ |

---

## 2. Response Validation

| Req ID | Requirement | Verified By | Status |
|--------|-------------|-------------|--------|
| FP-03 | Response fingerprint validated before order completion (success message types: 8, 10, M, P) | **Admin Panel:** Success Types test (8, 10, M, P → true; 0, 1 → false); **Admin Panel:** Callback: Invalid Fingerprint test (verifies fingerprint comparison logic); **PHPUnit:** `Vinti4_Callback_Handler_Test::test_handle_rejects_fingerprint_mismatch`, `Vinti4_Formatting_Test::test_is_success_message_type_true_cases`, `test_is_success_message_type_false_cases`; **Code Review:** `includes/class-vinti4-callback-handler.php` lines 130–153 — fingerprint validation before `payment_complete()` | ☐ |
| CB-02 | Callback validates order exists, merchantRef matches, fingerprint is valid, amount matches | **PHPUnit:** `Vinti4_Callback_Handler_Test::test_handle_rejects_invalid_order`, `test_handle_rejects_merchant_ref_mismatch`, `test_handle_rejects_fingerprint_mismatch`, `test_handle_rejects_amount_mismatch`; **Admin Panel:** Callback: Invalid Fingerprint test; **Code Review:** `includes/class-vinti4-callback-handler.php` — steps 2–7 in `handle()` | ☐ |

---

## 3. Order Processing

| Req ID | Requirement | Verified By | Status |
|--------|-------------|-------------|--------|
| CB-03 | Successful callback calls `payment_complete()` exactly once (no manual stock reduction) | **PHPUnit:** `Vinti4_Callback_Handler_Test::test_handle_success_calls_payment_complete_once`, `test_handle_success_does_not_call_update_status_completed`; **Code Review:** `includes/class-vinti4-callback-handler.php` line 169 — `$order->payment_complete( $transaction_id )`; no `reduce_order_stock()` or `update_status('completed')` calls | ☐ |
| CB-04 | Duplicate callbacks are safely rejected (idempotency via `_vinti4_callback_processed` meta) | **Admin Panel:** Callback: Duplicate Detection test (verifies idempotency meta key and `already_processed` guard); **PHPUnit:** `Vinti4_Callback_Handler_Test::test_handle_duplicate_callback_is_rejected`; **Code Review:** `includes/class-vinti4-callback-handler.php` lines 112–124 — `_vinti4_callback_processed` meta check before any order mutation | ☐ |
| CB-05 | Failed/invalid callback marks order failed and redirects shopper to checkout | **PHPUnit:** `Vinti4_Callback_Handler_Test::test_handle_failure_marks_order_failed`, `test_handle_rejects_fingerprint_mismatch_marks_failed`; **Code Review:** `includes/class-vinti4-callback-handler.php` lines 151–153 (fingerprint fail → failed), 182–192 (general failure → failed + redirect to checkout) | ☐ |
| PAY-05 | Shopper is redirected to a receipt/start page that auto-posts to SISP | **Code Review:** `includes/class-vinti4-redirect-form.php` — `render()` outputs standalone HTML form with auto-submit JavaScript; `includes/class-wc-gateway-vinti4.php` — `process_payment()` redirects to `/vinti4-payment/`; `vinti4.php` — rewrite rule + `parse_request` handler | ☐ |

---

## 4. Redirect Flow

| Req ID | Requirement | Verified By | Status |
|--------|-------------|-------------|--------|
| PAY-01 | `process_payment()` validates config and creates a canonical payment attempt | **PHPUnit:** `WC_Gateway_Vinti4_Test::test_process_payment_returns_failure_on_empty_settings`; **Code Review:** `includes/class-wc-gateway-vinti4.php` lines 200–249 — config check, `build_payment_attempt()`, meta storage, redirect | ☐ |
| PAY-03 | All request fields stored on the order as meta before redirect | **PHPUnit:** `WC_Gateway_Vinti4_Test::test_process_payment_stores_attempt_meta`; **Code Review:** `includes/class-wc-gateway-vinti4.php` lines 224–234 — 9 meta keys stored before redirect (`_vinti4_attempt_id`, `_vinti4_timestamp`, `_vinti4_merchant_ref`, `_vinti4_merchant_session`, `_vinti4_transaction_code`, `_vinti4_amount`, `_vinti4_currency`, `_vinti4_purchase_request_b64`, `_vinti4_fingerprint`) | ☐ |

---

## 5. Settings & Configuration

| Req ID | Requirement | Verified By | Status |
|--------|-------------|-------------|--------|
| SETT-01 | Gateway configurable in WooCommerce → Settings → Payments (not separate admin menu) | **Code Review:** `includes/class-wc-gateway-vinti4.php` — extends `WC_Payment_Gateway` (standard WooCommerce settings integration); `vinti4.php` — registered via `woocommerce_payment_gateways` filter | ☐ |
| SETT-02 | Settings include: enabled, title, description, POS ID, POS Auth Code, SISP URL, language (pt/en), debug toggle | **Admin Panel:** Config Present test (checks pos_id, pos_auth_code, vbv2_url non-empty); **Code Review:** `includes/class-wc-gateway-vinti4.php` — `init_form_fields()` defines all 8 fields: enabled, title, description, pos_id, pos_auth_code, vbv2_url, language, debug, currency_default | ☐ |
| SETT-03 | POS Auth Code field preserves valid characters (no aggressive sanitization) | **Code Review:** `includes/class-wc-gateway-vinti4.php` — `process_admin_options()` overrides parent to use `wp_unslash()` only (preserves % + / = characters) | ☐ |
| SETT-04 | Currency auto-detected from WooCommerce order currency with configurable default (CVE) | **Admin Panel:** Currency Map test (CVE → 132); **Code Review:** `includes/class-wc-gateway-vinti4.php` — `get_currency_code()` — triple fallback: order currency → currency_default setting → hardcoded CVE ('132') | ☐ |

---

## 6. Plugin Lifecycle

| Req ID | Requirement | Verified By | Status |
|--------|-------------|-------------|--------|
| BOOT-01 | Plugin activates without fatal errors when WooCommerce is active | **Code Review:** `vinti4.php` — `vinti4_init()` checks `class_exists('WooCommerce')` before loading gateway; all includes guarded by `defined('ABSPATH') || exit`; class_exists guards prevent double-loading | ☐ |
| BOOT-02 | Plugin shows admin notice and stays dormant when WooCommerce is inactive | **Code Review:** `includes/class-vinti4-admin-notices.php` — `register_missing_wc_notice()` adds admin notice; `vinti4.php` — `vinti4_init()` returns early if WooCommerce not active, admin notices loaded unconditionally before the check | ☐ |
| BOOT-03 | Gateway is registered via `woocommerce_payment_gateways` filter without direct instantiation | **Admin Panel:** Gateway Registered test; **Code Review:** `vinti4.php` — `vinti4_add_gateway()` adds class name string to filter, never instantiates directly | ☐ |
| BOOT-04 | No pages created on activation, no raw SQL on deactivation | **Code Review:** `vinti4.php` — `register_activation_hook()` only flushes rewrite rules; `register_deactivation_hook()` only flushes rewrite rules; `uninstall.php` only deletes options; no `CREATE TABLE` or raw SQL anywhere | ☐ |

---

## 7. Logging

| Req ID | Requirement | Verified By | Status |
|--------|-------------|-------------|--------|
| LOG-01 | Structured debug logging for request fingerprint inputs, outgoing payload, callback receipt, validation results | **Admin Panel:** Logger Available test; **Code Review:** `includes/class-vinti4-logger.php` — `log()` writes to WooCommerce logger with source 'vinti4'; `includes/class-vinti4-request-builder.php` — logs fingerprint inputs and request payload; `includes/class-vinti4-callback-handler.php` — logs callback receipt, fingerprint mismatch, amount mismatch, duplicate, success, failure | ☐ |
| LOG-02 | Full POS auth code never appears in logs | **Admin Panel:** Logger Mask test (ABCDEFGHYZ → ABC***YZ), Logger Mask Empty test ('' → ''); **Code Review:** `includes/class-vinti4-logger.php` — `mask_auth_code()` masks auth code; all log calls use masked version; `includes/class-vinti4-request-builder.php` — logs masked auth code in fingerprint inputs | ☐ |
| LOG-03 | Logs can distinguish: request formation issue, callback fingerprint mismatch, duplicate callback, invalid amount, invalid reference | **Code Review:** `includes/class-wc-gateway-vinti4.php` — 'gateway configuration incomplete' message; `includes/class-vinti4-callback-handler.php` — distinct log messages: 'fingerprint mismatch', 'amount mismatch', 'Duplicate callback detected', 'could not parse order ID', 'merchantRef mismatch'; all include context (order ID, amounts, refs) | ☐ |
| FP-04 | Debug logs capture fingerprint inputs without exposing full POS auth code | **Admin Panel:** Logger Mask test; **Code Review:** `includes/class-vinti4-request-builder.php` — `$result` variable logged before return, auth code masked via `Vinti4_Logger::mask_auth_code()` | ☐ |

---

## 8. Checkout Blocks

| Req ID | Requirement | Verified By | Status |
|--------|-------------|-------------|--------|
| BLK-01 | Gateway registers block payment-method integration for Cart/Checkout Blocks | **Admin Panel:** Blocks Support test (verifies class exists and extends AbstractPaymentMethodType); **Code Review:** `vinti4.php` — `woocommerce_blocks_payment_method_type_registration` action registers `WC_Vinti4_Blocks_Support`; `includes/class-wc-vinti4-blocks-support.php` — extends `AbstractPaymentMethodType` | ☐ |
| BLK-02 | Gateway title and description render correctly in Checkout Block | **Code Review:** `includes/class-wc-vinti4-blocks-support.php` — `get_payment_method_data()` returns title and description from settings; `assets/js/blocks.js` renders via wcSettings | ☐ |
| BLK-03 | Selecting Vinti4 in Checkout Block routes through `process_payment()` | **Code Review:** `includes/class-wc-vinti4-blocks-support.php` — `get_payment_method_data()` returns `supports: ['products']`; gateway name 'vinti4' matches `WC_Gateway_Vinti4::$id`; WooCommerce Blocks framework routes to `process_payment()` for registered payment methods | ☐ |

---

## Verification Methods Summary

| Method | Count | Description |
|--------|-------|-------------|
| PHPUnit Test | 14 | Automated test cases in `tests/` directory (Plan 08-01) |
| Admin Panel Test | 12 | Diagnostic tests in WooCommerce → Vinti4 Tests (Plan 08-02) |
| Code Review | 27 | Manual source inspection with file and line references |

**Note:** PHPUnit tests are defined in Plan 08-01 (running in parallel). Admin panel tests are from Task 1 of this plan. Code review references point to specific files and lines in the plugin source.

---

## Certification Sign-Off

| Role | Name | Date | Signature |
|------|------|------|-----------|
| Developer | | | |
| QA / Tester | | | |
| SISP Representative | | | |

---

*Checklist generated: 2026-04-16*
*Plugin version: 1.0.0*
*All 27 v1 requirements mapped.*
