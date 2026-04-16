# Vinti4 for WooCommerce — v1 Remediation PRD

## Document control

| Field               | Value                                                                                                                                     |
| ------------------- | ----------------------------------------------------------------------------------------------------------------------------------------- |
| Product             | Vinti4 for WooCommerce                                                                                                                    |
| Version             | v1.0                                                                                                                                      |
| Type                | Product Requirements Document                                                                                                             |
| Goal                | Fix activation failures, current WooCommerce integration failures, fingerprint instability, callback handling, and checkout compatibility |
| Scope               | Existing legacy Vinti4 WordPress/WooCommerce plugin                                                                                       |
| Out of scope for v1 | Saved cards in WooCommerce account area, subscriptions, refunds UI, admin capture/void tooling, full tokenization UX                      |

## 1. Executive summary

The current plugin is a legacy WooCommerce gateway adaptation that breaks on modern WordPress/WooCommerce because it is not structured as a current WooCommerce payment extension. The main failures are: unsafe activation/bootstrap behavior, missing WooCommerce dependency guards, brittle standalone callback files, weak request-attempt persistence, outdated 3DS request shaping, no Cart/Checkout Block integration, and fingerprint-related instability caused by non-canonical request handling. WooCommerce’s gateway docs require a class-based gateway with guarded loading, proper settings, and gateway lifecycle hooks; Cart and Checkout Blocks require explicit payment-method integration; and WooCommerce 10.7 continues to invest in checkout, Store API, HPOS, and block fixes, so the extension must align with those patterns. ([The WooCommerce Developer Blog][1])

v1 will deliver a stable, production-usable hosted redirect gateway for Vinti4/SISP that:

* activates safely on current WooCommerce,
* works with classic checkout and Checkout Block,
* uses WooCommerce-native gateway lifecycle and callback routing,
* stores a canonical payment attempt before redirect,
* reduces fingerprint mismatch risk,
* validates callbacks idempotently,
* avoids raw SQL / brittle page creation patterns,
* is structured for later tokenization work.

## 2. Problem statement

The current plugin fails in several categories:

### 2.1 Activation and bootstrap failures

The plugin assumes WooCommerce base classes are available during load and does not safely guard gateway class definition and initialization. WooCommerce’s plugin-base docs show the expected guarded pattern using `class_exists( 'WC_Payment_Gateway' )`. ([The WooCommerce Developer Blog][1])

### 2.2 Gateway architecture is outdated

The plugin relies on old patterns, custom pages, standalone PHP callback files, and manual order finalization instead of WooCommerce-native gateway APIs. WooCommerce’s Payment Gateway API expects a gateway class extending `WC_Payment_Gateway`, settings initialized with `init_form_fields()` / `init_settings()`, and payment flows centered around `process_payment()` and API hooks. ([The WooCommerce Developer Blog][2])

### 2.3 Checkout Block incompatibility

Modern WooCommerce stores often use Cart and Checkout Blocks. Payment methods for blocks must be registered explicitly. Without that, the gateway may not appear or behave correctly in block checkout. ([The WooCommerce Developer Blog][3])

### 2.4 Fingerprint instability

SISP requires exact fingerprint generation and validation with strict field order, timestamp formatting, integer amount treatment, SHA-512 + Base64, and exact request/response field handling. The plugin’s legacy flow does not persist a canonical payment attempt strongly enough, which makes retries, callback matching, and debugging fragile. SISP’s security and service docs are explicit that fingerprint generation and response validation must follow the documentation exactly.   

### 2.5 Outdated request shaping

The SISP service spec was updated in May 2023 with parameter name corrections and 3DSServer requirement changes. It also notes that `purchaseDate` was excluded from the JSON and `TimeStamp` should be considered instead. 

### 2.6 Unsafe lifecycle behavior

The current plugin creates pages on activation, deletes content directly from posts tables on deactivation, and manually reduces stock / empties cart during callback success handling. Modern WooCommerce patterns should rely on order APIs and `payment_complete()` rather than manual stock/order side effects. WooCommerce’s docs note that stock reduction is handled by core payment completion flow. ([The WooCommerce Developer Blog][2])

## 3. Objectives

### 3.1 Primary objectives

* Prevent critical-error activation on current WooCommerce.
* Deliver a stable hosted-redirect payment flow for Vinti4.
* Support classic shortcode checkout and Cart/Checkout Blocks.
* Eliminate the main structural causes of fingerprint issues.
* Make callback handling idempotent and tied to the exact payment attempt.
* Align with WooCommerce 10.7-era extension patterns around checkout, blocks, Store API, and HPOS-safe behavior. ([The WooCommerce Developer Blog][2])

### 3.2 Secondary objectives

* Improve observability for support/debugging.
* Create a clean codebase foundation for v2 tokenization and richer merchant tooling.
* Remove unsafe plugin lifecycle behavior.

## 4. Non-goals for v1

v1 will not include:

* full saved-card/token UX inside My Account,
* recurring billing / subscriptions,
* admin capture / refund / void flows,
* multi-POS orchestration,
* advanced DCC receipt rendering beyond pass-through structure,
* full SISP tokenization flows in WooCommerce UI.

## 5. Users and stakeholders

### 5.1 Merchant admin

Needs a plugin that activates safely, can be configured inside WooCommerce payment settings, and produces reliable checkout behavior.

### 5.2 Shopper

Needs a clear checkout option, safe redirect to SISP, a reliable return path, and correct order state after payment.

### 5.3 Support / developer

Needs logs, payment attempt metadata, transaction correlation, and deterministic fingerprint inputs.

## 6. Functional requirements

### FR-1 Safe plugin activation

The plugin must activate without fatal errors when WooCommerce is active, and must fail gracefully when WooCommerce is missing or inactive. This requires guarded loading using WooCommerce class checks. ([The WooCommerce Developer Blog][1])

### FR-2 WooCommerce-native gateway registration

The gateway must be registered using the `woocommerce_payment_gateways` filter and extend `WC_Payment_Gateway`. It must use `init_form_fields()` and `init_settings()` for configuration. ([The WooCommerce Developer Blog][2])

### FR-3 Hosted redirect flow

`process_payment()` must create a canonical payment attempt, store the relevant fields on the order, and redirect the shopper to a controlled gateway-start page or receipt step that auto-posts to SISP.

### FR-4 Canonical fingerprint generation

The plugin must build the request fingerprint from a single canonical function using exact SISP field ordering and normalization rules. Request fields must be stored on the order before redirect. SISP requires SHA-512 over UTF-8 bytes, Base64 encoding, exact parameter ordering, strict timestamp formatting, amount ×1000 treatment, and field-specific normalization. 

### FR-5 Canonical response fingerprint validation

The plugin must validate response fingerprints using a dedicated function that matches SISP’s response rules for success/error message types. SISP’s security doc and examples require developers to validate response fingerprints to prevent fraudulent acceptance.   

### FR-6 Unique merchant reference per attempt

Each payment attempt must generate a unique `merchantRef` and `merchantSession`, stored on the order and used for callback matching. This reduces retry ambiguity and support/debugging risk.

### FR-7 WooCommerce API callback endpoint

The plugin must replace standalone PHP callback files with a WooCommerce API endpoint via `woocommerce_api_{gateway_id}`.

### FR-8 Idempotent callback processing

The callback handler must:

* reject missing/invalid orders,
* verify the payment attempt context,
* verify fingerprint,
* verify success/failure state,
* verify amount,
* prevent double-processing,
* save transaction identifiers,
* call `payment_complete()` only once.

### FR-9 Checkout Block support

The gateway must register a block payment-method integration so it appears and works in Cart/Checkout Blocks. ([The WooCommerce Developer Blog][3])

### FR-10 Settings inside WooCommerce

The plugin must expose settings in WooCommerce → Settings → Payments, not via a separate top-level menu for core gateway config.

### FR-11 HPOS-safe / modern WooCommerce-safe behavior

The plugin must avoid direct SQL writes/deletes against posts/order tables and rely on WooCommerce order APIs and metadata. WooCommerce 10.7 continues improving HPOS and checkout/Store API performance, so extension behavior should align with those APIs. ([The WooCommerce Developer Blog][2])

### FR-12 Logging

The plugin must support structured debug logging for:

* request fingerprint input fields,
* outgoing request payload identifiers,
* callback receipt,
* callback validation result,
* duplicate callback handling.

Sensitive values such as full auth secrets must never be logged.

## 7. Non-functional requirements

### NFR-1 Reliability

* No fatal errors on activation when WooCommerce is active.
* No duplicate order completion on repeated callbacks.
* Retries must not corrupt order state.

### NFR-2 Security

* Secrets stored in WooCommerce gateway settings.
* Password/auth fields should preserve valid characters; WooCommerce 10.7 specifically notes password fields now use `trim()` instead of `sanitize_text_field()` to preserve `%` characters. Follow that handling pattern for auth secrets. ([The WooCommerce Developer Blog][2])
* No raw SQL content deletion.
* Validate all callbacks strictly before completing orders.

### NFR-3 Maintainability

* Separate bootstrap, gateway logic, block integration, helper functions, templates, and assets.
* No business logic in standalone public PHP endpoints.

### NFR-4 Compatibility

* WordPress current stable
* WooCommerce current stable
* PHP 8.1+
* Classic checkout and Checkout Block

## 8. What to fix summary

### Critical

1. Add WooCommerce dependency guards.
2. Replace legacy bootstrap with modern gateway registration.
3. Remove direct gateway instantiation during load.
4. Replace `api/postback.php` and `api/callback.php` with gateway-owned lifecycle + WC API callback.
5. Add block payment-method support.
6. Make request and response fingerprint handling canonical and stored.
7. Make callback idempotent.

### High

8. Replace `merchantRef = order_id` with unique per-attempt references.
9. Move settings into WooCommerce payment settings.
10. Update 3DS payload to current SISP rules, including removal of deprecated `purchaseDate`. 

### Medium

11. Remove activation-created pages and deactivation deletes.
12. Stop manual stock reduction / cart emptying in callback success path.
13. Add debug logging and order notes.
14. Normalize checkout error and return flows.

## 9. v1 success criteria

A build is accepted when all of the following are true:

* Plugin activates without a critical error on a site with WooCommerce active.
* Plugin does not fatal if WooCommerce is inactive; instead it shows an admin notice and remains dormant.
* Vinti4 appears under WooCommerce payment methods.
* Vinti4 appears in classic checkout and Checkout Block.
* A successful payment attempt completes the order exactly once.
* A failed payment attempt marks the order failed/pending as designed and returns the customer safely.
* Duplicate callbacks do not double-complete or double-note the order.
* All request fingerprint inputs are stored on the order before redirect.
* Support logs can explain the difference between:

  * request formation issue,
  * callback fingerprint mismatch,
  * duplicate callback,
  * invalid amount / invalid reference,
  * transport failure.

## 10. Spec kit

## 10.1 Architecture decision

**Chosen v1 architecture:** hosted redirect gateway with WooCommerce-native bootstrap and callback.

Flow:

1. Shopper selects Vinti4 in Woo checkout.
2. `process_payment()` validates config and creates a canonical payment attempt.
3. Gateway stores request fields and fingerprint input fields in order meta.
4. Shopper is sent to a receipt/start page that auto-posts to SISP.
5. SISP sends result to WooCommerce API callback.
6. Callback validates response fingerprint + order attempt state.
7. Order is completed or failed exactly once.
8. Customer is redirected to thank-you or checkout as appropriate.

## 10.2 Data model (order meta)

Store these meta keys per attempt:

```text
_vinti4_attempt_id
_vinti4_merchant_ref
_vinti4_merchant_session
_vinti4_timestamp
_vinti4_transaction_code
_vinti4_amount
_vinti4_currency
_vinti4_entity_code
_vinti4_reference_number
_vinti4_purchase_request_b64
_vinti4_request_fingerprint
_vinti4_callback_processed
_vinti4_callback_result
_vinti4_callback_message_type
_vinti4_transaction_id
_vinti4_clearing_period
_vinti4_raw_response_hash_debug   // optional sanitized debug string, no secrets
```

## 10.3 Settings schema

Gateway settings under `woocommerce_vinti4_settings`:

```text
enabled               checkbox
title                 text
description           textarea
pos_id                text
pos_auth_code         password
vbv2_url              text
language              select (pt/en)
debug                 checkbox
```

## 10.4 Gateway ID

Use a stable string ID:

```php
vinti4
```

Do not use numeric IDs.

## 10.5 Request fingerprint rules

Implement per SISP security spec:

* SHA-512
* UTF-8 bytes
* Base64-encoded output
* no separators
* strict field order
* `TimeStamp` = `yyyy-MM-dd HH:mm:ss`
* amount in hash = integer amount × 1000
* trim merchant string fields
* normalize numeric optional fields where required by spec
* include only the fields required for the transaction context. 

## 10.6 Response fingerprint rules

Implement separate builders for:

* success payment response
* error response
* token-related message types only if tokenization is added later

For v1 basic payments, support message types relevant to standard payments and errors as documented by SISP.  

## 10.7 Checkout Block integration

Register a payment method integration using Woo Blocks’ payment method integration approach. WooCommerce docs require payment methods for blocks to supply registration, script handles, active-state checks, and payment-method data. ([The WooCommerce Developer Blog][3])

## 10.8 3DS purchaseRequest handling

Use current SISP service rules:

* remove deprecated `purchaseDate`,
* shape and base64-encode `purchaseRequest`,
* populate recommended fields from Woo customer/order data where possible,
* keep optional fields empty rather than inventing low-quality garbage values. 

## 11. Proposed repository tree

```text
vinti4-woocommerce/
├─ vinti4.php
├─ readme.txt
├─ uninstall.php
├─ assets/
│  ├─ images/
│  │  └─ logo-vinti4.png
│  └─ js/
│     └─ blocks/
│        └─ vinti4.js
├─ includes/
│  ├─ class-wc-gateway-vinti4.php
│  ├─ class-wc-vinti4-blocks-support.php
│  ├─ class-vinti4-request-builder.php
│  ├─ class-vinti4-fingerprint.php
│  ├─ class-vinti4-logger.php
│  ├─ class-vinti4-admin-notices.php
│  └─ functions-vinti4-formatting.php
├─ templates/
│  └─ payment-redirect-form.php
└─ tests/
   ├─ unit/
   │  ├─ FingerprintTest.php
   │  └─ RequestBuilderTest.php
   └─ integration/
      └─ CallbackFlowTest.php
```

## 12. Files to create

### `vinti4.php`

Plugin bootstrap only.
Responsibilities:

* plugin header,
* guarded load,
* include files,
* register gateway,
* register block support,
* admin notice if WooCommerce missing.

### `includes/class-wc-gateway-vinti4.php`

Main WooCommerce gateway.
Responsibilities:

* constructor,
* settings,
* `process_payment()`,
* receipt/start rendering,
* callback route,
* order completion/failure,
* support declarations.

### `includes/class-wc-vinti4-blocks-support.php`

Checkout Block registration and data provider.

### `includes/class-vinti4-request-builder.php`

Single place to assemble request payload and purchaseRequest JSON.

### `includes/class-vinti4-fingerprint.php`

Single place to generate:

* request fingerprint,
* success response fingerprint,
* error response fingerprint.

### `includes/class-vinti4-logger.php`

Safe wrapper over Woo logger with redaction.

### `includes/class-vinti4-admin-notices.php`

Dependency / configuration notices.

### `includes/functions-vinti4-formatting.php`

Helpers for:

* timestamp formatting,
* amount normalization,
* ISO country/state mapping adapters,
* merchantRef parsing,
* phone shaping.

### `templates/payment-redirect-form.php`

Auto-post form to SISP using the canonical stored request data.

### `assets/js/blocks/vinti4.js`

Checkout Block client registration.

### `uninstall.php`

Remove only plugin-owned options if needed. Do not delete orders/pages/posts.

## 13. Files to delete or retire

Retire these legacy patterns:

* `api/postback.php`
* `api/callback.php`
* activation-created checkout pages
* direct SQL delete logic on deactivation
* separate admin menu for core payment settings

## 14. Detailed milestones

# Milestone 1 — Safe activation and modern bootstrap

## Goal

Make the plugin activate safely and register cleanly with WooCommerce without fatal errors.

## Deliverables

* new `vinti4.php`
* dependency guards
* admin notice for missing WooCommerce
* gateway registration without direct instantiation

## Acceptance criteria

* activating with WooCommerce active does not critical error
* activating without WooCommerce does not fatal
* plugin appears in payment methods list

## Implementation guidelines

### `vinti4.php`

```php
<?php
/**
 * Plugin Name: Vinti4 for WooCommerce
 * Description: Hosted redirect payment gateway for Vinti4 / SISP.
 * Version: 1.0.0
 */

defined( 'ABSPATH' ) || exit;

define( 'VINTI4_VERSION', '1.0.0' );
define( 'VINTI4_PLUGIN_FILE', __FILE__ );
define( 'VINTI4_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'VINTI4_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once VINTI4_PLUGIN_DIR . 'includes/class-vinti4-admin-notices.php';

add_action( 'plugins_loaded', function () {
    if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'WC_Payment_Gateway' ) ) {
        Vinti4_Admin_Notices::register_missing_wc_notice();
        return;
    }

    require_once VINTI4_PLUGIN_DIR . 'includes/functions-vinti4-formatting.php';
    require_once VINTI4_PLUGIN_DIR . 'includes/class-vinti4-logger.php';
    require_once VINTI4_PLUGIN_DIR . 'includes/class-vinti4-fingerprint.php';
    require_once VINTI4_PLUGIN_DIR . 'includes/class-vinti4-request-builder.php';
    require_once VINTI4_PLUGIN_DIR . 'includes/class-wc-gateway-vinti4.php';
    require_once VINTI4_PLUGIN_DIR . 'includes/class-wc-vinti4-blocks-support.php';
}, 20 );

add_filter( 'woocommerce_payment_gateways', function ( $methods ) {
    if ( class_exists( 'WC_Gateway_Vinti4' ) ) {
        $methods[] = 'WC_Gateway_Vinti4';
    }
    return $methods;
} );

add_action( 'woocommerce_blocks_payment_method_type_registration', function ( $registry ) {
    if ( class_exists( 'WC_Vinti4_Blocks_Support' ) ) {
        $registry->register( new WC_Vinti4_Blocks_Support() );
    }
} );
```

### Notes

* No `new WC_Gateway_Vinti4()` in bootstrap.
* No activation-created pages.
* No raw SQL cleanup.

# Milestone 2 — Core gateway class and settings

## Goal

Move all payment settings and lifecycle into a proper WooCommerce gateway class.

## Deliverables

* `class-wc-gateway-vinti4.php`
* settings UI in WooCommerce
* gateway icon/title/description/supports

## Acceptance criteria

* gateway configurable in WooCommerce settings
* settings persist
* no separate top-level admin menu required

## Implementation guidelines

### Constructor skeleton

```php
class WC_Gateway_Vinti4 extends WC_Payment_Gateway {

    public function __construct() {
        $this->id                 = 'vinti4';
        $this->method_title       = __( 'Vinti4', 'vinti4' );
        $this->method_description = __( 'Pay via Vinti4 / SISP hosted payment page.', 'vinti4' );
        $this->has_fields         = false;
        $this->supports           = array( 'products' );
        $this->icon               = VINTI4_PLUGIN_URL . 'assets/images/logo-vinti4.png';

        $this->init_form_fields();
        $this->init_settings();

        $this->title       = $this->get_option( 'title' );
        $this->description = $this->get_option( 'description' );
        $this->enabled     = $this->get_option( 'enabled' );
        $this->pos_id      = trim( (string) $this->get_option( 'pos_id' ) );
        $this->pos_auth    = trim( (string) $this->get_option( 'pos_auth_code' ) );
        $this->vbv2_url    = trim( (string) $this->get_option( 'vbv2_url' ) );
        $this->language    = $this->get_option( 'language', 'pt' );
        $this->debug       = 'yes' === $this->get_option( 'debug', 'no' );

        add_action(
            'woocommerce_update_options_payment_gateways_' . $this->id,
            array( $this, 'process_admin_options' )
        );

        add_action(
            'woocommerce_api_' . strtolower( get_class( $this ) ),
            array( $this, 'handle_callback' )
        );
    }
}
```

### Settings fields

```php
public function init_form_fields() {
    $this->form_fields = array(
        'enabled' => array(
            'title'   => __( 'Enable/Disable', 'vinti4' ),
            'type'    => 'checkbox',
            'label'   => __( 'Enable Vinti4', 'vinti4' ),
            'default' => 'yes',
        ),
        'title' => array(
            'title'   => __( 'Title', 'vinti4' ),
            'type'    => 'text',
            'default' => __( 'Pay with Vinti4', 'vinti4' ),
        ),
        'description' => array(
            'title'   => __( 'Description', 'vinti4' ),
            'type'    => 'textarea',
            'default' => __( 'You will be redirected to Vinti4 to complete payment.', 'vinti4' ),
        ),
        'pos_id' => array(
            'title' => __( 'POS ID', 'vinti4' ),
            'type'  => 'text',
        ),
        'pos_auth_code' => array(
            'title' => __( 'POS Auth Code', 'vinti4' ),
            'type'  => 'password',
        ),
        'vbv2_url' => array(
            'title' => __( 'SISP Payment URL', 'vinti4' ),
            'type'  => 'text',
        ),
        'language' => array(
            'title'   => __( 'Language', 'vinti4' ),
            'type'    => 'select',
            'options' => array(
                'pt' => 'Português',
                'en' => 'English',
            ),
            'default' => 'pt',
        ),
        'debug' => array(
            'title'   => __( 'Debug logging', 'vinti4' ),
            'type'    => 'checkbox',
            'label'   => __( 'Enable debug logs', 'vinti4' ),
            'default' => 'no',
        ),
    );
}
```

# Milestone 3 — Canonical request building and fingerprint hardening

## Goal

Remove most real-world fingerprint instability by creating a single request-building pipeline.

## Deliverables

* `class-vinti4-request-builder.php`
* `class-vinti4-fingerprint.php`
* canonical order meta persistence

## Acceptance criteria

* request fingerprint generated from one code path only
* request fields stored on order before redirect
* logs can reproduce the exact fingerprint input context without exposing secrets

## Implementation guidelines

### Request builder contract

```php
class Vinti4_Request_Builder {

    public static function build_payment_attempt( WC_Order $order, WC_Gateway_Vinti4 $gateway ) : array {
        $timestamp        = gmdate( 'Y-m-d H:i:s' );
        $attempt_id       = wp_generate_uuid4();
        $merchant_ref     = 'WC' . $order->get_id() . '-' . gmdate( 'YmdHis' );
        $merchant_session = 'S' . wp_generate_password( 12, false, false );
        $transaction_code = '1';
        $amount           = (string) absint( round( (float) $order->get_total() ) );
        $currency         = '132';

        $purchase_request_json = self::build_purchase_request_json( $order );
        $purchase_request_b64  = base64_encode(
            wp_json_encode( $purchase_request_json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
        );

        $fingerprint = Vinti4_Fingerprint::build_request_fingerprint(
            $gateway->pos_auth,
            $timestamp,
            $amount,
            $merchant_ref,
            $merchant_session,
            $gateway->pos_id,
            $currency,
            $transaction_code,
            '',
            '',
            ''
        );

        return array(
            'attempt_id'            => $attempt_id,
            'timestamp'             => $timestamp,
            'merchant_ref'          => $merchant_ref,
            'merchant_session'      => $merchant_session,
            'transaction_code'      => $transaction_code,
            'amount'                => $amount,
            'currency'              => $currency,
            'purchase_request_b64'  => $purchase_request_b64,
            'fingerprint'           => $fingerprint,
        );
    }
}
```

### Fingerprint builder contract

```php
class Vinti4_Fingerprint {

    public static function sha512_base64( string $value ) : string {
        return base64_encode( hash( 'sha512', $value, true ) );
    }

    public static function build_request_fingerprint(
        string $pos_auth_code,
        string $timestamp,
        string $amount,
        string $merchant_ref,
        string $merchant_session,
        string $pos_id,
        string $currency,
        string $transaction_code,
        string $entity_code = '',
        string $reference_number = '',
        string $token = ''
    ) : string {
        $base = self::sha512_base64( $pos_auth_code )
            . trim( $timestamp )
            . (string) ( absint( $amount ) * 1000 )
            . trim( $merchant_ref )
            . trim( $merchant_session )
            . trim( $pos_id )
            . trim( $currency )
            . trim( $transaction_code );

        if ( '' !== $entity_code ) {
            $base .= (string) absint( ltrim( $entity_code, '0' ) ?: '0' );
        }

        if ( '' !== $reference_number ) {
            $base .= (string) absint( ltrim( $reference_number, '0' ) ?: '0' );
        }

        if ( '' !== $token ) {
            $base .= trim( $token );
        }

        return self::sha512_base64( $base );
    }
}
```

### Why this matters

SISP requires exact field order, SHA-512 + Base64, and strict formatting for request integrity. 

### Important rule

Never compute the outgoing fingerprint from transient form variables after the attempt has been built. Build once, store once, post once.

# Milestone 4 — Hosted redirect start page

## Goal

Use a WooCommerce-native path to present and auto-submit the SISP form.

## Deliverables

* receipt/start flow
* template file
* no standalone public `postback.php`

## Acceptance criteria

* shopper can click “Place order” and reach the SISP redirect form
* request fields match saved attempt metadata

## Implementation guidelines

### `process_payment()`

```php
public function process_payment( $order_id ) {
    $order = wc_get_order( $order_id );

    if ( ! $order ) {
        wc_add_notice( __( 'Invalid order.', 'vinti4' ), 'error' );
        return array( 'result' => 'failure' );
    }

    if ( empty( $this->pos_id ) || empty( $this->pos_auth ) || empty( $this->vbv2_url ) ) {
        wc_add_notice( __( 'Vinti4 is not configured correctly.', 'vinti4' ), 'error' );
        return array( 'result' => 'failure' );
    }

    $attempt = Vinti4_Request_Builder::build_payment_attempt( $order, $this );
    $this->persist_attempt( $order, $attempt );

    return array(
        'result'   => 'success',
        'redirect' => $order->get_checkout_payment_url( true ),
    );
}
```

### `receipt_page()`

Render `templates/payment-redirect-form.php` with the saved attempt.

### Template behavior

* form `action` = SISP URL
* hidden inputs from saved canonical attempt
* query params `FingerPrint`, `TimeStamp`, `FingerPrintVersion` where required by integration pattern
* auto-submit with JS

# Milestone 5 — Callback handling and idempotency

## Goal

Replace the brittle callback file with a safe gateway callback.

## Deliverables

* `handle_callback()`
* success/failure routing
* transaction persistence
* duplicate callback protection

## Acceptance criteria

* valid callback completes order once
* invalid callback never completes order
* duplicate callback does not mutate order twice

## Implementation guidelines

### Callback flow

```php
public function handle_callback() {
    $payload = wp_unslash( $_POST ?: array() );

    $merchant_ref = isset( $payload['merchantRespMerchantRef'] ) ? (string) $payload['merchantRespMerchantRef'] : '';
    $order_id     = vinti4_extract_order_id_from_merchant_ref( $merchant_ref );
    $order        = wc_get_order( $order_id );

    if ( ! $order ) {
        status_header( 400 );
        exit( 'Invalid order' );
    }

    if ( 'yes' === $order->get_meta( '_vinti4_callback_processed' ) || $order->is_paid() ) {
        wp_safe_redirect( $this->get_return_url( $order ) );
        exit;
    }

    $expected_ref = (string) $order->get_meta( '_vinti4_merchant_ref' );
    if ( $merchant_ref !== $expected_ref ) {
        $order->add_order_note( 'Vinti4 callback rejected: merchantRef mismatch.' );
        $order->update_status( 'failed' );
        wp_safe_redirect( wc_get_checkout_url() );
        exit;
    }

    $message_type = (string) ( $payload['messageType'] ?? '' );
    $merchant_resp = (string) ( $payload['merchantResp'] ?? '' );

    $fingerprint_ok = $this->validate_callback_fingerprint( $payload );

    if ( $fingerprint_ok && $this->is_success_payload( $message_type, $merchant_resp ) ) {
        $transaction_id = sanitize_text_field(
            (string) ( $payload['merchantRespCP'] ?? '' ) . '-' . (string) ( $payload['merchantRespTid'] ?? '' )
        );

        $order->set_transaction_id( $transaction_id );
        $order->update_meta_data( '_vinti4_callback_processed', 'yes' );
        $order->update_meta_data( '_vinti4_callback_result', 'success' );
        $order->payment_complete( $transaction_id );
        $order->add_order_note( 'Vinti4 payment confirmed by callback.' );
        $order->save();

        wp_safe_redirect( $this->get_return_url( $order ) );
        exit;
    }

    $order->update_meta_data( '_vinti4_callback_processed', 'yes' );
    $order->update_meta_data( '_vinti4_callback_result', 'failed' );
    $order->update_status( 'failed', 'Vinti4 callback failed validation or indicated failure.' );
    $order->save();

    wc_add_notice( __( 'Payment failed. Please try again.', 'vinti4' ), 'error' );
    wp_safe_redirect( wc_get_checkout_url() );
    exit;
}
```

### Important note

Use `payment_complete()` rather than manual stock reduction or direct completion logic. WooCommerce notes that stock reduction is handled in core payment completion flow. ([The WooCommerce Developer Blog][2])

# Milestone 6 — Checkout Block support

## Goal

Make the gateway visible and usable in WooCommerce blocks checkout.

## Deliverables

* `class-wc-vinti4-blocks-support.php`
* `assets/js/blocks/vinti4.js`

## Acceptance criteria

* gateway shows in Checkout Block
* title and description render correctly
* selecting Vinti4 routes through `process_payment()`

## Implementation guidelines

### PHP integration skeleton

```php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class WC_Vinti4_Blocks_Support extends AbstractPaymentMethodType {

    protected $name = 'vinti4';
    protected $settings = array();

    public function initialize() {
        $this->settings = get_option( 'woocommerce_vinti4_settings', array() );
    }

    public function is_active() {
        return ! empty( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'];
    }

    public function get_payment_method_script_handles() {
        wp_register_script(
            'wc-vinti4-blocks',
            VINTI4_PLUGIN_URL . 'assets/js/blocks/vinti4.js',
            array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities' ),
            VINTI4_VERSION,
            true
        );

        return array( 'wc-vinti4-blocks' );
    }

    public function get_payment_method_data() {
        return array(
            'title'       => $this->settings['title'] ?? 'Vinti4',
            'description' => $this->settings['description'] ?? '',
            'supports'    => array(
                'features' => array( 'products' ),
            ),
        );
    }
}
```

### JS integration skeleton

```javascript
const settings = window.wc.wcSettings.getSetting('vinti4_data', {});
const label = window.wp.htmlEntities.decodeEntities(settings.title || 'Vinti4');

window.wc.wcBlocksRegistry.registerPaymentMethod({
  name: 'vinti4',
  label: label,
  content: window.wp.element.createElement('div', {}, settings.description || 'Pay via Vinti4'),
  edit: window.wp.element.createElement('div', {}, settings.description || 'Pay via Vinti4'),
  canMakePayment: () => true,
  ariaLabel: label,
  supports: {
    features: settings.supports?.features || ['products'],
  },
});
```

WooCommerce’s blocks docs require payment-method integration registration and data provisioning. ([The WooCommerce Developer Blog][3])

# Milestone 7 — Logging, diagnostics, and supportability

## Goal

Make fingerprint and callback problems diagnosable without exposing secrets.

## Deliverables

* logger class
* structured logs
* order notes for major events

## Acceptance criteria

* support can see attempt id, merchant ref, order id, callback result
* no full POS auth code in logs
* logs can distinguish request-formation issue from callback mismatch

## Implementation guidelines

### Log fields

Allowed:

* order id
* attempt id
* merchant ref
* messageType
* merchantResp
* amount
* transaction id
* validation result flags

Forbidden:

* full `posAuthCode`
* full card data
* raw `purchaseRequest` with excessive PII unless redacted

# Milestone 8 — Testing and certification prep

## Goal

Create a minimum test harness for the new gateway and prepare for SISP certification.

## Deliverables

* unit tests for fingerprint builder
* integration tests for callback flow
* certification checklist mapping

## Acceptance criteria

* request fingerprint unit tests pass
* response fingerprint unit tests pass
* duplicate callback test passes
* failure callback test passes

## Test cases to include

* generate request fingerprint for purchase
* compare against expected fixture
* success callback with valid fingerprint
* success callback duplicate replay
* invalid fingerprint callback
* mismatched merchantRef callback
* amount mismatch callback

SISP’s plan test includes technical validation of fingerprint generation, request/response handling, and callback treatment. 

## 15. Detailed implementation guidelines

## 15.1 Dependency guard

Never define or instantiate the gateway before confirming:

```php
class_exists( 'WooCommerce' )
class_exists( 'WC_Payment_Gateway' )
```

## 15.2 Do not directly instantiate the gateway on bootstrap

Register the class with WooCommerce. Let Woo instantiate it when needed.

## 15.3 Do not use raw SQL to remove pages/posts

No direct `DELETE FROM wp_posts` behavior.

## 15.4 Do not create pages on activation

Use WooCommerce payment and thank-you flows.

## 15.5 Do not complete orders from unverified callback data

Callback must pass:

* order resolution,
* expected merchantRef,
* fingerprint validation,
* success state validation,
* duplicate protection.

## 15.6 Do not use bare order ID as merchantRef

Use a unique per-attempt value.

## 15.7 Do not manually reduce stock on callback success

Use:

```php
$order->payment_complete( $transaction_id );
```

## 15.8 Keep auth secret handling conservative

Use `trim()` semantics for secret text fields rather than aggressive sanitization that can strip valid characters. WooCommerce 10.7 explicitly notes this issue for gateway password fields. ([The WooCommerce Developer Blog][2])

## 15.9 Update purchaseRequest handling

Do not include deprecated `purchaseDate`. Use `TimeStamp` and the current 3DS field definitions from SISP’s current service spec. 

## 16. Backlog for post-v1

### v1.1

* richer admin diagnostics page
* callback replay viewer
* manual re-check endpoint using SISP transaction status API if needed. 

### v2

* tokenization flows:

  * token request,
  * token payment,
  * token cancel,
  * saved-payment-method UX in WooCommerce. SISP tokenization requires those operations and customer-facing support for token lifecycle.  

### v2.1

* refunds/void support where feasible
* My Account saved token management
* subscriptions compatibility study

## 17. Risks and mitigation

### Risk 1: fingerprint mismatch persists

Mitigation:

* canonical request storage,
* single fingerprint builder,
* deterministic normalization,
* debug logs with non-secret hash inputs.

### Risk 2: block checkout still fails on a specific store

Mitigation:

* register explicit block integration,
* test with current Woo stable,
* verify end-to-end with one-block-method and multi-method stores.

### Risk 3: legacy merchant data is incomplete for 3DS fields

Mitigation:

* populate high-confidence fields from Woo order/customer data,
* leave optional fields empty rather than inventing wrong data,
* document required merchant/customer data quality.

### Risk 4: callback replay or delayed callback

Mitigation:

* idempotency flags,
* transaction ID checks,
* order `is_paid()` guard.

## 18. Acceptance checklist

* [ ] Plugin activation safe with WooCommerce active.
* [ ] No fatal when WooCommerce inactive.
* [ ] Gateway visible in WooCommerce payments.
* [ ] Gateway visible in Checkout Block.
* [ ] Payment attempt persists canonical request data.
* [ ] Request fingerprint generated from one code path.
* [ ] Response fingerprint validated before order completion.
* [ ] Duplicate callbacks safe.
* [ ] No standalone callback PHP file needed.
* [ ] No raw SQL deletion.
* [ ] No activation-created pages.
* [ ] Uses WooCommerce `payment_complete()`.
* [ ] Logging available and secret-safe.

## 19. Recommended build order

1. Milestone 1 — bootstrap and dependency guard.
2. Milestone 2 — gateway class and settings.
3. Milestone 3 — canonical request builder + fingerprint classes.
4. Milestone 4 — hosted redirect form.
5. Milestone 5 — callback and idempotency.
6. Milestone 6 — Checkout Block support.
7. Milestone 7 — logging and diagnostics.
8. Milestone 8 — tests and certification prep.

## 20. Final build note

This v1 should be treated as a **stability and standards-alignment release**, not a feature expansion release. The main job is to make the Vinti4 plugin behave like a current WooCommerce payment extension, reduce fingerprint failures by design, and create a clean base for tokenization later.

If you want the next step, I can turn this PRD into a second document: a **developer implementation spec** with exact file-by-file code skeletons and patch-ready starter files.

[1]: https://developer.woocommerce.com/docs/features/payments/payment-gateway-plugin-base/ "WooCommerce payment gateway plugin base | WooCommerce developer docs"
[2]: https://developer.woocommerce.com/docs/features/payments/payment-gateway-api/ "WooCommerce Payment Gateway API | WooCommerce developer docs"
[3]: https://developer.woocommerce.com/docs/block-development/extensible-blocks/cart-and-checkout-blocks/checkout-payment-methods/payment-method-integration/ "Payment method integration | WooCommerce developer docs"
