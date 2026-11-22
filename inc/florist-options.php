<?php
/**
 * Florist options for products: bouquet size, vase, extras and a gift card
 * message (the delivery date and time slot are chosen at checkout). Prices are added to the cart item and the
 * choices are saved on the order (shown in admin, emails and My Account).
 *
 * Settings: Shop settings → Product options.
 * Per product: "Florist options" checkbox in Product data → General,
 *              "Flower care" text in Product data → Advanced.
 *
 * @package Fleuriste
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * Settings
 * ---------------------------------------------------------------------- */

/**
 * Default setting values.
 *
 * @return array
 */
function fleuriste_fo_defaults() {
	return array(
		'fleuriste_fo_enabled'     => 'yes',
		'fleuriste_fo_sizes'       => "Classic | 0\nDeluxe | 300\nGrand | 600",
		'fleuriste_fo_vases'       => "No vase | 0\nGlass vase | 250\nCeramic vase | 450",
		'fleuriste_fo_extras'      => "Chocolates | 250\nTeddy bear | 350\nBalloon | 150\nScented candle | 400",
		'fleuriste_fo_slots'       => "Anytime (9am – 7pm) | 0\nMorning (9am – 12pm) | 0\nAfternoon (12pm – 4pm) | 0\nEvening (4pm – 7pm) | 100",
		'fleuriste_fo_cutoff'      => '14:00',
		'fleuriste_fo_closed_days' => array(),
		'fleuriste_fo_max_days'    => '60',
		'fleuriste_fo_message_max' => '200',
		'fleuriste_fo_size_guide'  => "Classic – our signature size, perfect for a thoughtful gift.\nDeluxe – around a third more stems for a fuller look.\nGrand – a generous, show-stopping arrangement.",
		'fleuriste_fo_care'        => "Trim 2cm from each stem at an angle and place in a clean vase with fresh water and the flower food provided. Keep away from direct sunlight, heaters and ripening fruit.\n\nChange the water every two days and re-trim the stems. Flowers open fully within 2–3 days of delivery.",
	);
}

/**
 * Categories whose products are customisable until the setting is saved:
 * Bouquets and Roses, if those categories exist.
 *
 * @return int[]
 */
function fleuriste_fo_default_custom_cats() {
	$ids = array();
	foreach ( array( 'bouquets', 'roses' ) as $slug ) {
		$term = get_term_by( 'slug', $slug, 'product_cat' );
		if ( $term ) {
			$ids[] = (int) $term->term_id;
		}
	}
	return $ids;
}

/**
 * Read one setting.
 *
 * @param string $key Option name.
 * @return mixed
 */
function fleuriste_fo_get( $key ) {
	$defaults = fleuriste_fo_defaults();
	$value    = get_option( $key, null );
	if ( 'fleuriste_fo_custom_cats' === $key ) {
		return null === $value ? fleuriste_fo_default_custom_cats() : array_map( 'absint', (array) $value );
	}
	if ( null === $value ) { // Not saved yet. An empty saved value means "none" (e.g. no cut-off, group hidden).
		$value = isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
	}
	return $value;
}

/**
 * Parse "Label | price" lines into a list of choices.
 *
 * @param string $key Option name.
 * @return array[] Each item: label, price.
 */
function fleuriste_fo_choices( $key ) {
	$lines   = preg_split( '/\r\n|\r|\n/', (string) fleuriste_fo_get( $key ) );
	$choices = array();
	foreach ( $lines as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		$parts = array_map( 'trim', explode( '|', $line, 2 ) );
		$price = isset( $parts[1] ) ? (float) wc_format_decimal( $parts[1] ) : 0.0;
		$choices[] = array(
			'label' => $parts[0],
			'price' => max( 0, $price ),
		);
	}
	return $choices;
}

/**
 * Settings section under WooCommerce → Settings → Products.
 */
add_filter(
	'woocommerce_get_sections_products',
	function ( $sections ) {
		$sections['fleuriste'] = __( 'Florist options', 'fleuriste' );
		return $sections;
	}
);

add_filter(
	'woocommerce_get_settings_products',
	function ( $settings, $section ) {
		if ( 'fleuriste' !== $section ) {
			return $settings;
		}
		$d    = fleuriste_fo_defaults();
		$help = __( 'One choice per line, written as "Name | extra price". Use 0 for no extra charge. Leave the box empty to hide this group.', 'fleuriste' );
		return array(
			array(
				'title' => __( 'Florist options', 'fleuriste' ),
				'type'  => 'title',
				'desc'  => __( 'The gift card message is shown on every product; the delivery date and time are chosen at checkout. Size, vase and extras are only shown on customisable products: those in the categories chosen below, or switched on in a product\'s Product data → General.', 'fleuriste' ),
				'id'    => 'fleuriste_fo',
			),
			array(
				'title'   => __( 'Show florist options', 'fleuriste' ),
				'id'      => 'fleuriste_fo_enabled',
				'type'    => 'checkbox',
				'default' => $d['fleuriste_fo_enabled'],
				'desc'    => __( 'Show delivery, card message and (on customisable products) size, vase and extras on product pages', 'fleuriste' ),
			),
			array(
				'title'    => __( 'Customisable categories', 'fleuriste' ),
				'id'       => 'fleuriste_fo_custom_cats',
				'type'     => 'multiselect',
				'class'    => 'wc-enhanced-select',
				'options'  => fleuriste_fo_category_options(),
				'default'  => fleuriste_fo_default_custom_cats(),
				'desc_tip' => __( 'Products in these categories show bouquet size, vase and extras. A single product can override this in Product data → General.', 'fleuriste' ),
				'css'      => 'width:420px;',
			),
			array(
				'title'    => __( 'Bouquet sizes', 'fleuriste' ),
				'id'       => 'fleuriste_fo_sizes',
				'type'     => 'textarea',
				'default'  => $d['fleuriste_fo_sizes'],
				'desc_tip' => $help,
				'css'      => 'min-height:90px;width:420px;',
			),
			array(
				'title'    => __( 'Size guide text', 'fleuriste' ),
				'id'       => 'fleuriste_fo_size_guide',
				'type'     => 'textarea',
				'default'  => $d['fleuriste_fo_size_guide'],
				'desc_tip' => __( 'Shown under "Size guide" next to the sizes. One line per size.', 'fleuriste' ),
				'css'      => 'min-height:90px;width:420px;',
			),
			array(
				'title'    => __( 'Vases', 'fleuriste' ),
				'id'       => 'fleuriste_fo_vases',
				'type'     => 'textarea',
				'default'  => $d['fleuriste_fo_vases'],
				'desc_tip' => $help,
				'css'      => 'min-height:90px;width:420px;',
			),
			array(
				'title'    => __( 'Extras', 'fleuriste' ),
				'id'       => 'fleuriste_fo_extras',
				'type'     => 'textarea',
				'default'  => $d['fleuriste_fo_extras'],
				'desc_tip' => $help . ' ' . __( 'Customers can tick several extras.', 'fleuriste' ),
				'css'      => 'min-height:110px;width:420px;',
			),
			array(
				'title'             => __( 'Card message length', 'fleuriste' ),
				'id'                => 'fleuriste_fo_message_max',
				'type'              => 'number',
				'default'           => $d['fleuriste_fo_message_max'],
				'desc'              => __( 'Maximum characters. Set 0 to hide the card message.', 'fleuriste' ),
				'custom_attributes' => array( 'min' => 0, 'step' => 1 ),
				'css'               => 'width:90px;',
			),
			array(
				'title'    => __( 'Default flower care', 'fleuriste' ),
				'id'       => 'fleuriste_fo_care',
				'type'     => 'textarea',
				'default'  => $d['fleuriste_fo_care'],
				'desc_tip' => __( 'Shown in the "Flower care" tab. A product can have its own text in Product data → Advanced.', 'fleuriste' ),
				'css'      => 'min-height:110px;width:420px;',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'fleuriste_fo',
			),
		);
	},
	10,
	2
);

/**
 * Product categories for the settings dropdown.
 *
 * @return array
 */
function fleuriste_fo_category_options() {
	$options = array();
	$terms   = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
		)
	);
	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			$options[ $term->term_id ] = $term->name;
		}
	}
	return $options;
}

/* -------------------------------------------------------------------------
 * Per-product fields
 * ---------------------------------------------------------------------- */

add_action(
	'woocommerce_product_options_general_product_data',
	function () {
		echo '<div class="options_group show_if_simple show_if_variable">';
		woocommerce_wp_select(
			array(
				'id'          => '_fleuriste_custom',
				'label'       => __( 'Customisable', 'fleuriste' ),
				'options'     => array(
					''    => __( 'Use category setting', 'fleuriste' ),
					'yes' => __( 'Yes – show size, vase and extras', 'fleuriste' ),
					'no'  => __( 'No – sold as shown', 'fleuriste' ),
				),
				'desc_tip'    => true,
				'description' => __( 'Customisable products let shoppers pick a bouquet size, a vase and extras. The card message is always shown.', 'fleuriste' ),
			)
		);
		woocommerce_wp_checkbox(
			array(
				'id'          => '_fleuriste_fo_disabled',
				'label'       => __( 'Hide all florist options', 'fleuriste' ),
				'description' => __( 'No card message or customisation for this product (e.g. a gift card).', 'fleuriste' ),
			)
		);
		echo '</div>';
	}
);

add_action(
	'woocommerce_product_options_advanced',
	function () {
		echo '<div class="options_group">';
		woocommerce_wp_textarea_input(
			array(
				'id'          => '_fleuriste_care',
				'label'       => __( 'Flower care', 'fleuriste' ),
				'description' => __( 'Shown in the "Flower care" tab. Leave empty to use the default text from Shop settings → Product options.', 'fleuriste' ),
				'desc_tip'    => true,
				'rows'        => 5,
			)
		);
		echo '</div>';
	}
);

add_action(
	'woocommerce_admin_process_product_object',
	function ( $product ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce checks the nonce before this hook.
		$product->update_meta_data( '_fleuriste_fo_disabled', isset( $_POST['_fleuriste_fo_disabled'] ) ? 'yes' : 'no' );
		if ( isset( $_POST['_fleuriste_custom'] ) ) {
			$custom = sanitize_key( wp_unslash( $_POST['_fleuriste_custom'] ) );
			$product->update_meta_data( '_fleuriste_custom', in_array( $custom, array( 'yes', 'no' ), true ) ? $custom : '' );
		}
		if ( isset( $_POST['_fleuriste_care'] ) ) {
			$product->update_meta_data( '_fleuriste_care', sanitize_textarea_field( wp_unslash( $_POST['_fleuriste_care'] ) ) );
		}
		// phpcs:enable
	}
);

/**
 * Are florist options shown for this product?
 *
 * @param WC_Product|int $product Product.
 * @return bool
 */
function fleuriste_fo_active( $product ) {
	$product = wc_get_product( $product );
	if ( ! $product || 'yes' !== fleuriste_fo_get( 'fleuriste_fo_enabled' ) ) {
		return false;
	}
	$parent_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
	if ( 'yes' === get_post_meta( $parent_id, '_fleuriste_fo_disabled', true ) ) {
		return false;
	}
	if ( $product->is_type( array( 'grouped', 'external' ) ) || $product->is_virtual() ) {
		return false;
	}
	return (bool) apply_filters( 'fleuriste_florist_options_active', true, $product );
}

/**
 * Does this product offer size, vase and extras?
 *
 * @param WC_Product|int $product Product (a variation counts as its parent).
 * @return bool
 */
function fleuriste_fo_customisable( $product ) {
	$product = wc_get_product( $product );
	if ( ! $product || ! fleuriste_fo_active( $product ) ) {
		return false;
	}
	$parent_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
	$choice    = get_post_meta( $parent_id, '_fleuriste_custom', true );
	if ( 'yes' === $choice || 'no' === $choice ) {
		$result = 'yes' === $choice;
	} else {
		$cats   = fleuriste_fo_get( 'fleuriste_fo_custom_cats' );
		$terms  = wc_get_product_term_ids( $parent_id, 'product_cat' );
		$result = (bool) array_intersect( $cats, $terms );
	}
	return (bool) apply_filters( 'fleuriste_product_customisable', $result, $product );
}

/* -------------------------------------------------------------------------
 * Delivery dates
 * ---------------------------------------------------------------------- */

/**
 * Same-day cut-off (HH:MM or '') for delivery or pickup.
 *
 * @param string $mode delivery|pickup.
 * @return string
 */
function fleuriste_fo_cutoff( $mode = 'delivery' ) {
	if ( 'pickup' === $mode && function_exists( 'fleuriste_dl_get' ) ) {
		return trim( (string) fleuriste_dl_get( 'pickup_cutoff' ) );
	}
	return trim( (string) fleuriste_fo_get( 'fleuriste_fo_cutoff' ) );
}

/**
 * Week days (1 = Monday … 7 = Sunday) without delivery or pickup.
 *
 * @param string $mode delivery|pickup.
 * @return string[]
 */
function fleuriste_fo_closed_days( $mode = 'delivery' ) {
	if ( 'pickup' === $mode && function_exists( 'fleuriste_dl_get' ) ) {
		return array_map( 'strval', (array) fleuriste_dl_get( 'pickup_closed' ) );
	}
	return array_map( 'strval', (array) fleuriste_fo_get( 'fleuriste_fo_closed_days' ) );
}

/**
 * Is the shop closed that day (week day off or holiday)?
 *
 * @param DateTimeImmutable $date Day.
 * @param string            $mode delivery|pickup.
 * @return bool
 */
function fleuriste_fo_is_closed( $date, $mode = 'delivery' ) {
	if ( in_array( $date->format( 'N' ), fleuriste_fo_closed_days( $mode ), true ) ) {
		return true;
	}
	return function_exists( 'fleuriste_dl_holidays' ) && in_array( $date->format( 'Y-m-d' ), fleuriste_dl_holidays(), true );
}

/**
 * Earliest delivery (or pickup) date (Y-m-d) in the shop's timezone.
 *
 * @param string $mode delivery|pickup.
 * @return string
 */
function fleuriste_fo_earliest_date( $mode = 'delivery' ) {
	$tz     = wp_timezone();
	$now    = new DateTimeImmutable( 'now', $tz );
	$date   = $now->setTime( 0, 0 );
	$cutoff = fleuriste_fo_cutoff( $mode );
	if ( preg_match( '/^(\d{1,2}):(\d{2})$/', $cutoff, $m ) ) {
		$cut = $now->setTime( (int) $m[1], (int) $m[2] );
		if ( $now >= $cut ) {
			$date = $date->modify( '+1 day' );
		}
	}
	for ( $i = 0; $i < 60 && fleuriste_fo_is_closed( $date, $mode ); $i++ ) {
		$date = $date->modify( '+1 day' );
	}
	return $date->format( 'Y-m-d' );
}

/**
 * Latest bookable date (Y-m-d).
 *
 * @return string
 */
function fleuriste_fo_latest_date() {
	$days = max( 1, (int) fleuriste_fo_get( 'fleuriste_fo_max_days' ) );
	$date = new DateTimeImmutable( 'today', wp_timezone() );
	return $date->modify( '+' . $days . ' days' )->format( 'Y-m-d' );
}

/**
 * Check a delivery (or pickup) date. Returns an error message or an empty string.
 *
 * @param string $value Y-m-d.
 * @param string $mode  delivery|pickup.
 * @return string
 */
function fleuriste_fo_date_error( $value, $mode = 'delivery' ) {
	$pickup = 'pickup' === $mode;
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
		return $pickup ? __( 'Please choose a pickup date.', 'fleuriste' ) : __( 'Please choose a delivery date.', 'fleuriste' );
	}
	$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value, wp_timezone() );
	if ( ! $date || $date->format( 'Y-m-d' ) !== $value ) {
		return $pickup ? __( 'Please choose a valid pickup date.', 'fleuriste' ) : __( 'Please choose a valid delivery date.', 'fleuriste' );
	}
	$earliest = fleuriste_fo_earliest_date( $mode );
	if ( $value < $earliest ) {
		$day = date_i18n( get_option( 'date_format' ), strtotime( $earliest ) );
		return $pickup
			/* translators: %s: date */
			? sprintf( __( 'The earliest pickup date is %s. Please choose another day.', 'fleuriste' ), $day )
			/* translators: %s: date */
			: sprintf( __( 'The earliest delivery date is %s. Please choose another day.', 'fleuriste' ), $day );
	}
	if ( $value > fleuriste_fo_latest_date() ) {
		return __( 'That date is too far ahead. Please choose an earlier day.', 'fleuriste' );
	}
	if ( fleuriste_fo_is_closed( $date, $mode ) ) {
		return $pickup
			? __( 'The shop is closed for pickup that day. Please choose another day.', 'fleuriste' )
			: __( 'We don\'t deliver that day. Please choose another day.', 'fleuriste' );
	}
	return '';
}

/* -------------------------------------------------------------------------
 * Product page form
 * ---------------------------------------------------------------------- */

/**
 * Plain-text price, e.g. "Rs 250".
 *
 * @param float $price Price.
 * @return string
 */
function fleuriste_fo_money( $price ) {
	return html_entity_decode( wp_strip_all_tags( wc_price( $price ) ), ENT_QUOTES, 'UTF-8' );
}

/**
 * Price label for a choice, e.g. "+Rs 250" or "Included".
 *
 * @param float  $price Price.
 * @param string $zero  Label when free.
 * @return string HTML.
 */
function fleuriste_fo_price_label( $price, $zero = '' ) {
	if ( $price <= 0 ) {
		return $zero ? '<em class="fl-opt__price is-free">' . esc_html( $zero ) . '</em>' : '';
	}
	return '<em class="fl-opt__price">+' . esc_html( fleuriste_fo_money( $price ) ) . '</em>';
}

/**
 * Print one group of radio or checkbox cards.
 *
 * @param string $name     Field name.
 * @param string $type     radio|checkbox.
 * @param array  $choices  Choices.
 * @param string $legend   Group title.
 * @param string $zero     Label for free choices.
 * @param string $extra    Extra HTML after the legend.
 */
function fleuriste_fo_group( $name, $type, $choices, $legend, $zero = '', $extra = '' ) {
	if ( empty( $choices ) ) {
		return;
	}
	printf( '<fieldset class="fl-opt-group fl-opt-group--%1$s"><legend class="fl-opt-group__title">%2$s%3$s</legend><div class="fl-opt-grid">', esc_attr( $name ), wp_kses_post( $legend ), $extra ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $extra is built by this file.
	foreach ( $choices as $i => $choice ) {
		$field = 'checkbox' === $type ? $name . '[]' : $name;
		printf(
			'<label class="fl-opt"><input type="%1$s" name="fleuriste_%2$s" value="%3$d" data-price="%4$s"%5$s><span class="fl-opt__card"><span class="fl-opt__name">%6$s</span>%7$s</span></label>',
			esc_attr( $type ),
			esc_attr( $field ),
			(int) $i,
			esc_attr( $choice['price'] ),
			( 'radio' === $type && 0 === $i ) ? ' checked' : '',
			esc_html( $choice['label'] ),
			fleuriste_fo_price_label( $choice['price'], $zero ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}
	echo '</div></fieldset>';
}

add_action(
	'woocommerce_before_add_to_cart_button',
	function () {
		global $product;
		if ( ! $product || ! fleuriste_fo_active( $product ) ) {
			return;
		}

		$sizes  = fleuriste_fo_choices( 'fleuriste_fo_sizes' );
		$vases  = fleuriste_fo_choices( 'fleuriste_fo_vases' );
		$extras = fleuriste_fo_choices( 'fleuriste_fo_extras' );
		$max    = (int) fleuriste_fo_get( 'fleuriste_fo_message_max' );
		$guide  = trim( (string) fleuriste_fo_get( 'fleuriste_fo_size_guide' ) );
		$base    = $product->is_type( 'variable' ) ? '' : wc_get_price_to_display( $product );
		$regular = ( '' !== $base && $product->is_on_sale() ) ? wc_get_price_to_display( $product, array( 'price' => $product->get_regular_price() ) ) : '';

		echo '<div class="fl-options" data-base="' . esc_attr( $base ) . '" data-regular="' . esc_attr( $regular ) . '">';

		$guide_html = '';
		if ( $guide ) {
			$guide_html = '<details class="fl-guide"><summary>' . esc_html__( 'Size guide', 'fleuriste' ) . '</summary><div class="fl-guide__body">' . wpautop( esc_html( $guide ) ) . '</div></details>';
		}
		if ( fleuriste_fo_customisable( $product ) ) {
			fleuriste_fo_group( 'size', 'radio', $sizes, esc_html__( 'Bouquet size', 'fleuriste' ), __( 'Included', 'fleuriste' ), $guide_html );
			fleuriste_fo_group( 'vase', 'radio', $vases, esc_html__( 'Add a vase', 'fleuriste' ), __( 'Included', 'fleuriste' ) );
			fleuriste_fo_group( 'extras', 'checkbox', $extras, esc_html__( 'Make it extra special', 'fleuriste' ) . ' <small>' . esc_html__( '(optional)', 'fleuriste' ) . '</small>' );
		}

		// Delivery date and time are chosen at checkout (inc/delivery-checkout.php).
		echo '<input type="hidden" name="fleuriste_opts" value="1">';

		// Card message.
		if ( $max > 0 ) {
			echo '<fieldset class="fl-opt-group fl-opt-group--message"><legend class="fl-opt-group__title">' . esc_html__( 'Gift card message', 'fleuriste' ) . ' <small>' . esc_html__( '(free)', 'fleuriste' ) . '</small></legend>';
			printf(
				'<textarea name="fleuriste_message" maxlength="%1$d" rows="3" placeholder="%2$s"></textarea><div class="fl-message__meta"><span class="fl-message__hint">%3$s</span><span class="fl-count" aria-live="polite">0 / %1$d</span></div>',
				(int) $max,
				esc_attr__( 'Write a few words for the card…', 'fleuriste' ),
				esc_html__( 'Delivery date, time and "send anonymously" are chosen at checkout.', 'fleuriste' )
			);
			echo '</fieldset>';
		}

		do_action( 'fleuriste_before_total', $product );

		echo '<div class="fl-total" aria-live="polite"><span>' . esc_html__( 'Total', 'fleuriste' ) . '</span><strong class="fl-total__value">' . ( '' !== $base ? wp_kses_post( wc_price( $base ) ) : '–' ) . '</strong></div>';
		echo '</div>';
	},
	5
);

/* -------------------------------------------------------------------------
 * Read, validate and price the choices
 * ---------------------------------------------------------------------- */

/**
 * Build the selection from the posted form. Returns null when no options were posted.
 *
 * @return array|null
 */
function fleuriste_fo_from_request( $product_id = 0 ) {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- Add to cart forms have no nonce in WooCommerce.
	if ( ! isset( $_POST['fleuriste_opts'] ) && ! isset( $_POST['fleuriste_size'] ) ) {
		return null;
	}
	$pick = function ( $key, $index ) {
		$list = fleuriste_fo_choices( $key );
		return isset( $list[ $index ] ) ? $list[ $index ] : null;
	};
	$data = array(
		'size'      => isset( $_POST['fleuriste_size'] ) ? $pick( 'fleuriste_fo_sizes', absint( $_POST['fleuriste_size'] ) ) : null,
		'vase'      => isset( $_POST['fleuriste_vase'] ) ? $pick( 'fleuriste_fo_vases', absint( $_POST['fleuriste_vase'] ) ) : null,
		'extras'    => array(),
		'date'      => '', // Chosen at checkout.
		'slot'      => null, // Chosen at checkout (its price is a fee there).
		'message'   => '',
		'anonymous' => ! empty( $_POST['fleuriste_anonymous'] ),
	);
	if ( ! empty( $_POST['fleuriste_extras'] ) && is_array( $_POST['fleuriste_extras'] ) ) {
		foreach ( array_map( 'absint', wp_unslash( $_POST['fleuriste_extras'] ) ) as $i ) {
			$extra = $pick( 'fleuriste_fo_extras', $i );
			if ( $extra ) {
				$data['extras'][ $i ] = $extra;
			}
		}
		$data['extras'] = array_values( $data['extras'] );
	}
	if ( $product_id && ! fleuriste_fo_customisable( $product_id ) ) {
		$data['size']   = null;
		$data['vase']   = null;
		$data['extras'] = array();
	}
	$max = (int) fleuriste_fo_get( 'fleuriste_fo_message_max' );
	if ( $max > 0 && isset( $_POST['fleuriste_message'] ) ) {
		$message         = sanitize_textarea_field( wp_unslash( $_POST['fleuriste_message'] ) );
		$data['message'] = function_exists( 'mb_substr' ) ? mb_substr( $message, 0, $max ) : substr( $message, 0, $max );
	}
	// phpcs:enable
	return $data;
}

/**
 * Total extra price of a selection.
 *
 * @param array $data Selection.
 * @return float
 */
function fleuriste_fo_extra_price( $data ) {
	$total = 0.0;
	foreach ( array( 'size', 'vase' ) as $key ) {
		if ( ! empty( $data[ $key ]['price'] ) ) {
			$total += (float) $data[ $key ]['price'];
		}
	}
	foreach ( (array) $data['extras'] as $extra ) {
		$total += (float) $extra['price'];
	}
	return $total;
}

add_filter(
	'woocommerce_add_to_cart_validation',
	function ( $passed, $product_id, $quantity, $variation_id = 0 ) {
		if ( ! fleuriste_fo_active( $variation_id ? $variation_id : $product_id ) ) {
			return $passed;
		}
		$data = fleuriste_fo_from_request( $product_id );
		if ( null === $data && fleuriste_fo_customisable( $variation_id ? $variation_id : $product_id ) ) {
			// Added from a shop grid: size, vase and extras are chosen on the product page.
			wc_add_notice(
				sprintf(
					/* translators: %s: link to the product */
					__( 'Please choose the options for %s on its product page.', 'fleuriste' ),
					'<a href="' . esc_url( get_permalink( $product_id ) ) . '">' . esc_html( get_the_title( $product_id ) ) . '</a>'
				),
				'error'
			);
			return false;
		}
		return $passed;
	},
	10,
	4
);

/**
 * Shop grids: customisable products link to the product page (to choose size, vase and extras)
 * instead of adding straight to the cart. Others are added directly; the delivery date is chosen at checkout.
 */
add_filter(
	'woocommerce_loop_add_to_cart_link',
	function ( $html, $product ) {
		if ( $product->is_type( 'simple' ) && fleuriste_fo_customisable( $product ) && $product->is_purchasable() && $product->is_in_stock() ) {
			$html = sprintf(
				'<a href="%1$s" class="button fl-choose" aria-label="%2$s">%3$s</a>',
				esc_url( $product->get_permalink() ),
				/* translators: %s: product name */
				esc_attr( sprintf( __( 'Choose options for %s', 'fleuriste' ), $product->get_name() ) ),
				esc_html__( 'Choose options', 'fleuriste' )
			);
		}
		return $html;
	},
	10,
	2
);
add_filter(
	'woocommerce_product_add_to_cart_text',
	function ( $text, $product ) {
		if ( $product && $product->is_type( 'simple' ) && fleuriste_fo_customisable( $product ) && $product->is_purchasable() && $product->is_in_stock() ) {
			return __( 'Choose options', 'fleuriste' );
		}
		return $text;
	},
	10,
	2
);
add_filter(
	'woocommerce_product_add_to_cart_url',
	function ( $url, $product ) {
		if ( $product && $product->is_type( 'simple' ) && fleuriste_fo_customisable( $product ) && $product->is_purchasable() && $product->is_in_stock() ) {
			return $product->get_permalink();
		}
		return $url;
	},
	10,
	2
);
add_filter(
	'woocommerce_product_supports',
	function ( $supports, $feature, $product ) {
		if ( 'ajax_add_to_cart' === $feature && $product && fleuriste_fo_customisable( $product ) ) {
			return false;
		}
		return $supports;
	},
	10,
	3
);

add_filter(
	'woocommerce_add_cart_item_data',
	function ( $cart_item_data, $product_id, $variation_id ) {
		if ( ! fleuriste_fo_active( $variation_id ? $variation_id : $product_id ) ) {
			return $cart_item_data;
		}
		$data = fleuriste_fo_from_request( $product_id );
		if ( $data ) {
			$data['extra_price']           = fleuriste_fo_extra_price( $data );
			$cart_item_data['fleuriste']   = $data;
			$cart_item_data['fleuriste_k'] = md5( wp_json_encode( $data ) ); // Different choices = separate cart lines.
		}
		return $cart_item_data;
	},
	10,
	3
);

/**
 * Apply the extra price to a cart item's product object.
 *
 * @param array $item Cart item.
 * @return array
 */
function fleuriste_fo_apply_price( $item ) {
	if ( ! empty( $item['fleuriste']['extra_price'] ) && isset( $item['data'] ) && is_a( $item['data'], 'WC_Product' ) ) {
		$item['data']->set_price( (float) $item['data']->get_price( 'edit' ) + (float) $item['fleuriste']['extra_price'] );
	}
	return $item;
}
add_filter( 'woocommerce_add_cart_item', 'fleuriste_fo_apply_price', 20 );
add_filter(
	'woocommerce_get_cart_item_from_session',
	function ( $item, $values ) {
		if ( isset( $values['fleuriste'] ) ) {
			$item['fleuriste']   = $values['fleuriste'];
			$item['fleuriste_k'] = isset( $values['fleuriste_k'] ) ? $values['fleuriste_k'] : '';
			$item                = fleuriste_fo_apply_price( $item );
		}
		return $item;
	},
	20,
	2
);

/**
 * Human-readable lines for a selection.
 *
 * @param array $data Selection.
 * @return array[] key => value pairs.
 */
function fleuriste_fo_lines( $data ) {
	$lines = array();
	$fmt   = function ( $choice ) {
		return $choice['label'] . ( $choice['price'] > 0 ? ' (+' . fleuriste_fo_money( $choice['price'] ) . ')' : '' );
	};
	if ( ! empty( $data['size'] ) ) {
		$lines[] = array( __( 'Bouquet size', 'fleuriste' ), $fmt( $data['size'] ) );
	}
	if ( ! empty( $data['vase'] ) ) {
		$lines[] = array( __( 'Vase', 'fleuriste' ), $fmt( $data['vase'] ) );
	}
	if ( ! empty( $data['extras'] ) ) {
		$lines[] = array( __( 'Extras', 'fleuriste' ), implode( ', ', array_map( $fmt, $data['extras'] ) ) );
	}
	if ( ! empty( $data['date'] ) ) {
		$lines[] = array( __( 'Delivery date', 'fleuriste' ), date_i18n( get_option( 'date_format' ), strtotime( $data['date'] ) ) );
	}
	if ( ! empty( $data['slot'] ) ) {
		$lines[] = array( __( 'Delivery time', 'fleuriste' ), $fmt( $data['slot'] ) );
	}
	if ( '' !== (string) $data['message'] ) {
		$lines[] = array( __( 'Card message', 'fleuriste' ), $data['message'] );
	}
	if ( ! empty( $data['anonymous'] ) ) {
		$lines[] = array( __( 'Send anonymously', 'fleuriste' ), __( 'Yes', 'fleuriste' ) );
	}
	return $lines;
}

add_filter(
	'woocommerce_get_item_data',
	function ( $item_data, $cart_item ) {
		if ( ! empty( $cart_item['fleuriste'] ) ) {
			foreach ( fleuriste_fo_lines( $cart_item['fleuriste'] ) as $line ) {
				$item_data[] = array(
					'key'   => $line[0],
					'value' => $line[1],
				);
			}
		}
		return $item_data;
	},
	10,
	2
);

add_action(
	'woocommerce_checkout_create_order_line_item',
	function ( $item, $cart_item_key, $values ) {
		if ( empty( $values['fleuriste'] ) ) {
			return;
		}
		foreach ( fleuriste_fo_lines( $values['fleuriste'] ) as $line ) {
			$item->add_meta_data( $line[0], $line[1], true );
		}
		if ( ! empty( $values['fleuriste']['date'] ) ) {
			$item->add_meta_data( '_fleuriste_delivery_date', $values['fleuriste']['date'], true );
		}
	},
	10,
	3
);

/* -------------------------------------------------------------------------
 * Flower care tab
 * ---------------------------------------------------------------------- */

add_filter(
	'woocommerce_product_tabs',
	function ( $tabs ) {
		global $product;
		if ( ! $product ) {
			return $tabs;
		}
		$care = trim( (string) $product->get_meta( '_fleuriste_care' ) );
		if ( '' === $care ) {
			$care = trim( (string) fleuriste_fo_get( 'fleuriste_fo_care' ) );
		}
		if ( '' === $care ) {
			return $tabs;
		}
		$tabs['fleuriste_care'] = array(
			'title'    => __( 'Flower care', 'fleuriste' ),
			'priority' => 15,
			'callback' => function () use ( $care ) {
				echo '<h2>' . esc_html__( 'Flower care', 'fleuriste' ) . '</h2>' . wp_kses_post( wpautop( esc_html( $care ) ) );
			},
		);
		return $tabs;
	},
	20
);
