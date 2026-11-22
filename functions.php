<?php
/**
 * Fleuriste functions and definitions.
 *
 * @package Fleuriste
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FLEURISTE_VERSION', '1.29.7' );

/**
 * Theme setup.
 */
function fleuriste_setup() {
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'editor-styles' );
	add_editor_style( 'assets/css/theme.css' );

	// WooCommerce.
	add_theme_support(
		'woocommerce',
		array(
			'thumbnail_image_width' => 600,
			'single_image_width'    => 1000,
			'product_grid'          => array(
				'default_columns' => 4,
				'min_columns'     => 2,
				'max_columns'     => 4,
			),
		)
	);
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );
}
add_action( 'after_setup_theme', 'fleuriste_setup' );

/**
 * Front-end styles and scripts.
 */
function fleuriste_enqueue() {
	wp_enqueue_style( 'fleuriste', get_theme_file_uri( 'assets/css/theme.css' ), array(), FLEURISTE_VERSION );
	if ( class_exists( 'WooCommerce' ) ) {
		wp_enqueue_style( 'fleuriste-woocommerce', get_theme_file_uri( 'assets/css/woocommerce.css' ), array( 'fleuriste' ), FLEURISTE_VERSION );
	}
	wp_enqueue_script( 'fleuriste', get_theme_file_uri( 'assets/js/theme.js' ), array(), FLEURISTE_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );
	if ( function_exists( 'is_product' ) && is_product() ) {
		wp_enqueue_script( 'fleuriste-product', get_theme_file_uri( 'assets/js/product.js' ), array( 'jquery' ), FLEURISTE_VERSION, true );
		wp_localize_script(
			'fleuriste-product',
			'fleuristeProduct',
			array(
				'symbol'    => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
				'format'    => html_entity_decode( get_woocommerce_price_format(), ENT_QUOTES, 'UTF-8' ),
				'decimals'  => wc_get_price_decimals(),
				'decimal'   => wc_get_price_decimal_separator(),
				'thousand'  => wc_get_price_thousand_separator(),
				'closedMsg' => __( 'We don\'t deliver on that day of the week. Please choose another day.', 'fleuriste' ),
				'chooseMsg' => __( 'Choose your options above', 'fleuriste' ),
			)
		);
	}
}
add_action( 'wp_enqueue_scripts', 'fleuriste_enqueue', 20 );

/**
 * Pattern category.
 */
function fleuriste_pattern_categories() {
	register_block_pattern_category( 'fleuriste', array( 'label' => __( 'Fleuriste', 'fleuriste' ) ) );
}
add_action( 'init', 'fleuriste_pattern_categories' );

/**
 * Run shortcodes inside Shortcode blocks that come from patterns in templates
 * (WordPress only expands shortcodes before patterns are inserted).
 *
 * @param string $content Block output.
 * @return string
 */
function fleuriste_render_shortcode_block( $content ) {
	return do_shortcode( $content );
}
add_filter( 'render_block_core/shortcode', 'fleuriste_render_shortcode_block' );

/**
 * Link to the shop page (falls back to /shop/ if WooCommerce is not active).
 *
 * @return string
 */
function fleuriste_shop_url() {
	if ( function_exists( 'wc_get_page_permalink' ) ) {
		return wc_get_page_permalink( 'shop' );
	}
	return home_url( '/shop/' );
}

/**
 * "All Products" links always open WooCommerce's real shop page, whatever its address
 * (some sites have it at /products/ and an unrelated empty page at /shop/).
 */
add_filter(
	'render_block',
	function ( $html, $block ) {
		$name = isset( $block['blockName'] ) ? $block['blockName'] : '';
		if ( ! in_array( $name, array( 'core/navigation-link', 'core/button' ), true ) || false === strpos( $html, 'href="/shop/"' ) ) {
			return $html;
		}
		return str_replace( 'href="/shop/"', 'href="' . esc_url( fleuriste_shop_url() ) . '"', $html );
	},
	10,
	2
);

// An empty page called "shop" that isn't the real shop page sends visitors to the shop.
add_action(
	'template_redirect',
	function () {
		if ( ! function_exists( 'wc_get_page_id' ) || ! is_page( 'shop' ) ) {
			return;
		}
		$page    = get_queried_object();
		$shop_id = (int) wc_get_page_id( 'shop' );
		if ( $page instanceof WP_Post && $shop_id > 0 && (int) $page->ID !== $shop_id && '' === trim( wp_strip_all_tags( (string) $page->post_content ) ) ) {
			wp_safe_redirect( fleuriste_shop_url(), 301 );
			exit;
		}
	}
);

/**
 * Link to a product category by slug.
 *
 * @param string $slug Category slug.
 * @return string
 */
function fleuriste_cat_url( $slug ) {
	if ( taxonomy_exists( 'product_cat' ) ) {
		$link = get_term_link( $slug, 'product_cat' );
		if ( ! is_wp_error( $link ) ) {
			return $link;
		}
	}
	return home_url( '/product-category/' . $slug . '/' );
}

/**
 * URL of a bundled theme image.
 *
 * @param string $file File name inside assets/images.
 * @return string
 */
function fleuriste_img( $file ) {
	return get_theme_file_uri( 'assets/images/' . $file );
}

// Announcement bar (Shop settings → Announcement bar).
require_once get_theme_file_path( 'inc/announcement.php' );

// Website sections: Shop settings → Website (home page sections, product page, footer).
require_once get_theme_file_path( 'inc/sections.php' );

// Hide the blog (Posts) from the admin unless switched on in Settings → Reading.
require_once get_theme_file_path( 'inc/admin-cleanup.php' );
require_once get_theme_file_path( 'inc/branding.php' );
require_once get_theme_file_path( 'inc/about-page.php' );
require_once get_theme_file_path( 'inc/contact.php' );
require_once get_theme_file_path( 'inc/info-pages.php' );

// Florist options (size, vase, extras, card message) and delivery date/time at checkout.
if ( class_exists( 'WooCommerce' ) ) {
	require_once get_theme_file_path( 'inc/florist-options.php' );
	require_once get_theme_file_path( 'inc/delivery.php' );
	require_once get_theme_file_path( 'inc/delivery-admin.php' );
	require_once get_theme_file_path( 'inc/checkout.php' );
	require_once get_theme_file_path( 'inc/delivery-checkout.php' );
	require_once get_theme_file_path( 'inc/menu.php' );
	require_once get_theme_file_path( 'inc/home-categories.php' );
	require_once get_theme_file_path( 'inc/occasions.php' );
	require_once get_theme_file_path( 'inc/category-banner.php' );
	require_once get_theme_file_path( 'inc/dashboard.php' );
	require_once get_theme_file_path( 'inc/board-alerts.php' );
	require_once get_theme_file_path( 'inc/drivers.php' );
	require_once get_theme_file_path( 'inc/whatsapp.php' );
	require_once get_theme_file_path( 'inc/notify.php' );
	require_once get_theme_file_path( 'inc/phone-order.php' );
	require_once get_theme_file_path( 'inc/suborders.php' );
	require_once get_theme_file_path( 'inc/stock.php' );
	require_once get_theme_file_path( 'inc/addons.php' );
	require_once get_theme_file_path( 'inc/reports.php' );
	require_once get_theme_file_path( 'inc/invoices.php' );
	require_once get_theme_file_path( 'inc/shop-details.php' );
	require_once get_theme_file_path( 'inc/admin-menu.php' );
	require_once get_theme_file_path( 'inc/manual.php' );
	require_once get_theme_file_path( 'inc/accounts.php' );
}

/* -------------------------------------------------------------------------
 * WooCommerce tweaks
 * ---------------------------------------------------------------------- */

if ( class_exists( 'WooCommerce' ) || defined( 'WC_PLUGIN_FILE' ) ) {

	// "All Products" (the shop page) and category pages always list the products themselves –
	// from every category – rather than category tiles, whatever WooCommerce's
	// "Shop page display" setting says (some demo imports switch it to categories).
	add_filter( 'option_woocommerce_shop_page_display', '__return_empty_string' );
	add_filter( 'option_woocommerce_category_archive_display', '__return_empty_string' );

	// The shop page lists every published product, newest first unless the customer sorts.
	add_action(
		'woocommerce_product_query',
		function ( $q ) {
			if ( is_shop() && ! is_search() ) {
				$q->set( 'tax_query', array_filter( (array) $q->get( 'tax_query' ), function ( $t ) { return ! is_array( $t ) || 'product_cat' !== ( $t['taxonomy'] ?? '' ); } ) ); // phpcs:ignore Generic.Formatting.DisallowMultipleStatements
			}
		}
	);

	// Product photo without extra photos: no thumbnail row, so the photo can be a little taller.
	add_filter(
		'woocommerce_single_product_image_gallery_classes',
		function ( $classes ) {
			global $product;
			if ( $product && ! $product->get_gallery_image_ids() ) {
				$classes[] = 'fl-single-image';
			}
			return $classes;
		}
	);

	// All Products, categories and occasions: 24 products per page (6 rows of 4), with page numbers;
	// related products in a row of 4.
	add_filter(
		'loop_shop_per_page',
		function () {
			return 24;
		},
		99
	);
	add_filter(
		'woocommerce_output_related_products_args',
		function ( $args ) {
			$args['posts_per_page'] = 4;
			$args['columns']        = 4;
			return $args;
		}
	);

	// Small reassurance list under the add-to-cart button.
	add_action(
		'woocommerce_after_add_to_cart_form',
		function () {
			$items = array_filter(
				array(
					'truck'  => (string) fleuriste_s( 'product', 'trust1' ),
					'leaf'   => (string) fleuriste_s( 'product', 'trust2' ),
					'shield' => (string) fleuriste_s( 'product', 'trust3' ),
				),
				function ( $t ) {
					return '' !== trim( $t );
				}
			);
			if ( ! $items ) {
				return;
			}
			echo '<ul class="fl-trust">';
			foreach ( $items as $icon => $label ) {
				printf(
					'<li><img src="%1$s" alt="" width="22" height="22" loading="lazy"><span>%2$s</span></li>',
					esc_url( get_theme_file_uri( 'assets/icons/' . $icon . '.svg' ) ),
					esc_html( $label )
				);
			}
			echo '</ul>';
		}
	);

	// Category label above the product title.
	add_action(
		'woocommerce_single_product_summary',
		function () {
			global $product;
			$terms = get_the_terms( $product->get_id(), 'product_cat' );
			if ( $terms && ! is_wp_error( $terms ) ) {
				$term = reset( $terms );
				printf( '<p class="fl-eyebrow fl-product-cat"><a href="%1$s">%2$s</a></p>', esc_url( get_term_link( $term ) ), esc_html( $term->name ) );
			}
		},
		4
	);

	// Wrap gallery + summary so the gallery can stay in view while the options scroll.
	add_action(
		'woocommerce_before_single_product_summary',
		function () {
			echo '<div class="fl-product-top">';
		},
		1
	);
	add_action(
		'woocommerce_after_single_product_summary',
		function () {
			echo '</div>';
		},
		1
	);

	// Reassurance cards under the related products.
	add_action(
		'woocommerce_after_single_product_summary',
		function () {
			if ( ! fleuriste_s( 'product', 'perks' ) ) {
				return;
			}
			$cards = array();
			foreach ( array( 1 => 'gift', 2 => 'truck', 3 => 'shield', 4 => 'flower' ) as $n => $icon ) {
				if ( fleuriste_s_has( 'product', 'p' . $n . '_title' ) ) {
					$cards[ $icon ] = array( fleuriste_s( 'product', 'p' . $n . '_title' ), fleuriste_s( 'product', 'p' . $n . '_text' ) );
				}
			}
			echo '<section class="fl-product-perks" aria-label="' . esc_attr__( 'Why order from us', 'fleuriste' ) . '">';
			foreach ( $cards as $icon => $card ) {
				printf(
					'<div class="fl-perk"><span class="fl-perk__icon"><img src="%1$s" alt="" width="26" height="26" loading="lazy"></span><h3>%2$s</h3><p>%3$s</p></div>',
					esc_url( get_theme_file_uri( 'assets/icons/' . $icon . '.svg' ) ),
					esc_html( $card[0] ),
					esc_html( $card[1] )
				);
			}
			echo '</section>';
		},
		30
	);

	// Sticky add-to-cart bar on product pages.
	add_action(
		'wp_footer',
		function () {
			if ( ! is_product() || ! fleuriste_s( 'product', 'sticky_cart' ) ) {
				return;
			}
			$product = wc_get_product( get_queried_object_id() );
			if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
				return;
			}
			printf(
				'<div class="fl-sticky-cart" aria-hidden="true"><div class="fl-sticky-cart__inner">%1$s<div class="fl-sticky-cart__info"><span class="fl-sticky-cart__name">%2$s</span><span class="fl-sticky-cart__price">%3$s</span></div><button type="button" class="fl-sticky-cart__btn wp-element-button" tabindex="-1">%4$s</button></div></div>',
				wp_kses_post( $product->get_image( 'woocommerce_gallery_thumbnail', array( 'class' => 'fl-sticky-cart__img' ) ) ),
				esc_html( $product->get_name() ),
				wp_kses_post( $product->get_price_html() ),
				esc_html( $product->single_add_to_cart_text() )
			);
		}
	);

	// Show the rupee sign (₨) as "Rs" followed by a space, e.g. "Rs 1,890".
	// The ₨ character is missing from most fonts and looks out of place.
	add_filter(
		'woocommerce_currency_symbol',
		function ( $symbol ) {
			if ( '&#x20a8;' === strtolower( $symbol ) || '₨' === $symbol || 'Rs' === $symbol ) {
				return 'Rs&nbsp;';
			}
			return $symbol;
		},
		20
	);

	// Sale badge wording.
	add_filter(
		'woocommerce_sale_flash',
		function () {
			return '<span class="onsale">' . esc_html__( 'Sale', 'fleuriste' ) . '</span>';
		}
	);
}
