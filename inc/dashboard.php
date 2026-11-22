<?php
/**
 * Deliveries dashboard (WordPress admin → Deliveries).
 *
 * Orders grouped by delivery date and time slot, with recipient, address, items,
 * options and card message, and one-click buttons to move an order along:
 * Awaiting payment → New → Preparing → Out for delivery → Delivered.
 *
 * @package Fleuriste
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * Order statuses: "Preparing" and "Out for delivery"
 * ---------------------------------------------------------------------- */

add_action(
	'init',
	function () {
		register_post_status(
			'wc-preparing',
			array(
				'label'                     => _x( 'Preparing', 'Order status', 'fleuriste' ),
				'public'                    => false,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of orders */
				'label_count'               => _n_noop( 'Preparing <span class="count">(%s)</span>', 'Preparing <span class="count">(%s)</span>', 'fleuriste' ),
			)
		);
		register_post_status(
			'wc-out-for-delivery',
			array(
				'label'                     => _x( 'Out for delivery', 'Order status', 'fleuriste' ),
				'public'                    => false,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of orders */
				'label_count'               => _n_noop( 'Out for delivery <span class="count">(%s)</span>', 'Out for delivery <span class="count">(%s)</span>', 'fleuriste' ),
			)
		);
	}
);

add_filter(
	'wc_order_statuses',
	function ( $statuses ) {
		$new = array();
		foreach ( $statuses as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'wc-processing' === $key ) {
				$new['wc-preparing']        = _x( 'Preparing', 'Order status', 'fleuriste' );
				$new['wc-out-for-delivery'] = _x( 'Out for delivery', 'Order status', 'fleuriste' );
			}
		}
		return $new;
	}
);

// Treat the new statuses as paid orders (reports, stock, "order again", downloads).
add_filter(
	'woocommerce_order_is_paid_statuses',
	function ( $statuses ) {
		return array_merge( $statuses, array( 'preparing', 'out-for-delivery' ) );
	}
);
add_filter(
	'woocommerce_reports_order_statuses',
	function ( $statuses ) {
		return is_array( $statuses ) ? array_merge( $statuses, array( 'preparing', 'out-for-delivery' ) ) : $statuses;
	}
);
add_filter(
	'woocommerce_analytics_actionable_order_statuses',
	function ( $statuses ) {
		return array_merge( (array) $statuses, array( 'preparing', 'out-for-delivery' ) );
	}
);

/* -------------------------------------------------------------------------
 * Helpers
 * ---------------------------------------------------------------------- */

/**
 * Workflow: status => [label, next status, button text, colour].
 *
 * @return array
 */
function fleuriste_board_flow() {
	return array(
		'pending'          => array( __( 'Awaiting payment', 'fleuriste' ), 'processing', __( 'Mark as paid', 'fleuriste' ), 'grey' ),
		'on-hold'          => array( __( 'Awaiting payment', 'fleuriste' ), 'processing', __( 'Mark as paid', 'fleuriste' ), 'grey' ),
		'processing'       => array( __( 'New', 'fleuriste' ), 'preparing', __( 'Start preparing', 'fleuriste' ), 'rose' ),
		'preparing'        => array( __( 'Preparing', 'fleuriste' ), 'out-for-delivery', __( 'Out for delivery', 'fleuriste' ), 'gold' ),
		'out-for-delivery' => array( __( 'Out for delivery', 'fleuriste' ), 'completed', __( 'Mark delivered', 'fleuriste' ), 'blue' ),
		'completed'        => array( __( 'Delivered', 'fleuriste' ), '', '', 'green' ),
		'cancelled'        => array( __( 'Cancelled', 'fleuriste' ), '', '', 'grey' ),
	);
}

/**
 * Status steps for an order collected at the shop ("out for delivery" = ready for pickup).
 *
 * @return array
 */
function fleuriste_board_flow_pickup() {
	$flow                     = fleuriste_board_flow();
	$flow['preparing']        = array( __( 'Preparing', 'fleuriste' ), 'out-for-delivery', __( 'Ready for pickup', 'fleuriste' ), 'gold' );
	$flow['out-for-delivery'] = array( __( 'Ready for pickup', 'fleuriste' ), 'completed', __( 'Mark collected', 'fleuriste' ), 'blue' );
	$flow['completed']        = array( __( 'Collected', 'fleuriste' ), '', '', 'green' );
	return $flow;
}

/**
 * Active (not finished) statuses.
 *
 * @return string[]
 */
function fleuriste_board_active_statuses() {
	return array( 'pending', 'on-hold', 'processing', 'preparing', 'out-for-delivery' );
}

/**
 * Today's date in the shop time zone (Y-m-d), optionally shifted by days.
 *
 * @param int $shift Days.
 * @return string
 */
function fleuriste_board_day( $shift = 0 ) {
	return ( new DateTimeImmutable( 'today', wp_timezone() ) )->modify( ( $shift >= 0 ? '+' : '' ) . (int) $shift . ' days' )->format( 'Y-m-d' );
}

/**
 * Everything the board needs from one order.
 *
 * @param WC_Order $order Order.
 * @return array
 */
function fleuriste_board_order_data( $order ) {
	$date  = '';
	$slot  = '';
	$items = array();
	foreach ( $order->get_items() as $item ) {
		$d = (string) $item->get_meta( '_fleuriste_delivery_date' );
		if ( $d && ( ! $date || $d < $date ) ) {
			$date = $d;
		}
		if ( ! $slot ) {
			$slot = (string) $item->get_meta( __( 'Delivery time', 'fleuriste' ) );
		}
		$details = array();
		$message = '';
		foreach ( $item->get_formatted_meta_data( '_', true ) as $meta ) {
			$key = wp_strip_all_tags( $meta->display_key );
			$val = trim( wp_strip_all_tags( $meta->display_value ) );
			if ( in_array( $key, array( __( 'Delivery date', 'fleuriste' ), __( 'Delivery time', 'fleuriste' ), __( 'Send anonymously', 'fleuriste' ) ), true ) ) {
				continue;
			}
			if ( __( 'Card message', 'fleuriste' ) === $key ) {
				$message = $val;
				continue;
			}
			$details[] = $key . ': ' . $val;
		}
		$items[] = array(
			'name'    => $item->get_name(),
			'qty'     => $item->get_quantity(),
			'details' => $details,
			'message' => $message,
		);
	}
	$slot_label = preg_replace( '/\s*\(\+[^)]*\)\s*$/', '', $slot ); // Drop "(+Rs 100)".
	$area_name  = function ( $code ) {
		if ( '' === (string) $code ) {
			return '';
		}
		$region = function_exists( 'fleuriste_dl_region' ) ? fleuriste_dl_region( $code ) : null;
		return $region ? $region['name'] : ucwords( strtolower( str_replace( '-', ' ', (string) $code ) ) );
	};
	$pickup     = function_exists( 'fleuriste_order_is_pickup' ) && fleuriste_order_is_pickup( $order );
	$address    = array_filter(
		array(
			$order->get_shipping_address_1(),
			$order->get_shipping_address_2(),
			$order->get_shipping_city(),
			$area_name( $order->get_shipping_state() ),
			$order->get_shipping_postcode(),
		)
	);
	if ( ! $address ) {
		$address = array_filter( array( $order->get_billing_address_1(), $order->get_billing_address_2(), $order->get_billing_city(), $area_name( $order->get_billing_state() ) ) );
	}
	$seen    = array();
	$address = array_filter(
		$address,
		function ( $part ) use ( &$seen ) {
			$key = strtolower( trim( $part ) );
			if ( isset( $seen[ $key ] ) ) {
				return false;
			}
			$seen[ $key ] = true;
			return true;
		}
	);
	if ( $pickup ) {
		$address = array();
	}
	$recipient = $pickup ? '' : trim( $order->get_formatted_shipping_full_name() );
	return array(
		'id'        => $order->get_id(),
		'number'    => $order->get_order_number(),
		'status'    => $order->get_status(),
		'date'      => $date,
		'slot'      => $slot_label,
		'slot_rank' => fleuriste_board_slot_rank( $slot_label ),
		'recipient' => $recipient ? $recipient : $order->get_formatted_billing_full_name(),
		'r_phone'   => $order->get_shipping_phone(),
		'address'   => implode( ', ', $address ),
		'pickup'    => $pickup,
		'sender'    => $order->get_formatted_billing_full_name(),
		's_phone'   => $order->get_billing_phone(),
		'email'     => $order->get_billing_email(),
		'anonymous' => (bool) $order->get_meta( '_wc_other/fleuriste/send-anonymously' ),
		'items'     => $items,
		'note'      => $order->get_customer_note(),
		'total'     => wp_strip_all_tags( html_entity_decode( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) ) ),
		'payment'   => $order->get_payment_method_title(),
		'created'   => $order->get_date_created() ? $order->get_date_created()->getTimestamp() : 0,
		'edit_url'  => $order->get_edit_order_url(),
		'driver'    => (string) $order->get_meta( '_fleuriste_driver' ),
		'parent'    => fleuriste_board_parent_info( $order ),
		'subs'      => fleuriste_board_subs_info( $order ),
	);
}

/**
 * Parent order of a suborder, for the board.
 *
 * @param WC_Order $order Order.
 * @return array|null id, number.
 */
function fleuriste_board_parent_info( $order ) {
	$parent = function_exists( 'fleuriste_parent_order' ) ? fleuriste_parent_order( $order ) : false;
	return $parent ? array(
		'id'     => $parent->get_id(),
		'number' => $parent->get_order_number(),
	) : null;
}

/**
 * Suborders of an order, for the board.
 *
 * @param WC_Order $order Order.
 * @return array[]
 */
function fleuriste_board_subs_info( $order ) {
	if ( ! function_exists( 'fleuriste_suborders' ) ) {
		return array();
	}
	$out = array();
	foreach ( fleuriste_suborders( $order ) as $sub ) {
		$items = array();
		foreach ( $sub->get_items() as $item ) {
			$items[] = $item->get_quantity() . ' × ' . $item->get_name();
		}
		$out[] = array(
			'id'     => $sub->get_id(),
			'number' => $sub->get_order_number(),
			'items'  => implode( ', ', $items ),
			'total'  => html_entity_decode( wp_strip_all_tags( wc_price( $sub->get_total() ) ) ),
			'status' => wc_get_order_status_name( $sub->get_status() ),
			'unpaid' => $sub->has_status( array( 'pending', 'on-hold' ) ),
			'gone'   => $sub->has_status( array( 'cancelled', 'refunded', 'failed' ) ),
		);
	}
	return $out;
}

/**
 * Position of a time slot in the settings list (for sorting).
 *
 * @param string $label Slot label.
 * @return int
 */
function fleuriste_board_slot_rank( $label ) {
	if ( function_exists( 'fleuriste_fo_choices' ) ) {
		foreach ( fleuriste_fo_choices( 'fleuriste_fo_slots' ) as $i => $choice ) {
			if ( $choice['label'] === $label ) {
				return $i;
			}
		}
	}
	return 99;
}

/**
 * Orders for the board.
 *
 * @return array[]
 */
function fleuriste_board_orders() {
	$active = wc_get_orders(
		array(
			'status'  => fleuriste_board_active_statuses(),
			'limit'   => 500,
			'orderby' => 'date',
			'order'   => 'ASC',
			'type'    => 'shop_order',
		)
	);
	$recent = wc_get_orders(
		array(
			'status'        => array( 'completed', 'cancelled' ),
			'limit'         => 200,
			'date_modified' => '>' . ( time() - 3 * DAY_IN_SECONDS ),
			'type'          => 'shop_order',
		)
	);
	return array_map( 'fleuriste_board_order_data', array_merge( $active, $recent ) );
}

/**
 * Filter orders for a view.
 *
 * @param array[] $orders Orders.
 * @param string  $view   View key.
 * @return array[]
 */
function fleuriste_board_filter( $orders, $view ) {
	$today    = fleuriste_board_day();
	$tomorrow = fleuriste_board_day( 1 );
	$active   = fleuriste_board_active_statuses();
	return array_values(
		array_filter(
			$orders,
			function ( $o ) use ( $view, $today, $tomorrow, $active ) {
				$is_active = in_array( $o['status'], $active, true );
				switch ( $view ) {
					case 'today':
						return $is_active && $o['date'] === $today;
					case 'tomorrow':
						return $is_active && $o['date'] === $tomorrow;
					case 'upcoming':
						return $is_active && $o['date'] > $tomorrow;
					case 'late':
						return $is_active && $o['date'] && $o['date'] < $today;
					case 'new':
						return in_array( $o['status'], array( 'processing', 'pending', 'on-hold' ), true );
					case 'nodate':
						return $is_active && ! $o['date'];
					case 'done':
						return ! $is_active;
				}
				return $is_active;
			}
		)
	);
}

/**
 * Find orders by number, name, phone or email (board orders plus the last 300 orders).
 *
 * @param array[] $orders Board orders.
 * @param string  $q      Search text.
 * @return array[]
 */
function fleuriste_board_search( $orders, $q ) {
	$by_id = array();
	foreach ( $orders as $o ) {
		$by_id[ $o['id'] ] = $o;
	}
	foreach ( wc_get_orders(
		array(
			'limit'   => 300,
			'orderby' => 'date',
			'order'   => 'DESC',
			'type'    => 'shop_order',
			'status'  => array_keys( wc_get_order_statuses() ),
		)
	) as $order ) {
		if ( ! isset( $by_id[ $order->get_id() ] ) && 'checkout-draft' !== $order->get_status() ) {
			$by_id[ $order->get_id() ] = fleuriste_board_order_data( $order );
		}
	}
	$num = ltrim( $q, '# ' );
	if ( ctype_digit( $num ) && ! isset( $by_id[ (int) $num ] ) ) {
		$order = wc_get_order( (int) $num );
		if ( $order && 'shop_order' === $order->get_type() ) {
			$by_id[ $order->get_id() ] = fleuriste_board_order_data( $order );
		}
	}
	// A short number is an order number: show just that order when it exists.
	if ( ctype_digit( $num ) && strlen( $num ) < 6 ) {
		foreach ( $by_id as $o ) {
			if ( (string) $o['number'] === $num ) {
				return array( $o );
			}
		}
	}
	$needle = function_exists( 'mb_strtolower' ) ? mb_strtolower( $q ) : strtolower( $q );
	$digits = preg_replace( '/\D+/', '', $q );
	return array_values(
		array_filter(
			$by_id,
			function ( $o ) use ( $num, $needle, $digits ) {
				if ( (string) $o['number'] === $num ) {
					return true;
				}
				foreach ( array( 'recipient', 'sender', 'email', 'address' ) as $k ) {
					$hay = function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $o[ $k ] ) : strtolower( (string) $o[ $k ] );
					if ( '' !== $hay && false !== strpos( $hay, $needle ) ) {
						return true;
					}
				}
				if ( strlen( $digits ) >= 4 ) {
					foreach ( array( 's_phone', 'r_phone' ) as $k ) {
						if ( false !== strpos( preg_replace( '/\D+/', '', (string) $o[ $k ] ), $digits ) ) {
							return true;
						}
					}
				}
				return false;
			}
		)
	);
}

/* -------------------------------------------------------------------------
 * Admin page
 * ---------------------------------------------------------------------- */

add_action(
	'admin_menu',
	function () {
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}
		$count = 0;
		if ( function_exists( 'wc_orders_count' ) ) {
			$count = (int) wc_orders_count( 'processing' );
		}
		$bubble = $count ? ' <span class="awaiting-mod count-' . $count . '"><span class="pending-count">' . $count . '</span></span>' : '';
		add_menu_page(
			__( 'Deliveries', 'fleuriste' ),
			__( 'Deliveries', 'fleuriste' ) . $bubble,
			'edit_shop_orders',
			'fleuriste-deliveries',
			'fleuriste_board_page',
			'dashicons-car',
			'55.4'
		);
	}
);

// Shop managers land on the board after logging in.
add_filter(
	'login_redirect',
	function ( $redirect, $requested, $user ) {
		if ( $user instanceof WP_User && in_array( 'shop_manager', (array) $user->roles, true ) && ( ! $requested || admin_url() === $requested ) ) {
			return admin_url( 'admin.php?page=fleuriste-deliveries' );
		}
		return $redirect;
	},
	20,
	3
);

/**
 * Board HTML for a view.
 *
 * @param string $view View key.
 * @return string
 */
function fleuriste_board_html( $view, $driver = '', $q = '' ) {
	$orders = fleuriste_board_orders();
	$q      = trim( (string) $q );
	$has_drivers = function_exists( 'fleuriste_drivers' ) && fleuriste_drivers();
	if ( $driver ) {
		$orders = array_values(
			array_filter(
				$orders,
				function ( $o ) use ( $driver ) {
					return 'none' === $driver ? '' === $o['driver'] : $o['driver'] === $driver;
				}
			)
		);
	}
	$views  = array(
		'today'    => __( 'Today', 'fleuriste' ),
		'tomorrow' => __( 'Tomorrow', 'fleuriste' ),
		'upcoming' => __( 'Later', 'fleuriste' ),
		'new'      => __( 'Not started', 'fleuriste' ),
		'late'     => __( 'Overdue', 'fleuriste' ),
		'nodate'   => __( 'No delivery date', 'fleuriste' ),
		'done'     => __( 'Delivered / cancelled', 'fleuriste' ),
	);
	if ( ! isset( $views[ $view ] ) ) {
		$view = 'today';
	}
	$counts = array();
	foreach ( $views as $key => $label ) {
		$counts[ $key ] = count( fleuriste_board_filter( $orders, $key ) );
	}
	$list = '' !== $q ? fleuriste_board_search( $orders, $q ) : fleuriste_board_filter( $orders, $view );
	usort(
		$list,
		function ( $a, $b ) {
			return array( $a['date'] ? $a['date'] : '9999', $a['slot_rank'], $a['created'] ) <=> array( $b['date'] ? $b['date'] : '9999', $b['slot_rank'], $b['created'] );
		}
	);
	$flow = fleuriste_board_flow();

	ob_start();
	?>
	<div class="fl-board__tiles">
		<button type="button" class="fl-tile-stat<?php echo 'today' === $view ? ' is-on' : ''; ?>" data-view="today"><strong><?php echo (int) $counts['today']; ?></strong><span><?php esc_html_e( 'to deliver today', 'fleuriste' ); ?></span></button>
		<button type="button" class="fl-tile-stat<?php echo 'tomorrow' === $view ? ' is-on' : ''; ?>" data-view="tomorrow"><strong><?php echo (int) $counts['tomorrow']; ?></strong><span><?php esc_html_e( 'for tomorrow', 'fleuriste' ); ?></span></button>
		<button type="button" class="fl-tile-stat fl-tile-stat--rose<?php echo 'new' === $view ? ' is-on' : ''; ?>" data-view="new"><strong><?php echo (int) $counts['new']; ?></strong><span><?php esc_html_e( 'not started yet', 'fleuriste' ); ?></span></button>
		<button type="button" class="fl-tile-stat<?php echo $counts['late'] ? ' fl-tile-stat--alert' : ''; ?><?php echo 'late' === $view ? ' is-on' : ''; ?>" data-view="late"><strong><?php echo (int) $counts['late']; ?></strong><span><?php esc_html_e( 'overdue', 'fleuriste' ); ?></span></button>
	</div>

	<nav class="fl-board__tabs" aria-label="<?php esc_attr_e( 'Views', 'fleuriste' ); ?>">
		<?php foreach ( $views as $key => $label ) : ?>
			<?php
			if ( 'nodate' === $key && ! $counts['nodate'] ) {
				continue;
			}
			?>
			<button type="button" class="fl-tab<?php echo ( $key === $view && '' === $q ) ? ' is-on' : ''; ?>" data-view="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?> <span class="fl-tab__n"><?php echo (int) $counts[ $key ]; ?></span></button>
		<?php endforeach; ?>
		<?php if ( $has_drivers ) : ?>
			<label class="fl-driver-filter-wrap"><span class="screen-reader-text"><?php esc_html_e( 'Driver', 'fleuriste' ); ?></span>
				<select class="fl-driver-filter">
					<option value=""><?php esc_html_e( 'All drivers', 'fleuriste' ); ?></option>
					<option value="none" <?php selected( 'none', $driver ); ?>><?php esc_html_e( 'No driver yet', 'fleuriste' ); ?></option>
					<?php foreach ( fleuriste_drivers() as $did => $d ) : ?>
						<option value="<?php echo esc_attr( $did ); ?>" <?php selected( $did, $driver ); ?>><?php echo esc_html( $d['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
		<?php endif; ?>
		<?php
		if ( $has_drivers && function_exists( 'fleuriste_run_sheet_form' ) ) {
			$sheet_day = 'tomorrow' === $view ? fleuriste_board_day( 1 ) : fleuriste_board_day();
			echo fleuriste_run_sheet_form( $sheet_day, $driver ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		?>
		<button type="button" class="button fl-print" onclick="window.print()"><?php esc_html_e( 'Print this list', 'fleuriste' ); ?></button>
	</nav>

	<h2 class="fl-board__title"><?php echo esc_html( '' !== $q ? sprintf( /* translators: %s: search text */ __( 'Orders matching “%s”', 'fleuriste' ), $q ) : $views[ $view ] ); ?> <small><?php echo esc_html( sprintf( /* translators: %d: number */ _n( '%d order', '%d orders', count( $list ), 'fleuriste' ), count( $list ) ) ); ?></small></h2>

	<?php if ( ! $list ) : ?>
		<div class="fl-empty"><?php echo '' !== $q ? esc_html__( 'No order found. Try the order number, a name or a phone number.', 'fleuriste' ) : esc_html__( 'Nothing here right now.', 'fleuriste' ); ?></div>
	<?php endif; ?>

	<?php
	$group = null;
	foreach ( $list as $o ) :
		$heading = $o['date'] ? date_i18n( 'l j F', strtotime( $o['date'] ) ) : __( 'No date', 'fleuriste' );
		$heading = $heading . ( $o['slot'] ? ' · ' . $o['slot'] : '' );
		if ( $heading !== $group ) :
			$group = $heading;
			?>
			<h3 class="fl-group"><?php echo esc_html( $heading ); ?></h3>
		<?php endif; ?>
		<?php
		$steps  = empty( $o['pickup'] ) ? $flow : fleuriste_board_flow_pickup();
		$f      = isset( $steps[ $o['status'] ] ) ? $steps[ $o['status'] ] : array( wc_get_order_status_name( $o['status'] ), '', '', 'grey' );
		$maps   = $o['address'] ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $o['address'] . ', Mauritius' ) : '';
		$wa     = $o['r_phone'] ? 'https://wa.me/' . preg_replace( '/\D+/', '', ( 0 === strpos( preg_replace( '/\s+/', '', $o['r_phone'] ), '+' ) ? $o['r_phone'] : '230' . $o['r_phone'] ) ) : '';
		?>
		<article class="fl-order fl-order--<?php echo esc_attr( $f[3] ); ?>" data-id="<?php echo (int) $o['id']; ?>">
			<header class="fl-order__head">
				<a class="fl-order__num" href="<?php echo esc_url( $o['edit_url'] ); ?>">#<?php echo esc_html( $o['number'] ); ?></a>
				<span class="fl-badge fl-badge--<?php echo esc_attr( $f[3] ); ?>"><?php echo esc_html( $f[0] ); ?></span>
				<?php if ( $o['anonymous'] ) : ?>
					<span class="fl-badge fl-badge--dark"><?php esc_html_e( 'Anonymous', 'fleuriste' ); ?></span>
				<?php endif; ?>
				<?php if ( ! empty( $o['pickup'] ) ) : ?>
					<span class="fl-badge fl-badge--pickup"><span class="dashicons dashicons-store" aria-hidden="true"></span> <?php esc_html_e( 'Pickup at the shop', 'fleuriste' ); ?></span>
				<?php endif; ?>
				<?php if ( $o['parent'] ) : ?>
					<span class="fl-badge fl-badge--sub"><?php echo esc_html( sprintf( /* translators: %s: order number */ __( 'Extra for #%s – deliver together', 'fleuriste' ), $o['parent']['number'] ) ); ?></span>
				<?php endif; ?>
				<?php
				$drv = ( $o['driver'] && function_exists( 'fleuriste_driver' ) ) ? fleuriste_driver( $o['driver'] ) : null;
				if ( $drv ) :
					?>
					<span class="fl-badge fl-badge--driver"><span class="dashicons dashicons-car" aria-hidden="true"></span> <?php echo esc_html( $drv['name'] ); ?></span>
				<?php elseif ( $has_drivers && empty( $o['pickup'] ) && in_array( $o['status'], array( 'preparing', 'out-for-delivery' ), true ) ) : ?>
					<span class="fl-badge fl-badge--warn"><?php esc_html_e( 'No driver', 'fleuriste' ); ?></span>
				<?php endif; ?>
				<span class="fl-order__total"><?php echo esc_html( $o['total'] ); ?> <small><?php echo esc_html( $o['payment'] ); ?></small></span>
			</header>

			<div class="fl-order__body">
				<div class="fl-order__col">
					<div class="fl-label"><?php echo empty( $o['pickup'] ) ? esc_html__( 'Deliver to', 'fleuriste' ) : esc_html__( 'Collected by', 'fleuriste' ); ?></div>
					<div class="fl-strong"><?php echo esc_html( $o['recipient'] ); ?></div>
					<?php if ( $o['address'] ) : ?>
						<div><?php echo esc_html( $o['address'] ); ?></div>
					<?php endif; ?>
					<div class="fl-links">
						<?php if ( $o['r_phone'] ) : ?>
							<a href="tel:<?php echo esc_attr( $o['r_phone'] ); ?>"><span class="dashicons dashicons-phone" aria-hidden="true"></span> <?php echo esc_html( $o['r_phone'] ); ?></a>
							<a href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener">WhatsApp</a>
						<?php endif; ?>
						<?php if ( $maps ) : ?>
							<a href="<?php echo esc_url( $maps ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Map', 'fleuriste' ); ?></a>
						<?php endif; ?>
					</div>
					<div class="fl-label"><?php esc_html_e( 'From', 'fleuriste' ); ?></div>
					<div><?php echo esc_html( $o['sender'] ); ?><?php echo $o['anonymous'] ? ' <em>(' . esc_html__( 'don\'t reveal', 'fleuriste' ) . ')</em>' : ''; ?></div>
					<div class="fl-links">
						<?php if ( $o['s_phone'] ) : ?>
							<a href="tel:<?php echo esc_attr( $o['s_phone'] ); ?>"><span class="dashicons dashicons-phone" aria-hidden="true"></span> <?php echo esc_html( $o['s_phone'] ); ?></a>
						<?php endif; ?>
						<?php if ( $o['email'] ) : ?>
							<a href="mailto:<?php echo esc_attr( $o['email'] ); ?>"><?php echo esc_html( $o['email'] ); ?></a>
						<?php endif; ?>
					</div>
				</div>

				<div class="fl-order__col">
					<div class="fl-label"><?php esc_html_e( 'To prepare', 'fleuriste' ); ?></div>
					<ul class="fl-items">
						<?php foreach ( $o['items'] as $it ) : ?>
							<li>
								<span class="fl-strong"><?php echo (int) $it['qty']; ?> × <?php echo esc_html( $it['name'] ); ?></span>
								<?php if ( $it['details'] ) : ?>
									<span class="fl-details"><?php echo esc_html( implode( ' · ', $it['details'] ) ); ?></span>
								<?php endif; ?>
								<?php if ( '' !== $it['message'] ) : ?>
									<blockquote class="fl-card-msg">“<?php echo esc_html( $it['message'] ); ?>”</blockquote>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
					<?php if ( $o['subs'] ) : ?>
						<div class="fl-label"><?php esc_html_e( 'Extras added (suborders)', 'fleuriste' ); ?></div>
						<ul class="fl-subs">
							<?php foreach ( $o['subs'] as $sb ) : ?>
								<li class="<?php echo $sb['gone'] ? 'is-gone' : ''; ?>">
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=fleuriste-new-order&order=' . $sb['id'] ) ); ?>">#<?php echo esc_html( $sb['number'] ); ?></a>
									<?php echo esc_html( $sb['items'] ); ?> · <?php echo esc_html( $sb['total'] ); ?>
									<span class="fl-badge<?php echo $sb['unpaid'] ? ' fl-badge--warn' : ''; ?>"><?php echo esc_html( $sb['unpaid'] ? __( 'Awaiting payment', 'fleuriste' ) : $sb['status'] ); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<?php if ( $o['note'] ) : ?>
						<div class="fl-label"><?php esc_html_e( 'Customer note', 'fleuriste' ); ?></div>
						<div class="fl-note"><?php echo esc_html( $o['note'] ); ?></div>
					<?php endif; ?>
				</div>
			</div>

			<footer class="fl-order__actions">
				<?php if ( $f[1] ) : ?>
					<button type="button" class="button button-primary fl-act" data-to="<?php echo esc_attr( $f[1] ); ?>"><?php echo esc_html( $f[2] ); ?> →</button>
				<?php endif; ?>
				<?php if ( ! empty( $o['pickup'] ) ) : ?>
					<span class="fl-pickup-note"><span class="dashicons dashicons-store" aria-hidden="true"></span> <?php esc_html_e( 'Customer collects – no driver', 'fleuriste' ); ?></span>
				<?php elseif ( $has_drivers && 'cancelled' !== $o['status'] ) : ?>
					<label class="fl-assign-wrap"><span class="dashicons dashicons-car" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Driver', 'fleuriste' ); ?></span>
						<?php echo fleuriste_driver_select( 'driver', $o['driver'], 'fl-assign' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</label>
				<?php elseif ( ! $has_drivers ) : ?>
					<a class="button-link" href="<?php echo esc_url( admin_url( 'admin.php?page=fleuriste-drivers' ) ); ?>"><?php esc_html_e( 'Add drivers', 'fleuriste' ); ?></a>
				<?php endif; ?>
				<?php if ( in_array( $o['status'], array( 'pending', 'on-hold', 'processing', 'preparing', 'out-for-delivery' ), true ) ) : ?>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=fleuriste-new-order&order=' . $o['id'] ) ); ?>"><span class="dashicons dashicons-edit" aria-hidden="true"></span> <?php esc_html_e( 'Edit', 'fleuriste' ); ?></a>
					<a class="button" href="<?php echo esc_url( $o['edit_url'] . '#fleuriste-delivery-box' ); ?>"><span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span> <?php esc_html_e( 'Change delivery', 'fleuriste' ); ?></a>
				<?php endif; ?>
				<?php if ( ! $o['parent'] && ! in_array( $o['status'], array( 'cancelled', 'refunded', 'failed' ), true ) ) : ?>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=fleuriste-new-order&parent=' . $o['id'] ) ); ?>" title="<?php esc_attr_e( 'Add extra items to this order (suborder)', 'fleuriste' ); ?>"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> <?php esc_html_e( 'Add to order', 'fleuriste' ); ?></a>
				<?php endif; ?>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=fleuriste-new-order&copy=' . $o['id'] ) ); ?>" title="<?php esc_attr_e( 'Start a new order from a copy of this one', 'fleuriste' ); ?>"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span> <?php esc_html_e( 'Duplicate', 'fleuriste' ); ?></a>
				<a class="button" href="<?php echo esc_url( $o['edit_url'] ); ?>"><?php esc_html_e( 'Open order', 'fleuriste' ); ?></a>
				<?php
				if ( function_exists( 'fleuriste_doc_board_buttons' ) && 'cancelled' !== $o['status'] ) {
					echo fleuriste_doc_board_buttons( $o['id'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped while building.
				}
				?>
				<?php
				if ( function_exists( 'fleuriste_notify_panel' ) ) {
					echo fleuriste_notify_panel( wc_get_order( $o['id'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				?>
				<?php if ( in_array( $o['status'], fleuriste_board_active_statuses(), true ) ) : ?>
					<?php echo fleuriste_cancel_panel( wc_get_order( $o['id'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php elseif ( 'cancelled' === $o['status'] ) : ?>
					<span class="fl-cancel-reason"><?php echo esc_html( (string) wc_get_order( $o['id'] )->get_meta( '_fleuriste_cancel_reason' ) ); ?></span>
					<button type="button" class="button-link fl-act fl-act--undo" data-to="processing"><?php esc_html_e( 'Reopen order', 'fleuriste' ); ?></button>
				<?php elseif ( 'completed' === $o['status'] ) : ?>
					<button type="button" class="button-link fl-act" data-to="out-for-delivery"><?php esc_html_e( 'Undo – back to out for delivery', 'fleuriste' ); ?></button>
				<?php endif; ?>
			</footer>
		</article>
	<?php endforeach; ?>
	<?php
	return ob_get_clean();
}

/**
 * Reasons for cancelling.
 *
 * @return string[]
 */
function fleuriste_cancel_reasons() {
	return apply_filters(
		'fleuriste_cancel_reasons',
		array(
			__( 'Customer asked to cancel', 'fleuriste' ),
			__( 'Flowers not available', 'fleuriste' ),
			__( 'We can\'t deliver to this address or date', 'fleuriste' ),
			__( 'Payment not received', 'fleuriste' ),
			__( 'Duplicate order', 'fleuriste' ),
			__( 'Other', 'fleuriste' ),
		)
	);
}

/**
 * Was this order paid online (so the money must be refunded)?
 *
 * @param WC_Order $order Order.
 * @return bool
 */
function fleuriste_paid_online( $order ) {
	return $order->is_paid() && ! in_array( $order->get_payment_method(), array( 'cod', 'bacs', 'cheque', 'fleuriste_instore', '' ), true );
}

/**
 * The cancel panel for a board card.
 *
 * @param WC_Order $order Order.
 * @return string
 */
function fleuriste_cancel_panel( $order ) {
	$wants = function_exists( 'fleuriste_buyer_wants_whatsapp' ) && fleuriste_buyer_wants_whatsapp( $order );
	$phone = function_exists( 'fleuriste_buyer_phone' ) ? fleuriste_buyer_phone( $order ) : '';
	ob_start();
	?>
	<details class="fl-cancel">
		<summary class="button-link button-link-delete"><?php esc_html_e( 'Cancel order', 'fleuriste' ); ?></summary>
		<div class="fl-cancel__box">
			<strong><?php echo esc_html( sprintf( /* translators: %s: order number */ __( 'Cancel order #%s', 'fleuriste' ), $order->get_order_number() ) ); ?></strong>
			<label><?php esc_html_e( 'Reason', 'fleuriste' ); ?>
				<select class="fl-cancel__reason">
					<?php foreach ( fleuriste_cancel_reasons() as $reason ) : ?>
						<option><?php echo esc_html( $reason ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label><?php esc_html_e( 'Note for the buyer (optional)', 'fleuriste' ); ?>
				<textarea class="fl-cancel__note" rows="2" placeholder="<?php esc_attr_e( 'e.g. We are very sorry – we will call you to suggest another bouquet.', 'fleuriste' ); ?>"></textarea>
			</label>
			<div class="fl-cancel__opts">
				<label><input type="checkbox" class="fl-cancel__email" <?php checked( (bool) $order->get_billing_email() ); ?> <?php disabled( ! $order->get_billing_email() ); ?>> <?php esc_html_e( 'Tell the buyer by email', 'fleuriste' ); ?></label>
				<label><input type="checkbox" class="fl-cancel__wa" <?php checked( $wants && $phone ); ?> <?php disabled( ! $phone ); ?>> <?php esc_html_e( 'and by WhatsApp', 'fleuriste' ); ?></label>
			</div>
			<?php if ( fleuriste_paid_online( $order ) ) : ?>
				<p class="fl-cancel__warn"><?php echo wp_kses_post( sprintf( /* translators: 1: amount, 2: link */ __( 'This order was paid online (%1$s). Cancelling does not refund it – after cancelling, <a href="%2$s" target="_blank">open the order</a> and click "Refund".', 'fleuriste' ), esc_html( html_entity_decode( wp_strip_all_tags( wc_price( $order->get_total() ) ) ) ), esc_url( $order->get_edit_order_url() ) ) ); ?></p>
			<?php endif; ?>
			<p class="fl-cancel__hint"><?php esc_html_e( 'Items go back into stock. You can reopen the order later from the "Delivered / cancelled" tab.', 'fleuriste' ); ?></p>
			<div class="fl-cancel__btns">
				<button type="button" class="button fl-cancel__go"><?php esc_html_e( 'Cancel this order', 'fleuriste' ); ?></button>
				<button type="button" class="button-link fl-cancel__keep"><?php esc_html_e( 'Keep the order', 'fleuriste' ); ?></button>
			</div>
		</div>
	</details>
	<?php
	return ob_get_clean();
}

/**
 * Admin page wrapper.
 */
function fleuriste_board_page() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$view   = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'today';
	$driver = isset( $_GET['driver'] ) ? sanitize_key( wp_unslash( $_GET['driver'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	?>
	<div class="wrap fl-board" data-nonce="<?php echo esc_attr( wp_create_nonce( 'fleuriste_board' ) ); ?>" data-view="<?php echo esc_attr( $view ); ?>" data-driver="<?php echo esc_attr( $driver ); ?>">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Deliveries', 'fleuriste' ); ?></h1>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=fleuriste-new-order' ) ); ?>" class="page-title-action"><?php esc_html_e( '+ New order', 'fleuriste' ); ?></a>
		<label class="fl-board__search"><span class="screen-reader-text"><?php esc_html_e( 'Find an order', 'fleuriste' ); ?></span><span class="dashicons dashicons-search" aria-hidden="true"></span><input type="search" placeholder="<?php esc_attr_e( 'Find an order: number, name or phone', 'fleuriste' ); ?>"></label>
		<span class="fl-board__updated" aria-live="polite"></span>
		<p class="fl-board__intro"><?php esc_html_e( 'Orders by delivery date and time. Use the button on each order to move it to the next step; the list refreshes by itself every minute.', 'fleuriste' ); ?></p>
		<div class="fl-board__inner"><?php echo fleuriste_board_html( $view, $driver ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped while building. ?></div>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * Ajax: refresh and status changes
 * ---------------------------------------------------------------------- */

add_action(
	'wp_ajax_fleuriste_board',
	function () {
		check_ajax_referer( 'fleuriste_board', 'nonce' );
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'fleuriste' ) ), 403 );
		}
		$view   = isset( $_POST['view'] ) ? sanitize_key( wp_unslash( $_POST['view'] ) ) : 'today';
		$driver = isset( $_POST['driver'] ) ? sanitize_key( wp_unslash( $_POST['driver'] ) ) : '';
		$msg    = '';
		$link   = '';
		if ( ! empty( $_POST['order'] ) && isset( $_POST['assign'] ) && function_exists( 'fleuriste_assign_driver' ) ) {
			$order = wc_get_order( absint( $_POST['order'] ) );
			if ( $order ) {
				$to = sanitize_key( wp_unslash( $_POST['assign'] ) );
				fleuriste_assign_driver( $order, $to );
				$d   = fleuriste_driver( $to );
				$msg = $d
					/* translators: 1: order number, 2: driver */
					? sprintf( __( 'Order #%1$s assigned to %2$s', 'fleuriste' ), $order->get_order_number(), $d['name'] )
					/* translators: %s: order number */
					: sprintf( __( 'Order #%s has no driver', 'fleuriste' ), $order->get_order_number() );
			}
		}
		if ( ! empty( $_POST['order'] ) && ! empty( $_POST['to'] ) ) {
			$order = wc_get_order( absint( $_POST['order'] ) );
			$to    = sanitize_key( wp_unslash( $_POST['to'] ) );
			$ok    = array( 'processing', 'preparing', 'out-for-delivery', 'completed', 'cancelled' );
			if ( ! $order || ! in_array( $to, $ok, true ) ) {
				wp_send_json_error( array( 'message' => __( 'That order could not be updated.', 'fleuriste' ) ), 400 );
			}
			$names = fleuriste_board_flow();
			$note  = sprintf(
				/* translators: %s: user name */
				__( 'Updated from the Deliveries board by %s.', 'fleuriste' ),
				wp_get_current_user()->display_name
			);
			if ( 'cancelled' === $to ) {
				$reason = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '';
				$extra  = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
				$order->update_meta_data( '_fleuriste_cancel_reason', $reason );
				$order->update_meta_data( '_fleuriste_cancel_note', $extra );
				/* translators: 1: reason, 2: note */
				$note .= ' ' . sprintf( __( 'Reason: %1$s. %2$s', 'fleuriste' ), $reason, $extra );
			} elseif ( 'cancelled' === $order->get_status() ) {
				$order->delete_meta_data( '_fleuriste_cancel_reason' );
				$order->delete_meta_data( '_fleuriste_cancel_note' );
			}
			$order->update_status( $to, trim( $note ) );
			$msg = sprintf(
				/* translators: 1: order number, 2: status */
				__( 'Order #%1$s is now: %2$s', 'fleuriste' ),
				$order->get_order_number(),
				isset( $names[ $to ] ) ? $names[ $to ][0] : $to
			);
			// Tell the buyer about a cancellation.
			if ( 'cancelled' === $to && function_exists( 'fleuriste_buyer_message' ) ) {
				$text = fleuriste_buyer_message( $order );
				$told = array();
				if ( ! empty( $_POST['email'] ) && fleuriste_email_buyer( $order, $text ) ) {
					$told[] = __( 'email', 'fleuriste' );
				}
				if ( ! empty( $_POST['whatsapp'] ) ) {
					$res = fleuriste_whatsapp_buyer_api( $order );
					if ( $res[0] ) {
						$told[] = __( 'WhatsApp', 'fleuriste' );
					} else {
						$link   = fleuriste_whatsapp_buyer_link( $order, $text );
						$told[] = __( 'WhatsApp (sent from the shop\'s phone)', 'fleuriste' );
					}
				}
				if ( $told ) {
					/* translators: 1: channels, 2: message */
					$order->add_order_note( sprintf( __( 'Buyer notified by %1$s: %2$s', 'fleuriste' ), implode( ' + ', $told ), $text ) );
					$msg .= ' · ' . __( 'buyer notified', 'fleuriste' );
				}
				if ( fleuriste_paid_online( $order ) ) {
					$msg .= ' · ' . __( 'remember to refund the payment in the order screen', 'fleuriste' );
				}
			}
		}
		wp_send_json_success(
			array(
				'html'    => fleuriste_board_html( $view, $driver, isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '' ),
				'message' => $msg,
				'link'    => $link,
			)
		);
	}
);

/* -------------------------------------------------------------------------
 * Styles and script for the board
 * ---------------------------------------------------------------------- */

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		// Colours for the new statuses on the normal Orders list.
		wp_add_inline_style( 'common', '.order-status.status-preparing{background:#f8e7c8;color:#7a5410}.order-status.status-out-for-delivery{background:#d6e7f7;color:#1d4f80}' );

		if ( 'toplevel_page_fleuriste-deliveries' !== $hook ) {
			return;
		}
		wp_register_style( 'fleuriste-board', false, array(), FLEURISTE_VERSION );
		wp_enqueue_style( 'fleuriste-board' );
		wp_add_inline_style(
			'fleuriste-board',
			'
			.fl-board{max-width:1100px;--rose:#9E4F63;--ink:#2B1D21;--blush:#F6EDEE;--line:#EBDCDD}
			.fl-board__intro{color:#646970;margin:.25rem 0 1rem}
			.fl-board__updated{margin-left:.75rem;color:#646970;font-size:12px}
			.fl-board__search{position:relative;display:inline-flex;align-items:center;margin:0 0 0 10px;vertical-align:middle}
			.fl-board__search .dashicons{position:absolute;left:8px;color:#8c8f94}
			.fl-board__search input{min-width:280px;padding-left:30px!important;border-radius:999px!important;min-height:32px}
			.fl-badge--sub{background:#efe6f7;color:#5b2d82}
			.fl-badge--pickup{background:#e6f4ea;color:#1e6b34}.fl-badge--pickup .dashicons,.fl-pickup-note .dashicons{font-size:14px;width:14px;height:14px;vertical-align:-2px}.fl-pickup-note{color:#1e6b34;font-size:13px}
			.fl-subs{margin:2px 0 10px;padding:0;list-style:none}
			.fl-subs li{padding:3px 0;display:flex;flex-wrap:wrap;gap:6px;align-items:center}
			.fl-subs li.is-gone{opacity:.55;text-decoration:line-through}
			.fl-subs .fl-badge--warn{background:#fcf0f1;color:#b32d2e}
			@media (max-width:782px){.fl-board__search{display:flex;margin:10px 0 0}.fl-board__search input{min-width:0;width:100%}}
			.fl-board__tiles{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:0 0 16px}
			.fl-tile-stat{all:unset;cursor:pointer;background:#fff;border:1px solid var(--line);border-radius:12px;padding:14px 16px;display:flex;flex-direction:column;gap:2px;transition:border-color .15s,box-shadow .15s}
			.fl-tile-stat strong{font-size:28px;line-height:1.1;color:var(--ink)}
			.fl-tile-stat span{color:#646970}
			.fl-tile-stat:hover,.fl-tile-stat:focus-visible{border-color:var(--rose)}
			.fl-tile-stat.is-on{border-color:var(--rose);box-shadow:0 0 0 1px var(--rose)}
			.fl-tile-stat--rose strong{color:var(--rose)}
			.fl-tile-stat--alert{background:#fcf0f1;border-color:#e6a5ab}.fl-tile-stat--alert strong{color:#b32d2e}
			.fl-board__tabs{display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin:0 0 10px}
			.fl-tab{border:1px solid var(--line);background:#fff;border-radius:999px;padding:6px 12px;cursor:pointer;color:var(--ink)}
			.fl-tab.is-on{background:var(--ink);border-color:var(--ink);color:#fff}
			.fl-tab__n{opacity:.65;margin-left:2px}
			.fl-print{margin-left:6px!important}
			.fl-runsheet{display:flex;flex-wrap:wrap;gap:6px;align-items:center;width:100%;order:10;margin-top:6px;padding:8px 10px;background:#fff;border:1px solid var(--line);border-radius:10px}
			.fl-runsheet .dashicons{color:var(--rose)}.fl-driver-filter-wrap{margin-left:auto}
			.fl-badge--driver{background:#e7eef8;color:#1d4f80}.fl-badge--driver .dashicons{font-size:14px;width:14px;height:14px;vertical-align:-2px}.fl-badge--warn{background:#fcf0f1;color:#b32d2e}
			.fl-assign-wrap{display:inline-flex;align-items:center;gap:4px}.fl-assign-wrap .dashicons{color:#8c8f94}.fl-assign{min-width:150px}
			.fl-board__title{margin:18px 0 6px}.fl-board__title small{font-weight:400;color:#646970}
			.fl-group{margin:22px 0 8px;font-size:13px;text-transform:uppercase;letter-spacing:.06em;color:var(--rose)}
			.fl-empty{background:#fff;border:1px dashed var(--line);border-radius:12px;padding:28px;text-align:center;color:#646970}
			.fl-order{background:#fff;border:1px solid var(--line);border-left:4px solid #c3c4c7;border-radius:12px;margin:0 0 12px;overflow:hidden}
			.fl-order--rose{border-left-color:var(--rose)}.fl-order--gold{border-left-color:#c99a3b}.fl-order--blue{border-left-color:#3b7fc9}.fl-order--green{border-left-color:#2e8a57}
			.fl-order__head{display:flex;flex-wrap:wrap;gap:8px;align-items:center;padding:12px 16px;border-bottom:1px solid #f0f0f1}
			.fl-order__num{font-weight:600;font-size:15px;text-decoration:none}
			.fl-order__total{margin-left:auto;font-weight:600}.fl-order__total small{font-weight:400;color:#646970}
			.fl-badge{border-radius:999px;padding:2px 10px;font-size:12px;font-weight:500;background:#f0f0f1;color:#50575e}
			.fl-badge--rose{background:var(--blush);color:var(--rose)}.fl-badge--gold{background:#f8e7c8;color:#7a5410}.fl-badge--blue{background:#d6e7f7;color:#1d4f80}.fl-badge--green{background:#d7f0e0;color:#1e6b41}.fl-badge--dark{background:var(--ink);color:#fff}
			.fl-order__body{display:grid;grid-template-columns:1fr 1.3fr;gap:16px;padding:12px 16px}
			.fl-label{font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#8c8f94;margin:8px 0 2px}
			.fl-label:first-child{margin-top:0}
			.fl-strong{font-weight:600;color:var(--ink)}
			.fl-links{display:flex;flex-wrap:wrap;gap:4px 12px;margin-top:2px}.fl-links .dashicons{font-size:16px;width:16px;height:16px;vertical-align:-3px;text-decoration:none}
			.fl-items{margin:0;padding:0;list-style:none}.fl-items li{margin:0 0 8px}
			.fl-details{display:block;color:#50575e}
			.fl-card-msg{margin:6px 0 0;padding:8px 12px;background:var(--blush);border-radius:8px;font-style:italic;color:var(--ink)}
			.fl-note{background:#fcf9e8;border-radius:8px;padding:6px 10px}
			.fl-order__actions{display:flex;flex-wrap:wrap;gap:8px;align-items:center;padding:10px 16px;background:#fafafa;border-top:1px solid #f0f0f1}
			.fl-order__actions .button-primary{background:var(--rose);border-color:var(--rose)}
			.fl-order__actions .button-primary:hover{background:var(--ink);border-color:var(--ink)}
			.fl-order__actions .button .dashicons{font-size:16px;width:16px;height:16px;vertical-align:-3px}
			.fl-cancel{margin-left:auto}
			.fl-cancel>summary{list-style:none;cursor:pointer;padding:4px 0}.fl-cancel>summary::-webkit-details-marker{display:none}
			.fl-cancel[open]{flex:1 1 100%;order:40;margin-left:0}
			.fl-cancel__box{display:grid;gap:8px;background:#fcf0f1;border:1px solid #f0c4c7;border-radius:10px;padding:12px;margin-top:6px}
			.fl-cancel__box label{display:grid;gap:3px;font-size:13px}
			.fl-cancel__box select,.fl-cancel__box textarea{width:100%;max-width:520px}
			.fl-cancel__opts{display:flex;flex-wrap:wrap;gap:6px 16px}.fl-cancel__opts label{display:flex;gap:6px;align-items:center}
			.fl-cancel__warn{margin:0;background:#fff;border-left:3px solid #b32d2e;padding:6px 10px}
			.fl-cancel__hint{margin:0;color:#646970;font-size:12px}
			.fl-cancel__btns{display:flex;gap:12px;align-items:center}
			.fl-cancel__go{background:#b32d2e!important;border-color:#b32d2e!important;color:#fff!important}
			.fl-cancel-reason{margin-left:auto;color:#646970;font-style:italic}
			.fl-notify[open]{flex:1 1 100%;order:30}
			.fl-notify>summary{list-style:none;cursor:pointer}.fl-notify>summary::-webkit-details-marker{display:none}
			.fl-notify>summary .dashicons{font-size:16px;width:16px;height:16px;vertical-align:-3px}
			.fl-wa-pill{background:#d7f0e0;color:#1e6b41;border-radius:999px;padding:0 6px;font-size:11px;margin-left:4px}
			.fl-notify__box{background:#fff;border:1px solid var(--line);border-radius:10px;padding:10px;margin-top:8px}
			.fl-notify__text{width:100%;font-size:13px}
			.fl-notify__opts{display:flex;flex-wrap:wrap;gap:8px 16px;align-items:center;margin-top:6px}
			.fl-notify__opts em{color:#8c8f94}
			.fl-notify__send{margin-left:auto!important}
			.fl-notify__last{margin:6px 0 0;color:#8c8f94;font-size:12px}
			.fl-board.is-busy .fl-board__inner{opacity:.55;pointer-events:none}
			@media (max-width:782px){.fl-board__tiles{grid-template-columns:repeat(2,minmax(0,1fr))}.fl-order__body{grid-template-columns:1fr}.fl-board .fl-print{display:none}}
			@media print{
				#adminmenumain,#wpadminbar,#wpfooter,.fl-board__tiles,.fl-board__tabs,.fl-order__actions,.fl-board__intro,.notice{display:none!important}
				#wpcontent{margin:0!important;padding:0!important}
				.fl-order{break-inside:avoid;border:1px solid #999}
			}'
		);
		wp_register_script( 'fleuriste-board', false, array(), FLEURISTE_VERSION, true );
		wp_enqueue_script( 'fleuriste-board' );
		wp_add_inline_script(
			'fleuriste-board',
			'( function () {
				var root = document.querySelector( ".fl-board" );
				if ( ! root ) { return; }
				var inner = root.querySelector( ".fl-board__inner" );
				var stamp = root.querySelector( ".fl-board__updated" );
				var confirmCancel = ' . wp_json_encode( __( 'Cancel this order? This can be undone from the order screen.', 'fleuriste' ) ) . ';
				var updatedText = ' . wp_json_encode( __( 'Updated', 'fleuriste' ) ) . ';
				var search = root.querySelector( ".fl-board__search input" );
				var searchTimer;
				search.addEventListener( "input", function () {
					clearTimeout( searchTimer );
					searchTimer = setTimeout( function () {
						var q = search.value.trim();
						if ( q.length === 1 ) { return; }
						root.dataset.q = q;
						load();
					}, 350 );
				} );
				function load( extra ) {
					var body = new URLSearchParams( Object.assign( { action: "fleuriste_board", nonce: root.dataset.nonce, view: root.dataset.view, driver: root.dataset.driver || "", q: root.dataset.q || "" }, extra || {} ) );
					root.classList.add( "is-busy" );
					return fetch( ajaxurl, { method: "POST", credentials: "same-origin", body: body } )
						.then( function ( r ) { return r.json(); } )
						.then( function ( res ) {
							root.classList.remove( "is-busy" );
							if ( ! res || ! res.success ) { alert( ( res && res.data && res.data.message ) || "Error" ); return; }
							inner.innerHTML = res.data.html;
							stamp.textContent = ( res.data.message ? res.data.message + " · " : "" ) + updatedText + " " + new Date().toLocaleTimeString( [], { hour: "2-digit", minute: "2-digit" } );
							return res;
						} )
						.catch( function () { root.classList.remove( "is-busy" ); } );
				}
				root.addEventListener( "change", function ( e ) {
					if ( e.target.classList.contains( "fl-driver-filter" ) ) {
						root.dataset.driver = e.target.value;
						history.replaceState( null, "", location.pathname + "?page=fleuriste-deliveries&view=" + root.dataset.view + ( e.target.value ? "&driver=" + e.target.value : "" ) );
						load();
					}
					if ( e.target.classList.contains( "fl-assign" ) ) {
						load( { order: e.target.closest( ".fl-order" ).dataset.id, assign: e.target.value } );
					}
				} );
				root.addEventListener( "click", function ( e ) {
					var v = e.target.closest( "[data-view]" );
					if ( v && v !== root ) {
						root.dataset.view = v.dataset.view;
						root.dataset.q = ""; search.value = "";
						history.replaceState( null, "", location.pathname + "?page=fleuriste-deliveries&view=" + v.dataset.view + ( root.dataset.driver ? "&driver=" + root.dataset.driver : "" ) );
						load();
						return;
					}
					var a = e.target.closest( ".fl-act" );
					if ( a ) {
						if ( a.dataset.to === "cancelled" && ! window.confirm( confirmCancel ) ) { return; }
						var id = a.closest( ".fl-order" ).dataset.id;
						load( { order: id, to: a.dataset.to } ).then( function () {
							// Offer to tell the buyer about the new status.
							var card = inner.querySelector( ".fl-order[data-id=\"" + id + "\"] .fl-notify" );
							if ( card ) { card.open = true; card.scrollIntoView( { block: "nearest", behavior: "smooth" } ); }
						} );
						return;
					}
					var keep = e.target.closest( ".fl-cancel__keep" );
					if ( keep ) { keep.closest( ".fl-cancel" ).open = false; return; }
					var go = e.target.closest( ".fl-cancel__go" );
					if ( go ) {
						var c = go.closest( ".fl-order" );
						var wa = c.querySelector( ".fl-cancel__wa" ).checked;
						var pop = wa ? window.open( "about:blank", "_blank" ) : null;
						load( {
							order: c.dataset.id, to: "cancelled",
							reason: c.querySelector( ".fl-cancel__reason" ).value,
							note: c.querySelector( ".fl-cancel__note" ).value,
							email: c.querySelector( ".fl-cancel__email" ).checked ? 1 : "",
							whatsapp: wa ? 1 : ""
						} ).then( function ( res ) {
							if ( pop ) { if ( res && res.data && res.data.link ) { pop.location = res.data.link; } else { pop.close(); } }
						} );
						return;
					}
					var send = e.target.closest( ".fl-notify__send" );
					if ( send ) {
						var box = send.closest( ".fl-order" );
						var popup = box.querySelector( ".fl-notify__wa" ).checked ? window.open( "about:blank", "_blank" ) : null;
						send.disabled = true;
						var body = new URLSearchParams( {
							action: "fleuriste_notify", nonce: root.dataset.nonce, order: box.dataset.id,
							text: box.querySelector( ".fl-notify__text" ).value,
							email: box.querySelector( ".fl-notify__email" ).checked ? 1 : "",
							whatsapp: box.querySelector( ".fl-notify__wa" ).checked ? 1 : ""
						} );
						fetch( ajaxurl, { method: "POST", credentials: "same-origin", body: body } ).then( function ( r ) { return r.json(); } ).then( function ( res ) {
							send.disabled = false;
							if ( ! res || ! res.success ) { if ( popup ) { popup.close(); } alert( ( res && res.data && res.data.message ) || "Error" ); return; }
							if ( res.data.link && popup ) { popup.location = res.data.link; } else if ( popup ) { popup.close(); }
							stamp.textContent = res.data.message;
							box.querySelector( ".fl-notify" ).open = false;
						} ).catch( function () { send.disabled = false; if ( popup ) { popup.close(); } } );
					}
				} );
				window.flBoardReload = load;
				setInterval( function () { if ( ! document.hidden ) { load(); } }, 60000 );
			} )();'
		);
	}
);
