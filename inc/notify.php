<?php
/**
 * Buyer notifications.
 *
 * - "Track your order" page (private link with the order key) and a button in every
 *   customer email.
 * - Checkout option "Send me order updates on WhatsApp".
 * - Automatic WhatsApp confirmation when the order is placed (WhatsApp Business Cloud API).
 * - "Notify buyer" on the Deliveries board: editable message sent by email and/or WhatsApp.
 *   Without the Business API, WhatsApp opens on the shop's phone with the message ready to send.
 *
 * @package Fleuriste
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * Track your order
 * ---------------------------------------------------------------------- */

/**
 * Private tracking link for an order.
 *
 * @param WC_Order $order Order.
 * @return string
 */
function fleuriste_track_url( $order ) {
	return add_query_arg( 'track-order', rawurlencode( $order->get_order_key() ), home_url( '/' ) );
}

/**
 * Customer-facing status steps.
 *
 * @return array status => label
 */
function fleuriste_track_steps( $pickup = false ) {
	if ( $pickup ) {
		return array(
			'received'  => __( 'Order received', 'fleuriste' ),
			'preparing' => __( 'Being prepared', 'fleuriste' ),
			'out'       => __( 'Ready for pickup', 'fleuriste' ),
			'delivered' => __( 'Collected', 'fleuriste' ),
		);
	}
	return array(
		'received'  => __( 'Order received', 'fleuriste' ),
		'preparing' => __( 'Being prepared', 'fleuriste' ),
		'out'       => __( 'Out for delivery', 'fleuriste' ),
		'delivered' => __( 'Delivered', 'fleuriste' ),
	);
}

/**
 * Which step an order status is at (0-3), -1 for cancelled/failed/refunded.
 *
 * @param string $status Order status.
 * @return int
 */
function fleuriste_track_step( $status ) {
	$map = array(
		'pending'          => 0,
		'on-hold'          => 0,
		'processing'       => 0,
		'preparing'        => 1,
		'out-for-delivery' => 2,
		'completed'        => 3,
	);
	return isset( $map[ $status ] ) ? $map[ $status ] : -1;
}

add_action(
	'template_redirect',
	function () {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['track-order'] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$key      = wc_clean( wp_unslash( $_GET['track-order'] ) );
		$order_id = wc_get_order_id_by_order_key( $key );
		$order    = $order_id ? wc_get_order( $order_id ) : false;
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );
		if ( ! $order || ! hash_equals( $order->get_order_key(), $key ) ) {
			wp_die( esc_html__( 'We couldn\'t find this order. Please check the link in your email.', 'fleuriste' ), esc_html__( 'Track your order', 'fleuriste' ), array( 'response' => 404 ) );
		}
		$data  = function_exists( 'fleuriste_board_order_data' ) ? fleuriste_board_order_data( $order ) : array();
		$step  = fleuriste_track_step( $order->get_status() );
		$pickup = function_exists( 'fleuriste_order_is_pickup' ) && fleuriste_order_is_pickup( $order );
		$steps  = fleuriste_track_steps( $pickup );
		$shop  = get_bloginfo( 'name' );
		?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html( sprintf( /* translators: 1: order number, 2: shop */ __( 'Order #%1$s – %2$s', 'fleuriste' ), $order->get_order_number(), $shop ) ); ?></title>
<link rel="stylesheet" href="<?php echo esc_url( get_theme_file_uri( 'assets/css/track.css' ) ); ?>?v=<?php echo esc_attr( FLEURISTE_VERSION ); ?>">
<style>
	@font-face{font-family:"Cormorant Garamond";src:url("<?php echo esc_url( get_theme_file_uri( 'assets/fonts/cormorant-garamond.woff' ) ); ?>") format("woff");font-weight:400 700;font-display:swap}
	@font-face{font-family:Jost;src:url("<?php echo esc_url( get_theme_file_uri( 'assets/fonts/jost.woff' ) ); ?>") format("woff");font-weight:300 700;font-display:swap}
</style>
</head>
<body>
	<header class="t-head"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="t-logo"><?php echo esc_html( $shop ); ?></a></header>
	<main class="t-main">
		<p class="t-eyebrow"><?php esc_html_e( 'Track your order', 'fleuriste' ); ?></p>
		<h1><?php echo esc_html( sprintf( /* translators: %s: order number */ __( 'Order #%s', 'fleuriste' ), $order->get_order_number() ) ); ?></h1>

		<?php if ( $step < 0 ) : ?>
			<div class="t-alert"><?php echo esc_html( sprintf( /* translators: %s: status */ __( 'This order is %s. Please contact us if you have any questions.', 'fleuriste' ), strtolower( wc_get_order_status_name( $order->get_status() ) ) ) ); ?></div>
		<?php else : ?>
			<ol class="t-steps">
				<?php
				$i = 0;
				foreach ( $steps as $label ) :
					$cls = $i < $step ? 'done' : ( $i === $step ? 'now' : '' );
					?>
					<li class="<?php echo esc_attr( $cls ); ?>"><span class="dot"></span><span class="lbl"><?php echo esc_html( $label ); ?></span></li>
					<?php
					$i++;
				endforeach;
				?>
			</ol>
			<?php if ( in_array( $order->get_status(), array( 'pending', 'on-hold' ), true ) ) : ?>
				<div class="t-alert"><?php esc_html_e( 'We are waiting for your payment. We will start preparing your order as soon as it arrives.', 'fleuriste' ); ?></div>
			<?php endif; ?>
		<?php endif; ?>

		<section class="t-card">
			<?php if ( ! empty( $data['date'] ) ) : ?>
				<div class="t-row"><span><?php echo $pickup ? esc_html__( 'Pickup', 'fleuriste' ) : esc_html__( 'Delivery', 'fleuriste' ); ?></span><strong><?php echo esc_html( date_i18n( 'l j F', strtotime( $data['date'] ) ) . ( $data['slot'] ? ' · ' . $data['slot'] : '' ) ); ?></strong></div>
			<?php endif; ?>
			<?php if ( $pickup ) : ?>
				<div class="t-row"><span><?php esc_html_e( 'Where', 'fleuriste' ); ?></span><strong><?php echo esc_html( function_exists( 'fleuriste_dl_pickup_address' ) && fleuriste_dl_pickup_address() ? fleuriste_dl_pickup_address() : $shop ); ?></strong></div>
			<?php else : ?>
				<div class="t-row"><span><?php esc_html_e( 'For', 'fleuriste' ); ?></span><strong><?php echo esc_html( trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() ) ); ?><?php echo $order->get_shipping_city() ? ', ' . esc_html( $order->get_shipping_city() ) : ''; ?></strong></div>
			<?php endif; ?>
			<div class="t-row"><span><?php esc_html_e( 'Items', 'fleuriste' ); ?></span>
				<strong>
				<?php foreach ( $order->get_items() as $item ) : ?>
					<span class="t-item"><?php echo (int) $item->get_quantity(); ?> × <?php echo esc_html( $item->get_name() ); ?></span>
				<?php endforeach; ?>
				</strong>
			</div>
			<div class="t-row"><span><?php esc_html_e( 'Total', 'fleuriste' ); ?></span><strong><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?> <small><?php echo esc_html( $order->get_payment_method_title() ); ?></small></strong></div>
			<?php $docs = function_exists( 'fleuriste_doc_customer_links' ) ? fleuriste_doc_customer_links( $order ) : ''; ?>
			<?php if ( $docs ) : ?>
				<div class="t-row"><span><?php esc_html_e( 'Documents', 'fleuriste' ); ?></span><strong><?php echo wp_kses_post( $docs ); ?></strong></div>
			<?php endif; ?>
		</section>

		<p class="t-help"><?php esc_html_e( 'Questions about your order? Reply to your confirmation email or contact us.', 'fleuriste' ); ?></p>
		<p><a class="t-btn" href="<?php echo esc_url( function_exists( 'fleuriste_shop_url' ) ? fleuriste_shop_url() : home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to the shop', 'fleuriste' ); ?></a></p>
	</main>
</body>
</html>
		<?php
		exit;
	}
);

// "Track your order" button at the top of every customer email.
add_action(
	'woocommerce_email_before_order_table',
	function ( $order, $sent_to_admin, $plain_text ) {
		if ( $sent_to_admin || ! $order instanceof WC_Order ) {
			return;
		}
		$url = fleuriste_track_url( $order );
		if ( $plain_text ) {
			echo esc_html__( 'Track your order:', 'fleuriste' ) . ' ' . esc_url_raw( $url ) . "\n\n";
			return;
		}
		printf(
			'<p style="margin:0 0 24px;"><a href="%1$s" style="display:inline-block;background:#9E4F63;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:999px;font-weight:600;">%2$s</a></p>',
			esc_url( $url ),
			esc_html__( 'Track your order', 'fleuriste' )
		);
	},
	5,
	3
);

// Link on the "order received" page and in My Account → Orders.
add_action(
	'woocommerce_thankyou',
	function ( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order ) {
			printf( '<p class="fl-track-link"><a class="button" href="%1$s">%2$s</a></p>', esc_url( fleuriste_track_url( $order ) ), esc_html__( 'Track your order', 'fleuriste' ) );
		}
	},
	5
);
add_filter(
	'woocommerce_my_account_my_orders_actions',
	function ( $actions, $order ) {
		$actions['fleuriste-track'] = array(
			'url'  => fleuriste_track_url( $order ),
			'name' => __( 'Track', 'fleuriste' ),
		);
		return $actions;
	},
	10,
	2
);

/* -------------------------------------------------------------------------
 * Checkout: "Send me order updates on WhatsApp"
 * ---------------------------------------------------------------------- */

add_action(
	'woocommerce_init',
	function () {
		if ( function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			woocommerce_register_additional_checkout_field(
				array(
					'id'       => 'fleuriste/whatsapp-updates',
					'label'    => __( 'Send me order updates on WhatsApp (to the phone number in my billing details)', 'fleuriste' ),
					'location' => 'contact',
					'type'     => 'checkbox',
				)
			);
		}
	}
);

/**
 * Did the buyer ask for WhatsApp updates?
 *
 * @param WC_Order $order Order.
 * @return bool
 */
function fleuriste_buyer_wants_whatsapp( $order ) {
	return (bool) $order->get_meta( '_wc_other/fleuriste/whatsapp-updates' );
}

/**
 * Buyer's phone in international format (Mauritius by default), digits only.
 *
 * @param WC_Order $order Order.
 * @return string
 */
function fleuriste_buyer_phone( $order ) {
	$phone = preg_replace( '/[^\d+]/', '', (string) $order->get_billing_phone() );
	if ( '' === $phone ) {
		return '';
	}
	if ( 0 === strpos( $phone, '+' ) ) {
		return substr( $phone, 1 );
	}
	if ( 0 === strpos( $phone, '00' ) ) {
		return substr( $phone, 2 );
	}
	return '230' . ltrim( $phone, '0' );
}

/* -------------------------------------------------------------------------
 * Messages
 * ---------------------------------------------------------------------- */

/**
 * Default message for the buyer, based on the order status.
 *
 * @param WC_Order $order Order.
 * @return string
 */
function fleuriste_buyer_message( $order ) {
	$name   = $order->get_billing_first_name();
	$num    = $order->get_order_number();
	$shop   = get_bloginfo( 'name' );
	$data   = function_exists( 'fleuriste_board_order_data' ) ? fleuriste_board_order_data( $order ) : array();
	$when   = ! empty( $data['date'] ) ? date_i18n( 'l j F', strtotime( $data['date'] ) ) . ( $data['slot'] ? ' (' . $data['slot'] . ')' : '' ) : '';
	$to     = trim( $order->get_shipping_first_name() );
	$status = $order->get_status();
	$pickup = function_exists( 'fleuriste_order_is_pickup' ) && fleuriste_order_is_pickup( $order );
	if ( $pickup && in_array( $status, array( 'preparing', 'out-for-delivery', 'completed' ), true ) ) {
		$where = function_exists( 'fleuriste_dl_pickup_address' ) ? fleuriste_dl_pickup_address() : '';
		switch ( $status ) {
			case 'preparing':
				/* translators: 1: name, 2: order number */
				$text = sprintf( __( 'Hi %1$s, our florists are now preparing your order #%2$s.', 'fleuriste' ), $name, $num );
				break;
			case 'out-for-delivery':
				/* translators: 1: name, 2: order number */
				$text = sprintf( __( 'Hi %1$s, good news – your order #%2$s is ready for pickup!', 'fleuriste' ), $name, $num ) . ( $where ? "\n" . __( 'Pick up at:', 'fleuriste' ) . ' ' . $where : '' );
				break;
			default:
				/* translators: 1: name, 2: order number */
				$text = sprintf( __( 'Hi %1$s, thank you for collecting your order #%2$s. We hope you love it!', 'fleuriste' ), $name, $num );
		}
		$status = 'fleuriste-pickup-done';
	}
	switch ( $status ) {
		case 'fleuriste-pickup-done':
			break;
		case 'preparing':
			/* translators: 1: name, 2: order number, 3: recipient */
			$text = sprintf( __( 'Hi %1$s, our florists are now preparing your order #%2$s for %3$s.', 'fleuriste' ), $name, $num, $to );
			break;
		case 'out-for-delivery':
			/* translators: 1: name, 2: order number, 3: recipient */
			$text = sprintf( __( 'Hi %1$s, good news – your order #%2$s is on its way to %3$s!', 'fleuriste' ), $name, $num, $to );
			break;
		case 'completed':
			/* translators: 1: name, 2: order number, 3: recipient */
			$text = sprintf( __( 'Hi %1$s, your order #%2$s has been delivered to %3$s. Thank you for choosing us!', 'fleuriste' ), $name, $num, $to );
			break;
		case 'on-hold':
		case 'pending':
			/* translators: 1: name, 2: order number */
			$text = sprintf( __( 'Hi %1$s, thank you for your order #%2$s. We will start preparing it as soon as your payment arrives.', 'fleuriste' ), $name, $num );
			break;
		case 'cancelled':
			$reason = (string) $order->get_meta( '_fleuriste_cancel_reason' );
			$extra  = (string) $order->get_meta( '_fleuriste_cancel_note' );
			/* translators: 1: name, 2: order number */
			$text = sprintf( __( 'Hi %1$s, we are sorry – your order #%2$s has been cancelled.', 'fleuriste' ), $name, $num );
			if ( $reason && __( 'Other', 'fleuriste' ) !== $reason ) {
				/* translators: %s: reason */
				$text .= ' ' . sprintf( __( 'Reason: %s.', 'fleuriste' ), $reason );
			}
			if ( $extra ) {
				$text .= "\n\n" . $extra;
			}
			if ( function_exists( 'fleuriste_paid_online' ) && fleuriste_paid_online( $order ) ) {
				$text .= "\n\n" . __( 'Your payment will be refunded to your original payment method.', 'fleuriste' );
			}
			$text .= "\n\n" . __( 'Please contact us if you have any questions.', 'fleuriste' );
			break;
		default:
			/* translators: 1: name, 2: order number, 3: delivery date */
			$text = $when
				? ( $pickup
					/* translators: 1: name, 2: order number, 3: pickup date */
					? sprintf( __( 'Hi %1$s, thank you for your order #%2$s! It will be ready for pickup on %3$s.', 'fleuriste' ), $name, $num, $when )
					: sprintf( __( 'Hi %1$s, thank you for your order #%2$s! It is confirmed for delivery on %3$s.', 'fleuriste' ), $name, $num, $when ) )
				/* translators: 1: name, 2: order number */
				: sprintf( __( 'Hi %1$s, thank you for your order #%2$s! It is confirmed.', 'fleuriste' ), $name, $num );
	}
	$text .= "\n\n" . __( 'Track your order:', 'fleuriste' ) . ' ' . fleuriste_track_url( $order ) . "\n\n– " . $shop;
	return (string) apply_filters( 'fleuriste_buyer_message', $text, $order );
}

/**
 * Short status text for WhatsApp templates.
 *
 * @param WC_Order $order Order.
 * @return string
 */
function fleuriste_buyer_status_text( $order ) {
	$steps = fleuriste_track_steps();
	$step  = fleuriste_track_step( $order->get_status() );
	return $step >= 0 ? array_values( $steps )[ $step ] : wc_get_order_status_name( $order->get_status() );
}

/**
 * Email the buyer.
 *
 * @param WC_Order $order Order.
 * @param string   $text  Message.
 * @return bool
 */
function fleuriste_email_buyer( $order, $text ) {
	$to = $order->get_billing_email();
	if ( ! $to ) {
		return false;
	}
	$mailer  = WC()->mailer();
	/* translators: 1: shop, 2: order number */
	$subject = sprintf( __( '[%1$s] Update on your order #%2$s', 'fleuriste' ), get_bloginfo( 'name' ), $order->get_order_number() );
	$url     = fleuriste_track_url( $order );
	$body    = wpautop( esc_html( str_replace( __( 'Track your order:', 'fleuriste' ) . ' ' . $url, '', $text ) ) );
	$body   .= '<p style="margin:24px 0;"><a href="' . esc_url( $url ) . '" style="display:inline-block;background:#9E4F63;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:999px;font-weight:600;">' . esc_html__( 'Track your order', 'fleuriste' ) . '</a></p>';
	/* translators: %s: status */
	$heading = sprintf( __( 'Your order: %s', 'fleuriste' ), fleuriste_buyer_status_text( $order ) );
	return (bool) $mailer->send( $to, $subject, $mailer->wrap_message( $heading, $body ) );
}

/**
 * WhatsApp the buyer through the Business Cloud API (template message).
 * Returns [ok, detail]. Without the API configured returns [false, 'no-api'].
 *
 * @param WC_Order $order Order.
 * @return array
 */
function fleuriste_whatsapp_buyer_api( $order ) {
	$s = function_exists( 'fleuriste_wa_settings' ) ? fleuriste_wa_settings() : array();
	if ( empty( $s['provider'] ) || 'meta' !== $s['provider'] || empty( $s['meta_phone'] ) || empty( $s['meta_token'] ) || empty( $s['cust_tpl'] ) ) {
		return array( false, 'no-api' );
	}
	$phone = fleuriste_buyer_phone( $order );
	if ( ! $phone ) {
		return array( false, __( 'no phone number', 'fleuriste' ) );
	}
	$params = array(
		$order->get_billing_first_name() ? $order->get_billing_first_name() : __( 'there', 'fleuriste' ),
		$order->get_order_number(),
		fleuriste_buyer_status_text( $order ),
		fleuriste_track_url( $order ),
	);
	$res    = wp_remote_post(
		'https://graph.facebook.com/v21.0/' . rawurlencode( $s['meta_phone'] ) . '/messages',
		array(
			'timeout' => 15,
			'headers' => array(
				'Authorization' => 'Bearer ' . $s['meta_token'],
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode(
				array(
					'messaging_product' => 'whatsapp',
					'to'                => $phone,
					'type'              => 'template',
					'template'          => array(
						'name'       => $s['cust_tpl'],
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
				)
			),
		)
	);
	if ( is_wp_error( $res ) ) {
		return array( false, $res->get_error_message() );
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	return array( $code >= 200 && $code < 300, 'HTTP ' . $code );
}

/**
 * "Click to chat" link that opens WhatsApp with the message ready to send.
 *
 * @param WC_Order $order Order.
 * @param string   $text  Message.
 * @return string
 */
function fleuriste_whatsapp_buyer_link( $order, $text ) {
	$phone = fleuriste_buyer_phone( $order );
	return $phone ? 'https://wa.me/' . $phone . '?text=' . rawurlencode( $text ) : '';
}

/* -------------------------------------------------------------------------
 * Automatic WhatsApp confirmation when the order is placed (Business API only)
 * ---------------------------------------------------------------------- */

/**
 * Queue the buyer confirmation.
 *
 * @param int $order_id Order ID.
 */
function fleuriste_buyer_confirm_queue( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order || $order->get_meta( '_fleuriste_buyer_wa_sent' ) || ! fleuriste_buyer_wants_whatsapp( $order ) ) {
		return;
	}
	$order->update_meta_data( '_fleuriste_buyer_wa_sent', time() );
	$order->save_meta_data();
	if ( function_exists( 'as_enqueue_async_action' ) ) {
		as_enqueue_async_action( 'fleuriste_buyer_confirm', array( (int) $order_id ), 'fleuriste' );
	} else {
		do_action( 'fleuriste_buyer_confirm', (int) $order_id );
	}
}
add_action( 'woocommerce_order_status_processing', 'fleuriste_buyer_confirm_queue', 25 );
add_action( 'woocommerce_order_status_on-hold', 'fleuriste_buyer_confirm_queue', 25 );

add_action(
	'fleuriste_buyer_confirm',
	function ( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$res = fleuriste_whatsapp_buyer_api( $order );
		if ( 'no-api' === $res[1] ) {
			$order->add_order_note( __( 'The buyer asked for WhatsApp updates. Automatic WhatsApp messages need the WhatsApp Business Cloud API (Shop settings → WhatsApp alerts); use "Notify buyer" on the Deliveries board to send one from your phone.', 'fleuriste' ) );
			return;
		}
		$order->add_order_note( $res[0] ? __( 'WhatsApp order confirmation sent to the buyer.', 'fleuriste' ) : __( 'WhatsApp order confirmation to the buyer failed: ', 'fleuriste' ) . $res[1] );
	}
);

/* -------------------------------------------------------------------------
 * "Notify buyer" from the Deliveries board
 * ---------------------------------------------------------------------- */

/**
 * The notify panel for a board card.
 *
 * @param WC_Order $order Order.
 * @return string
 */
function fleuriste_notify_panel( $order ) {
	$wants = fleuriste_buyer_wants_whatsapp( $order );
	$phone = fleuriste_buyer_phone( $order );
	$last  = $order->get_meta( '_fleuriste_notified' );
	ob_start();
	?>
	<details class="fl-notify">
		<summary class="button"><span class="dashicons dashicons-email-alt" aria-hidden="true"></span> <?php esc_html_e( 'Notify buyer', 'fleuriste' ); ?><?php echo $wants ? ' <span class="fl-wa-pill">WhatsApp ✓</span>' : ''; ?></summary>
		<div class="fl-notify__box">
			<textarea class="fl-notify__text" rows="5"><?php echo esc_textarea( fleuriste_buyer_message( $order ) ); ?></textarea>
			<div class="fl-notify__opts">
				<label><input type="checkbox" class="fl-notify__email" <?php checked( (bool) $order->get_billing_email() ); ?> <?php disabled( ! $order->get_billing_email() ); ?>> <?php echo esc_html( sprintf( /* translators: %s: email */ __( 'Email (%s)', 'fleuriste' ), $order->get_billing_email() ? $order->get_billing_email() : __( 'no email', 'fleuriste' ) ) ); ?></label>
				<label><input type="checkbox" class="fl-notify__wa" <?php checked( $wants && $phone ); ?> <?php disabled( ! $phone ); ?>> <?php echo esc_html( sprintf( /* translators: %s: phone */ __( 'WhatsApp (%s)', 'fleuriste' ), $phone ? '+' . $phone : __( 'no phone', 'fleuriste' ) ) ); ?><?php echo $wants ? '' : ' <em>' . esc_html__( '– buyer didn\'t ask for WhatsApp', 'fleuriste' ) . '</em>'; ?></label>
				<button type="button" class="button button-primary fl-notify__send"><?php esc_html_e( 'Send', 'fleuriste' ); ?></button>
			</div>
			<?php if ( $last ) : ?>
				<p class="fl-notify__last"><?php echo esc_html( sprintf( /* translators: %s: date */ __( 'Last notified: %s', 'fleuriste' ), $last ) ); ?></p>
			<?php endif; ?>
		</div>
	</details>
	<?php
	return ob_get_clean();
}

add_action(
	'wp_ajax_fleuriste_notify',
	function () {
		check_ajax_referer( 'fleuriste_board', 'nonce' );
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'fleuriste' ) ), 403 );
		}
		$order = isset( $_POST['order'] ) ? wc_get_order( absint( $_POST['order'] ) ) : false;
		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'Order not found.', 'fleuriste' ) ), 404 );
		}
		$text  = isset( $_POST['text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['text'] ) ) : '';
		$text  = '' !== trim( $text ) ? $text : fleuriste_buyer_message( $order );
		$sent  = array();
		$link  = '';
		$error = array();
		if ( ! empty( $_POST['email'] ) ) {
			if ( fleuriste_email_buyer( $order, $text ) ) {
				$sent[] = __( 'email', 'fleuriste' );
			} else {
				$error[] = __( 'the email could not be sent', 'fleuriste' );
			}
		}
		if ( ! empty( $_POST['whatsapp'] ) ) {
			$res = fleuriste_whatsapp_buyer_api( $order );
			if ( $res[0] ) {
				$sent[] = __( 'WhatsApp', 'fleuriste' );
			} else {
				// No Business API (or it failed): open WhatsApp with the message ready.
				$link = fleuriste_whatsapp_buyer_link( $order, $text );
				if ( 'no-api' !== $res[1] ) {
					$error[] = __( 'WhatsApp API: ', 'fleuriste' ) . $res[1];
				}
			}
		}
		$stamp = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
		if ( $sent || $link ) {
			$order->update_meta_data( '_fleuriste_notified', $stamp );
			$how = implode( ' + ', array_merge( $sent, $link ? array( __( 'WhatsApp (sent from the shop\'s phone)', 'fleuriste' ) ) : array() ) );
			/* translators: 1: channels, 2: message */
			$order->add_order_note( sprintf( __( 'Buyer notified by %1$s: %2$s', 'fleuriste' ), $how, $text ) );
			$order->save();
		}
		wp_send_json_success(
			array(
				'link'    => $link,
				'message' => $sent
					/* translators: 1: order number, 2: channels */
					? sprintf( __( 'Order #%1$s: buyer notified by %2$s.', 'fleuriste' ), $order->get_order_number(), implode( ' + ', $sent ) ) . ( $link ? ' ' . __( 'WhatsApp is opening with the message – press send.', 'fleuriste' ) : '' ) . ( $error ? ' ' . implode( '; ', $error ) : '' )
					: ( $link ? __( 'WhatsApp is opening with the message – press send.', 'fleuriste' ) : ( $error ? implode( '; ', $error ) : __( 'Choose email or WhatsApp.', 'fleuriste' ) ) ),
			)
		);
	}
);
