<?php
/**
 * "Shop by category" on the home page: built from the real product categories.
 *
 * Each tile uses the category's own picture (Products → Categories → Thumbnail),
 * or, when it has none, the photo of the first product in that category.
 * A category can be left out with "Show on the home page".
 *
 * Shortcode: [fleuriste_categories limit="8"]
 *
 * @package Fleuriste
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Picture of a category: its own thumbnail, else the first product's photo,
 * else its header image. Returns an attachment ID or 0.
 *
 * @param WP_Term $term  Category.
 * @param int[]   $avoid Photos already used by other tiles (a different product photo is picked when possible).
 * @return int
 */
function fleuriste_category_image_id( $term, $avoid = array() ) {
	$own = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
	if ( $own && wp_attachment_is_image( $own ) ) {
		return $own;
	}
	// First product in the category (as it is listed on the category page), with a photo.
	$products = wc_get_products(
		array(
			'status'   => 'publish',
			'limit'    => 10,
			'category' => array( $term->slug ),
			'orderby'  => array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			),
			'return'   => 'objects',
		)
	);
	$first = 0;
	foreach ( $products as $product ) {
		$pic = (int) $product->get_image_id();
		if ( ! $pic || ! $product->is_visible() ) {
			continue;
		}
		if ( ! in_array( $pic, $avoid, true ) ) {
			return $pic;
		}
		$first = $first ? $first : $pic;
	}
	if ( $first ) {
		return $first;
	}
	$banner = defined( 'FLEURISTE_BANNER_META' ) ? (int) get_term_meta( $term->term_id, FLEURISTE_BANNER_META, true ) : 0;
	return $banner && wp_attachment_is_image( $banner ) ? $banner : 0;
}

/**
 * Categories shown on the home page.
 *
 * @param int $limit Maximum number.
 * @return WP_Term[]
 */
function fleuriste_home_categories( $limit = 8 ) {
	if ( ! taxonomy_exists( 'product_cat' ) ) {
		return array();
	}
	$terms = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'parent'     => 0,
			'hide_empty' => true,
			'orderby'    => 'menu_order',
			'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
		)
	);
	if ( is_wp_error( $terms ) ) {
		return array();
	}
	$terms = array_values(
		array_filter(
			$terms,
			function ( $term ) {
				return 'yes' !== get_term_meta( $term->term_id, 'fleuriste_hide_on_home', true );
			}
		)
	);
	return array_slice( $terms, 0, max( 1, (int) $limit ) );
}

add_shortcode(
	'fleuriste_categories',
	function ( $atts ) {
		$atts  = shortcode_atts( array( 'limit' => 8 ), $atts, 'fleuriste_categories' );
		$terms = fleuriste_home_categories( (int) $atts['limit'] );
		if ( ! $terms ) {
			return current_user_can( 'manage_product_terms' )
				? '<p class="fl-cats__empty">' . esc_html__( 'Your product categories will appear here once they have products (Products → Categories).', 'fleuriste' ) . '</p>'
				: '';
		}
		$html = '<div class="fl-cats__row" style="--fl-cats:' . (int) min( 7, count( $terms ) ) . '">';
		$used = array();
		// Categories with their own picture first, so automatic photos avoid those.
		foreach ( $terms as $term ) {
			$own = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
			if ( $own ) {
				$used[] = $own;
			}
		}
		foreach ( $terms as $term ) {
			$link   = get_term_link( $term );
			$img    = fleuriste_category_image_id( $term, $used );
			$used[] = $img;
			$photo = $img
				? wp_get_attachment_image( $img, 'woocommerce_thumbnail', false, array( 'alt' => $term->name, 'loading' => 'lazy' ) )
				: '<img src="' . esc_url( wc_placeholder_img_src( 'woocommerce_thumbnail' ) ) . '" alt="' . esc_attr( $term->name ) . '" loading="lazy">';
			$html .= sprintf(
				'<div class="fl-cat"><figure class="fl-cat__img"><a href="%1$s">%2$s</a></figure><p class="fl-cat__name"><a href="%1$s">%3$s</a></p></div>',
				esc_url( is_wp_error( $link ) ? '#' : $link ),
				$photo, // Built by WordPress.
				esc_html( $term->name )
			);
		}
		return $html . '</div>';
	}
);

/* -------------------------------------------------------------------------
 * Category screen: "Show on the home page" and a note about the picture
 * ---------------------------------------------------------------------- */

add_action(
	'product_cat_add_form_fields',
	function () {
		?>
		<div class="form-field">
			<label><input type="checkbox" name="fleuriste_show_on_home" value="1" checked> <?php esc_html_e( 'Show on the home page ("Shop by category")', 'fleuriste' ); ?></label>
			<p><?php esc_html_e( 'Uses the Thumbnail picture above. Without one, the photo of the first product in the category is used.', 'fleuriste' ); ?></p>
		</div>
		<?php
	},
	20
);

add_action(
	'product_cat_edit_form_fields',
	function ( $term ) {
		$show = 'yes' !== get_term_meta( $term->term_id, 'fleuriste_hide_on_home', true );
		$img  = fleuriste_category_image_id( $term );
		$own  = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
		?>
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'Home page', 'fleuriste' ); ?></th>
			<td>
				<label><input type="checkbox" name="fleuriste_show_on_home" value="1" <?php checked( $show ); ?>> <?php esc_html_e( 'Show on the home page ("Shop by category")', 'fleuriste' ); ?></label>
				<p class="description">
					<?php
					if ( $own ) {
						esc_html_e( 'The tile uses the Thumbnail picture above.', 'fleuriste' );
					} elseif ( $img ) {
						esc_html_e( 'No Thumbnail picture: the tile uses the photo of the first product in this category. Choose a Thumbnail above to use your own.', 'fleuriste' );
					} else {
						esc_html_e( 'No picture yet: choose a Thumbnail above, or add a product with a photo to this category.', 'fleuriste' );
					}
					?>
				</p>
				<?php if ( $img && ! $own ) : ?>
					<p><?php echo wp_get_attachment_image( $img, array( 80, 80 ), false, array( 'style' => 'border-radius:50%;width:80px;height:80px;object-fit:cover' ) ); ?></p>
				<?php endif; ?>
				<p class="description"><?php esc_html_e( 'Only main categories (no parent) appear on the home page, in the same order as the Categories screen.', 'fleuriste' ); ?></p>
			</td>
		</tr>
		<?php
	},
	20
);

/**
 * Save the home page checkbox.
 *
 * @param int $term_id Term ID.
 */
function fleuriste_save_home_flag( $term_id ) {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- WordPress checks the term form nonce.
	if ( ! isset( $_POST['tag_ID'] ) && ! isset( $_POST['tag-name'] ) ) {
		return;
	}
	if ( empty( $_POST['fleuriste_show_on_home'] ) ) {
		update_term_meta( $term_id, 'fleuriste_hide_on_home', 'yes' );
	} else {
		delete_term_meta( $term_id, 'fleuriste_hide_on_home' );
	}
	// phpcs:enable
}
add_action( 'created_product_cat', 'fleuriste_save_home_flag' );
add_action( 'edited_product_cat', 'fleuriste_save_home_flag' );

// Categories list: show the picture the home page uses (also when it comes from a product).
add_filter(
	'manage_product_cat_custom_column',
	function ( $columns, $column, $term_id ) {
		if ( 'thumb' !== $column || get_term_meta( $term_id, 'thumbnail_id', true ) ) {
			return $columns;
		}
		$term = get_term( $term_id, 'product_cat' );
		$img  = ( $term && ! is_wp_error( $term ) ) ? fleuriste_category_image_id( $term ) : 0;
		if ( $img ) {
			$url = wp_get_attachment_image_url( $img, 'thumbnail' );
			// Replace WooCommerce's placeholder with the product photo (slightly faded = automatic).
			$columns = '<img src="' . esc_url( $url ) . '" alt="' . esc_attr__( 'From the first product', 'fleuriste' ) . '" title="' . esc_attr__( 'From the first product', 'fleuriste' ) . '" class="wp-post-image" height="48" width="48" style="opacity:.75">';
		}
		return $columns;
	},
	20,
	3
);
