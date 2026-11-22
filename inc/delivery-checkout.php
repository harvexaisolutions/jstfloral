<?php
/**
 * Delivery date and time, chosen once at checkout for the whole order.
 *
 * - Two checkout fields (Checkout block, "Additional order information"):
 *   Delivery date (respects the same-day cut-off, closed days and "book up to")
 *   and Delivery time (the slots from Shop settings → Product options).
 * - A slot with a price ("Evening | 100") adds a fee line to the order.
 * - When the order is placed, the date and time are copied onto every item, so the
 *   Orders & Deliveries board, drivers, reports, invoices and the order editor keep working.
 *
 * @package Fleuriste
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FLEURISTE_DC_DATE', 'fleuriste/delivery-date' );
define( 'FLEURISTE_DC_SLOT', 'fleuriste/delivery-slot' );
define( 'FLEURISTE_DC_PDATE', 'fleuriste/pickup-date' );
define( 'FLEURISTE_DC_PSLOT', 'fleuriste/pickup-slot' );

/**
 * Are delivery choices on? (Shop settings → Product options → florist options enabled.)
 *
 * @return bool
 */
function fleuriste_dc_enabled() {
	return function_exists( 'fleuriste_fo_get' ) && 'yes' === fleuriste_fo_get( 'fleuriste_fo_enabled' );
}

/**
 * Price of a time slot, by its label.
 *
 * @param string $label Slot label.
 * @return float
 */
function fleuriste_dc_slot_price( $label ) {
	foreach ( fleuriste_fo_choices( 'fleuriste_fo_slots' ) as $slot ) {
		if ( (string) $slot['label'] === (string) $label ) {
			return (float) $slot['price'];
		}
	}
	return 0.0;
}

/**
 * Bookable dates, for shops on a WooCommerce version without date fields.
 *
 * @return array Y-m-d => label.
 */
function fleuriste_dc_date_options( $mode = 'delivery' ) {
	$tz      = wp_timezone();
	$today   = new DateTimeImmutable( 'today', $tz );
	$date    = DateTimeImmutable::createFromFormat( '!Y-m-d', fleuriste_fo_earliest_date( $mode ), $tz );
	$last    = fleuriste_fo_latest_date();
	$options = array();
	for ( $i = 0; $i < 400 && $date->format( 'Y-m-d' ) <= $last; $i++, $date = $date->modify( '+1 day' ) ) {
		$value = $date->format( 'Y-m-d' );
		if ( fleuriste_fo_date_error( $value, $mode ) ) {
			continue;
		}
		$label = date_i18n( 'D j M', $date->getTimestamp() + $date->getOffset() );
		if ( $value === $today->format( 'Y-m-d' ) ) {
			/* translators: %s: date */
			$label = sprintf( __( 'Today, %s', 'fleuriste' ), $label );
		} elseif ( $value === $today->modify( '+1 day' )->format( 'Y-m-d' ) ) {
			/* translators: %s: date */
			$label = sprintf( __( 'Tomorrow, %s', 'fleuriste' ), $label );
		}
		$options[] = array(
			'value' => $value,
			'label' => $label,
		);
	}
	return $options;
}

/* -------------------------------------------------------------------------
 * The checkout fields
 * ---------------------------------------------------------------------- */

/**
 * Register a date field for delivery or pickup.
 *
 * @param string     $id     Field ID.
 * @param string     $label  Label.
 * @param string     $mode   delivery|pickup.
 * @param array|null $hidden Rule for hiding the field.
 */
function fleuriste_dc_register_date( $id, $label, $mode, $hidden ) {
	$field = array(
		'id'                => $id,
		'label'             => $label,
		'location'          => 'order',
		'required'          => true,
		'validate_callback' => function ( $value ) use ( $mode ) {
			if ( null === $value || '' === $value ) {
				return null; // "Required" is checked by WooCommerce (and skipped when the field is hidden).
			}
			$error = fleuriste_fo_date_error( (string) $value, $mode );
			return $error ? new WP_Error( 'fleuriste_' . $mode . '_date', $error ) : null;
		},
	);
	if ( $hidden ) {
		$field['hidden'] = $hidden;
	}
	if ( version_compare( WC()->version, '11.2', '>=' ) ) {
		// Date picker (WooCommerce 11.2+), limited to the bookable range.
		$field['type'] = 'date';
		$field['min']  = fleuriste_fo_earliest_date( $mode );
		$field['max']  = fleuriste_fo_latest_date();
	} else {
		$field['type']    = 'select';
		$field['options'] = fleuriste_dc_date_options( $mode );
	}
	woocommerce_register_additional_checkout_field( $field );
}

/**
 * Rule: field shown only for delivery (true) or only for pickup (false).
 *
 * @param bool $pickup Hide when the customer picks up?
 * @return array
 */
function fleuriste_dc_rule( $pickup ) {
	return array(
		'type'       => 'object',
		'properties' => array(
			'cart' => array(
				'properties' => array(
					'prefers_collection' => array( 'const' => $pickup ),
				),
			),
		),
	);
}

add_action(
	'woocommerce_init',
	function () {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) || ! fleuriste_dc_enabled() ) {
			return;
		}
		$pickup_on   = function_exists( 'fleuriste_dl_pickup_on' ) && fleuriste_dl_pickup_on();
		$delivery_on = ! function_exists( 'fleuriste_dl_delivery_on' ) || fleuriste_dl_delivery_on();

		if ( $delivery_on ) {
			$hide = $pickup_on ? fleuriste_dc_rule( true ) : null;
			fleuriste_dc_register_date( FLEURISTE_DC_DATE, __( 'Delivery date', 'fleuriste' ), 'delivery', $hide );
			$slots = fleuriste_fo_choices( 'fleuriste_fo_slots' );
			if ( $slots ) {
				$field = array(
					'id'       => FLEURISTE_DC_SLOT,
					'label'    => __( 'Delivery time', 'fleuriste' ),
					'location' => 'order',
					'type'     => 'select',
					'required' => true,
					'options'  => array_map(
						function ( $slot ) {
							return array(
								'value' => $slot['label'],
								'label' => $slot['label'] . ( $slot['price'] > 0 ? ' (+' . fleuriste_fo_money( $slot['price'] ) . ')' : '' ),
							);
						},
						$slots
					),
				);
				if ( $hide ) {
					$field['hidden'] = $hide;
				}
				woocommerce_register_additional_checkout_field( $field );
			}
		}

		if ( $pickup_on ) {
			$hide = $delivery_on ? fleuriste_dc_rule( false ) : null;
			fleuriste_dc_register_date( FLEURISTE_DC_PDATE, __( 'Pickup date', 'fleuriste' ), 'pickup', $hide );
			$slots = fleuriste_dl_pickup_slots();
			if ( $slots ) {
				$field = array(
					'id'       => FLEURISTE_DC_PSLOT,
					'label'    => __( 'Pickup time', 'fleuriste' ),
					'location' => 'order',
					'type'     => 'select',
					'required' => true,
					'options'  => array_map(
						function ( $slot ) {
							return array(
								'value' => $slot,
								'label' => $slot,
							);
						},
						$slots
					),
				);
				if ( $hide ) {
					$field['hidden'] = $hide;
				}
				woocommerce_register_additional_checkout_field( $field );
			}
		}
	},
	5 // Before "Send anonymously", so the date and time come first.
);

/* -------------------------------------------------------------------------
 * Time slot fee
 * ---------------------------------------------------------------------- */

/**
 * The slot the customer picked: from the checkout request being processed,
 * else the one the checkout page last sent.
 *
 * @return string
 */
function fleuriste_dc_current_slot() {
	if ( isset( $GLOBALS['fleuriste_dc_request_slot'] ) ) {
		return (string) $GLOBALS['fleuriste_dc_request_slot'];
	}
	return ( function_exists( 'WC' ) && WC()->session ) ? (string) WC()->session->get( 'fleuriste_slot', '' ) : '';
}

// Placing the order: use the slot sent with the order, whatever the page showed.
add_filter(
	'rest_request_before_callbacks',
	function ( $response, $handler, $request ) {
		if ( $request instanceof WP_REST_Request && preg_match( '#^/wc/store(/v\d+)?/checkout$#', $request->get_route() ) && 'GET' !== $request->get_method() ) {
			$fields = (array) $request->get_param( 'additional_fields' );
			if ( array_key_exists( FLEURISTE_DC_SLOT, $fields ) ) {
				$GLOBALS['fleuriste_dc_request_slot'] = sanitize_text_field( (string) $fields[ FLEURISTE_DC_SLOT ] );
			}
		}
		return $response;
	},
	10,
	3
);

// The checkout page tells us when the slot changes, so the total updates straight away.
// The theme loads after WooCommerce Blocks, so register on init.
add_action(
	'init',
	function () {
		if ( ! function_exists( 'woocommerce_store_api_register_update_callback' ) ) {
			return;
		}
		woocommerce_store_api_register_update_callback(
			array(
				'namespace' => 'fleuriste-slot',
				'callback'  => function ( $data ) {
					if ( WC()->session ) {
						WC()->session->set( 'fleuriste_slot', isset( $data['slot'] ) ? sanitize_text_field( (string) $data['slot'] ) : '' );
					}
				},
			)
		);
	}
);

add_action(
	'woocommerce_cart_calculate_fees',
	function ( $cart ) {
		if ( ! fleuriste_dc_enabled() || ( function_exists( 'fleuriste_dl_cart_is_pickup' ) && fleuriste_dl_cart_is_pickup() ) ) {
			return;
		}
		$slot  = fleuriste_dc_current_slot();
		$price = '' !== $slot ? fleuriste_dc_slot_price( $slot ) : 0;
		if ( $price > 0 ) {
			/* translators: %s: time slot, e.g. "Evening (4pm – 7pm)" */
			$cart->add_fee( sprintf( __( 'Delivery time: %s', 'fleuriste' ), $slot ), $price, false );
		}
	}
);

/* -------------------------------------------------------------------------
 * Checkout page script: slot fee and the cut-off note
 * ---------------------------------------------------------------------- */

add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! fleuriste_dc_enabled() || ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
			return;
		}
		$fmt   = function ( $t ) {
			return date_i18n( get_option( 'time_format' ), strtotime( '2000-01-01 ' . $t ) );
		};
		$notes = array();
		if ( fleuriste_fo_cutoff( 'delivery' ) ) {
			/* translators: %s: time */
			$notes['fleuriste-delivery-date'] = sprintf( __( 'Order before %s for same-day delivery.', 'fleuriste' ), $fmt( fleuriste_fo_cutoff( 'delivery' ) ) );
		}
		if ( function_exists( 'fleuriste_dl_pickup_on' ) && fleuriste_dl_pickup_on() ) {
			$where = fleuriste_dl_pickup_address();
			$pick  = fleuriste_fo_cutoff( 'pickup' )
				/* translators: %s: time */
				? sprintf( __( 'Order before %s to pick up today.', 'fleuriste' ), $fmt( fleuriste_fo_cutoff( 'pickup' ) ) )
				: '';
			$notes['fleuriste-pickup-date'] = trim( ( $where ? __( 'Pick up at:', 'fleuriste' ) . ' ' . $where . '. ' : '' ) . $pick );
		}
		$cfg = array(
			'slotField' => FLEURISTE_DC_SLOT,
			'slot'      => WC()->session ? (string) WC()->session->get( 'fleuriste_slot', '' ) : '',
			'notes'     => array_filter( $notes ),
		);
		$js  = '(function(c){var last=c.slot;function slot(){var d=window.wp&&wp.data,s=d&&d.select("wc/store/checkout");if(!s||!s.getAdditionalFields)return null;var f=s.getAdditionalFields()||{};return f[c.slotField]||"";}';
		$js .= 'function tick(){var v=slot();if(v===null||v===last)return;var u=window.wc&&wc.blocksCheckout&&wc.blocksCheckout.extensionCartUpdate;if(!u)return;last=v;u({namespace:"fleuriste-slot",data:{slot:v}});}';
		$js .= 'function note(){document.querySelectorAll(".fl-dc-note").forEach(function(p){if(!document.querySelector("[id$=\'"+p.dataset.for+"\']"))p.remove();});Object.keys(c.notes||{}).forEach(function(k){var el=document.querySelector("[id$=\'"+k+"\']");if(!el)return;var box=el.closest(".wc-block-components-text-input,.wc-block-components-date-input,.wc-blocks-components-select,.wc-block-components-select-input")||el.parentNode;if(box.nextElementSibling&&box.nextElementSibling.classList.contains("fl-dc-note"))return;var p=document.createElement("p");p.className="fl-dc-note";p.dataset.for=k;p.textContent=c.notes[k];box.insertAdjacentElement("afterend",p);});}';
		$js .= 'var n=0,t=setInterval(function(){if(window.wp&&wp.data&&wp.data.select("wc/store/checkout")){clearInterval(t);wp.data.subscribe(tick);tick();}if(++n>200)clearInterval(t);},100);setInterval(note,500);})(' . wp_json_encode( $cfg ) . ');';
		wp_register_script( 'fleuriste-delivery-checkout', '', array( 'wp-data', 'wc-blocks-checkout' ), FLEURISTE_VERSION, true );
		wp_enqueue_script( 'fleuriste-delivery-checkout' );
		wp_add_inline_script( 'fleuriste-delivery-checkout', $js );
		wp_register_style( 'fleuriste-delivery-checkout', false, array(), FLEURISTE_VERSION );
		wp_enqueue_style( 'fleuriste-delivery-checkout' );
		wp_add_inline_style( 'fleuriste-delivery-checkout', '.fl-dc-note{margin:.35em 0 0;font-size:.85em;opacity:.75}' );
	},
	20
);

/* -------------------------------------------------------------------------
 * The order: copy the choice onto every item
 * ---------------------------------------------------------------------- */

/**
 * Delivery date and time chosen at checkout for an order.
 *
 * @param WC_Order $order Order.
 * @return array { date: Y-m-d|'', slot: label|'' }
 */
function fleuriste_dc_order_choice( $order ) {
	$pickup = function_exists( 'fleuriste_order_is_pickup' ) && fleuriste_order_is_pickup( $order );
	return array(
		'pickup' => $pickup,
		'date'   => (string) $order->get_meta( '_wc_other/' . ( $pickup ? FLEURISTE_DC_PDATE : FLEURISTE_DC_DATE ) ),
		'slot'   => (string) $order->get_meta( '_wc_other/' . ( $pickup ? FLEURISTE_DC_PSLOT : FLEURISTE_DC_SLOT ) ),
	);
}

/**
 * Write a delivery date and time onto an order and all its items.
 *
 * @param WC_Order $order Order.
 * @param string   $date  Y-m-d.
 * @param string   $slot  Slot label (without price).
 */
function fleuriste_dc_apply_to_order( $order, $date, $slot ) {
	$k_date = __( 'Delivery date', 'fleuriste' );
	$k_time = __( 'Delivery time', 'fleuriste' );
	foreach ( $order->get_items() as $item ) {
		if ( $date ) {
			$item->update_meta_data( $k_date, date_i18n( get_option( 'date_format' ), strtotime( $date ) ) );
			$item->update_meta_data( '_fleuriste_delivery_date', $date );
		}
		if ( $slot ) {
			$item->update_meta_data( $k_time, $slot );
		}
		$item->save();
	}
	if ( $date ) {
		$order->update_meta_data( '_fleuriste_delivery_date', $date );
	}
	if ( $slot ) {
		$order->update_meta_data( '_fleuriste_delivery_slot', $slot );
	}
	// Keep the checkout fields in step (they show on the order screen and in emails).
	if ( 'store-api' === $order->get_created_via() ) {
		$pickup = function_exists( 'fleuriste_order_is_pickup' ) && fleuriste_order_is_pickup( $order );
		if ( $date ) {
			$order->update_meta_data( '_wc_other/' . ( $pickup ? FLEURISTE_DC_PDATE : FLEURISTE_DC_DATE ), $date );
		}
		if ( $slot ) {
			$order->update_meta_data( '_wc_other/' . ( $pickup ? FLEURISTE_DC_PSLOT : FLEURISTE_DC_SLOT ), $slot );
		}
	}
	$order->save();
}

add_action(
	'woocommerce_store_api_checkout_order_processed',
	function ( $order ) {
		$choice = fleuriste_dc_order_choice( $order );
		if ( $choice['pickup'] ) {
			$order->update_meta_data( '_fleuriste_pickup', 'yes' );
			// Only the pickup choice belongs on the order.
			$order->delete_meta_data( '_wc_other/' . FLEURISTE_DC_DATE );
			$order->delete_meta_data( '_wc_other/' . FLEURISTE_DC_SLOT );
		} else {
			$order->delete_meta_data( '_wc_other/' . FLEURISTE_DC_PDATE );
			$order->delete_meta_data( '_wc_other/' . FLEURISTE_DC_PSLOT );
		}
		if ( $choice['date'] || $choice['slot'] ) {
			fleuriste_dc_apply_to_order( $order, $choice['date'], $choice['slot'] );
		} else {
			$order->save();
		}
		if ( WC()->session ) {
			WC()->session->set( 'fleuriste_slot', '' );
		}
	},
	5
);

// Shown once for the order (checkout fields), so not repeated under every item for checkout orders.
add_filter(
	'woocommerce_order_item_get_formatted_meta_data',
	function ( $meta, $item ) {
		if ( is_admin() && ! wp_doing_ajax() && isset( $_GET['page'] ) && 0 === strpos( sanitize_key( wp_unslash( $_GET['page'] ) ), 'fleuriste' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $meta; // Our own screens read these lines.
		}
		$order = $item->get_order();
		if ( ! $order || 'store-api' !== $order->get_created_via() || ! ( $order->get_meta( '_wc_other/' . FLEURISTE_DC_DATE ) || $order->get_meta( '_wc_other/' . FLEURISTE_DC_PDATE ) ) ) {
			return $meta;
		}
		$hide = array( __( 'Delivery date', 'fleuriste' ), __( 'Delivery time', 'fleuriste' ) );
		foreach ( $meta as $id => $m ) {
			if ( in_array( $m->key, $hide, true ) ) {
				unset( $meta[ $id ] );
			}
		}
		return $meta;
	},
	10,
	2
);
