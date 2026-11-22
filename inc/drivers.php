<?php
/**
 * Drivers: managed in Orders & Deliveries → Drivers, assigned to orders on the Deliveries
 * board or the order screen. Each driver gets a private link (no login needed)
 * showing their deliveries for today and tomorrow, with a "Delivered" button.
 *
 * @package Fleuriste
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FLEURISTE_DRIVERS_OPTION = 'fleuriste_drivers';
const FLEURISTE_DRIVER_META    = '_fleuriste_driver';

/**
 * All drivers: id => [name, phone, active, token].
 *
 * @param bool $active_only Only active drivers.
 * @return array
 */
function fleuriste_drivers( $active_only = false ) {
	$drivers = get_option( FLEURISTE_DRIVERS_OPTION, array() );
	$drivers = is_array( $drivers ) ? $drivers : array();
	if ( $active_only ) {
		$drivers = array_filter(
			$drivers,
			function ( $d ) {
				return ! empty( $d['active'] );
			}
		);
	}
	uasort(
		$drivers,
		function ( $a, $b ) {
			return strcasecmp( $a['name'], $b['name'] );
		}
	);
	return $drivers;
}

/**
 * One driver or null.
 *
 * @param string $id Driver ID.
 * @return array|null
 */
function fleuriste_driver( $id ) {
	$all = fleuriste_drivers();
	return ( $id && isset( $all[ $id ] ) ) ? $all[ $id ] : null;
}

/**
 * Private link for a driver.
 *
 * @param array $driver Driver.
 * @return string
 */
function fleuriste_driver_link( $driver ) {
	return add_query_arg( 'fleuriste-driver', $driver['token'], home_url( '/' ) );
}

/**
 * Assign a driver to an order (empty ID removes the driver).
 *
 * @param WC_Order $order Order.
 * @param string   $id    Driver ID.
 */
function fleuriste_assign_driver( $order, $id ) {
	$old = (string) $order->get_meta( FLEURISTE_DRIVER_META );
	if ( $old === (string) $id ) {
		return;
	}
	$driver = fleuriste_driver( $id );
	if ( $driver ) {
		$order->update_meta_data( FLEURISTE_DRIVER_META, $id );
		/* translators: %s: driver name */
		$order->add_order_note( sprintf( __( 'Driver assigned: %s', 'fleuriste' ), $driver['name'] ) );
	} else {
		$order->delete_meta_data( FLEURISTE_DRIVER_META );
		$order->add_order_note( __( 'Driver removed.', 'fleuriste' ) );
	}
	$order->save();
}

/**
 * Driver dropdown.
 *
 * @param string $name     Field name.
 * @param string $selected Selected ID.
 * @param string $class    CSS class.
 * @param string $none     Label for "no driver".
 * @return string
 */
function fleuriste_driver_select( $name, $selected, $class = '', $none = '' ) {
	$html  = '<select name="' . esc_attr( $name ) . '" class="' . esc_attr( $class ) . '">';
	$html .= '<option value="">' . esc_html( $none ? $none : __( '— No driver —', 'fleuriste' ) ) . '</option>';
	foreach ( fleuriste_drivers() as $id => $d ) {
		if ( empty( $d['active'] ) && (string) $id !== (string) $selected ) {
			continue;
		}
		$html .= '<option value="' . esc_attr( $id ) . '"' . selected( (string) $id, (string) $selected, false ) . '>' . esc_html( $d['name'] ) . '</option>';
	}
	return $html . '</select>';
}

/* -------------------------------------------------------------------------
 * Orders & Deliveries → Drivers admin page
 * ---------------------------------------------------------------------- */

add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'fleuriste-deliveries', __( 'Deliveries', 'fleuriste' ), __( 'Board', 'fleuriste' ), 'edit_shop_orders', 'fleuriste-deliveries', 'fleuriste_board_page' );
		add_submenu_page( 'fleuriste-deliveries', __( 'Drivers', 'fleuriste' ), __( 'Drivers', 'fleuriste' ), 'edit_shop_orders', 'fleuriste-drivers', 'fleuriste_drivers_page' );
	},
	20
);

add_action(
	'admin_post_fleuriste_drivers',
	function () {
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'fleuriste' ) );
		}
		check_admin_referer( 'fleuriste_drivers' );
		$drivers = fleuriste_drivers();
		$do      = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		$id      = isset( $_POST['id'] ) ? sanitize_key( wp_unslash( $_POST['id'] ) ) : '';
		$msg     = '';
		if ( 'add' === $do ) {
			$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
			if ( $name ) {
				$new_id             = 'd' . strtolower( wp_generate_password( 8, false ) );
				$drivers[ $new_id ] = array(
					'name'   => $name,
					'phone'  => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
					'active' => 1,
					'token'  => wp_generate_password( 24, false ),
				);
				$msg                = 'added';
			}
		} elseif ( $id && isset( $drivers[ $id ] ) ) {
			if ( 'save' === $do ) {
				$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
				if ( $name ) {
					$drivers[ $id ]['name'] = $name;
				}
				$drivers[ $id ]['phone'] = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
				$msg                     = 'saved';
			} elseif ( 'toggle' === $do ) {
				$drivers[ $id ]['active'] = empty( $drivers[ $id ]['active'] ) ? 1 : 0;
				$msg                      = 'saved';
			} elseif ( 'newlink' === $do ) {
				$drivers[ $id ]['token'] = wp_generate_password( 24, false );
				$msg                     = 'newlink';
			}
		}
		update_option( FLEURISTE_DRIVERS_OPTION, $drivers, false );
		wp_safe_redirect( add_query_arg( 'msg', $msg, admin_url( 'admin.php?page=fleuriste-drivers' ) ) );
		exit;
	}
);

/**
 * Drivers page.
 */
function fleuriste_drivers_page() {
	$drivers = fleuriste_drivers();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$msg     = isset( $_GET['msg'] ) ? sanitize_key( wp_unslash( $_GET['msg'] ) ) : '';
	$notices = array(
		'added'   => __( 'Driver added. Send them their link below.', 'fleuriste' ),
		'saved'   => __( 'Saved.', 'fleuriste' ),
		'newlink' => __( 'New link created. The old link no longer works – send the driver the new one.', 'fleuriste' ),
	);
	$form = function ( $do, $id = '' ) {
		echo '<input type="hidden" name="action" value="fleuriste_drivers"><input type="hidden" name="do" value="' . esc_attr( $do ) . '"><input type="hidden" name="id" value="' . esc_attr( $id ) . '">';
		wp_nonce_field( 'fleuriste_drivers' );
	};
	?>
	<div class="wrap fl-drivers">
		<h1><?php esc_html_e( 'Drivers', 'fleuriste' ); ?></h1>
		<?php if ( isset( $notices[ $msg ] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notices[ $msg ] ); ?></p></div>
		<?php endif; ?>
		<p><?php esc_html_e( 'Add the people who deliver your orders, then pick a driver on each order in Deliveries. Every driver has a private link that opens their deliveries on their phone – no password needed. Keep the link private; create a new one if it is shared by mistake.', 'fleuriste' ); ?></p>

		<h2><?php esc_html_e( 'Add a driver', 'fleuriste' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fl-drivers__add">
			<?php $form( 'add' ); ?>
			<input type="text" name="name" required placeholder="<?php esc_attr_e( 'Name', 'fleuriste' ); ?>">
			<input type="tel" name="phone" placeholder="<?php esc_attr_e( 'Phone (optional)', 'fleuriste' ); ?>">
			<button class="button button-primary"><?php esc_html_e( 'Add driver', 'fleuriste' ); ?></button>
		</form>

		<h2><?php esc_html_e( 'Your drivers', 'fleuriste' ); ?></h2>
		<?php if ( ! $drivers ) : ?>
			<p><em><?php esc_html_e( 'No drivers yet.', 'fleuriste' ); ?></em></p>
		<?php else : ?>
			<table class="widefat striped fl-drivers__table">
				<thead><tr>
					<th><?php esc_html_e( 'Name and phone', 'fleuriste' ); ?></th>
					<th><?php esc_html_e( 'Today', 'fleuriste' ); ?></th>
					<th><?php esc_html_e( 'Private link', 'fleuriste' ); ?></th>
					<th><?php esc_html_e( 'Status', 'fleuriste' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $drivers as $id => $d ) : ?>
					<?php
					$link  = fleuriste_driver_link( $d );
					$today = function_exists( 'fleuriste_driver_orders' ) ? count( fleuriste_driver_orders( $id, fleuriste_board_day() ) ) : 0;
					$wa    = $d['phone'] ? 'https://wa.me/' . preg_replace( '/\D+/', '', ( 0 === strpos( preg_replace( '/\s+/', '', $d['phone'] ), '+' ) ? $d['phone'] : '230' . $d['phone'] ) ) . '?text=' . rawurlencode( sprintf( /* translators: %s: link */ __( 'Your deliveries: %s', 'fleuriste' ), $link ) ) : '';
					?>
					<tr class="<?php echo empty( $d['active'] ) ? 'is-off' : ''; ?>">
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fl-drivers__row">
								<?php $form( 'save', $id ); ?>
								<input type="text" name="name" value="<?php echo esc_attr( $d['name'] ); ?>" required>
								<input type="tel" name="phone" value="<?php echo esc_attr( $d['phone'] ); ?>" placeholder="<?php esc_attr_e( 'Phone', 'fleuriste' ); ?>">
								<button class="button"><?php esc_html_e( 'Save', 'fleuriste' ); ?></button>
							</form>
						</td>
						<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=fleuriste-deliveries&view=today&driver=' . $id ) ); ?>"><?php echo esc_html( sprintf( /* translators: %d: number */ _n( '%d delivery', '%d deliveries', $today, 'fleuriste' ), $today ) ); ?></a>
							<br><a href="<?php echo esc_url( fleuriste_run_sheet_url( fleuriste_board_day(), $id ) ); ?>" target="_blank"><?php esc_html_e( 'Print run sheet', 'fleuriste' ); ?></a></td>
						<td class="fl-drivers__link">
							<input type="text" readonly value="<?php echo esc_attr( $link ); ?>" onclick="this.select()">
							<button type="button" class="button fl-copy" data-link="<?php echo esc_attr( $link ); ?>"><?php esc_html_e( 'Copy', 'fleuriste' ); ?></button>
							<?php if ( $wa ) : ?>
								<a class="button" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Send by WhatsApp', 'fleuriste' ); ?></a>
							<?php endif; ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
								<?php $form( 'newlink', $id ); ?>
								<button class="button-link" onclick="return confirm('<?php echo esc_js( __( 'Create a new link? The current link will stop working.', 'fleuriste' ) ); ?>')"><?php esc_html_e( 'New link', 'fleuriste' ); ?></button>
							</form>
						</td>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<?php $form( 'toggle', $id ); ?>
								<?php if ( empty( $d['active'] ) ) : ?>
									<span class="fl-off"><?php esc_html_e( 'Inactive', 'fleuriste' ); ?></span> <button class="button-link"><?php esc_html_e( 'Reactivate', 'fleuriste' ); ?></button>
								<?php else : ?>
									<span class="fl-on"><?php esc_html_e( 'Active', 'fleuriste' ); ?></span> <button class="button-link button-link-delete"><?php esc_html_e( 'Deactivate', 'fleuriste' ); ?></button>
								<?php endif; ?>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description"><?php esc_html_e( 'Deactivated drivers disappear from the driver lists and their link stops working; their past orders keep their name.', 'fleuriste' ); ?></p>
		<?php endif; ?>
	</div>
	<style>
		.fl-drivers__add,.fl-drivers__row{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
		.fl-drivers__add input,.fl-drivers__row input{min-width:180px}
		.fl-drivers__link input{width:260px;font-size:12px}
		.fl-drivers__table td{vertical-align:middle}
		.fl-drivers__table tr.is-off td{opacity:.6}
		.fl-on{color:#1e6b41;font-weight:600}.fl-off{color:#8c8f94;font-weight:600}
	</style>
	<script>
	document.addEventListener( 'click', function ( e ) {
		var b = e.target.closest( '.fl-copy' );
		if ( ! b ) { return; }
		navigator.clipboard && navigator.clipboard.writeText( b.dataset.link ).then( function () {
			var t = b.textContent; b.textContent = '<?php echo esc_js( __( 'Copied', 'fleuriste' ) ); ?>'; setTimeout( function () { b.textContent = t; }, 1500 );
		} );
	} );
	</script>
	<?php
}

/* -------------------------------------------------------------------------
 * Driver box on the order screen
 * ---------------------------------------------------------------------- */

add_action(
	'add_meta_boxes',
	function () {
		$screen = function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';
		add_meta_box(
			'fleuriste-driver',
			__( 'Delivery driver', 'fleuriste' ),
			function ( $post_or_order ) {
				$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
				if ( ! $order ) {
					return;
				}
				wp_nonce_field( 'fleuriste_driver_box', 'fleuriste_driver_nonce' );
				echo fleuriste_driver_select( 'fleuriste_driver', (string) $order->get_meta( FLEURISTE_DRIVER_META ), 'widefat' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '<p class="description"><a href="' . esc_url( admin_url( 'admin.php?page=fleuriste-drivers' ) ) . '">' . esc_html__( 'Manage drivers', 'fleuriste' ) . '</a></p>';
			},
			$screen,
			'side',
			'high'
		);
	}
);

add_action(
	'woocommerce_process_shop_order_meta',
	function ( $order_id ) {
		if ( ! isset( $_POST['fleuriste_driver_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['fleuriste_driver_nonce'] ) ), 'fleuriste_driver_box' ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( $order && isset( $_POST['fleuriste_driver'] ) ) {
			fleuriste_assign_driver( $order, sanitize_key( wp_unslash( $_POST['fleuriste_driver'] ) ) );
		}
	},
	50
);

// "Driver" column on the Orders list.
foreach ( array( 'manage_woocommerce_page_wc-orders_columns', 'manage_edit-shop_order_columns' ) as $fleuriste_hook ) {
	add_filter(
		$fleuriste_hook,
		function ( $columns ) {
			$new = array();
			foreach ( $columns as $key => $label ) {
				$new[ $key ] = $label;
				if ( 'order_status' === $key ) {
					$new['fleuriste_driver'] = __( 'Driver', 'fleuriste' );
				}
			}
			return $new;
		}
	);
}
add_action(
	'manage_woocommerce_page_wc-orders_custom_column',
	function ( $column, $order ) {
		if ( 'fleuriste_driver' === $column ) {
			$d = fleuriste_driver( (string) $order->get_meta( FLEURISTE_DRIVER_META ) );
			echo $d ? esc_html( $d['name'] ) : '<span aria-hidden="true">—</span>';
		}
	},
	10,
	2
);
add_action(
	'manage_shop_order_posts_custom_column',
	function ( $column, $post_id ) {
		if ( 'fleuriste_driver' === $column ) {
			$order = wc_get_order( $post_id );
			$d     = $order ? fleuriste_driver( (string) $order->get_meta( FLEURISTE_DRIVER_META ) ) : null;
			echo $d ? esc_html( $d['name'] ) : '<span aria-hidden="true">—</span>';
		}
	},
	10,
	2
);

/* -------------------------------------------------------------------------
 * Driver's page (private link, no login)
 * ---------------------------------------------------------------------- */

/**
 * Active orders for a driver on a date.
 *
 * @param string $driver_id Driver ID.
 * @param string $date      Y-m-d.
 * @return array[]
 */
function fleuriste_driver_orders( $driver_id, $date ) {
	$orders = wc_get_orders(
		array(
			'status'     => array( 'processing', 'preparing', 'out-for-delivery', 'on-hold', 'pending', 'completed' ),
			'limit'      => 200,
			'type'       => 'shop_order',
			'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => FLEURISTE_DRIVER_META,
					'value' => $driver_id,
				),
			),
		)
	);
	$list = array();
	foreach ( $orders as $order ) {
		$data = fleuriste_board_order_data( $order );
		if ( $data['driver'] !== (string) $driver_id ) {
			continue; // Older order storage ignores meta_query, so check here too.
		}
		if ( $data['date'] === $date || ( ! $data['date'] && $date === fleuriste_board_day() ) ) {
			if ( 'completed' === $data['status'] && $order->get_date_modified() && $order->get_date_modified()->getTimestamp() < time() - DAY_IN_SECONDS ) {
				continue;
			}
			$list[] = $data;
		}
	}
	usort(
		$list,
		function ( $a, $b ) {
			return array( 'completed' === $a['status'], $a['slot_rank'], $a['created'] ) <=> array( 'completed' === $b['status'], $b['slot_rank'], $b['created'] );
		}
	);
	return $list;
}

add_action(
	'template_redirect',
	function () {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['fleuriste-driver'] ) ) {
			return;
		}
		$token = sanitize_text_field( wp_unslash( $_GET['fleuriste-driver'] ) );
		// phpcs:enable
		$driver_id = '';
		$driver    = null;
		foreach ( fleuriste_drivers( true ) as $id => $d ) {
			if ( hash_equals( (string) $d['token'], $token ) ) {
				$driver_id = $id;
				$driver    = $d;
				break;
			}
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );
		if ( ! $driver ) {
			status_header( 404 );
			wp_die( esc_html__( 'This link is not valid any more. Please ask the shop for a new one.', 'fleuriste' ), esc_html__( 'Deliveries', 'fleuriste' ), array( 'response' => 404 ) );
		}

		// "Delivered" button.
		$done = '';
		if ( isset( $_POST['delivered'], $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ), 'fleuriste_driver_' . $driver_id ) ) {
			$order = wc_get_order( absint( $_POST['delivered'] ) );
			if ( $order && (string) $order->get_meta( FLEURISTE_DRIVER_META ) === (string) $driver_id && in_array( $order->get_status(), array( 'processing', 'preparing', 'out-for-delivery' ), true ) ) {
				/* translators: %s: driver name */
				$order->update_status( 'completed', sprintf( __( 'Marked delivered by driver %s.', 'fleuriste' ), $driver['name'] ) );
				/* translators: %s: order number */
				$done = sprintf( __( 'Order #%s marked as delivered. Thank you!', 'fleuriste' ), $order->get_order_number() );
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$day   = ( isset( $_GET['day'] ) && 'tomorrow' === $_GET['day'] ) ? 'tomorrow' : 'today';
		$date  = fleuriste_board_day( 'tomorrow' === $day ? 1 : 0 );
		$list  = fleuriste_driver_orders( $driver_id, $date );
		$flow  = fleuriste_board_flow();
		$base  = fleuriste_driver_link( $driver );
		$todo  = count(
			array_filter(
				$list,
				function ( $o ) {
					return 'completed' !== $o['status'];
				}
			)
		);
		?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html( sprintf( /* translators: %s: driver */ __( 'Deliveries – %s', 'fleuriste' ), $driver['name'] ) ); ?></title>
<style>
	:root{--rose:#9E4F63;--ink:#2B1D21;--blush:#F6EDEE;--line:#EBDCDD}
	*{box-sizing:border-box}
	body{margin:0;font:16px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#FCF8F8;color:#3d3236}
	header{background:var(--ink);color:#fff;padding:16px}
	header h1{margin:0;font-size:20px}
	header p{margin:2px 0 0;opacity:.8;font-size:14px}
	main{padding:12px 12px 40px;max-width:640px;margin:0 auto}
	.tabs{display:flex;gap:8px;margin:4px 0 12px}
	.tabs a{flex:1;text-align:center;padding:10px;border-radius:999px;border:1px solid var(--line);background:#fff;color:var(--ink);text-decoration:none;font-weight:600}
	.tabs a.on{background:var(--rose);border-color:var(--rose);color:#fff}
	.ok{background:#d7f0e0;color:#1e6b41;padding:10px 14px;border-radius:10px;margin:0 0 12px}
	.card{background:#fff;border:1px solid var(--line);border-left:4px solid var(--rose);border-radius:12px;padding:14px;margin:0 0 12px}
	.card.done{border-left-color:#2e8a57;opacity:.6}
	.top{display:flex;justify-content:space-between;gap:8px;align-items:center;font-size:14px;color:#6b5d61}
	.slot{font-weight:700;color:var(--rose)}
	.name{font-size:18px;font-weight:700;color:var(--ink);margin:6px 0 0}
	.addr{margin:2px 0 10px}
	.btns{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
	.btns a{display:block;text-align:center;padding:10px 4px;border-radius:10px;background:var(--blush);color:var(--rose);font-weight:600;text-decoration:none}
	.items{margin:10px 0 0;padding:10px;background:#faf6f6;border-radius:8px;font-size:14px}
	.warn{margin-top:8px;font-size:14px;font-weight:600;color:#b32d2e}
	.paid{font-size:14px;margin-top:6px}
	form{margin:10px 0 0}
	button{width:100%;padding:14px;border:0;border-radius:999px;background:var(--ink);color:#fff;font-size:16px;font-weight:600}
	.empty{text-align:center;color:#6b5d61;padding:40px 10px}
</style>
</head>
<body>
<header>
	<h1><?php echo esc_html( sprintf( /* translators: %s: driver */ __( 'Hello %s', 'fleuriste' ), $driver['name'] ) ); ?></h1>
	<p><?php echo esc_html( date_i18n( 'l j F', strtotime( $date ) ) ); ?> · <?php echo esc_html( sprintf( /* translators: %d: number */ _n( '%d delivery to do', '%d deliveries to do', $todo, 'fleuriste' ), $todo ) ); ?></p>
</header>
<main>
	<nav class="tabs">
		<a class="<?php echo 'today' === $day ? 'on' : ''; ?>" href="<?php echo esc_url( $base ); ?>"><?php esc_html_e( 'Today', 'fleuriste' ); ?></a>
		<a class="<?php echo 'tomorrow' === $day ? 'on' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'day', 'tomorrow', $base ) ); ?>"><?php esc_html_e( 'Tomorrow', 'fleuriste' ); ?></a>
	</nav>
	<?php if ( $done ) : ?>
		<div class="ok"><?php echo esc_html( $done ); ?></div>
	<?php endif; ?>
	<?php if ( ! $list ) : ?>
		<div class="empty"><?php esc_html_e( 'No deliveries assigned to you for this day.', 'fleuriste' ); ?></div>
	<?php endif; ?>
	<?php foreach ( $list as $o ) : ?>
		<?php
		$maps = $o['address'] ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $o['address'] . ', Mauritius' ) : '';
		$wa   = $o['r_phone'] ? 'https://wa.me/' . preg_replace( '/\D+/', '', ( 0 === strpos( preg_replace( '/\s+/', '', $o['r_phone'] ), '+' ) ? $o['r_phone'] : '230' . $o['r_phone'] ) ) : '';
		$paid = in_array( $o['status'], array( 'pending', 'on-hold' ), true ) || false !== stripos( $o['payment'], 'cash' );
		?>
		<article class="card<?php echo 'completed' === $o['status'] ? ' done' : ''; ?>">
			<div class="top"><span class="slot"><?php echo esc_html( $o['slot'] ? $o['slot'] : __( 'Any time', 'fleuriste' ) ); ?></span><span>#<?php echo esc_html( $o['number'] ); ?> · <?php echo esc_html( isset( $flow[ $o['status'] ] ) ? $flow[ $o['status'] ][0] : $o['status'] ); ?></span></div>
			<div class="name"><?php echo esc_html( $o['recipient'] ); ?></div>
			<div class="addr"><?php echo esc_html( $o['address'] ); ?></div>
			<div class="btns">
				<?php if ( $o['r_phone'] ) : ?>
					<a href="tel:<?php echo esc_attr( $o['r_phone'] ); ?>"><?php esc_html_e( 'Call', 'fleuriste' ); ?></a>
					<a href="<?php echo esc_url( $wa ); ?>"><?php esc_html_e( 'WhatsApp', 'fleuriste' ); ?></a>
				<?php endif; ?>
				<?php if ( $maps ) : ?>
					<a href="<?php echo esc_url( $maps ); ?>"><?php esc_html_e( 'Map', 'fleuriste' ); ?></a>
				<?php endif; ?>
			</div>
			<div class="items">
				<?php foreach ( $o['items'] as $it ) : ?>
					<div><?php echo (int) $it['qty']; ?> × <?php echo esc_html( $it['name'] ); ?></div>
				<?php endforeach; ?>
				<?php if ( $o['note'] ) : ?>
					<div><em><?php echo esc_html( $o['note'] ); ?></em></div>
				<?php endif; ?>
			</div>
			<?php if ( $o['anonymous'] ) : ?>
				<div class="warn"><?php esc_html_e( 'Anonymous gift – do not say who it is from.', 'fleuriste' ); ?></div>
			<?php else : ?>
				<div class="paid"><?php echo esc_html( sprintf( /* translators: %s: name */ __( 'From: %s', 'fleuriste' ), $o['sender'] ) ); ?></div>
			<?php endif; ?>
			<?php if ( $paid && 'completed' !== $o['status'] ) : ?>
				<div class="warn"><?php echo esc_html( sprintf( /* translators: %s: amount */ __( 'Collect payment: %s', 'fleuriste' ), $o['total'] ) ); ?></div>
			<?php endif; ?>
			<?php if ( in_array( $o['status'], array( 'processing', 'preparing', 'out-for-delivery' ), true ) ) : ?>
				<form method="post">
					<?php wp_nonce_field( 'fleuriste_driver_' . $driver_id ); ?>
					<input type="hidden" name="delivered" value="<?php echo (int) $o['id']; ?>">
					<button onclick="return confirm('<?php echo esc_js( __( 'Mark this order as delivered?', 'fleuriste' ) ); ?>')"><?php esc_html_e( 'Delivered ✓', 'fleuriste' ); ?></button>
				</form>
			<?php endif; ?>
		</article>
	<?php endforeach; ?>
</main>
</body>
</html>
		<?php
		exit;
	}
);

/* -------------------------------------------------------------------------
 * Printable run sheets (one page per driver)
 * ---------------------------------------------------------------------- */

/**
 * Link to the run sheet.
 *
 * @param string $date   Y-m-d.
 * @param string $driver Driver ID, "all" or "none".
 * @return string
 */
function fleuriste_run_sheet_url( $date, $driver = 'all' ) {
	return wp_nonce_url(
		add_query_arg(
			array(
				'action' => 'fleuriste_run_sheet',
				'date'   => $date,
				'driver' => $driver,
			),
			admin_url( 'admin-post.php' )
		),
		'fleuriste_run_sheet'
	);
}

/**
 * Small "print run sheets" form for the board.
 *
 * @param string $date   Default date.
 * @param string $driver Default driver.
 * @return string
 */
function fleuriste_run_sheet_form( $date, $driver = '' ) {
	ob_start();
	?>
	<form class="fl-runsheet" method="get" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" target="_blank">
		<input type="hidden" name="action" value="fleuriste_run_sheet">
		<?php wp_nonce_field( 'fleuriste_run_sheet', '_wpnonce', false ); ?>
		<span class="dashicons dashicons-printer" aria-hidden="true"></span>
		<label><span class="screen-reader-text"><?php esc_html_e( 'Date', 'fleuriste' ); ?></span><input type="date" name="date" value="<?php echo esc_attr( $date ); ?>"></label>
		<label><span class="screen-reader-text"><?php esc_html_e( 'Driver', 'fleuriste' ); ?></span>
			<select name="driver">
				<option value="all"><?php esc_html_e( 'All drivers (one page each)', 'fleuriste' ); ?></option>
				<?php foreach ( fleuriste_drivers( true ) as $id => $d ) : ?>
					<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $id, $driver ); ?>><?php echo esc_html( $d['name'] ); ?></option>
				<?php endforeach; ?>
				<option value="none" <?php selected( 'none', $driver ); ?>><?php esc_html_e( 'Orders with no driver', 'fleuriste' ); ?></option>
			</select>
		</label>
		<button class="button"><?php esc_html_e( 'Print run sheets', 'fleuriste' ); ?></button>
	</form>
	<?php
	return ob_get_clean();
}

add_action(
	'admin_post_fleuriste_run_sheet',
	function () {
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'fleuriste' ) );
		}
		check_admin_referer( 'fleuriste_run_sheet' );
		$date   = isset( $_GET['date'] ) ? sanitize_text_field( wp_unslash( $_GET['date'] ) ) : '';
		$date   = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : fleuriste_board_day();
		$choice = isset( $_GET['driver'] ) ? sanitize_key( wp_unslash( $_GET['driver'] ) ) : 'all';

		// Active orders for that date.
		$orders = array_filter(
			fleuriste_board_orders(),
			function ( $o ) use ( $date ) {
				return $o['date'] === $date && empty( $o['pickup'] ) && in_array( $o['status'], array( 'pending', 'on-hold', 'processing', 'preparing', 'out-for-delivery' ), true );
			}
		);
		usort(
			$orders,
			function ( $a, $b ) {
				return array( $a['slot_rank'], $a['created'] ) <=> array( $b['slot_rank'], $b['created'] );
			}
		);

		// Pages: one per driver (only drivers with stops when printing everyone).
		$pages   = array();
		$drivers = fleuriste_drivers();
		$wanted  = 'all' === $choice ? array_keys( $drivers ) : array( $choice );
		foreach ( $wanted as $id ) {
			$stops = array_values(
				array_filter(
					$orders,
					function ( $o ) use ( $id ) {
						return 'none' === $id ? '' === $o['driver'] : $o['driver'] === (string) $id;
					}
				)
			);
			if ( 'all' === $choice && ! $stops ) {
				continue;
			}
			$pages[] = array(
				'title' => 'none' === $id ? __( 'Not assigned to a driver', 'fleuriste' ) : ( isset( $drivers[ $id ] ) ? $drivers[ $id ]['name'] : $id ),
				'phone' => isset( $drivers[ $id ] ) ? $drivers[ $id ]['phone'] : '',
				'stops' => $stops,
			);
		}
		if ( 'all' === $choice ) {
			$unassigned = array_values(
				array_filter(
					$orders,
					function ( $o ) {
						return '' === $o['driver'];
					}
				)
			);
			if ( $unassigned ) {
				$pages[] = array(
					'title' => __( 'Not assigned to a driver', 'fleuriste' ),
					'phone' => '',
					'stops' => $unassigned,
				);
			}
		}

		$day_label = date_i18n( 'l j F Y', strtotime( $date ) );
		nocache_headers();
		?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( sprintf( /* translators: %s: date */ __( 'Run sheets – %s', 'fleuriste' ), $day_label ) ); ?></title>
<style>
	@page { size: A4 landscape; margin: 12mm; }
	* { box-sizing: border-box; }
	body { margin: 0; font: 12px/1.4 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; color: #111; background: #f3f3f3; }
	.toolbar { position: sticky; top: 0; background: #2B1D21; color: #fff; padding: 10px 16px; display: flex; gap: 12px; align-items: center; }
	.toolbar button { background: #fff; color: #2B1D21; border: 0; border-radius: 999px; padding: 8px 18px; font-weight: 600; cursor: pointer; }
	.sheet { background: #fff; max-width: 1120px; margin: 16px auto; padding: 18px 22px; box-shadow: 0 2px 10px rgba(0,0,0,.08); }
	.sheet + .sheet { page-break-before: always; break-before: page; }
	.head { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 2px solid #111; padding-bottom: 8px; margin-bottom: 10px; }
	.head h1 { margin: 0; font-size: 20px; }
	.head .meta { text-align: right; }
	.sum { display: flex; gap: 24px; margin: 0 0 10px; font-size: 13px; }
	.sum strong { font-size: 15px; }
	table { width: 100%; border-collapse: collapse; }
	th, td { border: 1px solid #999; padding: 6px 7px; vertical-align: top; text-align: left; }
	th { background: #eee; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; }
	td.n { width: 28px; text-align: center; font-weight: 700; }
	td.time { width: 105px; font-weight: 600; }
	td.who strong { font-size: 13px; }
	td.phone { width: 110px; white-space: nowrap; }
	td.items { width: 190px; }
	td.cash { width: 95px; font-weight: 700; white-space: nowrap; }
	td.notes { width: 170px; }
	td.sign { width: 120px; }
	.anon { display: inline-block; background: #111; color: #fff; padding: 1px 6px; border-radius: 4px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
	.empty { padding: 24px; text-align: center; color: #666; border: 1px dashed #bbb; }
	.foot { margin-top: 10px; display: flex; justify-content: space-between; font-size: 11px; color: #555; }
	@media print { body { background: #fff; } .toolbar { display: none; } .sheet { box-shadow: none; margin: 0; max-width: none; padding: 0; } }
</style>
</head>
<body>
<div class="toolbar">
	<button onclick="window.print()"><?php esc_html_e( 'Print', 'fleuriste' ); ?></button>
	<span><?php echo esc_html( $day_label ); ?> · <?php echo esc_html( sprintf( /* translators: %d: number */ _n( '%d page', '%d pages', max( 1, count( $pages ) ), 'fleuriste' ), max( 1, count( $pages ) ) ) ); ?></span>
</div>
<?php if ( ! $pages ) : ?>
	<section class="sheet"><div class="empty"><?php esc_html_e( 'No deliveries for this date.', 'fleuriste' ); ?></div></section>
<?php endif; ?>
<?php foreach ( $pages as $page ) : ?>
	<?php
	$cash = 0.0;
	foreach ( $page['stops'] as $o ) {
		$order = wc_get_order( $o['id'] );
		if ( $order && ( 'cod' === $order->get_payment_method() || in_array( $o['status'], array( 'pending', 'on-hold' ), true ) ) ) {
			$cash += (float) $order->get_total();
		}
	}
	?>
	<section class="sheet">
		<div class="head">
			<div>
				<h1><?php echo esc_html( $page['title'] ); ?></h1>
				<?php if ( $page['phone'] ) : ?><div><?php echo esc_html( $page['phone'] ); ?></div><?php endif; ?>
			</div>
			<div class="meta">
				<strong><?php echo esc_html( get_bloginfo( 'name' ) ); ?></strong><br>
				<?php echo esc_html( sprintf( /* translators: %s: date */ __( 'Run sheet – %s', 'fleuriste' ), $day_label ) ); ?>
			</div>
		</div>
		<div class="sum">
			<span><?php esc_html_e( 'Stops', 'fleuriste' ); ?>: <strong><?php echo (int) count( $page['stops'] ); ?></strong></span>
			<span><?php esc_html_e( 'Bouquets / items', 'fleuriste' ); ?>: <strong><?php echo (int) array_sum( array_map( function ( $o ) { return array_sum( wp_list_pluck( $o['items'], 'qty' ) ); }, $page['stops'] ) ); ?></strong></span>
			<span><?php esc_html_e( 'Cash to collect', 'fleuriste' ); ?>: <strong><?php echo esc_html( html_entity_decode( wp_strip_all_tags( wc_price( $cash ) ) ) ); ?></strong></span>
		</div>
		<?php if ( ! $page['stops'] ) : ?>
			<div class="empty"><?php esc_html_e( 'No deliveries for this driver on this date.', 'fleuriste' ); ?></div>
		<?php else : ?>
			<table>
				<thead><tr>
					<th>#</th>
					<th><?php esc_html_e( 'Time', 'fleuriste' ); ?></th>
					<th><?php esc_html_e( 'Recipient and address', 'fleuriste' ); ?></th>
					<th><?php esc_html_e( 'Phone', 'fleuriste' ); ?></th>
					<th><?php esc_html_e( 'Items', 'fleuriste' ); ?></th>
					<th><?php esc_html_e( 'Collect', 'fleuriste' ); ?></th>
					<th><?php esc_html_e( 'Notes', 'fleuriste' ); ?></th>
					<th><?php esc_html_e( 'Delivered – time / signature', 'fleuriste' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $page['stops'] as $i => $o ) : ?>
					<?php
					$order   = wc_get_order( $o['id'] );
					$collect = $order && ( 'cod' === $order->get_payment_method() || in_array( $o['status'], array( 'pending', 'on-hold' ), true ) );
					?>
					<tr>
						<td class="n"><?php echo (int) ( $i + 1 ); ?></td>
						<td class="time"><?php echo esc_html( $o['slot'] ? $o['slot'] : __( 'Any time', 'fleuriste' ) ); ?><br><small>#<?php echo esc_html( $o['number'] ); ?></small></td>
						<td class="who"><strong><?php echo esc_html( $o['recipient'] ); ?></strong><br><?php echo esc_html( $o['address'] ); ?></td>
						<td class="phone"><?php echo esc_html( $o['r_phone'] ? $o['r_phone'] : '—' ); ?></td>
						<td class="items">
							<?php foreach ( $o['items'] as $it ) : ?>
								<div><?php echo (int) $it['qty']; ?> × <?php echo esc_html( $it['name'] ); ?></div>
							<?php endforeach; ?>
						</td>
						<td class="cash"><?php echo $collect ? esc_html( $o['total'] ) : esc_html__( 'Paid', 'fleuriste' ); ?></td>
						<td class="notes">
							<?php if ( $o['anonymous'] ) : ?>
								<span class="anon"><?php esc_html_e( 'Anonymous', 'fleuriste' ); ?></span> <?php esc_html_e( 'don\'t say who it is from', 'fleuriste' ); ?><br>
							<?php else : ?>
								<?php echo esc_html( sprintf( /* translators: %s: name */ __( 'From: %s', 'fleuriste' ), $o['sender'] ) ); ?><br>
							<?php endif; ?>
							<?php echo esc_html( $o['note'] ); ?>
						</td>
						<td class="sign"></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<div class="foot">
			<span><?php echo esc_html( sprintf( /* translators: %s: date and time */ __( 'Printed %s', 'fleuriste' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ) ); ?></span>
			<span><?php esc_html_e( 'Driver signature: ____________________', 'fleuriste' ); ?></span>
		</div>
	</section>
<?php endforeach; ?>
<script>window.addEventListener( 'load', function () { setTimeout( function () { window.print(); }, 300 ); } );</script>
</body>
</html>
		<?php
		exit;
	}
);
