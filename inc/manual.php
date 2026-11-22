<?php
/**
 * "Start here": a short manual – the first page after logging in. What each menu
 * is for and how to do the everyday things, with a button to each page.
 *
 * @package Fleuriste
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'admin_menu',
	function () {
		add_menu_page( __( 'Start here', 'fleuriste' ), __( 'Start here', 'fleuriste' ), 'edit_shop_orders', 'fleuriste-start', 'fleuriste_manual_page', 'dashicons-book-alt', '2.1' );
	},
	5
);

// Open "Start here" after logging in (for the shop owner and staff).
add_filter(
	'login_redirect',
	function ( $redirect, $requested, $user ) {
		$to_dashboard = '' === (string) $requested || in_array( $redirect, array( admin_url(), admin_url( 'index.php' ), admin_url( '/' ) ), true );
		if ( $user instanceof WP_User && $user->has_cap( 'edit_shop_orders' ) && ( $to_dashboard || ! $user->has_cap( 'manage_options' ) && false !== strpos( (string) $redirect, '/wp-admin/index.php' ) ) ) {
			return admin_url( 'admin.php?page=fleuriste-start' );
		}
		return $redirect;
	},
	20,
	3
);

/**
 * Link to an admin page.
 *
 * @param string $path  Admin path, e.g. admin.php?page=fleuriste-stock.
 * @param string $label Button text.
 * @param bool   $main  Primary button.
 * @return string
 */
function fleuriste_manual_btn( $path, $label, $main = false ) {
	return '<a class="button' . ( $main ? ' button-primary' : '' ) . '" href="' . esc_url( admin_url( $path ) ) . '">' . esc_html( $label ) . '</a>';
}

/**
 * The page.
 */
function fleuriste_manual_page() {
	$orders = function_exists( 'fleuriste_orders_list_slug' ) ? fleuriste_orders_list_slug() : 'edit.php?post_type=shop_order';

	$daily = array(
		array( 'dashicons-car', __( 'See and handle today\'s orders', 'fleuriste' ), __( 'Orders & Deliveries shows the orders by delivery day. Click the button on an order to move it on: Start preparing → Out for delivery (or Ready for pickup) → Delivered. Click "Turn on new-order alerts" to hear a chime for every new order while the board is open.', 'fleuriste' ), 'admin.php?page=fleuriste-deliveries', __( 'Open the board', 'fleuriste' ) ),
		array( 'dashicons-phone', __( 'Take an order by phone or in the shop', 'fleuriste' ), __( 'Fill in the buyer, the recipient, the flowers, the date and the payment. You can send the buyer a payment link.', 'fleuriste' ), 'admin.php?page=fleuriste-new-order', __( 'New order', 'fleuriste' ) ),
		array( 'dashicons-calendar-alt', __( 'Change a delivery', 'fleuriste' ), __( 'On the board, click "Change delivery" on the order: another region, pickup, another date or time, or a delivery method you type in.', 'fleuriste' ), 'admin.php?page=fleuriste-deliveries', __( 'Open the board', 'fleuriste' ) ),
		array( 'dashicons-plus-alt2', __( 'Add something to an order', 'fleuriste' ), __( 'On the board, click "Add to order": extra items with their own payment, delivered together with the order.', 'fleuriste' ), 'admin.php?page=fleuriste-deliveries', __( 'Open the board', 'fleuriste' ) ),
		array( 'dashicons-dismiss', __( 'A product is sold out', 'fleuriste' ), __( 'Switch it off in Stock. Switch it back on when it\'s available again. Orders already placed are not affected.', 'fleuriste' ), 'admin.php?page=fleuriste-stock', __( 'Stock', 'fleuriste' ) ),
		array( 'dashicons-chart-bar', __( 'Monthly figures', 'fleuriste' ), __( 'Orders received and fulfilled per month, with Excel and PDF downloads for the accountant.', 'fleuriste' ), 'admin.php?page=fleuriste-reports', __( 'Reports', 'fleuriste' ) ),
	);

	$howto = array(
		__( 'Add a product', 'fleuriste' )                     => array( __( 'Products → Add New. Give it a name, a price, a photo (Product image) and a category. Tick the occasions under Product tags (Birthday, Sympathy…). Click Publish.', 'fleuriste' ), 'post-new.php?post_type=product', __( 'Add a product', 'fleuriste' ) ),
		__( 'Change a price or a photo', 'fleuriste' )         => array( __( 'Products → click the product → change Regular price, Sale price or Product image → Update.', 'fleuriste' ), 'edit.php?post_type=product', __( 'Products', 'fleuriste' ) ),
		__( 'Add more photos to a product', 'fleuriste' )      => array( __( 'Edit the product → "Product gallery" box (right-hand side, under Product image) → Add product gallery images → choose several photos → Update. They show as small pictures under the main photo; customers click or swipe through them.', 'fleuriste' ), 'edit.php?post_type=product', __( 'Products', 'fleuriste' ) ),
		__( 'Put a product under an occasion', 'fleuriste' )   => array( __( 'Edit the product → Product tags → "Choose from the most used tags" → Birthday, Anniversary & Love, Sympathy or Get Well Soon. The home page occasion tiles list these products.', 'fleuriste' ), 'edit.php?post_type=product', __( 'Products', 'fleuriste' ) ),
		__( 'Add or rename a category', 'fleuriste' )          => array( __( 'Products → Categories. Categories appear in the menu, the footer and "Shop by category" on the home page.', 'fleuriste' ), 'edit-tags.php?taxonomy=product_cat&post_type=product', __( 'Categories', 'fleuriste' ) ),
		__( 'Change delivery fees, regions or times', 'fleuriste' ) => array( __( 'Shop settings → Delivery & pickup: regions with their fee, delivery and pickup times, same-day cut-off, days off and holidays.', 'fleuriste' ), 'admin.php?page=fleuriste-delivery', __( 'Delivery & pickup', 'fleuriste' ) ),
		__( 'Change the shop address, phone or hours', 'fleuriste' ) => array( __( 'Shop settings → Shop details. Entered once, used everywhere (footer, Contact page, invoices, pickup).', 'fleuriste' ), 'admin.php?page=fleuriste-company', __( 'Shop details', 'fleuriste' ) ),
		__( 'Change the home page', 'fleuriste' )              => array( __( 'Shop settings → Website: texts, photos, which sections show and in which order, the products shown, and the footer texts.', 'fleuriste' ), 'admin.php?page=fleuriste-website', __( 'Website', 'fleuriste' ) ),
		__( 'Show a message at the top of the site', 'fleuriste' ) => array( __( 'Shop settings → Announcement bar, e.g. "Order by 2pm for same-day delivery". You can schedule messages.', 'fleuriste' ), 'admin.php?page=fleuriste-announcement', __( 'Announcement bar', 'fleuriste' ) ),
		__( 'Sizes, vases and extras on products', 'fleuriste' ) => array( __( 'Shop settings → Product options: the choices and their prices, and which products offer them.', 'fleuriste' ), 'admin.php?page=wc-settings&tab=products&section=fleuriste', __( 'Product options', 'fleuriste' ) ),
		__( 'Print an invoice or a receipt', 'fleuriste' )     => array( __( 'On the board, click Invoice (or Receipt) on the order. Legal details printed on them: Shop settings → Shop details.', 'fleuriste' ), 'admin.php?page=fleuriste-deliveries', __( 'Open the board', 'fleuriste' ) ),
		__( 'Edit the About us or Contact page', 'fleuriste' ) => array( __( 'Pages → About us / Contact us. The contact details on the Contact page come from Shop details.', 'fleuriste' ), 'edit.php?post_type=page', __( 'Pages', 'fleuriste' ) ),
		__( 'Add a driver', 'fleuriste' )                      => array( __( 'Orders & Deliveries → Drivers. Each driver gets a private link (no login) to see their deliveries for today and tomorrow on the phone and mark them delivered.', 'fleuriste' ), 'admin.php?page=fleuriste-drivers', __( 'Drivers', 'fleuriste' ) ),
	);

	$menus = array(
		array( __( 'Orders & Deliveries', 'fleuriste' ), __( 'Everyday work: the board of orders, new orders, all orders, customers, stock, reports and drivers.', 'fleuriste' ) ),
		array( __( 'Products', 'fleuriste' ), __( 'The flowers you sell: add, edit, categories and occasion tags.', 'fleuriste' ) ),
		array( __( 'Shop settings', 'fleuriste' ), __( 'Set up once: shop details, website texts, product options, delivery & pickup, payments, WhatsApp alerts, announcement bar.', 'fleuriste' ) ),
		array( __( 'Pages', 'fleuriste' ), __( 'About us, Contact us and other pages of the site.', 'fleuriste' ) ),
		array( __( 'WooCommerce', 'fleuriste' ), __( 'Advanced settings only (currency, taxes, email sender). You don\'t need it for daily work.', 'fleuriste' ) ),
	);
	?>
	<div class="wrap fl-man">
		<h1><?php esc_html_e( 'Start here', 'fleuriste' ); ?></h1>
		<p class="fl-man__lead"><?php echo esc_html( sprintf( /* translators: %s: shop name */ __( 'A quick guide to running %s. Click a button to go straight to the page.', 'fleuriste' ), get_bloginfo( 'name' ) ) ); ?>
			<?php echo fleuriste_manual_btn( 'admin.php?page=fleuriste-deliveries', __( 'Today\'s orders', 'fleuriste' ), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<a class="button" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank"><?php esc_html_e( 'View the website', 'fleuriste' ); ?></a>
		</p>

		<h2><?php esc_html_e( 'Every day', 'fleuriste' ); ?></h2>
		<div class="fl-man__grid">
			<?php foreach ( $daily as $d ) : ?>
				<div class="fl-man__card">
					<h3><span class="dashicons <?php echo esc_attr( $d[0] ); ?>" aria-hidden="true"></span> <?php echo esc_html( $d[1] ); ?></h3>
					<p><?php echo esc_html( $d[2] ); ?></p>
					<?php echo fleuriste_manual_btn( $d[3], $d[4] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			<?php endforeach; ?>
		</div>

		<h2><?php esc_html_e( 'How do I…', 'fleuriste' ); ?></h2>
		<div class="fl-man__list">
			<?php foreach ( $howto as $q => $a ) : ?>
				<details class="fl-man__item">
					<summary><?php echo esc_html( $q ); ?></summary>
					<p><?php echo esc_html( $a[0] ); ?></p>
					<?php echo fleuriste_manual_btn( $a[1], $a[2] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</details>
			<?php endforeach; ?>
		</div>

		<h2><?php esc_html_e( 'What each menu is for', 'fleuriste' ); ?></h2>
		<table class="widefat striped fl-man__menus">
			<tbody>
				<?php foreach ( $menus as $m ) : ?>
					<tr><th scope="row"><?php echo esc_html( $m[0] ); ?></th><td><?php echo esc_html( $m[1] ); ?></td></tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<style>
		.fl-man{max-width:1100px}.fl-man__lead{font-size:14px;display:flex;flex-wrap:wrap;gap:8px;align-items:center}
		.fl-man h2{margin-top:28px}
		.fl-man__grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px}
		.fl-man__card{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:14px 16px 16px}
		.fl-man__card h3{margin:0 0 6px;font-size:15px;display:flex;gap:8px;align-items:center}.fl-man__card .dashicons{color:#9e4f63}
		.fl-man__card p{margin:0 0 12px;color:#50575e}
		.fl-man__list{display:grid;gap:8px}
		.fl-man__item{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:0 16px}
		.fl-man__item summary{cursor:pointer;padding:12px 0;font-weight:600;font-size:14px}
		.fl-man__item p{margin:0 0 10px;color:#50575e}.fl-man__item[open]{padding-bottom:14px}
		.fl-man__menus th{width:200px;font-weight:600}
	</style>
	<?php
}

/* -------------------------------------------------------------------------
 * Shop managers (and other staff who aren't administrators) don't see the
 * WordPress Dashboard, Marketing or Posts: they are hidden, and opening them
 * shows "Start here" instead.
 * ---------------------------------------------------------------------- */

/**
 * Should this user skip the WordPress Dashboard?
 *
 * @return bool
 */
function fleuriste_hide_dashboard_for_user() {
	return is_user_logged_in() && ! current_user_can( 'manage_options' ) && current_user_can( 'edit_shop_orders' );
}

add_action(
	'admin_menu',
	function () {
		if ( fleuriste_hide_dashboard_for_user() ) {
			remove_menu_page( 'index.php' );
			remove_menu_page( 'woocommerce-marketing' ); // Marketing.
			remove_menu_page( 'edit.php' );              // Posts.
		}
	},
	999
);

add_action(
	'load-index.php',
	function () {
		if ( fleuriste_hide_dashboard_for_user() ) {
			wp_safe_redirect( admin_url( 'admin.php?page=fleuriste-start' ) );
			exit;
		}
	}
);

// The "Dashboard" link in the black admin bar (site name menu) goes to Start here too.
add_action(
	'admin_bar_menu',
	function ( $bar ) {
		if ( fleuriste_hide_dashboard_for_user() && $bar->get_node( 'dashboard' ) ) {
			$bar->add_node(
				array(
					'id'    => 'dashboard',
					'title' => __( 'Start here', 'fleuriste' ),
					'href'  => admin_url( 'admin.php?page=fleuriste-start' ),
				)
			);
		}
	},
	100
);

// Posts and Marketing pages opened directly: back to Start here.
add_action(
	'current_screen',
	function ( $screen ) {
		if ( ! fleuriste_hide_dashboard_for_user() || ! $screen ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$path    = isset( $_GET['path'] ) ? sanitize_text_field( wp_unslash( $_GET['path'] ) ) : '';
		$posts   = 'post' === $screen->post_type && in_array( $screen->base, array( 'edit', 'post' ), true );
		$tags    = in_array( $screen->taxonomy, array( 'category', 'post_tag' ), true );
		$market  = 0 === strpos( $path, '/marketing' ) || false !== strpos( (string) $screen->id, 'marketing' );
		if ( $posts || $tags || $market ) {
			wp_safe_redirect( admin_url( 'admin.php?page=fleuriste-start' ) );
			exit;
		}
	}
);
