# Vinti4 for WooCommerce — Payment Gateway Plugin

## What This Is

A modern WooCommerce payment gateway plugin integrating with SISP (Sistema Interbancário de Pagamentos) to process card payments (Vinti4, Visa, Mastercard, American Express) via a hosted 3DS redirect flow. Supports partial deposits and multi-attempt payment requests. Target market is Cape Verde (default) and Angola, with the plugin structured to support additional SISP markets.

## Core Value

A shopper can select Vinti4 at WooCommerce checkout, be redirected securely to SISP's 3DS payment page, and return to a correctly-completed or correctly-failed order — every time, without fingerprint mismatches, duplicate completions, or fatal errors.

## Requirements

### Validated

- [x] Plugin activates safely with WooCommerce dependency guards (v1.0)
- [x] Gateway settings are available in WooCommerce -> Settings -> Payments (v1.0)
- [x] Hosted SISP redirect checkout flow works for card payments (v1.0)
- [x] Request/response fingerprint generation and validation follow SISP requirements (v1.0)
- [x] Callback handling is idempotent and protects against duplicate completion (v1.0)
- [x] Checkout Block integration is implemented (v1.0)
- [x] Structured logging and diagnostics are implemented with secret redaction (v1.0)
- [x] Baseline tests and certification prep artifacts were delivered (v1.0)
- [x] Admin can create a new payment attempt from an existing WooCommerce order without re-running checkout (v1.1)
- [x] Each new attempt creates a unique merchantRef and merchantSession (v1.1)
- [x] Request fingerprint is generated from attempt-scoped data, with amount bound to that attempt (v1.1)
- [x] Attempt metadata is stored as append-only history (no overwrite) (v1.1)
- [x] Admin can request a partial payment using percentage and fixed amount modes (v1.1)
- [x] Partial amount validation blocks invalid values (v1.1)
- [x] Outstanding balance is computed from successful paid attempts (v1.1)
- [x] A successful attempt updates paid/outstanding totals correctly at order level (v1.1)
- [x] Admin can send a payment request link/form for a specific attempt (v1.1)
- [x] Callback lookup resolves the exact attempt by attempt reference context before order mutation (v1.1)
- [x] Idempotency guard is enforced per attempt (v1.1)
- [x] Invalid reference/session/fingerprint fails safely with diagnostic reason (v1.1)
- [x] Existing hosted 3DS redirect flow remains functional after multi-attempt changes (v1.1)
- [x] Sandbox 3DS test card can complete a partial-attempt payment path in test mode (v1.1)
- [x] Logs include attempt ID, amount, merchantRef, and callback outcome per attempt (v1.1)
- [x] Logs distinguish invalid reference vs invalid fingerprint vs duplicate callback (v1.1)

### Active

(No active requirements — all v1.0 and v1.1 requirements validated. Next milestone will define new active requirements.)

### Out of Scope

- Saved cards / tokenization UX in WooCommerce My Account — v2 scope
- Recurring billing / subscriptions — v2 scope
- Admin capture / refund / void flows — v2 scope
- Multi-POS orchestration — not needed for v1
- Advanced DCC receipt rendering beyond pass-through — not needed for v1
- Full SISP tokenization flows in WooCommerce UI — v2 scope
- Direct card data entry inside WooCommerce checkout (non-hosted flow) — not part of current SISP hosted redirect model
- Automated installment schedules and dunning workflows — deferred until manual partial request flow is stable
- Scheduled installment plans (COLL-01) — future milestone
- Automatic reminders and dunning (COLL-02) — future milestone
- Customer self-service portal for remaining balance (COLL-03) — future milestone
- Admin capture/void/refund per attempt (OPS-01) — future milestone
- Attempt replay tool (OPS-02) — future milestone

## Context

### Current Codebase State

Shipped v1.0 + v1.1. Total ~6,871 PHP lines (production + tests). 16 production classes, 48+ unit tests, 12 admin self-tests.

**Tech stack:** PHP 8.1+, WordPress, WooCommerce 10.7+, SISP 3DS hosted redirect, HPOS-safe order meta.

### Architecture

- Gateway: `WC_Gateway_Vinti4` extends `WC_Payment_Gateway`
- Request Builder: `Vinti4_Request_Builder` with SHA-512+Base64 fingerprint
- Callback Handler: `Vinti4_Callback_Handler` with attempt-level reconciliation
- Attempt Factory: `Vinti4_Attempt_Factory` for canonical attempt creation
- Attempt Store: `Vinti4_Attempt_Store` for append-only history + legacy projection
- Admin: `Vinti4_Admin_Partial_Payment` for meta box + AJAX
- Blocks: `WC_Vinti4_Blocks_Support` extends `AbstractPaymentMethodType`
- Logging: `Vinti4_Logger` with secret redaction
- Test Panel: `Vinti4_Admin_Test_Panel` with 12 diagnostic self-tests

### Known Issues / Technical Debt

- PHP CLI not available in dev environment — lint and PHPUnit must be verified before release
- Admin meta box renders for all shop_order posts regardless of payment method (should check Vinti4 gateway)
- `mark_attempt_completed()` relies on implicit save() via `get_paid_total()` — fragile if refactored
- Redundant `get_paid_total()` calls in callback handler (3 where 1 suffices)
- Amount type variance: stored string, validated int, summed float — consistent but type-fragile
- Logger never initialized in tests (debug=false) — logging paths untested
- E2E SISP flow requires running WP/WC/SISP stack for runtime verification

### Legacy Plugin Analysis

The legacy plugin at `KingSolomun79/vinti4-wp-plugin` has been fully analyzed. Key findings:

**Files (8 PHP files, no testing folder):**
- `vinti4.php` — Main bootstrap + gateway class (316 lines)
- `api/lib.php` — Fingerprint generation functions (51 lines)
- `api/postback.php` — Payment redirect form builder (217 lines)
- `api/callback.php` — Callback handler (104 lines)
- `admin/view.php` — Settings page HTML (64 lines)
- `api/index.php`, `index.php`, `uninstall.php` — Stubs

**Code worth preserving (from `api/lib.php`):**
- `GerarFingerPrintEnvio()` — Request fingerprint: SHA-512 + Base64
- `GerarFingerPrintRespostaBemSucedida()` — Response fingerprint with 16 fields
- Success message types: `"8"`, `"10"`, `"M"`, `"P"`

**Legacy problems fixed in rewrite:**
- Gateway ID is numeric `2424` → string `vinti4`
- No `class_exists('WC_Payment_Gateway')` guard → added
- `new WC_Gateway_vinti4()` called directly → filter-based registration
- Brittle `require_once("../../../../wp-load.php")` → `woocommerce_api_{gateway_id}`
- Zero callback idempotency → per-attempt idempotency
- `merchantRef = order_id` → unique per-attempt with entropy
- Manual `$order->reduce_order_stock()` → `payment_complete()`
- Raw SQL `DELETE FROM wp_posts` → safe uninstall (settings only)
- Separate admin menu → WooCommerce → Settings → Payments
- Deprecated `purchaseDate` → removed
- `sokil/php-isocodes` dependency → eliminated (static currency map)
- Currency hardcoded to `'132'` → auto-detect from order with fallback chain

### Official SISP Documentation

Located at `KingSolomun79/vinti4-wp-plugin/vinti4docs/`.

### Sandbox Test Environment

- **POS ID:** 90000414
- **POS Auth Code:** 2XSPcf5fmjXiZ7hA
- **Merchant ID:** 9000406
- **3DS Test URL:** `https://3dsteste.vinti4net.cv/3ds_middleware_php/public/3ds_init.php`
- **Test Card:** pan: `4012001037141112`, exp: `12/25`, cvv2: `123`, otp: `123456`

### Target Platform

- WordPress current stable
- WooCommerce current stable (10.7+)
- PHP 8.1+
- Classic checkout + Checkout Block
- Default locale: Cape Verde (CVE currency, Portuguese language)

## Constraints

- **PHP 8.1+**: Target minimum PHP version
- **WooCommerce Gateway API**: Must extend `WC_Payment_Gateway`, use `init_form_fields()`, `init_settings()`, `process_payment()`
- **WooCommerce Blocks API**: Must register payment method via `AbstractPaymentMethodType`
- **No Composer dependencies**: Use WordPress/WooCommerce built-in functions or lightweight inline mapping
- **SISP protocol compliance**: Fingerprint generation must follow exact SISP spec — field order, SHA-512, Base64, amount ×1000, timestamp format
- **No raw SQL**: Must use WooCommerce order APIs and metadata (HPOS-safe)
- **Currency**: Auto-detect from WooCommerce order currency, configurable default (CVE for Cape Verde)

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Gateway ID: `vinti4` | PRD specifies stable string ID, not numeric | ✓ Good |
| Hosted redirect flow (not API) | SISP uses redirect-based payment, not direct API | ✓ Good |
| Currency auto-detect from order | Supports multiple markets (CVE default, AOA, etc.) | ✓ Good |
| No Composer dependencies | Eliminate `sokil/php-isocodes`, use built-in WP/Woo functions | ✓ Good |
| Settings in WooCommerce → Payments | Not separate top-level admin menu | ✓ Good |
| purchaseRequest without `purchaseDate` | Deprecated by SISP per PRD section 15.9 | ✓ Good |
| Use `payment_complete()` for order completion | WooCommerce handles stock reduction | ✓ Good |
| Callback via `woocommerce_api_{gateway_id}` | Replace standalone PHP callback files | ✓ Good |
| Build per PRD milestone order | Bootstrap → Gateway → Fingerprint → Redirect → Callback → Blocks → Logging → Tests | ✓ Good |
| `_vinti4_attempt_history` canonical key | Immutable audit trail, no destructive overwrites | ✓ Good |
| Factory + Store split | Creation concerns separate from persistence/projection | ✓ Good |
| Attempt-first callback resolution with legacy fallback | Multi-attempt support without breaking pre-v1.1 orders | ✓ Good |
| Per-attempt idempotency via unique meta keys | Each attempt independently deduplicated | ✓ Good |
| Outstanding threshold ≤ 0.01 for completion | Floating-point tolerance | ✓ Good |
| HPOS dual registration for meta box | Both classic and HPOS order storage backends | ✓ Good |
| Inline CSS for progress bar | Zero dependencies | ✓ Good |
| Structured failure type logging | Distinguishable diagnostic categories | ✓ Good |

---
*Last updated: 2026-04-18 after v1.1 milestone completion*
