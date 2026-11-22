<?php
/**
 * Occasions: product tags Birthday, Anniversary & Love, Sympathy and Get Well Soon.
 *
 * Tick them on a product (Products → Edit → Product tags → "Choose from the most
 * used tags"). The "Shop by occasion" tiles on the home page open the tag's page,
 * which lists every product with that occasion, whatever its category.
 *
 * @package Fleuriste
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The occasion tags: slug => name.
 *
 * @return array
 */
function fleuriste_occasion_tags() {
	return array(
		'birthday'         => __( 'Birthday', 'fleuriste' ),
		'anniversary-love' => __( 'Anniversary & Love', 'fleuriste' ),
		'sympathy'         => __( 'Sympathy', 'fleuriste' ),
		'get-well-soon'    => __( 'Get Well Soon', 'fleuriste' ),
	);
}

/**
 * Create the occasion tags if they are missing. Returns slug => term ID.
 *
 * @return int[]
 */
function fleuriste_ensure_occasion_tags() {
	$ids = array();
	foreach ( fleuriste_occasion_tags() as $slug => $name ) {
		$term = get_term_by( 'slug', $slug, 'product_tag' );
		if ( ! $term ) {
			$made = wp_insert_term( $name, 'product_tag', array( 'slug' => $slug ) );
			$ids[ $slug ] = is_wp_error( $made ) ? 0 : (int) $made['term_id'];
		} else {
			$ids[ $slug ] = (int) $term->term_id;
		}
	}
	return $ids;
}

// Once: create the tags, tag funeral products "Sympathy", and point the
// home page occasion tiles at the tags.
add_action(
	'admin_init',
	function () {
		if ( get_option( 'fleuriste_occasions_v1' ) || ! current_user_can( 'manage_woocommerce' ) || ! taxonomy_exists( 'product_tag' ) ) {
			return;
		}
		$ids = fleuriste_ensure_occasion_tags();

		$funeral = array();
		foreach ( array( 'funeral', 'funerals', 'sympathy' ) as $slug ) {
			if ( term_exists( $slug, 'product_cat' ) ) {
				$funeral[] = $slug;
			}
		}
		if ( $funeral && $ids['sympathy'] ) {
			$products = wc_get_products(
				array(
					'status'   => array( 'publish', 'draft', 'private' ),
					'limit'    => -1,
					'category' => $funeral,
					'return'   => 'ids',
				)
			);
			foreach ( $products as $pid ) {
				wp_set_object_terms( $pid, array( $ids['sympathy'] ), 'product_tag', true );
			}
		}

		// Saved occasion tiles that still point to the old example categories.
		$site = get_option( 'fleuriste_site', array() );
		if ( is_array( $site ) && ! empty( $site['v'] ) && is_array( $site['v'] ) ) {
			$map = array(
				'cat:birthday'    => 'tag:birthday',
				'cat:anniversary' => 'tag:anniversary-love',
				'cat:sympathy'    => 'tag:sympathy',
				'cat:get-well'    => 'tag:get-well-soon',
			);
			foreach ( $site['v'] as $k => $val ) {
				if ( 0 === strpos( $k, 'occasions.' ) && is_string( $val ) && isset( $map[ $val ] ) && ! term_exists( substr( $val, 4 ), 'product_cat' ) ) {
					$site['v'][ $k ] = $map[ $val ];
				}
			}
			update_option( 'fleuriste_site', $site );
		}
		update_option( 'fleuriste_occasions_v1', time() );
	}
);
