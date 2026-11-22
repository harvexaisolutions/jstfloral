<?php
/**
 * One place for the shop's details (Shop settings → Shop details) – everything
 * else reads from there:
 * - WooCommerce's store address and email sender name follow it (their fields in
 *   WooCommerce → Settings point here instead);
 * - footer, Contact page, invoices and the pickup location use it.
 *
 * @package Fleuriste
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * WooCommerce follows Shop details
 * ---------------------------------------------------------------------- */

$fleuriste_wc_from_details = array(
	'woocommerce_store_address'   => function ( $c ) {
		return $c['street'];
	},
	'woocommerce_store_address_2' => function () {
		return '';
	},
	'woocommerce_store_city'      => function ( $c ) {
		$r = ( $c['region'] && function_exists( 'fleuriste_dl_region' ) ) ? fleuriste_dl_region( $c['region'] ) : null;
		return '' !== trim( $c['town'] ) ? $c['town'] : ( $r ? $r['name'] : '' );
	},
	'woocommerce_default_country' => function () {
		return function_exists( 'fleuriste_shop_country' ) ? fleuriste_shop_country() : 'MU';
	},
	'woocommerce_email_from_name' => function () {
		return get_bloginfo( 'name' );
	},
);
foreach ( $fleuriste_wc_from_details as $fleuriste_opt => $fleuriste_get ) {
	add_filter(
		'pre_option_' . $fleuriste_opt,
		function ( $pre ) use ( $fleuriste_get ) {
			if ( ! function_exists( 'fleuriste_company' ) ) {
				return $pre;
			}
			$c = fleuriste_company();
			if ( '' === trim( (string) $c['street'] ) && ! in_array( current_filter(), array( 'pre_option_woocommerce_email_from_name', 'pre_option_woocommerce_default_country' ), true ) ) {
				return $pre; // Nothing entered yet: keep WooCommerce's value.
			}
			return $fleuriste_get( $c );
		}
	);
}
unset( $fleuriste_wc_from_details, $fleuriste_opt, $fleuriste_get );

// WooCommerce → Settings → General: the store address fields are replaced by a link to Shop details.
add_filter(
	'woocommerce_general_settings',
	function ( $settings ) {
		$hide = array( 'woocommerce_store_address', 'woocommerce_store_address_2', 'woocommerce_store_city', 'woocommerce_default_country', 'woocommerce_store_postcode' );
		$out  = array();
		foreach ( $settings as $s ) {
			if ( isset( $s['id'] ) && in_array( $s['id'], $hide, true ) ) {
				if ( 'woocommerce_store_address' === $s['id'] ) {
					$out[] = array(
						'title' => __( 'Store address', 'fleuriste' ),
						'type'  => 'info',
						'text'  => sprintf(
							/* translators: %s: link */
							__( 'Set in %s (used everywhere on the site).', 'fleuriste' ),
							'<a href="' . esc_url( admin_url( 'admin.php?page=fleuriste-company' ) ) . '">' . esc_html__( 'Shop settings → Shop details', 'fleuriste' ) . '</a>'
						),
						'id'    => 'fleuriste_store_address_info',
					);
				}
				continue;
			}
			$out[] = $s;
		}
		return $out;
	},
	99
);

// WooCommerce settings pages: say what lives where.
add_action(
	'admin_notices',
	function () {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['page'] ) || 'wc-settings' !== $_GET['page'] ) {
			return;
		}
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
		// phpcs:enable
		$link = function ( $page, $label ) {
			return '<a href="' . esc_url( admin_url( 'admin.php?page=' . $page ) ) . '">' . esc_html( $label ) . '</a>';
		};
		$msg = '';
		if ( 'shipping' === $tab ) {
			$msg = sprintf(
				/* translators: %s: link */
				__( 'Delivery regions and fees, delivery and pickup days and times, and the pickup address are set in %s. You don\'t need to change anything here.', 'fleuriste' ),
				$link( 'fleuriste-delivery', __( 'Shop settings → Delivery & pickup', 'fleuriste' ) )
			);
		} elseif ( 'general' === $tab ) {
			$msg = sprintf(
				/* translators: %s: link */
				__( 'The shop\'s name, address, phone, email and logo are set in %s. Use this page for currency and selling locations only.', 'fleuriste' ),
				$link( 'fleuriste-company', __( 'Shop settings → Shop details', 'fleuriste' ) )
			);
		} elseif ( 'products' === $tab ) {
			return; // Our "Product options" section lives here.
		} else {
			$msg = sprintf(
				/* translators: %s: link */
				__( 'Everyday shop settings are in %s. These WooCommerce settings are for advanced options.', 'fleuriste' ),
				$link( 'fleuriste-company', __( 'Shop settings', 'fleuriste' ) )
			);
		}
		echo '<div class="notice notice-info"><p>' . wp_kses_post( $msg ) . '</p></div>';
	}
);

/* -------------------------------------------------------------------------
 * Once: gather details that used to be entered in several places
 * ---------------------------------------------------------------------- */

add_action(
	'admin_init',
	function () {
		if ( get_option( 'fleuriste_details_v1' ) || ! current_user_can( 'manage_woocommerce' ) || ! function_exists( 'fleuriste_company' ) ) {
			return;
		}
		update_option( 'fleuriste_details_v1', time() );
		$saved = get_option( 'fleuriste_company', array() );
		$saved = is_array( $saved ) ? $saved : array();
		$site  = get_option( 'fleuriste_site', array() );
		$site  = is_array( $site ) ? $site : array();
		$v     = isset( $site['v'] ) && is_array( $site['v'] ) ? $site['v'] : array();

		// Old one-box address → street / town / region.
		$legacy = isset( $v['footer.address'] ) && '' !== trim( $v['footer.address'] ) ? $v['footer.address'] : ( isset( $saved['address'] ) ? $saved['address'] : '' );
		if ( empty( $saved['street'] ) && '' !== trim( (string) $legacy ) ) {
			$lines = array_values(
				array_filter(
					array_map( 'trim', preg_split( '/\r\n|\r|\n|,/', (string) $legacy ) ),
					function ( $l ) {
						return '' !== $l && 'mauritius' !== strtolower( $l );
					}
				)
			);
			$saved['street'] = isset( $lines[0] ) ? $lines[0] : '';
			$saved['town']   = isset( $lines[1] ) ? trim( preg_replace( '/\s*\d{5}$/', '', $lines[1] ) ) : '';
		}
		if ( empty( $saved['region'] ) && ! empty( $saved['town'] ) && function_exists( 'fleuriste_dl_regions' ) ) {
			foreach ( fleuriste_dl_regions() as $r ) {
				if ( false !== stripos( $r['name'], $saved['town'] ) ) {
					$saved['region'] = $r['id'];
					break;
				}
			}
		}
		// Footer contacts → Shop details.
		if ( ! empty( $v['footer.phone'] ) && empty( $saved['phone'] ) ) {
			$saved['phone'] = $v['footer.phone'];
		}
		if ( ! empty( $v['footer.whatsapp'] ) && empty( $saved['whatsapp'] ) ) {
			$saved['whatsapp'] = $v['footer.whatsapp'];
		}
		if ( isset( $v['footer.hours'] ) && ! isset( $saved['hours'] ) ) {
			$saved['hours'] = $v['footer.hours'];
		}
		if ( ! empty( $v['footer.email'] ) ) {
			if ( empty( $saved['email'] ) ) {
				$saved['email'] = $v['footer.email'];
			}
			$v['footer.show_email'] = '1';
		}
		// The shop name now lives in one place (the site title).
		if ( ! empty( $saved['trading_name'] ) && get_bloginfo( 'name' ) !== $saved['trading_name'] && '' === trim( (string) get_option( 'blogname' ) ) ) {
			update_option( 'blogname', $saved['trading_name'] );
		}
		unset( $saved['trading_name'], $v['footer.address'], $v['footer.phone'], $v['footer.whatsapp'], $v['footer.hours'], $v['footer.email'] );
		$saved['address'] = '';
		update_option( 'fleuriste_company', $saved );
		$site['v'] = $v;
		update_option( 'fleuriste_site', $site );

		// Pickup at the shop address unless a different one was entered.
		$dl = get_option( 'fleuriste_delivery', array() );
		if ( is_array( $dl ) ) {
			$same = empty( $dl['pickup_address'] ) || ( ! empty( $saved['street'] ) && 0 === strcasecmp( trim( $dl['pickup_address'] ), trim( $saved['street'] ) ) );
			$dl['pickup_same'] = $same ? 'yes' : 'no';
			update_option( 'fleuriste_delivery', $dl );
		}
		if ( function_exists( 'fleuriste_dl_sync' ) ) {
			fleuriste_dl_sync();
		}
	},
	5
);
