<?php
/**
 * Admin menu, organised by job:
 *
 *  Orders & Deliveries – daily work: board, new order, all orders, customers,
 *                        stock, drivers, reports.
 *  Products            – WooCommerce's products menu (unchanged).
 *  Shop settings       – set up once: company & invoices, product options,
 *                        delivery fees, payments, WhatsApp, announcement bar,
 *                        invoice printing, all WooCommerce settings.
 *
 * Page addresses don't change; only where they appear in the menu.
 *
 * @package Fleuriste
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Address of the WooCommerce orders list (new or classic order storage).
 *
 * @return string
 */
function fleuriste_orders_list_slug() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
		return 'admin.php?page=wc-orders';
	}
	return 'edit.php?post_type=shop_order';
}

/**
 * Links in "Shop settings" that open WooCommerce / plugin pages: slug => [label, capability, match].
 *
 * @return array
 */
function fleuriste_settings_links() {
	$links = array(
		'admin.php?page=wc-settings&tab=products&section=fleuriste' => array( __( 'Product options', 'fleuriste' ), 'manage_woocommerce', array( 'page' => 'wc-settings', 'tab' => 'products', 'section' => 'fleuriste' ) ),
		'admin.php?page=wc-settings&tab=checkout'                   => array( __( 'Payment methods', 'fleuriste' ), 'manage_woocommerce', array( 'page' => 'wc-settings', 'tab' => 'checkout' ) ),
	);
	return $links;
}

add_action(
	'admin_menu',
	function () {
		global $menu;

		// 1. "Deliveries" becomes "Orders & Deliveries".
		foreach ( (array) $menu as $i => $item ) {
			if ( isset( $item[2] ) && 'fleuriste-deliveries' === $item[2] ) {
				$menu[ $i ][0] = str_replace( __( 'Deliveries', 'fleuriste' ), __( 'Orders & Deliveries', 'fleuriste' ), $item[0] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			}
		}
		// Links to WooCommerce's own lists, next to the board.
		add_submenu_page( 'fleuriste-deliveries', __( 'All orders', 'fleuriste' ), __( 'All orders', 'fleuriste' ), 'edit_shop_orders', fleuriste_orders_list_slug(), '', 2 );
		add_submenu_page( 'fleuriste-deliveries', __( 'Customers', 'fleuriste' ), __( 'Customers', 'fleuriste' ), 'view_woocommerce_reports', 'admin.php?page=wc-admin&path=/customers', '', 3 );

		// 2. "Shop settings": move the set-up pages here.
		remove_submenu_page( 'fleuriste-deliveries', 'fleuriste-company' );
		remove_submenu_page( 'fleuriste-deliveries', 'fleuriste-whatsapp' );
		remove_submenu_page( 'themes.php', 'fleuriste-announcement' );

		add_menu_page( __( 'Shop settings', 'fleuriste' ), __( 'Shop settings', 'fleuriste' ), 'manage_woocommerce', 'fleuriste-company', 'fleuriste_company_page', 'dashicons-store', '55.6' );
		add_submenu_page( 'fleuriste-company', __( 'Shop details', 'fleuriste' ), __( 'Shop details', 'fleuriste' ), 'manage_woocommerce', 'fleuriste-company', 'fleuriste_company_page' );
		foreach ( fleuriste_settings_links() as $slug => $link ) {
			add_submenu_page( 'fleuriste-company', $link[0], $link[0], $link[1], $slug );
		}
		if ( function_exists( 'fleuriste_wa_page' ) ) {
			add_submenu_page( 'fleuriste-company', __( 'WhatsApp alerts', 'fleuriste' ), __( 'WhatsApp alerts', 'fleuriste' ), 'manage_woocommerce', 'fleuriste-whatsapp', 'fleuriste_wa_page' );
		}
		if ( function_exists( 'fleuriste_ann_page' ) ) {
			add_submenu_page( 'fleuriste-company', __( 'Announcement bar', 'fleuriste' ), __( 'Announcement bar', 'fleuriste' ), 'edit_theme_options', 'fleuriste-announcement', 'fleuriste_ann_page' );
		}
		if ( function_exists( 'fleuriste_wcdn_active' ) && fleuriste_wcdn_active() ) {
			add_submenu_page( 'fleuriste-company', __( 'Invoice printing', 'fleuriste' ), __( 'Invoice printing', 'fleuriste' ), 'manage_woocommerce', 'admin.php?page=wcdn_page' );
		}
		add_submenu_page( 'fleuriste-company', __( 'All WooCommerce settings', 'fleuriste' ), __( 'All WooCommerce settings', 'fleuriste' ), 'manage_woocommerce', 'admin.php?page=wc-settings' );
	},
	99
);

// WooCommerce's own "Payments" menu duplicates Shop settings → Payment methods.
add_action(
	'admin_menu',
	function () {
		remove_menu_page( 'admin.php?page=wc-settings&tab=checkout&from=PAYMENTS_MENU_ITEM' );
	},
	9999
);

// Main menu order: Start here, Dashboard, Orders & Deliveries, Products, Shop settings, then WooCommerce and the rest.
add_filter( 'custom_menu_order', '__return_true', 99 );
add_filter(
	'menu_order',
	function () {
		return array(
			'fleuriste-start',
			'index.php',
			'separator1',
			'fleuriste-deliveries',
			'edit.php?post_type=product',
			'fleuriste-company',
			'separator-woocommerce',
			'woocommerce',
			'wc-admin&path=/analytics/overview',
			'woocommerce-marketing',
		);
	},
	99
);

/**
 * Keep the right menu open when a linked WooCommerce page is shown.
 *
 * @return array|null [ parent, submenu ] or null.
 */
function fleuriste_menu_current() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$get = array_map(
		function ( $v ) {
			return is_string( $v ) ? sanitize_text_field( wp_unslash( $v ) ) : '';
		},
		$_GET
	);
	// phpcs:enable
	foreach ( fleuriste_settings_links() as $slug => $link ) {
		$match = true;
		foreach ( $link[2] as $k => $v ) {
			if ( ! isset( $get[ $k ] ) || $get[ $k ] !== $v ) {
				$match = false;
			}
		}
		// The shipping and payments tabs also match their sub-pages; the products tab only the florist section.
		if ( $match ) {
			return array( 'fleuriste-company', $slug );
		}
	}
	if ( isset( $get['page'] ) && 'wcdn_page' === $get['page'] ) {
		return array( 'fleuriste-company', 'admin.php?page=wcdn_page' );
	}
	if ( isset( $get['page'] ) && 'wc-admin' === $get['page'] && isset( $get['path'] ) && '/customers' === $get['path'] ) {
		return array( 'fleuriste-deliveries', 'admin.php?page=wc-admin&path=/customers' );
	}
	return null;
}

add_filter(
	'parent_file',
	function ( $parent ) {
		$cur = fleuriste_menu_current();
		return $cur ? $cur[0] : $parent;
	},
	99
);
add_filter(
	'submenu_file',
	function ( $submenu_file ) {
		$cur = fleuriste_menu_current();
		return $cur ? $cur[1] : $submenu_file;
	},
	99
);

// WordPress recalculates the open menu for plugin pages (WooCommerce settings and
// its React pages) after the filters above, so move the highlight in the browser too.
add_action(
	'admin_footer',
	function () {
		$cur = fleuriste_menu_current();
		if ( ! $cur ) {
			return;
		}
		?>
		<script>
		( function () {
			var to = document.getElementById( <?php echo wp_json_encode( 'toplevel_page_' . $cur[0] ); ?> );
			if ( ! to ) { return; }
			document.querySelectorAll( '#adminmenu li.wp-has-current-submenu, #adminmenu li.current' ).forEach( function ( li ) {
				if ( li === to || ! li.classList.contains( 'menu-top' ) ) { return; }
				li.classList.remove( 'wp-has-current-submenu', 'wp-menu-open', 'current' );
				li.classList.add( 'wp-not-current-submenu' );
				var a = li.querySelector( 'a.menu-top' );
				if ( a ) { a.classList.remove( 'wp-has-current-submenu', 'current' ); a.classList.add( 'wp-not-current-submenu' ); }
				li.querySelectorAll( '.wp-submenu li.current, .wp-submenu a.current' ).forEach( function ( el ) { el.classList.remove( 'current' ); } );
			} );
			to.classList.remove( 'wp-not-current-submenu' );
			to.classList.add( 'wp-has-current-submenu', 'wp-menu-open' );
			var top = to.querySelector( 'a.menu-top' );
			if ( top ) { top.classList.remove( 'wp-not-current-submenu' ); top.classList.add( 'wp-has-current-submenu' ); }
			var slug = <?php echo wp_json_encode( $cur[1] ); ?>;
			to.querySelectorAll( '.wp-submenu a' ).forEach( function ( a ) {
				var href = a.getAttribute( 'href' ) || '';
				if ( href === slug || href.slice( -slug.length ) === slug ) { a.classList.add( 'current' ); a.parentNode.classList.add( 'current' ); }
			} );
		} )();
		</script>
		<?php
	}
);

// A short line at the top of "Product options" so it's clear where it lives.
add_action(
	'woocommerce_settings_tabs_products',
	function () {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['section'] ) && 'fleuriste' === $_GET['section'] ) {
			echo '<p class="description" style="margin:12px 0 0">' . esc_html__( 'Bouquet sizes, vases, extras, delivery times, add-ons and "Frequently bought together". Also in Shop settings → Product options.', 'fleuriste' ) . '</p>';
		}
	},
	5
);
