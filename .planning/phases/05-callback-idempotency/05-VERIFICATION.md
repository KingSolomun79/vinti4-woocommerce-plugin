---
phase: 05-callback-idempotency
verified: 2026-04-16T12:00:00Z
status: passed
score: 11/11 must-haves verified
---

# Phase 5: Callback & Idempotency Verification Report

**Phase Goal:** SISP callbacks are handled safely via WooCommerce API endpoint with full validation and duplicate protection
**Verified:** 2026-04-16T12:00:00Z
**Status:** passed
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | Response fingerprint computation produces the same hash as SISP for identical input fields | ✓ VERIFIED | `Vinti4_Fingerprint::build_response_fingerprint()` (lines 154-190) concatenates all 16 fields in order with `sha512_base64(posAuthCode)` prefix, then hashes the entire base string. Uses `self::sha512_base64()` for both stages. Amount multiplied by 1000. |
| 2 | Success message types 8, 10, M, P are correctly identified; all others are rejected | ✓ VERIFIED | `vinti4_is_success_message_type()` (formatting.php line 136-137): `in_array($message_type, array('8','10','M','P'), true)` — strict comparison prevents type coercion. |
| 3 | A valid success callback completes the order exactly once via payment_complete() | ✓ VERIFIED | Callback handler line 160: `$order->payment_complete($transaction_id)`. Idempotency ensured by `_vinti4_callback_processed` meta check (line 107-117) which exits before any mutation on repeat calls. `mark_processed_and_redirect()` (line 198) sets meta after first processing. |
| 4 | An invalid fingerprint never completes the order — order is marked failed instead | ✓ VERIFIED | Line 143-145: `if ($expected_fingerprint !== $result_fingerprint)` → `$order->update_status('failed', ...)` + `mark_processed_and_redirect()`. Does NOT call `payment_complete()`. |
| 5 | A mismatched merchantRef never completes the order — callback is rejected | ✓ VERIFIED | Line 98-104: `$stored_ref = $order->get_meta('_vinti4_merchant_ref')` compared against callback `$merchant_ref`. If mismatch, `wp_safe_redirect(wc_get_checkout_url())` + `exit`. No order mutation occurs. |
| 6 | A duplicate callback is safely rejected without mutating the order | ✓ VERIFIED | Line 107-117: `$already_processed = $order->get_meta('_vinti4_callback_processed')`. If truthy, redirects to order-received (if processing/completed) or checkout URL, then `exit`. No `update_status`, no `payment_complete`, no meta changes. |
| 7 | A failed callback marks the order as failed and redirects to checkout | ✓ VERIFIED | Line 172-181: When `$is_success` is false, `$order->update_status('failed', ...)` with error detail/description, then `mark_processed_and_redirect($order, wc_get_checkout_url())`. |
| 8 | No manual stock reduction or cart emptying occurs anywhere in the callback path | ✓ VERIFIED | Grep confirms only reference to `reduce_order_stock`/`empty_cart` is a comment at line 159: "Do NOT call reduce_order_stock(), empty_cart()...". No actual calls to these functions exist. `payment_complete()` handles this natively. |
| 9 | Callback endpoint is accessible at wc-api=vinti4 via the woocommerce_api_vinti4 hook | ✓ VERIFIED | Gateway line 49: `add_action('woocommerce_api_' . $this->id, array($this, 'handle_callback'))` — `$this->id` is `'vinti4'` (line 23), producing hook `woocommerce_api_vinti4`. |
| 10 | Gateway handle_callback() delegates to Vinti4_Callback_Handler::handle() | ✓ VERIFIED | Gateway lines 256-258: `public function handle_callback(): void { Vinti4_Callback_Handler::handle( $this ); }` |
| 11 | Callback handler class is loaded before the hook fires | ✓ VERIFIED | vinti4.php line 50: `require_once VINTI4_PLUGIN_DIR . 'includes/class-vinti4-callback-handler.php';` inside `vinti4_init()` which runs on `plugins_loaded` at priority 20. The gateway constructor (which registers the hook) also runs during `plugins_loaded`. Include is before the gateway is loaded, guaranteeing the class exists when the hook fires. |

**Score:** 11/11 truths verified

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `includes/class-vinti4-fingerprint.php` | Contains `build_response_fingerprint` | ✓ VERIFIED | 191 lines, method at line 154. Full implementation with 16 params, SHA-512+Base64, amount ×1000. |
| `includes/functions-vinti4-formatting.php` | Contains `vinti4_is_success_message_type` | ✓ VERIFIED | 138 lines, function at line 136. Strict `in_array` with types 8, 10, M, P. |
| `includes/class-vinti4-callback-handler.php` | Contains `class Vinti4_Callback_Handler` (min 80 lines) | ✓ VERIFIED | 203 lines (well above 80 minimum). Complete class with `handle()` (54-182) and `mark_processed_and_redirect()` (197-202). |
| `includes/class-wc-gateway-vinti4.php` | Contains `handle_callback` | ✓ VERIFIED | 259 lines, method at line 256. Delegates to `Vinti4_Callback_Handler::handle($this)`. |
| `vinti4.php` | Contains `class-vinti4-callback-handler` | ✓ VERIFIED | Line 50: `require_once` for callback handler class. Loaded inside `vinti4_init()`. |

### Key Link Verification

| From | To | Via | Status | Details |
|------|----|-----|--------|---------|
| `build_response_fingerprint()` | `self::sha512_base64()` | Direct call | ✓ WIRED | Fingerprint.php lines 172, 189: uses `self::sha512_base64()` for both posAuthCode hash and final hash |
| `Vinti4_Callback_Handler::handle()` | `Vinti4_Fingerprint::build_response_fingerprint` | Static call | ✓ WIRED | Callback-handler.php line 124: `Vinti4_Fingerprint::build_response_fingerprint(...)` with all 16 params |
| `Vinti4_Callback_Handler::handle()` | `->payment_complete()` | Order method call | ✓ WIRED | Callback-handler.php line 160: `$order->payment_complete($transaction_id)` |
| `Vinti4_Callback_Handler::handle()` | `vinti4_parse_order_id_from_ref` | Function call | ✓ WIRED | Callback-handler.php line 85: `$order_id = vinti4_parse_order_id_from_ref($merchant_ref)` |
| `Vinti4_Callback_Handler::handle()` | `_vinti4_callback_processed` | Meta get/set | ✓ WIRED | Line 107: `get_meta('_vinti4_callback_processed')` for read; line 198: `update_meta_data('_vinti4_callback_processed', '1')` for write |
| Gateway class | `woocommerce_api_` hook | `add_action` | ✓ WIRED | Gateway line 49: `add_action('woocommerce_api_' . $this->id, array($this, 'handle_callback'))` |
| `handle_callback()` | `Vinti4_Callback_Handler::handle` | Delegation | ✓ WIRED | Gateway line 257: `Vinti4_Callback_Handler::handle($this)` |
| `vinti4_init()` | `class-vinti4-callback-handler.php` | `require_once` | ✓ WIRED | vinti4.php line 50: require of callback handler before gateway loads |

### Requirements Coverage

| Requirement | Status | Supporting Truths |
|-------------|--------|-------------------|
| CB-01: Valid success callback completes order exactly once | ✓ SATISFIED | Truths 3, 6 (payment_complete + idempotency) |
| CB-02: Invalid fingerprint/fingerprint mismatch → order failed | ✓ SATISFIED | Truth 4 (fingerprint validation → update_status failed) |
| CB-03: Mismatched merchantRef rejected | ✓ SATISFIED | Truth 5 (stored ref comparison → redirect + exit) |
| CB-04: Duplicate callback rejected without mutation | ✓ SATISFIED | Truth 6 (callback_processed meta → redirect + exit) |
| CB-05: Failed callback marks order failed, redirects to checkout | ✓ SATISFIED | Truth 7 (update_status failed + redirect to checkout URL) |
| FP-03: Response fingerprint uses SHA-512 + Base64 with SISP field ordering | ✓ SATISFIED | Truths 1, 2 |

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| None | — | — | — | No anti-patterns detected |

**Stub scan results:**
- TODO/FIXME/PLACEHOLDER patterns: 0 found
- Empty return patterns: 0 found
- Console.log-only implementations: N/A (PHP)
- Manual stock/cart operations: 0 actual calls (only a comment explaining NOT to call them)

### Control Flow Analysis

Verified all terminal paths in `Vinti4_Callback_Handler::handle()`:

1. **Missing fields** (line 80-82): `wp_die()` — terminates
2. **Invalid order ID** (line 87-89): `wp_die()` — terminates
3. **Order not found** (line 93-95): `wp_die()` — terminates
4. **MerchantRef mismatch** (line 100-104): `wp_safe_redirect()` + `exit` — terminates
5. **Duplicate callback, order successful** (line 111-113): redirect to order-received + `exit`
6. **Duplicate callback, order not successful** (line 115-116): redirect to checkout + `exit`
7. **Success + invalid fingerprint** (line 143-145): `update_status('failed')` + `mark_processed_and_redirect()` → `exit`
8. **Success + amount mismatch** (line 152-154): `update_status('failed')` + `mark_processed_and_redirect()` → `exit`
9. **Success + valid** (line 160-168): `payment_complete()` + `mark_processed_and_redirect()` → `exit`
10. **Failure** (line 172-181): `update_status('failed')` + `mark_processed_and_redirect()` → `exit`

All paths terminate correctly via `exit` (inside `mark_processed_and_redirect()` or directly). No fall-through from success to failure path.

### Human Verification Required

None required for this phase. All truths are mechanically verifiable from code:
- Cryptographic correctness (SHA-512 + Base64) is a standard algorithm
- WooCommerce `payment_complete()` behavior is well-documented
- All validation chains and control flows are traceable via static analysis

### Gaps Summary

No gaps found. All 11 must-haves verified at all three levels:

1. **Existence**: All 5 required artifacts present
2. **Substance**: All artifacts have real, non-stub implementations (callback handler is 203 lines, fingerprint is 191 lines, formatting helpers 138 lines)
3. **Wiring**: All 8 key links confirmed — imports, function calls, hook registrations, and meta operations all connect correctly

---

_Verified: 2026-04-16T12:00:00Z_
_Verifier: Claude (gsd-verifier)_
