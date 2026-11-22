<?php
/**
 * Checkout for a flower shop: the billing address (the sender) and the delivery
 * address (the recipient) are collected separately unless the customer ticks "same address", the delivery section
 * asks for the recipient, and the sender can choose to stay anonymous.
 *
 * Works with the Checkout block and with the classic [woocommerce_checkout] shortcode.
 *
 * @package Fleuriste
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Labels used at checkout.
 *
 * @return array
 */
function fleuriste_checkout_labels() {
	return array(
		'delivery_title'  => __( 'Delivery address – who should receive the flowers?', 'fleuriste' ),
		'billing_title'   => __( 'Billing address – your details', 'fleuriste' ),
		'same_address'    => __( 'My billing address is the same as the delivery address (the flowers are for me)', 'fleuriste' ),
		'anonymous'       => __( 'Send anonymously – the recipient won\'t see who the flowers are from', 'fleuriste' ),
		'recipient_first' => __( 'Recipient\'s first name', 'fleuriste' ),
		'recipient_last'  => __( 'Recipient\'s last name', 'fleuriste' ),
		'recipient_phone' => __( 'Recipient\'s phone (for the driver)', 'fleuriste' ),
	);
}

/* -------------------------------------------------------------------------
 * "Send anonymously" – saved on the order, shown in admin, emails and My Account
 * ---------------------------------------------------------------------- */

add_action(
	'woocommerce_init',
	function () {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}
		$labels = fleuriste_checkout_labels();
		woocommerce_register_additional_checkout_field(
			array(
				'id'       => 'fleuriste/send-anonymously',
				'label'    => $labels['anonymous'],
				'location' => 'order',
				'type'     => 'checkbox',
			)
		);
	}
);

/* -------------------------------------------------------------------------
 * Checkout block: rename the sections and always collect a separate billing address
 * ---------------------------------------------------------------------- */

add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
			return;
		}
		$labels = fleuriste_checkout_labels();
		$map    = array(
			'Shipping address' => $labels['delivery_title'],
			'Billing address'  => $labels['billing_title'],
			'Use same address for billing' => $labels['same_address'],
			'Ship'                         => __( 'Delivery', 'fleuriste' ),
			'Shipping options'             => __( 'Delivery', 'fleuriste' ),
			'Shipping'                     => __( 'Delivery', 'fleuriste' ),
			'Additional order information' => __( 'Day and time', 'fleuriste' ),
		);
		// Runs before the checkout scripts so their headings pick up the new wording.
		wp_add_inline_script(
			'wp-hooks',
			'(function(){var m=' . wp_json_encode( $map ) . ';window.wp&&wp.hooks&&wp.hooks.addFilter("i18n.gettext_woocommerce","fleuriste/checkout-labels",function(t,text){return Object.prototype.hasOwnProperty.call(m,text)?m[text]:t;});})();'
		);
		// WooCommerce keeps the "choose a region" error of an address form that is no longer shown
		// (after switching to Pickup or ticking "same address"), which silently blocks the order.
		wp_add_inline_script(
			'wp-data',
			'(function(){setInterval(function(){var d=window.wp&&wp.data;if(!d||!d.select("wc/store/validation"))return;var errs=d.select("wc/store/validation").getValidationErrors()||{};Object.keys(errs).forEach(function(k){var m=/^(shipping|billing)_(.+)$/.exec(k);if(m&&!document.getElementById(m[1]+"-"+m[2])){d.dispatch("wc/store/validation").clearValidationError(k);}});},400);})();',
			'after'
		);
		// "Use same address for billing": the customer can tick it when the flowers are for themselves.
		// It starts unticked (most orders are gifts); after that it is the customer's choice.
		wp_add_inline_script(
			'wp-data',
			'(function(){var k="fl_same_addr_set";try{if(sessionStorage.getItem(k))return;}catch(e){}var n=0,t=setInterval(function(){var cb=document.querySelector(".wc-block-checkout__use-address-for-billing input[type=checkbox]");if(cb){clearInterval(t);try{sessionStorage.setItem(k,"1");}catch(e){}if(cb.checked){cb.click();}}else if(++n>150){clearInterval(t);}},200);})();',
			'after'
		);
	},
	5
);

/* -------------------------------------------------------------------------
 * Classic checkout (shortcode)
 * ---------------------------------------------------------------------- */

// The delivery address form starts open (most orders are gifts); customers can untick it to use their billing address.
add_filter( 'woocommerce_ship_to_different_address_checked', '__return_true' );
add_filter( 'woocommerce_cart_needs_shipping_address', '__return_true' );

// Recipient wording and phone on the delivery (shipping) address.
add_filter(
	'woocommerce_shipping_fields',
	function ( $fields ) {
		$labels = fleuriste_checkout_labels();
		if ( isset( $fields['shipping_first_name'] ) ) {
			$fields['shipping_first_name']['label'] = $labels['recipient_first'];
		}
		if ( isset( $fields['shipping_last_name'] ) ) {
			$fields['shipping_last_name']['label'] = $labels['recipient_last'];
		}
		if ( ! isset( $fields['shipping_phone'] ) ) {
			$fields['shipping_phone'] = array(
				'type'         => 'tel',
				'class'        => array( 'form-row-wide' ),
				'validate'     => array( 'phone' ),
				'autocomplete' => 'tel',
				'priority'     => 100,
			);
		}
		$fields['shipping_phone']['label']    = $labels['recipient_phone'];
		$fields['shipping_phone']['required'] = false;
		return $fields;
	}
);

add_filter(
	'gettext_woocommerce',
	function ( $translation, $text ) {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return $translation;
		}
		$labels = fleuriste_checkout_labels();
		switch ( $text ) {
			case 'Ship to a different address?':
				return $labels['delivery_title'];
			case 'Billing details':
			case 'Billing &amp; Shipping':
				return $labels['billing_title'];
		}
		return $translation;
	},
	10,
	2
);

/* -------------------------------------------------------------------------
 * Mauritius only: the country is filled in and can't be changed
 * ---------------------------------------------------------------------- */

/**
 * Country the shop sells and delivers to (filterable for other shops).
 *
 * @return string ISO code.
 */
function fleuriste_shop_country() {
	return (string) apply_filters( 'fleuriste_shop_country', 'MU' );
}

/**
 * Limit a country list to the shop country.
 *
 * @param array $countries Code => name.
 * @return array
 */
function fleuriste_only_shop_country( $countries ) {
	$code = fleuriste_shop_country();
	$all  = WC()->countries ? WC()->countries->get_countries() : array();
	$name = isset( $countries[ $code ] ) ? $countries[ $code ] : ( isset( $all[ $code ] ) ? $all[ $code ] : $code );
	return array( $code => $name );
}
add_filter( 'woocommerce_countries_allowed_countries', 'fleuriste_only_shop_country', 99 );
add_filter( 'woocommerce_countries_shipping_countries', 'fleuriste_only_shop_country', 99 );

// WooCommerce settings that would otherwise ask customers for a country: the shop
// sells and delivers to Mauritius only, and the cart doesn't ask for an address.
add_filter( 'pre_option_woocommerce_allowed_countries', function () { return 'specific'; } ); // phpcs:ignore Generic.Formatting.DisallowMultipleStatements
add_filter( 'pre_option_woocommerce_specific_allowed_countries', function () { return array( fleuriste_shop_country() ); } ); // phpcs:ignore Generic.Formatting.DisallowMultipleStatements
add_filter( 'pre_option_woocommerce_ship_to_countries', function () { return ''; } ); // phpcs:ignore Generic.Formatting.DisallowMultipleStatements
add_filter( 'pre_option_woocommerce_default_customer_address', function () { return 'base'; } ); // phpcs:ignore Generic.Formatting.DisallowMultipleStatements
add_filter( 'pre_option_woocommerce_enable_shipping_calc', function () { return 'no'; } ); // phpcs:ignore Generic.Formatting.DisallowMultipleStatements
add_filter(
	'woocommerce_customer_get_billing_country',
	function ( $country ) {
		return $country ? $country : fleuriste_shop_country();
	}
);
add_filter(
	'woocommerce_customer_get_shipping_country',
	function ( $country ) {
		return $country ? $country : fleuriste_shop_country();
	}
);

// New and returning customers start with the shop country.
add_filter(
	'default_checkout_billing_country',
	function () {
		return fleuriste_shop_country();
	},
	99
);
add_filter(
	'default_checkout_shipping_country',
	function () {
		return fleuriste_shop_country();
	},
	99
);
add_filter(
	'woocommerce_customer_default_location_array',
	function ( $location ) {
		$location['country'] = fleuriste_shop_country();
		return $location;
	},
	99
);

// Never accept another country, whatever the browser sends.
add_action(
	'woocommerce_after_checkout_validation',
	function ( $data, $errors ) {
		$code = fleuriste_shop_country();
		foreach ( array( 'billing_country', 'shipping_country' ) as $key ) {
			if ( ! empty( $data[ $key ] ) && $code !== $data[ $key ] ) {
				$errors->add( 'fleuriste_country', __( 'Sorry, we only deliver within Mauritius.', 'fleuriste' ) );
				return;
			}
		}
	},
	10,
	2
);
add_action(
	'woocommerce_store_api_checkout_update_order_from_request',
	function ( $order ) {
		$code = fleuriste_shop_country();
		if ( $order->get_billing_country() && $code !== $order->get_billing_country() ) {
			$order->set_billing_country( $code );
		}
		if ( $order->get_shipping_country() && $code !== $order->get_shipping_country() ) {
			$order->set_shipping_country( $code );
		}
	}
);

/* -------------------------------------------------------------------------
 * Mauritian address format: "Region" (the delivery areas from Shop settings →
 * Delivery & pickup, which set the delivery fee). No town field: the region is enough.
 * ---------------------------------------------------------------------- */

add_filter(
	'woocommerce_get_country_locale',
	function ( $locale ) {
		$code = fleuriste_shop_country();
		if ( ! isset( $locale[ $code ] ) ) {
			$locale[ $code ] = array();
		}
		$has_areas        = function_exists( 'fleuriste_dl_regions' ) && fleuriste_dl_regions();
		$locale[ $code ]['city']  = array_merge(
			isset( $locale[ $code ]['city'] ) ? $locale[ $code ]['city'] : array(),
			$has_areas
				? array(
					'required' => false,
					'hidden'   => true,
				)
				: array(
					'label'    => __( 'Region', 'fleuriste' ),
					'required' => true,
				)
		);
		$locale[ $code ]['state'] = $has_areas
			? array(
				'label'    => __( 'Region', 'fleuriste' ),
				'required' => true,
				'hidden'   => false,
				'priority' => 65,
			)
			: array(
				'required' => false,
				'hidden'   => true,
			);
		$locale[ $code ]['postcode'] = array_merge(
			isset( $locale[ $code ]['postcode'] ) ? $locale[ $code ]['postcode'] : array(),
			array( 'required' => false )
		);
		return $locale;
	},
	99
);

// Classic checkout and My Account address forms.
add_filter(
	'woocommerce_default_address_fields',
	function ( $fields ) {
		$has_areas = function_exists( 'fleuriste_dl_regions' ) && fleuriste_dl_regions();
		if ( $has_areas ) {
			unset( $fields['city'] );
		} elseif ( isset( $fields['city'] ) ) {
			$fields['city']['label'] = __( 'Region', 'fleuriste' );
		}
		if ( $has_areas && isset( $fields['state'] ) ) {
			$fields['state']['label'] = __( 'Region', 'fleuriste' );
		} else {
			unset( $fields['state'] );
		}
		return $fields;
	},
	99
);
