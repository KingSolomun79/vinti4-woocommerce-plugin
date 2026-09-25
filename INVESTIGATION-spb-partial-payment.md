# São Pedro Bay — Partial Payment Investigation

Branch: `investigate/partial-payment-second-request` (based on `feature/cleaner-sisp-purchase-request`)
Status: root cause #1 confirmed; #2's original hypothesis was checked against live order data and disproven (see below) — no smoking-gun order found, need the client to name the specific order; #3 is a feature request.
This file is git-ignored — local working notes only, delete when the investigation closes.

## Reported by client (Carlos, WhatsApp, today)

> Cheers Jo Manera, we have a situation and a question.
> [situation]
> [screenshot: order card — "...Sea Serviced Apartment", 155,325 CVE, stay 22/08/2026–25/08/2026 roughly, deposit-related figures]
> we asked a 30% deposit from this booking and now we want to charge the rest but the system doesnt let us
> [screenshot: WooCommerce-style order view with a status area circled in pink]
> Also i don't understand why the system is still showing pending if it's paid?
> And finally, would it be possible to integrate a search feature? If not it takes us quite some time to identify the bookings...
> Let me know

Also noted earlier same thread: "Sisp has a 'problema geral' so doublecheck if you receive bookings tonight" — operational heads-up from the client about a general SISP (payment processor) outage, likely unrelated to the bugs below but worth keeping in mind if any orders from that night look anomalous.

Three distinct issues to address:
1. Can't charge the remaining balance after an initial deposit request (CONFIRMED root cause).
2. Order still shows "pending" even though it was paid (HYPOTHESIS below, needs confirmation).
3. Feature request: search/filter on the reception dashboard (none exists today).

---

## System map

Two **separate, disconnected** systems handle partial/deposit payments on this site:

### A. This repo's native system (correct design, NOT what the client is using)
- [`includes/class-vinti4-admin-partial-payment.php`](includes/class-vinti4-admin-partial-payment.php) — WooCommerce order-edit meta box. Staff can create a new payment request (fixed amount or %) any number of times while `outstanding > 0.01`.
- [`includes/class-vinti4-attempt-store.php`](includes/class-vinti4-attempt-store.php) — append-only attempt history in order meta (`_vinti4_attempt_history`). `get_outstanding_total()` = `order_total - paid_total`, computed from attempts whose status is `completed` / `callback_received`. No artificial lock — as many attempts as needed.
- [`includes/class-vinti4-callback-handler.php`](includes/class-vinti4-callback-handler.php) — validates SISP's callback amount against the **specific attempt's** stored amount (`$attempt['amount']`), not the order's full total. On match: `payment_complete()` / `update_status('processing')`. There's also a **legacy path** (line ~647+) that validates against a single order-level meta key `_vinti4_amount` instead of the attempt store, for orders that predate the attempt-history system.
- [`includes/class-wc-gateway-vinti4.php`](includes/class-wc-gateway-vinti4.php) line 259: the normal checkout flow calls `create_payment_attempt($order, (float) $order->get_total(), ['source' => 'checkout'])` then `Vinti4_Attempt_Store::append_attempt(...)` — i.e. it registers a proper attempt record using whatever `get_total()` returns at that moment.

### B. The client's live "Reception Dashboard" snippet (what's actually in use — buggy)
Two files pulled from the live site's snippet plugin (WPCode or similar), **not part of this git repo**, kept locally as `Reception Dashboard - Partials.php` / `Override WooCommerce Order Total for Partial Payments.php` (git-ignored) for reference:

- `Reception Dashboard - Partials.php` — `spb_reception_approval_list()` shortcode. Renders a table of orders with a % selector + "Send Link" button per row. On click, POSTs to an n8n webhook (`https://n8nnew.seolutional.com/webhook/c5c48737-3091-4206-8116-e27cb0d8db8b`) with `order_id`, `requested_percentage`, `amount_to_pay_cve`.
- `Override WooCommerce Order Total for Partial Payments.php` — three hooks (`woocommerce_available_payment_gateways`, `woocommerce_order_get_total` filter @ priority 20, and a `wp` action) that override `$order->set_total()` **in memory only** (never saved) so the Vinti4 gateway's fingerprint/checkout uses the deposit amount instead of the full order total, while the WP admin/dashboard keeps showing the full price.
- n8n workflow: `RoomRaccoon - Email Listener & Messenger (HITL)`, workflow ID `AttwbezGuct4Yrhm`, hosted at `https://n8nnew.seolutional.com`. Local export kept as `RoomRaccoon - Email Listener & Messenger (HITL).json` (git-ignored). Relevant node: **"Update Woo Order"** (~line 386 in the JSON), triggered by the same dashboard webhook, writes order meta:
  - `spb_payment_link_sent` = `'1'`
  - `spb_partial_payment_requested` = amount from the webhook payload
  - `spb_partial_percentage` = percentage from the webhook payload
  - `spb_original_total` = the order's total at that time

System B does **not** call `Vinti4_Attempt_Store::append_attempt()` or `create_payment_attempt()` at all — it never registers a real "attempt" in system A's history. It's a fully parallel, bespoke tracking mechanism using its own order meta keys.

---

## Bug #1: Can't request the remaining balance — CONFIRMED

`Reception Dashboard - Partials.php` disables the entire row (select, manual input, button) the moment `spb_payment_link_sent === '1'`:

```php
$is_disabled = ($link_sent == '1');   // Reception Dashboard - Partials.php line 97
```

I checked the full n8n workflow export: `spb_payment_link_sent` is written in **exactly one place** ("Update Woo Order" node), **always to `'1'`, never reset back to empty/0** — not on payment completion, not on order status change, not anywhere else in the workflow or in either PHP file.

Result: the first time ANY link is sent for an order (30%, 40%, 50%, or 100%), that row is **permanently frozen** on the Reception Dashboard, regardless of whether there's still an outstanding balance.

### Live evidence (read-only WooCommerce REST API pull, 50 most recent orders, confirmed via app-password auth as `john@morabeza.digital`)

| Order | Status | Total (CVE) | % requested | Requested (CVE) | Remaining (CVE) |
|---|---|---|---|---|---|
| #682 | processing | 234,850 | 30% | 70,455 | 164,395 — unreachable via dashboard |
| #681 | processing | 155,925 | 30% | 46,777.50 | 109,147.50 — unreachable via dashboard |
| #680 | processing | 80,850 | 30% | 24,255 | 56,595 — unreachable via dashboard |
| #711 | pending | 413,875 | 40% | 165,550 | still pending, row already locked |

All three `processing` orders already had their 30% deposit captured successfully — the client's report matches this exactly.

### Fix options
1. **Minimal**: change `Reception Dashboard - Partials.php`'s disabled logic to key off *remaining balance* (recompute from `spb_original_total - spb_partial_payment_requested`, or better, actual paid total) instead of a one-shot "already sent" flag. Have the n8n workflow clear `spb_payment_link_sent` (or better, stop relying on it as a lock at all) once a request is fulfilled.
2. **Structural (recommended)**: retire the bespoke `spb_*` meta system and have the dashboard's "Send Link" action call into this repo's existing `Vinti4_Admin_Partial_Payment` / `Vinti4_Attempt_Store` flow instead, so there is one source of truth for paid/outstanding and the two systems stop diverging.

---

## Bug #2: Order shows "pending" despite being paid — HYPOTHESIS NOT CONFIRMED by live data; original mechanism theory was wrong

Investigated 2026-09-23 against live order data (read-only WC REST API, `john@morabeza.digital`). The original hypothesis (System B's override causing an `amount_mismatch` → order stuck) does **not** hold up:

### The WhatsApp screenshot order (#681, 155,925 CVE) actually worked correctly

Pulled full order + `_vinti4_attempt_history` for #681 (the order matching the client's screenshot):
- 3 checkout attempts exist in `_vinti4_attempt_history`, all with `amount: 46778` (the 30% deposit, `metadata.source: "checkout"`) — **not** the full order total. This proves System B's `woocommerce_order_get_total` override *does* successfully feed the normal gateway checkout flow (`class-wc-gateway-vinti4.php:259`), which *does* call `create_payment_attempt()`/`append_attempt()` with the correct (overridden) amount. The original doc's claim that "System B never calls `create_payment_attempt()`" is wrong for this order.
- Attempts 1 (08-26) and 2 (08-27) were abandoned (no `status` key — guest never completed 3DS / never got a callback).
- Attempt 3 (08-30) has `status: completed`, `_vinti4_amount` legacy meta = 46778, and order notes confirm: `Payment via Pay with Vinti4 (205363)` → `Vinti4 payment authorized. TID: 205363` → status correctly flipped to `processing`, `date_paid` set.
- **This order is not an example of bug #2 at all.** It's a clean example of bug #1 only (dashboard row permanently locked at 30%, remaining balance unreachable).

### No live order found matching "paid but still pending"

Checked systematically:
- All 100 currently-`pending` orders: none has a `completed` entry in `_vinti4_attempt_history`. The handful with legacy `_vinti4_amount` set (e.g. #709, #642, #585) just reflect that meta being written at *attempt creation* (redirect-to-SISP time, `class-vinti4-attempt-store.php:244` / `class-vinti4-redirect-form.php:115`), not proof of payment — these are simply abandoned checkouts.
- All 12 recent `failed` orders: every note reads `Vinti4 payment failed: - Authentication failure` (genuine card/3DS declines). None shows an `amount_mismatch` note — so the override-causes-mismatch theory has zero supporting evidence in the sample.
- Confirmed via source (`class-vinti4-callback-handler.php:539-597` and `:749-774`) that **both** the attempt-based and legacy callback paths call `update_status('processing')` or `payment_complete()` synchronously on any validated success — there's no code path where a successful callback leaves an order at `pending`.

### Remaining plausible root cause: redirect-only callback, no server-to-server fallback

`urlMerchantResponse` (the only callback SISP is given — `class-vinti4-request-builder.php:116`, `class-vinti4-redirect-form.php:167`) points at a single endpoint, `/wc-api/vinti4/`, registered via `add_action('woocommerce_api_' . $this->id, ...)` (`class-wc-gateway-vinti4.php:60`). This is a **browser-redirect callback only** — no separate async/server-to-server notification URL exists in this codebase. If the customer's bank app or browser fails to complete the redirect back to the site after SISP authorizes the charge (closed tab, network drop after OTP, back button), SISP has the money but WooCommerce never receives any request — the order legitimately stays `pending` forever, with zero trace in `_vinti4_attempt_history` (indistinguishable, from the WC side, from an abandoned checkout). This matches "pending but the client believes it's paid" better than the original theory, but **can't be confirmed or ruled out from WC REST API data alone** — it needs either SISP merchant-portal transaction records or web server access/error logs around `/wc-api/vinti4/` hits, neither of which is available in this session.

**Next steps to actually confirm**: ask the client for the *specific* order number/date behind the "still shows pending" comment (order #681 is not it — it resolved cleanly on 2026-08-30, three weeks before the report). Once identified, check whether SISP's own merchant dashboard shows a settled transaction for it with no matching order note/attempt here — that would confirm the redirect-drop theory and point toward adding a reconciliation job (poll SISP for transaction status on stale pending orders) rather than a code fix to the callback validation logic itself.

---

## Feature request #3: Search on the Reception Dashboard

Confirmed — `Reception Dashboard - Partials.php` has no search/filter UI at all (grepped, no matches). Client says identifying bookings currently takes significant time. Straightforward addition: a text input filtering the rendered rows client-side (guest name, booking type, order ID) would likely be enough given the dashboard only loads 50 orders at a time (`wc_get_orders(['limit' => 50, ...])` — also worth flagging that `limit => 50` may itself be part of why bookings are hard to find, if there are more than 50 open orders at a time).

---

## Access / environment notes for a fresh session

- Live site: `https://booking.saopedrobay.com`. WP Application Password for `john@morabeza.digital` was provided in chat for **read-only** investigation only — do not repeat it into files; it's already in the user's own message history. **No edits to the live site** — confirmed constraint, still applies.
- WooCommerce REST API is reachable via HTTP Basic Auth with that app password, e.g.:
  `curl -u "john@morabeza.digital:<app-password>" "https://booking.saopedrobay.com/wp-json/wc/v3/orders?per_page=50&status=any&orderby=date&order=desc"`
- n8n instance: `https://n8nnew.seolutional.com`. An MCP server named `n8n-mcp` was registered for this project at **local scope** (`claude mcp add --transport http n8n-mcp https://n8nnew.seolutional.com/mcp-server/http --header "Authorization: Bearer ..." -s local`) — config lives in `~/.claude.json` under this project, not committed to git. **Do not edit the live n8n workflow** — read-only, per user instruction.
  - **2026-09-23 update**: still not usable. `claude mcp list` reports it "✔ Connected" (that's just an HTTP reachability check), but it never actually attaches as an MCP server in-session — `ListMcpResourcesTool`/`ToolSearch` don't see it, and the only n8n tools available are from an unrelated "claude.ai n8n" server pointed at a different (personal) n8n instance. Don't burn time re-probing `claude mcp list` — go straight to the local JSON export fallback.
- The relevant n8n workflow is `RoomRaccoon - Email Listener & Messenger (HITL)`, ID `AttwbezGuct4Yrhm`. A local JSON export also exists at the project root (git-ignored) as a fallback if the MCP connection isn't available.
- `Reception Dashboard - Partials.php`, `Override WooCommerce Order Total for Partial Payments.php`, and the n8n workflow JSON export live at the project root and are git-ignored (see `.gitignore`), and the user intends to delete them once this investigation wraps up.
  - **2026-09-23 update**: these were originally kept locally as `code1.php`/`code2.php`, but those two filenames were never actually added to `.gitignore` and ended up committed to this branch by mistake (see `git log` on this branch). Renamed to their real WPCode snippet titles above (matches an automated WPCode backup that dropped fresh copies under these names) and *now* correctly git-ignored going forward. The old `code1.php`/`code2.php` were deleted from the working tree; the stray commit still exists in this branch's history but isn't being rewritten.

---

## Follow-up: dashboard/system gap analysis — 2026-09-23

Client sent a fresh screenshot of the live dashboard plus a broader ask: accurate remainder tracking with a hard cap (can't request more than what's actually still owed), an owner-visible payment log, an accurate balance tally, and advice on reflecting partial/full payment in WooCommerce order status without adding a custom status. Investigated read-only against live order data.

### Critical finding: the bug-1 fix (as drafted) won't actually work once deployed

Pulled order **#738** ("jo konings", created today 15:14 — the exact row in the client's screenshot): total 1,100 CVE, 70% requested (770 CVE), **`_vinti4_attempt_history` has zero entries** — nothing has been paid, the guest hasn't even opened the payment link. The live dashboard shows "Rem: 770 CVE." That's wrong; with nothing paid, the true remaining balance is the full 1,100.

Root cause: `Override WooCommerce Order Total for Partial Payments.php`'s `woocommerce_order_get_total` filter (priority 20, `spb_vinti4_modal_final_force`) only checks `is_admin()` — it is **not** scoped to the checkout/pay page, so it also rewrites `$order->get_total()` on the Reception Dashboard page itself whenever `spb_payment_link_sent === '1'`. The already-drafted fix in `Reception Dashboard - Partials.php` computes outstanding via `Vinti4_Attempt_Store::get_outstanding_total($order)`, which internally calls `$order->get_total()` — so it silently reads the corrupted (already-overridden) total instead of the real one, and the balance is wrong by construction. **Deploying the current `Reception Dashboard - Partials.php` fix alone will not fix the displayed balance.**

`Reception Dashboard - Partials.php` already has its own equivalent override at the bottom (section 4, `spb_vinti4_deposit_amount_override`), correctly scoped to `is_wc_endpoint_url('order-pay')` only. `Override WooCommerce Order Total for Partial Payments.php` is redundant on top of it and is the one causing the corruption.

**Recommendation: retire `Override WooCommerce Order Total for Partial Payments.php` entirely.** Confirmed it isn't needed — `Reception Dashboard - Partials.php`'s own scoped override already does the job of forcing the deposit amount into the gateway at checkout time.

### Gap: no cap on requested amount vs. actual outstanding

The dashboard's % dropdown/manual input always computes the requested amount against the **original order total** (`data-total` in the JS), never against what's actually still owed, and nothing clamps it. Even after fixing the lock (bug 1) and the total-override corruption above, staff could still select "100%" after a 30% deposit has already been paid and re-request the full original amount — there's no validation anywhere in the System B path preventing over-collection.

This exact validation already exists, correctly, in System A: [class-vinti4-admin-partial-payment.php:290-321](includes/class-vinti4-admin-partial-payment.php#L290-L321) computes percentage against `Vinti4_Attempt_Store::get_outstanding_total()` and hard-rejects (server-side, in the AJAX handler) any amount exceeding outstanding + 0.01. Needs porting into whatever accepts the dashboard's "Send Link" request — and must be enforced server-side (n8n webhook / a repo-side endpoint), since client-side JS clamping alone can be bypassed.

### Gap: no payment log / audit trail on the dashboard side

`Reception Dashboard - Partials.php` + the n8n "Update Woo Order" node **overwrite** `spb_partial_payment_requested` / `spb_partial_percentage` / `spb_original_total` on every "Send Link" click — no history of "30% sent, then 30% more, then rest by cash" is retained anywhere in System B.

System A already solves this correctly, just isn't wired to the dashboard: [class-vinti4-admin-partial-payment.php:76-127](includes/class-vinti4-admin-partial-payment.php#L76-L127) renders a real append-only history table (amount, Paid/Failed/Pending badge, date, reference) per order in the wp-admin order screen, backed by `_vinti4_attempt_history`. Recommend routing the dashboard's "Send Link" action through this same attempt-store flow (`create_payment_attempt()` + `Vinti4_Attempt_Store::append_attempt()`) instead of raw `spb_*` meta overwrites, so every request is automatically logged with no extra UI work.

Cash/offline payments have **no recording mechanism at all** yet — `prototype-record-payment.html` from the prior session sketched three UI variants for this, but it's throwaway HTML only, no backend. Needs a real handler that appends a synthetic `completed` attempt (tagged `metadata.source = 'manual_cash'` or similar) plus an order note, so manual payments flow into the same outstanding calculation and log as gateway payments.

### WooCommerce status advice (no new custom status)

Current behavior ([class-vinti4-callback-handler.php:551-598](includes/class-vinti4-callback-handler.php#L551-L598)): partial payment → stock `processing`; full payment → `processing` or `completed` per the existing `order_status_after_payment` toggle ([class-wc-gateway-vinti4.php:131](includes/class-wc-gateway-vinti4.php#L131)). Both partial and full currently land on the same status (`processing`) unless full-payment is set to `completed` — so status alone can't distinguish "partially paid, balance owing" from "fully paid."

Recommended: **don't add a custom order status.** WooCommerce ties stock handling, reports, and other plugins' automations to its native statuses; a new `wc-partial-payment` status requires `register_post_status()` plus auditing every place that filters by status. Two options, in order of preference:
1. Leave WC status alone; add a separate **payment-progress badge** ("Partially Paid — X owing" / "Paid in Full"), computed purely from `Vinti4_Attempt_Store::get_outstanding_total()`, shown next to the existing status badge on both the dashboard and the admin order list. Zero risk to anything keyed off WC status.
2. If it must show in the native WC status column/filter: repurpose **`on-hold`** for "partially paid, balance outstanding," reserve `processing`/`completed` for fully paid. Real WC status, no registration needed — but `on-hold` carries its own conventional meaning elsewhere (stock handling, other tooling) that this would be overloading; flag that tradeoff before committing to it.

### Proposed course of action (sequenced)

1. **Retire `Override WooCommerce Order Total for Partial Payments.php`** on the live WPCode snippet plugin — removes the redundant, unscoped total override that's corrupting the dashboard's own balance reads. Lowest risk, fixes the tally-accuracy problem at the root; nothing else here can be trusted live until this lands.
2. **Deploy the already-drafted `Reception Dashboard - Partials.php` fix** (recompute outstanding via `Vinti4_Attempt_Store`, unlock rows once genuinely outstanding) — now safe to trust once step 1 is live.
3. **Add the outstanding-balance cap** to the request-creation path (dashboard → n8n or a new repo-side endpoint), mirroring the validation already in `class-vinti4-admin-partial-payment.php` — closes the "can't request more than what's left" gap, enforced server-side.
4. **Route "Send Link" through the attempt-store flow** instead of raw `spb_*` meta overwrites — gives every dashboard-issued request a permanent, appended log entry automatically (closes the audit-log gap for online requests).
5. **Build the "Record Payment" (cash/offline) backend** from the `prototype-record-payment.html` UI work — append a manual attempt + order note so cash payments count toward the same outstanding calc and log.
6. **Add the payment-progress badge** (Partially Paid / Paid in Full) once outstanding is trustworthy end to end — no new WC status needed.
7. Separately, still blocked on the client: **spot-check the 29 flagged bookings** and **get the specific order number** behind the original "still shows pending" report (bug #2) — unrelated to the above, needs their input to close out.

Steps 1–2 are the only ones with a "deploy" step outside this repo (the WPCode snippet on the live site) — no deploy access from here, per the existing constraint in `FINDINGS-reception-dashboard-payment-bugs.md`. Steps 3–6 are net-new work (dashboard request path + a cash-recording feature) and would benefit from being scoped as their own follow-up, since they go beyond the three originally reported bugs.
