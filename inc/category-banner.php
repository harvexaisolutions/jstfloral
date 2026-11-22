<?php
/**
 * Header images for shop pages:
 * - each product category: "Header image" on Products → Categories (edit a category);
 * - All Products: the Shop page's featured image (Pages → Shop → Featured image).
 *
 * @package Fleuriste
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FLEURISTE_BANNER_META = 'fleuriste_header_image';

/* -------------------------------------------------------------------------
 * Admin: image picker on the category screens
 * ---------------------------------------------------------------------- */

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || 'product_cat' !== $screen->taxonomy ) {
			return;
		}
		wp_enqueue_media();
		wp_add_inline_script(
			'media-editor',
			'jQuery( function ( $ ) {
				$( document ).on( "click", ".fl-banner-pick", function ( e ) {
					e.preventDefault();
					var box = $( this ).closest( ".fl-banner-field" );
					var frame = wp.media( { title: "' . esc_js( __( 'Choose a header image', 'fleuriste' ) ) . '", button: { text: "' . esc_js( __( 'Use this image', 'fleuriste' ) ) . '" }, library: { type: "image" }, multiple: false } );
					frame.on( "select", function () {
						var img = frame.state().get( "selection" ).first().toJSON();
						var url = ( img.sizes && img.sizes.medium_large ) ? img.sizes.medium_large.url : img.url;
						box.find( "input" ).val( img.id );
						box.find( ".fl-banner-preview" ).html( "<img src=\"" + url + "\" alt=\"\">" );
						box.find( ".fl-banner-remove" ).show();
					} );
					frame.open();
				} );
				$( document ).on( "click", ".fl-banner-remove", function ( e ) {
					e.preventDefault();
					var box = $( this ).closest( ".fl-banner-field" );
					box.find( "input" ).val( "" );
					box.find( ".fl-banner-preview" ).empty();
					$( this ).hide();
				} );
				// Clear the picker after a category is added with Ajax.
				$( document ).ajaxComplete( function ( event, xhr, settings ) {
					if ( settings.data && settings.data.indexOf( "action=add-tag" ) !== -1 && xhr.responseText.indexOf( "wp_error" ) === -1 ) {
						$( ".fl-banner-field input" ).val( "" );
						$( ".fl-banner-preview" ).empty();
						$( ".fl-banner-remove" ).hide();
					}
				} );
			} );'
		);
		wp_add_inline_style( 'media-views', '.fl-banner-preview img{display:block;max-width:420px;width:100%;height:120px;object-fit:cover;border-radius:6px;margin:6px 0}.fl-banner-field .button{margin-right:6px}' );
	}
);

/**
 * Picker markup.
 *
 * @param int $image_id Attachment ID.
 */
function fleuriste_banner_field( $image_id ) {
	$url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium_large' ) : '';
	?>
	<div class="fl-banner-field">
		<input type="hidden" name="fleuriste_header_image" value="<?php echo esc_attr( $image_id ? $image_id : '' ); ?>">
		<div class="fl-banner-preview"><?php echo $url ? '<img src="' . esc_url( $url ) . '" alt="">' : ''; ?></div>
		<button type="button" class="button fl-banner-pick"><?php esc_html_e( 'Choose image', 'fleuriste' ); ?></button>
		<button type="button" class="button-link button-link-delete fl-banner-remove" <?php echo $url ? '' : 'style="display:none"'; ?>><?php esc_html_e( 'Remove', 'fleuriste' ); ?></button>
		<p class="description"><?php esc_html_e( 'A wide photo shown behind the category name at the top of its page. About 1800 × 600 pixels works well.', 'fleuriste' ); ?></p>
	</div>
	<?php
}

add_action(
	'product_cat_add_form_fields',
	function () {
		echo '<div class="form-field"><label>' . esc_html__( 'Header image', 'fleuriste' ) . '</label>';
		fleuriste_banner_field( 0 );
		echo '</div>';
	},
	20
);

add_action(
	'product_cat_edit_form_fields',
	function ( $term ) {
		echo '<tr class="form-field"><th scope="row">' . esc_html__( 'Header image', 'fleuriste' ) . '</th><td>';
		fleuriste_banner_field( (int) get_term_meta( $term->term_id, FLEURISTE_BANNER_META, true ) );
		echo '</td></tr>';
	},
	20
);

/**
 * Save the picked image.
 *
 * @param int $term_id Term ID.
 */
function fleuriste_banner_save( $term_id ) {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- WordPress checks the term form nonce.
	if ( ! isset( $_POST['fleuriste_header_image'] ) ) {
		return;
	}
	$id = absint( $_POST['fleuriste_header_image'] );
	// phpcs:enable
	if ( $id && wp_attachment_is_image( $id ) ) {
		update_term_meta( $term_id, FLEURISTE_BANNER_META, $id );
	} else {
		delete_term_meta( $term_id, FLEURISTE_BANNER_META );
	}
}
add_action( 'created_product_cat', 'fleuriste_banner_save' );
add_action( 'edited_product_cat', 'fleuriste_banner_save' );

// A hint on the Shop page editor.
add_filter(
	'admin_post_thumbnail_html',
	function ( $html, $post_id ) {
		if ( function_exists( 'wc_get_page_id' ) && (int) $post_id === (int) wc_get_page_id( 'shop' ) ) {
			$html .= '<p class="description">' . esc_html__( 'Shown as the header image on the All Products page.', 'fleuriste' ) . '</p>';
		}
		return $html;
	},
	10,
	2
);

/* -------------------------------------------------------------------------
 * Front end
 * ---------------------------------------------------------------------- */

/**
 * Header image for the current shop page, if any.
 *
 * @return int Attachment ID or 0.
 */
function fleuriste_current_banner_id() {
	if ( is_product_category() ) {
		$term = get_queried_object();
		$id   = (int) get_term_meta( $term->term_id, FLEURISTE_BANNER_META, true );
		// Subcategories without their own image use their parent's.
		while ( ! $id && $term && $term->parent ) {
			$term = get_term( $term->parent, 'product_cat' );
			$id   = ( $term && ! is_wp_error( $term ) ) ? (int) get_term_meta( $term->term_id, FLEURISTE_BANNER_META, true ) : 0;
		}
		return $id;
	}
	if ( is_shop() && ! is_search() ) {
		return (int) get_post_thumbnail_id( wc_get_page_id( 'shop' ) );
	}
	return 0;
}

add_filter(
	'body_class',
	function ( $classes ) {
		if ( ! function_exists( 'is_product_category' ) ) {
			return $classes;
		}
		if ( fleuriste_current_banner_id() ) {
			$classes[] = 'fl-has-banner';
		} elseif ( is_shop() || is_product_taxonomy() ) {
			$classes[] = 'fl-no-banner'; // No header image: just a compact title, no banner.
		}
		return $classes;
	}
);

add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! function_exists( 'is_product_category' ) ) {
			return;
		}
		$id = fleuriste_current_banner_id();
		if ( ! $id ) {
			return;
		}
		$large = wp_get_attachment_image_url( $id, 'full' );
		$small = wp_get_attachment_image_url( $id, 'large' );
		if ( ! $large ) {
			return;
		}
		$css  = '.fl-has-banner .woocommerce-products-header{--fl-banner:url("' . esc_url_raw( $large ) . '")}';
		$css .= '@media (max-width:768px){.fl-has-banner .woocommerce-products-header{--fl-banner:url("' . esc_url_raw( $small ? $small : $large ) . '")}}';
		wp_add_inline_style( 'fleuriste-woocommerce', $css );
	},
	30
);
