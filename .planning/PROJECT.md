# Vinti4 for WooCommerce — v1 Remediation

## What This Is

A complete rewrite of the Vinti4 WooCommerce payment gateway plugin from a brittle legacy adaptation into a proper, modern WooCommerce payment extension. The plugin integrates with SISP (Sistema Interbancário de Pagamentos) to process card payments (Vinti4, Visa, Mastercard, American Express) via a hosted redirect flow. Target market is Cape Verde (default) and Angola, with the plugin structured to support additional SISP markets.

## Core Value

A shopper can select Vinti4 at WooCommerce checkout, be redirected securely to SISP's 3DS payment page, and return to a correctly-completed or correctly-failed order — every time, without fingerprint mismatches, duplicate completions, or fatal errors.

## Requirements

### Validated

(None yet — ship to validate)

### Active

- [ ] Plugin activates without fatal errors when WooCommerce is active
- [ ] Plugin shows admin notice and stays dormant when WooCommerce is missing
- [ ] Gateway appears in WooCommerce → Settings → Payments
- [ ] Gateway appears in classic shortcode checkout
- [ ] Gateway appears in Checkout Block
- [ ] Shopper can complete a payment via SISP hosted redirect
- [ ] Order is completed exactly once on successful payment callback
- [ ] Failed payment marks order failed and returns shopper to checkout
- [ ] Duplicate callbacks are safely rejected (no double-completion)
- [ ] Request fingerprint is generated from a single canonical code path
- [ ] Response fingerprint is validated before order completion
- [ ] All request fields are stored on the order before redirect
- [ ] Each payment attempt has a unique merchantRef and merchantSession
- [ ] Callbacks are handled via WooCommerce API endpoint (no standalone PHP files)
- [ ] Currency is auto-detected from WooCommerce order currency with configurable default (CVE)
- [ ] Debug logging captures attempt context without exposing secrets
- [ ] No raw SQL, no page creation on activation, no manual stock reduction
- [ ] Auth secret fields preserve valid characters (no aggressive sanitization)

### Out of Scope

- Saved cards / tokenization UX in WooCommerce My Account — v2 scope
- Recurring billing / subscriptions — v2 scope
- Admin capture / refund / void flows — v2 scope
- Multi-POS orchestration — not needed for v1
- Advanced DCC receipt rendering beyond pass-through — not needed for v1
- Full SISP tokenization flows in WooCommerce UI — v2 scope

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
*Last updated: 2026-04-16 after initialization*
