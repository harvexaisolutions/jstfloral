<?php
/**
 * Quick stock: one switch per product to mark it in or out of stock.
 *
 * Orders & Deliveries → Stock, a "Mark out of stock" link in Products, and an admin-bar
 * button on product pages for the shop owner.
 *
 * @package Fleuriste
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'fleuriste-deliveries', __( 'Stock', 'fleuriste' ), __( 'Stock', 'fleuriste' ), 'edit_products', 'fleuriste-stock', 'fleuriste_stock_page', 3 );
	},
	20
);

/**
 * Mark a product (or variation) in or out of stock.
 *
 * Products that count stock go to 0 when marked out; when marked back in with no
 * count left, stock counting is switched off so the product can be sold again.
 *
 * @param WC_Product $product Product.
 * @param bool       $in      In stock?
 * @param int|null   $qty     New stock count (only for products that count stock).
 * @return string Result note.
 */
function fleuriste_stock_set( $product, $in, $qty = null ) {
	$note = '';
	if ( $product->managing_stock() ) {
		if ( null !== $qty ) {
			wc_update_product_stock( $product, max( 0, (int) $qty ) );
		} elseif ( ! $in ) {
			wc_update_product_stock( $product, 0 );
		} elseif ( (int) $product->get_stock_quantity() <= 0 ) {
			$product->set_manage_stock( false );
			$product->set_stock_status( 'instock' );
			$product->save();
			$note = __( 'Stock count turned off', 'fleuriste' );
		}
	} else {
		$product->set_stock_status( $in ? 'instock' : 'outofstock' );
		$product->save();
	}
	// Variable products: the parent follows its variations.
	if ( $product->is_type( 'variation' ) ) {
		WC_Product_Variable::sync( $product->get_parent_id() );
	} elseif ( $product->is_type( 'variable' ) && null === $qty ) {
		foreach ( $product->get_children() as $child_id ) {
			$child = wc_get_product( $child_id );
			if ( $child ) {
				fleuriste_stock_set( $child, $in );
			}
		}
		WC_Product_Variable::sync( $product->get_id() );
	}
	wc_delete_product_transients( $product->get_parent_id() ? $product->get_parent_id() : $product->get_id() );
	return $note;
}

/**
 * Data for one row.
 *
 * @param WC_Product $product Product.
 * @return array
 */
function fleuriste_stock_row( $product ) {
	$product = wc_get_product( $product->get_id() ); // Fresh copy after a change.
	return array(
		'id'      => $product->get_id(),
		'in'      => $product->is_in_stock(),
		'managed' => $product->managing_stock(),
		'qty'     => $product->managing_stock() ? (int) $product->get_stock_quantity() : null,
	);
}

/* -------------------------------------------------------------------------
 * The page
 * ---------------------------------------------------------------------- */

/**
 * Orders & Deliveries → Stock.
 */
function fleuriste_stock_page() {
	$products = wc_get_products(
		array(
			'status'  => array( 'publish', 'private' ),
			'limit'   => 1000,
			'orderby' => 'title',
			'order'   => 'ASC',
			'type'    => array( 'simple', 'variable' ),
		)
	);
	$out      = 0;
	foreach ( $products as $p ) {
		if ( ! $p->is_in_stock() ) {
			++$out;
		}
	}
	$terms = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => true,
		)
	);
	?>
	<div class="wrap fl-stock" data-nonce="<?php echo esc_attr( wp_create_nonce( 'fleuriste_stock' ) ); ?>">
		<h1><?php esc_html_e( 'Stock', 'fleuriste' ); ?></h1>
		<p class="fl-stock__intro"><?php esc_html_e( 'Flip the switch to take a product off sale for now (e.g. no peonies today) and back on when it\'s available again. Changes are saved straight away.', 'fleuriste' ); ?></p>

		<div class="fl-stock__bar">
			<input type="search" id="fl-stock-q" placeholder="<?php esc_attr_e( 'Search products…', 'fleuriste' ); ?>">
			<select id="fl-stock-cat">
				<option value=""><?php esc_html_e( 'All categories', 'fleuriste' ); ?></option>
				<?php if ( ! is_wp_error( $terms ) ) : ?>
					<?php foreach ( $terms as $t ) : ?>
						<option value="<?php echo (int) $t->term_id; ?>"><?php echo esc_html( $t->name ); ?></option>
					<?php endforeach; ?>
				<?php endif; ?>
			</select>
			<label class="fl-stock__only"><input type="checkbox" id="fl-stock-out"> <?php esc_html_e( 'Only out of stock', 'fleuriste' ); ?> (<span id="fl-stock-n"><?php echo (int) $out; ?></span>)</label>
		</div>

		<ul class="fl-stock__list" id="fl-stock-list">
			<?php foreach ( $products as $p ) : ?>
				<?php
				$ids  = wc_get_product_term_ids( $p->get_id(), 'product_cat' );
				$kids = $p->is_type( 'variable' ) ? array_filter( array_map( 'wc_get_product', $p->get_children() ) ) : array();
				?>
				<li class="fl-stock__item" data-name="<?php echo esc_attr( strtolower( $p->get_name() ) ); ?>" data-cats="<?php echo esc_attr( ',' . implode( ',', $ids ) . ',' ); ?>">
					<?php fleuriste_stock_line( $p, $p->get_name(), true ); ?>
					<?php if ( $kids ) : ?>
						<ul class="fl-stock__kids">
							<?php foreach ( $kids as $k ) : ?>
								<li><?php fleuriste_stock_line( $k, wc_get_formatted_variation( $k, true, false ), false ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
		<p class="fl-stock__empty" id="fl-stock-empty" hidden><?php esc_html_e( 'No products match.', 'fleuriste' ); ?></p>
		<p class="description"><?php esc_html_e( 'Out-of-stock products stay visible in the shop marked "Out of stock" and can\'t be ordered. To hide them completely, tick "Hide out of stock items from the catalogue" in WooCommerce → Settings → Products → Inventory.', 'fleuriste' ); ?></p>
	</div>
	<style>
		.fl-stock{max-width:900px}
		.fl-stock__intro{font-size:14px;color:#50575e;margin:4px 0 16px}
		.fl-stock__bar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:14px}
		.fl-stock__bar input[type=search]{min-width:220px;flex:1;min-height:38px}
		.fl-stock__bar select{min-height:38px}
		.fl-stock__list,.fl-stock__kids{list-style:none;margin:0;padding:0}
		.fl-stock__item{background:#fff;border:1px solid #e2d6d8;border-radius:10px;margin:0 0 8px;overflow:hidden}
		.fl-stock__item[hidden]{display:none}
		.fl-stock__line{display:flex;align-items:center;gap:12px;padding:10px 14px}
		.fl-stock__kids .fl-stock__line{padding:8px 14px 8px 72px;border-top:1px solid #f1e8e9;background:#fdfafa}
		.fl-stock__line img{width:46px;height:46px;object-fit:cover;border-radius:8px;flex:none}
		.fl-stock__name{flex:1;min-width:0}
		.fl-stock__name a{font-weight:600;font-size:14px;text-decoration:none;color:#2b1d21}
		.fl-stock__name small{display:block;color:#7a6a6e;margin-top:2px}
		.fl-stock__qty{width:70px}
		.fl-stock__state{font-size:12px;font-weight:600;min-width:86px;text-align:right}
		.fl-stock__line.is-out .fl-stock__state{color:#b32d2e}
		.fl-stock__line:not(.is-out) .fl-stock__state{color:#1a7f37}
		.fl-stock__line.is-out img{filter:grayscale(1);opacity:.6}
		.fl-switch{position:relative;width:46px;height:26px;flex:none;cursor:pointer}
		.fl-switch input{position:absolute;opacity:0;inset:0;margin:0;cursor:pointer}
		.fl-switch span{position:absolute;inset:0;border-radius:99px;background:#d0c4c6;transition:.2s}
		.fl-switch span::after{content:"";position:absolute;top:3px;left:3px;width:20px;height:20px;border-radius:50%;background:#fff;transition:.2s;box-shadow:0 1px 2px rgba(0,0,0,.25)}
		.fl-switch input:checked+span{background:#1a7f37}
		.fl-switch input:checked+span::after{transform:translateX(20px)}
		.fl-switch input:focus-visible+span{outline:2px solid #9e4f63;outline-offset:2px}
		.fl-stock__line.is-busy{opacity:.55}
		@media (max-width:600px){.fl-stock__kids .fl-stock__line{padding-left:24px}.fl-stock__state{min-width:0}.fl-stock__qty-wrap small{display:none}}
	</style>
	<script>
	( function () {
		var wrap = document.querySelector( '.fl-stock' );
		var nonce = wrap.dataset.nonce;
		var q = document.getElementById( 'fl-stock-q' ), cat = document.getElementById( 'fl-stock-cat' ), onlyOut = document.getElementById( 'fl-stock-out' );
		var txtIn = <?php echo wp_json_encode( __( 'In stock', 'fleuriste' ) ); ?>, txtOut = <?php echo wp_json_encode( __( 'Out of stock', 'fleuriste' ) ); ?>, txtLeft = <?php echo wp_json_encode( __( '%d left', 'fleuriste' ) ); ?>;
		function filter() {
			var term = q.value.trim().toLowerCase(), c = cat.value, any = false;
			document.querySelectorAll( '.fl-stock__item' ).forEach( function ( li ) {
				var show = ( ! term || li.dataset.name.indexOf( term ) !== -1 ) && ( ! c || li.dataset.cats.indexOf( ',' + c + ',' ) !== -1 ) && ( ! onlyOut.checked || li.querySelector( '.is-out' ) );
				li.hidden = ! show; any = any || show;
			} );
			document.getElementById( 'fl-stock-empty' ).hidden = any;
		}
		[ q, cat, onlyOut ].forEach( function ( el ) { el.addEventListener( 'input', filter ); el.addEventListener( 'change', filter ); } );
		function paint( r ) {
			var line = wrap.querySelector( '.fl-stock__line[data-id="' + r.id + '"]' );
			if ( ! line ) { return; }
			line.classList.toggle( 'is-out', ! r.in );
			line.querySelector( '.fl-switch input' ).checked = r.in;
			var qty = line.querySelector( '.fl-stock__qty' );
			if ( qty ) { if ( r.managed ) { qty.value = r.qty; } else { qty.closest( '.fl-stock__qty-wrap' ).remove(); } }
			line.querySelector( '.fl-stock__state' ).textContent = r.in ? ( r.managed ? txtLeft.replace( '%d', r.qty ) : txtIn ) : txtOut;
		}
		function save( line, data ) {
			line.classList.add( 'is-busy' );
			var body = new FormData();
			body.append( 'action', 'fleuriste_stock' ); body.append( 'nonce', nonce ); body.append( 'id', line.dataset.id );
			Object.keys( data ).forEach( function ( k ) { body.append( k, data[ k ] ); } );
			fetch( ajaxurl, { method: 'POST', credentials: 'same-origin', body: body } ).then( function ( r ) { return r.json(); } ).then( function ( res ) {
				line.classList.remove( 'is-busy' );
				if ( ! res.success ) { return; }
				res.data.rows.forEach( paint );
				document.getElementById( 'fl-stock-n' ).textContent = wrap.querySelectorAll( '.fl-stock__item > .fl-stock__line.is-out' ).length;
			} ).catch( function () { line.classList.remove( 'is-busy' ); } );
		}
		wrap.addEventListener( 'change', function ( e ) {
			var line = e.target.closest( '.fl-stock__line' );
			if ( ! line ) { return; }
			if ( e.target.matches( '.fl-switch input' ) ) { save( line, { in: e.target.checked ? 1 : 0 } ); }
			if ( e.target.matches( '.fl-stock__qty' ) ) { save( line, { qty: e.target.value } ); }
		} );
	} )();
	</script>
	<?php
}

/**
 * One product line with its switch.
 *
 * @param WC_Product $p      Product or variation.
 * @param string     $label  Name to show.
 * @param bool       $parent Top-level product?
 */
function fleuriste_stock_line( $p, $label, $parent ) {
	$in   = $p->is_in_stock();
	$edit = get_edit_post_link( $p->get_parent_id() ? $p->get_parent_id() : $p->get_id() );
	if ( $p->managing_stock() ) {
		/* translators: %d: stock count */
		$state = $in ? sprintf( __( '%d left', 'fleuriste' ), (int) $p->get_stock_quantity() ) : __( 'Out of stock', 'fleuriste' );
	} else {
		$state = $in ? __( 'In stock', 'fleuriste' ) : __( 'Out of stock', 'fleuriste' );
	}
	?>
	<div class="fl-stock__line<?php echo $in ? '' : ' is-out'; ?>" data-id="<?php echo (int) $p->get_id(); ?>">
		<?php if ( $parent ) : ?>
			<?php echo wp_kses_post( $p->get_image( 'thumbnail' ) ); ?>
		<?php endif; ?>
		<div class="fl-stock__name">
			<?php if ( $parent ) : ?>
				<a href="<?php echo esc_url( $edit ); ?>"><?php echo esc_html( $label ); ?></a>
				<small><?php echo wp_kses_post( $p->is_type( 'variable' ) ? __( 'All options', 'fleuriste' ) . ' · ' . $p->get_price_html() : $p->get_price_html() ); ?></small>
			<?php else : ?>
				<span><?php echo esc_html( $label ); ?></span> <small><?php echo wp_kses_post( $p->get_price_html() ); ?></small>
			<?php endif; ?>
		</div>
		<?php if ( $p->managing_stock() ) : ?>
			<label class="fl-stock__qty-wrap"><small><?php esc_html_e( 'Count', 'fleuriste' ); ?></small> <input type="number" min="0" step="1" class="fl-stock__qty small-text" value="<?php echo (int) $p->get_stock_quantity(); ?>" aria-label="<?php esc_attr_e( 'Stock count', 'fleuriste' ); ?>"></label>
		<?php endif; ?>
		<span class="fl-stock__state"><?php echo esc_html( $state ); ?></span>
		<label class="fl-switch" title="<?php esc_attr_e( 'In stock', 'fleuriste' ); ?>">
			<input type="checkbox" <?php checked( $in ); ?> aria-label="<?php echo esc_attr( sprintf( /* translators: %s: product */ __( '%s in stock', 'fleuriste' ), wp_strip_all_tags( $label ) ) ); ?>">
			<span></span>
		</label>
	</div>
	<?php
}

add_action(
	'wp_ajax_fleuriste_stock',
	function () {
		check_ajax_referer( 'fleuriste_stock', 'nonce' );
		if ( ! current_user_can( 'edit_products' ) ) {
			wp_send_json_error( null, 403 );
		}
		$product = wc_get_product( isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0 );
		if ( ! $product ) {
			wp_send_json_error();
		}
		if ( isset( $_POST['qty'] ) && $product->managing_stock() ) {
			fleuriste_stock_set( $product, true, (int) $_POST['qty'] );
		} else {
			fleuriste_stock_set( $product, ! empty( $_POST['in'] ) );
		}
		// Send back the product, its parent and its variations so the page stays in step.
		$ids = array( $product->get_id() );
		if ( $product->get_parent_id() ) {
			$ids[] = $product->get_parent_id();
		}
		if ( $product->is_type( 'variable' ) ) {
			$ids = array_merge( $ids, $product->get_children() );
		}
		$rows = array();
		foreach ( $ids as $id ) {
			$p = wc_get_product( $id );
			if ( $p ) {
				$rows[] = fleuriste_stock_row( $p );
			}
		}
		wp_send_json_success( array( 'rows' => $rows ) );
	}
);

/* -------------------------------------------------------------------------
 * Shortcuts: Products list and the admin bar on product pages
 * ---------------------------------------------------------------------- */

/**
 * Link that flips a product's stock.
 *
 * @param WC_Product $product Product.
 * @return string
 */
function fleuriste_stock_toggle_url( $product ) {
	return wp_nonce_url(
		add_query_arg(
			array(
				'action' => 'fleuriste_stock_toggle',
				'id'     => $product->get_id(),
				'in'     => $product->is_in_stock() ? 0 : 1,
			),
			admin_url( 'admin-post.php' )
		),
		'fleuriste_stock_toggle_' . $product->get_id()
	);
}

add_filter(
	'post_row_actions',
	function ( $actions, $post ) {
		if ( 'product' !== $post->post_type || ! current_user_can( 'edit_product', $post->ID ) ) {
			return $actions;
		}
		$product = wc_get_product( $post->ID );
		if ( $product && $product->is_type( array( 'simple', 'variable' ) ) ) {
			$actions['fleuriste_stock'] = '<a href="' . esc_url( fleuriste_stock_toggle_url( $product ) ) . '">' . ( $product->is_in_stock() ? esc_html__( 'Mark out of stock', 'fleuriste' ) : esc_html__( 'Mark in stock', 'fleuriste' ) ) . '</a>';
		}
		return $actions;
	},
	10,
	2
);

add_action(
	'admin_bar_menu',
	function ( $bar ) {
		if ( is_admin() || ! function_exists( 'is_product' ) || ! is_product() || ! current_user_can( 'edit_products' ) ) {
			return;
		}
		$product = wc_get_product( get_queried_object_id() );
		if ( ! $product || ! $product->is_type( array( 'simple', 'variable' ) ) ) {
			return;
		}
		$bar->add_node(
			array(
				'id'    => 'fleuriste-stock',
				'title' => $product->is_in_stock() ? '<span class="ab-icon dashicons dashicons-dismiss" style="top:2px"></span>' . esc_html__( 'Mark out of stock', 'fleuriste' ) : '<span class="ab-icon dashicons dashicons-yes-alt" style="top:2px"></span>' . esc_html__( 'Mark in stock', 'fleuriste' ),
				'href'  => fleuriste_stock_toggle_url( $product ),
			)
		);
	},
	90
);

add_action(
	'admin_post_fleuriste_stock_toggle',
	function () {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		check_admin_referer( 'fleuriste_stock_toggle_' . $id );
		if ( ! current_user_can( 'edit_product', $id ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'fleuriste' ) );
		}
		$product = wc_get_product( $id );
		if ( $product ) {
			fleuriste_stock_set( $product, ! empty( $_GET['in'] ) );
		}
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=fleuriste-stock' ) );
		exit;
	}
);

// Orders already placed are not affected by "out of stock": the buyer can still pay an
// order taken earlier (payment link, or retrying a failed card payment) for a product
// that has since been marked out of stock. Only new orders are blocked.
add_filter( 'woocommerce_pay_order_product_in_stock', '__return_true' );
add_filter( 'woocommerce_pay_order_product_has_enough_stock', '__return_true' );
