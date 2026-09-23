/**
 * RECEPTION DASHBOARD & PAYMENT OVERRIDE SYSTEM
 * For: São Pedro Bay
 */

add_shortcode('reception_dashboard', 'spb_reception_approval_list');

function spb_reception_approval_list() {
    if (!current_user_can('edit_posts')) return 'Access Denied';

    // --- 1. GLOBAL HEADER ---
    $logo_url         = "https://booking.saopedrobay.com/wp-content/uploads/2026/03/favicon-sao-pedro-bay.png";
    $registration_url = "https://booking.saopedrobay.com/guest-registration/";
    $dashboard_url    = "https://booking.saopedrobay.com/reception-dashboard/";
    
    $header_html = '
    <div id="spb-header" style="text-align: center; margin-bottom: 30px; font-family: sans-serif;">
        <img src="' . $logo_url . '" alt="São Pedro Bay" style="max-width: 120px; height: auto; margin-bottom: 15px;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #0A5B50; padding-bottom: 10px; max-width: 1200px; margin: 0 auto;">
            <h2 style="margin: 0; color: #333; font-weight: bold; text-transform: uppercase; font-size: 1.2em;">Reception Dashboard</h2>
            <nav>
                <a href="' . $dashboard_url . '" style="text-decoration: none; color: #0A5B50; font-weight: bold; margin-right: 20px; font-size: 14px;">Reception List</a>
                <a href="' . $registration_url . '" style="text-decoration: none; background: #0A5B50; color: #fff; padding: 8px 16px; border-radius: 4px; font-size: 14px; font-weight: bold;">+ New Registration</a>
            </nav>
        </div>
    </div>';

    // --- 2. DATA RETRIEVAL ---
    $orders = wc_get_orders(array(
        'limit'   => 50, 
        'status'  => array('pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed'),
        'orderby' => 'date', 
        'order'   => 'DESC'
    ));

    $html = '<div id="spb-container" style="max-width: 1200px; margin: 0 auto;">' . $header_html;

    if (empty($orders)) {
        $html .= '<p style="text-align:center; padding:20px;">No orders found.</p></div>';
        return $html;
    }

    $html .= '<div style="margin-bottom: 15px;">
                <input type="text" id="spb-search" placeholder="Search by guest name, booking, or order #..."
                       style="width:100%; max-width: 400px; padding: 10px 14px; font-size: 14px; border: 1px solid #ccc; border-radius: 4px; font-family: sans-serif; box-sizing: border-box;">
              </div>';

    $html .= '<div style="overflow-x: auto;">
                <table style="width:100%; border-collapse: collapse; background: #fff; border: 1px solid #ddd; min-width: 1150px; font-family: sans-serif;">
                <tr style="background: #0A5B50; color: #fff; text-align: left;">
                    <th style="padding: 12px;">Source</th>
                    <th style="padding: 12px;">Guest / Booking Details</th>
                    <th style="padding: 12px;">Order Total (CVE/EUR)</th>
                    <th style="padding: 12px; width: 160px;">Payment Split</th>
                    <th style="padding: 12px;">Requested Amount</th>
                    <th style="padding: 12px;">Action</th>
                </tr>';

    foreach ($orders as $order) {
        $order_id  = $order->get_id();
        
        // --- DATA INTEGRITY FIX ---
        // Prioritize the original total captured by n8n so figures don't drop in the UI
        $original_meta = $order->get_meta('spb_original_total');
        $total_cve     = !empty($original_meta) ? (float) $original_meta : (float) $order->get_total();
        $total_eur     = $total_cve / 110.00;
        $status        = $order->get_status();
        
        // Metadata & Order Data
        $link_sent   = $order->get_meta('spb_payment_link_sent'); 
        $sent_pct    = $order->get_meta('spb_partial_percentage');
        $sent_amount = $order->get_meta('spb_partial_payment_requested');
        $arrival     = $order->get_meta('arrival_date');
        $departure   = $order->get_meta('departure_date');
        $order_date  = $order->get_date_created()->date('d/m/Y H:i');

        // Source Logic
        $source = $order->get_meta('source_origin');
        $is_staff = (stripos($source, 'staff') !== false);
        $source_label = !empty($source) ? str_replace('_', ' ', $source) : 'Unknown';
        $source_style = $is_staff 
            ? 'background: #E8F5E9; color: #2E7D32; border: 1px solid #A5D6A7;' 
            : 'background: #E3F2FD; color: #1565C0; border: 1px solid #90CAF9;';

        $items = $order->get_items();
        $booking_info = "Accommodation Not Found";
        foreach ($items as $item) { $booking_info = $item->get_name(); break; }

        // Outstanding balance from the real attempt history (what's actually been paid),
        // not just the last requested amount — a link having been sent before doesn't
        // mean the balance it covered was ever paid, or that it covered the whole total.
        $outstanding = class_exists('Vinti4_Attempt_Store')
            ? Vinti4_Attempt_Store::get_outstanding_total($order)
            : max(0, $total_cve - (float)$sent_amount);

        $display_sent_amt = $link_sent ? number_format((float)$sent_amount, 0) . " CVE" : "—";
        $balance = $link_sent ? number_format($outstanding, 0) . " CVE" : "—";

        // Status Badge
        $status_colors = [
            'pending'    => ['bg' => '#ffc107', 'text' => '#000'],
            'processing' => ['bg' => '#28a745', 'text' => '#fff'],
            'on-hold'    => ['bg' => '#17a2b8', 'text' => '#fff'],
            'completed'  => ['bg' => '#6c757d', 'text' => '#fff'],
            'cancelled'  => ['bg' => '#dc3545', 'text' => '#fff'],
        ];
        $st_style = $status_colors[$status] ?? ['bg' => '#eee', 'text' => '#333'];

        // Searchable text blob for the dashboard search box, below.
        $search_blob = esc_attr(strtolower(
            $order_id . ' ' .
            $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() . ' ' .
            $booking_info . ' ' . $source_label
        ));

        // A link having been sent once used to permanently lock the row (bug: staff
        // could never request the remaining balance after an initial deposit). Lock it
        // only while there's genuinely nothing left owing.
        $is_disabled = ($outstanding <= 0.01);
        $button_style = $is_disabled ? 'background:#ccc; color:#666; cursor:not-allowed;' : 'background:#0A5B50; color:white; cursor:pointer;';
        $input_attr = $is_disabled ? 'disabled' : '';

        $html .= "<tr id='row-{$order_id}' data-search='{$search_blob}' style='border-bottom: 1px solid #eee; " . ($is_disabled ? 'background-color: #f9f9f9;' : '') . "'>
                    <td style='padding: 12px;'>
                        <span style='font-size: 10px; font-weight: bold; padding: 4px 8px; border-radius: 4px; text-transform: uppercase; {$source_style}'>
                            {$source_label}
                        </span>
                    </td>
                    <td style='padding: 12px;'>
                        <div style='margin-bottom: 4px;'><strong>{$order->get_billing_first_name()} {$order->get_billing_last_name()}</strong></div>
                        <div style='color:#0A5B50; font-weight:600; font-size: 0.9em;'>{$booking_info}</div>
                        <div style='color:#666; font-size: 0.85em; margin-top: 4px;'>
                            📅 <strong>Stay:</strong> {$arrival} - {$departure}<br>
                            🕒 <strong>Created:</strong> {$order_date}
                        </div>
                        <span style='background:{$st_style['bg']}; color:{$st_style['text']}; font-size:9px; padding:2px 5px; border-radius:3px; text-transform:uppercase; font-weight:bold; display:inline-block; margin-top:6px;'>{$status}</span>
                    </td>
                    <td style='padding: 12px;'>
                        <div style='font-weight:bold; color:#333;'>" . number_format($total_cve, 0) . " CVE</div>
                        <div style='font-size:11px; color:#666;'>" . number_format($total_eur, 2) . " EUR</div>
                    </td>
                    <td style='padding: 12px;'>
                        <div style='display: flex; flex-direction: column; gap: 5px; width: 140px;'>
                            <select class='spb-pct-select' data-id='{$order_id}' {$input_attr} style='font-size: 12px; padding: 4px; width: 100%;'>
                                <option value='100' " . selected($sent_pct, '100', false) . " " . (!$sent_pct ? 'selected' : '') . ">100% Full Payment</option>
                                <option value='30' " . selected($sent_pct, '30', false) . ">30% Deposit</option>
                                <option value='40' " . selected($sent_pct, '40', false) . ">40% Deposit</option>
                                <option value='50' " . selected($sent_pct, '50', false) . ">50% Deposit</option>
                                " . ($is_disabled && !in_array($sent_pct, ['30','40','50','100']) ? "<option value='{$sent_pct}' selected>{$sent_pct}% (Manual)</option>" : "<option value='manual'>Custom %...</option>") . "
                            </select>
                            <input type='number' class='spb-manual-input' data-id='{$order_id}' placeholder='%' 
                                   style='display:none; font-size: 12px; padding: 4px; width: 100%;' min='1' max='100' value='{$sent_pct}'>
                        </div>
                    </td>
                    <td style='padding: 12px;'>
                        <div style='font-size: 0.85em;'>
                            <div style='color: #0A5B50;'><strong>Sent:</strong> {$display_sent_amt}</div>
                            <div style='color: #d9534f;'><strong>Rem:</strong> {$balance}</div>
                        </div>
                    </td>
                    <td style='padding: 12px;'>
                        <button class='spb-approve' 
                                data-id='{$order_id}' 
                                data-total='{$total_cve}'
                                " . ($is_disabled ? 'disabled' : '') . "
                                style='border:none; padding:10px 15px; border-radius:4px; font-weight:bold; {$button_style}'>
                            Send Link
                        </button>
                    </td>
                  </tr>";
    }
    $html .= '</table></div></div>';

    // --- 3. JAVASCRIPT (UI + Webhook) ---
    $html .= "
    <script>
    document.getElementById('spb-search').addEventListener('input', function() {
        const query = this.value.trim().toLowerCase();
        document.querySelectorAll('#spb-container tr[data-search]').forEach(function(row) {
            row.style.display = row.getAttribute('data-search').includes(query) ? '' : 'none';
        });
    });

    function updateRowUI(orderId) {
        const select = document.querySelector('.spb-pct-select[data-id=\"' + orderId + '\"]');
        const manual = document.querySelector('.spb-manual-input[data-id=\"' + orderId + '\"]');
        const btn = document.querySelector('.spb-approve[data-id=\"' + orderId + '\"]');
        
        if (!select || !btn || btn.disabled) return;
        manual.style.display = (select.value === 'manual') ? 'block' : 'none';
        
        let val = (select.value === 'manual') ? manual.value : select.value;
        if (val == 100) {
            btn.innerText = 'Send 100% Full Payment';
        } else if (val > 0) {
            btn.innerText = 'Send ' + val + '% Deposit';
        } else {
            btn.innerText = 'Send Link';
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.spb-pct-select').forEach(s => updateRowUI(s.getAttribute('data-id')));
    });

    document.querySelectorAll('.spb-pct-select, .spb-manual-input').forEach(el => {
        el.addEventListener('input', function() { updateRowUI(this.getAttribute('data-id')); });
    });

    document.querySelectorAll('.spb-approve').forEach(button => {
        button.addEventListener('click', function() {
            const btn = this;
            const orderId = btn.getAttribute('data-id');
            const total = parseFloat(btn.getAttribute('data-total'));
            const select = document.querySelector('.spb-pct-select[data-id=\"' + orderId + '\"]');
            const manualInput = document.querySelector('.spb-manual-input[data-id=\"' + orderId + '\"]');
            
            let percentage = select.value === 'manual' ? manualInput.value : select.value;
            if (!percentage || percentage <= 0 || percentage > 100) { alert('Invalid percentage'); return; }

            const amount = (total * (percentage / 100)).toFixed(2);
            btn.disabled = true;
            btn.innerText = 'Sending...';

            const forceRefresh = setTimeout(() => { window.location.reload(); }, 4000);

            fetch('https://n8nnew.seolutional.com/webhook/c5c48737-3091-4206-8116-e27cb0d8db8b', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    order_id: orderId,
                    requested_percentage: percentage,
                    amount_to_pay_cve: amount,
                    amount_to_pay_eur: (amount / 110.00).toFixed(2)
                })
            }).then(res => { 
                clearTimeout(forceRefresh);
                window.location.reload(); 
            }).catch(err => { 
                window.location.reload(); 
            });
        });
    });
    </script>";

    return $html;
}

/**
 * 4. THE GLOBAL VINTI4 FORCE
 * This ensures the bank's Fingerprint uses the deposit amount 
 * while keeping the Dashboard and Admin figures at full price.
 */
add_action('wp', 'spb_vinti4_deposit_amount_override');
function spb_vinti4_deposit_amount_override() {
    // Only target the frontend 'Order Pay' page
    if (is_admin() || !is_wc_endpoint_url('order-pay')) return;

    $order_id = absint(get_query_var('order-pay'));
    $order = wc_get_order($order_id);

    if ($order && $order->get_meta('spb_payment_link_sent') === '1') {
        $partial = $order->get_meta('spb_partial_payment_requested');
        if (!empty($partial)) {
            // Memory-only override: Tricking the bank's plugin without saving to DB.
            $order->set_total((float)$partial);
        }
    }
}




// /**
//  * TEMPORARY AUTOMATION: Auto-trigger the 100% payment webhook on new orders
//  */
// add_action( 'woocommerce_new_order', 'spb_auto_trigger_payment_webhook', 10, 2 );
// add_action( 'woocommerce_update_order', 'spb_auto_trigger_payment_webhook', 10, 2 );

// function spb_auto_trigger_payment_webhook( $order_id, $order ) {
//     // 1. Check for the n8n completed flag OR our local instantaneous lock
//     if ( $order->get_meta('spb_payment_link_sent') === '1' || get_post_meta( $order_id, '_spb_webhook_processing_lock', true ) === '1' ) {
//         return;
//     }

//     // 2. GUARD: Only fire if the order actually has a total. 
//     $total_cve = (float) $order->get_total();
//     if ( $total_cve <= 0 ) {
//         return; 
//     }

//     // 3. APPLY LOCK: Instantly write a lock to the DB *before* pinging n8n.
//     // We use update_post_meta instead of $order->save() to prevent infinite loops on the update_order hook.
//     update_post_meta( $order_id, '_spb_webhook_processing_lock', '1' );

//     // 4. Build the payload
//     $total_eur = number_format( $total_cve / 110.00, 2, '.', '' );
//     $payload = array(
//         'order_id'             => $order_id,
//         'requested_percentage' => '100',
//         'amount_to_pay_cve'    => $total_cve,
//         'amount_to_pay_eur'    => $total_eur
//     );

//     // 5. Send the POST request to n8n
//     wp_remote_post( 'https://n8nnew.seolutional.com/webhook/c5c48737-3091-4206-8116-e27cb0d8db8b', array(
//         'method'      => 'POST',
//         'timeout'     => 15,
//         'blocking'    => false,
//         'headers'     => array(
//             'Content-Type' => 'application/json',
//         ),
//         'body'        => wp_json_encode( $payload ),
//     ) );
// }