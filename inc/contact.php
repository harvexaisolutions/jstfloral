<?php
/**
 * Contact page: the shop's contact details and a simple contact form.
 *
 * Shortcodes: [fleuriste_contact_details] and [fleuriste_contact_form].
 * Details come from Shop settings → Shop details. Messages are emailed to the
 * shop email (or the site's admin email).
 *
 * @package Fleuriste
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The shop's contact details.
 *
 * @return array address (lines), phone, email, whatsapp (number), hours (lines).
 */
function fleuriste_contact_info() {
	$c     = function_exists( 'fleuriste_company' ) ? fleuriste_company() : array();
	$lines = function ( $text ) {
		return array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $text ) ) ) );
	};
	return array(
		'address'  => $lines( isset( $c['address'] ) ? $c['address'] : '' ),
		'phone'    => isset( $c['phone'] ) ? (string) $c['phone'] : '',
		'email'    => ! empty( $c['email'] ) ? (string) $c['email'] : '',
		'whatsapp' => isset( $c['whatsapp'] ) ? preg_replace( '/\D+/', '', (string) $c['whatsapp'] ) : '',
		'hours'    => $lines( isset( $c['hours'] ) ? $c['hours'] : '' ),
	);
}

add_shortcode(
	'fleuriste_contact_details',
	function () {
		$i    = fleuriste_contact_info();
		$rows = array();
		if ( $i['address'] ) {
			$rows[] = array( __( 'Our shop', 'fleuriste' ), implode( '<br>', array_map( 'esc_html', $i['address'] ) ) );
		}
		if ( $i['phone'] ) {
			$rows[] = array( __( 'Phone', 'fleuriste' ), '<a href="tel:' . esc_attr( preg_replace( '/[^\d+]/', '', $i['phone'] ) ) . '">' . esc_html( $i['phone'] ) . '</a>' );
		}
		if ( $i['whatsapp'] ) {
			$wa     = strlen( $i['whatsapp'] ) <= 8 ? '230' . $i['whatsapp'] : $i['whatsapp'];
			$rows[] = array( 'WhatsApp', '<a href="' . esc_url( 'https://wa.me/' . $wa ) . '" target="_blank" rel="noopener">' . esc_html__( 'Send us a message', 'fleuriste' ) . '</a>' );
		}
		if ( $i['email'] ) {
			$rows[] = array( __( 'Email', 'fleuriste' ), '<a href="mailto:' . esc_attr( $i['email'] ) . '">' . esc_html( $i['email'] ) . '</a>' );
		}
		if ( $i['hours'] ) {
			$rows[] = array( __( 'Opening hours', 'fleuriste' ), implode( '<br>', array_map( 'esc_html', $i['hours'] ) ) );
		}
		if ( ! $rows ) {
			return current_user_can( 'manage_woocommerce' ) ? '<p>' . esc_html__( 'Add your address, phone and email in Shop settings → Shop details.', 'fleuriste' ) . '</p>' : '';
		}
		$html = '<dl class="fl-contact-details">';
		foreach ( $rows as $r ) {
			$html .= '<div><dt>' . esc_html( $r[0] ) . '</dt><dd>' . $r[1] . '</dd></div>'; // Values escaped above.
		}
		return $html . '</dl>';
	}
);

add_shortcode(
	'fleuriste_contact_form',
	function () {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$state = isset( $_GET['contact'] ) ? sanitize_key( wp_unslash( $_GET['contact'] ) ) : '';
		// phpcs:enable
		$html = '';
		if ( 'sent' === $state ) {
			$html .= '<p class="fl-contact-note is-ok" role="status">' . esc_html__( 'Thank you – your message has been sent. We\'ll get back to you soon.', 'fleuriste' ) . '</p>';
		} elseif ( 'error' === $state ) {
			$html .= '<p class="fl-contact-note is-error" role="alert">' . esc_html__( 'Please fill in your name, a valid email and your message.', 'fleuriste' ) . '</p>';
		} elseif ( 'failed' === $state ) {
			$html .= '<p class="fl-contact-note is-error" role="alert">' . esc_html__( 'Sorry, your message could not be sent. Please call or WhatsApp us instead.', 'fleuriste' ) . '</p>';
		}
		ob_start();
		?>
		<form class="fl-contact-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="fleuriste_contact">
			<input type="hidden" name="back" value="<?php echo esc_url( get_permalink() ); ?>">
			<input type="hidden" name="t" value="<?php echo esc_attr( time() ); ?>">
			<?php wp_nonce_field( 'fleuriste_contact', 'fl_contact_nonce' ); ?>
			<p class="fl-contact-hp" aria-hidden="true"><label><?php esc_html_e( 'Leave this empty', 'fleuriste' ); ?><input type="text" name="website" tabindex="-1" autocomplete="off"></label></p>
			<div class="fl-contact-row">
				<p><label for="fl-c-name"><?php esc_html_e( 'Name', 'fleuriste' ); ?></label><input type="text" id="fl-c-name" name="name" required autocomplete="name"></p>
				<p><label for="fl-c-phone"><?php esc_html_e( 'Phone (optional)', 'fleuriste' ); ?></label><input type="tel" id="fl-c-phone" name="phone" autocomplete="tel"></p>
			</div>
			<p><label for="fl-c-email"><?php esc_html_e( 'Email', 'fleuriste' ); ?></label><input type="email" id="fl-c-email" name="email" required autocomplete="email"></p>
			<p><label for="fl-c-msg"><?php esc_html_e( 'Message', 'fleuriste' ); ?></label><textarea id="fl-c-msg" name="message" rows="6" required></textarea></p>
			<p><button type="submit" class="wp-element-button"><?php esc_html_e( 'Send message', 'fleuriste' ); ?></button></p>
		</form>
		<?php
		return $html . ob_get_clean();
	}
);

/**
 * Handle the contact form.
 */
function fleuriste_contact_submit() {
	$post = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked below.
	$back = isset( $post['back'] ) ? esc_url_raw( $post['back'] ) : home_url( '/' );
	$back = wp_validate_redirect( $back, home_url( '/' ) );
	$go   = function ( $state ) use ( $back ) {
		wp_safe_redirect( add_query_arg( 'contact', $state, $back ) . '#fl-contact' );
		exit;
	};
	if ( empty( $post['fl_contact_nonce'] ) || ! wp_verify_nonce( $post['fl_contact_nonce'], 'fleuriste_contact' ) ) {
		$go( 'error' );
	}
	// Spam traps: the hidden field must stay empty and a person takes a few seconds to write.
	if ( ! empty( $post['website'] ) || ( isset( $post['t'] ) && time() - (int) $post['t'] < 3 ) ) {
		$go( 'sent' );
	}
	$name    = isset( $post['name'] ) ? sanitize_text_field( $post['name'] ) : '';
	$email   = isset( $post['email'] ) ? sanitize_email( $post['email'] ) : '';
	$phone   = isset( $post['phone'] ) ? sanitize_text_field( $post['phone'] ) : '';
	$message = isset( $post['message'] ) ? sanitize_textarea_field( $post['message'] ) : '';
	if ( '' === $name || ! is_email( $email ) || '' === trim( $message ) ) {
		$go( 'error' );
	}
	$info = fleuriste_contact_info();
	$to   = $info['email'] && is_email( $info['email'] ) ? $info['email'] : get_option( 'admin_email' );
	/* translators: 1: shop name, 2: sender name */
	$subject = sprintf( __( '[%1$s] New message from %2$s', 'fleuriste' ), get_bloginfo( 'name' ), $name );
	$body    = sprintf( "%s: %s\n%s: %s\n%s: %s\n\n%s", __( 'Name', 'fleuriste' ), $name, __( 'Email', 'fleuriste' ), $email, __( 'Phone', 'fleuriste' ), $phone ? $phone : '–', $message );
	$sent    = wp_mail( $to, $subject, $body, array( 'Reply-To: ' . $name . ' <' . $email . '>' ) );
	$go( $sent ? 'sent' : 'failed' );
}
add_action( 'admin_post_fleuriste_contact', 'fleuriste_contact_submit' );
add_action( 'admin_post_nopriv_fleuriste_contact', 'fleuriste_contact_submit' );

/**
 * Page content: details on one side, the form on the other.
 *
 * @return string
 */
function fleuriste_contact_page_markup() {
	return '<!-- wp:paragraph {"align":"center","className":"fl-contact-intro"} -->
<p class="has-text-align-center fl-contact-intro">' . esc_html__( 'Questions about an order, a special request or a custom arrangement? Get in touch – we\'re happy to help.', 'fleuriste' ) . '</p>
<!-- /wp:paragraph -->

<!-- wp:columns {"align":"wide","className":"fl-contact"} -->
<div class="wp-block-columns alignwide fl-contact" id="fl-contact"><!-- wp:column {"width":"38%","className":"fl-contact__info"} -->
<div class="wp-block-column fl-contact__info" style="flex-basis:38%"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">' . esc_html__( 'Contact details', 'fleuriste' ) . '</h3>
<!-- /wp:heading -->

<!-- wp:shortcode -->
[fleuriste_contact_details]
<!-- /wp:shortcode --></div>
<!-- /wp:column -->

<!-- wp:column {"width":"62%","className":"fl-contact__form"} -->
<div class="wp-block-column fl-contact__form" style="flex-basis:62%"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">' . esc_html__( 'Send us a message', 'fleuriste' ) . '</h3>
<!-- /wp:heading -->

<!-- wp:shortcode -->
[fleuriste_contact_form]
<!-- /wp:shortcode --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->';
}

// Once: the Contact page (/contact/ or /contact-us/) gets the simple layout, or is created.
// The previous content stays in the page's revisions.
add_action(
	'admin_init',
	function () {
		if ( get_option( 'fleuriste_contact_v1' ) || ! current_user_can( 'edit_pages' ) || wp_doing_ajax() ) {
			return;
		}
		update_option( 'fleuriste_contact_v1', time() );
		$page = get_page_by_path( 'contact' );
		$page = $page ? $page : get_page_by_path( 'contact-us' );
		$data = array(
			'post_title'   => __( 'Contact us', 'fleuriste' ),
			'post_content' => fleuriste_contact_page_markup(),
			'post_status'  => 'publish',
			'post_type'    => 'page',
		);
		if ( $page ) {
			$data['ID'] = $page->ID;
			$id         = wp_update_post( wp_slash( $data ) );
		} else {
			$data['post_name'] = 'contact';
			$id                = wp_insert_post( wp_slash( $data ) );
		}
		if ( $id && ! is_wp_error( $id ) ) {
			update_post_meta( $id, '_wp_page_template', 'default' );
		}
	}
);
