<?php
/**
 * Delivery areas page and Flower care page.
 *
 * [fleuriste_delivery_info] shows the delivery areas and fees, delivery times and
 * pickup details, straight from Shop settings → Delivery & pickup, so the page is
 * always up to date. Both pages are created once (or filled in if empty).
 *
 * @package Fleuriste
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * "14:00" → "2:00 pm" in the site's time format.
 *
 * @param string $hm H:i.
 * @return string
 */
function fleuriste_ip_time( $hm ) {
	if ( ! preg_match( '/^(\d{1,2}):(\d{2})$/', trim( (string) $hm ), $m ) ) {
		return '';
	}
	$d = ( new DateTimeImmutable( 'today', wp_timezone() ) )->setTime( (int) $m[1], (int) $m[2] );
	return wp_date( get_option( 'time_format', 'g:i a' ), $d->getTimestamp() );
}

/**
 * Week day names for 1 (Monday) … 7 (Sunday).
 *
 * @param string[] $days Day numbers.
 * @return string
 */
function fleuriste_ip_days( $days ) {
	$names = array();
	foreach ( (array) $days as $n ) {
		$n = (int) $n;
		if ( $n >= 1 && $n <= 7 ) {
			$names[ $n ] = wp_date( 'l', strtotime( 'monday this week +' . ( $n - 1 ) . ' days' ) );
		}
	}
	ksort( $names );
	return implode( ', ', $names );
}

/**
 * Money, or "Free".
 *
 * @param float $amount Amount.
 * @return string HTML.
 */
function fleuriste_ip_price( $amount ) {
	$amount = (float) $amount;
	if ( $amount <= 0 ) {
		return esc_html__( 'Free', 'fleuriste' );
	}
	return function_exists( 'wc_price' ) ? wc_price( $amount ) : esc_html( number_format_i18n( $amount ) );
}

add_shortcode(
	'fleuriste_delivery_info',
	function () {
		if ( ! function_exists( 'fleuriste_dl_settings' ) ) {
			return '';
		}
		$cards = array();

		// Areas and fees.
		if ( fleuriste_dl_delivery_on() ) {
			$regions = fleuriste_dl_regions();
			usort(
				$regions,
				function ( $a, $b ) {
					return ( (float) $a['price'] <=> (float) $b['price'] ) ?: strcasecmp( $a['name'], $b['name'] );
				}
			);
			$rows = '';
			foreach ( $regions as $r ) {
				$rows .= '<tr><td>' . esc_html( $r['name'] ) . '</td><td>' . fleuriste_ip_price( $r['price'] ) . '</td></tr>';
			}
			$html = '<h3>' . esc_html__( 'Areas and fees', 'fleuriste' ) . '</h3>';
			if ( $rows ) {
				$html .= '<table class="fl-dinfo-table"><thead><tr><th>' . esc_html__( 'Region', 'fleuriste' ) . '</th><th>' . esc_html__( 'Delivery fee', 'fleuriste' ) . '</th></tr></thead><tbody>' . $rows . '</tbody></table>';
			}
			$free = (float) fleuriste_dl_get( 'free_over' );
			if ( $free > 0 ) {
				/* translators: %s: order amount */
				$html .= '<p class="fl-dinfo-free">' . sprintf( esc_html__( 'Free delivery on orders over %s.', 'fleuriste' ), fleuriste_ip_price( $free ) ) . '</p>';
			}
			$html .= '<p class="fl-dinfo-note">' . esc_html__( 'Choose your region at checkout and the fee is added automatically. Can\'t find your area? Contact us – we\'ll do our best to get your flowers there.', 'fleuriste' ) . '</p>';
			$cards[] = array( 'areas', $html );

			// Times.
			$html  = '<h3>' . esc_html__( 'Delivery times', 'fleuriste' ) . '</h3><ul class="fl-dinfo-list">';
			$slots = function_exists( 'fleuriste_fo_choices' ) ? fleuriste_fo_choices( 'fleuriste_fo_slots' ) : array();
			foreach ( $slots as $s ) {
				$extra = (float) $s['price'] > 0 ? ' <span>+ ' . fleuriste_ip_price( $s['price'] ) . '</span>' : '';
				$html .= '<li>' . esc_html( $s['label'] ) . $extra . '</li>';
			}
			$html .= '</ul>';
			$cut = fleuriste_ip_time( function_exists( 'fleuriste_fo_cutoff' ) ? fleuriste_fo_cutoff( 'delivery' ) : '' );
			if ( $cut ) {
				/* translators: %s: time */
				$html .= '<p><strong>' . esc_html__( 'Same-day delivery', 'fleuriste' ) . '</strong><br>' . sprintf( esc_html__( 'Order before %s and we can deliver today. After that, the earliest delivery is the next open day.', 'fleuriste' ), esc_html( $cut ) ) . '</p>';
			}
			$closed = fleuriste_ip_days( function_exists( 'fleuriste_fo_closed_days' ) ? fleuriste_fo_closed_days( 'delivery' ) : array() );
			if ( $closed ) {
				/* translators: %s: week days */
				$html .= '<p>' . sprintf( esc_html__( 'No deliveries on: %s.', 'fleuriste' ), esc_html( $closed ) ) . '</p>';
			}
			$html   .= '<p class="fl-dinfo-note">' . esc_html__( 'You pick the day and time at checkout. We\'ll message you when your flowers are on their way.', 'fleuriste' ) . '</p>';
			$cards[] = array( 'times', $html );
		}

		// Pickup.
		if ( fleuriste_dl_pickup_on() ) {
			$html = '<h3>' . esc_html( fleuriste_dl_get( 'pickup_title' ) ? fleuriste_dl_get( 'pickup_title' ) : __( 'Pickup from the shop', 'fleuriste' ) ) . '</h3>';
			$html .= '<p>' . esc_html__( 'Prefer to collect? Choose pickup at checkout – it\'s free.', 'fleuriste' ) . '</p>';
			$addr = fleuriste_dl_pickup_address();
			if ( $addr ) {
				$html .= '<p><strong>' . esc_html__( 'Where', 'fleuriste' ) . '</strong><br>' . esc_html( $addr ) . '</p>';
			}
			$ps = fleuriste_dl_pickup_slots();
			if ( $ps ) {
				$html .= '<p><strong>' . esc_html__( 'When', 'fleuriste' ) . '</strong></p><ul class="fl-dinfo-list">';
				foreach ( $ps as $label ) {
					$html .= '<li>' . esc_html( $label ) . '</li>';
				}
				$html .= '</ul>';
			}
			$pcut = fleuriste_ip_time( fleuriste_dl_get( 'pickup_cutoff' ) );
			if ( $pcut ) {
				/* translators: %s: time */
				$html .= '<p>' . sprintf( esc_html__( 'Order before %s to collect the same day.', 'fleuriste' ), esc_html( $pcut ) ) . '</p>';
			}
			$pclosed = fleuriste_ip_days( fleuriste_dl_get( 'pickup_closed' ) );
			if ( $pclosed ) {
				/* translators: %s: week days */
				$html .= '<p>' . sprintf( esc_html__( 'Closed for pickup on: %s.', 'fleuriste' ), esc_html( $pclosed ) ) . '</p>';
			}
			$details = trim( (string) fleuriste_dl_get( 'pickup_details' ) );
			if ( $details ) {
				$html .= '<p class="fl-dinfo-note">' . esc_html( $details ) . '</p>';
			}
			$cards[] = array( 'pickup', $html );
		}

		// Upcoming holidays.
		$today = wp_date( 'Y-m-d' );
		$hol   = array();
		foreach ( fleuriste_dl_holidays() as $d ) {
			if ( $d >= $today && count( $hol ) < 8 ) {
				$hol[] = wp_date( get_option( 'date_format', 'j F Y' ), strtotime( $d . ' 12:00' ) );
			}
		}
		$hol_html = $hol ? '<p class="fl-dinfo-holidays"><strong>' . esc_html__( 'Closed (no delivery or pickup):', 'fleuriste' ) . '</strong> ' . esc_html( implode( ' · ', $hol ) ) . '</p>' : '';

		if ( ! $cards ) {
			return '<p>' . esc_html__( 'Delivery and pickup are currently unavailable. Please contact us.', 'fleuriste' ) . '</p>';
		}
		$out = '<div class="fl-dinfo">';
		foreach ( $cards as $c ) {
			$out .= '<section class="fl-dinfo__card fl-dinfo__card--' . esc_attr( $c[0] ) . '">' . $c[1] . '</section>'; // Escaped above.
		}
		return $out . '</div>' . $hol_html;
	}
);

/**
 * Delivery areas page content.
 *
 * @return string
 */
function fleuriste_delivery_page_markup() {
	return '<!-- wp:paragraph {"align":"center","className":"fl-info-intro"} -->
<p class="has-text-align-center fl-info-intro">' . esc_html__( 'We deliver fresh flowers all over Mauritius, hand-delivered by our own team. Here\'s where we go, what it costs and when.', 'fleuriste' ) . '</p>
<!-- /wp:paragraph -->

<!-- wp:shortcode -->
[fleuriste_delivery_info]
<!-- /wp:shortcode -->

<!-- wp:group {"align":"wide","className":"fl-info-faq","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide fl-info-faq"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">' . esc_html__( 'Good to know', 'fleuriste' ) . '</h3>
<!-- /wp:heading -->

<!-- wp:list -->
<ul class="wp-block-list"><li><strong>' . esc_html__( 'Nobody home?', 'fleuriste' ) . '</strong> ' . esc_html__( 'We\'ll call the recipient, and if we can\'t reach them we\'ll leave the flowers with a neighbour or reception, or bring them back the next day.', 'fleuriste' ) . '</li><li><strong>' . esc_html__( 'Surprise deliveries', 'fleuriste' ) . '</strong> ' . esc_html__( 'We only call the recipient if we need to. Add any instructions in the order notes.', 'fleuriste' ) . '</li><li><strong>' . esc_html__( 'Hospitals and offices', 'fleuriste' ) . '</strong> ' . esc_html__( 'Please add the ward, floor or department to the address so we find them quickly.', 'fleuriste' ) . '</li><li><strong>' . esc_html__( 'Funerals', 'fleuriste' ) . '</strong> ' . esc_html__( 'Give us the place and time of the service and we\'ll deliver in good time beforehand.', 'fleuriste' ) . '</li></ul>
<!-- /wp:list --></div>
<!-- /wp:group -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"},"className":"fl-info-cta"} -->
<div class="wp-block-buttons fl-info-cta"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/shop/">' . esc_html__( 'Shop flowers', 'fleuriste' ) . '</a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="/contact/">' . esc_html__( 'Contact us', 'fleuriste' ) . '</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->';
}

/**
 * One care card.
 *
 * @param string   $title Title.
 * @param string[] $tips  Tips.
 * @return string
 */
function fleuriste_care_card( $title, $tips ) {
	$items = '';
	foreach ( $tips as $t ) {
		$items .= '<li>' . esc_html( $t ) . '</li>';
	}
	return '<!-- wp:group {"className":"fl-care__card","layout":{"type":"default"}} -->
<div class="wp-block-group fl-care__card"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">' . esc_html( $title ) . '</h3>
<!-- /wp:heading -->

<!-- wp:list -->
<ul class="wp-block-list">' . $items . '</ul>
<!-- /wp:list --></div>
<!-- /wp:group -->';
}

/**
 * Flower care page content.
 *
 * @return string
 */
function fleuriste_care_page_markup() {
	$cards = array(
		fleuriste_care_card(
			__( 'Hand-tied bouquets', 'fleuriste' ),
			array(
				__( 'Unwrap the bouquet but keep the tie in place – it holds the shape.', 'fleuriste' ),
				__( 'Cut 2 cm off each stem at an angle with a clean, sharp knife or scissors.', 'fleuriste' ),
				__( 'Remove any leaves that would sit below the water line.', 'fleuriste' ),
				__( 'Place in a clean vase two-thirds full of cool water with the flower food.', 'fleuriste' ),
			)
		),
		fleuriste_care_card(
			__( 'Every two days', 'fleuriste' ),
			array(
				__( 'Change the water completely and rinse the vase.', 'fleuriste' ),
				__( 'Re-cut the stems by about 1 cm.', 'fleuriste' ),
				__( 'Take out any flowers that are fading so the rest last longer.', 'fleuriste' ),
			)
		),
		fleuriste_care_card(
			__( 'Boxes and baskets', 'fleuriste' ),
			array(
				__( 'These are arranged in wet floral foam – no vase needed.', 'fleuriste' ),
				__( 'Top up with a little water every day so the foam never dries out.', 'fleuriste' ),
				__( 'Water slowly at the base; don\'t let it overflow the box.', 'fleuriste' ),
			)
		),
		fleuriste_care_card(
			__( 'Where to put them', 'fleuriste' ),
			array(
				__( 'Somewhere cool and bright, out of direct sun.', 'fleuriste' ),
				__( 'Away from air-conditioning vents, fans and heaters.', 'fleuriste' ),
				__( 'Away from fruit bowls – ripening fruit makes flowers age faster.', 'fleuriste' ),
			)
		),
		fleuriste_care_card(
			__( 'Roses', 'fleuriste' ),
			array(
				__( 'If a head droops, re-cut the stem and stand it in deep, cool water for an hour.', 'fleuriste' ),
				__( 'Gently remove the outer "guard" petals if they look bruised.', 'fleuriste' ),
			)
		),
		fleuriste_care_card(
			__( 'Lilies and orchids', 'fleuriste' ),
			array(
				__( 'Pull off lily pollen as the flowers open – it stains clothes and tablecloths.', 'fleuriste' ),
				__( 'Lilies are toxic to cats; keep them out of reach.', 'fleuriste' ),
				__( 'Potted orchids need only a small glass of water once a week.', 'fleuriste' ),
			)
		),
	);
	return '<!-- wp:paragraph {"align":"center","className":"fl-info-intro"} -->
<p class="has-text-align-center fl-info-intro">' . esc_html__( 'A few minutes of care keeps your flowers fresh for a week or more. In the Mauritian heat, fresh water is the secret.', 'fleuriste' ) . '</p>
<!-- /wp:paragraph -->

<!-- wp:group {"align":"wide","className":"fl-care","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide fl-care">' . implode( "\n\n", $cards ) . '</div>
<!-- /wp:group -->

<!-- wp:paragraph {"align":"center","className":"fl-info-intro"} -->
<p class="has-text-align-center fl-info-intro">' . esc_html__( 'Each product page also has care tips for that arrangement. Something not right with your flowers? Contact us within 24 hours of delivery and we\'ll make it right – that\'s our freshness guarantee.', 'fleuriste' ) . '</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"},"className":"fl-info-cta"} -->
<div class="wp-block-buttons fl-info-cta"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/contact/">' . esc_html__( 'Contact us', 'fleuriste' ) . '</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->';
}

/**
 * Create a page at a slug, or fill it in when it is empty.
 *
 * @param string   $slug    Slug.
 * @param string   $title   Title.
 * @param string   $content Content.
 * @param string[] $alts    Other slugs it may already have.
 */
function fleuriste_ip_ensure_page( $slug, $title, $content, $alts = array() ) {
	$page = null;
	foreach ( array_merge( array( $slug ), $alts ) as $s ) {
		$page = get_page_by_path( $s );
		if ( $page ) {
			break;
		}
	}
	if ( $page && '' !== trim( wp_strip_all_tags( (string) $page->post_content ) ) && 'publish' === $page->post_status ) {
		return; // The shop already wrote something – leave it.
	}
	$data = array(
		'post_title'   => $title,
		'post_content' => $content,
		'post_status'  => 'publish',
		'post_type'    => 'page',
	);
	if ( $page ) {
		$data['ID'] = $page->ID;
		wp_update_post( wp_slash( $data ) );
	} else {
		$data['post_name'] = $slug;
		wp_insert_post( wp_slash( $data ) );
	}
}

/**
 * Set up both pages once.
 */
function fleuriste_ip_setup() {
	if ( get_option( 'fleuriste_info_pages_v1' ) ) {
		return;
	}
	update_option( 'fleuriste_info_pages_v1', time() );
	fleuriste_ip_ensure_page( 'delivery', __( 'Delivery areas', 'fleuriste' ), fleuriste_delivery_page_markup(), array( 'delivery-areas', 'delivery-information' ) );
	fleuriste_ip_ensure_page( 'flower-care', __( 'Flower care', 'fleuriste' ), fleuriste_care_page_markup(), array( 'care' ) );
	flush_rewrite_rules( false );
}
add_action( 'admin_init', 'fleuriste_ip_setup' );
// Also on the front end, so the pages appear without anyone opening the admin.
add_action(
	'wp_loaded',
	function () {
		if ( ! is_admin() && ! wp_doing_ajax() && ! wp_doing_cron() ) {
			fleuriste_ip_setup();
		}
	}
);
