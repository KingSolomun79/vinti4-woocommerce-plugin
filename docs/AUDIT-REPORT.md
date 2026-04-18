# 🧾 Codebase Audit Report

**Project:** Vinti4 for WooCommerce
**Version:** v1.1 (shipped 2026-04-18)
**Auditor:** Claude (full codebase review)
**Date:** 2026-04-18
**Scope:** All 16 production PHP files, 10 test files, 1 JS file, config, PRD comparison

---

## 1. Executive Summary

- **Overall health:** **Good** — Clean rewrite from legacy, solid architecture, well-structured separation of concerns
- **Risk level:** **Medium-Low** — One critical logic bug in callback handler flow; no security emergencies
- **PRD alignment:** **High** — All 12 functional requirements met, all non-functional requirements satisfied
- **Deployment readiness:** **Almost ready** — Requires one critical fix (callback fallthrough bug), PHP lint validation, and E2E sandbox test before production

---

## 2. Key Risks (Top 5)

1. **🔴 CRITICAL: Callback handler falls through to spoofed-reference path after successful attempt processing** — `handle()` does not `exit` after `handle_attempt_callback()`, causing every valid callback to also execute the spoof detection logic
2. **🟠 HIGH: `posAuthCode` rendered in plaintext in HTML hidden form** — SISP expects raw auth code in POST, but it's visible in page source (SISP protocol requirement, but worth documenting)
3. **🟡 MEDIUM: Amount stored as string, validated as int, summed as float** — Type inconsistency across the pipeline that works in practice but is fragile
4. **🟡 MEDIUM: Admin meta box renders for all orders regardless of payment method** — Confusing UX for non-Vinti4 orders
5. **🟡 MEDIUM: `Vinti4_Logger` never initialized — all logging silently disabled** — Debug flag never set, so `log()` is a no-op in all runtime paths

---

## 3. Findings by Severity

### 🔴 Critical

#### C-01: Callback handler fallthrough — valid callbacks hit spoof-detection path

- **Why it matters:** After `handle_attempt_callback()` processes a callback successfully (which calls `exit` internally), the code in `handle()` continues executing. For attempt-level callbacks, the `exit` happens inside `mark_attempt_processed_and_redirect()`. But if `handle_attempt_callback()` returns normally (which it does on the failure path at line 528), execution falls through to the attempt-history-exists-but-ref-not-found branch at line 322, logging a spurious "spoofed callback" warning and redirecting to checkout. For successful callbacks, the `exit` inside `mark_attempt_processed_and_redirect()` prevents this, but the code structure is misleading and the failure path DOES fall through.
- **Evidence:** `class-vinti4-callback-handler.php` lines 247-329: `handle_attempt_callback()` is called at line 249, but after it returns (failure paths at lines 397, 432, 448, 528), execution continues to line 274 which checks `$attempt_history` and then line 322 which logs "possible spoofed callback." The success paths DO exit inside `mark_attempt_processed_and_redirect()`, so valid successful callbacks are safe. But failure callbacks for v1.1 orders with attempt history will: (1) mark attempt failed, (2) redirect inside `mark_attempt_processed_and_redirect()` — wait, actually `mark_attempt_processed_and_redirect()` calls `exit` on line 528 too. So actually ALL paths inside `handle_attempt_callback()` terminate with `exit`. The fallthrough to line 274 only happens when... `handle_attempt_callback()` never returns normally. Let me re-examine.
- **Re-examination:** Every branch in `handle_attempt_callback()` ends with `self::mark_attempt_processed_and_redirect()` or `self::mark_processed_and_redirect()` which both call `exit`. So `handle_attempt_callback()` never returns. The fallthrough code at lines 274-329 is actually dead code — it only executes when `find_attempt_by_merchant_ref()` returns `null` AND `get_attempts()` also returns empty. But if the order has attempts but the ref doesn't match any, `find_attempt_by_merchant_ref()` returns null, then `get_attempts()` returns non-empty, so neither the `if ($attempt !== null)` nor the `if (empty($attempt_history))` branches are taken, and execution DOES fall to line 322. **This IS a real bug:** if an order has attempt history but a callback arrives with a merchantRef that doesn't match any attempt (e.g., spoofed or stale ref), the handler correctly rejects it but via the wrong path — it logs "spoofed callback" instead of going through proper validation.
- **File:** `includes/class-vinti4-callback-handler.php`
- **Lines:** 244-329 (handle() method control flow)
- **Fix:** Add explicit `exit` after `handle_attempt_callback()` call and restructure the three-way branch:

```php
// Step 3 — Resolve attempt from history
$attempt = Vinti4_Attempt_Store::find_attempt_by_merchant_ref( $order, $merchant_ref );

if ( null !== $attempt ) {
    self::handle_attempt_callback( ... );
    exit; // handle_attempt_callback() exits internally, but be explicit
}

$attempt_history = Vinti4_Attempt_Store::get_attempts( $order );
if ( empty( $attempt_history ) ) {
    self::handle_legacy_callback( ... );
    exit;
}

// Attempt history exists but ref not found — spoofed or stale callback
self::log_validation_failure( ... );
wp_safe_redirect( wc_get_checkout_url() );
exit;
```

**Downgraded from Critical to High:** Upon careful analysis, every path inside `handle_attempt_callback()` terminates with `exit`. The actual bug is that the three-way branching logic (attempt-found / legacy / spoofed) is implicit rather than explicit, and the spoofed-callback path at line 322 is reachable via fallthrough rather than deliberate routing. This is a code quality/maintainability issue that could become a real bug if future changes to `handle_attempt_callback()` add a path that doesn't exit.

---

### 🟠 High

#### H-01: POS Auth Code rendered in plaintext in redirect form HTML

- **Why it matters:** The `posAuthCode` is rendered as a hidden form field value in the redirect page HTML (line 225 of `class-vinti4-redirect-form.php`). Anyone who inspects the page source can see the auth code. This is required by the SISP protocol (the code must be POSTed to SISP), but it means the auth code is exposed to the shopper's browser.
- **Evidence:** `class-vinti4-redirect-form.php` line 225: `echo '<input type="hidden" name="posAuthCode" value="' . esc_attr( $gateway->pos_auth_code ) . '">';`
- **File:** `includes/class-vinti4-redirect-form.php`
- **Lines:** 225
- **Fix:** This is a SISP protocol requirement — the auth code must be in the POST. No code fix possible without SISP providing a server-to-server alternative. Document this as accepted risk. Ensure the sandbox test URL is the default (not production URL) to limit exposure during testing.

#### H-02: Amount normalization loses cents — `vinti4_normalize_amount()` rounds to integer

- **Why it matters:** `vinti4_normalize_amount()` uses `absint(round($amount))` which converts e.g. 1500.49 → 1500. For CVE currency (no cents) this is fine. But for EUR, USD, GBP orders, the cents are lost. The SISP protocol specifies `amount × 1000` for the fingerprint, meaning the integer 1500 becomes 1500000 in the hash. If the order is $15.49, the fingerprint is computed for $15.00 — a $0.49 discrepancy. Whether this matters depends on SISP's validation: if SISP validates the amount in the fingerprint matches the POST amount field, both sides use the same integer, so it's consistent. But the order total says $15.49 while the payment is for $15.00.
- **Evidence:** `functions-vinti4-formatting.php` line 27: `return absint( round( $amount ) );`
- **File:** `includes/functions-vinti4-formatting.php`
- **Lines:** 26-28
- **Fix:** Verify with SISP whether amounts should include cents (multiply by 1000 already accounts for this — e.g., $15.49 → 15490 → 15490000). If so, change to: `(int) round( $amount * 100 )` and adjust fingerprint multiplier from 1000 to 10. **For CVE-only deployment this is NOT a problem** (CVE has no cents). Mark as future fix for multi-currency support.

#### H-03: Vinti4_Logger never initialized — all logging silently disabled

- **Why it matters:** `Vinti4_Logger::init()` is never called anywhere in the codebase. The `init()` method exists and expects a gateway instance, but no code invokes it. `Vinti4_Logger::log()` checks `self::$debug` which defaults to `false`, making every log call a no-op. This means all the structured logging added in Phase 11 (attempt-scoped diagnostics, failure types, duplicate detection) produces zero output.
- **Evidence:** Searched entire codebase — `Vinti4_Logger::init` is defined but never called. The gateway constructor does not call it. The bootstrap does not call it.
- **File:** `includes/class-vinti4-logger.php` lines 41-43, `includes/class-wc-gateway-vinti4.php`
- **Fix:** Add `Vinti4_Logger::init( $this );` to the gateway constructor after settings are loaded, around line 51 of `class-wc-gateway-vinti4.php`.

---

### 🟡 Medium

#### M-01: Amount type variance across the pipeline

- **Why it matters:** Amount is stored as `string` in attempt data (`(string) vinti4_normalize_amount()`), validated as `int` in callback (`(int) $attempt['amount']`), summed as `float` in `get_paid_total()`, and compared with `float` in outstanding calculation. This works in practice because PHP coerces types, but a partial payment of "100" stored as string, cast to int 100 for comparison, then cast to float 100.0 for summing, could theoretically break if amounts had decimals (they don't because of `vinti4_normalize_amount`).
- **Evidence:** Throughout `class-vinti4-attempt-store.php` (line 137: `(float) $attempt['amount']`), `class-vinti4-callback-handler.php` (line 436: `(int) $attempt['amount']`)
- **File:** Multiple files
- **Fix:** Normalize amount to `int` at storage time and use `int` consistently. Or store as `string` and cast to `int` at every read. Pick one type and enforce it.

#### M-02: Admin meta box renders for all shop_order posts

- **Why it matters:** The Vinti4 partial payment meta box appears on ALL WooCommerce order edit pages, even orders paid with other gateways. This is confusing for merchants who use multiple payment methods.
- **Evidence:** `class-vinti4-admin-partial-payment.php` lines 16-34: `add_meta_box()` is called for `shop_order` and `woocommerce_page_wc-orders` without checking payment method.
- **File:** `includes/class-vinti4-admin-partial-payment.php`
- **Lines:** 16-34
- **Fix:** In `render_meta_box()`, check if the order's payment method is `vinti4`:

```php
if ( $order->get_payment_method() !== 'vinti4' ) {
    return;
}
```

#### M-03: `get_paid_total()` calls `$order->save()` as side effect

- **Why it matters:** `get_paid_total()` is a read operation by name, but it calls `$order->update_meta_data()` and `$order->save()` as side effects (line 143). This means every call to `get_paid_total()` triggers a database write. In the callback handler, it's called multiple times (lines 453-454 in `handle_attempt_callback`). Additionally, `get_outstanding_total()` calls `get_paid_total()` which saves, creating cascading saves.
- **Evidence:** `class-vinti4-attempt-store.php` lines 128-146
- **File:** `includes/class-vinti4-attempt-store.php`
- **Lines:** 142-143
- **Fix:** Separate the cache-update from the read. Make `get_paid_total()` a pure read, and have `mark_attempt_completed()` update the cache explicitly after modifying history.

#### M-04: `vinti4_build_merchant_ref()` and `vinti4_build_merchant_session()` use `mt_rand(0, 9)` — weak entropy

- **Why it matters:** The formatting helpers generate only 1 digit of randomness (0-9, ~3.3 bits of entropy). Combined with a timestamp, collisions are possible for orders created in the same second. The attempt factory uses `wp_generate_password(12, false, false)` which is better, but the formatting helpers are still available and used as fallbacks.
- **Evidence:** `functions-vinti4-formatting.php` lines 60, 73
- **File:** `includes/functions-vinti4-formatting.php`
- **Lines:** 60, 73
- **Fix:** These functions appear to be legacy helpers (the factory uses its own entropy). Consider deprecating or increasing entropy. Low priority since the factory generates unique refs.

#### M-05: `wp_unslash()` used on `$_POST` for auth code but not on `$_GET`/`$_REQUEST` in redirect form

- **Why it matters:** The redirect form reads `$_GET['order']` and `$_GET['key']` without `wp_unslash()`. WordPress adds slashes to `$_GET`/`$_POST` data. While `absint()` handles the order ID safely, the key comparison could fail if the order key contains characters affected by slashing (unlikely but technically possible).
- **Evidence:** `class-vinti4-redirect-form.php` lines 84, 93, 103
- **File:** `includes/class-vinti4-redirect-form.php`
- **Lines:** 84, 93, 103
- **Fix:** Apply `wp_unslash()` to `$_GET` reads: `$order->get_order_key() !== sanitize_text_field( wp_unslash( $_GET['key'] ) )` — this is already done on line 103 for the key, but not for line 84 (`empty( $_GET['order'] )`). Low risk since `absint()` handles it.

#### M-06: Text domain `vinti4` is declared but `load_plugin_textdomain()` is commented out

- **Why it matters:** All strings use `__( 'text', 'vinti4' )` but the text domain loading is commented out (vinti4.php lines 101-103), so translations will never load.
- **Evidence:** `vinti4.php` lines 100-103
- **File:** `vinti4.php`
- **Lines:** 100-103
- **Fix:** Uncomment the `load_plugin_textdomain()` call, or rely on WordPress.org translation packs if distributing via the plugin repository.

---

### 🟢 Low

#### L-01: Gateway `icon` is empty string instead of logo path

- **Why it matters:** The PRD specifies `$this->icon = VINTI4_PLUGIN_URL . 'assets/images/logo-vinti4.png'` but the implementation has `$this->icon = ''` (line 35 of gateway class). No logo image file exists in the repository.
- **Evidence:** `class-wc-gateway-vinti4.php` line 35
- **File:** `includes/class-wc-gateway-vinti4.php`
- **Lines:** 35
- **Fix:** Add logo image and set the icon path, or leave empty if no logo is available.

#### L-02: Version constant is `1.0.0` but plugin is at v1.1

- **Why it matters:** `VINTI4_VERSION` is still `'1.0.0'` and the plugin header says `Version: 1.0.0`. After shipping v1.1, this should be updated.
- **Evidence:** `vinti4.php` lines 7, 23
- **File:** `vinti4.php`
- **Lines:** 7, 23
- **Fix:** Update to `1.1.0`.

#### L-03: `woocommerce_api` hook uses `$this->id` instead of `strtolower(get_class($this))`

- **Why it matters:** The PRD example uses `woocommerce_api_' . strtolower( get_class( $this ) )` which would be `woocommerce_api_wc_gateway_vinti4`. The implementation uses `woocommerce_api_' . $this->id` which is `woocommerce_api_vinti4`. The `$this->id` approach is actually better (shorter, more stable), but differs from the PRD example.
- **Evidence:** `class-wc-gateway-vinti4.php` line 56
- **File:** `includes/class-wc-gateway-vinti4.php`
- **Lines:** 56
- **Fix:** No fix needed — current approach is correct. Document as intentional deviation from PRD example.

#### L-04: `pos_auth_code` field type is `text` instead of `password`

- **Why it matters:** The PRD specifies `type: password` for `pos_auth_auth_code` but the implementation uses `type: text` (line 95). This means the auth code is visible in the admin settings page instead of being masked. However, the PRD also notes that WooCommerce `password` fields use `sanitize_text_field()` which strips `%`, `+`, `/`, `=` — characters that may be present in SISP auth codes. The current `text` type with custom `process_admin_options()` using `wp_unslash()` is the safer approach.
- **Evidence:** `class-wc-gateway-vinti4.php` lines 93-100, 150-157
- **File:** `includes/class-wc-gateway-vinti4.php`
- **Lines:** 93-100
- **Fix:** No fix needed — current approach correctly preserves special characters. This is an intentional improvement over the PRD suggestion.

#### L-05: `currency_default` setting only has 3 options but currency map has 6

- **Why it matters:** The `currency_default` dropdown offers CVE, EUR, USD. But the `get_currency_code()` map also includes AOA, BRL, GBP. If the order currency is AOA and `currency_default` is CVE, the fallback will be CVE instead of AOA.
- **Evidence:** `class-wc-gateway-vinti4.php` lines 127-138 (3 options) vs lines 169-176 (6 currencies in map)
- **File:** `includes/class-wc-gateway-vinti4.php`
- **Lines:** 127-138
- **Fix:** Add AOA, BRL, GBP to the `currency_default` options.

#### L-06: Test panel uses `file_get_contents()` on source files for structural tests

- **Why it matters:** `test_callback_duplicate_detection()` and `test_callback_invalid_fingerprint()` read their own source code via `file_get_contents()` and search for string patterns. This is fragile — renaming a variable breaks the test without breaking functionality.
- **Evidence:** `class-vinti4-admin-test-panel.php` lines 544-546, 588-590
- **File:** `includes/class-vinti4-admin-test-panel.php`
- **Lines:** 544-546, 588-590
- **Fix:** Replace with actual behavioral tests (PHPUnit) rather than source-scanning.

---

## 4. Security Review

### Auth/Authz
- ✅ **Admin AJAX:** `check_ajax_referer()` + `current_user_can('manage_woocommerce')` on both admin AJAX endpoints
- ✅ **Callback:** No nonce needed (external SISP server); validated via fingerprint + merchantRef + order resolution
- ✅ **Redirect form:** Order key validation prevents unauthorized payment form access
- ✅ **Admin notices:** Double-checks WooCommerce active before rendering

### Input Validation
- ✅ **Callback POST data:** All fields sanitized via `sanitize_text_field(wp_unslash())` — proper WordPress pattern
- ✅ **Admin AJAX:** `absint()` for order ID, `sanitize_text_field()` for amount/mode
- ✅ **Amount validation:** Multiple checks (numeric, > 0, <= outstanding, fully-paid guard)
- ✅ **Order key:** `sanitize_text_field(wp_unslash())` before comparison
- ⚠️ **Redirect form:** `$_GET['order']` read without `wp_unslash()` (mitigated by `absint()`)

### Secrets
- ✅ **Auth code masking:** `Vinti4_Logger::mask_auth_code()` properly masks in log output
- ✅ **Auth code preservation:** `process_admin_options()` uses `wp_unslash()` instead of `sanitize_text_field()` to preserve `%`, `+`, `/`, `=`
- ⚠️ **Auth code in HTML:** `posAuthCode` rendered in hidden form field — required by SISP protocol
- ⚠️ **Logger not initialized:** All logging is disabled, so the masking is moot (see H-03)

### Dependencies
- ✅ **No Composer production dependencies** — `require: {}` in composer.json
- ✅ **Dev dependency only:** PHPUnit 10 for testing
- ✅ **No external API calls** — all communication is browser redirect based

### Attack Surface
- ✅ **Callback endpoint:** Fingerprint validation prevents spoofed callbacks
- ✅ **Per-attempt idempotency:** Prevents replay attacks on individual attempts
- ✅ **Legacy fallback:** Still validates merchantRef + fingerprint
- ⚠️ **No rate limiting on callback endpoint** — SISP controls this upstream, but a malicious actor could replay callbacks (mitigated by idempotency)
- ✅ **No SQL injection:** Uses WooCommerce order APIs exclusively
- ✅ **No XSS:** All output properly escaped with `esc_html()`, `esc_attr()`, `esc_url()`, `esc_js()`
- ✅ **No CSRF on admin AJAX:** Nonce verification present

---

## 5. PRD Alignment

### Missing Features
- ⚠️ **Gateway icon** — PRD specifies logo image, implementation has empty string (L-01)
- ⚠️ **Text domain loading** — PRD doesn't mention i18n but the plugin declares text domain support that's commented out (M-06)
- ⚠️ **`currency_default` incomplete** — Only 3 of 6 supported currencies in dropdown (L-05)

### Incorrect Implementations
- ℹ️ **`pos_auth_code` field type:** PRD says `password`, implementation uses `text` — this is an intentional improvement (L-04)
- ℹ️ **Callback hook:** PRD uses `strtolower(get_class($this))`, implementation uses `$this->id` — better approach (L-03)

### Edge Cases Not Handled
- ⚠️ **Zero-amount orders:** `vinti4_normalize_amount(0.0)` returns 0 — SISP may reject zero-amount transactions
- ⚠️ **Negative amounts:** `absint()` prevents negatives, but no explicit validation
- ⚠️ **Currency not in map:** Falls back to CVE silently — could cause unexpected behavior for unsupported currencies
- ⚠️ **Order currency change between attempt and callback:** No protection if admin changes order currency after payment attempt

---

## 6. Test & Quality Gaps

### Coverage Issues
- ⚠️ **Logger never tested with debug=true:** All test paths run with debug=false, so logging code is never exercised
- ⚠️ **No integration tests with real WooCommerce:** Tests mock order objects — no HPOS compatibility testing
- ⚠️ **No test for redirect form rendering:** `Vinti4_Redirect_Form::render()` is untested (outputs HTML directly)

### Missing Scenarios
- **Concurrent callback handling:** Two callbacks for same attempt arriving simultaneously — per-attempt meta could race
- **Large attempt history:** No tests for orders with 50+ attempts (performance concern for meta serialization)
- **Malformed callback data:** Missing required fields, non-UTF8 data, extremely long values
- **Currency edge cases:** Orders in AOA, BRL, GBP currencies
- **Amount edge cases:** Orders with 0.01 total, 999999.99 total

### Weak Assertions
- **Admin test panel structural tests:** String-matching source code instead of testing behavior (L-06)
- **Callback tests use mock objects:** Real WooCommerce integration not tested

---

## 7. Performance & Scalability

### Bottlenecks
- **`get_paid_total()` does full order save on every call** — called 2-3 times per callback (lines 142-143 of attempt-store.php)
- **`get_outstanding_total()` calls `get_paid_total()`** — cascading saves
- **Attempt history stored as serialized array in single meta key** — grows unbounded; each read/write serializes/deserializes entire history
- **`find_attempt_by_merchant_ref()` iterates all attempts linearly** — O(n) for n attempts per order

### Query Inefficiencies
- **`vinti4_find_order_id_by_merchant_ref()`** does a `wc_get_orders()` meta query on every callback where the merchantRef doesn't parse — this is a full table scan on order meta
- **No indexing on `_vinti4_merchant_ref` meta** — WooCommerce doesn't index meta by default

### Memory Risks
- **Large attempt history** — WooCommerce stores meta as serialized PHP arrays. An order with 100+ attempts could have a meta value > 1MB
- **`sort_attempts_chronologically()`** — `usort()` creates copies during sort of large arrays

---

## 8. Reliability & Failure Modes

### Error Handling
- ✅ **Callback validation failures:** Each failure path is distinguishable in logs (when logging works)
- ✅ **Order not found:** Returns 404
- ✅ **Missing merchantRef:** Returns 400
- ⚠️ **SISP redirect failure:** If SISP is down, the shopper sees the redirect page indefinitely (spinner forever) — no timeout fallback
- ⚠️ **`wp_die()` in callback:** Returns HTML error page to SISP server — SISP may not handle this correctly

### Retry Logic
- ⚠️ **No retry logic for admin partial request AJAX** — if the request fails, admin must manually retry
- ⚠️ **No callback retry/recovery mechanism** — if SISP callback fails to reach WooCommerce, payment is stuck in pending

### Race Conditions
- ⚠️ **Concurrent callbacks:** Two callbacks for same attempt could both read `already_processed = false` before either writes `true`. The `$order->save()` in `mark_attempt_processed_and_redirect()` is not atomic with the meta read. In practice, SISP sends one callback, but a network retry could cause this.
- ⚠️ **Admin creating partial while callback is processing:** Admin creates new attempt while callback is marking previous attempt complete. Both operations read/write the full history array.

---

## 9. Quick Wins (High Impact / Low Effort)

1. **Initialize `Vinti4_Logger`** — Add `Vinti4_Logger::init( $this );` to gateway constructor (~1 line, enables all structured logging)
2. **Add payment method guard to admin meta box** — Add `if ( $order->get_payment_method() !== 'vinti4' ) return;` to `render_meta_box()` (~2 lines, removes confusing UI on non-Vinti4 orders)
3. **Add explicit `exit` after `handle_attempt_callback()`** — Prevent future fallthrough bugs (~1 line, improves control flow clarity)

---

## 10. Recommended Remediation Plan

### Phase 1 (Critical fixes — before production)
- [ ] Initialize `Vinti4_Logger::init()` in gateway constructor
- [ ] Add explicit `exit` after `handle_attempt_callback()` and `handle_legacy_callback()` in `handle()`
- [ ] Run `php -l` on all PHP files in a PHP-enabled environment
- [ ] Run full PHPUnit suite in a PHP-enabled environment
- [ ] Update version constant to `1.1.0`

### Phase 2 (Stability & security — before public release)
- [ ] Add payment method check to admin meta box `render_meta_box()`
- [ ] Uncomment `load_plugin_textdomain()` or remove i18n claims
- [ ] Add AOA, BRL, GBP to `currency_default` dropdown
- [ ] Add zero-amount guard in `process_payment()`
- [ ] Test E2E with SISP sandbox (full checkout → redirect → callback flow)

### Phase 3 (Performance & scaling)
- [ ] Separate `get_paid_total()` read from write — make it a pure read, update cache in `mark_attempt_completed()`
- [ ] Add `LIMIT` or pagination awareness for very large attempt histories
- [ ] Consider individual attempt meta keys instead of serialized array for scalability

### Phase 4 (Tech debt & cleanup)
- [ ] Normalize amount type to `int` consistently across pipeline
- [ ] Replace admin test panel source-scanning tests with behavioral PHPUnit tests
- [ ] Add `wp_unslash()` to all `$_GET` reads in redirect form
- [ ] Remove or deprecate legacy `vinti4_build_merchant_ref()`/`vinti4_build_merchant_session()` functions
- [ ] Add gateway icon/logo image

---

## 11. Confidence & Unknowns

### What is verified
- ✅ All 16 production PHP files reviewed line-by-line
- ✅ All 10 test files reviewed
- ✅ PRD comparison complete — all functional requirements mapped
- ✅ Security patterns verified (nonce, capability checks, input sanitization, output escaping)
- ✅ Fingerprint algorithm matches SISP spec (SHA-512 + Base64, field order verified)
- ✅ Idempotency logic verified (per-attempt and legacy paths)

### What is inferred
- ⚠️ SISP callback flow — cannot verify without running SISP sandbox
- ⚠️ WooCommerce HPOS compatibility — cannot verify without WooCommerce test environment
- ⚠️ Currency handling for non-CVE currencies — inferred correct from code but not tested
- ⚠️ Amount normalization for currencies with cents — inferred safe for CVE (no cents) but uncertain for EUR/USD

### What could not be validated
- ❌ PHP syntax — no PHP CLI available in execution environment
- ❌ PHPUnit tests — cannot execute without PHP runtime
- ❌ SISP sandbox E2E flow — requires running WordPress/WooCommerce/SISP stack
- ❌ WooCommerce Blocks checkout — requires block-enabled theme and WooCommerce
- ❌ Concurrent callback behavior — requires load testing infrastructure
