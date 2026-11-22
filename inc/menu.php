<?php
/**
 * Main menu: product categories are added automatically between "All Products"
 * and "Contact Us" (subcategories open as a dropdown). A category can be left out
 * of the menu with the "Show in main menu" checkbox on Products → Categories.
 *
 * @package Fleuriste
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Categories for the menu, as a tree.
 *
 * @param int $parent Parent term ID.
 * @param int $depth  Current depth (max 2 levels).
 * @return WP_Term[]
 */
function fleuriste_menu_categories( $parent = 0, $depth = 0 ) {
	if ( ! taxonomy_exists( 'product_cat' ) || $depth > 1 ) {
		return array();
	}
	$terms = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'parent'     => $parent,
			'hide_empty' => true,
			'orderby'    => 'menu_order',
			'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
		)
	);
	if ( is_wp_error( $terms ) ) {
		return array();
	}
	return array_values(
		array_filter(
			$terms,
			function ( $term ) {
				return 'yes' !== get_term_meta( $term->term_id, 'fleuriste_hide_in_menu', true );
			}
		)
	);
}

/**
 * Menu HTML for a list of categories.
 *
 * @param WP_Term[] $terms Terms.
 * @param int       $depth Depth.
 * @return string
 */
function fleuriste_menu_items_html( $terms, $depth = 0 ) {
	$current = is_tax( 'product_cat' ) ? get_queried_object_id() : 0;
	$html    = '';
	foreach ( $terms as $term ) {
		$children  = fleuriste_menu_categories( $term->term_id, $depth + 1 );
		$is_active = $current && ( $current === $term->term_id || term_is_ancestor_of( $term, $current, 'product_cat' ) );
		$classes   = array( 'wp-block-navigation-item', 'fl-nav-cat' );
		if ( $children ) {
			$classes[] = 'has-child';
			$classes[] = 'open-on-hover-click';
			$classes[] = 'wp-block-navigation-submenu';
		} else {
			$classes[] = 'wp-block-navigation-link';
		}
		if ( $is_active ) {
			$classes[] = 'current-menu-item';
		}
		$html .= '<li class="' . esc_attr( implode( ' ', $classes ) ) . '">';
		$html .= sprintf(
			'<a class="wp-block-navigation-item__content" href="%1$s"%2$s><span class="wp-block-navigation-item__label">%3$s</span></a>',
			esc_url( get_term_link( $term ) ),
			$is_active ? ' aria-current="page"' : '',
			esc_html( $term->name )
		);
		if ( $children ) {
			$html .= '<span class="fl-nav-caret" aria-hidden="true"></span>';
			$html .= '<ul class="wp-block-navigation__submenu-container wp-block-navigation-submenu">' . fleuriste_menu_items_html( $children, $depth + 1 ) . '</ul>';
		}
		$html .= '</li>';
	}
	return $html;
}

/**
 * Insert the categories into the header navigation, just before "Contact Us".
 */
add_filter(
	'render_block_core/navigation',
	function ( $content, $block ) {
		$class = isset( $block['attrs']['className'] ) ? $block['attrs']['className'] : '';
		if ( false === strpos( $class, 'fl-nav' ) || false !== strpos( $content, 'fl-nav-cat' ) ) {
			return $content;
		}
		$items = fleuriste_menu_items_html( fleuriste_menu_categories() );
		if ( '' === $items ) {
			return $content;
		}
		// Before the "Contact Us" item, or at the end of the list if it was removed.
		if ( preg_match( '/<li[^>]*class="[^"]*fl-nav-contact[^"]*"/', $content, $m, PREG_OFFSET_CAPTURE ) ) {
			return substr_replace( $content, $items, $m[0][1], 0 );
		}
		$pos = strrpos( $content, '</ul>' );
		return false === $pos ? $content : substr_replace( $content, $items, $pos, 0 );
	},
	10,
	2
);

/* -------------------------------------------------------------------------
 * "Show in main menu" checkbox on product categories
 * ---------------------------------------------------------------------- */

add_action(
	'product_cat_add_form_fields',
	function () {
		?>
		<div class="form-field">
			<label><input type="checkbox" name="fleuriste_show_in_menu" value="1" checked> <?php esc_html_e( 'Show in main menu', 'fleuriste' ); ?></label>
			<p><?php esc_html_e( 'Main categories appear in the top menu; subcategories appear in its dropdown. Empty categories are hidden until they have products.', 'fleuriste' ); ?></p>
		</div>
		<?php
	}
);

add_action(
	'product_cat_edit_form_fields',
	function ( $term ) {
		$show = 'yes' !== get_term_meta( $term->term_id, 'fleuriste_hide_in_menu', true );
		?>
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'Main menu', 'fleuriste' ); ?></th>
			<td>
				<label><input type="checkbox" name="fleuriste_show_in_menu" value="1" <?php checked( $show ); ?>> <?php esc_html_e( 'Show in main menu', 'fleuriste' ); ?></label>
				<p class="description"><?php esc_html_e( 'Drag categories on the Categories screen to change their order in the menu.', 'fleuriste' ); ?></p>
			</td>
		</tr>
		<?php
	}
);

/**
 * Save the checkbox.
 *
 * @param int $term_id Term ID.
 */
function fleuriste_save_menu_flag( $term_id ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WordPress checks the term form nonce.
	if ( ! isset( $_POST['tag_ID'] ) && ! isset( $_POST['tag-name'] ) ) {
		return; // Not saved from the category form (e.g. quick edit or import).
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( empty( $_POST['fleuriste_show_in_menu'] ) ) {
		update_term_meta( $term_id, 'fleuriste_hide_in_menu', 'yes' );
	} else {
		delete_term_meta( $term_id, 'fleuriste_hide_in_menu' );
	}
}
add_action( 'created_product_cat', 'fleuriste_save_menu_flag' );
add_action( 'edited_product_cat', 'fleuriste_save_menu_flag' );

// "Menu" column on the categories list.
add_filter(
	'manage_edit-product_cat_columns',
	function ( $columns ) {
		$columns['fleuriste_menu'] = __( 'In menu', 'fleuriste' );
		return $columns;
	}
);
add_filter(
	'manage_product_cat_custom_column',
	function ( $out, $column, $term_id ) {
		if ( 'fleuriste_menu' === $column ) {
			$out = 'yes' === get_term_meta( $term_id, 'fleuriste_hide_in_menu', true ) ? '—' : '✓';
		}
		return $out;
	},
	10,
	3
);
