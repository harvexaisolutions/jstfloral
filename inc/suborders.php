<?php
/**
 * Suborders: extra items for an order that was already placed.
 *
 * A suborder is a normal WooCommerce order (own items, payment and total) linked
 * to its parent order. It is created from Orders & Deliveries → "Add to order" with the
 * parent's buyer, recipient, delivery date and driver, and moves along with the
 * parent on the board (preparing → out for delivery → delivered).
 *
 * @package Fleuriste
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FLEURISTE_PARENT_META', '_fleuriste_parent_order' );
define( 'FLEURISTE_SUBS_META', '_fleuriste_suborders' );

/**
 * Link to the "Add to order" form.
 *
 * @param int $order_id Parent order ID.
 * @return string
 */
function fleuriste_suborder_url( $order_id ) {
	return admin_url( 'admin.php?page=fleuriste-new-order&parent=' . absint( $order_id ) );
}

/**
 * The parent of a suborder, or false.
 *
 * @param WC_Order $order Order.
 * @return WC_Order|false
 */
function fleuriste_parent_order( $order ) {
	$id = (int) $order->get_meta( FLEURISTE_PARENT_META );
	return $id ? wc_get_order( $id ) : false;
}

/**
 * Suborders of an order (most recent last).
 *
 * @param WC_Order $order Order.
 * @return WC_Order[]
 */
function fleuriste_suborders( $order ) {
	$list = array();
	foreach ( array_filter( array_map( 'absint', (array) $order->get_meta( FLEURISTE_SUBS_META ) ) ) as $id ) {
		$sub = wc_get_order( $id );
		if ( $sub && 'trash' !== $sub->get_status() ) {
			$list[] = $sub;
		}
	}
	return $list;
}

/**
 * Link a new suborder to its parent: meta on both, driver copied, notes.
 *
 * @param WC_Order $parent Parent order.
 * @param WC_Order $sub    New suborder.
 */
function fleuriste_link_suborder( $parent, $sub ) {
	$sub->update_meta_data( FLEURISTE_PARENT_META, $parent->get_id() );
	$driver = (string) $parent->get_meta( '_fleuriste_driver' );
	if ( $driver && ! $sub->get_meta( '_fleuriste_driver' ) ) {
		$sub->update_meta_data( '_fleuriste_driver', $driver );
	}
	$sub->save();
	$subs   = array_filter( array_map( 'absint', (array) $parent->get_meta( FLEURISTE_SUBS_META ) ) );
	$subs[] = $sub->get_id();
	$parent->update_meta_data( FLEURISTE_SUBS_META, array_values( array_unique( $subs ) ) );
	$parent->save();

	$items = array();
	foreach ( $sub->get_items() as $item ) {
		$items[] = $item->get_quantity() . ' × ' . $item->get_name();
	}
	$user = wp_get_current_user()->display_name;
	/* translators: 1: order number, 2: user */
	$sub->add_order_note( sprintf( __( 'Suborder: extra items for order #%1$s, added by %2$s.', 'fleuriste' ), $parent->get_order_number(), $user ) );
	$parent->add_order_note(
		sprintf(
			/* translators: 1: suborder number, 2: items, 3: total */
			__( 'Suborder #%1$s added: %2$s (%3$s).', 'fleuriste' ),
			$sub->get_order_number(),
			implode( ', ', $items ),
			html_entity_decode( wp_strip_all_tags( wc_price( $sub->get_total() ) ) )
		)
	);
}

/**
 * Message to the buyer about a suborder (email / WhatsApp).
 *
 * @param WC_Order $sub    Suborder.
 * @param WC_Order $parent Parent order.
 * @param bool     $link   Include the payment link?
 * @return string
 */
function fleuriste_suborder_message( $sub, $parent, $link ) {
	$fmt   = function ( $v ) {
		return html_entity_decode( wp_strip_all_tags( wc_price( $v ) ) );
	};
	$lines = array();
	foreach ( $sub->get_items() as $item ) {
		$lines[] = $item->get_quantity() . ' × ' . $item->get_name() . ' – ' . $fmt( (float) $item->get_total() + (float) $item->get_total_tax() );
	}
	foreach ( $sub->get_fees() as $fee ) {
		$lines[] = $fee->get_name() . ': ' . ( (float) $fee->get_total() < 0 ? '−' . $fmt( -(float) $fee->get_total() ) : $fmt( $fee->get_total() ) );
	}
	if ( $sub->get_items( 'shipping' ) ) {
		/* translators: %s: amount */
		$lines[] = sprintf( __( 'Delivery: %s', 'fleuriste' ), $fmt( $sub->get_shipping_total() ) );
	}
	$data = fleuriste_board_order_data( $sub );
	$when = $data['date'] ? date_i18n( 'l j F', strtotime( $data['date'] ) ) . ( $data['slot'] ? ' (' . $data['slot'] . ')' : '' ) : '';

	$text  = sprintf(
		/* translators: 1: first name, 2: order number */
		__( 'Hi %1$s, as agreed we have added to your order #%2$s:', 'fleuriste' ),
		$sub->get_billing_first_name(),
		$parent->get_order_number()
	) . "\n\n" . implode( "\n", $lines );
	/* translators: 1: total, 2: reference */
	$text .= "\n\n" . sprintf( __( 'Total: %1$s (reference #%2$s)', 'fleuriste' ), $fmt( $sub->get_total() ), $sub->get_order_number() );
	if ( $link ) {
		$text .= "\n" . __( 'Pay here:', 'fleuriste' ) . ' ' . $sub->get_checkout_payment_url();
	} elseif ( 'cod' === $sub->get_payment_method() ) {
		$text .= "\n" . __( 'To be paid on delivery.', 'fleuriste' );
	}
	if ( $when ) {
		$text .= "\n\n" . ( $sub->get_items( 'shipping' )
			/* translators: %s: date */
			? sprintf( __( 'Delivery: %s', 'fleuriste' ), $when )
			/* translators: 1: order number, 2: date */
			: sprintf( __( 'It will be delivered together with order #%1$s on %2$s.', 'fleuriste' ), $parent->get_order_number(), $when ) );
	}
	if ( function_exists( 'fleuriste_track_url' ) ) {
		$text .= "\n\n" . __( 'Track your order:', 'fleuriste' ) . ' ' . fleuriste_track_url( $parent );
	}
	return $text . "\n\n– " . get_bloginfo( 'name' );
}

/* -------------------------------------------------------------------------
 * Suborders follow their parent on the board
 * ---------------------------------------------------------------------- */

add_action(
	'woocommerce_order_status_changed',
	function ( $order_id, $from, $to, $order ) {
		$rank = array(
			'processing'       => 1,
			'preparing'        => 2,
			'out-for-delivery' => 3,
			'completed'        => 4,
		);
		if ( ! isset( $rank[ $to ] ) || $rank[ $to ] < 2 || ! $order instanceof WC_Order ) {
			return;
		}
		$subs = fleuriste_suborders( $order );
		if ( ! $subs ) {
			return;
		}
		// The buyer already hears about the main order; no second "completed" email for the extras.
		$quiet = '__return_false';
		add_filter( 'woocommerce_email_enabled_customer_completed_order', $quiet, 99 );
		foreach ( $subs as $sub ) {
			$s = $sub->get_status();
			// Unpaid suborders (payment link, bank transfer) stay where they are.
			if ( isset( $rank[ $s ] ) && $rank[ $s ] < $rank[ $to ] ) {
				/* translators: %s: order number */
				$sub->update_status( $to, sprintf( __( 'Moved along with order #%s.', 'fleuriste' ), $order->get_order_number() ) );
			}
		}
		remove_filter( 'woocommerce_email_enabled_customer_completed_order', $quiet, 99 );
	},
	20,
	4
);

/* -------------------------------------------------------------------------
 * Order screen and Orders list
 * ---------------------------------------------------------------------- */

add_action(
	'woocommerce_admin_order_data_after_order_details',
	function ( $order ) {
		if ( ! $order->get_id() || 'auto-draft' === $order->get_status() ) {
			return;
		}
		$parent = fleuriste_parent_order( $order );
		echo '<div class="form-field form-field-wide fl-subs-box" style="margin-top:12px">';
		if ( $parent ) {
			echo '<p><strong>' . esc_html__( 'Suborder of', 'fleuriste' ) . '</strong> <a href="' . esc_url( $parent->get_edit_order_url() ) . '">#' . esc_html( $parent->get_order_number() ) . '</a> (' . esc_html( wc_get_order_status_name( $parent->get_status() ) ) . ')</p>';
		}
		$subs = fleuriste_suborders( $order );
		if ( $subs ) {
			echo '<p><strong>' . esc_html__( 'Suborders', 'fleuriste' ) . '</strong><br>';
			foreach ( $subs as $sub ) {
				echo '<a href="' . esc_url( $sub->get_edit_order_url() ) . '">#' . esc_html( $sub->get_order_number() ) . '</a> · ' . wp_kses_post( wc_price( $sub->get_total() ) ) . ' · ' . esc_html( wc_get_order_status_name( $sub->get_status() ) ) . '<br>';
			}
			echo '</p>';
		}
		if ( ! $parent && ! $order->has_status( array( 'cancelled', 'refunded', 'failed' ) ) ) {
			echo '<p><a class="button" href="' . esc_url( fleuriste_suborder_url( $order->get_id() ) ) . '"><span class="dashicons dashicons-plus-alt2" style="vertical-align:text-bottom"></span> ' . esc_html__( 'Add to this order (suborder)', 'fleuriste' ) . '</a></p>';
		}
		echo '</div>';
	},
	20
);

add_filter(
	'woocommerce_admin_order_actions',
	function ( $actions, $order ) {
		if ( ! fleuriste_parent_order( $order ) && ! $order->has_status( array( 'cancelled', 'refunded', 'failed' ) ) ) {
			$actions['fleuriste_sub'] = array(
				'url'    => fleuriste_suborder_url( $order->get_id() ),
				'name'   => __( 'Add to order', 'fleuriste' ),
				'action' => 'fleuriste-sub',
			);
		}
		return $actions;
	},
	11,
	2
);

add_action(
	'admin_head',
	function () {
		echo '<style>.wc-action-button-fleuriste-sub::after{font-family:dashicons!important;content:"\f132"!important}</style>';
	}
);

// Suborders in the Orders list: "↳ #82" under the order number.
add_filter(
	'woocommerce_admin_order_buyer_name',
	function ( $name, $order ) {
		$parent = fleuriste_parent_order( $order );
		/* translators: %s: order number */
		return $parent ? $name . ' ' . sprintf( __( '(extra for #%s)', 'fleuriste' ), $parent->get_order_number() ) : $name;
	},
	10,
	2
);
