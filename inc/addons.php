<?php
/**
 * Add-on products (chocolates, teddy bear, balloon…) offered as tick-boxes on the
 * product page, and the "Frequently bought together" section.
 *
 * Add-ons are real products of the shop, so they have their own price, stock and
 * sales figures. They are added to the cart as separate lines next to the bouquet.
 *
 * @package Fleuriste
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * Settings (Shop settings → Product options)
 * ---------------------------------------------------------------------- */

/**
 * Simple products, for the settings dropdown.
 *
 * @return array id => name
 */
function fleuriste_addon_product_options() {
	$options = array();
	foreach ( wc_get_products(
		array(
			'status'  => 'publish',
			'type'    => 'simple',
			'limit'   => 500,
			'orderby' => 'title',
			'order'   => 'ASC',
		)
	) as $p ) {
		$options[ $p->get_id() ] = wp_strip_all_tags( $p->get_name() . ' – ' . wc_price( $p->get_price() ) );
	}
	return $options;
}

add_filter(
	'woocommerce_get_settings_products',
	function ( $settings, $section ) {
		if ( 'fleuriste' !== $section ) {
			return $settings;
		}
		$settings[] = array(
			'title' => __( 'Add-ons', 'fleuriste' ),
			'type'  => 'title',
			'desc'  => __( 'Add-ons are products from your shop (chocolates, a teddy bear, a balloon…) shown as tick-boxes above the Add to cart button. They are added to the cart as their own line, with their own price and stock. A product can choose its own add-ons, or none, in Product data → Linked products.', 'fleuriste' ),
			'id'    => 'fleuriste_addons',
		);
		$settings[] = array(
			'title'    => __( 'Default add-ons', 'fleuriste' ),
			'id'       => 'fleuriste_addons_default',
			'type'     => 'multiselect',
			'class'    => 'wc-enhanced-select',
			'options'  => fleuriste_addon_product_options(),
			'default'  => array(),
			'desc_tip' => __( 'Leave empty if you only want add-ons on products where you choose them.', 'fleuriste' ),
			'css'      => 'width:420px;',
		);
		$settings[] = array(
			'title'   => __( 'Show default add-ons on', 'fleuriste' ),
			'id'      => 'fleuriste_addons_where',
			'type'    => 'select',
			'default' => 'custom',
			'options' => array(
				'all'    => __( 'All products', 'fleuriste' ),
				'custom' => __( 'Customisable products only', 'fleuriste' ),
			),
		);
		$settings[] = array(
			'title'   => __( 'Add-ons heading', 'fleuriste' ),
			'id'      => 'fleuriste_addons_title',
			'type'    => 'text',
			'default' => __( 'Add a gift', 'fleuriste' ),
			'css'     => 'width:420px;',
		);
		$settings[] = array(
			'type' => 'sectionend',
			'id'   => 'fleuriste_addons',
		);

		$settings[] = array(
			'title' => __( 'Frequently bought together', 'fleuriste' ),
			'type'  => 'title',
			'desc'  => __( 'A row of products under the product details. Choose them yourself per product in Product data → Linked products, or let the shop pick them from what customers have bought together in past orders.', 'fleuriste' ),
			'id'    => 'fleuriste_fbt',
		);
		$settings[] = array(
			'title'   => __( 'Show', 'fleuriste' ),
			'id'      => 'fleuriste_fbt_mode',
			'type'    => 'select',
			'default' => 'auto',
			'options' => array(
				'auto'   => __( 'Your choice per product, otherwise picked automatically', 'fleuriste' ),
				'manual' => __( 'Only on products where you choose them', 'fleuriste' ),
				'off'    => __( 'Don\'t show', 'fleuriste' ),
			),
		);
		$settings[] = array(
			'title'   => __( 'Heading', 'fleuriste' ),
			'id'      => 'fleuriste_fbt_title',
			'type'    => 'text',
			'default' => __( 'Frequently bought together', 'fleuriste' ),
			'css'     => 'width:420px;',
		);
		$settings[] = array(
			'title'             => __( 'How many products', 'fleuriste' ),
			'id'                => 'fleuriste_fbt_count',
			'type'              => 'number',
			'default'           => 4,
			'custom_attributes' => array(
				'min'  => 1,
				'max'  => 8,
				'step' => 1,
			),
			'css'               => 'width:90px;',
		);
		$settings[] = array(
			'type' => 'sectionend',
			'id'   => 'fleuriste_fbt',
		);
		return $settings;
	},
	20,
	2
);

/* -------------------------------------------------------------------------
 * Per product (Product data → Linked products)
 * ---------------------------------------------------------------------- */

/**
 * A product search box like WooCommerce's Upsells field.
 *
 * @param string $name  Field name.
 * @param string $label Label.
 * @param int[]  $ids   Selected products.
 * @param string $help  Help text.
 */
function fleuriste_product_search_field( $name, $label, $ids, $help ) {
	global $post;
	?>
	<p class="form-field">
		<label for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $label ); ?></label>
		<select class="wc-product-search" multiple="multiple" style="width: 50%;" id="<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>[]" data-placeholder="<?php esc_attr_e( 'Search for a product…', 'fleuriste' ); ?>" data-action="woocommerce_json_search_products" data-exclude="<?php echo (int) $post->ID; ?>">
			<?php
			foreach ( $ids as $id ) {
				$p = wc_get_product( $id );
				if ( $p ) {
					echo '<option value="' . (int) $id . '" selected="selected">' . esc_html( wp_strip_all_tags( $p->get_formatted_name() ) ) . '</option>';
				}
			}
			?>
		</select> <?php echo wc_help_tip( $help ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</p>
	<?php
}

add_action(
	'woocommerce_product_options_related',
	function () {
		global $post;
		$product = wc_get_product( $post->ID );
		echo '<div class="options_group">';
		fleuriste_product_search_field(
			'_fleuriste_addons',
			__( 'Add-ons', 'fleuriste' ),
			array_filter( array_map( 'absint', (array) $product->get_meta( '_fleuriste_addons' ) ) ),
			__( 'Tick-box gifts shown above Add to cart (simple products only). Leave empty to use the default add-ons from Shop settings → Product options.', 'fleuriste' )
		);
		woocommerce_wp_checkbox(
			array(
				'id'          => '_fleuriste_addons_off',
				'label'       => __( 'No add-ons', 'fleuriste' ),
				'description' => __( 'Don\'t offer add-ons on this product', 'fleuriste' ),
			)
		);
		fleuriste_product_search_field(
			'_fleuriste_fbt',
			__( 'Frequently bought together', 'fleuriste' ),
			array_filter( array_map( 'absint', (array) $product->get_meta( '_fleuriste_fbt' ) ) ),
			__( 'Shown in a row under the product details. Leave empty and the shop picks products customers often buy with this one.', 'fleuriste' )
		);
		echo '</div>';
	}
);

add_action(
	'woocommerce_admin_process_product_object',
	function ( $product ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce checks the nonce before this hook.
		foreach ( array( '_fleuriste_addons', '_fleuriste_fbt' ) as $key ) {
			$ids = isset( $_POST[ $key ] ) ? array_values( array_filter( array_map( 'absint', (array) wp_unslash( $_POST[ $key ] ) ) ) ) : array();
			$product->update_meta_data( $key, array_diff( $ids, array( $product->get_id() ) ) );
		}
		$product->update_meta_data( '_fleuriste_addons_off', isset( $_POST['_fleuriste_addons_off'] ) ? 'yes' : 'no' );
		// phpcs:enable
	}
);

/* -------------------------------------------------------------------------
 * Add-ons on the product page
 * ---------------------------------------------------------------------- */

/**
 * Add-on products offered with a product (only ones that can be bought right now).
 *
 * @param WC_Product|int $product Product (a variation counts as its parent).
 * @return WC_Product[]
 */
function fleuriste_addons_for( $product ) {
	$product = wc_get_product( $product );
	if ( ! $product ) {
		return array();
	}
	$parent = $product->get_parent_id() ? wc_get_product( $product->get_parent_id() ) : $product;
	if ( 'yes' === $parent->get_meta( '_fleuriste_addons_off' ) ) {
		return array();
	}
	$ids = array_filter( array_map( 'absint', (array) $parent->get_meta( '_fleuriste_addons' ) ) );
	if ( ! $ids ) {
		$where = get_option( 'fleuriste_addons_where', 'custom' );
		if ( 'all' === $where || ( function_exists( 'fleuriste_fo_customisable' ) && fleuriste_fo_customisable( $parent ) ) ) {
			$ids = array_filter( array_map( 'absint', (array) get_option( 'fleuriste_addons_default', array() ) ) );
		}
	}
	$list = array();
	foreach ( array_unique( $ids ) as $id ) {
		if ( $id === $parent->get_id() ) {
			continue;
		}
		$p = wc_get_product( $id );
		if ( $p && $p->is_type( 'simple' ) && $p->is_purchasable() && $p->is_in_stock() && 'publish' === $p->get_status() ) {
			$list[ $id ] = $p;
		}
	}
	return apply_filters( 'fleuriste_addons_for', $list, $parent );
}

/**
 * Print the add-on tick-boxes.
 *
 * @param WC_Product $product Product.
 */
function fleuriste_addons_render( $product ) {
	$addons = fleuriste_addons_for( $product );
	if ( ! $addons ) {
		return;
	}
	$title = trim( (string) get_option( 'fleuriste_addons_title', '' ) );
	$title = '' !== $title ? $title : __( 'Add a gift', 'fleuriste' );
	echo '<fieldset class="fl-opt-group fl-opt-group--extras fl-opt-group--addons"><legend class="fl-opt-group__title">' . esc_html( $title ) . ' <small>' . esc_html__( '(optional)', 'fleuriste' ) . '</small></legend><div class="fl-opt-grid fl-addons">';
	foreach ( $addons as $id => $a ) {
		$price = wc_get_price_to_display( $a );
		printf(
			'<label class="fl-opt fl-addon"><input type="checkbox" name="fleuriste_addons[]" value="%1$d" data-addon-price="%2$s"><span class="fl-opt__card">%3$s<span class="fl-opt__name">%4$s</span><em class="fl-opt__price">+%5$s</em></span></label>',
			(int) $id,
			esc_attr( $price ),
			wp_kses_post( $a->get_image( 'woocommerce_gallery_thumbnail', array( 'class' => 'fl-addon__img' ) ) ),
			esc_html( $a->get_name() ),
			esc_html( fleuriste_fo_money( $price ) )
		);
	}
	echo '</div></fieldset>';
}

// Inside the florist options box (just above the total)…
add_action( 'fleuriste_before_total', 'fleuriste_addons_render' );
// …or on its own for products without florist options.
add_action(
	'woocommerce_before_add_to_cart_button',
	function () {
		global $product;
		if ( $product && ! ( function_exists( 'fleuriste_fo_active' ) && fleuriste_fo_active( $product ) ) ) {
			echo '<div class="fl-options fl-options--addons-only">';
			fleuriste_addons_render( $product );
			echo '</div>';
		}
	},
	6
);

/* -------------------------------------------------------------------------
 * Add-ons in the cart
 * ---------------------------------------------------------------------- */

/**
 * Set while add-ons are being added, so the bouquet's options aren't copied onto them.
 *
 * @var bool
 */
$GLOBALS['fleuriste_adding_addon'] = false;

add_action(
	'woocommerce_add_to_cart',
	function ( $cart_item_key, $product_id, $quantity, $variation_id ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Add to cart forms have no nonce in WooCommerce.
		if ( ! empty( $GLOBALS['fleuriste_adding_addon'] ) || empty( $_POST['fleuriste_addons'] ) || ! is_array( $_POST['fleuriste_addons'] ) ) {
			return;
		}
		$chosen = array_unique( array_map( 'absint', wp_unslash( $_POST['fleuriste_addons'] ) ) );
		unset( $_POST['fleuriste_addons'] ); // Only once per form.
		// phpcs:enable
		$allowed = fleuriste_addons_for( $variation_id ? $variation_id : $product_id );
		$parent  = wc_get_product( $product_id );
		$GLOBALS['fleuriste_adding_addon'] = true;
		foreach ( $chosen as $id ) {
			if ( isset( $allowed[ $id ] ) ) {
				WC()->cart->add_to_cart(
					$id,
					1,
					0,
					array(),
					array(
						'fleuriste_addon_for' => $parent ? $parent->get_name() : '',
						'fleuriste_addon_key' => $cart_item_key,
					)
				);
			}
		}
		$GLOBALS['fleuriste_adding_addon'] = false;
	},
	10,
	4
);

// Don't copy the bouquet's delivery options, card message or price extras onto an add-on.
add_filter(
	'woocommerce_add_cart_item_data',
	function ( $data ) {
		if ( ! empty( $GLOBALS['fleuriste_adding_addon'] ) ) {
			unset( $data['fleuriste'], $data['fleuriste_k'] );
		}
		return $data;
	},
	50
);

add_filter(
	'woocommerce_get_cart_item_from_session',
	function ( $item, $values ) {
		foreach ( array( 'fleuriste_addon_for', 'fleuriste_addon_key' ) as $k ) {
			if ( isset( $values[ $k ] ) ) {
				$item[ $k ] = $values[ $k ];
			}
		}
		return $item;
	},
	20,
	2
);

add_filter(
	'woocommerce_get_item_data',
	function ( $item_data, $cart_item ) {
		if ( ! empty( $cart_item['fleuriste_addon_for'] ) ) {
			$item_data[] = array(
				'key'   => __( 'Add-on for', 'fleuriste' ),
				'value' => $cart_item['fleuriste_addon_for'],
			);
		}
		return $item_data;
	},
	10,
	2
);

add_action(
	'woocommerce_checkout_create_order_line_item',
	function ( $item, $cart_item_key, $values ) {
		if ( ! empty( $values['fleuriste_addon_for'] ) ) {
			$item->add_meta_data( __( 'Add-on for', 'fleuriste' ), $values['fleuriste_addon_for'], true );
		}
	},
	10,
	3
);

// Removing a bouquet from the cart also removes the add-ons that came with it.
add_action(
	'woocommerce_cart_item_removed',
	function ( $removed_key, $cart ) {
		foreach ( $cart->get_cart() as $key => $item ) {
			if ( isset( $item['fleuriste_addon_key'] ) && $item['fleuriste_addon_key'] === $removed_key ) {
				$cart->remove_cart_item( $key );
			}
		}
	},
	10,
	2
);

/* -------------------------------------------------------------------------
 * Frequently bought together
 * ---------------------------------------------------------------------- */

/**
 * How often each pair of products was bought in the same order (recent orders).
 * Cached for six hours.
 *
 * @return array product_id => [ other_id => count ]
 */
function fleuriste_fbt_matrix() {
	$matrix = get_transient( 'fleuriste_fbt_matrix' );
	if ( is_array( $matrix ) ) {
		return $matrix;
	}
	$matrix = array();
	$orders = wc_get_orders(
		array(
			'limit'   => 500,
			'orderby' => 'date',
			'order'   => 'DESC',
			'type'    => 'shop_order',
			'status'  => array( 'wc-processing', 'wc-completed', 'wc-on-hold', 'wc-preparing', 'wc-out-for-delivery' ),
		)
	);
	foreach ( $orders as $order ) {
		$ids = array();
		foreach ( $order->get_items() as $item ) {
			$ids[] = (int) $item->get_product_id();
		}
		$ids = array_values( array_unique( array_filter( $ids ) ) );
		foreach ( $ids as $a ) {
			foreach ( $ids as $b ) {
				if ( $a !== $b ) {
					$matrix[ $a ][ $b ] = isset( $matrix[ $a ][ $b ] ) ? $matrix[ $a ][ $b ] + 1 : 1;
				}
			}
		}
	}
	set_transient( 'fleuriste_fbt_matrix', $matrix, 6 * HOUR_IN_SECONDS );
	return $matrix;
}

/**
 * Products to show as "Frequently bought together".
 *
 * Your own choice first; otherwise products most often in the same orders, then
 * WooCommerce cross-sells, then best sellers from the same category.
 *
 * @param WC_Product $product Product.
 * @return int[]
 */
function fleuriste_fbt_ids( $product ) {
	$mode  = get_option( 'fleuriste_fbt_mode', 'auto' );
	$count = max( 1, min( 8, (int) get_option( 'fleuriste_fbt_count', 4 ) ) );
	if ( 'off' === $mode ) {
		return array();
	}
	$self   = $product->get_id();
	$usable = function ( $id ) use ( $self ) {
		$p = wc_get_product( $id );
		return $p && $id !== $self && 'publish' === $p->get_status() && $p->is_visible() && $p->is_in_stock() && $p->is_purchasable();
	};
	$manual = array_values( array_filter( array_map( 'absint', (array) $product->get_meta( '_fleuriste_fbt' ) ), $usable ) );
	if ( $manual || 'manual' === $mode ) {
		return array_slice( $manual, 0, $count );
	}

	$ids    = array();
	$matrix = fleuriste_fbt_matrix();
	if ( ! empty( $matrix[ $self ] ) ) {
		$pairs = $matrix[ $self ];
		arsort( $pairs );
		$ids = array_keys( $pairs );
	}
	$ids = array_merge( $ids, $product->get_cross_sell_ids() );
	$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ), $usable ) ) );
	if ( count( $ids ) < $count ) {
		$cats = wc_get_product_term_ids( $self, 'product_cat' );
		if ( $cats ) {
			$more = wc_get_products(
				array(
					'status'       => 'publish',
					'limit'        => $count * 3,
					'orderby'      => 'popularity',
					'stock_status' => 'instock',
					'visibility'   => 'catalog',
					'exclude'      => array_merge( array( $self ), $ids ),
					'category'     => array_filter(
						array_map(
							function ( $id ) {
								$t = get_term( $id, 'product_cat' );
								return $t && ! is_wp_error( $t ) ? $t->slug : '';
							},
							$cats
						)
					),
					'return'       => 'ids',
				)
			);
			$ids = array_values( array_unique( array_merge( $ids, array_filter( $more, $usable ) ) ) );
		}
	}
	return array_slice( $ids, 0, $count );
}

add_action(
	'woocommerce_after_single_product_summary',
	function () {
		global $product;
		if ( ! $product ) {
			return;
		}
		$ids = fleuriste_fbt_ids( $product );
		if ( ! $ids ) {
			return;
		}
		$GLOBALS['fleuriste_fbt_shown'] = $ids;
		$title = trim( (string) get_option( 'fleuriste_fbt_title', '' ) );
		$title = '' !== $title ? $title : __( 'Frequently bought together', 'fleuriste' );
		$main  = $product;
		echo '<section class="related products fl-fbt"><h2>' . esc_html( $title ) . '</h2>';
		wc_set_loop_prop( 'name', 'fleuriste-fbt' );
		wc_set_loop_prop( 'columns', min( 4, count( $ids ) ) > 2 ? 4 : max( 2, count( $ids ) ) );
		woocommerce_product_loop_start();
		foreach ( $ids as $id ) {
			$post_object = get_post( $id );
			setup_postdata( $GLOBALS['post'] = $post_object ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited, Squiz.PHP.DisallowMultipleAssignments.Found
			wc_get_template_part( 'content', 'product' );
		}
		woocommerce_product_loop_end();
		wp_reset_postdata();
		$GLOBALS['product'] = $main; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		wc_reset_loop();
		echo '</section>';
	},
	15
);

// Don't repeat those products in "Related products".
add_filter(
	'woocommerce_related_products',
	function ( $related ) {
		if ( empty( $GLOBALS['fleuriste_fbt_shown'] ) ) {
			return $related;
		}
		return array_values( array_diff( $related, $GLOBALS['fleuriste_fbt_shown'] ) );
	}
);

// Fresh numbers after each new order are not needed instantly; clear the cache when orders complete.
add_action(
	'woocommerce_order_status_completed',
	function () {
		delete_transient( 'fleuriste_fbt_matrix' );
	}
);
