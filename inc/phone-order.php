<?php
/**
 * Quick order on behalf of a customer (Orders & Deliveries → New order).
 *
 * One page for phone, WhatsApp and walk-in orders: buyer (with look-up of returning
 * customers by phone or email), recipient, products with florist options, delivery
 * date and time, card message, payment (cash on delivery, bank transfer, paid in shop,
 * or an online payment link), driver and buyer confirmation.
 *
 * @package Fleuriste
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'fleuriste-deliveries', __( 'New order', 'fleuriste' ), __( '+ New order', 'fleuriste' ), 'edit_shop_orders', 'fleuriste-new-order', 'fleuriste_phone_order_page', 1 );
	},
	25
);

/**
 * Products that can be sold, for the order form.
 *
 * @return array[] id, name, price, custom, cat
 */
function fleuriste_phone_order_products() {
	$list     = array();
	$products = wc_get_products(
		array(
			'status'  => 'publish',
			'limit'   => 500,
			'orderby' => 'title',
			'order'   => 'ASC',
			'type'    => array( 'simple', 'variable' ),
		)
	);
	foreach ( $products as $product ) {
		$terms = get_the_terms( $product->get_id(), 'product_cat' );
		$cat   = ( $terms && ! is_wp_error( $terms ) ) ? reset( $terms )->name : __( 'Other', 'fleuriste' );
		$items = $product->is_type( 'variable' ) ? array_filter( array_map( 'wc_get_product', $product->get_children() ) ) : array( $product );
		foreach ( $items as $item ) {
			if ( ! $item->is_purchasable() ) {
				continue;
			}
			$name = $item->is_type( 'variation' ) ? $product->get_name() . ' – ' . wc_get_formatted_variation( $item, true, false ) : $item->get_name();
			$list[] = array(
				'id'     => $item->get_id(),
				'name'   => wp_strip_all_tags( $name ),
				'price'  => (float) wc_get_price_to_display( $item ),
				'custom' => function_exists( 'fleuriste_fo_customisable' ) && fleuriste_fo_customisable( $item ),
				'stock'  => $item->is_in_stock(),
				'cat'    => $cat,
			);
		}
	}
	return $list;
}

/**
 * Statuses in which an order can still be changed from the board.
 *
 * @return string[]
 */
function fleuriste_po_editable_statuses() {
	return array( 'pending', 'on-hold', 'processing', 'preparing', 'out-for-delivery' );
}

/**
 * Let WooCommerce's own order screen edit items in our working statuses too.
 */
add_filter(
	'wc_order_is_editable',
	function ( $editable, $order ) {
		return $editable || in_array( $order->get_status(), array( 'processing', 'preparing', 'out-for-delivery' ), true );
	},
	10,
	2
);

/**
 * Find a choice index from a stored label such as "Deluxe (+Rs 300)".
 *
 * @param string $key   Setting key.
 * @param string $value Stored value.
 * @return int|null
 */
function fleuriste_po_choice_index( $key, $value ) {
	$label = trim( preg_replace( '/\s*\(\+[^)]*\)\s*$/u', '', (string) $value ) );
	foreach ( fleuriste_fo_choices( $key ) as $i => $choice ) {
		if ( $choice['label'] === $label ) {
			return $i;
		}
	}
	return null;
}

/**
 * Current values of an order, to fill in the form when editing.
 *
 * @param WC_Order $order Order.
 * @return array
 */
function fleuriste_po_prefill( $order ) {
	$k_size  = __( 'Bouquet size', 'fleuriste' );
	$k_vase  = __( 'Vase', 'fleuriste' );
	$k_extra = __( 'Extras', 'fleuriste' );
	$k_time  = __( 'Delivery time', 'fleuriste' );
	$k_msg   = __( 'Card message', 'fleuriste' );
	$items   = array();
	$date    = '';
	$slot    = null;
	$message = '';
	$custom  = array();
	foreach ( $order->get_items() as $item ) {
		$product_id = $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id();
		$date       = $date ? $date : (string) $item->get_meta( '_fleuriste_delivery_date' );
		$slot       = null !== $slot ? $slot : fleuriste_po_choice_index( 'fleuriste_fo_slots', $item->get_meta( $k_time ) );
		$message    = '' !== $message ? $message : (string) $item->get_meta( $k_msg );
		if ( ! $product_id || $item->get_meta( '_fleuriste_custom_item' ) ) {
			$qty      = max( 1, (int) $item->get_quantity() );
			$custom[] = array(
				'name'  => $item->get_name(),
				'price' => round( (float) $item->get_subtotal() / $qty, 2 ),
				'qty'   => $qty,
			);
			continue;
		}
		$extras     = array();
		foreach ( array_filter( array_map( 'trim', explode( ',', (string) $item->get_meta( $k_extra ) ) ) ) as $label ) {
			$i = fleuriste_po_choice_index( 'fleuriste_fo_extras', $label );
			if ( null !== $i ) {
				$extras[] = $i;
			}
		}
		$items[] = array(
			'product' => $product_id,
			'qty'     => $item->get_quantity(),
			'size'    => fleuriste_po_choice_index( 'fleuriste_fo_sizes', $item->get_meta( $k_size ) ),
			'vase'    => fleuriste_po_choice_index( 'fleuriste_fo_vases', $item->get_meta( $k_vase ) ),
			'extras'  => $extras,
		);
	}
	$discount = array(
		'type'  => 'amount',
		'value' => '',
	);
	foreach ( $order->get_fees() as $fee_item ) {
		if ( $fee_item->get_meta( '_fleuriste_discount_type' ) ) {
			$discount = array(
				'type'  => (string) $fee_item->get_meta( '_fleuriste_discount_type' ),
				'value' => (float) $fee_item->get_meta( '_fleuriste_discount_value' ),
			);
		}
	}
	$parent = (int) $order->get_meta( '_fleuriste_parent_order' );
	$same = ! $order->get_shipping_address_1() && $order->get_shipping_first_name() === $order->get_billing_first_name();
	$pay  = $order->get_payment_method();
	$pay  = 'fleuriste_instore' === $pay ? 'instore' : ( in_array( $pay, array( 'cod', 'bacs' ), true ) ? $pay : ( 'pending' === $order->get_status() ? 'link' : 'online' ) );
	return array(
		'id'        => $order->get_id(),
		'number'    => $order->get_order_number(),
		'b_first'   => $order->get_billing_first_name(),
		'b_last'    => $order->get_billing_last_name(),
		'b_phone'   => $order->get_billing_phone(),
		'b_email'   => $order->get_billing_email(),
		'b_wa'      => (bool) $order->get_meta( '_wc_other/fleuriste/whatsapp-updates' ),
		'same'      => $same,
		'r_first'   => $order->get_shipping_first_name(),
		'r_last'    => $order->get_shipping_last_name(),
		'r_address' => $order->get_shipping_address_1(),
		'r_city'    => $order->get_shipping_city(),
		'r_state'   => $order->get_shipping_state(),
		'r_phone'   => $order->get_shipping_phone(),
		'anon'      => (bool) $order->get_meta( '_wc_other/fleuriste/send-anonymously' ),
		'items'     => $items,
		'custom'    => $custom,
		'discount'  => $discount,
		'delivery'  => (bool) $order->get_items( 'shipping' ) && ! ( function_exists( 'fleuriste_order_is_pickup' ) && fleuriste_order_is_pickup( $order ) ),
		'parent'    => $parent,
		'date'      => $date,
		'slot'      => $slot,
		'message'   => $message,
		'fee'       => (float) $order->get_shipping_total(),
		'driver'    => (string) $order->get_meta( '_fleuriste_driver' ),
		'pay'       => $pay,
		'paid_as'   => $order->get_payment_method_title(),
	);
}

/**
 * Default delivery fee: the first flat rate found in the shipping zones.
 *
 * @return float
 */
function fleuriste_phone_order_default_fee() {
	if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
		return 0.0;
	}
	foreach ( WC_Shipping_Zones::get_zones() as $zone ) {
		foreach ( $zone['shipping_methods'] as $method ) {
			if ( 'flat_rate' === $method->id && 'yes' === $method->enabled ) {
				return (float) wc_format_decimal( $method->get_option( 'cost' ) );
			}
		}
	}
	return 0.0;
}

/**
 * The form.
 */
function fleuriste_phone_order_page() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$edit_order = isset( $_GET['order'] ) ? wc_get_order( absint( $_GET['order'] ) ) : false;
	if ( $edit_order && ! in_array( $edit_order->get_status(), fleuriste_po_editable_statuses(), true ) ) {
		echo '<div class="wrap"><h1>' . esc_html__( 'Edit order', 'fleuriste' ) . '</h1><div class="notice notice-warning"><p>' . esc_html__( 'Delivered and cancelled orders can\'t be changed here. Reopen the order first, or use the order screen.', 'fleuriste' ) . '</p></div></div>';
		return;
	}
	$prefill  = $edit_order ? fleuriste_po_prefill( $edit_order ) : null;
	// Duplicate: start a new order from a copy of an existing one.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$copy_order = ( ! $edit_order && isset( $_GET['copy'] ) ) ? wc_get_order( absint( $_GET['copy'] ) ) : false;
	$copy       = null;
	if ( $copy_order && is_a( $copy_order, 'WC_Order' ) && ! is_a( $copy_order, 'WC_Order_Refund' ) ) {
		$copy = fleuriste_po_prefill( $copy_order );
		// A new order is not paid yet and has no driver yet.
		$copy['pay']    = in_array( $copy['pay'], array( 'cod', 'bacs', 'link' ), true ) ? $copy['pay'] : 'cod';
		$copy['driver'] = '';
		$copy['copy']   = true;
	}
	// Suborder: extra items for an existing order (same buyer, recipient and delivery).
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$parent_order = ( ! $edit_order && ! $copy && isset( $_GET['parent'] ) ) ? wc_get_order( absint( $_GET['parent'] ) ) : false;
	$sub          = null;
	if ( $parent_order && is_a( $parent_order, 'WC_Order' ) && ! is_a( $parent_order, 'WC_Order_Refund' ) ) {
		if ( $parent_order->has_status( array( 'cancelled', 'refunded', 'failed' ) ) ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Add to an order', 'fleuriste' ) . '</h1><div class="notice notice-warning"><p>' . esc_html__( 'This order is cancelled. Reopen it first, or take a new order.', 'fleuriste' ) . '</p></div></div>';
			return;
		}
		$sub             = fleuriste_po_prefill( $parent_order );
		$sub['sub']      = true;
		$sub['items']    = array();
		$sub['custom']   = array();
		$sub['message']  = '';
		$sub['discount'] = array(
			'type'  => 'amount',
			'value' => '',
		);
		// Extras normally go out with the original delivery; charge delivery only if it's already delivered.
		$sub['delivery'] = $parent_order->has_status( 'completed' );
		$sub['fee']      = fleuriste_phone_order_default_fee();
		$sub['pay']      = in_array( $sub['pay'], array( 'cod', 'bacs' ), true ) ? $sub['pay'] : 'link';
		$sub['parent']   = $parent_order->get_id();
	}
	$pay_now        = $prefill ? $prefill['pay'] : ( $copy ? $copy['pay'] : ( $sub ? $sub['pay'] : 'cod' ) );
	$parent_of_edit = ( $prefill && $prefill['parent'] ) ? wc_get_order( $prefill['parent'] ) : false;
	$products = fleuriste_phone_order_products();
	$sizes    = function_exists( 'fleuriste_fo_choices' ) ? fleuriste_fo_choices( 'fleuriste_fo_sizes' ) : array();
	$vases    = function_exists( 'fleuriste_fo_choices' ) ? fleuriste_fo_choices( 'fleuriste_fo_vases' ) : array();
	$extras   = function_exists( 'fleuriste_fo_choices' ) ? fleuriste_fo_choices( 'fleuriste_fo_extras' ) : array();
	$slots    = function_exists( 'fleuriste_fo_choices' ) ? fleuriste_fo_choices( 'fleuriste_fo_slots' ) : array();
	$today    = fleuriste_board_day();
	$default  = function_exists( 'fleuriste_fo_earliest_date' ) ? fleuriste_fo_earliest_date() : $today;
	$fee      = fleuriste_phone_order_default_fee();
	$max_msg  = function_exists( 'fleuriste_fo_get' ) ? (int) fleuriste_fo_get( 'fleuriste_fo_message_max' ) : 200;
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$error = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : '';
	$money = array(
		'symbol'   => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
		'format'   => html_entity_decode( get_woocommerce_price_format(), ENT_QUOTES, 'UTF-8' ),
		'decimals' => wc_get_price_decimals(),
		'decimal'  => wc_get_price_decimal_separator(),
		'thousand' => wc_get_price_thousand_separator(),
	);
	?>
	<div class="wrap fl-po">
		<?php if ( $prefill ) : ?>
			<h1><?php echo esc_html( $parent_of_edit ? sprintf( /* translators: 1: suborder number, 2: order number */ __( 'Edit suborder #%1$s (extra for order #%2$s)', 'fleuriste' ), $prefill['number'], $parent_of_edit->get_order_number() ) : sprintf( /* translators: %s: order number */ __( 'Edit order #%s', 'fleuriste' ), $prefill['number'] ) ); ?></h1>
			<p class="fl-po__intro"><?php esc_html_e( 'Change anything below and save. Totals and stock are recalculated, and the change is recorded in the order notes.', 'fleuriste' ); ?> <a href="<?php echo esc_url( $edit_order->get_edit_order_url() ); ?>"><?php esc_html_e( 'Open the full order screen', 'fleuriste' ); ?></a></p>
		<?php elseif ( $sub ) : ?>
			<h1><?php echo esc_html( sprintf( /* translators: %s: order number */ __( 'Add to order #%s', 'fleuriste' ), $sub['number'] ) ); ?></h1>
			<p class="fl-po__intro"><?php esc_html_e( 'Creates a suborder: a separate order linked to the original, with its own items, payment and total. Buyer, recipient and delivery are copied from the original order.', 'fleuriste' ); ?> <a href="<?php echo esc_url( $parent_order->get_edit_order_url() ); ?>"><?php echo esc_html( sprintf( /* translators: %s: order number */ __( 'View order #%s', 'fleuriste' ), $sub['number'] ) ); ?></a></p>
			<?php
			$pd       = fleuriste_board_order_data( $parent_order );
			$pd_items = array();
			foreach ( $pd['items'] as $it ) {
				$pd_items[] = $it['qty'] . ' × ' . $it['name'];
			}
			?>
			<div class="fl-po__parent">
				<div>
					<strong><?php echo esc_html( sprintf( /* translators: 1: order number, 2: total */ __( 'Order #%1$s · %2$s', 'fleuriste' ), $pd['number'], $pd['total'] ) ); ?></strong>
					<span class="fl-po__parent-status"><?php echo esc_html( wc_get_order_status_name( $pd['status'] ) ); ?></span><br>
					<?php echo esc_html( implode( ', ', $pd_items ) ); ?>
				</div>
				<div>
					<?php echo esc_html( sprintf( /* translators: 1: buyer, 2: recipient */ __( 'From %1$s to %2$s', 'fleuriste' ), $pd['sender'], $pd['recipient'] ) ); ?><br>
					<?php echo esc_html( ( $pd['date'] ? date_i18n( 'l j F', strtotime( $pd['date'] ) ) : __( 'No delivery date', 'fleuriste' ) ) . ( $pd['slot'] ? ' · ' . $pd['slot'] : '' ) ); ?>
				</div>
				<button type="button" class="button" id="fl-show-people"><?php esc_html_e( 'Change buyer / recipient', 'fleuriste' ); ?></button>
			</div>
		<?php elseif ( $copy ) : ?>
			<h1><?php echo esc_html( sprintf( /* translators: %s: order number */ __( 'New order – copy of #%s', 'fleuriste' ), $copy['number'] ) ); ?></h1>
			<p class="fl-po__intro"><?php esc_html_e( 'Everything from the original order is filled in. Change what you need (date, recipient, message…) and create the new order. The original order is not changed.', 'fleuriste' ); ?> <a href="<?php echo esc_url( $copy_order->get_edit_order_url() ); ?>"><?php echo esc_html( sprintf( /* translators: %s: order number */ __( 'View order #%s', 'fleuriste' ), $copy['number'] ) ); ?></a></p>
		<?php else : ?>
			<h1><?php esc_html_e( 'New order', 'fleuriste' ); ?></h1>
			<p class="fl-po__intro"><?php esc_html_e( 'For orders taken by phone, WhatsApp or in the shop. Start with the buyer\'s phone number to fill in a returning customer.', 'fleuriste' ); ?></p>
		<?php endif; ?>
		<?php if ( $error ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="fl-po-form" autocomplete="off">
			<input type="hidden" name="action" value="fleuriste_phone_order">
			<input type="hidden" name="order_id" value="<?php echo $prefill ? (int) $prefill['id'] : ''; ?>">
			<input type="hidden" name="copied_from" value="<?php echo $copy ? (int) $copy['id'] : ''; ?>">
			<input type="hidden" name="parent_id" value="<?php echo $sub ? (int) $sub['parent'] : ''; ?>">
			<?php wp_nonce_field( 'fleuriste_phone_order' ); ?>

			<div class="fl-po__grid" id="fl-people"<?php echo $sub ? ' hidden' : ''; ?>>
				<section class="fl-po__card">
					<h2><span>1</span> <?php esc_html_e( 'Buyer', 'fleuriste' ); ?></h2>
					<div class="fl-po__row">
						<label><?php esc_html_e( 'Phone', 'fleuriste' ); ?><input type="tel" name="b_phone" id="fl-b-phone" required placeholder="5xxx xxxx"></label>
						<label><?php esc_html_e( 'Email (optional)', 'fleuriste' ); ?><input type="email" name="b_email" id="fl-b-email"></label>
					</div>
					<div class="fl-po__lookup" id="fl-lookup" hidden></div>
					<div class="fl-po__row">
						<label><?php esc_html_e( 'First name', 'fleuriste' ); ?><input type="text" name="b_first" id="fl-b-first" required></label>
						<label><?php esc_html_e( 'Last name', 'fleuriste' ); ?><input type="text" name="b_last" id="fl-b-last"></label>
					</div>
					<label class="fl-po__check"><input type="checkbox" name="b_whatsapp" value="1"> <?php esc_html_e( 'Buyer wants updates on WhatsApp', 'fleuriste' ); ?></label>
				</section>

				<section class="fl-po__card">
					<h2><span>2</span> <?php esc_html_e( 'Recipient', 'fleuriste' ); ?></h2>
					<div class="fl-po__recents" id="fl-recents" hidden></div>
					<label class="fl-po__check"><input type="checkbox" id="fl-same" name="same_as_buyer" value="1"> <?php esc_html_e( 'Same as the buyer (pick-up or self-delivery)', 'fleuriste' ); ?></label>
					<div id="fl-recipient">
						<div class="fl-po__row">
							<label><?php esc_html_e( 'First name', 'fleuriste' ); ?><input type="text" name="r_first" id="fl-r-first"></label>
							<label><?php esc_html_e( 'Last name', 'fleuriste' ); ?><input type="text" name="r_last" id="fl-r-last"></label>
						</div>
						<label><?php esc_html_e( 'Address', 'fleuriste' ); ?><input type="text" name="r_address" id="fl-r-address"></label>
						<div class="fl-po__row">
							<?php $po_areas = function_exists( 'fleuriste_dl_regions' ) ? fleuriste_dl_regions() : array(); ?>
							<?php if ( $po_areas ) : ?>
								<label><?php esc_html_e( 'Area', 'fleuriste' ); ?>
									<select name="r_state" id="fl-r-state">
										<option value="" data-price=""><?php esc_html_e( 'Choose…', 'fleuriste' ); ?></option>
										<?php foreach ( $po_areas as $po_area ) : ?>
											<option value="<?php echo esc_attr( $po_area['id'] ); ?>" data-price="<?php echo esc_attr( $po_area['price'] ); ?>"><?php echo esc_html( $po_area['name'] . ' (' . fleuriste_fo_money( $po_area['price'] ) . ')' ); ?></option>
										<?php endforeach; ?>
									</select>
								</label>
								<input type="hidden" name="r_city" id="fl-r-city">
							<?php else : ?>
								<label><?php esc_html_e( 'Region', 'fleuriste' ); ?><input type="text" name="r_city" id="fl-r-city"></label>
							<?php endif; ?>
						</div>
						<label><?php esc_html_e( 'Phone', 'fleuriste' ); ?><input type="tel" name="r_phone" id="fl-r-phone"></label>
					</div>
					<label class="fl-po__check"><input type="checkbox" name="anonymous" value="1"> <?php esc_html_e( 'Send anonymously', 'fleuriste' ); ?></label>
				</section>
			</div>

			<section class="fl-po__card">
				<h2><span>3</span> <?php echo $sub ? esc_html__( 'What to add', 'fleuriste' ) : esc_html__( 'Flowers', 'fleuriste' ); ?></h2>
				<div id="fl-items"></div>
				<div id="fl-customs"></div>
				<div class="fl-po__add">
					<button type="button" class="button" id="fl-add-item">+ <?php esc_html_e( 'Add a product', 'fleuriste' ); ?></button>
					<button type="button" class="button" id="fl-add-custom">+ <?php esc_html_e( 'Add a custom item', 'fleuriste' ); ?></button>
					<small><?php esc_html_e( 'Custom item: anything not in the shop, with your own name and price (e.g. "6 extra red roses", "Helium balloon").', 'fleuriste' ); ?></small>
				</div>
				<label class="fl-po__msg"><?php esc_html_e( 'Card message (optional)', 'fleuriste' ); ?>
					<textarea name="message" rows="2" maxlength="<?php echo (int) max( 1, $max_msg ); ?>"></textarea>
				</label>
			</section>

			<div class="fl-po__grid">
				<section class="fl-po__card">
					<h2><span>4</span> <?php esc_html_e( 'Delivery', 'fleuriste' ); ?></h2>
					<div class="fl-po__row">
						<label><?php esc_html_e( 'Date', 'fleuriste' ); ?><input type="date" name="date" value="<?php echo esc_attr( $default ); ?>" min="<?php echo esc_attr( $today ); ?>" required></label>
						<label><?php esc_html_e( 'Time', 'fleuriste' ); ?>
							<select name="slot" id="fl-slot">
								<?php foreach ( $slots as $i => $slot ) : ?>
									<option value="<?php echo (int) $i; ?>" data-price="<?php echo esc_attr( $slot['price'] ); ?>"><?php echo esc_html( $slot['label'] . ( $slot['price'] > 0 ? ' +' . fleuriste_fo_money( $slot['price'] ) : '' ) ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
					</div>
					<label class="fl-po__check"><input type="checkbox" name="delivery" id="fl-delivery" value="1" checked> <?php echo $sub ? esc_html__( 'Charge delivery (leave unticked if it goes with the original order)', 'fleuriste' ) : esc_html__( 'Delivery applies (untick for pick-up in the shop)', 'fleuriste' ); ?></label>
					<div class="fl-po__row">
						<label><?php esc_html_e( 'Delivery fee', 'fleuriste' ); ?><input type="number" name="fee" id="fl-fee" min="0" step="any" value="<?php echo esc_attr( $fee ); ?>"></label>
						<?php if ( function_exists( 'fleuriste_drivers' ) && fleuriste_drivers( true ) ) : ?>
							<label><?php esc_html_e( 'Driver', 'fleuriste' ); ?><?php echo fleuriste_driver_select( 'driver', '', '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
						<?php endif; ?>
					</div>
					<label><?php esc_html_e( 'Note for the team (not shown to the buyer)', 'fleuriste' ); ?><input type="text" name="note"></label>
				</section>

				<section class="fl-po__card">
					<h2><span>5</span> <?php esc_html_e( 'Payment', 'fleuriste' ); ?></h2>
					<?php if ( $prefill && 'online' === $prefill['pay'] ) : ?>
						<p class="fl-po__paid"><?php echo esc_html( sprintf( /* translators: 1: method, 2: amount */ __( 'Paid online (%1$s): %2$s. If the total changes, the difference has to be refunded or collected separately.', 'fleuriste' ), $prefill['paid_as'], html_entity_decode( wp_strip_all_tags( wc_price( $edit_order->get_total() ) ) ) ) ); ?></p>
						<input type="hidden" name="pay" value="online">
					<?php endif; ?>
					<div class="fl-po__pay"<?php echo ( $prefill && 'online' === $prefill['pay'] ) ? ' hidden' : ''; ?>>
						<label><input type="radio" name="pay" value="cod" <?php checked( 'cod' === $pay_now ); ?> <?php disabled( $prefill && 'online' === $prefill['pay'] ); ?>> <strong><?php esc_html_e( 'Cash on delivery', 'fleuriste' ); ?></strong></label>
						<label><input type="radio" name="pay" value="instore" <?php checked( 'instore' === $pay_now ); ?> <?php disabled( $prefill && 'online' === $prefill['pay'] ); ?>> <strong><?php esc_html_e( 'Already paid', 'fleuriste' ); ?></strong> <small><?php esc_html_e( '(cash, card or Juice in the shop)', 'fleuriste' ); ?></small></label>
						<label><input type="radio" name="pay" value="bacs" <?php checked( 'bacs' === $pay_now ); ?> <?php disabled( $prefill && 'online' === $prefill['pay'] ); ?>> <strong><?php esc_html_e( 'Bank transfer', 'fleuriste' ); ?></strong> <small><?php esc_html_e( '(awaiting payment)', 'fleuriste' ); ?></small></label>
						<label><input type="radio" name="pay" value="link" <?php checked( 'link' === $pay_now ); ?> <?php disabled( $prefill && 'online' === $prefill['pay'] ); ?>> <strong><?php esc_html_e( 'Send a payment link', 'fleuriste' ); ?></strong> <small><?php esc_html_e( '(buyer pays online by card)', 'fleuriste' ); ?></small></label>
					</div>
					<div class="fl-po__discount">
						<label for="fl-discount"><?php esc_html_e( 'Discount (optional)', 'fleuriste' ); ?></label>
						<div><input type="number" name="discount" id="fl-discount" min="0" step="any" placeholder="0"><select name="discount_type" id="fl-discount-type" aria-label="<?php esc_attr_e( 'Discount type', 'fleuriste' ); ?>"><option value="amount"><?php echo esc_html( trim( $money['symbol'] ) ? trim( $money['symbol'] ) : __( 'Amount', 'fleuriste' ) ); ?></option><option value="percent">%</option></select></div>
					</div>
					<div class="fl-po__total">
						<div><span><?php esc_html_e( 'Items', 'fleuriste' ); ?></span><strong id="fl-t-items">–</strong></div>
						<div id="fl-t-disc-row" hidden><span><?php esc_html_e( 'Discount', 'fleuriste' ); ?></span><strong id="fl-t-disc">–</strong></div>
						<div><span><?php esc_html_e( 'Delivery', 'fleuriste' ); ?></span><strong id="fl-t-fee">–</strong></div>
						<div class="fl-po__grand"><span><?php esc_html_e( 'Total', 'fleuriste' ); ?></span><strong id="fl-t-total">–</strong></div>
					</div>
					<label class="fl-po__check"><input type="checkbox" name="notify_email" value="1" <?php checked( ! $prefill ); ?>> <?php echo $prefill ? esc_html__( 'Email the buyer about the change', 'fleuriste' ) : ( $sub ? esc_html__( 'Email the buyer the extra items (and the payment link)', 'fleuriste' ) : esc_html__( 'Email the buyer a confirmation (or the payment link)', 'fleuriste' ) ); ?></label>
					<label class="fl-po__check"><input type="checkbox" name="notify_wa" value="1"> <?php esc_html_e( 'Send it on WhatsApp too', 'fleuriste' ); ?></label>
					<button class="button button-primary button-hero fl-po__submit"><?php echo $prefill ? esc_html__( 'Save changes', 'fleuriste' ) : ( $sub ? esc_html__( 'Create suborder', 'fleuriste' ) : esc_html__( 'Create order', 'fleuriste' ) ); ?></button>
				</section>
			</div>
		</form>
	</div>

	<template id="fl-custom-tpl">
		<div class="fl-po__item fl-po__custom">
			<input type="text" class="fl-c-name" placeholder="<?php esc_attr_e( 'Custom item, e.g. 6 extra red roses', 'fleuriste' ); ?>" aria-label="<?php esc_attr_e( 'Item name', 'fleuriste' ); ?>">
			<input type="number" class="fl-c-qty small-text" min="1" value="1" aria-label="<?php esc_attr_e( 'Quantity', 'fleuriste' ); ?>">
			<input type="number" class="fl-c-price" min="0" step="any" placeholder="<?php esc_attr_e( 'Price each', 'fleuriste' ); ?>" aria-label="<?php esc_attr_e( 'Price each', 'fleuriste' ); ?>">
			<button type="button" class="button-link button-link-delete fl-c-remove" aria-label="<?php esc_attr_e( 'Remove', 'fleuriste' ); ?>">✕</button>
			<span class="fl-i-total fl-c-total">–</span>
		</div>
	</template>

	<template id="fl-item-tpl">
		<div class="fl-po__item">
			<select class="fl-i-product">
				<option value=""><?php esc_html_e( 'Choose a product…', 'fleuriste' ); ?></option>
				<?php
				$by_cat = array();
				foreach ( $products as $p ) {
					$by_cat[ $p['cat'] ][] = $p;
				}
				ksort( $by_cat );
				foreach ( $by_cat as $cat => $items ) :
					?>
					<optgroup label="<?php echo esc_attr( $cat ); ?>">
						<?php foreach ( $items as $p ) : ?>
							<option value="<?php echo (int) $p['id']; ?>"><?php echo esc_html( $p['name'] . ' – ' . fleuriste_fo_money( $p['price'] ) . ( $p['stock'] ? '' : ' (' . __( 'out of stock', 'fleuriste' ) . ')' ) ); ?></option>
						<?php endforeach; ?>
					</optgroup>
				<?php endforeach; ?>
			</select>
			<input type="number" class="fl-i-qty small-text" min="1" value="1" aria-label="<?php esc_attr_e( 'Quantity', 'fleuriste' ); ?>">
			<span class="fl-i-total">–</span>
			<button type="button" class="button-link button-link-delete fl-i-remove" aria-label="<?php esc_attr_e( 'Remove', 'fleuriste' ); ?>">✕</button>
			<div class="fl-i-options" hidden>
				<?php if ( $sizes ) : ?>
					<label><?php esc_html_e( 'Size', 'fleuriste' ); ?> <select class="fl-i-size"><?php foreach ( $sizes as $i => $c ) : ?><option value="<?php echo (int) $i; ?>" data-price="<?php echo esc_attr( $c['price'] ); ?>"><?php echo esc_html( $c['label'] . ( $c['price'] > 0 ? ' +' . fleuriste_fo_money( $c['price'] ) : '' ) ); ?></option><?php endforeach; ?></select></label>
				<?php endif; ?>
				<?php if ( $vases ) : ?>
					<label><?php esc_html_e( 'Vase', 'fleuriste' ); ?> <select class="fl-i-vase"><?php foreach ( $vases as $i => $c ) : ?><option value="<?php echo (int) $i; ?>" data-price="<?php echo esc_attr( $c['price'] ); ?>"><?php echo esc_html( $c['label'] . ( $c['price'] > 0 ? ' +' . fleuriste_fo_money( $c['price'] ) : '' ) ); ?></option><?php endforeach; ?></select></label>
				<?php endif; ?>
				<?php foreach ( $extras as $i => $c ) : ?>
					<label class="fl-i-extra"><input type="checkbox" value="<?php echo (int) $i; ?>" data-price="<?php echo esc_attr( $c['price'] ); ?>"> <?php echo esc_html( $c['label'] . ' +' . fleuriste_fo_money( $c['price'] ) ); ?></label>
				<?php endforeach; ?>
			</div>
		</div>
	</template>

	<style>
		.fl-po{max-width:1100px;--rose:#9E4F63;--line:#EBDCDD}
		.fl-po__intro{color:#646970}
		.fl-po__grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
		.fl-po__card{background:#fff;border:1px solid var(--line);border-radius:12px;padding:6px 18px 18px;margin:0 0 16px}
		.fl-po__card h2{display:flex;align-items:center;gap:8px;font-size:16px}
		.fl-po__card h2 span{display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:50%;background:var(--rose);color:#fff;font-size:12px}
		.fl-po label{display:grid;gap:3px;margin:0 0 10px;font-weight:500}
		.fl-po input[type=text],.fl-po input[type=tel],.fl-po input[type=email],.fl-po input[type=date],.fl-po input[type=number]:not(.small-text),.fl-po select,.fl-po textarea{width:100%}
		.fl-po__row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
		.fl-po label.fl-po__check{display:flex;gap:6px;align-items:center;font-weight:400}
		.fl-po__lookup,.fl-po__recents{background:#f6f7f7;border-radius:8px;padding:8px 10px;margin:0 0 10px;font-size:13px}
		.fl-po__recents button{margin:4px 6px 0 0}
		.fl-po__item{display:grid;grid-template-columns:1fr 70px 110px 24px;gap:8px;align-items:center;padding:10px 0;border-bottom:1px solid #f0f0f1}
		.fl-i-total{text-align:right;font-weight:600}
		.fl-i-options{grid-column:1/-1;display:flex;flex-wrap:wrap;gap:6px 14px;align-items:center;background:#fcf8f8;border-radius:8px;padding:8px 10px}
		.fl-i-options label{display:flex;gap:6px;align-items:center;margin:0;font-weight:400}
		.fl-i-options select{width:auto}
		#fl-add-item{margin:10px 0 14px}
		.fl-po__pay{display:grid;gap:2px;margin:0 0 10px}
		.fl-po__pay label{display:block;font-weight:400}
		.fl-po__pay small{color:#646970}
		.fl-po__total{border-top:1px solid #f0f0f1;padding:10px 0;margin:0 0 6px}
		.fl-po__total div{display:flex;justify-content:space-between;padding:2px 0}
		.fl-po__grand{font-size:18px;color:var(--rose);border-top:1px dashed var(--line);margin-top:4px;padding-top:6px!important}
		.fl-po__paid{background:#fcf9e8;border-radius:8px;padding:8px 10px}
		.fl-po__submit{width:100%;text-align:center;margin-top:8px!important;background:var(--rose)!important;border-color:var(--rose)!important}
		.fl-po [hidden]{display:none!important}
		.fl-po__add{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:10px 0 14px}
		.fl-po__add small{color:#646970;flex-basis:100%}
		#fl-add-item{margin:0}
		.fl-po__custom{grid-template-columns:1fr 70px 120px 24px}
		.fl-po__custom .fl-c-total{grid-column:1/-1;margin-top:-4px;font-size:12px;color:#646970;font-weight:400}
		.fl-po__discount{margin:0 0 6px}
		.fl-po__discount label{margin:0 0 3px}
		.fl-po__discount > div{display:flex;gap:6px}
		.fl-po__discount input{flex:1}
		.fl-po__discount select{width:auto!important}
		.fl-po__parent{display:grid;grid-template-columns:1fr 1fr auto;gap:12px;align-items:center;background:#fff;border:1px solid var(--line);border-left:4px solid var(--rose);border-radius:12px;padding:12px 16px;margin:0 0 16px;line-height:1.6}
		.fl-po__parent-status{display:inline-block;margin-left:6px;border-radius:99px;padding:0 8px;background:#f0f0f1;font-size:12px}
		#fl-fee:disabled{opacity:.5}
		@media (max-width:900px){.fl-po__grid{grid-template-columns:1fr}.fl-po__parent{grid-template-columns:1fr}}
		@media (max-width:600px){.fl-po__row{grid-template-columns:1fr}.fl-po__item{grid-template-columns:1fr 60px 24px}.fl-i-total{grid-column:1/3;text-align:left}}
	</style>
	<script>
	( function () {
		var products = <?php echo wp_json_encode( array_column( $products, null, 'id' ) ); ?>;
		var cfg = <?php echo wp_json_encode( $money ); ?>;
		var ajax = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
		var nonce = <?php echo wp_json_encode( wp_create_nonce( 'fleuriste_lookup' ) ); ?>;
		var form = document.getElementById( 'fl-po-form' );
		var list = document.getElementById( 'fl-items' );
		var tpl = document.getElementById( 'fl-item-tpl' ).innerHTML;
		var ctpl = document.getElementById( 'fl-custom-tpl' ).innerHTML;
		var clist = document.getElementById( 'fl-customs' );
		var n = 0, cn = 0;
		function money( v ) {
			var p = Math.abs( v ).toFixed( cfg.decimals ).split( '.' );
			p[0] = p[0].replace( /\B(?=(\d{3})+(?!\d))/g, cfg.thousand );
			return cfg.format.replace( '%1$s', cfg.symbol ).replace( '%2$s', p.join( cfg.decimal ) );
		}
		function addItem() {
			var i = n++;
			var wrap = document.createElement( 'div' );
			wrap.innerHTML = tpl;
			var row = wrap.firstElementChild;
			row.querySelector( '.fl-i-product' ).name = 'items[' + i + '][product]';
			row.querySelector( '.fl-i-qty' ).name = 'items[' + i + '][qty]';
			var s = row.querySelector( '.fl-i-size' ); if ( s ) { s.name = 'items[' + i + '][size]'; }
			var v = row.querySelector( '.fl-i-vase' ); if ( v ) { v.name = 'items[' + i + '][vase]'; }
			row.querySelectorAll( '.fl-i-extra input' ).forEach( function ( c ) { c.name = 'items[' + i + '][extras][]'; } );
			list.appendChild( row );
			update();
		}
		function addCustom( c ) {
			var i = cn++;
			var wrap = document.createElement( 'div' );
			wrap.innerHTML = ctpl;
			var row = wrap.firstElementChild;
			row.querySelector( '.fl-c-name' ).name = 'custom[' + i + '][name]';
			row.querySelector( '.fl-c-qty' ).name = 'custom[' + i + '][qty]';
			row.querySelector( '.fl-c-price' ).name = 'custom[' + i + '][price]';
			if ( c ) {
				row.querySelector( '.fl-c-name' ).value = c.name || '';
				row.querySelector( '.fl-c-qty' ).value = c.qty || 1;
				row.querySelector( '.fl-c-price' ).value = ( c.price !== undefined && c.price !== null ) ? c.price : '';
			}
			clist.appendChild( row );
			if ( ! c ) { row.querySelector( '.fl-c-name' ).focus(); }
			update();
		}
		function update() {
			var sum = 0;
			clist.querySelectorAll( '.fl-po__custom' ).forEach( function ( row ) {
				var price = parseFloat( row.querySelector( '.fl-c-price' ).value );
				var qty = Math.max( 1, parseInt( row.querySelector( '.fl-c-qty' ).value, 10 ) || 1 );
				var out = row.querySelector( '.fl-c-total' );
				if ( isNaN( price ) ) { out.textContent = ''; return; }
				out.textContent = qty > 1 ? qty + ' × ' + money( price ) + ' = ' + money( price * qty ) : money( price );
				sum += price * qty;
			} );
			list.querySelectorAll( '.fl-po__item' ).forEach( function ( row ) {
				var p = products[ row.querySelector( '.fl-i-product' ).value ];
				var opts = row.querySelector( '.fl-i-options' );
				opts.hidden = ! ( p && p.custom );
				if ( ! p ) { row.querySelector( '.fl-i-total' ).textContent = '–'; return; }
				var unit = p.price;
				if ( p.custom ) {
					opts.querySelectorAll( 'select option:checked, input:checked' ).forEach( function ( o ) { unit += parseFloat( o.dataset.price ) || 0; } );
				}
				var line = unit * Math.max( 1, parseInt( row.querySelector( '.fl-i-qty' ).value, 10 ) || 1 );
				row.querySelector( '.fl-i-total' ).textContent = money( line );
				sum += line;
			} );
			var slot = document.querySelector( '#fl-slot option:checked' );
			var deliv = document.getElementById( 'fl-delivery' ).checked;
			document.getElementById( 'fl-fee' ).disabled = ! deliv;
			var fee = deliv ? ( parseFloat( document.getElementById( 'fl-fee' ).value ) || 0 ) + ( slot ? parseFloat( slot.dataset.price ) || 0 : 0 ) : 0;
			var dv = parseFloat( document.getElementById( 'fl-discount' ).value ) || 0;
			var disc = 0;
			if ( dv > 0 ) {
				disc = document.getElementById( 'fl-discount-type' ).value === 'percent' ? sum * Math.min( 100, dv ) / 100 : Math.min( dv, sum );
				disc = Math.round( disc * Math.pow( 10, cfg.decimals ) ) / Math.pow( 10, cfg.decimals );
			}
			document.getElementById( 'fl-t-disc-row' ).hidden = ! disc;
			document.getElementById( 'fl-t-disc' ).textContent = '− ' + money( disc );
			document.getElementById( 'fl-t-items' ).textContent = money( sum );
			document.getElementById( 'fl-t-fee' ).textContent = deliv ? money( fee ) : <?php echo wp_json_encode( __( 'none', 'fleuriste' ) ); ?>;
			document.getElementById( 'fl-t-total' ).textContent = money( sum - disc + fee );
		}
		// Choosing the recipient's area fills in its delivery fee.
		var areaSel = document.getElementById( 'fl-r-state' );
		if ( areaSel ) {
			areaSel.addEventListener( 'change', function () {
				var o = areaSel.options[ areaSel.selectedIndex ];
				if ( o && o.dataset.price !== '' ) { document.getElementById( 'fl-fee' ).value = o.dataset.price; }
				document.getElementById( 'fl-fee' ).dispatchEvent( new Event( 'input', { bubbles: true } ) );
			} );
		}
		document.getElementById( 'fl-add-item' ).addEventListener( 'click', function () { addItem(); } );
		document.getElementById( 'fl-add-custom' ).addEventListener( 'click', function () { addCustom(); } );
		list.addEventListener( 'click', function ( e ) {
			if ( e.target.classList.contains( 'fl-i-remove' ) && ( list.children.length > 1 || clist.children.length ) ) { e.target.closest( '.fl-po__item' ).remove(); update(); }
		} );
		clist.addEventListener( 'click', function ( e ) {
			if ( e.target.classList.contains( 'fl-c-remove' ) ) { e.target.closest( '.fl-po__item' ).remove(); update(); }
		} );
		var showPeople = document.getElementById( 'fl-show-people' );
		if ( showPeople ) {
			showPeople.addEventListener( 'click', function () { document.getElementById( 'fl-people' ).hidden = false; showPeople.remove(); } );
		}
		form.addEventListener( 'input', update );
		form.addEventListener( 'change', update );

		// Recipient same as buyer.
		var same = document.getElementById( 'fl-same' );
		same.addEventListener( 'change', function () { document.getElementById( 'fl-recipient' ).hidden = same.checked; } );

		// Returning customers: look up by phone or email.
		var timer;
		function lookup() {
			var phone = document.getElementById( 'fl-b-phone' ).value.trim();
			var email = document.getElementById( 'fl-b-email' ).value.trim();
			if ( phone.replace( /\D/g, '' ).length < 7 && email.indexOf( '@' ) < 1 ) { return; }
			fetch( ajax, { method: 'POST', credentials: 'same-origin', body: new URLSearchParams( { action: 'fleuriste_lookup', nonce: nonce, phone: phone, email: email } ) } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( res ) {
					var box = document.getElementById( 'fl-lookup' );
					var rec = document.getElementById( 'fl-recents' );
					if ( ! res.success || ! res.data.buyer ) { box.hidden = true; rec.hidden = true; return; }
					var b = res.data.buyer;
					[ [ 'fl-b-first', b.first ], [ 'fl-b-last', b.last ], [ 'fl-b-email', b.email ] ].forEach( function ( f ) {
						var el = document.getElementById( f[0] ); if ( el && ! el.value && f[1] ) { el.value = f[1]; }
					} );
					box.hidden = false;
					box.textContent = <?php echo wp_json_encode( __( 'Returning customer:', 'fleuriste' ) ); ?> + ' ' + b.first + ' ' + b.last + ' · ' + res.data.count + ' ' + <?php echo wp_json_encode( __( 'previous order(s)', 'fleuriste' ) ); ?>;
					rec.innerHTML = '';
					if ( res.data.recipients.length ) {
						rec.hidden = false;
						rec.appendChild( document.createTextNode( <?php echo wp_json_encode( __( 'Previous recipients:', 'fleuriste' ) ); ?> ) );
						rec.appendChild( document.createElement( 'br' ) );
						res.data.recipients.forEach( function ( r ) {
							var btn = document.createElement( 'button' );
							btn.type = 'button'; btn.className = 'button button-small';
							btn.textContent = r.first + ' ' + r.last + ( r.city ? ', ' + r.city : '' );
							btn.addEventListener( 'click', function () {
								same.checked = false; document.getElementById( 'fl-recipient' ).hidden = false;
								document.getElementById( 'fl-r-first' ).value = r.first; document.getElementById( 'fl-r-last' ).value = r.last;
								document.getElementById( 'fl-r-address' ).value = r.address; document.getElementById( 'fl-r-city' ).value = r.city;
								var st = document.getElementById( 'fl-r-state' ); if ( st && r.state ) { st.value = r.state; st.dispatchEvent( new Event( 'change' ) ); }
								document.getElementById( 'fl-r-phone' ).value = r.phone;
							} );
							rec.appendChild( btn );
						} );
					}
				} );
		}
		[ 'fl-b-phone', 'fl-b-email' ].forEach( function ( id ) {
			document.getElementById( id ).addEventListener( 'input', function () { clearTimeout( timer ); timer = setTimeout( lookup, 500 ); } );
		} );

		form.addEventListener( 'submit', function ( e ) {
			var hasProduct = Array.prototype.some.call( list.querySelectorAll( '.fl-i-product' ), function ( sel ) { return sel.value; } );
			var badCustom = false, hasCustom = false;
			clist.querySelectorAll( '.fl-po__custom' ).forEach( function ( row ) {
				var name = row.querySelector( '.fl-c-name' ).value.trim(), price = row.querySelector( '.fl-c-price' ).value;
				if ( name ) { hasCustom = true; if ( price === '' ) { badCustom = true; } }
			} );
			if ( badCustom ) {
				e.preventDefault();
				alert( <?php echo wp_json_encode( __( 'Please enter a price for each custom item.', 'fleuriste' ) ); ?> );
				return;
			}
			if ( ! hasProduct && ! hasCustom ) {
				e.preventDefault();
				alert( <?php echo wp_json_encode( __( 'Please choose a product or add a custom item.', 'fleuriste' ) ); ?> );
				return;
			}
			if ( ! same.checked && ( ! document.getElementById( 'fl-r-first' ).value.trim() || ! document.getElementById( 'fl-r-address' ).value.trim() ) ) {
				e.preventDefault();
				alert( <?php echo wp_json_encode( __( 'Please enter the recipient\'s name and address, or tick "Same as the buyer".', 'fleuriste' ) ); ?> );
			}
		} );
		var pre = <?php echo wp_json_encode( $prefill ? $prefill : ( $copy ? $copy : $sub ) ); ?>;
		if ( pre ) {
			var set = function ( sel, v ) { var el = form.querySelector( sel ); if ( el && v !== null && v !== undefined ) { el.value = v; } };
			set( '[name=b_first]', pre.b_first ); set( '[name=b_last]', pre.b_last ); set( '[name=b_phone]', pre.b_phone ); set( '[name=b_email]', pre.b_email );
			form.querySelector( '[name=b_whatsapp]' ).checked = pre.b_wa;
			same.checked = pre.same; document.getElementById( 'fl-recipient' ).hidden = pre.same;
			set( '[name=r_first]', pre.r_first ); set( '[name=r_last]', pre.r_last ); set( '[name=r_address]', pre.r_address ); set( '[name=r_city]', pre.r_city ); set( '[name=r_state]', pre.r_state ); set( '[name=r_phone]', pre.r_phone );
			form.querySelector( '[name=anonymous]' ).checked = pre.anon;
			set( '[name=date]', pre.date ); set( '[name=slot]', pre.slot ); set( '[name=message]', pre.message ); set( '[name=fee]', pre.fee ); set( '[name=driver]', pre.driver );
			var dateEl = form.querySelector( '[name=date]' );
			if ( pre.copy ) {
				// A copy gets the earliest possible date unless the original date is still ahead.
				if ( ! pre.date || pre.date < dateEl.defaultValue ) { dateEl.value = dateEl.defaultValue; }
			} else if ( pre.sub ) {
				// Extras go with the original delivery; if that date has passed, today.
				if ( ! pre.date || pre.date < dateEl.min ) { dateEl.value = dateEl.min; }
			} else if ( pre.date && pre.date < dateEl.min ) { dateEl.min = pre.date; }
			document.getElementById( 'fl-delivery' ).checked = pre.delivery !== false;
			if ( pre.discount && pre.discount.value ) {
				document.getElementById( 'fl-discount' ).value = pre.discount.value;
				document.getElementById( 'fl-discount-type' ).value = pre.discount.type === 'percent' ? 'percent' : 'amount';
			}
			( pre.custom || [] ).forEach( function ( c ) { addCustom( c ); } );
			( pre.items.length || ( pre.custom || [] ).length ? pre.items : [ {} ] ).forEach( function ( it ) {
				addItem();
				var row = list.lastElementChild;
				if ( it.product ) { row.querySelector( '.fl-i-product' ).value = it.product; }
				if ( it.qty ) { row.querySelector( '.fl-i-qty' ).value = it.qty; }
				var sz = row.querySelector( '.fl-i-size' ); if ( sz && it.size !== null && it.size !== undefined ) { sz.value = it.size; }
				var vs = row.querySelector( '.fl-i-vase' ); if ( vs && it.vase !== null && it.vase !== undefined ) { vs.value = it.vase; }
				row.querySelectorAll( '.fl-i-extra input' ).forEach( function ( c ) { c.checked = ( it.extras || [] ).indexOf( parseInt( c.value, 10 ) ) !== -1; } );
			} );
			update();
		} else {
			addItem();
		}
	} )();
	</script>
	<?php
}

/* -------------------------------------------------------------------------
 * Returning customer look-up
 * ---------------------------------------------------------------------- */

add_action(
	'wp_ajax_fleuriste_lookup',
	function () {
		check_ajax_referer( 'fleuriste_lookup', 'nonce' );
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_send_json_error( null, 403 );
		}
		$phone  = isset( $_POST['phone'] ) ? preg_replace( '/\D/', '', wp_unslash( $_POST['phone'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$email  = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$orders = array();
		if ( $email ) {
			$orders = wc_get_orders(
				array(
					'billing_email' => $email,
					'limit'         => 20,
					'type'          => 'shop_order',
				)
			);
		}
		if ( ! $orders && strlen( $phone ) >= 7 ) {
			// Phone numbers are stored as typed, so compare digits only.
			$recent = wc_get_orders(
				array(
					'limit'   => 300,
					'type'    => 'shop_order',
					'orderby' => 'date',
					'order'   => 'DESC',
				)
			);
			$tail   = substr( $phone, -7 );
			foreach ( $recent as $o ) {
				if ( substr( preg_replace( '/\D/', '', $o->get_billing_phone() ), -7 ) === $tail ) {
					$orders[] = $o;
				}
			}
		}
		if ( ! $orders ) {
			wp_send_json_success( array( 'buyer' => null ) );
		}
		$last       = $orders[0];
		$recipients = array();
		foreach ( $orders as $o ) {
			$key = strtolower( $o->get_shipping_first_name() . '|' . $o->get_shipping_address_1() );
			if ( '|' === $key || isset( $recipients[ $key ] ) ) {
				continue;
			}
			$recipients[ $key ] = array(
				'first'   => $o->get_shipping_first_name(),
				'last'    => $o->get_shipping_last_name(),
				'address' => $o->get_shipping_address_1(),
				'city'    => $o->get_shipping_city(),
				'state'   => $o->get_shipping_state(),
				'phone'   => $o->get_shipping_phone(),
			);
			if ( count( $recipients ) >= 4 ) {
				break;
			}
		}
		wp_send_json_success(
			array(
				'buyer'      => array(
					'first' => $last->get_billing_first_name(),
					'last'  => $last->get_billing_last_name(),
					'email' => $last->get_billing_email(),
				),
				'count'      => count( $orders ),
				'recipients' => array_values( $recipients ),
			)
		);
	}
);

/* -------------------------------------------------------------------------
 * Create the order
 * ---------------------------------------------------------------------- */

add_action(
	'admin_post_fleuriste_phone_order',
	function () {
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'fleuriste' ) );
		}
		check_admin_referer( 'fleuriste_phone_order' );
		$post = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each field is sanitized below.
		$back = function ( $msg ) use ( $post ) {
			$args = array( 'error' => rawurlencode( $msg ) );
			foreach ( array( 'order_id' => 'order', 'copied_from' => 'copy', 'parent_id' => 'parent' ) as $field => $param ) {
				if ( ! empty( $post[ $field ] ) ) {
					$args[ $param ] = absint( $post[ $field ] );
				}
			}
			wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php?page=fleuriste-new-order' ) ) );
			exit;
		};
		$text = function ( $key ) use ( $post ) {
			return isset( $post[ $key ] ) ? sanitize_text_field( $post[ $key ] ) : '';
		};

		// Products.
		$lines = array();
		$pick  = function ( $key, $index ) {
			$list = fleuriste_fo_choices( $key );
			return ( '' !== $index && null !== $index && isset( $list[ (int) $index ] ) ) ? $list[ (int) $index ] : null;
		};
		foreach ( (array) ( isset( $post['items'] ) ? $post['items'] : array() ) as $row ) {
			$product = ! empty( $row['product'] ) ? wc_get_product( absint( $row['product'] ) ) : null;
			if ( ! $product || ! $product->is_purchasable() ) {
				continue;
			}
			$custom = fleuriste_fo_customisable( $product );
			$data   = array(
				'size'      => $custom ? $pick( 'fleuriste_fo_sizes', isset( $row['size'] ) ? $row['size'] : null ) : null,
				'vase'      => $custom ? $pick( 'fleuriste_fo_vases', isset( $row['vase'] ) ? $row['vase'] : null ) : null,
				'extras'    => array(),
				'date'      => '',
				'slot'      => null,
				'message'   => '',
				'anonymous' => false,
			);
			if ( $custom && ! empty( $row['extras'] ) ) {
				foreach ( (array) $row['extras'] as $i ) {
					$extra = $pick( 'fleuriste_fo_extras', $i );
					if ( $extra ) {
						$data['extras'][] = $extra;
					}
				}
			}
			$lines[] = array(
				'product' => $product,
				'qty'     => max( 1, absint( isset( $row['qty'] ) ? $row['qty'] : 1 ) ),
				'data'    => $data,
			);
		}
		// Custom items (name + price, not a shop product).
		$customs = array();
		foreach ( (array) ( isset( $post['custom'] ) ? $post['custom'] : array() ) as $row ) {
			$name = isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '';
			if ( '' === $name ) {
				continue;
			}
			$customs[] = array(
				'name'  => $name,
				'price' => max( 0, (float) wc_format_decimal( isset( $row['price'] ) ? $row['price'] : 0 ) ),
				'qty'   => max( 1, absint( isset( $row['qty'] ) ? $row['qty'] : 1 ) ),
			);
		}
		if ( ! $lines && ! $customs ) {
			$back( __( 'Please choose a product or add a custom item.', 'fleuriste' ) );
		}

		$date = $text( 'date' );
		$keep_date = '';
		$existing  = ! empty( $post['order_id'] ) ? wc_get_order( absint( $post['order_id'] ) ) : false;
		if ( $existing ) {
			$keep_date = fleuriste_board_order_data( $existing )['date'];
		}
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) || ( $date < fleuriste_board_day() && $date !== $keep_date ) ) {
			$back( __( 'Please choose a delivery date from today onwards.', 'fleuriste' ) );
		}
		$slot    = $pick( 'fleuriste_fo_slots', isset( $post['slot'] ) ? $post['slot'] : null );
		$message = isset( $post['message'] ) ? sanitize_textarea_field( $post['message'] ) : '';
		$anon    = ! empty( $post['anonymous'] );

		$first = $text( 'b_first' );
		$phone = $text( 'b_phone' );
		$email = isset( $post['b_email'] ) ? sanitize_email( $post['b_email'] ) : '';
		if ( ! $first || ! $phone ) {
			$back( __( 'Please enter the buyer\'s first name and phone number.', 'fleuriste' ) );
		}

		$billing = array(
			'first_name' => $first,
			'last_name'  => $text( 'b_last' ),
			'phone'      => $phone,
			'email'      => $email,
			'country'    => function_exists( 'fleuriste_shop_country' ) ? fleuriste_shop_country() : 'MU',
		);
		if ( ! empty( $post['same_as_buyer'] ) ) {
			$shipping = array(
				'first_name' => $billing['first_name'],
				'last_name'  => $billing['last_name'],
				'phone'      => $phone,
				'country'    => $billing['country'],
			);
		} else {
			$shipping = array(
				'first_name' => $text( 'r_first' ),
				'last_name'  => $text( 'r_last' ),
				'address_1'  => $text( 'r_address' ),
				'city'       => $text( 'r_city' ),
				'state'      => ( function_exists( 'fleuriste_dl_region' ) && fleuriste_dl_region( $text( 'r_state' ) ) ) ? fleuriste_dl_region( $text( 'r_state' ) )['id'] : '',
				'phone'      => $text( 'r_phone' ),
				'country'    => $billing['country'],
			);
			if ( ! $shipping['first_name'] || ! $shipping['address_1'] ) {
				$back( __( 'Please enter the recipient\'s name and address.', 'fleuriste' ) );
			}
		}

		// Don't let WooCommerce send its own emails while the order is being built.
		$quiet = function () {
			return false;
		};
		foreach ( array( 'customer_processing_order', 'customer_on_hold_order', 'customer_completed_order' ) as $email_id ) {
			add_filter( 'woocommerce_email_enabled_' . $email_id, $quiet, 99 );
		}

		$edit_id = isset( $post['order_id'] ) ? absint( $post['order_id'] ) : 0;
		$editing = false;
		if ( $edit_id ) {
			$order = wc_get_order( $edit_id );
			if ( ! $order || ! in_array( $order->get_status(), fleuriste_po_editable_statuses(), true ) ) {
				$back( __( 'This order can no longer be changed here.', 'fleuriste' ) );
			}
			$editing   = true;
			$old_total = (float) $order->get_total();
			$old_items = array();
			foreach ( $order->get_items() as $old ) {
				$old_items[] = $old->get_quantity() . ' × ' . $old->get_name();
			}
			$old_date = fleuriste_board_order_data( $order )['date'];
			// Put the old items back into stock; the new ones are taken out again below.
			if ( $order->get_data_store()->get_stock_reduced( $order->get_id() ) ) {
				wc_increase_stock_levels( $order );
			}
			foreach ( array_keys( $order->get_items( array( 'line_item', 'fee', 'shipping' ) ) ) as $item_id ) {
				$order->remove_item( $item_id );
			}
			$order->save();
			$order = wc_get_order( $edit_id );
		} else {
			$order = wc_create_order( array( 'customer_id' => $email && ( $user = get_user_by( 'email', $email ) ) ? $user->ID : 0 ) );
			if ( is_wp_error( $order ) ) {
				$back( $order->get_error_message() );
			}
			$order->set_created_via( 'fleuriste-phone' );
		}
		$order->set_address( $billing, 'billing' );
		$order->set_address( $shipping, 'shipping' );

		foreach ( $lines as $i => $line ) {
			$data              = $line['data'];
			$data['date']      = $date;
			$data['slot']      = 0 === $i ? $slot : null; // Time slot fee is charged once (see fee line below).
			$data['message']   = 0 === $i ? $message : '';
			$data['anonymous'] = $anon;
			$unit              = (float) wc_get_price_excluding_tax( $line['product'] ) + fleuriste_fo_extra_price( array_merge( $data, array( 'slot' => null ) ) );
			$total             = $unit * $line['qty'];
			$item_id           = $order->add_product(
				$line['product'],
				$line['qty'],
				array(
					'subtotal' => $total,
					'total'    => $total,
				)
			);
			$item              = $order->get_item( $item_id );
			$data['slot']      = $slot ? array( 'label' => $slot['label'], 'price' => 0 ) : null; // Show the time on every line; its fee is a separate line.
			foreach ( fleuriste_fo_lines( $data ) as $meta ) {
				$item->add_meta_data( $meta[0], $meta[1], true );
			}
			$item->add_meta_data( '_fleuriste_delivery_date', $date, true );
			$item->save();
		}

		foreach ( $customs as $i => $c ) {
			$item = new WC_Order_Item_Product();
			$item->set_name( $c['name'] );
			$item->set_quantity( $c['qty'] );
			$item->set_subtotal( $c['price'] * $c['qty'] );
			$item->set_total( $c['price'] * $c['qty'] );
			$item->add_meta_data( '_fleuriste_custom_item', 'yes', true );
			$cdata = array(
				'size'      => null,
				'vase'      => null,
				'extras'    => array(),
				'date'      => $date,
				'slot'      => $slot ? array( 'label' => $slot['label'], 'price' => 0 ) : null,
				'message'   => ( ! $lines && 0 === $i ) ? $message : '',
				'anonymous' => $anon,
			);
			foreach ( fleuriste_fo_lines( $cdata ) as $meta ) {
				$item->add_meta_data( $meta[0], $meta[1], true );
			}
			$item->add_meta_data( '_fleuriste_delivery_date', $date, true );
			$order->add_item( $item );
		}

		// Discount (an amount or a percentage of the items).
		$disc_value = max( 0, (float) wc_format_decimal( isset( $post['discount'] ) ? $post['discount'] : 0 ) );
		$disc_type  = ( isset( $post['discount_type'] ) && 'percent' === $post['discount_type'] ) ? 'percent' : 'amount';
		if ( $disc_value > 0 ) {
			$items_sum = 0.0;
			foreach ( $order->get_items() as $it ) {
				$items_sum += (float) $it->get_total();
			}
			$disc = 'percent' === $disc_type ? $items_sum * min( 100, $disc_value ) / 100 : min( $disc_value, $items_sum );
			$disc = round( $disc, wc_get_price_decimals() );
			if ( $disc > 0 ) {
				$disc_item = new WC_Order_Item_Fee();
				/* translators: %s: percentage */
				$disc_item->set_name( 'percent' === $disc_type ? sprintf( __( 'Discount (%s%%)', 'fleuriste' ), wc_format_localized_decimal( $disc_value ) ) : __( 'Discount', 'fleuriste' ) );
				$disc_item->set_amount( -$disc );
				$disc_item->set_total( -$disc );
				$disc_item->set_tax_status( 'none' );
				$disc_item->add_meta_data( '_fleuriste_discount_type', $disc_type, true );
				$disc_item->add_meta_data( '_fleuriste_discount_value', $disc_value, true );
				$order->add_item( $disc_item );
			}
		}

		// Delivery fee and time-slot surcharge (none when delivery doesn't apply).
		$delivery = ! empty( $post['delivery'] );
		$fee      = $delivery ? max( 0, (float) wc_format_decimal( isset( $post['fee'] ) ? $post['fee'] : 0 ) ) : 0;
		if ( $delivery ) {
			$shipping_item = new WC_Order_Item_Shipping();
			$shipping_item->set_method_title( __( 'Delivery', 'fleuriste' ) );
			$shipping_item->set_method_id( 'flat_rate' );
			$shipping_item->set_total( $fee );
			$order->add_item( $shipping_item );
		}
		if ( $delivery && $slot && $slot['price'] > 0 ) {
			$fee_item = new WC_Order_Item_Fee();
			/* translators: %s: time slot */
			$fee_item->set_name( sprintf( __( 'Delivery time: %s', 'fleuriste' ), $slot['label'] ) );
			$fee_item->set_total( $slot['price'] );
			$fee_item->set_tax_status( 'none' );
			$order->add_item( $fee_item );
		}

		if ( $anon ) {
			$order->update_meta_data( '_wc_other/fleuriste/send-anonymously', '1' );
		} else {
			$order->delete_meta_data( '_wc_other/fleuriste/send-anonymously' );
		}
		if ( ! empty( $post['b_whatsapp'] ) ) {
			$order->update_meta_data( '_wc_other/fleuriste/whatsapp-updates', '1' );
		} else {
			$order->delete_meta_data( '_wc_other/fleuriste/whatsapp-updates' );
		}
		// No delivery on a main order = the customer collects at the shop.
		if ( ! $delivery && empty( $post['parent_id'] ) && ! $order->get_meta( '_fleuriste_parent_order' ) ) {
			$order->update_meta_data( '_fleuriste_pickup', 'yes' );
		} else {
			$order->delete_meta_data( '_fleuriste_pickup' );
		}
		// Delivery date and time for the whole order (as chosen at checkout for online orders).
		$order->update_meta_data( '_fleuriste_delivery_date', $date );
		if ( $slot ) {
			$order->update_meta_data( '_fleuriste_delivery_slot', $slot['label'] );
		} else {
			$order->delete_meta_data( '_fleuriste_delivery_slot' );
		}
		if ( 'store-api' === $order->get_created_via() ) {
			$is_pickup = 'yes' === $order->get_meta( '_fleuriste_pickup' );
			$order->delete_meta_data( '_wc_other/fleuriste/' . ( $is_pickup ? 'delivery' : 'pickup' ) . '-date' );
			$order->delete_meta_data( '_wc_other/fleuriste/' . ( $is_pickup ? 'delivery' : 'pickup' ) . '-slot' );
			$order->update_meta_data( '_wc_other/fleuriste/' . ( $is_pickup ? 'pickup' : 'delivery' ) . '-date', $date );
			if ( $slot ) {
				$order->update_meta_data( '_wc_other/fleuriste/' . ( $is_pickup ? 'pickup' : 'delivery' ) . '-slot', $slot['label'] );
			}
		}
		$driver = isset( $post['driver'] ) ? sanitize_key( $post['driver'] ) : '';
		if ( $driver && function_exists( 'fleuriste_driver' ) && fleuriste_driver( $driver ) ) {
			$order->update_meta_data( FLEURISTE_DRIVER_META, $driver );
		} elseif ( $editing && isset( $post['driver'] ) ) {
			$order->delete_meta_data( FLEURISTE_DRIVER_META );
		}
		$order->calculate_totals();

		if ( $editing ) {
			fleuriste_po_finish_edit( $order, $post, $old_total, $old_items, $old_date, $date, $text( 'pay' ), $quiet );
		}

		// Payment.
		$pay  = $text( 'pay' );
		$user = wp_get_current_user()->display_name;
		/* translators: %s: user name */
		$order->add_order_note( sprintf( __( 'Order taken by %s (phone / in shop).', 'fleuriste' ), $user ) );
		$copied_from = ! empty( $post['copied_from'] ) ? wc_get_order( absint( $post['copied_from'] ) ) : false;
		if ( $copied_from ) {
			$order->update_meta_data( '_fleuriste_copied_from', $copied_from->get_id() );
			$order->save();
			/* translators: %s: order number */
			$order->add_order_note( sprintf( __( 'Copied from order #%s.', 'fleuriste' ), $copied_from->get_order_number() ) );
			/* translators: 1: new order number, 2: user */
			$copied_from->add_order_note( sprintf( __( 'Duplicated as order #%1$s by %2$s.', 'fleuriste' ), $order->get_order_number(), $user ) );
		}
		$parent = ! empty( $post['parent_id'] ) ? wc_get_order( absint( $post['parent_id'] ) ) : false;
		if ( $parent && ! is_a( $parent, 'WC_Order_Refund' ) ) {
			fleuriste_link_suborder( $parent, $order );
		} else {
			$parent = false;
		}
		if ( ! empty( $post['note'] ) ) {
			$order->add_order_note( sanitize_text_field( $post['note'] ) );
		}
		$gateways = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : array();
		switch ( $pay ) {
			case 'instore':
				$order->set_payment_method( 'fleuriste_instore' );
				$order->set_payment_method_title( __( 'Paid in shop', 'fleuriste' ) );
				$order->save();
				$order->payment_complete();
				break;
			case 'bacs':
				$order->set_payment_method( 'bacs' );
				$order->set_payment_method_title( isset( $gateways['bacs'] ) ? $gateways['bacs']->get_title() : __( 'Bank transfer', 'fleuriste' ) );
				$order->save();
				$order->update_status( 'on-hold' );
				break;
			case 'link':
				$order->save(); // Stays "Pending payment" until the buyer pays.
				break;
			default:
				$order->set_payment_method( 'cod' );
				$order->set_payment_method_title( isset( $gateways['cod'] ) ? $gateways['cod']->get_title() : __( 'Cash on delivery', 'fleuriste' ) );
				$order->save();
				$order->update_status( 'processing' );
		}
		foreach ( array( 'customer_processing_order', 'customer_on_hold_order', 'customer_completed_order' ) as $email_id ) {
			remove_filter( 'woocommerce_email_enabled_' . $email_id, $quiet, 99 );
		}

		// Tell the buyer.
		$link_url = $order->get_checkout_payment_url();
		$wa_text  = fleuriste_buyer_message( $order );
		if ( 'link' === $pay ) {
			/* translators: 1: name, 2: order number, 3: total, 4: link */
			$wa_text = sprintf( __( 'Hi %1$s, here is the link to pay for your order #%2$s (%3$s): %4$s', 'fleuriste' ), $first, $order->get_order_number(), html_entity_decode( wp_strip_all_tags( wc_price( $order->get_total() ) ) ), $link_url ) . "\n\n– " . get_bloginfo( 'name' );
		}
		if ( $parent ) {
			$wa_text = fleuriste_suborder_message( $order, $parent, 'link' === $pay );
		}
		$done = array();
		if ( ! empty( $post['notify_email'] ) && $email && $parent ) {
			if ( fleuriste_email_buyer( $order, $wa_text ) ) {
				$done[] = 'link' === $pay ? __( 'extra items and payment link emailed', 'fleuriste' ) : __( 'extra items emailed', 'fleuriste' );
			}
		} elseif ( ! empty( $post['notify_email'] ) && $email ) {
			if ( 'link' === $pay ) {
				$mails = WC()->mailer()->get_emails();
				if ( isset( $mails['WC_Email_Customer_Invoice'] ) ) {
					$mails['WC_Email_Customer_Invoice']->trigger( $order->get_id(), $order );
					$done[] = __( 'payment link emailed', 'fleuriste' );
				}
			} elseif ( fleuriste_email_buyer( $order, $wa_text ) ) {
				$done[] = __( 'confirmation emailed', 'fleuriste' );
			}
		}
		$wa_link = '';
		if ( ! empty( $post['notify_wa'] ) ) {
			$res = ( 'link' === $pay || $parent ) ? array( false, 'no-api' ) : fleuriste_whatsapp_buyer_api( $order );
			if ( $res[0] ) {
				$done[] = __( 'WhatsApp sent', 'fleuriste' );
			} else {
				$wa_link = fleuriste_whatsapp_buyer_link( $order, $wa_text );
			}
		}
		if ( $done || $wa_link ) {
			/* translators: %s: what was sent */
			$order->add_order_note( sprintf( __( 'Buyer contacted: %s', 'fleuriste' ), implode( ', ', array_merge( $done, $wa_link ? array( __( 'WhatsApp from the shop\'s phone', 'fleuriste' ) ) : array() ) ) ) );
		}

		set_transient(
			'fleuriste_po_done_' . get_current_user_id(),
			array(
				'id'     => $order->get_id(),
				'number' => $order->get_order_number(),
				'edit'   => $order->get_edit_order_url(),
				'total'  => html_entity_decode( wp_strip_all_tags( wc_price( $order->get_total() ) ) ),
				'done'   => $done,
				'wa'     => $wa_link,
				'link'   => 'link' === $pay ? $link_url : '',
				'parent' => $parent ? $parent->get_order_number() : '',
			),
			300
		);
		$view = fleuriste_board_day() === $date ? 'today' : ( fleuriste_board_day( 1 ) === $date ? 'tomorrow' : 'upcoming' );
		wp_safe_redirect( admin_url( 'admin.php?page=fleuriste-deliveries&view=' . $view ) );
		exit;
	}
);

/**
 * Finish saving an edited order: payment changes, stock, notes, buyer message, redirect.
 *
 * @param WC_Order $order     Order.
 * @param array    $post      Posted data.
 * @param float    $old_total Total before the change.
 * @param string[] $old_items Items before the change.
 * @param string   $old_date  Delivery date before the change.
 * @param string   $date      New delivery date.
 * @param string   $pay       Payment choice.
 * @param callable $quiet     Email-silencing filter to remove at the end.
 */
function fleuriste_po_finish_edit( $order, $post, $old_total, $old_items, $old_date, $date, $pay, $quiet ) {
	$status   = $order->get_status();
	$gateways = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : array();
	$current  = $order->get_payment_method();
	$is_cod   = 'cod' === $current;

	// Payment changes are only allowed while nothing has been paid online.
	if ( 'online' !== $pay ) {
		if ( 'instore' === $pay && 'fleuriste_instore' !== $current ) {
			$order->set_payment_method( 'fleuriste_instore' );
			$order->set_payment_method_title( __( 'Paid in shop', 'fleuriste' ) );
			$order->set_date_paid( time() );
			if ( in_array( $status, array( 'pending', 'on-hold' ), true ) ) {
				$order->save();
				$order->payment_complete();
			}
		} elseif ( 'cod' === $pay && ! $is_cod ) {
			$order->set_payment_method( 'cod' );
			$order->set_payment_method_title( isset( $gateways['cod'] ) ? $gateways['cod']->get_title() : __( 'Cash on delivery', 'fleuriste' ) );
			if ( in_array( $status, array( 'pending', 'on-hold' ), true ) ) {
				$order->set_status( 'processing' );
			}
		} elseif ( 'bacs' === $pay && 'bacs' !== $current ) {
			$order->set_payment_method( 'bacs' );
			$order->set_payment_method_title( isset( $gateways['bacs'] ) ? $gateways['bacs']->get_title() : __( 'Bank transfer', 'fleuriste' ) );
			if ( 'pending' === $status ) {
				$order->set_status( 'on-hold' );
			}
		} elseif ( 'link' === $pay && 'pending' !== $status ) {
			$order->set_payment_method( '' );
			$order->set_payment_method_title( '' );
			$order->set_status( 'pending' );
		}
	}
	$order->save();

	// Take the (new) items out of stock again for orders that hold stock.
	if ( in_array( $order->get_status(), array( 'on-hold', 'processing', 'preparing', 'out-for-delivery' ), true ) ) {
		wc_reduce_stock_levels( $order->get_id() );
	}

	// Note what changed.
	$new_items = array();
	foreach ( $order->get_items() as $item ) {
		$new_items[] = $item->get_quantity() . ' × ' . $item->get_name();
	}
	$fmt     = function ( $v ) {
		return html_entity_decode( wp_strip_all_tags( wc_price( $v ) ) );
	};
	$changes = array();
	if ( $new_items !== $old_items ) {
		/* translators: 1: old items, 2: new items */
		$changes[] = sprintf( __( 'items %1$s → %2$s', 'fleuriste' ), implode( ', ', $old_items ), implode( ', ', $new_items ) );
	}
	if ( $old_date !== $date ) {
		/* translators: 1: old date, 2: new date */
		$changes[] = sprintf( __( 'delivery date %1$s → %2$s', 'fleuriste' ), $old_date, $date );
	}
	if ( abs( $old_total - (float) $order->get_total() ) > 0.001 ) {
		/* translators: 1: old total, 2: new total */
		$changes[] = sprintf( __( 'total %1$s → %2$s', 'fleuriste' ), $fmt( $old_total ), $fmt( $order->get_total() ) );
	}
	/* translators: 1: user, 2: changes */
	$order->add_order_note( sprintf( __( 'Order changed by %1$s: %2$s.', 'fleuriste' ), wp_get_current_user()->display_name, $changes ? implode( '; ', $changes ) : __( 'details updated', 'fleuriste' ) ) );
	if ( ! empty( $post['note'] ) ) {
		$order->add_order_note( sanitize_text_field( $post['note'] ) );
	}

	// Money already paid online.
	$balance = '';
	$diff    = (float) $order->get_total() - $old_total;
	if ( 'online' === $pay && abs( $diff ) > 0.001 ) {
		$balance = $diff > 0
			/* translators: %s: amount */
			? sprintf( __( 'The buyer already paid online – collect the extra %s.', 'fleuriste' ), $fmt( $diff ) )
			/* translators: %s: amount */
			: sprintf( __( 'The buyer already paid online – refund %s in the order screen.', 'fleuriste' ), $fmt( -$diff ) );
		$order->add_order_note( $balance );
	}

	foreach ( array( 'customer_processing_order', 'customer_on_hold_order', 'customer_completed_order' ) as $email_id ) {
		remove_filter( 'woocommerce_email_enabled_' . $email_id, $quiet, 99 );
	}

	// Tell the buyer.
	$data = fleuriste_board_order_data( $order );
	$when = $data['date'] ? date_i18n( 'l j F', strtotime( $data['date'] ) ) . ( $data['slot'] ? ' (' . $data['slot'] . ')' : '' ) : '';
	$text = sprintf(
		/* translators: 1: name, 2: order number */
		__( 'Hi %1$s, we have updated your order #%2$s.', 'fleuriste' ),
		$order->get_billing_first_name(),
		$order->get_order_number()
	) . "\n\n" . implode( "\n", $new_items )
		/* translators: %s: date */
		. ( $when ? "\n" . sprintf( __( 'Delivery: %s', 'fleuriste' ), $when ) : '' )
		/* translators: %s: total */
		. "\n" . sprintf( __( 'Total: %s', 'fleuriste' ), $fmt( $order->get_total() ) )
		. ( 'pending' === $order->get_status() ? "\n" . __( 'Pay here:', 'fleuriste' ) . ' ' . $order->get_checkout_payment_url() : '' )
		. "\n\n" . __( 'Track your order:', 'fleuriste' ) . ' ' . fleuriste_track_url( $order ) . "\n\n– " . get_bloginfo( 'name' );
	$done = array();
	if ( ! empty( $post['notify_email'] ) && $order->get_billing_email() && fleuriste_email_buyer( $order, $text ) ) {
		$done[] = __( 'buyer emailed', 'fleuriste' );
	}
	$wa_link = '';
	if ( ! empty( $post['notify_wa'] ) ) {
		$wa_link = fleuriste_whatsapp_buyer_link( $order, $text );
	}
	if ( $done || $wa_link ) {
		/* translators: 1: channels, 2: message */
		$order->add_order_note( sprintf( __( 'Buyer told about the change (%1$s): %2$s', 'fleuriste' ), implode( ', ', array_merge( $done, $wa_link ? array( 'WhatsApp' ) : array() ) ), $text ) );
	}

	set_transient(
		'fleuriste_po_done_' . get_current_user_id(),
		array(
			'id'      => $order->get_id(),
			'number'  => $order->get_order_number(),
			'edit'    => $order->get_edit_order_url(),
			'total'   => $fmt( $order->get_total() ),
			'done'    => array_merge( $done, $balance ? array( $balance ) : array() ),
			'wa'      => $wa_link,
			'link'    => 'pending' === $order->get_status() ? $order->get_checkout_payment_url() : '',
			'updated' => true,
		),
		300
	);
	$view = fleuriste_board_day() === $date ? 'today' : ( fleuriste_board_day( 1 ) === $date ? 'tomorrow' : 'upcoming' );
	wp_safe_redirect( admin_url( 'admin.php?page=fleuriste-deliveries&view=' . $view ) );
	exit;
}

// "Order created" message on the board.
add_action(
	'admin_notices',
	function () {
		$screen = get_current_screen();
		if ( ! $screen || 'toplevel_page_fleuriste-deliveries' !== $screen->id ) {
			return;
		}
		$key  = 'fleuriste_po_done_' . get_current_user_id();
		$done = get_transient( $key );
		if ( ! $done ) {
			return;
		}
		delete_transient( $key );
		if ( ! empty( $done['updated'] ) ) {
			/* translators: 1: order number, 2: total */
			$headline = sprintf( __( 'Order #%1$s updated (new total %2$s).', 'fleuriste' ), $done['number'], $done['total'] );
		} elseif ( ! empty( $done['parent'] ) ) {
			/* translators: 1: suborder number, 2: order number, 3: total */
			$headline = sprintf( __( 'Suborder #%1$s added to order #%2$s (%3$s).', 'fleuriste' ), $done['number'], $done['parent'], $done['total'] );
		} else {
			/* translators: 1: order number, 2: total */
			$headline = sprintf( __( 'Order #%1$s created (%2$s).', 'fleuriste' ), $done['number'], $done['total'] );
		}
		echo '<div class="notice notice-success is-dismissible fl-po-done"><p><strong>' . esc_html( $headline ) . '</strong> ';
		if ( $done['done'] ) {
			echo esc_html( rtrim( ucfirst( implode( ', ', $done['done'] ) ), '.' ) . '.' ) . ' ';
		}
		echo '<a href="' . esc_url( $done['edit'] ) . '">' . esc_html__( 'Open order', 'fleuriste' ) . '</a>';
		if ( ! empty( $done['id'] ) && function_exists( 'fleuriste_doc_board_buttons' ) ) {
			echo ' · <span class="fl-po-done__docs">' . fleuriste_doc_board_buttons( $done['id'] ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped while building.
		}
		if ( $done['wa'] ) {
			echo ' · <a class="button button-primary" href="' . esc_url( $done['wa'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Send on WhatsApp', 'fleuriste' ) . '</a>';
		}
		if ( $done['link'] ) {
			echo '</p><p>' . esc_html__( 'Payment link:', 'fleuriste' ) . ' <input type="text" readonly value="' . esc_attr( $done['link'] ) . '" onclick="this.select()" style="width:420px;max-width:100%">';
		}
		echo ' · <a href="' . esc_url( admin_url( 'admin.php?page=fleuriste-new-order' ) ) . '">' . esc_html__( 'Take another order', 'fleuriste' ) . '</a></p></div>';
	}
);

/* -------------------------------------------------------------------------
 * "Duplicate" links in WooCommerce → Orders and on the order screen
 * ---------------------------------------------------------------------- */

/**
 * Link to the order form pre-filled from an order.
 *
 * @param int $order_id Order ID.
 * @return string
 */
function fleuriste_po_copy_url( $order_id ) {
	return admin_url( 'admin.php?page=fleuriste-new-order&copy=' . absint( $order_id ) );
}

add_filter(
	'woocommerce_admin_order_actions',
	function ( $actions, $order ) {
		$actions['fleuriste_copy'] = array(
			'url'    => fleuriste_po_copy_url( $order->get_id() ),
			'name'   => __( 'Duplicate', 'fleuriste' ),
			'action' => 'fleuriste-copy',
		);
		return $actions;
	},
	10,
	2
);

add_action(
	'admin_head',
	function () {
		echo '<style>.wc-action-button-fleuriste-copy::after{font-family:dashicons!important;content:"\f105"!important}</style>';
	}
);

add_action(
	'woocommerce_admin_order_data_after_order_details',
	function ( $order ) {
		if ( ! $order->get_id() || 'auto-draft' === $order->get_status() ) {
			return;
		}
		echo '<p class="form-field form-field-wide"><a class="button" href="' . esc_url( fleuriste_po_copy_url( $order->get_id() ) ) . '"><span class="dashicons dashicons-admin-page" style="vertical-align:text-bottom"></span> ' . esc_html__( 'Duplicate this order', 'fleuriste' ) . '</a></p>';
	}
);
