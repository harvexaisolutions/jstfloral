<?php
/**
 * WhatsApp alerts: the owner (and any staff numbers) get a WhatsApp message for
 * every new order. Settings: Shop settings → WhatsApp alerts.
 *
 * Sent with the WhatsApp Business Cloud API (Meta, official, paid per message):
 * phone number ID, access token and an approved template. For free alerts, use the
 * new-order chime and notification on the Orders & Deliveries board.
 *
 * Messages are sent in the background (Action Scheduler), so checkout is never slowed down.
 *
 * @package Fleuriste
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FLEURISTE_WA_OPTION = 'fleuriste_whatsapp';

/**
 * Settings with defaults.
 *
 * @return array
 */
function fleuriste_wa_settings() {
	$s = get_option( FLEURISTE_WA_OPTION, array() );
	if ( is_array( $s ) ) {
		$s['provider'] = 'meta'; // CallMeBot stopped working in 2026.
	}
	return wp_parse_args(
		is_array( $s ) ? $s : array(),
		array(
			'enabled'    => 0,
			'provider'   => 'meta',
			'recipients' => '', // One number per line, e.g. +2305xxxxxxx.
			'meta_phone' => '',
			'meta_token' => '',
			'meta_tpl'   => 'new_order_alert',
			'meta_lang'  => 'en',
			'cust_tpl'   => 'order_update',
			'on_hold'    => 1,
		)
	);
}

/**
 * Recipients: [ [phone, apikey], ... ].
 *
 * @param array $s Settings.
 * @return array
 */
function fleuriste_wa_recipients( $s ) {
	$out = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $s['recipients'] ) as $line ) {
		$parts = array_map( 'trim', explode( '|', $line, 2 ) );
		$phone = preg_replace( '/[^\d+]/', '', $parts[0] );
		if ( '' === $phone ) {
			continue;
		}
		if ( 0 !== strpos( $phone, '+' ) ) {
			$phone = '+230' . $phone; // Mauritius numbers without country code.
		}
		$out[] = array( $phone, isset( $parts[1] ) ? $parts[1] : '' );
	}
	return $out;
}

/**
 * The alert text for an order.
 *
 * @param WC_Order $order Order.
 * @return string
 */
function fleuriste_wa_message( $order ) {
	$data  = function_exists( 'fleuriste_board_order_data' ) ? fleuriste_board_order_data( $order ) : array();
	$lines = array();
	/* translators: 1: order number, 2: shop name */
	$lines[] = sprintf( __( '🌸 New order #%1$s – %2$s', 'fleuriste' ), $order->get_order_number(), get_bloginfo( 'name' ) );
	if ( ! empty( $data['date'] ) ) {
		$lines[] = sprintf(
			/* translators: %s: date and time */
			__( 'Delivery: %s', 'fleuriste' ),
			date_i18n( 'D j M', strtotime( $data['date'] ) ) . ( $data['slot'] ? ', ' . $data['slot'] : '' )
		);
	}
	foreach ( $order->get_items() as $item ) {
		$lines[] = '• ' . $item->get_quantity() . ' × ' . $item->get_name();
	}
	if ( ! empty( $data['recipient'] ) ) {
		/* translators: 1: name, 2: town/region */
		$lines[] = sprintf( __( 'To: %1$s (%2$s)', 'fleuriste' ), $data['recipient'], $order->get_shipping_city() ? $order->get_shipping_city() : $order->get_billing_city() );
	}
	$lines[] = sprintf(
		/* translators: 1: total, 2: payment method */
		__( 'Total: %1$s – %2$s', 'fleuriste' ),
		html_entity_decode( wp_strip_all_tags( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) ) ),
		$order->get_payment_method_title()
	);
	if ( in_array( $order->get_status(), array( 'on-hold', 'pending' ), true ) ) {
		$lines[] = __( '⚠️ Awaiting payment', 'fleuriste' );
	}
	if ( ! empty( $data['anonymous'] ) ) {
		$lines[] = __( 'Anonymous gift', 'fleuriste' );
	}
	$lines[] = admin_url( 'admin.php?page=fleuriste-deliveries' );
	return (string) apply_filters( 'fleuriste_whatsapp_message', implode( "\n", $lines ), $order );
}

/**
 * Send a text to every recipient. Returns a list of [phone, ok, detail].
 *
 * @param string        $text  Message.
 * @param WC_Order|null $order Order (for template parameters).
 * @return array
 */
function fleuriste_wa_send( $text, $order = null ) {
	$s       = fleuriste_wa_settings();
	$results = array();
	foreach ( fleuriste_wa_recipients( $s ) as $r ) {
		list( $phone, $key ) = $r;
		if ( 'meta' === $s['provider'] ) {
			if ( ! $s['meta_phone'] || ! $s['meta_token'] ) {
				$results[] = array( $phone, false, __( 'Phone number ID or access token missing', 'fleuriste' ) );
				continue;
			}
			$params = $order ? array(
				$order->get_order_number(),
				fleuriste_wa_delivery_text( $order ),
				html_entity_decode( wp_strip_all_tags( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) ) ),
			) : array( 'TEST', __( 'test message', 'fleuriste' ), '0' );
			$body   = array(
				'messaging_product' => 'whatsapp',
				'to'                => ltrim( $phone, '+' ),
				'type'              => 'template',
				'template'          => array(
					'name'       => $s['meta_tpl'],
					'language'   => array( 'code' => $s['meta_lang'] ),
					'components' => array(
						array(
							'type'       => 'body',
							'parameters' => array_map(
								function ( $p ) {
									return array(
										'type' => 'text',
										'text' => (string) $p,
									);
								},
								$params
							),
						),
					),
				),
			);
			$res    = wp_remote_post(
				'https://graph.facebook.com/v21.0/' . rawurlencode( $s['meta_phone'] ) . '/messages',
				array(
					'timeout' => 15,
					'headers' => array(
						'Authorization' => 'Bearer ' . $s['meta_token'],
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode( $body ),
				)
			);
		}
		if ( is_wp_error( $res ) ) {
			$results[] = array( $phone, false, $res->get_error_message() );
			continue;
		}
		$code      = (int) wp_remote_retrieve_response_code( $res );
		$body_text = wp_strip_all_tags( (string) wp_remote_retrieve_body( $res ) );
		$ok        = $code >= 200 && $code < 300 && false === stripos( $body_text, 'error' );
		$results[] = array( $phone, $ok, $ok ? 'OK' : ( 'HTTP ' . $code . ' ' . mb_substr( $body_text, 0, 160 ) ) );
	}
	return $results;
}

/**
 * Short delivery text for template messages.
 *
 * @param WC_Order $order Order.
 * @return string
 */
function fleuriste_wa_delivery_text( $order ) {
	$data = function_exists( 'fleuriste_board_order_data' ) ? fleuriste_board_order_data( $order ) : array();
	if ( empty( $data['date'] ) ) {
		return __( 'no date', 'fleuriste' );
	}
	return date_i18n( 'D j M', strtotime( $data['date'] ) ) . ( $data['slot'] ? ', ' . $data['slot'] : '' );
}

/* -------------------------------------------------------------------------
 * Trigger: once per order, when it becomes Processing (paid) or On hold
 * ---------------------------------------------------------------------- */

/**
 * Queue the alert for an order.
 *
 * @param int $order_id Order ID.
 */
function fleuriste_wa_queue( $order_id ) {
	$s = fleuriste_wa_settings();
	if ( empty( $s['enabled'] ) ) {
		return;
	}
	$order = wc_get_order( $order_id );
	if ( ! $order || $order->get_meta( '_fleuriste_wa_sent' ) ) {
		return;
	}
	if ( 'fleuriste-phone' === $order->get_created_via() ) {
		return; // Taken by the shop itself – no need to alert the shop.
	}
	if ( 'on-hold' === $order->get_status() && empty( $s['on_hold'] ) ) {
		return;
	}
	$order->update_meta_data( '_fleuriste_wa_sent', time() );
	$order->save_meta_data();
	if ( function_exists( 'as_enqueue_async_action' ) ) {
		as_enqueue_async_action( 'fleuriste_wa_send_order', array( (int) $order_id ), 'fleuriste' );
	} else {
		do_action( 'fleuriste_wa_send_order', (int) $order_id );
	}
}
add_action( 'woocommerce_order_status_processing', 'fleuriste_wa_queue', 20 );
add_action( 'woocommerce_order_status_on-hold', 'fleuriste_wa_queue', 20 );

add_action(
	'fleuriste_wa_send_order',
	function ( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$results = fleuriste_wa_send( fleuriste_wa_message( $order ), $order );
		$notes   = array();
		foreach ( $results as $r ) {
			$notes[] = $r[0] . ': ' . ( $r[1] ? __( 'sent', 'fleuriste' ) : __( 'failed', 'fleuriste' ) . ' (' . $r[2] . ')' );
		}
		if ( $notes ) {
			$order->add_order_note( __( 'WhatsApp alert', 'fleuriste' ) . ' – ' . implode( '; ', $notes ) );
		}
	}
);

/* -------------------------------------------------------------------------
 * Settings page: Shop settings → WhatsApp alerts
 * ---------------------------------------------------------------------- */

add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'fleuriste-deliveries', __( 'WhatsApp alerts', 'fleuriste' ), __( 'WhatsApp alerts', 'fleuriste' ), 'manage_woocommerce', 'fleuriste-whatsapp', 'fleuriste_wa_page' );
	},
	30
);

add_action(
	'admin_post_fleuriste_whatsapp',
	function () {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'fleuriste' ) );
		}
		check_admin_referer( 'fleuriste_whatsapp' );
		$old = fleuriste_wa_settings();
		$new = array(
			'enabled'    => empty( $_POST['enabled'] ) ? 0 : 1,
			'on_hold'    => empty( $_POST['on_hold'] ) ? 0 : 1,
			'provider'   => 'meta',
			'recipients' => isset( $_POST['recipients'] ) ? sanitize_textarea_field( wp_unslash( $_POST['recipients'] ) ) : '',
			'meta_phone' => isset( $_POST['meta_phone'] ) ? preg_replace( '/\D/', '', wp_unslash( $_POST['meta_phone'] ) ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			'meta_token' => isset( $_POST['meta_token'] ) && '' !== trim( wp_unslash( $_POST['meta_token'] ) ) ? sanitize_text_field( wp_unslash( $_POST['meta_token'] ) ) : $old['meta_token'], // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			'meta_tpl'   => isset( $_POST['meta_tpl'] ) ? sanitize_key( wp_unslash( $_POST['meta_tpl'] ) ) : 'new_order_alert',
			'meta_lang'  => isset( $_POST['meta_lang'] ) ? sanitize_text_field( wp_unslash( $_POST['meta_lang'] ) ) : 'en',
			'cust_tpl'   => isset( $_POST['cust_tpl'] ) ? sanitize_key( wp_unslash( $_POST['cust_tpl'] ) ) : '',
		);
		update_option( FLEURISTE_WA_OPTION, $new, false );
		$args = array( 'saved' => 1 );
		if ( ! empty( $_POST['test'] ) ) {
			$results = fleuriste_wa_send( __( '🌸 Test message from your shop. WhatsApp alerts are working!', 'fleuriste' ) );
			set_transient( 'fleuriste_wa_test_' . get_current_user_id(), $results, 120 );
			$args['tested'] = 1;
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php?page=fleuriste-whatsapp' ) ) );
		exit;
	}
);

/**
 * Settings page.
 */
function fleuriste_wa_page() {
	$s = fleuriste_wa_settings();
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$tested = ! empty( $_GET['tested'] ) ? get_transient( 'fleuriste_wa_test_' . get_current_user_id() ) : false;
	$saved  = ! empty( $_GET['saved'] );
	// phpcs:enable
	?>
	<div class="wrap fl-wa">
		<h1><?php esc_html_e( 'WhatsApp alerts', 'fleuriste' ); ?></h1>
		<?php if ( is_array( $tested ) ) : ?>
			<?php if ( ! $tested ) : ?>
				<div class="notice notice-warning"><p><?php esc_html_e( 'Add at least one phone number, then send the test again.', 'fleuriste' ); ?></p></div>
			<?php endif; ?>
			<?php foreach ( $tested as $r ) : ?>
				<div class="notice <?php echo $r[1] ? 'notice-success' : 'notice-error'; ?>"><p><strong><?php echo esc_html( $r[0] ); ?></strong>: <?php echo $r[1] ? esc_html__( 'test message sent – check WhatsApp.', 'fleuriste' ) : esc_html( __( 'could not send – ', 'fleuriste' ) . $r[2] ); ?></p></div>
			<?php endforeach; ?>
		<?php elseif ( $saved ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Saved.', 'fleuriste' ); ?></p></div>
		<?php endif; ?>

		<p><?php esc_html_e( 'Get a WhatsApp message for every new order: order number, delivery date and time, what was ordered, the recipient\'s area, the total and a link to the Deliveries board.', 'fleuriste' ); ?></p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="fleuriste_whatsapp">
			<?php wp_nonce_field( 'fleuriste_whatsapp' ); ?>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><?php esc_html_e( 'Alerts', 'fleuriste' ); ?></th>
					<td>
						<label><input type="checkbox" name="enabled" value="1" <?php checked( $s['enabled'] ); ?>> <?php esc_html_e( 'Send a WhatsApp message for each new order', 'fleuriste' ); ?></label><br>
						<label><input type="checkbox" name="on_hold" value="1" <?php checked( $s['on_hold'] ); ?>> <?php esc_html_e( 'Also for orders awaiting payment (bank transfer)', 'fleuriste' ); ?></label>
					</td></tr>
				<tr><th scope="row"><label for="fl-wa-rec"><?php esc_html_e( 'Phone numbers', 'fleuriste' ); ?></label></th>
					<td>
						<textarea id="fl-wa-rec" name="recipients" rows="4" class="large-text code" placeholder="+230 5xxx xxxx"><?php echo esc_textarea( preg_replace( '/\s*\|.*$/m', '', (string) $s['recipients'] ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One number per line (the owner and any staff). Numbers without a country code are treated as Mauritian (+230).', 'fleuriste' ); ?></p>
					</td></tr>
			</table>

			<div class="notice notice-info inline" style="max-width:900px"><p>
				<?php esc_html_e( 'WhatsApp messages sent automatically go through Meta\'s WhatsApp Business API, which charges a small fee per message (a card is needed in your Meta account).', 'fleuriste' ); ?>
				<?php
				printf(
					/* translators: %s: link */
					esc_html__( 'Free alternative: keep the %s open on a phone, tablet or computer – it chimes and shows a notification for every new order. WooCommerce also emails you each new order.', 'fleuriste' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=fleuriste-deliveries' ) ) . '">' . esc_html__( 'Orders & Deliveries board', 'fleuriste' ) . '</a>'
				);
				?>
			</p></div>

			<div class="fl-wa-box">
				<h2><?php esc_html_e( 'WhatsApp Business Cloud API', 'fleuriste' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><label for="fl-wa-pid"><?php esc_html_e( 'Phone number ID', 'fleuriste' ); ?></label></th>
						<td><input id="fl-wa-pid" type="text" name="meta_phone" value="<?php echo esc_attr( $s['meta_phone'] ); ?>" class="regular-text"></td></tr>
					<tr><th scope="row"><label for="fl-wa-tok"><?php esc_html_e( 'Permanent access token', 'fleuriste' ); ?></label></th>
						<td><input id="fl-wa-tok" type="password" name="meta_token" value="" class="regular-text" autocomplete="off" placeholder="<?php echo $s['meta_token'] ? esc_attr__( 'Saved – leave empty to keep', 'fleuriste' ) : ''; ?>"></td></tr>
					<tr><th scope="row"><label for="fl-wa-tpl"><?php esc_html_e( 'Template name and language', 'fleuriste' ); ?></label></th>
						<td><input id="fl-wa-tpl" type="text" name="meta_tpl" value="<?php echo esc_attr( $s['meta_tpl'] ); ?>"> <input type="text" name="meta_lang" value="<?php echo esc_attr( $s['meta_lang'] ); ?>" class="small-text">
						<p class="description"><?php esc_html_e( 'Create a "Utility" template in WhatsApp Manager with three variables, e.g.: "New order #{{1}} for {{2}}. Total {{3}}. Open your Deliveries board for details." Once Meta approves it, enter its name here.', 'fleuriste' ); ?></p></td></tr>
					<tr><th scope="row"><label for="fl-wa-ctpl"><?php esc_html_e( 'Buyer updates template', 'fleuriste' ); ?></label></th>
						<td><input id="fl-wa-ctpl" type="text" name="cust_tpl" value="<?php echo esc_attr( $s['cust_tpl'] ); ?>">
						<p class="description"><?php esc_html_e( 'Used to message buyers who ticked "Send me order updates on WhatsApp": automatically when they order, and from "Notify buyer" on the board. Four variables, e.g.: "Hi {{1}}, your order #{{2}}: {{3}}. Track it here: {{4}}". Leave empty to send buyer messages from your own WhatsApp instead.', 'fleuriste' ); ?></p></td></tr>
				</table>
			</div>

			<p class="submit">
				<button class="button button-primary"><?php esc_html_e( 'Save', 'fleuriste' ); ?></button>
				<button class="button" name="test" value="1"><?php esc_html_e( 'Save and send a test', 'fleuriste' ); ?></button>
			</p>
		</form>
	</div>
	<style>.fl-wa-box{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:4px 20px 12px;max-width:900px;margin:0 0 16px}.fl-wa-box h2{font-size:15px}</style>
	<?php
}
