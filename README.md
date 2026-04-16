# Vinti4 for WooCommerce

Hosted redirect payment gateway for Vinti4 / SISP, rebuilt for current WooCommerce.

## Status

This plugin is the updated v1 remediation of the legacy Vinti4 WooCommerce plugin.

Its purpose is to fix the structural issues that caused the old plugin to fail on modern WordPress and WooCommerce stores, especially:

* critical errors on activation,
* broken or fragile WooCommerce gateway loading,
* callback and redirect instability,
* fingerprint mismatch issues,
* outdated request shaping,
* poor maintainability,
* no support for modern WooCommerce checkout patterns.

---

## Why this plugin exists

The original Vinti4 WooCommerce plugin was based on an old WooCommerce gateway pattern and had several problems that made it unreliable on current sites.

### Main issues in the old plugin

#### 1. Unsafe WooCommerce bootstrap

The old plugin assumed WooCommerce classes were always available and did not safely guard gateway class loading.

This could cause a **critical error on activation** if WooCommerce was not loaded in the expected way.

#### 2. Outdated gateway architecture

The old plugin used legacy patterns such as:

* standalone public PHP files for payment start and callback,
* direct gateway instantiation during plugin load,
* custom pages created on activation,
* direct cleanup logic on deactivation,
* logic copied from an old offline gateway example.

These patterns are brittle and do not match how a current WooCommerce payment gateway should behave.

#### 3. Fingerprint instability

The old flow was too loose around the exact request values used to generate and validate fingerprints.

Typical real-world failures included:

* request fields not being persisted canonically before redirect,
* retries using ambiguous references,
* callback mismatches,
* difficult debugging when a payment attempt was retried,
* request formatting drifting from the exact data used to build the fingerprint.

#### 4. Weak callback handling

The old callback flow was not properly idempotent.

That creates risks such as:

* duplicate callbacks,
* repeated order completion attempts,
* wrong order state transitions,
* harder support and reconciliation.

#### 5. No modern Checkout Block support

The old plugin only targeted older checkout behavior and did not include explicit support for Cart / Checkout Blocks.

#### 6. Unsafe lifecycle behavior

The old plugin relied on activation/deactivation side effects such as creating pages and removing content in ways that should not be part of a payment plugin lifecycle.

#### 7. Poor maintainability

The codebase mixed:

* bootstrap logic,
* payment logic,
* callbacks,
* admin configuration,
* formatting logic,
* redirect output,

without clear separation of responsibility.

---

## What this updated plugin fixes

This updated v1 focuses on **stability, correctness, and modern WooCommerce compatibility**.

### v1 goals

* Activate safely on current WooCommerce.
* Register as a proper WooCommerce payment gateway.
* Work with current WooCommerce checkout patterns.
* Use WooCommerce-native callback handling.
* Reduce fingerprint-related failures by design.
* Persist a canonical payment attempt before redirect.
* Prevent duplicate callback processing.
* Improve observability and supportability.
* Create a clean foundation for future tokenization work.

---

## Issues tackled in v1

### 1. Safe dependency loading

The plugin now loads only when WooCommerce and its payment gateway base classes are available.

This prevents the activation fatal errors caused by the old plugin.

### 2. Proper gateway lifecycle

The plugin is rebuilt around a proper WooCommerce gateway class.

This includes:

* clean registration,
* gateway settings inside WooCommerce,
* payment processing through the WooCommerce gateway lifecycle,
* callback handling owned by the gateway.

### 3. Canonical request building

A payment attempt is built once, stored once, and used consistently.

That means the plugin stores the exact request context before sending the customer to SISP.

This is critical for reducing fingerprint mismatch issues.

### 4. Fingerprint hardening

The plugin centralizes fingerprint generation and validation.

Instead of computing request data in a loose or duplicated way, the updated version:

* builds request fingerprints from one canonical code path,
* validates response fingerprints from one canonical code path,
* persists the values needed to understand what was actually sent,
* reduces retry ambiguity with unique payment attempt identifiers.

### 5. Safer callback processing

The callback flow is idempotent.

The updated plugin prevents repeated completion of the same order when duplicate or delayed callbacks occur.

### 6. Cleaner redirect flow

The payment redirect flow is handled inside the plugin’s gateway architecture rather than relying on brittle standalone files.

### 7. Better WooCommerce compatibility

The updated plugin is designed to align with current WooCommerce payment-extension patterns.

### 8. Better logging and support diagnostics

The plugin adds structured debug logging so support can diagnose:

* request construction issues,
* callback validation failures,
* duplicate callback events,
* amount mismatch,
* merchant reference mismatch,
* configuration errors.

---

## Security model

Security is one of the main reasons this plugin was rebuilt.

### Request integrity

The plugin builds the payment request in a canonical way and generates the outgoing fingerprint from the exact stored values used for the payment attempt.

### Response integrity

The callback validates the response before updating the order state.

An order is not treated as paid unless the callback passes validation.

### Callback idempotency

Duplicate callbacks are handled safely.

The plugin checks whether the order was already processed before applying payment completion logic again.

### Secret handling

Sensitive configuration values such as merchant authentication secrets are stored in WooCommerce gateway settings and are never meant to be exposed in frontend output or full debug logs.

### Reduced operational risk

The updated plugin avoids unsafe behaviors such as:

* direct public callback scripts with brittle relative WordPress loading,
* direct manual cleanup of site content,
* manual order-completion logic without robust guards.

### Logging discipline

Debug logs must be useful without exposing secrets.

The plugin is designed to log identifiers and validation results, but not full sensitive credentials.

---

## Architecture overview

The updated plugin is organized into separate responsibilities.

### Main components

* **Plugin bootstrap**: loads the plugin safely and registers the gateway.
* **Gateway class**: owns settings, payment start, and callback handling.
* **Request builder**: builds the payment attempt and request payload.
* **Fingerprint helper**: generates and validates request/response fingerprints.
* **Logger**: structured debug logging with safe redaction.
* **Block support**: registration for modern WooCommerce Checkout Block support.
* **Template**: hosted redirect form that auto-posts to SISP.

### Payment flow

1. Customer selects Vinti4 at checkout.
2. WooCommerce calls the gateway payment processor.
3. The plugin creates a canonical payment attempt.
4. The payment request values are stored on the order.
5. The customer is redirected to the SISP payment flow.
6. SISP returns the result to the plugin callback endpoint.
7. The plugin validates the callback.
8. The order is completed or failed exactly once.

---

## What this plugin does not try to do in v1

This release is focused on making the WooCommerce integration stable.

It does **not** yet aim to fully implement:

* saved card management in WooCommerce account pages,
* subscription billing,
* advanced tokenization UX,
* admin-side refund tooling,
* broader merchant portal tooling.

Those can be added in later versions once the base gateway is stable.

---

## Installation

1. Make sure WordPress is running normally.
2. Make sure WooCommerce is installed and activated.
3. Upload the plugin folder to `wp-content/plugins/`.
4. Activate the plugin from the WordPress admin plugins page.
5. Go to **WooCommerce → Settings → Payments**.
6. Find **Vinti4** and enable it.
7. Enter the merchant configuration values provided by SISP:

   * POS ID
   * POS Auth Code
   * SISP payment URL
   * language preference
8. Save settings.

---

## Configuration notes

The exact merchant parameters must match the values provided for your environment.

Typical required values include:

* POS ID,
* POS authentication code,
* payment request URL,
* callback routing through the plugin,
* language setting,
* optional debug mode.

Do not use test credentials in production.

---

## Debugging and support

When debug mode is enabled, the plugin should help identify:

* whether the payment attempt was created,
* which merchant reference was used,
* whether the callback arrived,
* whether the callback was accepted or rejected,
* whether the order had already been processed,
* whether the problem is configuration, request integrity, or callback validation.

### Typical problems the updated plugin is meant to reduce

* critical error on plugin activation,
* gateway not showing correctly in checkout,
* payment redirects failing,
* fingerprint mismatch caused by inconsistent request values,
* callback processed twice,
* order not updating correctly after payment,
* hard-to-debug retry failures.

---

## Recommended operating practices

* Keep WooCommerce updated.
* Keep the plugin updated.
* Use separate test and production credentials.
* Enable debug mode only when needed.
* Validate the full payment flow in a test environment before production rollout.
* Keep logs protected because order/payment metadata can still be operationally sensitive.

---

## Migration note from the old plugin

This plugin should be treated as a structural replacement of the legacy Vinti4 WooCommerce plugin, not as a minor patch.

The old plugin had deep architectural issues. The updated version changes the way the integration is loaded and processed so that the gateway becomes reliable on current WooCommerce.

If you are migrating from the old plugin:

* review all payment settings carefully,
* retest the full checkout flow,
* validate callback behavior,
* validate success and failure flows,
* validate order status transitions,
* validate fingerprint-related logging in test mode.

---

## Future roadmap

Potential future releases may include:

* fuller tokenization support,
* saved payment methods UX,
* richer admin diagnostics,
* transaction-status reconciliation tools,
* improved merchant support tooling,
* subscriptions compatibility review.

---

## Summary

The old Vinti4 WooCommerce plugin was failing because it was built on legacy patterns that are not reliable on current WooCommerce.

This updated v1 exists to solve the core integration problems first:

* safe activation,
* proper gateway loading,
* stable redirect flow,
* better fingerprint handling,
* safer callback validation,
* modern WooCommerce compatibility,
* better logging and maintainability.

This is the baseline needed before expanding the plugin into more advanced payment features.
