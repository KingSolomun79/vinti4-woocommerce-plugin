/**
 * RETIRED — 2026-09-25. Do not re-enable.
 *
 * All three overrides below force $order->set_total() to the deposit amount
 * in memory on the checkout/pay page. That's already done correctly by
 * "Reception Dashboard - Partials.php" itself (section 4,
 * spb_vinti4_deposit_amount_override, scoped to is_wc_endpoint_url('order-pay')),
 * so this entire snippet is redundant with that one.
 *
 * Worse, function #2 (spb_vinti4_modal_final_force) hooks
 * woocommerce_order_get_total with NO page/endpoint check beyond is_admin() —
 * it rewrites get_total() on every frontend request once a payment link has
 * been sent for an order, including the Reception Dashboard's own page. That
 * silently corrupted the dashboard's own outstanding-balance calculation
 * (Vinti4_Attempt_Store::get_outstanding_total() reads get_total() too), which
 * is why the dashboard showed "Rem" equal to the last requested amount instead
 * of the real remaining balance — confirmed live against order #738, see
 * INVESTIGATION-spb-partial-payment.md, "Follow-up: dashboard/system gap
 * analysis — 2026-09-23".
 *
 * This file's WPCode snippet must be deactivated (or deleted) on the live
 * site as part of deploying the "Reception Dashboard - Partials.php" fix —
 * deploying that fix alone, while this snippet stays active, does not
 * actually fix the balance display. Left everything below commented out
 * (rather than deleting outright) so the original logic stays available for
 * reference if a future issue needs to compare against it.
 */

// add_filter('woocommerce_available_payment_gateways', 'spb_force_gateway_total_sync');
// function spb_force_gateway_total_sync($available_gateways) {
//     if (is_admin()) return $available_gateways;
//
//     $order_id = absint(get_query_var('order-pay'));
//     if (!$order_id) return $available_gateways;
//
//     $order = wc_get_order($order_id);
//     if ($order && $order->get_meta('spb_payment_link_sent') === '1') {
//         $partial_amount = $order->get_meta('spb_partial_payment_requested');
//
//         // This is a "surgical" strike to force the gateway's internal order reference
//         if (!empty($partial_amount)) {
//             $order->set_total($partial_amount);
//             // We don't save the order here to keep the backend 27500 correct
//         }
//     }
//     return $available_gateways;
// }

// add_filter('woocommerce_order_get_total', 'spb_vinti4_modal_final_force', 20, 2);
// function spb_vinti4_modal_final_force($total, $order) {
//     if (is_admin()) return $total;
//
//     $link_sent = $order->get_meta('spb_payment_link_sent');
//     $partial_amount = $order->get_meta('spb_partial_payment_requested');
//
//     if ($link_sent === '1' && !empty($partial_amount)) {
//         // We use a high priority (20) to make sure we are the last ones touching the total
//         return (float) $partial_amount;
//     }
//     return $total;
// }

// add_action('wp', 'spb_global_order_total_force');
// function spb_global_order_total_force() {
//     // Only run on the checkout/pay-order page
//     if (is_admin() || !is_wc_endpoint_url('order-pay')) return;
//
//     $order_id = absint(get_query_var('order-pay'));
//     if (!$order_id) return;
//
//     $order = wc_get_order($order_id);
//     if ($order && $order->get_meta('spb_payment_link_sent') === '1') {
//         $partial_amount = $order->get_meta('spb_partial_payment_requested');
//
//         if (!empty($partial_amount)) {
//             // This forces the value into the object's core property in RAM.
//             // When the Vinti4 plugin calls $order->get_total(), it will get the deposit.
//             $order->set_total((float)$partial_amount);
//         }
//     }
// }
