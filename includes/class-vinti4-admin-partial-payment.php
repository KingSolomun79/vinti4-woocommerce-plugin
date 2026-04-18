<?php
defined( 'ABSPATH' ) || exit;

if ( class_exists( 'Vinti4_Admin_Partial_Payment' ) ) {
	return;
}

class Vinti4_Admin_Partial_Payment {

	public static function register(): void {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'wp_ajax_vinti4_create_partial_request', array( __CLASS__, 'handle_create_partial_request' ) );
	}

	public static function add_meta_box(): void {
		add_meta_box(
			'vinti4_partial_payment',
			__( 'Vinti4 Payment Requests', 'vinti4' ),
			array( __CLASS__, 'render_meta_box' ),
			'shop_order',
			'side',
			'high'
		);

		add_meta_box(
			'vinti4_partial_payment',
			__( 'Vinti4 Payment Requests', 'vinti4' ),
			array( __CLASS__, 'render_meta_box' ),
			'woocommerce_page_wc-orders',
			'side',
			'high'
		);
	}

	public static function render_meta_box( $post ): void {
		$order_id = is_a( $post, 'WP_Post' ) ? $post->ID : (int) $post;
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			echo '<p>' . esc_html__( 'Order not found.', 'vinti4' ) . '</p>';
			return;
		}

		$outstanding  = Vinti4_Attempt_Store::get_outstanding_total( $order );
		$paid         = Vinti4_Attempt_Store::get_paid_total( $order );
		$order_total  = (float) $order->get_total();

		$highlight = '';
		if ( $outstanding > 0 ) {
			$highlight = 'font-weight:bold; color:#2271b1;';
		}

		echo '<style>.vinti4-partial-form table{width:100%} .vinti4-partial-form td{padding:2px 4px;}</style>';

		echo '<div class="vinti4-partial-form">';

		echo '<table>';
		echo '<tr><td>' . esc_html__( 'Order Total:', 'vinti4' ) . '</td><td>' . wc_price( $order_total ) . '</td></tr>';
		echo '<tr><td>' . esc_html__( 'Paid:', 'vinti4' ) . '</td><td>' . wc_price( $paid ) . '</td></tr>';
		echo '<tr><td>' . esc_html__( 'Outstanding:', 'vinti4' ) . '</td><td style="' . esc_attr( $highlight ) . '">' . wc_price( $outstanding ) . '</td></tr>';
		echo '</table>';

		if ( $outstanding <= 0 ) {
			echo '<p>' . esc_html__( 'This order is fully paid.', 'vinti4' ) . '</p>';
			echo '</div>';
			return;
		}

		wp_nonce_field( 'vinti4_partial_payment', 'vinti4_partial_nonce' );

		echo '<input type="hidden" name="vinti4_order_id" value="' . esc_attr( (string) $order->get_id() ) . '">';

		echo '<p>';
		echo '<label><input type="radio" name="vinti4_amount_mode" value="fixed" checked> ' . esc_html__( 'Fixed Amount', 'vinti4' ) . '</label><br>';
		echo '<label><input type="radio" name="vinti4_amount_mode" value="percentage"> ' . esc_html__( 'Percentage (%)', 'vinti4' ) . '</label>';
		echo '</p>';

		echo '<p><input type="text" name="vinti4_partial_amount" placeholder="' . esc_attr__( 'Enter amount', 'vinti4' ) . '" style="width:100%"></p>';

		echo '<p><button type="button" class="button button-primary" id="vinti4-submit-partial">' . esc_html__( 'Create Payment Request', 'vinti4' ) . '</button></p>';

		echo '<div class="vinti4-partial-error" style="color:#8B0000;margin-top:8px"></div>';

		echo '<div class="vinti4-payment-link-area" style="display:none;margin-top:12px">';
		echo '<p><strong>' . esc_html__( 'Payment Link:', 'vinti4' ) . '</strong></p>';
		echo '<p><input type="text" readonly class="vinti4-payment-url" style="width:100%"></p>';
		echo '<p><button type="button" class="button vinti4-copy-link">' . esc_html__( 'Copy Link', 'vinti4' ) . '</button></p>';
		echo '</div>';

		echo '</div>';

		?>
		<script>
		(function() {
			var btn = document.getElementById('vinti4-submit-partial');
			if (!btn) return;

			var form = btn.closest('.vinti4-partial-form');
			var errorArea = form.querySelector('.vinti4-partial-error');
			var linkArea = form.querySelector('.vinti4-payment-link-area');
			var linkInput = form.querySelector('.vinti4-payment-url');
			var copyBtn = form.querySelector('.vinti4-copy-link');

			btn.addEventListener('click', function(e) {
				e.preventDefault();

				errorArea.textContent = '';
				linkArea.style.display = 'none';

				var amountInput = form.querySelector('input[name="vinti4_partial_amount"]');
				var amount = amountInput.value.trim();

				if (!amount || isNaN(parseFloat(amount))) {
					errorArea.textContent = '<?php echo esc_js( __( 'Please enter a valid number.', 'vinti4' ) ); ?>';
					return;
				}

				var mode = form.querySelector('input[name="vinti4_amount_mode"]:checked').value;
				var nonce = form.querySelector('input[name="vinti4_partial_nonce"]').value;
				var orderId = form.querySelector('input[name="vinti4_order_id"]').value;

				btn.disabled = true;
				btn.textContent = '<?php echo esc_js( __( 'Processing...', 'vinti4' ) ); ?>';

				var formData = new FormData();
				formData.append('action', 'vinti4_create_partial_request');
				formData.append('vinti4_partial_nonce', nonce);
				formData.append('vinti4_order_id', orderId);
				formData.append('vinti4_amount_mode', mode);
				formData.append('vinti4_partial_amount', amount);

				fetch(ajaxurl, {
					method: 'POST',
					body: formData,
					credentials: 'same-origin'
				})
				.then(function(response) { return response.json(); })
				.then(function(response) {
					btn.disabled = false;
					btn.textContent = '<?php echo esc_js( __( 'Create Payment Request', 'vinti4' ) ); ?>';

					if (response.success) {
						linkArea.style.display = 'block';
						linkInput.value = response.data.payment_link;
					} else {
						errorArea.textContent = response.data.message || '<?php echo esc_js( __( 'An error occurred.', 'vinti4' ) ); ?>';
					}
				})
				.catch(function() {
					btn.disabled = false;
					btn.textContent = '<?php echo esc_js( __( 'Create Payment Request', 'vinti4' ) ); ?>';
					errorArea.textContent = '<?php echo esc_js( __( 'Request failed. Please try again.', 'vinti4' ) ); ?>';
				});
			});

			if (copyBtn) {
				copyBtn.addEventListener('click', function() {
					if (linkInput && linkInput.value) {
						navigator.clipboard.writeText(linkInput.value).then(function() {
							var original = copyBtn.textContent;
							copyBtn.textContent = '<?php echo esc_js( __( 'Copied!', 'vinti4' ) ); ?>';
							setTimeout(function() { copyBtn.textContent = original; }, 2000);
						});
					}
				});
			}
		})();
		</script>
		<?php
	}

	public static function handle_create_partial_request(): void {
		check_ajax_referer( 'vinti4_partial_payment', 'vinti4_partial_nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'vinti4' ) ) );
		}

		$order_id    = absint( $_POST['vinti4_order_id'] ?? 0 );
		$mode        = sanitize_text_field( wp_unslash( $_POST['vinti4_amount_mode'] ?? 'fixed' ) );
		$raw_amount  = sanitize_text_field( wp_unslash( $_POST['vinti4_partial_amount'] ?? '' ) );

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'Order not found.', 'vinti4' ) ) );
		}

		if ( ! is_numeric( $raw_amount ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid number.', 'vinti4' ) ) );
		}

		if ( 'percentage' === $mode ) {
			$percentage = (float) $raw_amount;

			if ( $percentage <= 0 || $percentage > 100 ) {
				wp_send_json_error( array( 'message' => __( 'Percentage must be between 1 and 100.', 'vinti4' ) ) );
			}

			$outstanding = Vinti4_Attempt_Store::get_outstanding_total( $order );
			$amount      = round( $outstanding * ( $percentage / 100 ), 2 );
		} else {
			$amount = (float) $raw_amount;
		}

		if ( $amount <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Amount must be greater than zero.', 'vinti4' ) ) );
		}

		$outstanding = Vinti4_Attempt_Store::get_outstanding_total( $order );

		if ( $outstanding <= 0.01 ) {
			wp_send_json_error( array( 'message' => __( 'This order is already fully paid.', 'vinti4' ) ) );
		}

		if ( $amount > $outstanding + 0.01 ) {
			wp_send_json_error( array(
				'message' => sprintf(
					/* translators: %s: outstanding balance amount */
					__( 'Amount cannot exceed the outstanding balance of %s.', 'vinti4' ),
					wc_price( $outstanding )
				),
			) );
		}

		$gateways = WC()->payment_gateways()->get_available_payment_gateways();
		$gateway  = $gateways['vinti4'] ?? null;

		if ( ! $gateway ) {
			wp_send_json_error( array( 'message' => __( 'Vinti4 gateway is not available.', 'vinti4' ) ) );
		}

		$attempt = $gateway->create_payment_attempt( $order, $amount, array( 'source' => 'admin_partial' ) );
		Vinti4_Attempt_Store::append_attempt( $order, $attempt, true );

		$payment_link = add_query_arg(
			array(
				'order' => $order->get_id(),
				'key'   => $order->get_order_key(),
			),
			home_url( '/vinti4-payment/' )
		);

		$order->add_order_note( sprintf(
			'Vinti4 partial payment request created: %s. Outstanding was: %s.',
			wc_price( $amount ),
			wc_price( $outstanding )
		) );

		Vinti4_Logger::log( sprintf(
			'Admin partial request: order %d, amount %.2f, attempt %s',
			$order->get_id(),
			$amount,
			$attempt['attempt_id'] ?? 'unknown'
		) );

		wp_send_json_success( array(
			'payment_link' => $payment_link,
			'amount'       => $amount,
			'attempt_id'   => $attempt['attempt_id'] ?? '',
		) );
	}
}
