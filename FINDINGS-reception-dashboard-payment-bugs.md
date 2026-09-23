# São Pedro Bay — Reception Dashboard Payment Findings

2026-09-23

## Summary

Three issues were reported: bookings stuck unable to collect the remaining balance after a deposit, a booking that appeared "pending" despite looking paid, and no way to search the reception dashboard.

- **Can't request the remaining balance** — root cause confirmed, fix written and ready to deploy.
- **Shows pending despite being paid** — the original theory didn't hold up against live data. Testing the fix surfaced a different, more concrete problem instead (details below) — we need one more detail from you to close this out.
- **Dashboard search** — added.

## Bug 1 — Can't request the remaining balance after a deposit

**Root cause:** every dashboard row permanently locks itself the first time ANY payment link is sent for a booking — 30%, 40%, 50%, or 100% — because the code was treating "a link was sent once" as "nothing more is owed." It never re-checked whether a balance was actually still outstanding.

**Live evidence:** three bookings currently sitting with a paid 30% deposit and no way to ask for the rest —

| Order | Total (CVE) | Deposit paid | Remaining (unreachable) |
|---|---|---|---|
| #682 | 234,850 | 70,455 (30%) | 164,395 |
| #681 | 155,925 | 46,777.50 (30%) | 109,147.50 |
| #680 | 80,850 | 24,255 (30%) | 56,595 |

**Fix (ready, not yet deployed):** the dashboard now checks the real outstanding balance — total minus what's actually been captured by the payment gateway — instead of a one-time "link sent" flag. A row unlocks automatically whenever there's genuinely still money owed, no matter how many links went out before.

## Bug 2 — "Still shows pending" despite being paid

**Original theory, ruled out:** we first suspected the deposit-amount override was causing the payment gateway to reject the charge. Checked this against live order data — the order from your screenshot (#681) actually went through cleanly: three payment attempts on record, the successful one matched the deposit amount exactly, and the order correctly flipped to "processing" once paid. No order in the sample showed the failure pattern we'd suspected.

**What we found instead, while testing the bug 1 fix:** the dashboard's old "Rem: 0 CVE" display — the thing that made a row look fully settled — never actually meant the guest had paid. It only meant reception had *sent* a payment request for the full amount. Those are written to the booking the moment the "Send Link" button is clicked, before the guest has done anything.

We found a live example: booking #728 (Mr François Husson, 3,989.70 CVE) showed as fully settled and locked on the old dashboard. Pulling the actual payment record for it shows **zero payment attempts, no transaction ID, nothing paid, and the booking status is still "pending."** The guest was never actually charged — the dashboard just made it look that way.

Widening the check, **29 currently-pending bookings show this same pattern** — a full-amount link was sent, the old dashboard showed them as settled, but there's no record of any payment ever being attempted. Combined total: roughly **2,550,000 CVE (~23,200 EUR)**.

This is very likely the actual mechanism behind the "still shows pending" report — inverted from how it first sounded: not a payment succeeding but the status failing to update, but reception being shown a false "paid" signal for a booking that was never actually charged.

**One thing we can't tell from here:** whether any of these 29 guests already paid you a different way — bank transfer, cash, something arranged directly — that never went through the online payment flow. That wouldn't show up in our records at all. Worth a quick spot-check before assuming they're simply unpaid.

## Feature request — dashboard search

Confirmed there was no way to search or filter the reception dashboard. Added a search box above the table that filters by guest name, booking, or order number as you type — no page reload needed.

## What we need from you

1. **Deploy** the updated dashboard code (`code1.php`) to the WPCode snippet on the live site — it's ready, we just don't have deploy access there ourselves.
2. **Spot-check the 29 flagged bookings** below against your own records or SISP's merchant portal before resending any payment links, in case some were already settled outside the online flow:

   #728 (Husson), #696 (Silva), #677 (Vandervinne), #674 (Slama), #668 (Niang), #664 (Cardona), #662 (Janssens), #659 (Blum), #658 (Davidson), #657 (Muldermans), #638 (Vivo Energy), #633 (Vovchyk), #629 (Comello), #625 (Rousseau), #624 (Frédérique), #622 & #621 (Portugal Konings), #593 (Gomes), #587 (Luz), #583 (Rodrigues Fortes), #581 (Carlo), #579, #578/#577/#576 (Jos Konings), #567 (Rodrigues), #565 (Fernandes), #559 & #558 (Breuer)

3. **Tell us which specific booking** prompted the original "still shows pending" comment, so we can check it directly against SISP's records — order #681 (the one from your screenshot) turned out not to be an example of this bug.
