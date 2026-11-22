<?php
/**
 * Website sections: one admin page (Shop settings → Website) to show, hide,
 * reorder and edit the home page sections, the product page extras and the footer.
 *
 * Patterns read their texts, links and pictures through fleuriste_s*() helpers,
 * so changes appear straight away without using the Site Editor.
 *
 * @package Fleuriste
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * What can be edited
 * ---------------------------------------------------------------------- */

/**
 * Field helper.
 *
 * @param string $label   Label.
 * @param string $type    text|textarea|number|image|link|select|check|lines.
 * @param mixed  $default Default value.
 * @param array  $extra   Extra settings (options, help, half).
 * @return array
 */
function fleuriste_f( $label, $type, $default = '', $extra = array() ) {
	return array_merge(
		array(
			'label'   => $label,
			'type'    => $type,
			'default' => $default,
		),
		$extra
	);
}

/**
 * All editable sections. Home sections have 'home' => true and can be reordered.
 *
 * @return array
 */
function fleuriste_sections_schema() {
	static $schema = null;
	if ( null !== $schema ) {
		return $schema;
	}
	$opt     = array( 'help' => __( 'Leave empty to hide.', 'fleuriste' ) );
	$half    = array( 'half' => true );
	$halfopt = array(
		'half' => true,
		'help' => __( 'Leave empty to hide.', 'fleuriste' ),
	);
	$pick    = array( 'help' => __( 'Optional. Pick products to show exactly these, in this order (overrides "Which products").', 'fleuriste' ) );
	$sources = array(
		'options' => array(
			'bestsellers' => __( 'Best sellers', 'fleuriste' ),
			'newest'      => __( 'Newest products', 'fleuriste' ),
			'featured'    => __( 'Featured products (star in Products)', 'fleuriste' ),
			'on_sale'     => __( 'Products on sale', 'fleuriste' ),
		),
	);

	$schema = array(
		'hero'         => array(
			'title'  => __( 'Top banner', 'fleuriste' ),
			'desc'   => __( 'Big title, buttons and photos at the top of the home page.', 'fleuriste' ),
			'home'   => true,
			'fields' => array(
				'eyebrow'       => fleuriste_f( __( 'Small text above the title', 'fleuriste' ), 'text', 'Hand-tied every morning', $opt ),
				'title'         => fleuriste_f( __( 'Title', 'fleuriste' ), 'text', 'Fresh flowers, hand-tied and' ),
				'title_em'      => fleuriste_f( __( 'Highlighted end of the title', 'fleuriste' ), 'text', 'delivered today', array( 'help' => __( 'Shown in italics after the title. Leave empty for none.', 'fleuriste' ) ) ),
				'text'          => fleuriste_f( __( 'Text', 'fleuriste' ), 'textarea', 'Seasonal bouquets, hand-tied fresh every morning. Order by 2pm for same-day delivery across the island.', $opt ),
				'button1_label' => fleuriste_f( __( 'Main button', 'fleuriste' ), 'text', 'Shop bouquets', $halfopt ),
				'button1_link'  => fleuriste_f( __( 'Main button goes to', 'fleuriste' ), 'link', 'shop', $half ),
				'button2_label' => fleuriste_f( __( 'Second button', 'fleuriste' ), 'text', 'Contact us', $halfopt ),
				'button2_link'  => fleuriste_f( __( 'Second button goes to', 'fleuriste' ), 'link', '/contact/', $half ),
				'point1'        => fleuriste_f( __( 'Tick point 1', 'fleuriste' ), 'text', 'Same-day delivery', $halfopt ),
				'point2'        => fleuriste_f( __( 'Tick point 2', 'fleuriste' ), 'text', 'Locally grown stems', $halfopt ),
				'point3'        => fleuriste_f( __( 'Tick point 3', 'fleuriste' ), 'text', 'Freshness guaranteed', $halfopt ),
				'image'         => fleuriste_f( __( 'Main photo', 'fleuriste' ), 'image', 'hero-main.webp', array( 'help' => __( 'Tall photo (about 900 × 1600).', 'fleuriste' ) ) ),
				'card_image'    => fleuriste_f( __( 'Small card photo', 'fleuriste' ), 'image', 'hero-secondary.webp' ),
				'card_title'    => fleuriste_f( __( 'Small card title', 'fleuriste' ), 'text', '', array( 'half' => true, 'help' => __( 'Leave empty to hide the small card.', 'fleuriste' ) ) ),
				'card_text'     => fleuriste_f( __( 'Small card text', 'fleuriste' ), 'text', '', $half ),
				'badge_big'     => fleuriste_f( __( 'Round badge – big text', 'fleuriste' ), 'text', '15% OFF', $halfopt ),
				'badge_text'    => fleuriste_f( __( 'Round badge – small text', 'fleuriste' ), 'text', 'First order · code BLOOM15', $half ),
			),
		),
		'features'     => array(
			'title'  => __( 'Features strip', 'fleuriste' ),
			'desc'   => __( 'Four short promises with icons (delivery, freshness…).', 'fleuriste' ),
			'home'   => true,
			'fields' => array(
				'f1_title' => fleuriste_f( __( '1 – Title', 'fleuriste' ), 'text', 'Same-day delivery', $half ),
				'f1_text'  => fleuriste_f( __( '1 – Text', 'fleuriste' ), 'text', 'Order before 2pm for delivery the same day across the city.', $half ),
				'f2_title' => fleuriste_f( __( '2 – Title', 'fleuriste' ), 'text', 'Freshness guarantee', $half ),
				'f2_text'  => fleuriste_f( __( '2 – Text', 'fleuriste' ), 'text', 'Every bouquet is guaranteed to stay fresh for at least 7 days.', $half ),
				'f3_title' => fleuriste_f( __( '3 – Title', 'fleuriste' ), 'text', 'Free card message', $half ),
				'f3_text'  => fleuriste_f( __( '3 – Text', 'fleuriste' ), 'text', 'Add a personal message to every bouquet, or send it anonymously.', $half ),
				'f4_title' => fleuriste_f( __( '4 – Title', 'fleuriste' ), 'text', 'Secure payment', $half ),
				'f4_text'  => fleuriste_f( __( '4 – Text', 'fleuriste' ), 'text', 'Pay safely with all major cards, Apple Pay and Google Pay.', $half ),
			),
		),
		'categories'   => array(
			'title'  => __( 'Shop by category', 'fleuriste' ),
			'desc'   => __( 'Round tiles of your main categories, with their pictures.', 'fleuriste' ),
			'home'   => true,
			'fields' => array(
				'eyebrow' => fleuriste_f( __( 'Small text above the title', 'fleuriste' ), 'text', 'Browse the studio', $halfopt ),
				'title'   => fleuriste_f( __( 'Title', 'fleuriste' ), 'text', 'Shop by category', $half ),
				'limit'   => fleuriste_f( __( 'How many categories', 'fleuriste' ), 'number', 8, array( 'help' => __( 'Pictures and order: Products → Categories.', 'fleuriste' ) ) ),
			),
		),
		'bestsellers'  => array(
			'title'  => __( 'Product grid (bestsellers / featured / on sale)', 'fleuriste' ),
			'desc'   => __( 'Up to 8 products in two rows: best sellers, featured, on sale, or your own choice.', 'fleuriste' ),
			'home'   => true,
			'fields' => array(
				'eyebrow'    => fleuriste_f( __( 'Small text above the title', 'fleuriste' ), 'text', 'Loved this week', $halfopt ),
				'title'      => fleuriste_f( __( 'Title', 'fleuriste' ), 'text', 'Bestsellers of the week', $half ),
				'text'       => fleuriste_f( __( 'Text', 'fleuriste' ), 'textarea', 'Our most-loved arrangements this week, hand-tied fresh and ready for delivery today.', $opt ),
				'source'     => fleuriste_f( __( 'Which products', 'fleuriste' ), 'select', 'bestsellers', array_merge( $sources, $half ) ),
				'count'      => fleuriste_f( __( 'How many (up to 8, two rows)', 'fleuriste' ), 'number', 8, array( 'half' => true, 'max' => 8 ) ),
				'products'   => fleuriste_f( __( 'Choose products', 'fleuriste' ), 'products', '', $pick ),
				'link_label' => fleuriste_f( __( 'Link text', 'fleuriste' ), 'text', 'View all flowers', $halfopt ),
				'link'       => fleuriste_f( __( 'Link goes to', 'fleuriste' ), 'link', 'shop', $half ),
			),
		),
		'occasions'    => array(
			'title'  => __( 'Shop by occasion', 'fleuriste' ),
			'desc'   => __( 'Up to four photo tiles: choose each occasion\'s name, photo and where it goes (empty a title to hide that tile).', 'fleuriste' ),
			'home'   => true,
			'fields' => array(
				'eyebrow'  => fleuriste_f( __( 'Small text above the title', 'fleuriste' ), 'text', 'For every moment', $halfopt ),
				'title'    => fleuriste_f( __( 'Title', 'fleuriste' ), 'text', 'Shop by occasion', $half ),
				'text'     => fleuriste_f( __( 'Text', 'fleuriste' ), 'textarea', 'From birthdays to heartfelt goodbyes, find the right flowers for every moment.', $opt ),
				'more'     => fleuriste_f( __( 'Tile link text', 'fleuriste' ), 'text', 'Shop now', $opt ),
				'o1_title' => fleuriste_f( __( 'Tile 1 (tall) – title', 'fleuriste' ), 'text', 'Birthday', $half ),
				'o1_link'  => fleuriste_f( __( 'Tile 1 – goes to', 'fleuriste' ), 'link', 'tag:birthday', $half ),
				'o1_image' => fleuriste_f( __( 'Tile 1 – photo', 'fleuriste' ), 'image', 'occasion-birthday.webp' ),
				'o2_title' => fleuriste_f( __( 'Tile 2 (wide) – title', 'fleuriste' ), 'text', 'Love & Anniversary', $half ),
				'o2_link'  => fleuriste_f( __( 'Tile 2 – goes to', 'fleuriste' ), 'link', 'tag:anniversary-love', $half ),
				'o2_image' => fleuriste_f( __( 'Tile 2 – photo', 'fleuriste' ), 'image', 'occasion-anniversary.webp' ),
				'o3_title' => fleuriste_f( __( 'Tile 3 – title', 'fleuriste' ), 'text', 'Sympathy', $half ),
				'o3_link'  => fleuriste_f( __( 'Tile 3 – goes to', 'fleuriste' ), 'link', 'tag:sympathy', $half ),
				'o3_image' => fleuriste_f( __( 'Tile 3 – photo', 'fleuriste' ), 'image', 'occasion-sympathy.webp' ),
				'o4_title' => fleuriste_f( __( 'Tile 4 – title', 'fleuriste' ), 'text', 'Get Well Soon', $half ),
				'o4_link'  => fleuriste_f( __( 'Tile 4 – goes to', 'fleuriste' ), 'link', 'tag:get-well-soon', $half ),
				'o4_image' => fleuriste_f( __( 'Tile 4 – photo', 'fleuriste' ), 'image', 'occasion-get-well.webp' ),
			),
		),
		'offers'       => array(
			'title'  => __( 'Offers', 'fleuriste' ),
			'desc'   => __( 'Three promotion cards with a photo, a big discount and a button.', 'fleuriste' ),
			'home'   => true,
			'fields' => array(
				'eyebrow'    => fleuriste_f( __( 'Small text above the title', 'fleuriste' ), 'text', 'Seasonal savings', $halfopt ),
				'title'      => fleuriste_f( __( 'Title', 'fleuriste' ), 'text', 'Offers in bloom', $half ),
				'text'       => fleuriste_f( __( 'Text', 'fleuriste' ), 'textarea', 'Seasonal savings on bouquets, subscriptions and gift bundles.', $opt ),
				'c1_eyebrow' => fleuriste_f( __( 'Card 1 – name', 'fleuriste' ), 'text', "Mother's Day pre-order", $half ),
				'c1_big'     => fleuriste_f( __( 'Card 1 – big text', 'fleuriste' ), 'text', '20% OFF', $half ),
				'c1_text'    => fleuriste_f( __( 'Card 1 – text', 'fleuriste' ), 'text', "Reserve early and save on all Mother's Day bouquets." ),
				'c1_button'  => fleuriste_f( __( 'Card 1 – button', 'fleuriste' ), 'text', 'Pre-order now', $halfopt ),
				'c1_link'    => fleuriste_f( __( 'Card 1 – goes to', 'fleuriste' ), 'link', 'shop', $half ),
				'c1_image'   => fleuriste_f( __( 'Card 1 – photo', 'fleuriste' ), 'image', 'offer-mothers-day.webp' ),
				'c2_eyebrow' => fleuriste_f( __( 'Card 2 – name', 'fleuriste' ), 'text', 'Flower subscription', $half ),
				'c2_big'     => fleuriste_f( __( 'Card 2 – big text', 'fleuriste' ), 'text', '25% OFF', $half ),
				'c2_text'    => fleuriste_f( __( 'Card 2 – text', 'fleuriste' ), 'text', 'Fresh blooms delivered weekly, fortnightly or monthly.' ),
				'c2_button'  => fleuriste_f( __( 'Card 2 – button', 'fleuriste' ), 'text', 'Start a subscription', $halfopt ),
				'c2_link'    => fleuriste_f( __( 'Card 2 – goes to', 'fleuriste' ), 'link', 'shop', $half ),
				'c2_image'   => fleuriste_f( __( 'Card 2 – photo', 'fleuriste' ), 'image', 'offer-subscription.webp' ),
				'c3_eyebrow' => fleuriste_f( __( 'Card 3 – name', 'fleuriste' ), 'text', 'Birthday bundle', $half ),
				'c3_big'     => fleuriste_f( __( 'Card 3 – big text', 'fleuriste' ), 'text', '15% OFF', $half ),
				'c3_text'    => fleuriste_f( __( 'Card 3 – text', 'fleuriste' ), 'text', 'Add chocolates and a balloon to any bouquet and save.' ),
				'c3_button'  => fleuriste_f( __( 'Card 3 – button', 'fleuriste' ), 'text', 'Build a bundle', $halfopt ),
				'c3_link'    => fleuriste_f( __( 'Card 3 – goes to', 'fleuriste' ), 'link', 'cat:gifts', $half ),
				'c3_image'   => fleuriste_f( __( 'Card 3 – photo', 'fleuriste' ), 'image', 'offer-birthday-bundle.webp' ),
			),
		),
		'about'        => array(
			'title'  => __( 'Our story', 'fleuriste' ),
			'desc'   => __( 'Photo with a round stamp, a short story and three ticks.', 'fleuriste' ),
			'home'   => true,
			'fields' => array(
				'image'        => fleuriste_f( __( 'Photo', 'fleuriste' ), 'image', 'about-florist.webp' ),
				'stamp_big'    => fleuriste_f( __( 'Round stamp – big text', 'fleuriste' ), 'text', '7', $halfopt ),
				'stamp_text'   => fleuriste_f( __( 'Round stamp – small text', 'fleuriste' ), 'text', 'day freshness guarantee', $half ),
				'eyebrow'      => fleuriste_f( __( 'Small text above the title', 'fleuriste' ), 'text', 'Our story', $halfopt ),
				'title'        => fleuriste_f( __( 'Title', 'fleuriste' ), 'text', 'Every bouquet starts with the', $half ),
				'title_em'     => fleuriste_f( __( 'Highlighted end of the title', 'fleuriste' ), 'text', 'freshest blooms' ),
				'text'         => fleuriste_f( __( 'Text', 'fleuriste' ), 'textarea', "We source directly from local growers and trusted flower farms, condition every stem by hand and arrange each order on the morning it is delivered. If your flowers don't last 7 days, we'll replace them." ),
				'b1'           => fleuriste_f( __( 'Tick 1', 'fleuriste' ), 'text', 'Sourced from local growers and trusted farms', $opt ),
				'b2'           => fleuriste_f( __( 'Tick 2', 'fleuriste' ), 'text', 'Every stem conditioned and arranged by hand', $opt ),
				'b3'           => fleuriste_f( __( 'Tick 3', 'fleuriste' ), 'text', 'Wrapped in recyclable paper and ribbon', $opt ),
				'button_label' => fleuriste_f( __( 'Button', 'fleuriste' ), 'text', 'Read our story', $halfopt ),
				'button_link'  => fleuriste_f( __( 'Button goes to', 'fleuriste' ), 'link', '/about/', $half ),
			),
		),
		'new-arrivals' => array(
			'title'  => __( 'Just in (newest products)', 'fleuriste' ),
			'desc'   => __( 'The newest products automatically, unless you choose them yourself.', 'fleuriste' ),
			'home'   => true,
			'fields' => array(
				'eyebrow'    => fleuriste_f( __( 'Small text above the title', 'fleuriste' ), 'text', 'Just in', $halfopt ),
				'title'      => fleuriste_f( __( 'Title', 'fleuriste' ), 'text', 'Fresh from the studio', $half ),
				'text'       => fleuriste_f( __( 'Text', 'fleuriste' ), 'textarea', 'Customer favourites and new seasonal arrangements, from classic red roses to long-lasting plants.', $opt ),
				'source'     => fleuriste_f( __( 'Which products', 'fleuriste' ), 'select', 'newest', array_merge( $sources, $half ) ),
				'count'      => fleuriste_f( __( 'How many (up to 8, two rows)', 'fleuriste' ), 'number', 4, array( 'half' => true, 'max' => 8 ) ),
				'products'   => fleuriste_f( __( 'Choose products', 'fleuriste' ), 'products', '', array( 'help' => __( 'Optional. Leave empty to show the newest products automatically, or pick products to show exactly these.', 'fleuriste' ) ) ),
				'link_label' => fleuriste_f( __( 'Link text', 'fleuriste' ), 'text', 'View all flowers', $halfopt ),
				'link'       => fleuriste_f( __( 'Link goes to', 'fleuriste' ), 'link', 'shop', $half ),
			),
		),
		'flower-club'  => array(
			'title'  => __( 'Flower club', 'fleuriste' ),
			'desc'   => __( 'Sign-up call to action. Needs customer accounts (Shop settings → Customer accounts) if the button goes to My Account.', 'fleuriste' ),
			'home'   => true,
			'fields' => array(
				'eyebrow'      => fleuriste_f( __( 'Small text above the title', 'fleuriste' ), 'text', 'Join our flower club', $halfopt ),
				'title'        => fleuriste_f( __( 'Title', 'fleuriste' ), 'text', 'Get 15% off your first bouquet', $half ),
				'text'         => fleuriste_f( __( 'Text', 'fleuriste' ), 'textarea', 'Create a free account to save your addresses, track orders and hear first about seasonal flowers and offers.', $opt ),
				'button_label' => fleuriste_f( __( 'Button', 'fleuriste' ), 'text', 'Join the club', $halfopt ),
				'button_link'  => fleuriste_f( __( 'Button goes to', 'fleuriste' ), 'link', 'account', $half ),
				'image'        => fleuriste_f( __( 'Picture (cut-out PNG works best)', 'fleuriste' ), 'image', 'subscribe-cutout.webp' ),
			),
		),
		'product'      => array(
			'title'  => __( 'Product page', 'fleuriste' ),
			'desc'   => __( 'Reassurance lines under Add to cart, and the cards under the product details.', 'fleuriste' ),
			'fields' => array(
				'trust1'      => fleuriste_f( __( 'Line 1 (truck icon)', 'fleuriste' ), 'text', 'Order before 2pm for same-day delivery', $opt ),
				'trust2'      => fleuriste_f( __( 'Line 2 (leaf icon)', 'fleuriste' ), 'text', 'Hand-tied the morning it is delivered', $opt ),
				'trust3'      => fleuriste_f( __( 'Line 3 (shield icon)', 'fleuriste' ), 'text', '7-day freshness guarantee', $opt ),
				'perks'       => fleuriste_f( __( 'Show the four cards under the product', 'fleuriste' ), 'check', 1 ),
				'p1_title'    => fleuriste_f( __( 'Card 1 – title', 'fleuriste' ), 'text', 'Secure payments', $half ),
				'p1_text'     => fleuriste_f( __( 'Card 1 – text', 'fleuriste' ), 'text', 'Pay safely with all major cards, Apple Pay and Google Pay.', $half ),
				'p2_title'    => fleuriste_f( __( 'Card 2 – title', 'fleuriste' ), 'text', 'Same-day delivery', $half ),
				'p2_text'     => fleuriste_f( __( 'Card 2 – text', 'fleuriste' ), 'text', 'Order before 2pm for delivery the same day across the city.', $half ),
				'p3_title'    => fleuriste_f( __( 'Card 3 – title', 'fleuriste' ), 'text', 'Freshness guarantee', $half ),
				'p3_text'     => fleuriste_f( __( 'Card 3 – text', 'fleuriste' ), 'text', 'Every bouquet is guaranteed to stay fresh for at least 7 days.', $half ),
				'p4_title'    => fleuriste_f( __( 'Card 4 – title', 'fleuriste' ), 'text', 'Free card message', $half ),
				'p4_text'     => fleuriste_f( __( 'Card 4 – text', 'fleuriste' ), 'text', 'Add a personal message to every bouquet, or send it anonymously.', $half ),
				'sticky_cart' => fleuriste_f( __( 'Show the Add to cart bar at the bottom when scrolling', 'fleuriste' ), 'check', 1 ),
			),
		),
		'footer'       => array(
			'title'  => __( 'Footer', 'fleuriste' ),
			'desc'   => __( 'Bottom of every page: about text, social links and help links. The address, opening hours, phone and WhatsApp come from Shop settings → Shop details.', 'fleuriste' ),
			'fields' => array(
				'about'     => fleuriste_f( __( 'About text', 'fleuriste' ), 'textarea', 'Seasonal bouquets, hand-tied fresh every morning and delivered across the island.', $opt ),
				'instagram' => fleuriste_f( __( 'Instagram link', 'fleuriste' ), 'text', '', $halfopt ),
				'facebook'  => fleuriste_f( __( 'Facebook link', 'fleuriste' ), 'text', '', $halfopt ),
				'tiktok'    => fleuriste_f( __( 'TikTok link', 'fleuriste' ), 'text', '', $halfopt ),
				'shop_head' => fleuriste_f( __( 'Column 1 title', 'fleuriste' ), 'text', 'Shop', array( 'half' => true, 'help' => __( 'Lists your main categories automatically.', 'fleuriste' ) ) ),
				'help_head' => fleuriste_f( __( 'Column 2 title', 'fleuriste' ), 'text', 'Help', $half ),
				'help'      => fleuriste_f( __( 'Column 2 links', 'fleuriste' ), 'lines', "Delivery Areas | /delivery/\nFlower Care | /flower-care/\nMy Account | /my-account/\nContact Us | /contact/", array( 'help' => __( 'One link per line: Text | address (e.g. Contact Us | /contact/).', 'fleuriste' ) ) ),
				'visit_head'=> fleuriste_f( __( 'Column 3 title', 'fleuriste' ), 'text', 'Visit our shop', array( 'half' => true, 'help' => __( 'Shows the address, opening hours, phone and WhatsApp from Shop settings → Shop details.', 'fleuriste' ) ) ),
				'show_email'=> fleuriste_f( __( 'Show the shop email in the footer', 'fleuriste' ), 'check', '', $half ),
				'copyright' => fleuriste_f( __( 'Copyright line', 'fleuriste' ), 'text', '© {year} {name}. Fresh flowers, made by hand.', array( 'help' => __( '{year} and {name} are filled in automatically.', 'fleuriste' ) ) ),
				'tagline'   => fleuriste_f( __( 'Bottom right text', 'fleuriste' ), 'text', 'Secure payment · Same-day delivery · Freshness guaranteed', $opt ),
			),
		),
	);
	return $schema;
}

/* -------------------------------------------------------------------------
 * Reading values
 * ---------------------------------------------------------------------- */

/**
 * Saved settings.
 *
 * @return array order, show, v.
 */
function fleuriste_site_settings() {
	$s = get_option( 'fleuriste_site', array() );
	return wp_parse_args(
		is_array( $s ) ? $s : array(),
		array(
			'order' => array(),
			'show'  => array(),
			'v'     => array(),
		)
	);
}

/**
 * Value of a field (saved, or the default).
 *
 * @param string $sec Section.
 * @param string $key Field.
 * @return mixed
 */
function fleuriste_s( $sec, $key ) {
	$s = fleuriste_site_settings();
	if ( array_key_exists( $sec . '.' . $key, $s['v'] ) ) {
		return $s['v'][ $sec . '.' . $key ];
	}
	$schema = fleuriste_sections_schema();
	return isset( $schema[ $sec ]['fields'][ $key ] ) ? $schema[ $sec ]['fields'][ $key ]['default'] : '';
}

/**
 * Echo a text field, escaped.
 *
 * @param string $sec Section.
 * @param string $key Field.
 */
function fleuriste_s_e( $sec, $key ) {
	echo esc_html( (string) fleuriste_s( $sec, $key ) );
}

/**
 * Is a field filled in?
 *
 * @param string $sec Section.
 * @param string $key Field.
 * @return bool
 */
function fleuriste_s_has( $sec, $key ) {
	return '' !== trim( (string) fleuriste_s( $sec, $key ) );
}

/**
 * URL of an image field (Media Library picture, or the theme's own photo).
 *
 * @param string $sec  Section.
 * @param string $key  Field.
 * @param string $size Image size.
 * @return string
 */
function fleuriste_s_img( $sec, $key, $size = 'large' ) {
	$v = fleuriste_s( $sec, $key );
	if ( is_numeric( $v ) && (int) $v > 0 ) {
		$url = wp_get_attachment_image_url( (int) $v, $size );
		if ( $url ) {
			return $url;
		}
	}
	$schema  = fleuriste_sections_schema();
	$default = isset( $schema[ $sec ]['fields'][ $key ] ) ? $schema[ $sec ]['fields'][ $key ]['default'] : '';
	return $default ? fleuriste_img( $default ) : '';
}

/**
 * Turn a stored link into a URL: shop, account, cat:slug, tag:slug (occasion), page:ID, /path/ or a full address.
 *
 * @param string $v Stored value.
 * @return string
 */
function fleuriste_resolve_link( $v ) {
	$v = trim( (string) $v );
	if ( '' === $v ) {
		return '';
	}
	if ( 'shop' === $v ) {
		return fleuriste_shop_url();
	}
	if ( 'account' === $v ) {
		return function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' );
	}
	if ( 0 === strpos( $v, 'cat:' ) ) {
		return fleuriste_cat_url( substr( $v, 4 ) );
	}
	if ( 0 === strpos( $v, 'tag:' ) ) {
		$link = taxonomy_exists( 'product_tag' ) ? get_term_link( substr( $v, 4 ), 'product_tag' ) : '';
		return ( $link && ! is_wp_error( $link ) ) ? $link : fleuriste_shop_url();
	}
	if ( 0 === strpos( $v, 'page:' ) ) {
		$url = get_permalink( (int) substr( $v, 5 ) );
		return $url ? $url : home_url( '/' );
	}
	if ( '/' === $v[0] ) {
		return home_url( $v );
	}
	if ( preg_match( '#^(https?:|mailto:|tel:)#i', $v ) ) {
		return $v;
	}
	return 'https://' . $v;
}

/**
 * URL of a link field.
 *
 * @param string $sec Section.
 * @param string $key Field.
 * @return string
 */
function fleuriste_s_link( $sec, $key ) {
	return fleuriste_resolve_link( fleuriste_s( $sec, $key ) );
}

/**
 * [products] shortcode for a product grid section.
 *
 * @param string $sec Section.
 * @return string
 */
function fleuriste_s_products( $sec ) {
	$count = max( 1, min( 8, (int) fleuriste_s( $sec, 'count' ) ) );
	$ids   = array_filter( array_map( 'absint', explode( ',', (string) fleuriste_s( $sec, 'products' ) ) ) );
	if ( $ids ) {
		return '[products ids="' . implode( ',', array_slice( $ids, 0, 8 ) ) . '" limit="' . min( 8, count( $ids ) ) . '" columns="4" orderby="post__in"]';
	}
	switch ( fleuriste_s( $sec, 'source' ) ) {
		case 'newest':
			$extra = 'orderby="date" order="DESC"';
			break;
		case 'featured':
			$extra = 'visibility="featured"';
			break;
		case 'on_sale':
			$extra = 'on_sale="true"';
			break;
		default:
			$extra = 'best_selling="true"';
	}
	return '[products limit="' . $count . '" columns="4" ' . $extra . ']';
}

/**
 * Home sections in display order.
 *
 * @return string[]
 */
function fleuriste_home_order() {
	$all   = array_keys(
		array_filter(
			fleuriste_sections_schema(),
			function ( $s ) {
				return ! empty( $s['home'] );
			}
		)
	);
	$saved = array_values( array_intersect( (array) fleuriste_site_settings()['order'], $all ) );
	if ( ! $saved ) {
		// Default page: banner, products, occasions, story, newest – then the optional extras.
		$saved = array( 'hero', 'bestsellers', 'occasions', 'about', 'new-arrivals', 'categories', 'features', 'offers', 'flower-club' );
	}
	return array_values( array_unique( array_merge( $saved, $all ) ) );
}

/**
 * Is a home section shown?
 *
 * @param string $sec Section.
 * @return bool
 */
function fleuriste_section_shown( $sec ) {
	$show = fleuriste_site_settings()['show'];
	if ( isset( $show[ $sec ] ) ) {
		return (bool) $show[ $sec ];
	}
	// Optional extras start hidden; switch them on in Shop settings → Website.
	return ! in_array( $sec, array( 'categories', 'features', 'offers', 'flower-club' ), true );
}

/* -------------------------------------------------------------------------
 * Rendering: home page sections block, and hiding sections in edited templates
 * ---------------------------------------------------------------------- */

add_action(
	'init',
	function () {
		register_block_type(
			'fleuriste/home-sections',
			array(
				'api_version'     => 3,
				'title'           => __( 'Home page sections', 'fleuriste' ),
				'category'        => 'theme',
				'render_callback' => function () {
					$registry = WP_Block_Patterns_Registry::get_instance();
					$html     = '';
					foreach ( fleuriste_home_order() as $sec ) {
						if ( ! fleuriste_section_shown( $sec ) ) {
							continue;
						}
						$pattern = $registry->get_registered( 'fleuriste/' . $sec );
						if ( $pattern && ! empty( $pattern['content'] ) ) {
							$html .= do_blocks( $pattern['content'] );
						}
					}
					return $html;
				},
			)
		);
	},
	20
);

// Templates edited in the Site Editor still list the sections one by one: hide switched-off ones.
add_filter(
	'render_block_core/pattern',
	function ( $html, $block ) {
		$slug = isset( $block['attrs']['slug'] ) ? (string) $block['attrs']['slug'] : '';
		if ( 0 === strpos( $slug, 'fleuriste/' ) ) {
			$sec    = substr( $slug, 10 );
			$schema = fleuriste_sections_schema();
			if ( ! empty( $schema[ $sec ]['home'] ) && ! fleuriste_section_shown( $sec ) ) {
				return '';
			}
		}
		return $html;
	},
	10,
	2
);

// Placeholder for the block in the Site Editor.
add_action(
	'enqueue_block_editor_assets',
	function () {
		wp_register_script( 'fleuriste-home-sections', false, array( 'wp-blocks', 'wp-element' ), FLEURISTE_VERSION, true );
		wp_enqueue_script( 'fleuriste-home-sections' );
		wp_add_inline_script(
			'fleuriste-home-sections',
			'( function ( blocks, el ) {
				if ( blocks.getBlockType( "fleuriste/home-sections" ) ) { return; }
				blocks.registerBlockType( "fleuriste/home-sections", {
					apiVersion: 3, title: ' . wp_json_encode( __( 'Home page sections', 'fleuriste' ) ) . ', category: "theme", icon: "layout",
					edit: function () {
						return el( "div", { style: { padding: "32px", border: "2px dashed #c9a2ad", borderRadius: "12px", textAlign: "center", background: "#fcf8f8" } },
							el( "strong", null, ' . wp_json_encode( __( 'Home page sections', 'fleuriste' ) ) . ' ),
							el( "p", { style: { margin: "6px 0 0" } }, ' . wp_json_encode( __( 'Show, hide, reorder and edit them in Shop settings → Website.', 'fleuriste' ) ) . ' ) );
					},
					save: function () { return null; }
				} );
			} )( window.wp.blocks, window.wp.element.createElement );'
		);
	}
);

/* -------------------------------------------------------------------------
 * Admin page: Shop settings → Website
 * ---------------------------------------------------------------------- */

add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'fleuriste-company', __( 'Website', 'fleuriste' ), __( 'Website', 'fleuriste' ), 'edit_theme_options', 'fleuriste-website', 'fleuriste_website_page', 1 );
	},
	100
);

add_action(
	'admin_post_fleuriste_website',
	function () {
		check_admin_referer( 'fleuriste_website' );
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'fleuriste' ) );
		}
		$in     = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field type below.
		$schema = fleuriste_sections_schema();
		$out    = array(
			'order' => array(),
			'show'  => array(),
			'v'     => array(),
		);
		foreach ( isset( $in['order'] ) ? (array) $in['order'] : array() as $sec ) {
			$sec = sanitize_key( $sec );
			if ( ! empty( $schema[ $sec ]['home'] ) ) {
				$out['order'][] = $sec;
			}
		}
		foreach ( $schema as $sec => $def ) {
			if ( ! empty( $def['home'] ) ) {
				$out['show'][ $sec ] = empty( $in['show'][ $sec ] ) ? 0 : 1;
			}
			foreach ( $def['fields'] as $key => $f ) {
				$raw = isset( $in['v'][ $sec ][ $key ] ) ? $in['v'][ $sec ][ $key ] : '';
				switch ( $f['type'] ) {
					case 'textarea':
					case 'lines':
						$val = str_replace( "\r\n", "\n", sanitize_textarea_field( $raw ) );
						break;
					case 'number':
						$val = max( 1, absint( $raw ) );
						if ( ! empty( $f['max'] ) ) {
							$val = min( (int) $f['max'], $val );
						}
						break;
					case 'products':
						$val = implode( ',', array_filter( array_map( 'absint', (array) $raw ) ) );
						break;
					case 'image':
						$val = absint( $raw ) ? absint( $raw ) : '';
						break;
					case 'check':
						$val = empty( $raw ) ? 0 : 1;
						break;
					case 'link':
						$val = 'custom' === $raw ? trim( sanitize_text_field( isset( $in['vc'][ $sec ][ $key ] ) ? $in['vc'][ $sec ][ $key ] : '' ) ) : sanitize_text_field( $raw );
						break;
					default:
						$val = sanitize_text_field( $raw );
				}
				// Only store what differs from the default, so defaults can improve later.
				if ( 'image' === $f['type'] && '' === $val ) {
					continue;
				}
				if ( (string) $val !== (string) $f['default'] ) {
					$out['v'][ $sec . '.' . $key ] = $val;
				}
			}
		}
		update_option( 'fleuriste_site', $out );
		$tab = isset( $in['tab'] ) ? sanitize_key( $in['tab'] ) : 'home';
		wp_safe_redirect( admin_url( 'admin.php?page=fleuriste-website&saved=1&tab=' . $tab ) );
		exit;
	}
);

/**
 * Choices for link fields.
 *
 * @return array value => label (grouped).
 */
function fleuriste_link_choices() {
	$choices = array(
		__( 'Shop', 'fleuriste' ) => array(
			'shop'    => __( 'All Products', 'fleuriste' ),
			'account' => __( 'My Account', 'fleuriste' ),
		),
	);
	$cats = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'orderby'    => 'name',
		)
	);
	if ( ! is_wp_error( $cats ) && $cats ) {
		foreach ( $cats as $c ) {
			$choices[ __( 'Categories', 'fleuriste' ) ][ 'cat:' . $c->slug ] = $c->name;
		}
	}
	$tags = taxonomy_exists( 'product_tag' ) ? get_terms(
		array(
			'taxonomy'   => 'product_tag',
			'hide_empty' => false,
			'orderby'    => 'name',
		)
	) : array();
	if ( ! is_wp_error( $tags ) && $tags ) {
		foreach ( $tags as $t ) {
			$choices[ __( 'Occasions (product tags)', 'fleuriste' ) ][ 'tag:' . $t->slug ] = $t->name;
		}
	}
	$pages = get_pages( array( 'sort_column' => 'post_title' ) );
	foreach ( $pages as $p ) {
		$choices[ __( 'Pages', 'fleuriste' ) ][ 'page:' . $p->ID ] = $p->post_title;
	}
	return $choices;
}

/**
 * One field in the admin form.
 *
 * @param string $sec Section.
 * @param string $key Field.
 * @param array  $f   Field definition.
 */
function fleuriste_website_field( $sec, $key, $f ) {
	$name  = 'v[' . $sec . '][' . $key . ']';
	$val   = fleuriste_s( $sec, $key );
	$id    = 'fl-w-' . $sec . '-' . $key;
	$def   = is_scalar( $f['default'] ) ? (string) $f['default'] : '';
	$class = 'fl-w-field fl-w-field--' . $f['type'] . ( ! empty( $f['half'] ) ? ' is-half' : '' );
	echo '<div class="' . esc_attr( $class ) . '">';
	if ( 'check' !== $f['type'] ) {
		echo '<label for="' . esc_attr( $id ) . '">' . esc_html( $f['label'] ) . '</label>';
	}
	switch ( $f['type'] ) {
		case 'textarea':
		case 'lines':
			printf( '<textarea id="%1$s" name="%2$s" rows="%4$d" data-default="%5$s">%3$s</textarea>', esc_attr( $id ), esc_attr( $name ), esc_textarea( (string) $val ), 'lines' === $f['type'] ? 4 : 3, esc_attr( $def ) );
			break;
		case 'number':
			printf( '<input type="number" min="1" max="%5$d" id="%1$s" name="%2$s" value="%3$s" data-default="%4$s" class="small-text">', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $val ), esc_attr( $def ), ! empty( $f['max'] ) ? (int) $f['max'] : 24 );
			break;
		case 'products':
			echo '<select class="wc-product-search fl-w-products" multiple="multiple" style="width:100%" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '[]" data-placeholder="' . esc_attr__( 'Search for a product…', 'fleuriste' ) . '" data-action="woocommerce_json_search_products" data-default="">';
			foreach ( array_filter( array_map( 'absint', explode( ',', (string) $val ) ) ) as $pid ) {
				$prod = wc_get_product( $pid );
				if ( $prod ) {
					echo '<option value="' . (int) $pid . '" selected="selected">' . esc_html( wp_strip_all_tags( $prod->get_formatted_name() ) ) . '</option>';
				}
			}
			echo '</select>';
			break;
		case 'check':
			printf( '<label><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s data-default="%5$s"> %4$s</label>', esc_attr( $id ), esc_attr( $name ), checked( (bool) $val, true, false ), esc_html( $f['label'] ), esc_attr( $def ) );
			break;
		case 'select':
			echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" data-default="' . esc_attr( $def ) . '">';
			foreach ( $f['options'] as $ov => $ol ) {
				echo '<option value="' . esc_attr( $ov ) . '" ' . selected( (string) $val, (string) $ov, false ) . '>' . esc_html( $ol ) . '</option>';
			}
			echo '</select>';
			break;
		case 'image':
			$att = is_numeric( $val ) ? (int) $val : 0;
			$url = fleuriste_s_img( $sec, $key, 'medium' );
			printf(
				'<div class="fl-w-img"><img src="%1$s" alt=""><div><input type="hidden" name="%2$s" value="%3$s" data-default=""><button type="button" class="button fl-w-pick">%4$s</button> <button type="button" class="button-link fl-w-clear"%6$s>%5$s</button></div></div>',
				esc_url( $url ),
				esc_attr( $name ),
				$att ? (int) $att : '',
				esc_html__( 'Choose photo', 'fleuriste' ),
				esc_html__( 'Use the theme photo', 'fleuriste' ),
				$att ? '' : ' hidden'
			);
			echo '<span class="fl-w-img-default" data-src="' . esc_url( $def ? fleuriste_img( $def ) : '' ) . '" hidden></span>';
			break;
		case 'link':
			$choices = fleuriste_link_choices();
			$known   = false;
			foreach ( $choices as $group ) {
				if ( isset( $group[ (string) $val ] ) ) {
					$known = true;
				}
			}
			echo '<div class="fl-w-link"><select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" data-default="' . esc_attr( $def ) . '">';
			foreach ( $choices as $group => $opts ) {
				echo '<optgroup label="' . esc_attr( $group ) . '">';
				foreach ( $opts as $ov => $ol ) {
					echo '<option value="' . esc_attr( $ov ) . '" ' . selected( (string) $val, (string) $ov, false ) . '>' . esc_html( $ol ) . '</option>';
				}
				echo '</optgroup>';
			}
			echo '<option value="custom" ' . selected( ! $known, true, false ) . '>' . esc_html__( 'Other address…', 'fleuriste' ) . '</option></select>';
			printf( '<input type="text" class="fl-w-custom" name="vc[%1$s][%2$s]" value="%3$s" placeholder="%4$s"%5$s>', esc_attr( $sec ), esc_attr( $key ), esc_attr( $known ? '' : (string) $val ), esc_attr__( '/contact/ or https://…', 'fleuriste' ), $known ? ' hidden' : '' );
			echo '</div>';
			break;
		default:
			printf( '<input type="text" id="%1$s" name="%2$s" value="%3$s" data-default="%4$s">', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $val ), esc_attr( $def ) );
	}
	if ( ! empty( $f['help'] ) ) {
		echo '<p class="description">' . esc_html( $f['help'] ) . '</p>';
	}
	echo '</div>';
}

/**
 * Section card (fields + reset).
 *
 * @param string $sec  Section.
 * @param array  $def  Definition.
 * @param bool   $open Start open.
 */
function fleuriste_website_card( $sec, $def, $open = false ) {
	$home = ! empty( $def['home'] );
	?>
	<li class="fl-w-sec<?php echo ( $home && ! fleuriste_section_shown( $sec ) ) ? ' is-off' : ''; ?>" data-sec="<?php echo esc_attr( $sec ); ?>">
		<div class="fl-w-head">
			<?php if ( $home ) : ?>
				<span class="fl-w-drag dashicons dashicons-menu" title="<?php esc_attr_e( 'Drag to change the order', 'fleuriste' ); ?>" aria-hidden="true"></span>
				<input type="hidden" name="order[]" value="<?php echo esc_attr( $sec ); ?>">
				<label class="fl-switch" title="<?php esc_attr_e( 'Show on the home page', 'fleuriste' ); ?>"><input type="checkbox" name="show[<?php echo esc_attr( $sec ); ?>]" value="1" <?php checked( fleuriste_section_shown( $sec ) ); ?> aria-label="<?php echo esc_attr( sprintf( /* translators: %s: section */ __( 'Show %s', 'fleuriste' ), $def['title'] ) ); ?>"><span></span></label>
			<?php endif; ?>
			<button type="button" class="fl-w-toggle" aria-expanded="<?php echo $open ? 'true' : 'false'; ?>">
				<strong><?php echo esc_html( $def['title'] ); ?></strong>
				<small><?php echo esc_html( $def['desc'] ); ?></small>
			</button>
			<?php if ( $home ) : ?>
				<span class="fl-w-state"><?php echo fleuriste_section_shown( $sec ) ? esc_html__( 'Shown', 'fleuriste' ) : esc_html__( 'Hidden', 'fleuriste' ); ?></span>
				<span class="fl-w-move">
					<button type="button" class="button-link fl-w-up" aria-label="<?php esc_attr_e( 'Move up', 'fleuriste' ); ?>">↑</button>
					<button type="button" class="button-link fl-w-down" aria-label="<?php esc_attr_e( 'Move down', 'fleuriste' ); ?>">↓</button>
				</span>
			<?php endif; ?>
		</div>
		<div class="fl-w-body"<?php echo $open ? '' : ' hidden'; ?>>
			<div class="fl-w-grid">
				<?php
				foreach ( $def['fields'] as $key => $f ) {
					fleuriste_website_field( $sec, $key, $f );
				}
				?>
			</div>
			<p class="fl-w-reset-wrap"><button type="button" class="button-link fl-w-reset"><?php esc_html_e( 'Reset this section to the default texts and photos', 'fleuriste' ); ?></button></p>
		</div>
	</li>
	<?php
}

/**
 * Shop settings → Website.
 */
function fleuriste_website_page() {
	wp_enqueue_media();
	wp_enqueue_script( 'jquery-ui-sortable' );
	if ( function_exists( 'WC' ) ) {
		wp_enqueue_script( 'wc-enhanced-select' );
		wp_enqueue_style( 'woocommerce_admin_styles' );
	}
	$schema = fleuriste_sections_schema();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'home';
	$tabs = array(
		'home'    => __( 'Home page', 'fleuriste' ),
		'product' => __( 'Product page', 'fleuriste' ),
		'footer'  => __( 'Footer', 'fleuriste' ),
	);
	$tab  = isset( $tabs[ $tab ] ) ? $tab : 'home';
	?>
	<div class="wrap fl-w">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Website', 'fleuriste' ); ?></h1>
		<a class="page-title-action" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank"><?php esc_html_e( 'View the website', 'fleuriste' ); ?></a>
		<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Saved. The changes are live on the website.', 'fleuriste' ); ?></p></div>
		<?php endif; ?>
		<p class="fl-w-intro"><?php esc_html_e( 'Choose what appears on the website and change the texts, buttons and photos. Other settings:', 'fleuriste' ); ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=fleuriste-announcement' ) ); ?>"><?php esc_html_e( 'Announcement bar', 'fleuriste' ); ?></a> ·
			<a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=product_cat&post_type=product' ) ); ?>"><?php esc_html_e( 'Categories, their pictures and menu', 'fleuriste' ); ?></a> ·
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=fleuriste-accounts' ) ); ?>"><?php esc_html_e( 'Customer accounts', 'fleuriste' ); ?></a> ·
			<a href="<?php echo esc_url( admin_url( 'site-editor.php' ) ); ?>"><?php esc_html_e( 'Colours and fonts (Site Editor → Styles)', 'fleuriste' ); ?></a>
		</p>

		<nav class="nav-tab-wrapper">
			<?php foreach ( $tabs as $k => $label ) : ?>
				<a class="nav-tab<?php echo $k === $tab ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=fleuriste-website&tab=' . $k ) ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</nav>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="fl-w-form">
			<input type="hidden" name="action" value="fleuriste_website">
			<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">
			<?php wp_nonce_field( 'fleuriste_website' ); ?>

			<?php
			// The form always posts every section, so tabs not shown are kept as hidden inputs.
			foreach ( array_keys( $tabs ) as $t ) {
				$secs = 'home' === $t ? fleuriste_home_order() : array( $t );
				$wrap = $t === $tab;
				echo $wrap ? '' : '<div hidden>';
				if ( 'home' === $t && $wrap ) {
					echo '<p class="description">' . esc_html__( 'Switch sections on or off, drag them (or use the arrows) to change the order, and click a section to edit it.', 'fleuriste' ) . '</p>';
				}
				echo '<ul class="fl-w-list' . ( 'home' === $t ? ' fl-w-sortable' : '' ) . '">';
				foreach ( $secs as $sec ) {
					fleuriste_website_card( $sec, $schema[ $sec ], 'home' !== $t );
				}
				echo '</ul>';
				echo $wrap ? '' : '</div>';
			}
			?>
			<div class="fl-w-save"><button class="button button-primary button-large"><?php esc_html_e( 'Save changes', 'fleuriste' ); ?></button> <span class="fl-w-dirty" hidden><?php esc_html_e( 'Unsaved changes', 'fleuriste' ); ?></span></div>
		</form>
	</div>
	<style>
		.fl-w{max-width:980px}
		.fl-w-intro{color:#50575e}
		.fl-w .nav-tab-wrapper{margin-bottom:14px}
		.fl-w-list{margin:10px 0;padding:0;list-style:none}
		.fl-w-sec{background:#fff;border:1px solid #e2d6d8;border-radius:12px;margin:0 0 10px;overflow:hidden}
		.fl-w-sec.is-off .fl-w-toggle strong{color:#8c8f94}
		.fl-w-sec.ui-sortable-helper{box-shadow:0 10px 30px rgba(43,29,33,.18)}
		.fl-w-sortable .ui-sortable-placeholder{visibility:visible!important;border:2px dashed #c9a2ad;background:#fcf8f8;border-radius:12px;margin:0 0 10px}
		.fl-w-head{display:flex;align-items:center;gap:12px;padding:12px 16px}
		.fl-w-drag{cursor:grab;color:#8c8f94}
		.fl-w-toggle{all:unset;cursor:pointer;flex:1;min-width:0}
		.fl-w-toggle strong{display:block;font-size:14px;color:#2b1d21}
		.fl-w-toggle small{display:block;color:#646970;margin-top:2px}
		.fl-w-toggle::after{content:"\f140";font-family:dashicons;float:right;margin-top:-30px;color:#8c8f94;font-size:20px}
		.fl-w-toggle[aria-expanded=true]::after{content:"\f142"}
		.fl-w-toggle:focus-visible{outline:2px solid #9e4f63;outline-offset:4px;border-radius:4px}
		.fl-w-state{font-size:12px;font-weight:600;color:#1a7f37;min-width:52px;text-align:right}
		.fl-w-sec.is-off .fl-w-state{color:#8c8f94}
		.fl-w-move .button-link{font-size:16px;text-decoration:none;padding:0 4px}
		.fl-w-body{border-top:1px solid #f1e8e9;padding:16px;background:#fdfafa}
		.fl-w-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px 18px}
		.fl-w-field{grid-column:1/-1;min-width:0}
		.fl-w-field.is-half{grid-column:auto}
		.fl-w-field > label{display:block;font-weight:600;margin:0 0 4px}
		.fl-w-field input[type=text],.fl-w-field textarea,.fl-w-field select{width:100%}
		.fl-w-field .description{margin:4px 0 0}
		.fl-w-img{display:flex;gap:12px;align-items:center}
		.fl-w-img img{width:96px;height:96px;object-fit:cover;border-radius:10px;border:1px solid #e2d6d8;background:#fff}
		.fl-w-link{display:grid;gap:6px}
		.fl-w-reset-wrap{margin:14px 0 0;text-align:right}
		.fl-w-reset{color:#b32d2e}
		.fl-w-save{position:sticky;bottom:0;background:rgba(240,240,241,.95);padding:12px 0;display:flex;gap:12px;align-items:center;z-index:5}
		.fl-w-dirty{color:#9e4f63;font-weight:600}
		.fl-switch{position:relative;width:42px;height:24px;flex:none;cursor:pointer}
		.fl-switch input{position:absolute;opacity:0;inset:0;margin:0;cursor:pointer}
		.fl-switch span{position:absolute;inset:0;border-radius:99px;background:#d0c4c6;transition:.2s}
		.fl-switch span::after{content:"";position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;background:#fff;transition:.2s;box-shadow:0 1px 2px rgba(0,0,0,.25)}
		.fl-switch input:checked+span{background:#1a7f37}
		.fl-switch input:checked+span::after{transform:translateX(18px)}
		.fl-switch input:focus-visible+span{outline:2px solid #9e4f63;outline-offset:2px}
		@media (max-width:782px){.fl-w-grid{grid-template-columns:1fr}.fl-w-field.is-half{grid-column:1/-1}.fl-w-move{display:none}}
	</style>
	<script>
	jQuery( function ( $ ) {
		var form = $( '#fl-w-form' ), dirty = false;
		function changed() { if ( ! dirty ) { dirty = true; $( '.fl-w-dirty' ).prop( 'hidden', false ); } }
		form.on( 'input change', changed );
		window.addEventListener( 'beforeunload', function ( e ) { if ( dirty ) { e.preventDefault(); e.returnValue = ''; } } );
		form.on( 'submit', function () { dirty = false; } );

		$( '.fl-w-sortable' ).sortable( { handle: '.fl-w-head', cancel: 'button,input,label,select,a', placeholder: 'ui-sortable-placeholder', forcePlaceholderSize: true, update: changed } );
		form.on( 'click', '.fl-w-up, .fl-w-down', function () {
			var li = $( this ).closest( '.fl-w-sec' );
			if ( $( this ).hasClass( 'fl-w-up' ) ) { li.prev().before( li ); } else { li.next().after( li ); }
			changed(); $( this ).trigger( 'focus' );
		} );
		form.on( 'click', '.fl-w-toggle', function () {
			var open = $( this ).attr( 'aria-expanded' ) !== 'true';
			$( this ).attr( 'aria-expanded', open ? 'true' : 'false' ).closest( '.fl-w-sec' ).find( '.fl-w-body' ).prop( 'hidden', ! open );
		} );
		form.on( 'change', '.fl-switch input', function () {
			var li = $( this ).closest( '.fl-w-sec' );
			li.toggleClass( 'is-off', ! this.checked );
			li.find( '.fl-w-state' ).text( this.checked ? <?php echo wp_json_encode( __( 'Shown', 'fleuriste' ) ); ?> : <?php echo wp_json_encode( __( 'Hidden', 'fleuriste' ) ); ?> );
		} );
		form.on( 'change', '.fl-w-link select', function () {
			$( this ).siblings( '.fl-w-custom' ).prop( 'hidden', this.value !== 'custom' );
		} );
		form.on( 'click', '.fl-w-pick', function () {
			var box = $( this ).closest( '.fl-w-field' ), frame = wp.media( { title: <?php echo wp_json_encode( __( 'Choose a photo', 'fleuriste' ) ); ?>, library: { type: 'image' }, multiple: false } );
			frame.on( 'select', function () {
				var a = frame.state().get( 'selection' ).first().toJSON();
				box.find( 'input[type=hidden]' ).val( a.id );
				box.find( 'img' ).attr( 'src', a.sizes && a.sizes.medium ? a.sizes.medium.url : a.url );
				box.find( '.fl-w-clear' ).prop( 'hidden', false ); changed();
			} );
			frame.open();
		} );
		form.on( 'click', '.fl-w-clear', function () {
			var box = $( this ).closest( '.fl-w-field' );
			box.find( 'input[type=hidden]' ).val( '' );
			box.find( 'img' ).attr( 'src', box.find( '.fl-w-img-default' ).data( 'src' ) );
			$( this ).prop( 'hidden', true ); changed();
		} );
		form.on( 'click', '.fl-w-reset', function () {
			if ( ! window.confirm( <?php echo wp_json_encode( __( 'Put back the default texts and photos for this section? Click Save to keep the change.', 'fleuriste' ) ); ?> ) ) { return; }
			var body = $( this ).closest( '.fl-w-body' );
			body.find( '[data-default]' ).each( function () {
				var d = $( this ).data( 'default' );
				if ( this.type === 'checkbox' ) { this.checked = String( d ) === '1'; }
				else if ( $( this ).is( 'select.fl-w-products' ) ) { return; }
				else if ( $( this ).is( 'select' ) && $( this ).closest( '.fl-w-link' ).length ) { $( this ).val( String( d ) ).trigger( 'change' ); if ( $( this ).val() === null ) { $( this ).val( 'custom' ).trigger( 'change' ); $( this ).siblings( '.fl-w-custom' ).val( String( d ) ); } }
				else { $( this ).val( String( d ) ); }
			} );
			body.find( '.fl-w-clear:not([hidden])' ).trigger( 'click' );
			body.find( 'select.fl-w-products' ).val( null ).trigger( 'change' );
			changed();
		} );
	} );
	</script>
	<?php
}
