# Vinti4 for WooCommerce — Payment Gateway Plugin

## What This Is

A complete rewrite of the Vinti4 WooCommerce payment gateway plugin — a modern WooCommerce payment extension that integrates with SISP (Sistema Interbancário de Pagamentos) to process card payments (Vinti4, Visa, Mastercard, American Express) via a hosted 3DS redirect flow. Target market is Cape Verde (default) and Angola, structured to support additional SISP markets.

## Core Value

A shopper can select Vinti4 at WooCommerce checkout, be redirected securely to SISP's 3DS payment page, and return to a correctly-completed or correctly-failed order — every time, without fingerprint mismatches, duplicate completions, or fatal errors.

## Requirements

### Validated

- ✓ Plugin activates without fatal errors when WooCommerce is active — v1.0
- ✓ Plugin shows admin notice and stays dormant when WooCommerce is missing — v1.0
- ✓ Gateway appears in WooCommerce → Settings → Payments — v1.0
- ✓ Gateway appears in classic shortcode checkout — v1.0
- ✓ Gateway appears in Checkout Block — v1.0
- ✓ Shopper can complete a payment via SISP hosted redirect — v1.0
- ✓ Order is completed exactly once on successful payment callback — v1.0
- ✓ Failed payment marks order failed and returns shopper to checkout — v1.0
- ✓ Duplicate callbacks are safely rejected (no double-completion) — v1.0
- ✓ Request fingerprint is generated from a single canonical code path — v1.0
- ✓ Response fingerprint is validated before order completion — v1.0
- ✓ All request fields are stored on the order before redirect — v1.0
- ✓ Each payment attempt has a unique merchantRef and merchantSession — v1.0
- ✓ Callbacks are handled via WooCommerce API endpoint (no standalone PHP files) — v1.0
- ✓ Currency is auto-detected from WooCommerce order currency with configurable default (CVE) — v1.0
- ✓ Debug logging captures attempt context without exposing secrets — v1.0
- ✓ No raw SQL, no page creation on activation, no manual stock reduction — v1.0
- ✓ Auth secret fields preserve valid characters (no aggressive sanitization) — v1.0

### Active

(None — awaiting v2 requirements definition)

### Out of Scope

- Saved cards / tokenization UX in WooCommerce My Account — v2 scope
- Recurring billing / subscriptions — v2 scope
- Admin capture / refund / void flows — v2 scope
- Multi-POS orchestration — not needed for v1
- Advanced DCC receipt rendering beyond pass-through — not needed for v1
- Full SISP tokenization flows in WooCommerce UI — v2 scope

## Context

### Current State

**Shipped:** v1.0 MVP (2026-04-16)
**Tech stack:** PHP 8.1+, WordPress, WooCommerce 10.7+, PHPUnit 10
**Production code:** ~2,069 lines PHP, ~112 lines JS
**Test code:** ~869 lines PHP (27 tests, 39 assertions)
**Dependencies:** Zero Composer dependencies (production)

**Production files (13 classes + 1 bootstrap):**
- `vinti4.php` — Plugin bootstrap with dependency guards
- `includes/class-wc-gateway-vinti4.php` — Main gateway class (settings, process_payment, callback)
- `includes/class-vinti4-fingerprint.php` — SHA-512+Base64 fingerprint generation
- `includes/class-vinti4-request-builder.php` — Canonical payment request builder
- `includes/class-vinti4-redirect-form.php` — Auto-posting HTML form to SISP
- `includes/class-vinti4-callback-handler.php` — 9-step callback validation with idempotency
- `includes/class-vinti4-logger.php` — Structured debug logging with auth code masking
- `includes/class-vinti4-admin-notices.php` — Missing WooCommerce admin notice
- `includes/class-vinti4-admin-test-panel.php` — 12 self-diagnostic tests
- `includes/class-wc-vinti4-blocks-support.php` — Checkout Block integration
- `includes/functions-vinti4-formatting.php` — 6 formatting helper functions
- `assets/js/blocks.js` — Block payment method registration
- `uninstall.php` — Safe uninstall (settings option only)

### Legacy Plugin Analysis

<details>
<summary>Legacy analysis (preserved for reference)</summary>

The legacy plugin at `KingSolomun79/vinti4-wp-plugin` was fully analyzed. Key findings:

**Files (8 PHP files, no testing folder):**
- `vinti4.php` — Main bootstrap + gateway class (316 lines)
- `api/lib.php` — Fingerprint generation functions (51 lines)
- `api/postback.php` — Payment redirect form builder (217 lines)
- `api/callback.php` — Callback handler (104 lines)
- `admin/view.php` — Settings page HTML (64 lines)
- `api/index.php`, `index.php`, `uninstall.php` — Stubs

**Legacy problems that were ALL fixed:**
- Gateway ID was numeric `2424` → string `vinti4`
- No `class_exists('WC_Payment_Gateway')` guard → added
- Direct instantiation `new WC_Gateway_vinti4()` → filter-based registration
- Brittle `require_once("../../../../wp-load.php")` → WooCommerce API endpoint
- Zero callback idempotency → `_vinti4_callback_processed` meta protection
- `merchantRef = order_id` → `WC{id}-YYYYMMDDHHmmss`
- Manual `$order->reduce_order_stock()` → `payment_complete()`
- Raw SQL `DELETE FROM wp_posts` → settings option deletion only
- Creates pages on activation → no page creation
- Separate admin menu → WooCommerce payment settings
- `purchaseDate` in JSON → removed (deprecated by SISP)
- `sokil/php-isocodes` dependency → eliminated, static map
- Currency hardcoded to `'132'` → auto-detect with CVE fallback

</details>

### Sandbox Test Environment

- **POS ID:** 90000414
- **POS Auth Code:** 2XSPcf5fmjXiZ7hA
- **Merchant ID:** 9000406
- **3DS Test URL:** `https://3dsteste.vinti4net.cv/3ds_middleware_php/public/3ds_init.php`
- **Test Card:** pan: `4012001037141112`, exp: `12/25`, cvv2: `123`, otp: `123456`

## Constraints

- **PHP 8.1+**: Target minimum PHP version
- **WooCommerce Gateway API**: Must extend `WC_Payment_Gateway`, use `init_form_fields()`, `init_settings()`, `process_payment()`
- **WooCommerce Blocks API**: Must register payment method via `AbstractPaymentMethodType`
- **No Composer dependencies**: Legacy used `sokil/php-isocodes` — eliminated with static map
- **SISP protocol compliance**: Fingerprint generation must follow exact SISP spec — field order, SHA-512, Base64, amount ×1000, timestamp format
- **No raw SQL**: Must use WooCommerce order APIs and metadata (HPOS-safe)
- **Currency**: Auto-detect from WooCommerce order currency, configurable default (CVE for Cape Verde)

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Gateway ID: `vinti4` | PRD specifies stable string ID, not numeric | ✓ Good — stable identifier |
| Hosted redirect flow (not API) | SISP uses redirect-based payment, not direct API | ✓ Good — matches SISP architecture |
| Currency auto-detect from order | Supports multiple markets (CVE default, AOA, etc.) | ✓ Good — multi-market ready |
| No Composer dependencies | Eliminate `sokil/php-isocodes`, use built-in WP/Woo functions | ✓ Good — zero production deps |
| Settings in WooCommerce → Payments | Not separate top-level admin menu | ✓ Good — follows WooCommerce conventions |
| purchaseRequest without `purchaseDate` | Deprecated by SISP per PRD section 15.9 | ✓ Good — future-proof |
| Use `payment_complete()` for order completion | WooCommerce handles stock reduction | ✓ Good — HPOS-safe, correct lifecycle |
| Callback via `woocommerce_api_{gateway_id}` | Replace standalone PHP callback files | ✓ Good — proper WC integration |
| Auth code sanitization via wp_unslash() only | Preserve % + / = in SISP auth codes | ✓ Good — no data loss |
| Static builder methods for fingerprint/request | No instance state needed | ✓ Good — clean, testable |
| Plain JS IIFE for block registration | No build step needed | ✓ Good — zero build complexity |
| PHPUnit with WP stubs | Test without WordPress installation | ✓ Good — CI-friendly |

---
*Last updated: 2026-04-16 after v1.0 milestone*
