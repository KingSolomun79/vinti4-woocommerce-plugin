# Vinti4 for WooCommerce

Accept payments via Vinti4 / SISP hosted payment page on your WooCommerce store.

**Version:** 1.0.0  
**Requires:** WordPress 6.0+, WooCommerce 8.0+, PHP 8.1+  
**Tested up to:** WordPress 6.8, WooCommerce 9.7  
**License:** GPLv3

---

## Table of Contents

1. [Installation](#1-installation)
2. [Configuration](#2-configuration)
3. [Running PHPUnit Tests](#3-running-phpunit-tests)
4. [Running Admin Diagnostic Tests](#4-running-admin-diagnostic-tests)
5. [Manual End-to-End Testing](#5-manual-end-to-end-testing)
6. [SISP Sandbox Testing](#6-sisp-sandbox-testing)
7. [Debug Logging](#7-debug-logging)
8. [SISP Certification Checklist](#8-sisp-certification-checklist)
9. [Plugin Architecture](#9-plugin-architecture)
10. [Troubleshooting](#10-troubleshooting)

---

## 1. Installation

### Prerequisites

- WordPress 6.0 or newer
- WooCommerce 8.0 or newer
- PHP 8.1 or newer

### Install the Plugin

**Option A: Upload via WordPress Admin (recommended)**

1. Download or zip the plugin folder as `vinti4-woocommerce-plugin.zip`
2. Go to **WordPress Admin → Plugins → Add New → Upload Plugin**
3. Choose the `.zip` file and click **Install Now**
4. Click **Activate**

**Option B: Manual installation**

1. Copy (or symlink) the `vinti4-woocommerce-plugin` folder into `wp-content/plugins/`
2. Go to **WordPress Admin → Plugins** and click **Activate** on "Vinti4 for WooCommerce"

### Post-Installation (IMPORTANT)

After activating the plugin, **flush WordPress rewrite rules** so the payment redirect endpoint works:

1. Go to **WordPress Admin → Settings → Permalinks**
2. Click **Save Changes** (you don't need to change anything, just save)

This registers the `/vinti4-payment/` endpoint that handles the redirect to SISP.

### Verify Installation

1. Go to **WooCommerce → Settings → Payments**
2. You should see **"Vinti4"** in the list of payment methods
3. If WooCommerce is not active, the plugin shows an admin notice and stays dormant

---

## 2. Configuration

Go to **WooCommerce → Settings → Payments → Vinti4 → Manage**.

### Settings Fields

| Field | Description | Default |
|-------|-------------|---------|
| **Enable Vinti4** | Checkbox to enable/disable the gateway | Off |
| **Title** | Shown to customers at checkout | "Pay with Vinti4" |
| **Description** | Shown to customers at checkout | "You will be redirected to Vinti4 to complete payment." |
| **POS ID** | Your POS identifier from SISP | (empty) |
| **POS Auth Code** | Authentication code from SISP. Special characters (`%`, `+`, `/`, `=`) are preserved exactly as entered | (empty) |
| **SISP Payment URL** | URL of the SISP 3DS payment page. Use the test URL for sandbox mode | `https://3dsteste.vinti4net.cv/...` (sandbox) |
| **Language** | Language for the SISP payment page | Portuguese |
| **Debug Mode** | Enable logging for debugging. **Do not enable in production.** | Off |
| **Default Currency** | Currency used when auto-detection from the order is not possible | CVE — Cape Verdean Escudo |

### Currency Auto-Detection

The plugin auto-detects the currency from the WooCommerce order. If the order currency is not in the supported map, it falls back to the **Default Currency** setting, then ultimately to CVE (code 132).

Supported currencies:

| Currency | ISO Code | SISP Numeric |
|----------|----------|--------------|
| Cape Verdean Escudo | CVE | 132 |
| Euro | EUR | 978 |
| US Dollar | USD | 840 |
| Angolan Kwanza | AOA | 973 |
| Brazilian Real | BRL | 986 |
| British Pound | GBP | 826 |

### Sandbox / Test Mode

By default, the **SISP Payment URL** is set to the SISP test (sandbox) environment. To switch to production:

1. Replace the URL with the production SISP 3DS URL provided by your bank/SISP
2. Update **POS ID** and **POS Auth Code** with your production credentials

---

## 3. Running PHPUnit Tests

The plugin includes 27 PHPUnit tests (39 assertions) that verify the core cryptographic and callback logic **without requiring a WordPress installation**.

### Prerequisites for Testing

- **PHP 8.1+** installed and available on your PATH
- **Composer** installed and available on your PATH

### Step-by-Step

#### Step 1: Install PHP (if not already installed)

**Windows:**
1. Download PHP from https://windows.php.net/download/
2. Extract to `C:\tools\php` (or any directory)
3. Rename `php.ini-development` to `php.ini`
4. Enable required extensions in `php.ini` by uncommenting:
   ```
   extension=openssl
   extension=mbstring
   extension=curl
   ```
5. Add `C:\tools\php` to your system PATH

**Verify:**
```bash
php -v
# Should show PHP 8.1+ or newer
```

#### Step 2: Install Composer (if not already installed)

Download and install from https://getcomposer.org/download/

**Verify:**
```bash
composer --version
```

#### Step 3: Install Test Dependencies

From the plugin root directory:
```bash
composer install
```

This installs PHPUnit 10 into `vendor/`. It creates a `composer.lock` file and `vendor/` directory. These are already in `.gitignore`.

#### Step 4: Run the Tests

```bash
composer test
# or equivalently:
vendor/bin/phpunit
```

#### Expected Output

```
PHPUnit 10.x.x

.....................................                         27 / 27 (100%)

Time: 00:00.123, Memory: 6.00 MB

OK (27 tests, 39 assertions)
```

### What the Tests Cover

| Test File | Tests | What It Verifies |
|-----------|-------|------------------|
| `tests/Test_Fingerprint.php` | 6 | SHA-512+Base64 hash computation, request fingerprint with exact SISP field ordering, optional field handling, amount ×1000 |
| `tests/Test_Formatting_Helpers.php` | 12 | Amount normalization, timestamp format, merchantRef generation/parsing, merchantSession uniqueness, phone shaping, success message type detection |
| `tests/Test_Logger_Mask.php` | 5 | Auth code masking (long codes, short codes, empty string) |
| `tests/Test_Callback_Handler.php` | 4 | Success callback completes order once, duplicate callback is rejected, fingerprint mismatch marks order failed, amount mismatch marks order failed |

### If Tests Fail

- **"Class not found" errors:** Run `composer install` again
- **PHP version errors:** Ensure PHP 8.1+ is installed (`php -v`)
- **Specific test failure:** The test output shows which test failed and why — this usually indicates a code change broke a SISP protocol requirement

---

## 4. Running Admin Diagnostic Tests

The plugin includes a built-in diagnostic panel accessible from WordPress admin. These tests run inside a live WordPress + WooCommerce environment and verify configuration, registration, and code structure.

### How to Run

1. Log into WordPress Admin
2. Go to **WooCommerce → Vinti4 Tests**
3. Click the **"Run Tests"** button
4. Results appear in a table showing each test name, status (PASS/FAIL), and detail

### What the 12 Tests Check

| # | Test Name | What It Verifies | Prerequisites |
|---|-----------|------------------|---------------|
| 1 | **Config Present** | `pos_id`, `pos_auth_code`, and `vbv2_url` are all non-empty | Configure settings first |
| 2 | **Logger Available** | `Vinti4_Logger` class is loaded with `log()` method | Plugin active |
| 3 | **Fingerprint Hash** | `sha512_base64()` produces a valid 64-byte SHA-512 hash | Plugin active |
| 4 | **Currency Map (CVE → 132)** | CVE currency correctly maps to numeric code 132 | Plugin active |
| 5 | **Success Types** | Message types 8, 10, M, P return true; 0, 1 return false | Plugin active |
| 6 | **Logger Mask** | `ABCDEFGHYZ` → `ABC*****YZ` (auth code is masked) | Plugin active |
| 7 | **Logger Mask (Empty)** | Empty string returns empty string (edge case) | Plugin active |
| 8 | **Callback Endpoint** | Rewrite rule `^vinti4-payment/?$` is registered | Flush permalinks |
| 9 | **Gateway Registered** | `WC_Gateway_Vinti4` is in WooCommerce's gateway list | Plugin active |
| 10 | **Blocks Support** | `WC_Vinti4_Blocks_Support` extends `AbstractPaymentMethodType` | Plugin active |
| 11 | **Callback: Duplicate Detection** | Callback handler implements `_vinti4_callback_processed` idempotency check | Plugin active |
| 12 | **Callback: Invalid Fingerprint** | Callback handler validates response fingerprint with `build_response_fingerprint()` | Plugin active |

### Expected Results

All 12 tests should show **✓ PASS**. If any fail:

| Failed Test | Fix |
|-------------|-----|
| Config Present | Go to **WooCommerce → Settings → Payments → Vinti4** and fill in POS ID, POS Auth Code, and SISP Payment URL |
| Callback Endpoint | Go to **Settings → Permalinks** and click **Save Changes** |
| Gateway Registered | Ensure WooCommerce is active and the plugin is activated |
| Blocks Support | Ensure WooCommerce 8.0+ is installed (Blocks API required) |

---

## 5. Manual End-to-End Testing

These are the tests you perform yourself in the browser to verify the complete payment flow works.

### Test 1: Plugin Activation Safety

**Purpose:** Plugin activates without errors and shows admin notice when WooCommerce is missing.

1. **With WooCommerce active:** Go to **Plugins**, activate "Vinti4 for WooCommerce" → no errors
2. **With WooCommerce inactive:** Deactivate WooCommerce → you should see an admin notice: *"Vinti4 requires WooCommerce to be installed and active."*
3. **Re-activate WooCommerce** → notice disappears, gateway appears in **WooCommerce → Settings → Payments**

### Test 2: Gateway Settings

**Purpose:** All settings render correctly and save properly.

1. Go to **WooCommerce → Settings → Payments → Vinti4 → Manage**
2. Verify all fields are present: Enable, Title, Description, POS ID, POS Auth Code, SISP Payment URL, Language, Debug, Default Currency
3. Fill in POS ID, POS Auth Code, and leave SISP Payment URL as sandbox default
4. Click **Save changes**
5. Verify settings persist after page reload

### Test 3: POS Auth Code Special Characters

**Purpose:** Special characters in the auth code are preserved (not stripped).

1. In **POS Auth Code**, enter a value like `Abc%def+ghi/jkl=mno`
2. Click **Save changes**
3. Reload the page
4. Verify the **POS Auth Code** field still shows `Abc%def+ghi/jkl=mno` (characters not mangled)

### Test 4: Gateway Appears at Classic Checkout

**Purpose:** Vinti4 appears as a payment option in the classic (shortcode) checkout.

1. Add a product to the cart
2. Go to the classic checkout page (not the block-based checkout)
3. Under **Payment methods**, you should see **"Pay with Vinti4"** (or whatever title you configured)
4. Select Vinti4

### Test 5: Gateway Appears at Checkout Block

**Purpose:** Vinti4 appears as a payment option in the WooCommerce Cart/Checkout Blocks.

1. Ensure you have a page using the WooCommerce **Checkout Block** (not the shortcode `[woocommerce_checkout]`)
2. Add a product to the cart
3. Go to the Checkout Block page
4. Under payment methods, you should see **"Pay with Vinti4"**
5. The title and description should match what you configured in settings

### Test 6: Checkout Without Configuration Shows Error

**Purpose:** Missing configuration produces a user-facing error instead of a crash.

1. Clear POS ID, POS Auth Code, or SISP Payment URL in settings (save empty)
2. Add a product to the cart, go to checkout, select Vinti4
3. Click **Place Order**
4. You should see an error: *"Payment configuration is incomplete. Please contact support."*
5. The order should NOT be created (or should be in failed status)

### Test 7: Redirect to SISP Payment Page

**Purpose:** Clicking Place Order redirects to the SISP payment page.

1. Configure valid sandbox credentials:
   - **POS ID:** `90000414`
   - **POS Auth Code:** `2XSPcf5fmjXiZ7hA`
   - **SISP Payment URL:** `https://3dsteste.vinti4net.cv/3ds_middleware_php/public/3ds_init.php`
2. Add a product, go to checkout, select Vinti4, click **Place Order**
3. You should see a "Redirecting to the payment page..." spinner
4. You should be auto-redirected to the SISP 3DS test payment page
5. Verify the SISP page shows the correct amount and merchant info

### Test 8: Successful Payment (SISP Sandbox)

**Purpose:** Complete payment via SISP sandbox returns to a correctly completed order.

1. Follow Test 7 to reach the SISP test payment page
2. Enter test card details:
   - **Card Number:** `4012001037141112`
   - **Expiry:** `12/25`
   - **CVV:** `123`
   - **OTP:** `123456`
3. Submit the payment
4. You should be redirected back to the WooCommerce **Order Received** page
5. In **WordPress Admin → WooCommerce → Orders**, find the order:
   - Status should be **Processing** (or Completed depending on your settings)
   - Order notes should show: *"Vinti4 payment authorized. TID: ..."*
   - No duplicate order notes (callback was processed once)

### Test 9: Failed Payment

**Purpose:** A failed payment at SISP returns the shopper to checkout and marks the order failed.

1. Follow Test 7 to reach the SISP test payment page
2. Enter incorrect card details or cancel the payment
3. You should be redirected back to the **checkout page**
4. In **WordPress Admin → WooCommerce → Orders**, find the order:
   - Status should be **Failed**
   - Order notes should indicate the failure reason

### Test 10: Duplicate Callback Protection

**Purpose:** Sending the same callback twice does not double-complete the order.

This is difficult to test manually (requires re-sending the exact SISP POST). To verify indirectly:

1. Complete a successful payment (Test 8)
2. Note the order ID
3. Check the order in admin — verify the `_vinti4_callback_processed` meta is set to `1`
4. The order has exactly ONE "payment authorized" note (not two)

### Test 11: Order Meta Storage

**Purpose:** All payment attempt fields are stored on the order before redirect.

1. Place an order with Vinti4 selected
2. In admin, edit the order
3. Look at **Custom Fields** (or use the order meta panel):
   - `_vinti4_attempt_id` — UUID
   - `_vinti4_timestamp` — formatted timestamp
   - `_vinti4_merchant_ref` — e.g. `WC42-20260416143022`
   - `_vinti4_merchant_session` — starts with `S`
   - `_vinti4_transaction_code` — `1`
   - `_vinti4_amount` — integer amount
   - `_vinti4_currency` — numeric code (e.g. `132`)
   - `_vinti4_purchase_request_b64` — Base64 JSON
   - `_vinti4_fingerprint` — Base64 SHA-512 hash

### Test 12: Deactivation and Uninstall Safety

**Purpose:** Deactivating does not delete data. Uninstalling only removes settings.

1. **Deactivate** the plugin → verify no orders, pages, or posts are deleted
2. **Re-activate** → verify all settings and orders are intact
3. To test uninstall (destructive): delete the plugin → only the `woocommerce_vinti4_settings` option is removed; orders and order meta remain

---

## 6. SISP Sandbox Testing

### Sandbox Credentials

| Setting | Value |
|---------|-------|
| POS ID | `90000414` |
| POS Auth Code | `2XSPcf5fmjXiZ7hA` |
| Merchant ID | `9000406` |
| 3DS Test URL | `https://3dsteste.vinti4net.cv/3ds_middleware_php/public/3ds_init.php` |

### Test Card

| Field | Value |
|-------|-------|
| PAN (Card Number) | `4012001037141112` |
| Expiry Date | `12/25` |
| CVV2 | `123` |
| OTP | `123456` |

### Complete Sandbox Flow

1. Configure the sandbox credentials in **WooCommerce → Settings → Payments → Vinti4**
2. Add any product to the cart
3. Go to checkout (classic or block-based)
4. Select "Pay with Vinti4"
5. Click **Place Order**
6. You are redirected to the SISP 3DS test page
7. Enter the test card details above
8. Enter OTP `123456`
9. Payment completes → redirected to Order Received page
10. Check the order in WooCommerce admin — status should be **Processing**

---

## 7. Debug Logging

### Enable Logging

1. Go to **WooCommerce → Settings → Payments → Vinti4 → Manage**
2. Check **"Enable logging"**
3. Click **Save changes**

### View Logs

1. Go to **WooCommerce → Status → Logs**
2. Select the log file with source **"vinti4"** from the dropdown
3. Click **View**

### What Gets Logged (when debug is enabled)

Every log entry is tagged with context (order ID, merchantRef, messageType) for easy searching.

| Event | Log Level | Example |
|-------|-----------|---------|
| Payment attempt created | `debug` | Attempt ID, order ID, merchantRef, amount, currency, masked auth code, fingerprint |
| Config validation failure | `error` | "gateway configuration incomplete (missing pos_id, pos_auth_code, or vbv2_url)" |
| Callback received | `debug` | "Callback received from SISP." |
| Callback: invalid data | `warning` | "missing merchantRef or resultFingerprint" |
| Callback: invalid reference | `warning` | "could not parse order ID from merchantRef" |
| Callback: merchantRef mismatch | `warning` | Stored vs. received reference |
| Callback: duplicate | `debug` | "Duplicate callback detected for order X" |
| Callback: fingerprint mismatch | `error` | "fingerprint mismatch" |
| Callback: amount mismatch | `error` | Stored vs. response amount |
| Callback: payment completed | `debug` | "Payment completed for order X. TID: Y" |
| Callback: payment failed | `warning` | "Payment failed for order X" |

### Security: Auth Code Masking

The full POS auth code **never** appears in any log entry. It is always masked:

- `2XSPcf5fmjXiZ7hA` → `2XS****hA` (first 3 + asterisks + last 2)
- `Abcd` → `A***` (short codes: first char + asterisks)
- Empty → `` (empty string passed through)

---

## 8. SISP Certification Checklist

For formal SISP certification, use the detailed checklist at:

📄 **`docs/certification-checklist.md`**

This checklist maps all 27 v1 requirements to specific verification methods:

| Verification Method | Count | Description |
|---------------------|-------|-------------|
| PHPUnit Test | 14 | Automated tests in `tests/` directory |
| Admin Panel Test | 12 | Diagnostic tests at **WooCommerce → Vinti4 Tests** |
| Code Review | 27 | Manual source inspection with file and line references |

### Certification Sign-Off

The checklist includes a sign-off table for:
- Developer
- QA / Tester
- SISP Representative

---

## 9. Plugin Architecture

### File Structure

```
vinti4-woocommerce-plugin/
├── vinti4.php                                      # Plugin bootstrap + rewrite endpoint
├── uninstall.php                                   # Safe uninstall (settings only)
├── composer.json                                   # PHPUnit dependency
├── phpunit.xml                                     # PHPUnit configuration
├── assets/
│   └── js/
│       └── blocks.js                               # Checkout Block registration
├── includes/
│   ├── class-wc-gateway-vinti4.php                 # Main gateway class (settings, process_payment, callback)
│   ├── class-vinti4-fingerprint.php                # SHA-512+Base64 fingerprint generation
│   ├── class-vinti4-request-builder.php            # Payment request builder
│   ├── class-vinti4-redirect-form.php              # Auto-posting HTML form to SISP
│   ├── class-vinti4-callback-handler.php           # 9-step callback validation with idempotency
│   ├── class-vinti4-logger.php                     # Structured logging with auth code masking
│   ├── class-vinti4-admin-notices.php              # Missing WooCommerce admin notice
│   ├── class-vinti4-admin-test-panel.php           # 12 diagnostic self-tests
│   ├── class-wc-vinti4-blocks-support.php          # Checkout Block integration
│   └── functions-vinti4-formatting.php             # Formatting helper functions
├── tests/
│   ├── bootstrap.php                               # WP/WC stubs for pure PHP testing
│   ├── Test_Fingerprint.php                        # 6 fingerprint tests
│   ├── Test_Formatting_Helpers.php                 # 12 formatting tests
│   ├── Test_Logger_Mask.php                        # 5 logger mask tests
│   ├── Test_Callback_Handler.php                   # 4 callback handler tests
│   └── fixtures/
│       ├── fingerprint-request.php                 # Pre-computed request fingerprint vectors
│       └── fingerprint-response.php                # Pre-computed response fingerprint vectors
├── docs/
│   ├── prd.md                                      # Product requirements document
│   └── certification-checklist.md                  # SISP certification checklist
└── .gitignore                                      # Excludes vendor/, .phpunit.cache
```

### Payment Flow

```
Customer checkout
       │
       ▼
process_payment()          Validates config, builds payment attempt
       │                   via Request Builder, stores 9 fields as
       │                   order meta, returns redirect URL
       ▼
/vinti4-payment/           WordPress rewrite endpoint →
       │                   Vinti4_Redirect_Form::render()
       │
       ▼
Auto-submit HTML form      Hidden form POSTs to SISP 3DS URL
       │                   with posID, posAuthCode, fingerprint,
       │                   merchantRef, amount, currency, etc.
       ▼
SISP 3DS Payment Page      Customer enters card details, 3DS auth
       │
       ▼
SISP Callback (POST)       SISP redirects back to
       │                   ?wc-api=vinti4 with result fields
       ▼
Callback Handler           9-step validation:
       │                   1. Extract POST data
       │                   2. Parse order ID from merchantRef
       │                   3. Verify merchantRef matches stored meta
       │                   4. Idempotency check (_vinti4_callback_processed)
       │                   5. Determine success/failure from messageType
       │                   6. Validate response fingerprint
       │                   7. Validate amount matches
       │                   8. payment_complete() (success)
       │                   9. update_status('failed') (failure)
       ▼
Order Received page        Customer sees success/failure result
```

### Callback Endpoint

The SISP callback is received at:

```
https://your-store.com/?wc-api=vinti4
```

This is handled via the `woocommerce_api_vinti4` action hook, which delegates to `Vinti4_Callback_Handler::handle()`.

### Idempotency

The `_vinti4_callback_processed` order meta is set **before** every terminal redirect. If SISP sends the same callback again:

1. The handler reads `_vinti4_callback_processed` = `1`
2. The handler immediately redirects without mutating the order
3. The customer is sent to the appropriate page (Order Received if success, Checkout if failure)

---

## 10. Troubleshooting

### "Payment configuration is incomplete" error at checkout

**Cause:** POS ID, POS Auth Code, or SISP Payment URL is empty.

**Fix:** Go to **WooCommerce → Settings → Payments → Vinti4** and fill in all three fields.

### 404 error on `/vinti4-payment/` page

**Cause:** WordPress rewrite rules haven't been flushed.

**Fix:** Go to **Settings → Permalinks** and click **Save Changes**.

### Vinti4 not appearing at checkout

**Cause:** Gateway is not enabled.

**Fix:** Go to **WooCommerce → Settings → Payments** and enable the Vinti4 toggle.

### Vinti4 appears in classic checkout but not in Checkout Block

**Cause:** Your checkout page is using the WooCommerce Checkout Block and the block integration is not loading.

**Fix:** 
1. Ensure WooCommerce 8.0+ is installed
2. Check **WooCommerce → Vinti4 Tests** → the "Blocks Support" test should pass
3. Clear any page/object caches

### Fingerprint mismatch in SISP callback

**Cause:** The POS Auth Code stored in WooCommerce settings doesn't match what SISP expects, or the auth code was corrupted by sanitization.

**Fix:**
1. Verify the POS Auth Code in settings matches what SISP provided — character for character
2. Ensure special characters (`%`, `+`, `/`, `=`) are preserved (the plugin uses `wp_unslash()` to preserve them)
3. Enable **Debug Mode** and check the logs for the masked auth code to verify it's correct

### Duplicate order completions

**Cause:** Should not happen — the plugin has idempotency protection.

**Verify:** Check the order meta for `_vinti4_callback_processed` = `1`. If somehow a duplicate occurred, check the debug logs for "Duplicate callback detected for order X".

### Where are the logs?

**WooCommerce → Status → Logs** → select the file with source "vinti4".

Logs only appear when **Debug Mode** is enabled in the gateway settings.

---

## Support

- **Plugin Issues:** https://github.com/vinti4/vinti4-woocommerce-plugin/issues
- **Vinti4 / SISP:** https://www.vinti4.cv
- **WooCommerce Docs:** https://woocommerce.com/document/payment-gateway-api/

---

## License

This plugin is licensed under the GNU General Public License v3.0 or later. See the [GPLv3 License](https://www.gnu.org/licenses/gpl-3.0.html) for details.
