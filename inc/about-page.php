<?php
/**
 * "About us" page: starter content (editable in Pages → About us), also available
 * as the block pattern "About us page".
 *
 * @package Fleuriste
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Photos for the page: product photos from the shop, else the theme's images.
 *
 * @param int $count Number of photos.
 * @return array[] url, alt.
 */
function fleuriste_about_photos( $count ) {
	$out = array();
	if ( function_exists( 'wc_get_products' ) ) {
		$ids = wc_get_featured_product_ids();
		$products = $ids ? wc_get_products(
			array(
				'include' => $ids,
				'limit'   => $count,
				'status'  => 'publish',
			)
		) : array();
		if ( count( $products ) < $count ) {
			$products = array_merge(
				$products,
				wc_get_products(
					array(
						'limit'   => $count,
						'status'  => 'publish',
						'orderby' => 'rand',
					)
				)
			);
		}
		foreach ( $products as $p ) {
			$url = $p->get_image_id() ? wp_get_attachment_image_url( $p->get_image_id(), 'large' ) : '';
			if ( $url && ! in_array( $url, wp_list_pluck( $out, 'url' ), true ) ) {
				$out[] = array(
					'url' => $url,
					'alt' => $p->get_name(),
				);
			}
			if ( count( $out ) >= $count ) {
				break;
			}
		}
	}
	foreach ( array( 'about-florist.webp', 'hero-main.webp', 'hero-secondary.webp', 'occasion-anniversary.webp', 'occasion-birthday.webp' ) as $file ) {
		if ( count( $out ) >= $count ) {
			break;
		}
		$out[] = array(
			'url' => fleuriste_img( $file ),
			'alt' => __( 'Flowers from our shop', 'fleuriste' ),
		);
	}
	return $out;
}

/**
 * The page content (blocks).
 *
 * @return string
 */
function fleuriste_about_page_markup() {
	$name   = get_bloginfo( 'name' );
	$photos = fleuriste_about_photos( 5 );
	$img    = function ( $i, $class = '' ) use ( $photos ) {
		$p = $photos[ $i ];
		return '<!-- wp:image {"sizeSlug":"large","linkDestination":"none","className":"' . esc_attr( $class ) . '"} -->' . "\n"
			. '<figure class="wp-block-image size-large ' . esc_attr( $class ) . '"><img src="' . esc_url( $p['url'] ) . '" alt="' . esc_attr( $p['alt'] ) . '"/></figure>' . "\n"
			. '<!-- /wp:image -->';
	};
	$shop    = function_exists( 'fleuriste_shop_url' ) ? fleuriste_shop_url() : home_url( '/shop/' );
	$contact = home_url( '/contact/' );

	ob_start();
	?>
<!-- wp:group {"align":"wide","className":"fl-ab-intro","layout":{"type":"constrained","contentSize":"760px"}} -->
<div class="wp-block-group alignwide fl-ab-intro"><!-- wp:paragraph {"align":"center","className":"fl-eyebrow"} -->
<p class="has-text-align-center fl-eyebrow"><?php esc_html_e( 'Our story', 'fleuriste' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"textAlign":"center","level":2} -->
<h2 class="wp-block-heading has-text-align-center"><?php esc_html_e( 'Flowers made with love in Mauritius,', 'fleuriste' ); ?> <em><?php esc_html_e( 'since 2014', 'fleuriste' ); ?></em></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","className":"fl-ab-lead"} -->
<p class="has-text-align-center fl-ab-lead"><?php echo esc_html( sprintf( /* translators: %s: shop name */ __( '%s is a family flower shop. For more than ten years we have been creating bouquets, arrangements and tributes for the moments that matter – birthdays, anniversaries, new beginnings and heartfelt goodbyes.', 'fleuriste' ), $name ) ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:columns {"verticalAlignment":"center","align":"wide","className":"fl-ab-split"} -->
<div class="wp-block-columns alignwide are-vertically-aligned-center fl-ab-split"><!-- wp:column {"verticalAlignment":"center","width":"50%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:50%"><?php echo $img( 0, 'fl-ab-photo' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center","width":"50%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:50%"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading"><?php esc_html_e( 'How it all started', 'fleuriste' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'It began with a small stall, a love of flowers and a few loyal customers. Word spread, one bouquet at a time, and today our shop prepares flowers for families and businesses all over the island.', 'fleuriste' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'What hasn\'t changed is how we work: we choose every stem ourselves, we arrange every order by hand, and we only send out flowers we would be proud to receive.', 'fleuriste' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:group {"align":"wide","className":"fl-ab-values","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide fl-ab-values"><!-- wp:heading {"textAlign":"center","level":3} -->
<h3 class="wp-block-heading has-text-align-center"><?php esc_html_e( 'What we care about', 'fleuriste' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:columns {"className":"fl-ab-cards"} -->
<div class="wp-block-columns fl-ab-cards"><!-- wp:column {"className":"fl-ab-card"} -->
<div class="wp-block-column fl-ab-card"><!-- wp:heading {"level":4} -->
<h4 class="wp-block-heading"><?php esc_html_e( 'Fresh every morning', 'fleuriste' ); ?></h4>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'Our flowers arrive fresh and are prepared on the day of delivery, so they last longer at home.', 'fleuriste' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column {"className":"fl-ab-card"} -->
<div class="wp-block-column fl-ab-card"><!-- wp:heading {"level":4} -->
<h4 class="wp-block-heading"><?php esc_html_e( 'Made by hand', 'fleuriste' ); ?></h4>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'Every bouquet is put together by our florists – no two are exactly alike, and each one is checked before it leaves.', 'fleuriste' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column {"className":"fl-ab-card"} -->
<div class="wp-block-column fl-ab-card"><!-- wp:heading {"level":4} -->
<h4 class="wp-block-heading"><?php esc_html_e( 'Delivered with care', 'fleuriste' ); ?></h4>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'We deliver across Mauritius on the day you choose, or you can collect your order from our shop.', 'fleuriste' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->

<!-- wp:columns {"align":"wide","className":"fl-ab-gallery"} -->
<div class="wp-block-columns alignwide fl-ab-gallery"><!-- wp:column -->
<div class="wp-block-column"><?php echo $img( 1, 'fl-ab-thumb' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><?php echo $img( 2, 'fl-ab-thumb' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><?php echo $img( 3, 'fl-ab-thumb' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><?php echo $img( 4, 'fl-ab-thumb' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:group {"align":"wide","className":"fl-ab-cta","layout":{"type":"constrained","contentSize":"680px"}} -->
<div class="wp-block-group alignwide fl-ab-cta"><!-- wp:heading {"textAlign":"center","level":3} -->
<h3 class="wp-block-heading has-text-align-center"><?php esc_html_e( 'Come and see us, or order online', 'fleuriste' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center"><?php esc_html_e( 'Visit our shop to choose your flowers in person, or browse our bouquets online and we\'ll take care of the rest.', 'fleuriste' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $shop ); ?>"><?php esc_html_e( 'Shop our flowers', 'fleuriste' ); ?></a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $contact ); ?>"><?php esc_html_e( 'Contact us', 'fleuriste' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->
	<?php
	return trim( ob_get_clean() );
}

add_action(
	'init',
	function () {
		if ( is_admin() && function_exists( 'register_block_pattern' ) ) {
			register_block_pattern(
				'fleuriste/about-page',
				array(
					'title'      => __( 'About us page', 'fleuriste' ),
					'categories' => array( 'about' ),
					'content'    => fleuriste_about_page_markup(),
				)
			);
		}
	},
	20
);

// Once: fill the About page (/about/ or /about-us/) with the starter content, or
// create it. The previous content stays in the page's revisions.
add_action(
	'admin_init',
	function () {
		if ( get_option( 'fleuriste_about_v1' ) || ! current_user_can( 'edit_pages' ) || wp_doing_ajax() ) {
			return;
		}
		update_option( 'fleuriste_about_v1', time() );
		$page = get_page_by_path( 'about' );
		$page = $page ? $page : get_page_by_path( 'about-us' );
		$data = array(
			'post_title'   => __( 'About us', 'fleuriste' ),
			'post_content' => fleuriste_about_page_markup(),
			'post_status'  => 'publish',
			'post_type'    => 'page',
		);
		if ( $page ) {
			$data['ID'] = $page->ID;
			$id         = wp_update_post( wp_slash( $data ) );
		} else {
			$data['post_name'] = 'about';
			$id                = wp_insert_post( wp_slash( $data ) );
		}
		if ( $id && ! is_wp_error( $id ) ) {
			update_post_meta( $id, '_wp_page_template', 'default' );
		}

		// Footer: "Visit the studio" is now "Visit our shop".
		$site = get_option( 'fleuriste_site', array() );
		if ( is_array( $site ) && isset( $site['v']['footer.visit_head'] ) && 'Visit the studio' === $site['v']['footer.visit_head'] ) {
			unset( $site['v']['footer.visit_head'] );
			update_option( 'fleuriste_site', $site );
		}
	}
);
