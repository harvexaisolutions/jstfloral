<?php
/**
 * Change an order's delivery from the order screen: delivery to a region, pickup
 * from the shop, or another method typed in by hand – with any date and time.
 *
 * Box "Delivery / pickup" on the WooCommerce order screen (and "Change delivery"
 * on the Orders & Deliveries board).
 *
 * @package Fleuriste
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Current delivery settings of an order, for the box.
 *
 * @param WC_Order $order Order.
 * @return array method (delivery|pickup|custom), region, fee, custom_name, date, slot, slot_fee.
 */
function fleuriste_dla_current( $order ) {
	$data     = function_exists( 'fleuriste_board_order_data' ) ? fleuriste_board_order_data( $order ) : array( 'date' => '', 'slot' => '' );
	$method   = 'delivery';
	$fee      = 0.0;
	$name     = '';
	$shipping = $order->get_items( 'shipping' );
	$ship     = $shipping ? reset( $shipping ) : null;
	if ( function_exists( 'fleuriste_order_is_pickup' ) && fleuriste_order_is_pickup( $order ) ) {
		$method = 'pickup';
	} elseif ( $ship ) {
		$fee = (float) $ship->get_total();
		if ( ! in_array( $ship->get_method_id(), array( 'fleuriste_delivery', 'flat_rate', 'free_shipping' ), true ) ) {
			$method = 'custom';
			$name   = $ship->get_name();
		} elseif ( 'flat_rate' === $ship->get_method_id() && __( 'Delivery', 'fleuriste' ) !== $ship->get_name() && ! fleuriste_dl_region( $order->get_shipping_state() ) ) {
			$method = 'custom';
			$name   = $ship->get_name();
		}
	} else {
		$method = 'none';
	}
	$slot_fee = 0.0;
	foreach ( $order->get_fees() as $f ) {
		if ( 0 === strpos( $f->get_name(), __( 'Delivery time:', 'fleuriste' ) ) ) {
			$slot_fee += (float) $f->get_total();
		}
	}
	return array(
		'method'      => $method,
		'region'      => strtoupper( (string) $order->get_shipping_state() ),
		'fee'         => $fee,
		'custom_name' => $name,
		'date'        => (string) $data['date'],
		'slot'        => (string) $data['slot'],
		'slot_fee'    => $slot_fee,
	);
}

/**
 * Change the delivery of an order.
 *
 * @param WC_Order $order Order.
 * @param array    $args  method (delivery|pickup|custom|none), region, fee, custom_name, date (Y-m-d), slot, slot_fee.
 * @return string Summary for the order note.
 */
function fleuriste_dla_apply( $order, $args ) {
	$args = wp_parse_args(
		$args,
		array(
			'method'      => 'delivery',
			'region'      => '',
			'fee'         => 0,
			'custom_name' => '',
			'date'        => '',
			'slot'        => '',
			'slot_fee'    => 0,
		)
	);
	$method   = in_array( $args['method'], array( 'delivery', 'pickup', 'custom', 'none' ), true ) ? $args['method'] : 'delivery';
	$fee      = max( 0, (float) $args['fee'] );
	$slot_fee = 'pickup' === $method || 'none' === $method ? 0 : max( 0, (float) $args['slot_fee'] );
	$region   = fleuriste_dl_region( $args['region'] );

	// Remove the old delivery line and time-slot charge.
	foreach ( $order->get_items( 'shipping' ) as $id => $item ) {
		$order->remove_item( $id );
	}
	foreach ( $order->get_fees() as $id => $item ) {
		if ( 0 === strpos( $item->get_name(), __( 'Delivery time:', 'fleuriste' ) ) ) {
			$order->remove_item( $id );
		}
	}

	$summary = '';
	if ( 'pickup' === $method ) {
		$ship = new WC_Order_Item_Shipping();
		$ship->set_method_title( fleuriste_dl_get( 'pickup_title' ) );
		$ship->set_method_id( 'pickup_location' );
		$ship->set_total( 0 );
		$ship->add_meta_data( 'pickup_location', get_bloginfo( 'name' ), true );
		if ( fleuriste_dl_pickup_address() ) {
			$ship->add_meta_data( 'pickup_address', fleuriste_dl_pickup_address(), true );
		}
		$order->add_item( $ship );
		$order->update_meta_data( '_fleuriste_pickup', 'yes' );
		$summary = fleuriste_dl_get( 'pickup_title' );
	} elseif ( 'none' !== $method ) {
		$ship = new WC_Order_Item_Shipping();
		if ( 'custom' === $method ) {
			$name = '' !== trim( $args['custom_name'] ) ? sanitize_text_field( $args['custom_name'] ) : __( 'Delivery', 'fleuriste' );
			$ship->set_method_title( $name );
			$ship->set_method_id( 'fleuriste_custom' );
		} else {
			/* translators: %s: region */
			$name = $region ? sprintf( __( 'Delivery – %s', 'fleuriste' ), $region['name'] ) : __( 'Delivery', 'fleuriste' );
			$ship->set_method_title( $name );
			$ship->set_method_id( 'fleuriste_delivery' );
			if ( $region ) {
				$order->set_shipping_state( $region['id'] );
			}
		}
		$ship->set_total( $fee );
		$order->add_item( $ship );
		$order->delete_meta_data( '_fleuriste_pickup' );
		$summary = $name . ' (' . html_entity_decode( wp_strip_all_tags( wc_price( $fee ) ) ) . ')';
	} else {
		$order->delete_meta_data( '_fleuriste_pickup' );
		$summary = __( 'No delivery', 'fleuriste' );
	}

	$slot = sanitize_text_field( (string) $args['slot'] );
	if ( $slot_fee > 0 && '' !== $slot ) {
		$f = new WC_Order_Item_Fee();
		/* translators: %s: time slot */
		$f->set_name( sprintf( __( 'Delivery time: %s', 'fleuriste' ), $slot ) );
		$f->set_amount( $slot_fee );
		$f->set_total( $slot_fee );
		$f->set_tax_status( 'none' );
		$order->add_item( $f );
	}
	$order->save();

	// Date and time on the order and its items (board, drivers, invoices, emails).
	$date = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $args['date'] ) ? $args['date'] : '';
	if ( 'store-api' === $order->get_created_via() ) {
		foreach ( array( 'delivery', 'pickup' ) as $kind ) {
			$order->delete_meta_data( '_wc_other/fleuriste/' . $kind . '-date' );
			$order->delete_meta_data( '_wc_other/fleuriste/' . $kind . '-slot' );
		}
	}
	if ( '' === $slot ) {
		foreach ( $order->get_items() as $item ) {
			$item->delete_meta_data( __( 'Delivery time', 'fleuriste' ) );
			$item->save();
		}
		$order->delete_meta_data( '_fleuriste_delivery_slot' );
	}
	if ( function_exists( 'fleuriste_dc_apply_to_order' ) ) {
		fleuriste_dc_apply_to_order( $order, $date, $slot );
	}
	$order->calculate_totals( false );

	$when = $date ? date_i18n( 'l j F', strtotime( $date ) ) . ( $slot ? ' · ' . $slot : '' ) : '';
	return $summary . ( $when ? ' – ' . $when : '' );
}

/* -------------------------------------------------------------------------
 * The box on the order screen
 * ---------------------------------------------------------------------- */

add_action(
	'add_meta_boxes',
	function () {
		$screen = function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';
		add_meta_box( 'fleuriste-delivery-box', __( 'Delivery / pickup', 'fleuriste' ), 'fleuriste_dla_box', $screen, 'side', 'high' );
	},
	30
);

/**
 * Print the box.
 *
 * @param WP_Post|WC_Order $post_or_order Order.
 */
function fleuriste_dla_box( $post_or_order ) {
	$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
	if ( ! $order ) {
		return;
	}
	$c       = fleuriste_dla_current( $order );
	$dslots  = fleuriste_fo_choices( 'fleuriste_fo_slots' );
	$pslots  = fleuriste_dl_pickup_slots();
	$known   = array_merge( wp_list_pluck( $dslots, 'label' ), $pslots );
	$other   = '' !== $c['slot'] && ! in_array( $c['slot'], $known, true );
	$regions = fleuriste_dl_regions();
	wp_nonce_field( 'fleuriste_dla', 'fleuriste_dla_nonce' );
	?>
	<div class="fl-dla">
		<input type="hidden" name="fl_dla_was" value="<?php echo esc_attr( md5( wp_json_encode( $c ) ) ); ?>">
		<p class="fl-dla__methods">
			<label><input type="radio" name="fl_dla_method" value="delivery" <?php checked( 'delivery', $c['method'] ); ?>> <?php esc_html_e( 'Delivery', 'fleuriste' ); ?></label>
			<label><input type="radio" name="fl_dla_method" value="pickup" <?php checked( 'pickup', $c['method'] ); ?>> <?php esc_html_e( 'Pickup from the shop', 'fleuriste' ); ?></label>
			<label><input type="radio" name="fl_dla_method" value="custom" <?php checked( 'custom', $c['method'] ); ?>> <?php esc_html_e( 'Other (type it in)', 'fleuriste' ); ?></label>
			<label><input type="radio" name="fl_dla_method" value="none" <?php checked( 'none', $c['method'] ); ?>> <?php esc_html_e( 'None (goes with another order)', 'fleuriste' ); ?></label>
		</p>
		<p class="fl-dla__for fl-dla--delivery">
			<label><?php esc_html_e( 'Region', 'fleuriste' ); ?><br>
				<select name="fl_dla_region" class="fl-dla-region" style="width:100%">
					<option value="" data-price=""><?php esc_html_e( 'Choose…', 'fleuriste' ); ?></option>
					<?php foreach ( $regions as $r ) : ?>
						<option value="<?php echo esc_attr( $r['id'] ); ?>" data-price="<?php echo esc_attr( $r['price'] ); ?>" <?php selected( strtoupper( $r['id'] ), $c['region'] ); ?>><?php echo esc_html( $r['name'] . ' (' . fleuriste_fo_money( $r['price'] ) . ')' ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
		</p>
		<p class="fl-dla__for fl-dla--custom">
			<label><?php esc_html_e( 'Method name', 'fleuriste' ); ?><br><input type="text" name="fl_dla_custom_name" value="<?php echo esc_attr( $c['custom_name'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Courier to Rodrigues', 'fleuriste' ); ?>" style="width:100%"></label>
		</p>
		<p class="fl-dla__for fl-dla--delivery fl-dla--custom">
			<label><?php esc_html_e( 'Delivery fee', 'fleuriste' ); ?> (<?php echo esc_html( trim( html_entity_decode( get_woocommerce_currency_symbol() ), " \xC2\xA0" ) ); ?>)<br><input type="number" name="fl_dla_fee" class="fl-dla-fee" value="<?php echo esc_attr( $c['fee'] ); ?>" min="0" step="any" style="width:100%"></label>
		</p>
		<p>
			<label><span class="fl-dla-datelabel"><?php esc_html_e( 'Date', 'fleuriste' ); ?></span><br><input type="date" name="fl_dla_date" value="<?php echo esc_attr( $c['date'] ); ?>" style="width:100%"></label>
		</p>
		<p>
			<label><?php esc_html_e( 'Time', 'fleuriste' ); ?><br>
				<select name="fl_dla_slot" class="fl-dla-slot" style="width:100%">
					<option value=""><?php esc_html_e( 'No time', 'fleuriste' ); ?></option>
					<?php if ( $dslots ) : ?>
						<optgroup label="<?php esc_attr_e( 'Delivery times', 'fleuriste' ); ?>">
							<?php foreach ( $dslots as $s ) : ?>
								<option value="<?php echo esc_attr( $s['label'] ); ?>" data-price="<?php echo esc_attr( $s['price'] ); ?>" <?php selected( $s['label'], $c['slot'] ); ?>><?php echo esc_html( $s['label'] . ( $s['price'] > 0 ? ' (+' . fleuriste_fo_money( $s['price'] ) . ')' : '' ) ); ?></option>
							<?php endforeach; ?>
						</optgroup>
					<?php endif; ?>
					<?php if ( $pslots ) : ?>
						<optgroup label="<?php esc_attr_e( 'Pickup times', 'fleuriste' ); ?>">
							<?php foreach ( $pslots as $s ) : ?>
								<option value="<?php echo esc_attr( $s ); ?>" data-price="0" <?php selected( $s, $c['slot'] ); ?>><?php echo esc_html( $s ); ?></option>
							<?php endforeach; ?>
						</optgroup>
					<?php endif; ?>
					<option value="__other" <?php selected( $other ); ?>><?php esc_html_e( 'Other (type it in)…', 'fleuriste' ); ?></option>
				</select>
			</label>
			<input type="text" name="fl_dla_slot_other" class="fl-dla-other" value="<?php echo esc_attr( $other ? $c['slot'] : '' ); ?>" placeholder="<?php esc_attr_e( 'e.g. 5:30pm sharp', 'fleuriste' ); ?>" style="width:100%;margin-top:4px">
		</p>
		<p class="fl-dla__for fl-dla--delivery fl-dla--custom">
			<label><?php esc_html_e( 'Time slot charge', 'fleuriste' ); ?> (<?php echo esc_html( trim( html_entity_decode( get_woocommerce_currency_symbol() ), " \xC2\xA0" ) ); ?>)<br><input type="number" name="fl_dla_slot_fee" class="fl-dla-slotfee" value="<?php echo esc_attr( $c['slot_fee'] ); ?>" min="0" step="any" style="width:100%"></label>
		</p>
		<p class="description"><?php esc_html_e( 'Any date and time can be chosen here. Click Update to save; the total is recalculated. Use "Notify buyer" on the board to tell the customer.', 'fleuriste' ); ?></p>
	</div>
	<style>.fl-dla__methods label{display:block;margin:0 0 4px}.fl-dla p{margin:0 0 10px}</style>
	<script>
	( function () {
		var box = document.querySelector( '.fl-dla' );
		if ( ! box ) { return; }
		function sync() {
			var m = ( box.querySelector( '[name=fl_dla_method]:checked' ) || {} ).value || 'delivery';
			box.querySelectorAll( '.fl-dla__for' ).forEach( function ( el ) { el.style.display = el.classList.contains( 'fl-dla--' + m ) ? '' : 'none'; } );
			var s = box.querySelector( '.fl-dla-slot' );
			box.querySelector( '.fl-dla-other' ).style.display = s.value === '__other' ? '' : 'none';
			box.querySelector( '.fl-dla-datelabel' ).textContent = m === 'pickup' ? <?php echo wp_json_encode( __( 'Pickup date', 'fleuriste' ) ); ?> : <?php echo wp_json_encode( __( 'Delivery date', 'fleuriste' ) ); ?>;
		}
		box.addEventListener( 'change', function ( e ) {
			if ( e.target.classList.contains( 'fl-dla-region' ) ) {
				var o = e.target.options[ e.target.selectedIndex ];
				if ( o && o.dataset.price !== '' ) { box.querySelector( '.fl-dla-fee' ).value = o.dataset.price; }
			}
			if ( e.target.classList.contains( 'fl-dla-slot' ) ) {
				var so = e.target.options[ e.target.selectedIndex ];
				if ( so && so.dataset.price !== undefined ) { box.querySelector( '.fl-dla-slotfee' ).value = so.dataset.price || 0; }
			}
			sync();
		} );
		sync();
	} )();
	</script>
	<?php
}

/**
 * Save the box when the order is updated.
 *
 * @param int $order_id Order ID.
 */
function fleuriste_dla_save( $order_id ) {
	if ( empty( $_POST['fleuriste_dla_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['fleuriste_dla_nonce'] ) ), 'fleuriste_dla' ) || ! current_user_can( 'edit_shop_orders' ) ) {
		return;
	}
	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}
	$post = wp_unslash( $_POST );
	$slot = isset( $post['fl_dla_slot'] ) ? sanitize_text_field( $post['fl_dla_slot'] ) : '';
	if ( '__other' === $slot ) {
		$slot = isset( $post['fl_dla_slot_other'] ) ? sanitize_text_field( $post['fl_dla_slot_other'] ) : '';
	}
	$args = array(
		'method'      => isset( $post['fl_dla_method'] ) ? sanitize_key( $post['fl_dla_method'] ) : 'delivery',
		'region'      => isset( $post['fl_dla_region'] ) ? sanitize_text_field( $post['fl_dla_region'] ) : '',
		'fee'         => isset( $post['fl_dla_fee'] ) ? (float) wc_format_decimal( $post['fl_dla_fee'] ) : 0,
		'custom_name' => isset( $post['fl_dla_custom_name'] ) ? sanitize_text_field( $post['fl_dla_custom_name'] ) : '',
		'date'        => isset( $post['fl_dla_date'] ) ? sanitize_text_field( $post['fl_dla_date'] ) : '',
		'slot'        => $slot,
		'slot_fee'    => isset( $post['fl_dla_slot_fee'] ) ? (float) wc_format_decimal( $post['fl_dla_slot_fee'] ) : 0,
	);
	// Only when something in the box changed.
	$now = fleuriste_dla_current( $order );
	$new = array(
		'method'      => $args['method'],
		'region'      => strtoupper( 'delivery' === $args['method'] ? $args['region'] : $now['region'] ),
		'fee'         => in_array( $args['method'], array( 'delivery', 'custom' ), true ) ? $args['fee'] : 0.0,
		'custom_name' => 'custom' === $args['method'] ? $args['custom_name'] : '',
		'date'        => $args['date'],
		'slot'        => $args['slot'],
		'slot_fee'    => in_array( $args['method'], array( 'delivery', 'custom' ), true ) ? $args['slot_fee'] : 0.0,
	);
	$was = array_merge( $now, array( 'fee' => (float) $now['fee'], 'slot_fee' => (float) $now['slot_fee'] ) );
	if ( 'custom' !== $was['method'] ) {
		$was['custom_name'] = '';
	}
	if ( in_array( $was['method'], array( 'pickup', 'none' ), true ) ) {
		$was['fee']      = 0.0;
		$was['slot_fee'] = 0.0;
	}
	if ( wp_json_encode( $was ) === wp_json_encode( array_merge( $new, array( 'fee' => (float) $new['fee'], 'slot_fee' => (float) $new['slot_fee'] ) ) ) ) {
		return;
	}
	remove_action( 'woocommerce_process_shop_order_meta', 'fleuriste_dla_save', 60 );
	$summary = fleuriste_dla_apply( $order, $args );
	/* translators: 1: user, 2: new delivery */
	$order->add_order_note( sprintf( __( 'Delivery changed by %1$s: %2$s.', 'fleuriste' ), wp_get_current_user()->display_name, $summary ) );
}
add_action( 'woocommerce_process_shop_order_meta', 'fleuriste_dla_save', 60 );
