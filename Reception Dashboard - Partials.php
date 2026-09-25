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
    $ajax_url         = admin_url('admin-ajax.php');
    $manual_nonce     = wp_create_nonce('vinti4_manual_payment');
    $method_labels    = class_exists('Vinti4_Manual_Payment') ? Vinti4_Manual_Payment::METHODS : array(
        'cash' => 'Cash', 'wire' => 'Bank Transfer', 'card_in_person' => 'Card (in person)', 'other' => 'Other',
    );

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
        // This also accumulates correctly across any number of payments on the same
        // order (online deposits, further link requests, manual cash/wire entries —
        // see Vinti4_Manual_Payment), since it sums every completed attempt in history
        // rather than looking at only the most recent request.
        $outstanding = class_exists('Vinti4_Attempt_Store')
            ? Vinti4_Attempt_Store::get_outstanding_total($order)
            : max(0, $total_cve - (float)$sent_amount);
        $paid_total = class_exists('Vinti4_Attempt_Store')
            ? Vinti4_Attempt_Store::get_paid_total($order)
            : 0.0;
        $attempts = class_exists('Vinti4_Attempt_Store')
            ? Vinti4_Attempt_Store::get_attempts($order)
            : array();

        // Compact history for the "Record Payment" modal — every attempt that
        // actually counted toward paid_total, oldest first, regardless of source.
        $history_for_modal = array();
        foreach ($attempts as $a) {
            $is_paid = (($a['status'] ?? '') === 'completed') || !empty($a['callback_received']);
            if (!$is_paid) continue;
            $source = $a['metadata']['source'] ?? '';
            if ('manual' === $source) {
                $method = $a['metadata']['method'] ?? 'other';
                $label  = $method_labels[$method] ?? 'Manual';
                $ref    = $a['metadata']['reference'] ?? '';
                if ($ref) $label .= ' (ref ' . $ref . ')';
            } else {
                $label = 'Online payment';
            }
            $history_for_modal[] = array(
                'amount' => (float) ($a['amount'] ?? 0),
                'label'  => $label,
                'when'   => $a['created_at_gmt'] ?? '',
            );
        }

        // The most recent "Send Link" request isn't in attempt history at all
        // until the guest actually opens the payment page — it only exists as
        // single-value spb_* meta, overwritten by each new request. Surface it
        // as a distinct "awaiting payment" line so the log reads as a narrative
        // (requested → paid/cash → recalculated), not just a list of amounts
        // that landed. Only shown while there's still something outstanding —
        // once the order is fully settled, a stale "awaiting payment" line
        // would be actively misleading.
        if ('1' === $link_sent && $outstanding > 0.01 && !empty($sent_amount)) {
            $history_for_modal[] = array(
                'amount' => (float) $sent_amount,
                'label'  => 'Link sent: ' . $sent_pct . '% requested — awaiting payment',
                'when'   => 'as of ' . $order->get_date_modified()->date('Y-m-d H:i'),
            );
        }

        $display_paid_amt = $paid_total > 0 ? number_format($paid_total, 0) . " CVE" : "—";
        $balance = ($link_sent || $paid_total > 0) ? number_format($outstanding, 0) . " CVE" : "—";
        $can_record_payment = $outstanding > 0.01;

        // Once an order is fully paid, the row locks — but staff still need a
        // way to see what was actually paid (online, cash, however many
        // entries) without digging into wp-admin. Reuse the same button and
        // modal in a read-only "View Records" mode instead of disabling it
        // outright, whenever there's actually something to show.
        $has_payment_records = !empty($history_for_modal);
        $payment_btn_enabled = $can_record_payment || $has_payment_records;
        $payment_btn_label   = $can_record_payment ? 'Record Payment' : ($has_payment_records ? 'View Records' : 'Record Payment');

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
                                <option value='100' " . selected($sent_pct, '100', false) . " " . (!$sent_pct ? 'selected' : '') . ">100% (Full Remaining Balance)</option>
                                <option value='30' " . selected($sent_pct, '30', false) . ">30% of remaining</option>
                                <option value='40' " . selected($sent_pct, '40', false) . ">40% of remaining</option>
                                <option value='50' " . selected($sent_pct, '50', false) . ">50% of remaining</option>
                                " . ($is_disabled && !in_array($sent_pct, ['30','40','50','100']) ? "<option value='{$sent_pct}' selected>{$sent_pct}% (Manual)</option>" : "<option value='manual'>Custom %...</option>") . "
                            </select>
                            <input type='number' class='spb-manual-input' data-id='{$order_id}' placeholder='%'
                                   style='display:none; font-size: 12px; padding: 4px; width: 100%;' min='1' max='100' value='{$sent_pct}'>
                            " . ($can_record_payment ? "<div style='font-size:10px; color:#888;'>of " . number_format($outstanding, 0) . " CVE remaining</div>" : "") . "
                        </div>
                    </td>
                    <td style='padding: 12px;'>
                        <div style='font-size: 0.85em;'>
                            <div style='color: #0A5B50;'><strong>Paid:</strong> {$display_paid_amt}</div>
                            <div style='color: #d9534f;'><strong>Rem:</strong> {$balance}</div>
                        </div>
                    </td>
                    <td style='padding: 12px;'>
                        <button class='spb-approve'
                                data-id='{$order_id}'
                                data-outstanding='{$outstanding}'
                                " . ($is_disabled ? 'disabled' : '') . "
                                style='width:100%; box-sizing:border-box; border:none; padding:8px 15px; border-radius:4px; font-weight:bold; margin-bottom:6px; line-height:1.3; text-align:center; {$button_style}'>
                            Send Link
                        </button>
                        <button class='spb-record-payment'
                                data-id='{$order_id}'
                                data-guest='" . esc_attr($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()) . "'
                                data-booking='" . esc_attr($booking_info) . "'
                                data-outstanding='{$outstanding}'
                                data-history='" . esc_attr(wp_json_encode($history_for_modal)) . "'
                                data-readonly='" . ($can_record_payment ? '0' : '1') . "'
                                " . ($payment_btn_enabled ? '' : 'disabled') . "
                                style='width:100%; border:1px solid #0A5B50; padding:8px 15px; border-radius:4px; font-weight:bold; background:#fff; color:#0A5B50; cursor:pointer;" . ($payment_btn_enabled ? '' : ' background:#f5f5f5; color:#999; border-color:#ccc; cursor:not-allowed;') . "'>
                            {$payment_btn_label}
                        </button>
                    </td>
                  </tr>";
    }
    $html .= '</table></div></div>';

    // --- 2b. RECORD PAYMENT MODAL (shared, populated per-row via JS) ---
    $method_options_html = '';
    foreach ($method_labels as $key => $label) {
        $method_options_html .= "<option value='" . esc_attr($key) . "'>" . esc_html($label) . "</option>";
    }

    $html .= "
    <div id='spb-payment-modal-overlay' style='display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); align-items:center; justify-content:center; z-index:999; font-family:sans-serif;'>
        <div style='background:#fff; border-radius:8px; padding:24px; width:380px; max-width:90vw; box-shadow:0 10px 40px rgba(0,0,0,.25);'>
            <h3 id='spb-modal-title' style='margin:0 0 4px; color:#0A5B50;'>Record a payment</h3>
            <div id='spb-modal-sub' style='font-size:12px; color:#777; margin-bottom:12px;'></div>

            <div id='spb-modal-history' style='max-height:150px; overflow-y:auto; border:1px solid #eee; border-radius:6px; margin-bottom:14px; display:none;'></div>

            <input type='hidden' id='spb-modal-order-id'>

            <div id='spb-modal-form-section'>
                <label style='font-size:11px; color:#555; font-weight:600; display:block; margin-bottom:2px;'>Method</label>
                <select id='spb-modal-method' style='font-size:13px; padding:6px; width:100%; border:1px solid #ccc; border-radius:4px; margin-bottom:10px;'>
                    {$method_options_html}
                </select>
                <label style='font-size:11px; color:#555; font-weight:600; display:block; margin-bottom:2px;'>Amount (CVE)</label>
                <input type='number' id='spb-modal-amount' style='font-size:13px; padding:6px; width:100%; border:1px solid #ccc; border-radius:4px; margin-bottom:10px; box-sizing:border-box;'>
                <label style='font-size:11px; color:#555; font-weight:600; display:block; margin-bottom:2px;'>Reference / note (optional)</label>
                <input type='text' id='spb-modal-reference' placeholder='e.g. receipt #, wire ref' style='font-size:13px; padding:6px; width:100%; border:1px solid #ccc; border-radius:4px; margin-bottom:10px; box-sizing:border-box;'>

                <div id='spb-modal-error' style='color:#8B0000; font-size:12px; margin-bottom:8px;'></div>

                <button id='spb-modal-confirm' style='width:100%; box-sizing:border-box; border:none; padding:10px; border-radius:4px; font-weight:bold; background:#0A5B50; color:#fff; cursor:pointer; margin-bottom:8px;'>Confirm payment</button>
            </div>

            <button id='spb-modal-cancel' style='width:100%; box-sizing:border-box; border:1px solid #0A5B50; padding:10px; border-radius:4px; font-weight:bold; background:#fff; color:#0A5B50; cursor:pointer;'>Cancel</button>
        </div>
    </div>";

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

        const outstanding = parseFloat(btn.getAttribute('data-outstanding')) || 0;
        let val = (select.value === 'manual') ? manual.value : select.value;
        if (val > 0 && val <= 100) {
            // Fixed two-line label (action on top, amount below) instead of one
            // long string left to the browser to wrap wherever it fits — keeps
            // every row the same button height and reads consistently next to
            // the single-line Record Payment button beneath it.
            const amt = Math.round(outstanding * (val / 100)).toLocaleString('en-US');
            const line1 = (val == 100 ? 'Send Full Remaining' : 'Send ' + val + '%');
            btn.innerHTML = '<span style=\"display:block;\">' + line1 + '</span>' +
                '<span style=\"display:block; font-weight:normal; font-size:11px; opacity:.85;\">(' + amt + ' CVE)</span>';
        } else {
            btn.innerHTML = 'Send Link';
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
            const outstanding = parseFloat(btn.getAttribute('data-outstanding'));
            const select = document.querySelector('.spb-pct-select[data-id=\"' + orderId + '\"]');
            const manualInput = document.querySelector('.spb-manual-input[data-id=\"' + orderId + '\"]');

            let percentage = select.value === 'manual' ? manualInput.value : select.value;
            if (!percentage || percentage <= 0 || percentage > 100) { alert('Invalid percentage'); return; }

            // Percentage is always of the OUTSTANDING balance, never the original
            // order total — so 100% can never ask for more than what's actually
            // still owed, no matter how much has already been paid (online or cash).
            const amount = (outstanding * (percentage / 100)).toFixed(2);
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

    // --- Record Payment modal ---
    const SPB_AJAX_URL = " . wp_json_encode($ajax_url) . ";
    const SPB_MANUAL_NONCE = " . wp_json_encode($manual_nonce) . ";

    const spbModalOverlay = document.getElementById('spb-payment-modal-overlay');
    const spbModalTitle = document.getElementById('spb-modal-title');
    const spbModalSub = document.getElementById('spb-modal-sub');
    const spbModalHistory = document.getElementById('spb-modal-history');
    const spbModalFormSection = document.getElementById('spb-modal-form-section');
    const spbModalOrderId = document.getElementById('spb-modal-order-id');
    const spbModalAmount = document.getElementById('spb-modal-amount');
    const spbModalReference = document.getElementById('spb-modal-reference');
    const spbModalError = document.getElementById('spb-modal-error');
    const spbModalConfirm = document.getElementById('spb-modal-confirm');
    const spbModalCancel = document.getElementById('spb-modal-cancel');

    function spbCloseModal() {
        spbModalOverlay.style.display = 'none';
        spbModalError.textContent = '';
    }

    // Guest name, booking title, and attempt labels/timestamps can all
    // originate from customer-controllable checkout fields — escape before
    // any of it goes into innerHTML, to avoid stored XSS via a booking name.
    function spbEscapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = (str === null || str === undefined) ? '' : String(str);
        return div.innerHTML;
    }

    document.querySelectorAll('.spb-record-payment').forEach(function(button) {
        if (button.disabled) return;
        button.addEventListener('click', function() {
            const outstanding = parseFloat(button.getAttribute('data-outstanding')) || 0;
            const readonly = button.getAttribute('data-readonly') === '1';
            const guest = spbEscapeHtml(button.getAttribute('data-guest'));
            const booking = spbEscapeHtml(button.getAttribute('data-booking'));
            let history = [];
            try { history = JSON.parse(button.getAttribute('data-history') || '[]'); } catch (e) {}

            spbModalOrderId.value = button.getAttribute('data-id');
            spbModalReference.value = '';
            spbModalError.textContent = '';

            // Fully paid (or otherwise locked) orders open the same modal
            // read-only — just the history, no new-payment form — so staff
            // can always see what was actually paid without an admin login.
            spbModalTitle.textContent = readonly ? 'Payment Records' : 'Record a payment';
            spbModalFormSection.style.display = readonly ? 'none' : 'block';
            spbModalCancel.textContent = readonly ? 'Close' : 'Cancel';

            const outstandingLine = outstanding > 0.01
                ? 'Outstanding: <strong>' + outstanding.toLocaleString('en-US') + ' CVE</strong>'
                : '<strong style=\"color:#2E7D32;\">Fully paid</strong>';
            spbModalSub.innerHTML = guest + ' — ' + booking + '<br>' + outstandingLine;

            if (!readonly) {
                spbModalAmount.value = outstanding.toFixed(2);
                spbModalAmount.max = outstanding.toFixed(2);
                spbModalConfirm.disabled = false;
                spbModalConfirm.textContent = 'Confirm payment';
            }

            if (history.length) {
                spbModalHistory.style.display = 'block';
                spbModalHistory.innerHTML = history.map(function(h) {
                    return '<div style=\"padding:6px 10px; font-size:12px; border-bottom:1px solid #f0f0f0;\">' +
                        spbEscapeHtml(h.label) + ' — ' + Math.round(h.amount).toLocaleString('en-US') + ' CVE' +
                        '<div style=\"color:#999; font-size:11px;\">' + spbEscapeHtml(h.when) + '</div></div>';
                }).join('');
            } else {
                spbModalHistory.style.display = 'none';
                spbModalHistory.innerHTML = '';
            }

            spbModalOverlay.style.display = 'flex';
        });
    });

    spbModalCancel.addEventListener('click', spbCloseModal);
    spbModalOverlay.addEventListener('click', function(e) {
        if (e.target === spbModalOverlay) spbCloseModal();
    });

    spbModalConfirm.addEventListener('click', function() {
        const orderId = spbModalOrderId.value;
        const method = document.getElementById('spb-modal-method').value;
        const amount = spbModalAmount.value;
        const reference = spbModalReference.value;

        if (!amount || parseFloat(amount) <= 0) {
            spbModalError.textContent = 'Please enter a valid amount.';
            return;
        }

        spbModalConfirm.disabled = true;
        spbModalConfirm.textContent = 'Saving...';
        spbModalError.textContent = '';

        const formData = new FormData();
        formData.append('action', 'vinti4_record_manual_payment');
        formData.append('nonce', SPB_MANUAL_NONCE);
        formData.append('order_id', orderId);
        formData.append('method', method);
        formData.append('amount', amount);
        formData.append('reference', reference);

        fetch(SPB_AJAX_URL, { method: 'POST', body: formData, credentials: 'same-origin' })
            .then(function(res) { return res.json(); })
            .then(function(res) {
                if (res.success) {
                    window.location.reload();
                } else {
                    spbModalConfirm.disabled = false;
                    spbModalConfirm.textContent = 'Confirm payment';
                    spbModalError.textContent = (res.data && res.data.message) || 'An error occurred.';
                }
            })
            .catch(function() {
                spbModalConfirm.disabled = false;
                spbModalConfirm.textContent = 'Confirm payment';
                spbModalError.textContent = 'Request failed. Please try again.';
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
