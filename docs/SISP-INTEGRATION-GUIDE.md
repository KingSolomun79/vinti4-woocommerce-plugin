# SISP/Vinti4 Payment Gateway — Complete Integration Guide

> **Version:** 2.0 (April 2026)  
> **Protocol:** SISP 3DS Hosted Payment Page  
> **Languages:** PHP reference implementation with cross-language notes  
> **Status:** Production-tested with full payments, partial payments, and 3DS/OTP flows

---

## Table of Contents

1. [Architecture Overview](#1-architecture-overview)
2. [Payment Flow (End-to-End)](#2-payment-flow-end-to-end)
3. [SISP Credentials & Configuration](#3-sisp-credentials--configuration)
4. [Fingerprint Algorithm — The Heart of the Integration](#4-fingerprint-algorithm--the-heart-of-the-integration)
5. [Request Fingerprint (Outgoing)](#5-request-fingerprint-outgoing)
6. [Response Fingerprint (Callback Verification)](#6-response-fingerprint-callback-verification)
7. [merchantRef & merchantSession — Critical Rules](#7-merchantref--merchantsession--critical-rules)
8. [Amount Handling](#8-amount-handling)
9. [Timestamp Format](#9-timestamp-format)
10. [3DS purchaseRequest JSON](#10-3ds-purchaserequest-json)
11. [Redirect Form — POSTing to SISP](#11-redirect-form--posting-to-sisp)
12. [URL Encoding — The `+` Character Trap](#12-url-encoding-the--character-trap)
13. [Callback Handling](#13-callback-handling)
14. [Idempotency](#14-idempotency)
15. [Partial Payments / Deposits](#15-partial-payments--deposits)
16. [Attempt History (Multi-Payment Tracking)](#16-attempt-history-multi-payment-tracking)
17. [Currency Codes](#17-currency-codes)
18. [Success/Failure Message Types](#18-successfailure-message-types)
19. [Complete Field Reference](#19-complete-field-reference)
20. [Common Pitfalls & Debugging](#20-common-pitfalls--debugging)
21. [Cross-Language Implementation Notes](#21-cross-language-implementation-notes)
22. [Testing Checklist](#22-testing-checklist)

---

## 1. Architecture Overview

```
┌──────────┐     ┌──────────────────┐     ┌───────────────┐     ┌──────────┐
│  Shopper  │────▶│  Your Server     │────▶│  SISP 3DS     │────▶│  Bank /   │
│  Checkout │     │  (build attempt, │     │  Payment Page │     │  Card OTP │
│           │◀────│   store meta,    │◀────│  (3DS auth,   │◀────│           │
│  Thank You│     │   render form)   │     │   callback)   │     │           │
└──────────┘     └──────────────────┘     └───────────────┘     └──────────┘
```

**The integration is a hosted redirect model:**

1. Your server builds a payment request with a cryptographic fingerprint
2. Shopper is redirected to SISP's payment page via auto-submit HTML form
3. SISP handles 3DS authentication (Visa OTP, etc.)
4. SISP sends callback POST to your server with results
5. Your server validates the response fingerprint and completes/fails the order

---

## 2. Payment Flow (End-to-End)

```
Step 1: Shopper clicks "Place Order"
         │
Step 2: Your server calls process_payment()
         │  ├── Generate merchantRef (exactly 15 chars, "MM" prefix)
         │  ├── Generate merchantSession (exactly 15 chars, "MS" prefix)
         │  ├── Normalize amount to integer
         │  ├── Compute request fingerprint (SHA-512 + Base64, double-hash)
         │  ├── Build 3DS purchaseRequest JSON, Base64-encode it
         │  └── Store ALL fields to order meta BEFORE any redirect
         │
Step 3: Redirect shopper to intermediate page
         │  └── HTML page with hidden <form> that auto-submits to SISP
         │
Step 4: SISP receives the POST
         │  ├── Validates fingerprint by recomputing with your fields
         │  ├── Presents card entry form (Visa/Mastercard)
         │  └── Triggers 3DS OTP if required (Visa)
         │
Step 5: Shopper enters card + OTP
         │
Step 6: SISP processes payment
         │  └── Sends callback POST to your urlMerchantResponse endpoint
         │
Step 7: Your callback handler
         │  ├── Find order by merchantRef
         │  ├── Validate response fingerprint
         │  ├── Validate amount matches
         │  ├── Check idempotency (not already processed)
         │  └── Complete or fail the order
         │
Step 8: SISP redirects shopper back
         └── To thank-you page (success) or checkout (failure)
```

---

## 3. SISP Credentials & Configuration

You need these from SISP:

| Credential    | Description                          | Example                                    |
|---------------|--------------------------------------|--------------------------------------------|
| `posID`       | Your POS identifier                  | `"900"`                                    |
| `posAuthCode` | Authentication code (keep secret!)   | `"A1B2C3D4-E5F6-7890"`                     |
| `vbv2_url`    | SISP 3DS payment page URL            | Sandbox: `https://3dsteste.vinti4net.cv/3ds_middleware_php/public/3ds_init.php` |

**Critical:** The `posAuthCode` may contain special characters (`%`, `+`, `/`, `=`). Your settings form must preserve these exactly — do NOT use `sanitize_text_field()` which strips them. Use `wp_unslash()` or equivalent.

---

## 4. Fingerprint Algorithm — The Heart of the Integration

The fingerprint is a **double SHA-512 + Base64** hash. Get this wrong and every payment is rejected with "Fingerprint Invalid."

### Algorithm (Prehashed Mode — Default)

```
fingerprint = SHA512_Base64( SHA512_Base64(posAuthCode) + field2 + field3 + ... + fieldN )
```

### The Core Primitive

```php
function sha512_base64(string $value): string {
    return base64_encode(hash('sha512', $value, true));
}
```

**Cross-language equivalents:**

| Language | Implementation |
|----------|---------------|
| **PHP** | `base64_encode(hash('sha512', $value, true))` |
| **Node.js** | `Buffer.from(crypto.createHash('sha512').update(value).digest()).toString('base64')` |
| **Python** | `base64.b64encode(hashlib.sha512(value.encode()).digest()).decode()` |
| **Java** | `Base64.getEncoder().encodeToString(MessageDigest.getInstance("SHA-512").digest(value.getBytes(StandardCharsets.UTF_8)))` |
| **C#** | `Convert.ToBase64String(SHA512.Create().ComputeHash(Encoding.UTF8.GetBytes(value)))` |

**Important:** The `true` parameter in PHP's `hash()` returns raw binary bytes. If you forget this and get the hex string instead, the fingerprint will be wrong.

---

## 5. Request Fingerprint (Outgoing)

### Field Order (MANDATORY — SISP enforces exact order)

The base string is built by concatenating these fields **in this exact order, with NO separators:**

| Position | Field | Source | Treatment |
|----------|-------|--------|-----------|
| 1 | Auth segment | `posAuthCode` | `SHA512_Base64(posAuthCode)` (prehashed mode) |
| 2 | Timestamp | Generated | `trim()` — format: `yyyy-MM-dd HH:mm:ss` |
| 3 | Amount | Order total | `absint(amount) × 1000` — converted to string |
| 4 | merchantRef | Generated | `trim()` — **exactly 15 characters** |
| 5 | merchantSession | Generated | `trim()` — **exactly 15 characters** |
| 6 | posID | Gateway config | `trim()` |
| 7 | Currency | ISO 4217 numeric | `trim()` — e.g., `"132"` for CVE |
| 8 | transactionCode | Fixed `"1"` | `trim()` — `"1"` = Authorization |

Optional fields (only append if non-empty):

| Position | Field | Treatment |
|----------|-------|-----------|
| 9 | entityCode | `absint(ltrim(value, '0') ?: '0')` |
| 10 | referenceNumber | `absint(ltrim(value, '0') ?: '0')` |
| 11 | token | `trim()` |

### Working PHP Code

```php
public static function build_request_fingerprint(
    string $pos_auth_code,
    string $timestamp,
    string $amount,
    string $merchant_ref,
    string $merchant_session,
    string $pos_id,
    string $currency,
    string $transaction_code
): string {
    // Step 1: Hash the posAuthCode first
    $auth_segment = self::sha512_base64($pos_auth_code);

    // Step 2: Concatenate fields in EXACT order with NO separators
    $base = $auth_segment
        . trim($timestamp)
        . (string)(absint($amount) * 1000)    // amount × 1000
        . trim($merchant_ref)
        . trim($merchant_session)
        . trim($pos_id)
        . trim($currency)
        . trim($transaction_code);

    // Step 3: Hash the entire concatenated string
    return self::sha512_base64($base);
}
```

### Worked Example

```
posAuthCode     = "TEST-AUTH-CODE"
timestamp       = "2026-04-18 15:10:08"
amount          = "132"                    (integer, before ×1000)
merchantRef     = "MM260418151008k"        (15 chars)
merchantSession = "MS260418151008m"        (15 chars)
posID           = "900"
currency        = "132"                    (CVE)
transactionCode = "1"

Step 1: auth = SHA512_Base64("TEST-AUTH-CODE")
        = "kR3j8xN...long base64 string...=="

Step 2: base = auth
           + "2026-04-18 15:10:08"
           + "132000"                     (132 × 1000)
           + "MM260418151008k"
           + "MS260418151008m"
           + "900"
           + "132"
           + "1"

Step 3: fingerprint = SHA512_Base64(base)
        = "Bx7mKp...another base64 string...=="
```

---

## 6. Response Fingerprint (Callback Verification)

When SISP sends the callback, you must verify the response by recomputing its fingerprint.

### Field Order (16 fields, ALL required)

| Position | Field | Callback POST key |
|----------|-------|-------------------|
| 1 | Auth segment | `posAuthCode` (hashed with SHA512_Base64) |
| 2 | messageType | `messageType` |
| 3 | clearingPeriod | `merchantRespCP` |
| 4 | transactionID | `merchantRespTid` |
| 5 | merchantRef | `merchantRespMerchantRef` |
| 6 | merchantSession | `merchantRespMerchantSession` |
| 7 | purchaseAmount | `merchantRespPurchaseAmount` (×1000 applied) |
| 8 | messageID | `merchantRespMessageID` |
| 9 | pan | `merchantRespPan` (masked card number) |
| 10 | merchantResponse | `merchantResp` |
| 11 | timestamp | `merchantRespTimeStamp` |
| 12 | referenceNumber | `merchantRespReferenceNumber` |
| 13 | entityCode | `merchantRespEntityCode` |
| 14 | clientReceipt | `merchantRespClientReceipt` |
| 15 | additionalErrorMessage | `merchantRespAdditionalErrorMessage` |
| 16 | reloadCode | `merchantRespReloadCode` |

### Working PHP Code

```php
public static function build_response_fingerprint(
    string $pos_auth_code,
    string $message_type,
    string $clearing_period,
    string $transaction_id,
    string $merchant_ref,
    string $merchant_session,
    string $purchase_amount,
    string $message_id,
    string $pan,
    string $merchant_response,
    string $timestamp,
    string $reference_number,
    string $entity_code,
    string $client_receipt,
    string $additional_error_message,
    string $reload_code
): string {
    $base = self::sha512_base64($pos_auth_code )
        . trim($message_type)
        . trim($clearing_period)
        . trim($transaction_id)
        . trim($merchant_ref)
        . trim($merchant_session)
        . (string)(absint($purchase_amount) * 1000)  // same ×1000 convention
        . trim($message_id)
        . trim($pan)
        . trim($merchant_response)
        . trim($timestamp)
        . trim($reference_number)
        . trim($entity_code)
        . trim($client_receipt)
        . trim($additional_error_message)
        . trim($reload_code);

    return self::sha512_base64($base);
}
```

**Verification:** Compare your computed fingerprint with `resultFingerPrint` from the callback. They must match exactly.

---

## 7. merchantRef & merchantSession — Critical Rules

### THE #1 RULE: Exactly 15 Characters Each

```
merchantRef     = 15 characters   (M M y y m m d d H H M M s s X)
merchantSession = 15 characters   (M S y y m m d d H H M M s s Y)
```

If either field is NOT exactly 15 characters, or if they are identical to each other, SISP will reject with **"Fingerprint Invalid"**.

### Format

```
merchantRef:     "MM" + yymmddHHMMSS + 1 alphanumeric suffix = 15 chars
merchantSession: "MS" + yymmddHHMMSS + 1 alphanumeric suffix = 15 chars
```

The `MM` and `MS` prefixes ensure they are always different from each other.

### Working PHP Code

```php
function build_merchant_ref(): string {
    $stamp = gmdate('ymdHis');           // 12 digits: 260418151008
    $suffix = strtolower(substr(bin2hex(random_bytes(4)), 0, 1)); // 1 char
    return 'MM' . $stamp . $suffix;      // "MM260418151008k" = 15 chars
}

function build_merchant_session(): string {
    $stamp = gmdate('ymdHis');           // 12 digits: 260418151008
    $suffix = strtolower(substr(bin2hex(random_bytes(4)), -1));  // different position!
    return 'MS' . $stamp . $suffix;      // "MS260418151008m" = 15 chars
}
```

### What Went Wrong in Production

The v1.1 refactoring changed merchantRef to `WC347-202604181353032sumehrc` (28 chars). SISP truncated it to 15 chars before recomputing its fingerprint, causing a permanent mismatch. The fix was restoring the 15-char format.

---

## 8. Amount Handling

### Two-step process:

1. **Normalize** the order total to an integer: `absint(round($order_total))`
2. **Multiply by 1000** inside the fingerprint builder

```
Order total: 132.49 CVE  →  normalize → 132  →  ×1000 → "132000" in fingerprint
Order total: 1500.00 CVE →  normalize → 1500 →  ×1000 → "1500000" in fingerprint
```

**Why `×1000`?** The SISP protocol requires amounts in the smallest currency unit ×1000. For CVE (Cape Verdean Escudo), which has no decimal subdivisions, this means the integer amount ×1000.

**Important for CVE:** The rounding to integer is correct because CVE has no cents. For EUR/USD/GBP (2 decimal places), you would need `round($amount * 100)` before the ×1000 step — but confirm with SISP documentation for your currency.

### PHP Code

```php
function normalize_amount(float $amount): int {
    return absint(round($amount));
}
```

### The amount sent in the POST form

The `amount` hidden field in the redirect form should be the **normalized integer** (before ×1000). SISP applies the ×1000 internally when computing its fingerprint.

```html
<input type="hidden" name="amount" value="132">  <!-- NOT 132000 -->
```

---

## 9. Timestamp Format

```
yyyy-MM-dd HH:mm:ss    (UTC)
```

Example: `2026-04-18 15:10:08`

```php
$timestamp = gmdate('Y-m-d H:i:s');
```

**Rules:**
- Always use UTC, never local time
- The timestamp in the fingerprint and the timestamp in the POST form must be identical
- SISP validates timestamp freshness (typically within a few minutes)

---

## 10. 3DS purchaseRequest JSON

The `purchaseRequest` field is a Base64-encoded JSON object containing 3DS data. **It is NOT part of the fingerprint computation.**

### Structure

```json
{
  "acctID": "123",
  "email": "customer@example.com",
  "addrMatch": "Y",
  "billAddrCity": "Praia",
  "billAddrCountry": "CV",
  "billAddrLine1": "123 Main St",
  "billAddrLine2": "",
  "billAddrLine3": "",
  "billAddrPostCode": "1000",
  "billAddrState": "",
  "shipAddrCity": "Praia",
  "shipAddrCountry": "CV",
  "shipAddrLine1": "123 Main St",
  "shipAddrPostCode": "1000",
  "shipAddrState": "",
  "workPhone": { "cc": "238", "subscriber": "123456789" },
  "mobilePhone": { "cc": "238", "subscriber": "123456789" },
  "acctInfo": { "chAccAgeInd": "05", "chAccDate": "" }
}
```

### Phone shaping

```php
function shape_phone(string $phone): array {
    $digits = preg_replace('/\D/', '', $phone);
    if (strlen($digits) >= 9) {
        return [
            'cc' => ltrim(substr($digits, 0, -9), '0'),
            'subscriber' => substr($digits, -9),
        ];
    }
    return ['cc' => '', 'subscriber' => $digits];
}
```

### Base64 encoding

```php
$purchase_request_b64 = base64_encode(
    json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);
```

**Important:** The deprecated `purchaseDate` field is NOT included. SISP removed it from the spec.

### `addrMatch` logic

```
"Y" if billing address === shipping address (street, city, postcode, country)
"N" otherwise
```

---

## 11. Redirect Form — POSTing to SISP

The redirect is an HTML page with a hidden form that auto-submits to SISP.

### Form action URL

The SISP URL receives `FingerPrint`, `TimeStamp`, and `FingerPrintVersion` as **query parameters**:

```
https://3dsteste.vinti4net.cv/3ds_middleware_php/public/3ds_init.php?FingerPrint=...&TimeStamp=...&FingerPrintVersion=1
```

### Hidden form fields (POST data)

```html
<form id="payment-form" method="post"
      action="{SISP_URL}?FingerPrint={encoded}&TimeStamp={encoded}&FingerPrintVersion=1">

  <!-- From gateway settings -->
  <input type="hidden" name="posID" value="900">
  <input type="hidden" name="posAuthCode" value="YOUR-AUTH-CODE">

  <!-- From order meta (generated during process_payment) -->
  <input type="hidden" name="merchantRef" value="MM260418151008k">
  <input type="hidden" name="merchantSession" value="MS260418151008m">
  <input type="hidden" name="amount" value="132">
  <input type="hidden" name="currency" value="132">
  <input type="hidden" name="transactionCode" value="1">
  <input type="hidden" name="fingerprint" value="Bx7mKp...==">
  <input type="hidden" name="timestamp" value="2026-04-18 15:10:08">
  <input type="hidden" name="purchaseRequest" value="eyJhY2N0...base64...">
  <input type="hidden" name="languageMessages" value="pt">
  <input type="hidden" name="urlMerchantResponse" value="https://yoursite.com/wc-api/vinti4/">
  <input type="hidden" name="is3DSec" value="1">
  <input type="hidden" name="lang" value="pt">
  <input type="hidden" name="appCode" value="VINTI4WOO">
  <input type="hidden" name="appName" value="Vinti4 WooCommerce">
</form>

<script>document.getElementById('payment-form').submit();</script>
```

---

## 12. URL Encoding — The `+` Character Trap

Base64-encoded fingerprints contain `+` characters. If you use standard URL encoding (`urlencode`), `+` becomes a space on the other end. SISP will compute a different fingerprint and reject.

### The Fix: Use RFC 3986 Encoding

```php
// WRONG — urlencode() turns + into space on decode
$sisp_url = $base_url . '?FingerPrint=' . urlencode($fingerprint);

// CORRECT — rawurlencode() preserves + as %2B
$sisp_url = $base_url . '?FingerPrint=' . rawurlencode($fingerprint)
         . '&TimeStamp=' . rawurlencode($timestamp)
         . '&FingerPrintVersion=' . rawurlencode($fingerprint_version);
```

**Cross-language:**
- **Node.js:** `encodeURIComponent(fingerprint)`
- **Python:** `urllib.parse.quote(fingerprint, safe='')`
- **Java:** `URLEncoder.encode(fingerprint, "UTF-8")` (but Java's URLEncoder uses `+` for spaces — manually replace `%20` if needed)

---

## 13. Callback Handling

SISP sends a POST to your `urlMerchantResponse` endpoint after payment processing.

### Callback POST fields

| Field | Key in POST |
|-------|-------------|
| Message type | `messageType` |
| Result fingerprint | `resultFingerPrint` |
| Merchant reference | `merchantRespMerchantRef` |
| Merchant session | `merchantRespMerchantSession` |
| Purchase amount | `merchantRespPurchaseAmount` |
| Clearing period | `merchantRespCP` |
| Transaction ID | `merchantRespTid` |
| Message ID | `merchantRespMessageID` |
| PAN (masked card) | `merchantRespPan` |
| Merchant response | `merchantResp` |
| Timestamp | `merchantRespTimeStamp` |
| Reference number | `merchantRespReferenceNumber` |
| Entity code | `merchantRespEntityCode` |
| Client receipt | `merchantRespClientReceipt` |
| Error detail | `merchantRespErrorDetail` |
| Error description | `merchantRespErrorDescription` |
| Additional error | `merchantRespAdditionalErrorMessage` |
| Reload code | `merchantRespReloadCode` |

### Validation Chain (in order)

```
1. Extract merchantRef from callback POST
2. Find order by merchantRef
   ├── Try parsing order ID from ref pattern (if embedded)
   └── Fallback: search database by stored merchantRef meta
3. Load order
4. Resolve the specific payment attempt from attempt history
5. Check idempotency (has this attempt already been processed?)
6. Validate merchantSession matches the attempt's stored value
7. Determine success vs failure from messageType
8. IF SUCCESS:
   a. Recompute response fingerprint
   b. Compare with resultFingerPrint from callback
   c. Validate amount matches attempt amount
   d. Mark attempt completed
   e. Handle partial/full payment logic
   f. Redirect to thank-you page
9. IF FAILURE:
   a. Mark attempt failed with error details
   b. Update order status
   c. Redirect to checkout
```

### Order Lookup by merchantRef

Since the `MM...` format doesn't embed the order ID, you need a database lookup:

```php
// Find order by stored merchantRef meta
function find_order_by_merchant_ref(string $merchant_ref): int {
    $orders = wc_get_orders([
        'limit'  => 1,
        'return' => 'ids',
        'meta_query' => [
            [
                'key'   => '_vinti4_merchant_ref',
                'value' => $merchant_ref,
            ],
        ],
    ]);

    return !empty($orders[0]) ? (int)$orders[0] : 0;
}
```

**Critical for HPOS (High-Performance Order Storage):** Use `meta_query` array format, NOT the shorthand `meta_key`/`meta_value` parameters. The shorthand is unreliable with WooCommerce HPOS.

---

## 14. Idempotency

SISP may send the callback multiple times. Your handler must be idempotent.

### Per-attempt idempotency (v1.1+)

```php
$processed_key = "_vinti4_attempt_{$attempt_id}_processed";
if ($order->get_meta($processed_key)) {
    // Already handled — redirect without mutating order
    wp_safe_redirect($thank_you_url);
    exit;
}
```

### On success processing:

```php
// Mark as processed BEFORE redirecting
$order->update_meta_data($processed_key, gmdate('Y-m-d H:i:s'));
$order->save();
```

---

## 15. Partial Payments / Deposits

The integration supports multiple payment attempts against a single order, enabling partial/deposit payments.

### How it works

1. Admin creates a partial payment attempt for an amount less than the order total
2. Each attempt has its own merchantRef, fingerprint, and SISP redirect
3. When a partial payment succeeds, the order stays in `processing` state
4. The system tracks paid total vs outstanding balance
5. When the outstanding balance reaches zero, the order is completed

### Tracking paid total

```php
function get_paid_total(WC_Order $order): float {
    $attempts = get_attempts($order);
    $paid = 0.0;

    foreach ($attempts as $attempt) {
        $is_completed = isset($attempt['status']) && 'completed' === $attempt['status'];
        $is_callback  = !empty($attempt['callback_received']);

        if ($is_completed || $is_callback) {
            $paid += (float)($attempt['amount'] ?? 0.0);
        }
    }

    return $paid;
}

function get_outstanding_total(WC_Order $order): float {
    return max(0.0, (float)$order->get_total() - get_paid_total($order));
}
```

### Callback decision logic

```php
$outstanding = get_outstanding_total($order);

if ($outstanding <= 0.01) {
    // Fully paid — complete the order
    $order->payment_complete($transaction_id);
    redirect_to_thank_you();
} else {
    // Partial payment — keep order in processing
    $order->update_status('processing');
    $order->add_order_note(sprintf(
        'Partial payment: %.2f. Paid: %.2f. Outstanding: %.2f.',
        $attempt_amount, $paid_total, $outstanding
    ));
    redirect_to_thank_you();
}
```

### Important: Never call `$order->save()` inside `get_paid_total()`

This causes unnecessary database writes on every call. The method should be a pure read operation.

---

## 16. Attempt History (Multi-Payment Tracking)

Each order stores an append-only array of payment attempts in order meta.

### Attempt record structure

```php
[
    'attempt_id'        => 'c59b8e2b-91e7-4e3f-ba37-f43a0a3def73',
    'sequence'          => 1,
    'timestamp'         => '2026-04-18 15:10:08',
    'merchant_ref'      => 'MM260418151008k',
    'merchant_session'  => 'MS260418151008m',
    'transaction_code'  => '1',
    'amount'            => '132',
    'currency'          => '132',
    'status'            => 'completed',        // 'pending' | 'completed' | 'failed'
    'transaction_id'    => 'TXN123456',
    'callback_received' => true,
    'created_at_gmt'    => '2026-04-18 15:10:08',
    'metadata'          => [
        'requested_amount' => '132.00',
        'source'           => 'checkout',      // 'checkout' | 'admin_partial'
    ],
]
```

### Legacy meta projection

For backward compatibility with the redirect form (which reads individual meta keys), the latest attempt is "projected" to legacy keys:

```php
$legacy_map = [
    '_vinti4_merchant_ref'          => 'merchant_ref',
    '_vinti4_merchant_session'      => 'merchant_session',
    '_vinti4_amount'                => 'amount',
    '_vinti4_currency'              => 'currency',
    '_vinti4_fingerprint'           => 'fingerprint',
    '_vinti4_timestamp'             => 'timestamp',
    '_vinti4_transaction_code'      => 'transaction_code',
    '_vinti4_purchase_request_b64'  => 'purchase_request_b64',
    '_vinti4_language_messages'     => 'languageMessages',
    '_vinti4_url_merchant_response' => 'urlMerchantResponse',
    '_vinti4_is_3dsec'              => 'is3DSec',
    '_vinti4_fingerprint_version'   => 'FingerPrintVersion',
];

foreach ($legacy_map as $meta_key => $attempt_key) {
    $order->update_meta_data($meta_key, $attempt[$attempt_key]);
}
```

---

## 17. Currency Codes

Use ISO 4217 **numeric** codes (not alphabetic):

| Currency | Alphabetic | Numeric |
|----------|-----------|---------|
| CVE (Cape Verdean Escudo) | CVE | 132 |
| EUR (Euro) | EUR | 978 |
| USD (US Dollar) | USD | 840 |
| AOA (Angolan Kwanza) | AOA | 973 |
| BRL (Brazilian Real) | BRL | 986 |
| GBP (British Pound) | GBP | 826 |

```php
$currency_map = [
    'CVE' => '132', 'EUR' => '978', 'USD' => '840',
    'AOA' => '973', 'BRL' => '986', 'GBP' => '826',
];
```

---

## 18. Success/Failure Message Types

### Success types

| messageType | Meaning |
|-------------|---------|
| `8` | Authorization (most common success) |
| `10` | Capture |
| `M` | SISP-specific success |
| `P` | SISP-specific success |

### Failure type

| messageType | Meaning |
|-------------|---------|
| `6` | Error/Invalid Data (e.g., fingerprint mismatch) |

```php
function is_success_message_type(string $type): bool {
    return in_array($type, ['8', '10', 'M', 'P'], true);
}
```

---

## 19. Complete Field Reference

### Outbound POST fields (form → SISP)

| Field name | Required | Value |
|-----------|----------|-------|
| `posID` | Yes | Your POS ID |
| `posAuthCode` | Yes | Your POS Auth Code |
| `merchantRef` | Yes | 15-char unique reference |
| `merchantSession` | Yes | 15-char unique session |
| `amount` | Yes | Integer amount (not ×1000) |
| `currency` | Yes | ISO 4217 numeric code |
| `transactionCode` | Yes | `"1"` for Authorization |
| `fingerprint` | Yes | The SHA-512+Base64 fingerprint |
| `timestamp` | Yes | `yyyy-MM-dd HH:mm:ss` UTC |
| `purchaseRequest` | Yes | Base64-encoded 3DS JSON |
| `languageMessages` | Yes | `"pt"` or `"en"` |
| `urlMerchantResponse` | Yes | Your callback URL |
| `is3DSec` | Yes | `"1"` for hosted 3DS |
| `lang` | Yes | `"pt"` or `"en"` |
| `appCode` | No | Application identifier |
| `appName` | No | Application name |

### Query parameters on SISP URL

| Parameter | Value |
|-----------|-------|
| `FingerPrint` | URL-encoded (RFC 3986) fingerprint |
| `TimeStamp` | URL-encoded timestamp |
| `FingerPrintVersion` | `"1"` |

---

## 20. Common Pitfalls & Debugging

### Pitfall 1: merchantRef/merchantSession wrong length

**Symptom:** "Fingerprint Invalid" on every payment  
**Cause:** Fields not exactly 15 chars. SISP truncates them, fingerprint mismatch.  
**Fix:** Use the `MM`/`MS` + 12-digit timestamp + 1-char suffix format.

### Pitfall 2: PHP 8.1+ TypeError on fingerprint call

**Symptom:** Fatal error during checkout  
**Cause:** Passing empty string `''` for a typed `int` parameter  
**Fix:** Omit optional parameters or pass correct types. Never pass `''` for `int`.

```php
// WRONG (PHP 8.1 TypeError)
build_request_fingerprint(..., '', '', '');

// CORRECT (use defaults)
build_request_fingerprint($auth, $time, $amount, $ref, $session, $pos, $cur, $code);
```

### Pitfall 3: `+` in Base64 fingerprint corrupted

**Symptom:** "Fingerprint Invalid" intermittently  
**Cause:** Using `urlencode()` instead of `rawurlencode()` for the query string  
**Fix:** Always use `rawurlencode()` for Base64 values in URLs.

### Pitfall 4: Logger never initialized

**Symptom:** No logs appear even with debug enabled  
**Cause:** `Vinti4_Logger::init()` never called  
**Fix:** Call it in the gateway constructor after loading settings.

### Pitfall 5: HPOS order lookup fails

**Symptom:** "Invalid merchant reference" on callback  
**Cause:** Using `meta_key`/`meta_value` shorthand with WooCommerce HPOS  
**Fix:** Use `meta_query` array format:

```php
// WRONG with HPOS
'meta_key' => '_vinti4_merchant_ref', 'meta_value' => $ref,

// CORRECT with HPOS
'meta_query' => [['key' => '_vinti4_merchant_ref', 'value' => $ref]],
```

### Pitfall 6: Callback order lookup fails after refactoring

**Symptom:** "Invalid merchant reference" — callback can't find order  
**Cause:** The `_vinti4_merchant_ref` meta wasn't stored before the callback arrived  
**Fix:** Ensure `process_payment()` stores all meta AND calls `$order->save()` BEFORE redirecting.

### Pitfall 7: Version constant mismatch

**Symptom:** WordPress shows wrong version, possible cache issues  
**Cause:** Header comment version doesn't match `VINTI4_VERSION` constant  
**Fix:** Keep both in sync.

---

## 21. Cross-Language Implementation Notes

### Node.js / Express

```javascript
const crypto = require('crypto');

function sha512Base64(value) {
    return Buffer.from(
        crypto.createHash('sha512').update(value, 'utf8').digest()
    ).toString('base64');
}

function buildRequestFingerprint(params) {
    const authSegment = sha512Base64(params.posAuthCode);
    const base = authSegment
        + params.timestamp.trim()
        + String(Math.abs(Math.round(Number(params.amount))) * 1000)
        + params.merchantRef.trim()
        + params.merchantSession.trim()
        + params.posId.trim()
        + params.currency.trim()
        + params.transactionCode.trim();
    return sha512Base64(base);
}
```

### Python / Django

```python
import hashlib
import base64

def sha512_base64(value: str) -> str:
    digest = hashlib.sha512(value.encode('utf-8')).digest()
    return base64.b64encode(digest).decode('ascii')

def build_request_fingerprint(pos_auth_code, timestamp, amount,
                               merchant_ref, merchant_session,
                               pos_id, currency, transaction_code):
    auth_segment = sha512_base64(pos_auth_code)
    base = (
        auth_segment
        + timestamp.strip()
        + str(abs(int(round(float(amount)))) * 1000)
        + merchant_ref.strip()
        + merchant_session.strip()
        + pos_id.strip()
        + currency.strip()
        + transaction_code.strip()
    )
    return sha512_base64(base)
```

### Java / Spring

```java
import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;
import java.util.Base64;

public class SispFingerprint {
    public static String sha512Base64(String value) {
        try {
            byte[] hash = MessageDigest.getInstance("SHA-512")
                .digest(value.getBytes(StandardCharsets.UTF_8));
            return Base64.getEncoder().encodeToString(hash);
        } catch (Exception e) {
            throw new RuntimeException(e);
        }
    }

    public static String buildRequestFingerprint(
        String posAuthCode, String timestamp, String amount,
        String merchantRef, String merchantSession,
        String posId, String currency, String transactionCode
    ) {
        String authSegment = sha512Base64(posAuthCode);
        String base = authSegment
            + timestamp.trim()
            + String.valueOf(Math.abs(Integer.parseInt(amount.trim())) * 1000)
            + merchantRef.trim()
            + merchantSession.trim()
            + posId.trim()
            + currency.trim()
            + transactionCode.trim();
        return sha512Base64(base);
    }
}
```

---

## 22. Testing Checklist

### Sandbox Setup

Use the SISP sandbox URL:
```
https://3dsteste.vinti4net.cv/3ds_middleware_php/public/3ds_init.php
```

### Test Cases

- [ ] **Full payment:** Order total CVE 132 → payment succeeds → order completed
- [ ] **OTP flow:** Visa card → OTP received → OTP entered → payment succeeds
- [ ] **Fingerprint validation:** Confirm fingerprint computed matches SISP expectation
- [ ] **Callback received:** `messageType=8` → order marked complete
- [ ] **Callback duplicate:** Send same callback twice → order not double-completed
- [ ] **Callback failure:** `messageType=6` → order marked failed
- [ ] **Partial payment:** Pay CVE 50 of CVE 132 order → order stays processing
- [ ] **Full partial sequence:** Pay CVE 50 then CVE 82 → order completes
- [ ] **Amount mismatch:** Callback with wrong amount → rejected
- [ ] **Session mismatch:** Callback with wrong merchantSession → rejected
- [ ] **Invalid fingerprint:** Tampered resultFingerPrint → rejected
- [ ] **merchantRef length:** Confirm exactly 15 characters generated
- [ ] **merchantSession length:** Confirm exactly 15 characters, different from merchantRef
- [ ] **URL encoding:** Fingerprint with `+` characters correctly encoded as `%2B`
- [ ] **Special chars in posAuthCode:** Auth code with `%+/=` preserved exactly

---

## Appendix: File Structure (Reference Implementation)

```
vinti4-woocommerce-plugin/
├── vinti4.php                                  # Plugin bootstrap
├── uninstall.php                               # Clean uninstall
├── includes/
│   ├── class-wc-gateway-vinti4.php             # WooCommerce gateway class
│   ├── class-vinti4-fingerprint.php            # SHA-512 + Base64 fingerprint builder
│   ├── class-vinti4-request-builder.php        # Payment attempt assembler
│   ├── class-vinti4-redirect-form.php          # Auto-submit HTML form renderer
│   ├── class-vinti4-callback-handler.php       # SISP callback validation
│   ├── class-vinti4-attempt-factory.php        # Canonical attempt creation
│   ├── class-vinti4-attempt-store.php          # Append-only attempt persistence
│   ├── class-vinti4-logger.php                 # Debug logging utility
│   ├── class-vinti4-admin-partial-payment.php  # Admin meta box for partial payments
│   ├── class-vinti4-admin-test-panel.php       # Diagnostic self-tests
│   ├── class-vinti4-admin-notices.php          # Missing WooCommerce notice
│   ├── class-vinti4-feature-compatibility.php  # HPOS + blocks compat
│   ├── class-wc-vinti4-blocks-support.php      # Checkout Block integration
│   └── functions-vinti4-formatting.php         # Amount, timestamp, phone helpers
├── assets/
│   └── js/
│       └── blocks.js                           # Checkout Block JS registration
└── docs/
    └── SISP-INTEGRATION-GUIDE.md               # This file
```

---

*This guide is derived from the production-tested Vinti4 for WooCommerce plugin v1.1. All code snippets have been validated against live SISP sandbox and production environments.*
