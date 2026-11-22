<?php
/**
 * Delivery & pickup: delivery areas with a fee, delivery days and times, and
 * pickup from the shop with its own days and times.
 *
 * Shop settings → Delivery & pickup.
 *
 * - Areas become the "Area" list of the Mauritius address at checkout; the
 *   "Fleuriste delivery" shipping method charges the fee of the chosen area.
 * - Pickup uses WooCommerce's own "Pickup" option (Delivery / Pickup switch at checkout).
 * - Dates and time slots are asked at checkout (inc/delivery-checkout.php).
 *
 * @package Fleuriste
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FLEURISTE_DL_OPTION', 'fleuriste_delivery' );

/**
 * Default settings.
 *
 * @return array
 */
function fleuriste_dl_defaults() {
	$areas = array(
		'Port Louis'                  => 150,
		'Beau Bassin / Rose Hill'     => 150,
		'Quatre Bornes'               => 150,
		'Vacoas / Phoenix'            => 150,
		'Curepipe'                    => 150,
		'Moka / Ebène'                => 150,
		'Pamplemousses / Terre Rouge' => 200,
		'Rose Belle / Nouvelle France' => 250,
		'Flic en Flac / Tamarin'      => 250,
		'Grand Baie / Pereybère'      => 250,
		'Triolet / Trou aux Biches'   => 250,
		'Goodlands / Grand Gaube'     => 300,
		'Centre de Flacq / Belle Mare' => 300,
		'Mahébourg / Plaisance'       => 300,
		'Souillac / Bel Ombre'        => 350,
		'Black River / Le Morne'      => 350,
	);
	$regions = array();
	foreach ( $areas as $name => $price ) {
		$regions[] = array(
			'id'    => fleuriste_dl_region_code( $name ),
			'name'  => $name,
			'price' => $price,
		);
	}
	return array(
		'regions'        => $regions,
		'free_over'      => '',
		'delivery_on'    => 'yes',
		'holidays'       => '',
		'pickup_on'      => 'yes',
		'pickup_title'   => __( 'Pickup from the shop', 'fleuriste' ),
		'pickup_same'    => 'yes',
		'pickup_address' => '',
		'pickup_town'    => '',
		'pickup_area'    => '',
		'pickup_details' => __( 'Show your order number at the counter.', 'fleuriste' ),
		'pickup_slots'   => "Morning (9am – 12pm)\nAfternoon (12pm – 4pm)\nEvening (4pm – 6pm)",
		'pickup_cutoff'  => '16:00',
		'pickup_closed'  => array(),
	);
}

/**
 * Area code from a name, e.g. "Grand Baie / Pereybère" → "GRAND-BAIE-PEREYBERE".
 *
 * @param string $name Area name.
 * @return string
 */
function fleuriste_dl_region_code( $name ) {
	$code = strtoupper( sanitize_title( remove_accents( $name ) ) );
	return '' !== $code ? substr( $code, 0, 60 ) : 'AREA-' . wp_rand( 100, 999 );
}

/**
 * All settings.
 *
 * @return array
 */
function fleuriste_dl_settings() {
	$saved = get_option( FLEURISTE_DL_OPTION, array() ); // WordPress caches options, so this is cheap.
	return array_merge( fleuriste_dl_defaults(), is_array( $saved ) ? $saved : array() );
}

/**
 * One setting.
 *
 * @param string $key Key.
 * @return mixed
 */
function fleuriste_dl_get( $key ) {
	$s = fleuriste_dl_settings();
	return isset( $s[ $key ] ) ? $s[ $key ] : '';
}

/**
 * Delivery areas: id, name, price.
 *
 * @return array[]
 */
function fleuriste_dl_regions() {
	return array_values( array_filter( (array) fleuriste_dl_get( 'regions' ), 'is_array' ) );
}

/**
 * One area by code (any case).
 *
 * @param string $code Area code.
 * @return array|null
 */
function fleuriste_dl_region( $code ) {
	$code = strtoupper( (string) $code );
	foreach ( fleuriste_dl_regions() as $r ) {
		if ( strtoupper( $r['id'] ) === $code ) {
			return $r;
		}
	}
	return null;
}

/**
 * Holidays (Y-m-d) when there is no delivery and no pickup.
 *
 * @return string[]
 */
function fleuriste_dl_holidays() {
	preg_match_all( '/\d{4}-\d{2}-\d{2}/', (string) fleuriste_dl_get( 'holidays' ), $m );
	return array_values( array_unique( $m[0] ) );
}

/**
 * Pickup time slots (labels).
 *
 * @return string[]
 */
function fleuriste_dl_pickup_slots() {
	return array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) fleuriste_dl_get( 'pickup_slots' ) ) ) ) );
}

/**
 * Is delivery offered? Is pickup offered?
 *
 * @return bool
 */
function fleuriste_dl_delivery_on() {
	return 'yes' === fleuriste_dl_get( 'delivery_on' );
}

/**
 * Is pickup from the shop offered?
 *
 * @return bool
 */
function fleuriste_dl_pickup_on() {
	return 'yes' === fleuriste_dl_get( 'pickup_on' );
}

/**
 * Shipping method IDs that mean "the customer collects".
 *
 * @return string[]
 */
function fleuriste_dl_pickup_method_ids() {
	return array( 'pickup_location', 'local_pickup' );
}

/**
 * Is this order collected at the shop?
 *
 * @param WC_Order $order Order.
 * @return bool
 */
function fleuriste_order_is_pickup( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return false;
	}
	if ( 'yes' === $order->get_meta( '_fleuriste_pickup' ) ) {
		return true;
	}
	foreach ( $order->get_items( 'shipping' ) as $ship ) {
		if ( in_array( $ship->get_method_id(), fleuriste_dl_pickup_method_ids(), true ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Does the current cart use pickup?
 *
 * @return bool
 */
function fleuriste_dl_cart_is_pickup() {
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		return false;
	}
	foreach ( (array) WC()->session->get( 'chosen_shipping_methods', array() ) as $rate ) {
		$method = strtok( (string) $rate, ':' );
		if ( in_array( $method, fleuriste_dl_pickup_method_ids(), true ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Where customers pick up: the shop address (Shop details) unless a different one is set.
 *
 * @return array street, town, region (code).
 */
function fleuriste_dl_pickup_location() {
	if ( 'no' !== fleuriste_dl_get( 'pickup_same' ) && function_exists( 'fleuriste_company' ) ) {
		$c = fleuriste_company();
		return array(
			'street' => (string) $c['street'],
			'town'   => (string) $c['town'],
			'region' => (string) $c['region'],
		);
	}
	return array(
		'street' => (string) fleuriste_dl_get( 'pickup_address' ),
		'town'   => (string) fleuriste_dl_get( 'pickup_town' ),
		'region' => (string) fleuriste_dl_get( 'pickup_area' ),
	);
}

/**
 * Pickup address as one line.
 *
 * @return string
 */
function fleuriste_dl_pickup_address() {
	$loc   = fleuriste_dl_pickup_location();
	$area  = fleuriste_dl_region( $loc['region'] );
	$parts = array();
	foreach ( array( $loc['street'], $loc['town'], $area ? $area['name'] : '' ) as $part ) {
		$part = trim( (string) $part );
		if ( '' !== $part && ! in_array( strtolower( $part ), array_map( 'strtolower', $parts ), true ) ) {
			$parts[] = $part;
		}
	}
	return implode( ', ', $parts );
}

/* -------------------------------------------------------------------------
 * Areas in the Mauritius address (checkout, My Account, order screens)
 * ---------------------------------------------------------------------- */

add_filter(
	'woocommerce_states',
	function ( $states ) {
		$list = array();
		foreach ( fleuriste_dl_regions() as $r ) {
			$list[ $r['id'] ] = $r['name'];
		}
		if ( $list ) {
			$code            = function_exists( 'fleuriste_shop_country' ) ? fleuriste_shop_country() : 'MU';
			$states[ $code ] = $list;
		}
		return $states;
	},
	20
);

/* -------------------------------------------------------------------------
 * Shipping method: fee of the chosen area
 * ---------------------------------------------------------------------- */

add_action(
	'woocommerce_shipping_init',
	function () {
		if ( class_exists( 'Fleuriste_Delivery_Method' ) ) {
			return;
		}
		/**
		 * Delivery with a fee per area (Shop settings → Delivery & pickup).
		 */
		class Fleuriste_Delivery_Method extends WC_Shipping_Method {
			/**
			 * Constructor.
			 *
			 * @param int $instance_id Instance ID.
			 */
			public function __construct( $instance_id = 0 ) {
				$this->id                 = 'fleuriste_delivery';
				$this->instance_id        = absint( $instance_id );
				$this->method_title       = __( 'Delivery by area', 'fleuriste' );
				$this->method_description = __( 'Charges the fee of the customer\'s area. Areas and fees: Shop settings → Delivery & pickup.', 'fleuriste' );
				$this->supports           = array( 'shipping-zones', 'instance-settings' );
				$this->instance_form_fields = array(
					'title' => array(
						'title'   => __( 'Name at checkout', 'fleuriste' ),
						'type'    => 'text',
						'default' => __( 'Delivery', 'fleuriste' ),
					),
				);
				$this->init_settings();
				$this->title   = $this->get_option( 'title', __( 'Delivery', 'fleuriste' ) );
				$this->enabled = $this->get_option( 'enabled', 'yes' );
				add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
			}

			/**
			 * Add the rate for the chosen area.
			 *
			 * @param array $package Package.
			 */
			public function calculate_shipping( $package = array() ) {
				if ( ! fleuriste_dl_delivery_on() ) {
					return;
				}
				$region = fleuriste_dl_region( isset( $package['destination']['state'] ) ? $package['destination']['state'] : '' );
				if ( ! $region ) {
					// No area yet: offer delivery at no cost so "Delivery" stays selected; the area is
					// required, and the real fee replaces this as soon as the customer picks it.
					$this->add_rate(
						array(
							'id'      => $this->get_rate_id(),
							/* translators: %s: method name */
							'label'   => sprintf( __( '%s – choose your area to see the fee', 'fleuriste' ), $this->title ),
							'cost'    => 0,
							'package' => $package,
						)
					);
					return;
				}
				$cost = (float) $region['price'];
				$free = (float) fleuriste_dl_get( 'free_over' );
				$sub  = isset( $package['contents_cost'] ) ? (float) $package['contents_cost'] : 0;
				if ( $free > 0 && $sub >= $free ) {
					$cost = 0;
				}
				$this->add_rate(
					array(
						'id'      => $this->get_rate_id(),
						/* translators: 1: method name, 2: area */
						'label'   => sprintf( __( '%1$s – %2$s', 'fleuriste' ), $this->title, $region['name'] ),
						'cost'    => $cost,
						'package' => $package,
					)
				);
			}
		}
	}
);

add_filter(
	'woocommerce_shipping_methods',
	function ( $methods ) {
		$methods['fleuriste_delivery'] = 'Fleuriste_Delivery_Method';
		return $methods;
	}
);

/**
 * Put "Delivery by area" in the Mauritius shipping zone (created if needed) and
 * switch off the other delivery prices there, so customers see one delivery fee.
 * Also writes the pickup settings into WooCommerce's Pickup option.
 */
function fleuriste_dl_sync() {
	if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
		return;
	}
	global $wpdb;
	$country = function_exists( 'fleuriste_shop_country' ) ? fleuriste_shop_country() : 'MU';
	$zone    = null;
	foreach ( WC_Shipping_Zones::get_zones() as $z ) {
		foreach ( $z['zone_locations'] as $loc ) {
			if ( 'country' === $loc->type && $country === $loc->code ) {
				$zone = new WC_Shipping_Zone( $z['id'] );
				break 2;
			}
		}
	}
	if ( ! $zone ) {
		$zone = new WC_Shipping_Zone();
		$zone->set_zone_name( 'Mauritius' );
		$zone->add_location( $country, 'country' );
		$zone->save();
	}
	$ours = 0;
	foreach ( $zone->get_shipping_methods() as $instance_id => $method ) {
		if ( 'fleuriste_delivery' === $method->id ) {
			$ours = $instance_id;
		}
	}
	if ( ! $ours ) {
		$ours = $zone->add_shipping_method( 'fleuriste_delivery' );
	}
	$table = $wpdb->prefix . 'woocommerce_shipping_zone_methods';
	foreach ( $zone->get_shipping_methods() as $instance_id => $method ) {
		if ( in_array( $method->id, array( 'flat_rate', 'free_shipping', 'local_pickup' ), true ) && 'yes' === $method->enabled ) {
			$wpdb->update( $table, array( 'is_enabled' => 0 ), array( 'instance_id' => absint( $instance_id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
	}
	$wpdb->update( $table, array( 'is_enabled' => fleuriste_dl_delivery_on() ? 1 : 0 ), array( 'instance_id' => absint( $ours ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	if ( class_exists( 'WC_Cache_Helper' ) ) {
		WC_Cache_Helper::invalidate_cache_group( 'shipping_zones' );
		WC_Cache_Helper::get_transient_version( 'shipping', true );
	}

	// Pickup (WooCommerce's own "Pickup" option in the Checkout block).
	$pickup_loc = fleuriste_dl_pickup_location();
	$settings = get_option( 'woocommerce_pickup_location_settings', array() );
	$settings = array_merge(
		is_array( $settings ) ? $settings : array(),
		array(
			'enabled'    => fleuriste_dl_pickup_on() ? 'yes' : 'no',
			'title'      => fleuriste_dl_get( 'pickup_title' ),
			'tax_status' => 'none',
			'cost'       => '',
		)
	);
	update_option( 'woocommerce_pickup_location_settings', $settings );
	update_option(
		'pickup_location_pickup_locations',
		array(
			array(
				'name'    => get_bloginfo( 'name' ),
				'address' => array(
					'address_1' => $pickup_loc['street'],
					'city'      => $pickup_loc['town'],
					'state'     => $pickup_loc['region'],
					'postcode'  => '',
					'country'   => $country,
				),
				'details' => fleuriste_dl_get( 'pickup_details' ),
				'enabled' => true,
			),
		)
	);
	update_option( 'fleuriste_dl_synced', FLEURISTE_VERSION );
}

// First run: set up the areas and pickup once, so checkout uses them straight away.
add_action(
	'admin_init',
	function () {
		if ( ! get_option( 'fleuriste_dl_synced' ) && current_user_can( 'manage_woocommerce' ) && ! wp_doing_ajax() ) {
			fleuriste_dl_sync();
		}
	}
);

/* -------------------------------------------------------------------------
 * Settings page: Shop settings → Delivery & pickup
 * ---------------------------------------------------------------------- */

add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'fleuriste-company', __( 'Delivery & pickup', 'fleuriste' ), __( 'Delivery & pickup', 'fleuriste' ), 'manage_woocommerce', 'fleuriste-delivery', 'fleuriste_dl_page', 3 );
	},
	100
);

add_action(
	'admin_post_fleuriste_delivery',
	function () {
		check_admin_referer( 'fleuriste_delivery' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'fleuriste' ) );
		}
		$post = wp_unslash( $_POST );
		$text = function ( $key ) use ( $post ) {
			return isset( $post[ $key ] ) ? sanitize_text_field( (string) $post[ $key ] ) : '';
		};
		$area = function ( $key ) use ( $post ) {
			return isset( $post[ $key ] ) ? sanitize_textarea_field( str_replace( "\r\n", "\n", (string) $post[ $key ] ) ) : '';
		};
		$time = function ( $value ) {
			$value = trim( $value );
			return preg_match( '/^([01]?\d|2[0-3]):[0-5]\d$/', $value ) ? $value : '';
		};
		$days = function ( $key ) use ( $post ) {
			return isset( $post[ $key ] ) ? array_values( array_intersect( array_map( 'strval', (array) $post[ $key ] ), array( '1', '2', '3', '4', '5', '6', '7' ) ) ) : array();
		};

		// Areas: keep each area's code when it is renamed, so past orders still show it.
		$regions = array();
		$used    = array();
		$names   = isset( $post['r_name'] ) ? (array) $post['r_name'] : array();
		foreach ( $names as $i => $name ) {
			$name = sanitize_text_field( (string) $name );
			if ( '' === $name ) {
				continue;
			}
			$id = isset( $post['r_id'][ $i ] ) ? strtoupper( sanitize_key( str_replace( '-', '_', (string) $post['r_id'][ $i ] ) ) ) : '';
			$id = str_replace( '_', '-', $id );
			$id = $id ? $id : fleuriste_dl_region_code( $name );
			while ( isset( $used[ $id ] ) ) {
				$id .= '-2';
			}
			$used[ $id ] = true;
			$regions[]   = array(
				'id'    => $id,
				'name'  => $name,
				'price' => max( 0, (float) wc_format_decimal( isset( $post['r_price'][ $i ] ) ? $post['r_price'][ $i ] : 0 ) ),
			);
		}

		$settings = array(
			'regions'        => $regions,
			'free_over'      => '' === trim( (string) ( isset( $post['free_over'] ) ? $post['free_over'] : '' ) ) ? '' : max( 0, (float) wc_format_decimal( $post['free_over'] ) ),
			'delivery_on'    => empty( $post['delivery_on'] ) ? 'no' : 'yes',
			'holidays'       => implode( "\n", array_unique( ( preg_match_all( '/\d{4}-\d{2}-\d{2}/', $area( 'holidays' ), $m ) ? $m[0] : array() ) ) ),
			'pickup_on'      => empty( $post['pickup_on'] ) ? 'no' : 'yes',
			'pickup_title'   => $text( 'pickup_title' ) ? $text( 'pickup_title' ) : __( 'Pickup from the shop', 'fleuriste' ),
			'pickup_same'    => empty( $post['pickup_same'] ) ? 'no' : 'yes',
			'pickup_address' => $text( 'pickup_address' ),
			'pickup_town'    => $text( 'pickup_town' ),
			'pickup_area'    => $text( 'pickup_area' ),
			'pickup_details' => $area( 'pickup_details' ),
			'pickup_slots'   => $area( 'pickup_slots' ),
			'pickup_cutoff'  => $time( $text( 'pickup_cutoff' ) ),
			'pickup_closed'  => $days( 'pickup_closed' ),
		);
		update_option( FLEURISTE_DL_OPTION, $settings );

		// Delivery times and booking window (shared with the order form on the board).
		update_option( 'fleuriste_fo_slots', $area( 'delivery_slots' ) );
		update_option( 'fleuriste_fo_cutoff', $time( $text( 'delivery_cutoff' ) ) );
		update_option( 'fleuriste_fo_closed_days', $days( 'delivery_closed' ) );
		update_option( 'fleuriste_fo_max_days', max( 1, absint( $text( 'max_days' ) ) ) );

		fleuriste_dl_sync();
		wp_safe_redirect( admin_url( 'admin.php?page=fleuriste-delivery&saved=1' ) );
		exit;
	}
);

/**
 * Week day checkboxes.
 *
 * @param string   $name     Field name.
 * @param string[] $selected Selected days.
 */
function fleuriste_dl_day_boxes( $name, $selected ) {
	global $wp_locale;
	$selected = array_map( 'strval', (array) $selected );
	echo '<div class="fl-dl-days">';
	for ( $d = 1; $d <= 7; $d++ ) {
		printf(
			'<label><input type="checkbox" name="%1$s[]" value="%2$d"%3$s> %4$s</label>',
			esc_attr( $name ),
			(int) $d,
			checked( in_array( (string) $d, $selected, true ), true, false ),
			esc_html( $wp_locale->get_weekday_abbrev( $wp_locale->get_weekday( $d % 7 ) ) )
		);
	}
	echo '</div>';
}

/**
 * The settings page.
 */
function fleuriste_dl_page() {
	$s       = fleuriste_dl_settings();
	$regions = fleuriste_dl_regions();
	$money   = get_woocommerce_currency_symbol();
	?>
	<div class="wrap fl-dl">
		<h1><?php esc_html_e( 'Delivery & pickup', 'fleuriste' ); ?></h1>
		<p class="fl-dl-lead"><?php esc_html_e( 'At checkout, customers choose Delivery or Pickup, then the day and time. For delivery they pick their area, and its fee is added.', 'fleuriste' ); ?></p>
		<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Saved. Checkout uses the new settings now.', 'fleuriste' ); ?></p></div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="fl-dl-form">
			<input type="hidden" name="action" value="fleuriste_delivery">
			<?php wp_nonce_field( 'fleuriste_delivery' ); ?>

			<div class="fl-dl-card">
				<h2><?php esc_html_e( 'Delivery areas and fees', 'fleuriste' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Customers pick their area in the delivery address. Drag to change the order of the list.', 'fleuriste' ); ?></p>
				<table class="fl-dl-areas">
					<thead><tr><th></th><th><?php esc_html_e( 'Area', 'fleuriste' ); ?></th><th><?php /* translators: %s: currency symbol */ echo esc_html( sprintf( __( 'Fee (%s)', 'fleuriste' ), $money ) ); ?></th><th></th></tr></thead>
					<tbody id="fl-dl-rows">
						<?php foreach ( $regions as $r ) : ?>
							<tr>
								<td class="fl-dl-drag" title="<?php esc_attr_e( 'Drag to reorder', 'fleuriste' ); ?>">⋮⋮</td>
								<td><input type="hidden" name="r_id[]" value="<?php echo esc_attr( $r['id'] ); ?>"><input type="text" name="r_name[]" value="<?php echo esc_attr( $r['name'] ); ?>" class="regular-text"></td>
								<td><input type="number" name="r_price[]" value="<?php echo esc_attr( $r['price'] ); ?>" min="0" step="any" class="small-text"></td>
								<td><button type="button" class="button-link fl-dl-del" aria-label="<?php esc_attr_e( 'Remove area', 'fleuriste' ); ?>">✕</button></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p><button type="button" class="button" id="fl-dl-add">+ <?php esc_html_e( 'Add an area', 'fleuriste' ); ?></button></p>
				<p><label><?php /* translators: %s: currency symbol */ echo esc_html( sprintf( __( 'Free delivery for orders over (%s)', 'fleuriste' ), $money ) ); ?>
					<input type="number" name="free_over" value="<?php echo esc_attr( $s['free_over'] ); ?>" min="0" step="any" class="small-text" placeholder="–"></label>
					<span class="description"><?php esc_html_e( 'Leave empty for no free delivery.', 'fleuriste' ); ?></span></p>
			</div>

			<div class="fl-dl-card">
				<h2><label class="fl-dl-switch"><input type="checkbox" name="delivery_on" value="1" <?php checked( fleuriste_dl_delivery_on() ); ?>> <?php esc_html_e( 'Delivery', 'fleuriste' ); ?></label></h2>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><label for="fl-dl-dslots"><?php esc_html_e( 'Delivery times', 'fleuriste' ); ?></label></th><td>
						<textarea id="fl-dl-dslots" name="delivery_slots" rows="5" class="large-text code"><?php echo esc_textarea( (string) fleuriste_fo_get( 'fleuriste_fo_slots' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One per line, as "Name | extra price" (e.g. "Evening (4pm – 7pm) | 100"). Use 0 or nothing for no extra charge. Leave empty to not ask for a time.', 'fleuriste' ); ?></p>
					</td></tr>
					<tr><th scope="row"><label for="fl-dl-dcut"><?php esc_html_e( 'Same-day delivery until', 'fleuriste' ); ?></label></th><td>
						<input type="time" id="fl-dl-dcut" name="delivery_cutoff" value="<?php echo esc_attr( fleuriste_fo_cutoff( 'delivery' ) ); ?>">
						<p class="description"><?php esc_html_e( 'After this time the earliest delivery day is tomorrow. Leave empty to allow same-day delivery all day.', 'fleuriste' ); ?></p>
					</td></tr>
					<tr><th scope="row"><?php esc_html_e( 'No delivery on', 'fleuriste' ); ?></th><td><?php fleuriste_dl_day_boxes( 'delivery_closed', fleuriste_fo_closed_days( 'delivery' ) ); ?></td></tr>
				</table>
			</div>

			<div class="fl-dl-card">
				<h2><label class="fl-dl-switch"><input type="checkbox" name="pickup_on" value="1" <?php checked( fleuriste_dl_pickup_on() ); ?>> <?php esc_html_e( 'Pickup from the shop', 'fleuriste' ); ?></label></h2>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><label for="fl-dl-ptitle"><?php esc_html_e( 'Name at checkout', 'fleuriste' ); ?></label></th><td><input type="text" id="fl-dl-ptitle" name="pickup_title" value="<?php echo esc_attr( $s['pickup_title'] ); ?>" class="regular-text"></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Pickup address', 'fleuriste' ); ?></th><td>
						<?php $shop_addr = function_exists( 'fleuriste_company' ) ? trim( str_replace( "\n", ', ', fleuriste_company()['address'] ) ) : ''; ?>
						<label><input type="checkbox" name="pickup_same" value="1" id="fl-dl-psame" <?php checked( 'no' !== $s['pickup_same'] ); ?>> <?php esc_html_e( 'Same as the shop address', 'fleuriste' ); ?></label>
						<p class="description"><?php echo $shop_addr ? esc_html( $shop_addr ) . ' – ' : ''; ?><a href="<?php echo esc_url( admin_url( 'admin.php?page=fleuriste-company' ) ); ?>"><?php esc_html_e( 'edit in Shop details', 'fleuriste' ); ?></a></p>
						<div id="fl-dl-paddr-box" style="margin-top:8px">
							<input type="text" id="fl-dl-paddr" name="pickup_address" value="<?php echo esc_attr( $s['pickup_address'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Street', 'fleuriste' ); ?>"><br>
							<input type="text" name="pickup_town" value="<?php echo esc_attr( $s['pickup_town'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Town (optional)', 'fleuriste' ); ?>" style="margin-top:6px"><br>
							<select name="pickup_area" style="margin-top:6px"><option value=""><?php esc_html_e( 'Region', 'fleuriste' ); ?></option>
								<?php foreach ( $regions as $r ) : ?>
									<option value="<?php echo esc_attr( $r['id'] ); ?>" <?php selected( $s['pickup_area'], $r['id'] ); ?>><?php echo esc_html( $r['name'] ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<script>( function () { var c = document.getElementById( 'fl-dl-psame' ), b = document.getElementById( 'fl-dl-paddr-box' ); function s() { b.style.display = c.checked ? 'none' : ''; } c.addEventListener( 'change', s ); s(); } )();</script>
					</td></tr>
					<tr><th scope="row"><label for="fl-dl-pdet"><?php esc_html_e( 'Pickup instructions', 'fleuriste' ); ?></label></th><td><textarea id="fl-dl-pdet" name="pickup_details" rows="2" class="large-text"><?php echo esc_textarea( $s['pickup_details'] ); ?></textarea><p class="description"><?php esc_html_e( 'Shown at checkout under the shop address (opening hours, parking…).', 'fleuriste' ); ?></p></td></tr>
					<tr><th scope="row"><label for="fl-dl-pslots"><?php esc_html_e( 'Pickup times', 'fleuriste' ); ?></label></th><td>
						<textarea id="fl-dl-pslots" name="pickup_slots" rows="4" class="large-text code"><?php echo esc_textarea( $s['pickup_slots'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One per line, e.g. "Morning (9am – 12pm)". Pickup is free. Leave empty to not ask for a time.', 'fleuriste' ); ?></p>
					</td></tr>
					<tr><th scope="row"><label for="fl-dl-pcut"><?php esc_html_e( 'Same-day pickup until', 'fleuriste' ); ?></label></th><td>
						<input type="time" id="fl-dl-pcut" name="pickup_cutoff" value="<?php echo esc_attr( $s['pickup_cutoff'] ); ?>">
						<p class="description"><?php esc_html_e( 'After this time the earliest pickup day is tomorrow. Leave empty to allow same-day pickup all day.', 'fleuriste' ); ?></p>
					</td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Shop closed for pickup on', 'fleuriste' ); ?></th><td><?php fleuriste_dl_day_boxes( 'pickup_closed', $s['pickup_closed'] ); ?></td></tr>
				</table>
			</div>

			<div class="fl-dl-card">
				<h2><?php esc_html_e( 'Booking window and holidays', 'fleuriste' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><label for="fl-dl-max"><?php esc_html_e( 'Customers can book up to', 'fleuriste' ); ?></label></th><td><input type="number" id="fl-dl-max" name="max_days" value="<?php echo esc_attr( (int) fleuriste_fo_get( 'fleuriste_fo_max_days' ) ); ?>" min="1" class="small-text"> <?php esc_html_e( 'days ahead', 'fleuriste' ); ?></td></tr>
					<tr><th scope="row"><label for="fl-dl-hol"><?php esc_html_e( 'Holidays (no delivery, no pickup)', 'fleuriste' ); ?></label></th><td>
						<p><input type="date" id="fl-dl-holpick"> <button type="button" class="button" id="fl-dl-holadd"><?php esc_html_e( 'Add date', 'fleuriste' ); ?></button></p>
						<textarea id="fl-dl-hol" name="holidays" rows="4" class="code" style="width:14em"><?php echo esc_textarea( implode( "\n", fleuriste_dl_holidays() ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One date per line (year-month-day). Customers can\'t choose these days.', 'fleuriste' ); ?></p>
					</td></tr>
				</table>
			</div>

			<p class="submit"><button class="button button-primary button-hero"><?php esc_html_e( 'Save', 'fleuriste' ); ?></button></p>
		</form>
	</div>
	<style>
		.fl-dl{max-width:860px}.fl-dl-lead{font-size:14px;max-width:640px}
		.fl-dl-card{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:6px 20px 14px;margin:16px 0}
		.fl-dl-card h2{font-size:16px;margin:14px 0 6px}.fl-dl-card .form-table th{width:210px}
		.fl-dl-areas{border-collapse:collapse;margin-top:8px}.fl-dl-areas th{text-align:left;font-weight:600;padding:4px 8px}
		.fl-dl-areas td{padding:3px 8px}.fl-dl-drag{cursor:grab;color:#999;user-select:none}
		.fl-dl-del{color:#b32d2e;font-size:16px;text-decoration:none}.fl-dl-days label{display:inline-block;margin-right:14px}
		.fl-dl-switch{font-size:16px}.fl-dl-areas tr.ui-sortable-helper{background:#f6f7f7}
	</style>
	<script>
	jQuery( function ( $ ) {
		var $rows = $( '#fl-dl-rows' );
		if ( $.fn.sortable ) { $rows.sortable( { handle: '.fl-dl-drag', axis: 'y' } ); }
		$( '#fl-dl-add' ).on( 'click', function () {
			var $r = $rows.find( 'tr' ).first().clone();
			if ( ! $r.length ) { return; }
			$r.find( 'input' ).val( '' );
			$rows.append( $r ); $r.find( 'input[type=text]' ).trigger( 'focus' );
		} );
		$rows.on( 'click', '.fl-dl-del', function () {
			if ( $rows.find( 'tr' ).length > 1 ) { $( this ).closest( 'tr' ).remove(); } else { $( this ).closest( 'tr' ).find( 'input' ).val( '' ); }
		} );
		$( '#fl-dl-holadd' ).on( 'click', function () {
			var v = $( '#fl-dl-holpick' ).val(), $t = $( '#fl-dl-hol' );
			if ( v && $t.val().indexOf( v ) === -1 ) { $t.val( ( $.trim( $t.val() ) ? $.trim( $t.val() ) + '\n' : '' ) + v ); }
		} );
	} );
	</script>
	<?php
}

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( false !== strpos( (string) $hook, 'fleuriste-delivery' ) ) {
			wp_enqueue_script( 'jquery-ui-sortable' );
		}
	}
);
