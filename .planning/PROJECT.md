# Vinti4 for WooCommerce — v1 Remediation

## What This Is

A complete rewrite of the Vinti4 WooCommerce payment gateway plugin from a brittle legacy adaptation into a proper, modern WooCommerce payment extension. The plugin integrates with SISP (Sistema Interbancário de Pagamentos) to process card payments (Vinti4, Visa, Mastercard, American Express) via a hosted redirect flow. Target market is Cape Verde (default) and Angola, with the plugin structured to support additional SISP markets.

## Core Value

A shopper can select Vinti4 at WooCommerce checkout, be redirected securely to SISP's 3DS payment page, and return to a correctly-completed or correctly-failed order — every time, without fingerprint mismatches, duplicate completions, or fatal errors.

## Current Milestone: v1.1 Partial Deposits and Multi-Attempt Payments

**Goal:** Enable partial payment requests from admin/dashboard by creating fresh, attempt-scoped SISP references for each payment request while preserving the existing card redirect flow.

**Target features:**
- Admin can create a new payment attempt for an existing WooCommerce order using a partial amount (percentage or fixed amount)
- Every partial attempt generates a unique merchantRef, merchantSession, and fingerprint bound to that exact amount
- Attempt history is persisted per order so retries and installments do not overwrite the original 100% checkout context
- Callback and reconciliation logic resolves per attempt and supports partial-paid progression to fully paid

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

### Active

- [ ] Admin can send a payment request for a partial amount on an existing order
- [ ] Partial amount requests always create a fresh merchantRef + merchantSession + fingerprint tuple
- [ ] Fingerprint amount always matches the exact partial attempt amount (SISP validation-safe)
- [ ] Attempt metadata is stored as attempt history per order (no destructive overwrite of previous attempts)
- [ ] Callback validation resolves against the specific attempt, not only the original order-level attempt
- [ ] Duplicate callbacks are prevented per attempt in multi-attempt flows
- [ ] Order payment state tracks partial-paid versus fully-paid progression across attempts
- [ ] Existing card payment rails (Vinti4/Visa/Mastercard/Amex through SISP 3DS redirect) remain working after multi-attempt changes

### Out of Scope

- Saved cards / tokenization UX in WooCommerce My Account — v2 scope
- Recurring billing / subscriptions — v2 scope
- Admin capture / refund / void flows — v2 scope
- Multi-POS orchestration — not needed for v1
- Advanced DCC receipt rendering beyond pass-through — not needed for v1
- Full SISP tokenization flows in WooCommerce UI — v2 scope
- Direct card data entry inside WooCommerce checkout (non-hosted flow) — not part of current SISP hosted redirect model
- Automated installment schedules and dunning workflows — deferred until manual partial request flow is stable

## Context

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
- `GerarFingerPrintEnvio()` — Request fingerprint: SHA-512 + Base64, field order: `sha512(posAutCode) + timestamp + (amount*1000) + merchantRef + merchantSession + posID + currency + transactionCode + entityCode + referenceNumber`
- `GerarFingerPrintRespostaBemSucedida()` — Response fingerprint with 16 fields including messageType, clearingPeriod, transactionID, merchantReference, amount, pan, merchantResponse, etc.
- Success message types: `"8"`, `"10"`, `"M"`, `"P"`

**Legacy problems that MUST be fixed:**
- Gateway ID is numeric `2424` (must be string `vinti4`)
- No `class_exists('WC_Payment_Gateway')` guard
- `new WC_Gateway_vinti4()` called directly in bootstrap
- `api/callback.php` uses `require_once("../../../../wp-load.php")` (brittle)
- `api/postback.php` uses same brittle wp-load pattern
- Zero callback idempotency / duplicate protection
- `merchantRef = order_id` (bare order ID, not unique per attempt)
- Manual `$order->reduce_order_stock()` instead of `payment_complete()`
- Raw SQL: `DELETE FROM wp_posts WHERE post_title LIKE '%vinti4%'`
- Creates pages on activation, deletes on deactivation
- Separate top-level admin menu (not WooCommerce payment settings)
- `purchaseDate` still in purchaseRequest JSON (deprecated by SISP)
- Uses `sokil/php-isocodes` Composer dependency (should be eliminated)
- Currency hardcoded to `'132'`

**purchaseRequest JSON structure (from `api/postback.php`):**
- `acctID`, `acctInfo` (chAccAgeInd, chAccChange, chAccDate, etc.)
- `email`, `addrMatch`
- Billing address: `billAddrCity`, `billAddrCountry`, `billAddrLine1/2/3`, `billAddrPostCode`, `billAddrState`
- Shipping address: `shipAddrCity`, `shipAddrCountry`, `shipAddrLine1`, `shipAddrPostCode`, `shipAddrState`
- Phone: `workPhone`, `mobilePhone` (with cc and subscriber fields)
- `purchaseDate` (DEPRECATED — must be removed per PRD)

### Official SISP Documentation

Located at `KingSolomun79/vinti4-wp-plugin/vinti4docs/`. Key files:
- `MOP021.013_Pagamento Web - Especificação de Serviço.pdf` — Main service spec
- `Pagamento Web - Especificação do Protocolo de Segurança v2.0.pdf` — Security/fingerprint spec
- `MD044.01_FAQs Migração Para o Protocolo 3DSServer 2.2.0.pdf` — 3DS migration guide
- `nodejs-vinti4.md` — Node.js code example (fully readable, confirms fingerprint algorithm)
- `Exemplo de Código em PHP.pdf` — PHP code example

**Fingerprint algorithm (confirmed across all sources):**
- Request: `SHA512(SHA512(posAutCode) + timestamp + amount*1000 + merchantRef + merchantSession + posID + currency + transactionCode [+ entityCode + referenceNumber])` → Base64
- Response (success): Same pattern with additional fields (messageType, clearingPeriod, transactionID, pan, merchantResponse, etc.)
- Amount in hash = integer amount × 1000
- Timestamp format = `yyyy-MM-dd HH:mm:ss`

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
- **No Composer dependencies**: Legacy used `sokil/php-isocodes` for country codes — use WordPress/WooCommerce built-in functions or lightweight inline mapping instead
- **SISP protocol compliance**: Fingerprint generation must follow exact SISP spec — field order, SHA-512, Base64, amount ×1000, timestamp format
- **No raw SQL**: Must use WooCommerce order APIs and metadata (HPOS-safe)
- **Currency**: Auto-detect from WooCommerce order currency, configurable default (CVE for Cape Verde)

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Gateway ID: `vinti4` | PRD specifies stable string ID, not numeric | — Pending |
| Hosted redirect flow (not API) | SISP uses redirect-based payment, not direct API | — Pending |
| Currency auto-detect from order | Supports multiple markets (CVE default, AOA, etc.) | — Pending |
| No Composer dependencies | Eliminate `sokil/php-isocodes`, use built-in WP/Woo functions | — Pending |
| Settings in WooCommerce → Payments | Not separate top-level admin menu | — Pending |
| purchaseRequest without `purchaseDate` | Deprecated by SISP per PRD section 15.9 | — Pending |
| Use `payment_complete()` for order completion | WooCommerce handles stock reduction | — Pending |
| Callback via `woocommerce_api_{gateway_id}` | Replace standalone PHP callback files | — Pending |
| Build per PRD milestone order | Bootstrap → Gateway → Fingerprint → Redirect → Callback → Blocks → Logging → Tests | — Pending |

---
*Last updated: 2026-04-17 after starting milestone v1.1*
