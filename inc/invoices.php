<?php
/**
 * Invoices and receipts (PDF) with the company's legal details.
 *
 * Shop settings → Shop details: shop name, official company name, BRN,
 * VAT number, address, logo, footer, numbering and email attachments.
 *
 * An invoice can be made for any order; a receipt once the order is paid.
 * Numbers are given the first time a document is made and never change.
 *
 * @package Fleuriste
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/lib/class-fleuriste-pdf.php';

/**
 * Is the "Print Invoice & Delivery Notes" plugin active? Then it makes the invoices
 * and receipts (its own numbering and emails) and we add the legal details to them.
 *
 * @return bool
 */
function fleuriste_wcdn_active() {
	return class_exists( '\\Tyche\\WCDN\\Helpers\\Utils' ) && method_exists( '\\Tyche\\WCDN\\Helpers\\Utils', 'get_print_page_url' );
}

/**
 * Print link of the plugin for an order.
 *
 * @param int         $order_id  Order ID.
 * @param string      $type      invoice|receipt|deliverynote.
 * @param string|null $email     Customer email (for customer links).
 * @param bool        $permalink Customer-facing link.
 * @return string
 */
function fleuriste_wcdn_url( $order_id, $type, $email = null, $permalink = false ) {
	return (string) \Tyche\WCDN\Helpers\Utils::get_print_page_url( array( $order_id ), $type, $email, $permalink );
}

/* -------------------------------------------------------------------------
 * Settings
 * ---------------------------------------------------------------------- */

/**
 * Company details with defaults.
 *
 * @return array
 */
function fleuriste_company() {
	$saved = get_option( 'fleuriste_company', array() );
	$c     = wp_parse_args(
		is_array( $saved ) ? $saved : array(),
		array(
			'legal_name'     => '',
			'brn'            => '',
			'vat'            => '',
			'street'         => '',
			'town'           => '',
			'region'         => '',
			'address'        => '',
			'phone'          => '',
			'email'          => get_option( 'woocommerce_email_from_address', get_option( 'admin_email' ) ),
			'whatsapp'       => '',
			'hours'          => "Mon–Sat 8AM – 6PM\nSun 9AM – 1PM",
			'website'        => home_url( '/' ),
			'logo_id'        => 0,
			'invoice_title'  => __( 'Invoice', 'fleuriste' ),
			'receipt_title'  => __( 'Receipt', 'fleuriste' ),
			'inv_prefix'     => 'INV-',
			'rcpt_prefix'    => 'RC-',
			'bank'           => '',
			'footer'         => __( 'Thank you for your order!', 'fleuriste' ),
			'attach_invoice' => '',
			'attach_receipt' => 'yes',
			'customer_links' => 'yes',
		)
	);
	// The shop name is the site title (Settings → General), the logo is the site logo:
	// one place each, edited on Shop settings → Shop details.
	$c['trading_name'] = get_bloginfo( 'name' );
	$site_logo         = (int) get_theme_mod( 'custom_logo' );
	$c['logo_id']      = $site_logo ? $site_logo : (int) $c['logo_id'];
	// The address, one line per part, built from street / town / region.
	if ( '' !== trim( $c['street'] ) ) {
		$region      = ( $c['region'] && function_exists( 'fleuriste_dl_region' ) ) ? fleuriste_dl_region( $c['region'] ) : null;
		$town        = '' !== trim( $c['town'] ) ? $c['town'] : ( $region ? $region['name'] : '' );
		$c['address'] = implode( "\n", array_filter( array( trim( $c['street'] ), trim( $town ), 'Mauritius' ) ) );
	}
	return $c;
}

/**
 * The shop's WhatsApp link (wa.me), or ''.
 *
 * @return string
 */
function fleuriste_company_whatsapp_url() {
	$n = preg_replace( '/\D+/', '', (string) fleuriste_company()['whatsapp'] );
	return '' === $n ? '' : 'https://wa.me/' . ( strlen( $n ) <= 8 ? '230' . $n : $n );
}

add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'fleuriste-deliveries', __( 'Shop details', 'fleuriste' ), __( 'Shop details', 'fleuriste' ), 'manage_woocommerce', 'fleuriste-company', 'fleuriste_company_page', 6 );
	},
	20
);

add_action(
	'admin_post_fleuriste_company',
	function () {
		check_admin_referer( 'fleuriste_company' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'fleuriste' ) );
		}
		$in   = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below.
		$text = function ( $k ) use ( $in ) {
			return isset( $in[ $k ] ) ? sanitize_text_field( $in[ $k ] ) : '';
		};
		$area = function ( $k ) use ( $in ) {
			return isset( $in[ $k ] ) ? sanitize_textarea_field( $in[ $k ] ) : '';
		};
		if ( '' !== $text( 'trading_name' ) ) {
			update_option( 'blogname', $text( 'trading_name' ) );
		}
		$logo_id = isset( $in['logo_id'] ) ? absint( $in['logo_id'] ) : 0;
		if ( $logo_id ) {
			set_theme_mod( 'custom_logo', $logo_id );
		} else {
			remove_theme_mod( 'custom_logo' );
		}
		update_option(
			'fleuriste_company',
			array(
				'legal_name'     => $text( 'legal_name' ),
				'brn'            => strtoupper( $text( 'brn' ) ),
				'vat'            => $text( 'vat' ),
				'street'         => $text( 'street' ),
				'town'           => $text( 'town' ),
				'region'         => $text( 'region' ),
				'address'        => '',
				'phone'          => $text( 'phone' ),
				'email'          => isset( $in['email'] ) ? sanitize_email( $in['email'] ) : '',
				'whatsapp'       => $text( 'whatsapp' ),
				'hours'          => $area( 'hours' ),
				'website'        => isset( $in['website'] ) ? esc_url_raw( $in['website'] ) : '',
				'logo_id'        => $logo_id,
				'invoice_title'  => $text( 'invoice_title' ),
				'receipt_title'  => $text( 'receipt_title' ),
				'inv_prefix'     => $text( 'inv_prefix' ),
				'rcpt_prefix'    => $text( 'rcpt_prefix' ),
				'bank'           => $area( 'bank' ),
				'footer'         => $area( 'footer' ),
				'attach_invoice' => empty( $in['attach_invoice'] ) ? '' : 'yes',
				'attach_receipt' => empty( $in['attach_receipt'] ) ? '' : 'yes',
				'customer_links' => empty( $in['customer_links'] ) ? '' : 'yes',
			),
			false
		);
		foreach ( array( 'invoice', 'receipt' ) as $type ) {
			$next = isset( $in[ 'next_' . $type ] ) ? absint( $in[ 'next_' . $type ] ) : 0;
			if ( $next > 0 ) {
				update_option( 'fleuriste_next_' . $type, $next, false );
			}
		}
		if ( function_exists( 'fleuriste_dl_sync' ) ) {
			fleuriste_dl_sync(); // Pickup address follows the shop address.
		}
		wp_safe_redirect( admin_url( 'admin.php?page=fleuriste-company&saved=1' ) );
		exit;
	}
);

/**
 * Shop settings → Shop details (the one place for the shop's name, address, contacts and logo).
 */
function fleuriste_company_page() {
	wp_enqueue_media();
	$c    = fleuriste_company();
	$logo = $c['logo_id'] ? wp_get_attachment_image_url( $c['logo_id'], 'medium' ) : '';
	// A recent order to preview the documents with.
	$sample = wc_get_orders(
		array(
			'limit'   => 1,
			'orderby' => 'date',
			'order'   => 'DESC',
			'type'    => 'shop_order',
			'status'  => array( 'wc-completed', 'wc-processing', 'wc-preparing', 'wc-out-for-delivery', 'wc-on-hold' ),
		)
	);
	$sample = $sample ? $sample[0] : null;
	$field  = function ( $name, $label, $value, $help = '', $type = 'text', $attrs = '' ) {
		printf(
			'<tr><th scope="row"><label for="fl-co-%1$s">%2$s</label></th><td><input type="%3$s" class="regular-text" id="fl-co-%1$s" name="%1$s" value="%4$s" %6$s>%5$s</td></tr>',
			esc_attr( $name ),
			esc_html( $label ),
			esc_attr( $type ),
			esc_attr( $value ),
			$help ? '<p class="description">' . esc_html( $help ) . '</p>' : '',
			$attrs // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed strings.
		);
	};
	?>
	<div class="wrap fl-co">
		<h1><?php esc_html_e( 'Shop details', 'fleuriste' ); ?></h1>
		<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Saved. The new details are used everywhere straight away.', 'fleuriste' ); ?></p></div>
		<?php endif; ?>
		<div class="fl-co__where">
			<strong><?php esc_html_e( 'Enter these once – they are used everywhere:', 'fleuriste' ); ?></strong>
			<?php esc_html_e( 'header and footer, Contact page, invoices and receipts, the pickup address at checkout, order emails and WooCommerce\'s store address.', 'fleuriste' ); ?>
		</div>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="fleuriste_company">
			<?php wp_nonce_field( 'fleuriste_company' ); ?>

			<h2><?php esc_html_e( 'Your shop', 'fleuriste' ); ?></h2>
			<table class="form-table" role="presentation">
				<?php
				$field( 'trading_name', __( 'Shop name', 'fleuriste' ), $c['trading_name'], __( 'The name customers know, e.g. JST Flowers. Also the site title.', 'fleuriste' ) );
				$field( 'street', __( 'Street address', 'fleuriste' ), $c['street'], __( 'e.g. 9 Bourbon Street', 'fleuriste' ) );
				$field( 'town', __( 'Town', 'fleuriste' ), $c['town'], __( 'e.g. Port Louis', 'fleuriste' ) );
				$regions = function_exists( 'fleuriste_dl_regions' ) ? fleuriste_dl_regions() : array();
				if ( $regions ) :
					?>
					<tr><th scope="row"><label for="fl-co-region"><?php esc_html_e( 'Region', 'fleuriste' ); ?></label></th><td>
						<select id="fl-co-region" name="region"><option value=""><?php esc_html_e( '—', 'fleuriste' ); ?></option>
							<?php foreach ( $regions as $r ) : ?>
								<option value="<?php echo esc_attr( $r['id'] ); ?>" <?php selected( strtoupper( (string) $c['region'] ), strtoupper( $r['id'] ) ); ?>><?php echo esc_html( $r['name'] ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Used for the pickup location at checkout. Regions: Shop settings → Delivery & pickup.', 'fleuriste' ); ?></p>
					</td></tr>
					<?php
				endif;
				$field( 'phone', __( 'Phone', 'fleuriste' ), $c['phone'] );
				$field( 'whatsapp', __( 'WhatsApp (optional)', 'fleuriste' ), $c['whatsapp'], __( 'Shown to customers (footer and Contact page). Leave empty to hide.', 'fleuriste' ) );
				$field( 'email', __( 'Email', 'fleuriste' ), $c['email'], __( 'Contact page, invoices; messages from the contact form go here.', 'fleuriste' ), 'email' );
				?>
				<tr><th scope="row"><label for="fl-co-hours"><?php esc_html_e( 'Opening hours', 'fleuriste' ); ?></label></th><td><textarea class="large-text" rows="3" id="fl-co-hours" name="hours"><?php echo esc_textarea( $c['hours'] ); ?></textarea><p class="description"><?php esc_html_e( 'One line per day or group of days. Shown in the footer and on the Contact page.', 'fleuriste' ); ?></p></td></tr>
				<?php $field( 'website', __( 'Website', 'fleuriste' ), $c['website'], '', 'url' ); ?>
				<tr><th scope="row"><?php esc_html_e( 'Logo', 'fleuriste' ); ?></th><td>
					<div class="fl-co__logo"><img src="<?php echo esc_url( $logo ); ?>" alt=""<?php echo $logo ? '' : ' hidden'; ?>></div>
					<input type="hidden" name="logo_id" id="fl-co-logo" value="<?php echo (int) $c['logo_id']; ?>">
					<button type="button" class="button" id="fl-co-logo-pick"><?php esc_html_e( 'Choose logo', 'fleuriste' ); ?></button>
					<button type="button" class="button-link button-link-delete" id="fl-co-logo-clear"<?php echo $logo ? '' : ' hidden'; ?>><?php esc_html_e( 'Remove', 'fleuriste' ); ?></button>
					<p class="description"><?php esc_html_e( 'Header, footer, invoices and receipts. Without a logo the shop name is shown.', 'fleuriste' ); ?></p>
				</td></tr>
			</table>

			<h2><?php esc_html_e( 'Legal details', 'fleuriste' ); ?> <small style="font-weight:400;color:#646970"><?php esc_html_e( '(printed on invoices and receipts)', 'fleuriste' ); ?></small></h2>
			<table class="form-table" role="presentation">
				<?php
				$field( 'legal_name', __( 'Official company name (optional)', 'fleuriste' ), $c['legal_name'], __( 'The registered name if different, e.g. JST Trading Ltd. Shown as "… is a trading name of …".', 'fleuriste' ) );
				$field( 'brn', __( 'BRN', 'fleuriste' ), $c['brn'], __( 'Business Registration Number, e.g. C07012345.', 'fleuriste' ) );
				$field( 'vat', __( 'VAT registration number (optional)', 'fleuriste' ), $c['vat'], __( 'Only if the business is VAT-registered.', 'fleuriste' ) );
				?>
			</table>

			<?php if ( fleuriste_wcdn_active() ) : ?>
				<h2><?php esc_html_e( 'Invoices and receipts', 'fleuriste' ); ?></h2>
				<div class="notice notice-info inline"><p>
					<?php esc_html_e( 'Invoices, receipts and delivery notes are made by the "Print Invoice & Delivery Notes" plugin. The details above (name, official company name, BRN, VAT number, address, phone, email and logo) are printed on them automatically.', 'fleuriste' ); ?>
					<?php esc_html_e( 'Numbering, titles, footer text and emailing are set in the plugin\'s own settings.', 'fleuriste' ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcdn_page' ) ); ?>"><?php esc_html_e( 'Plugin settings', 'fleuriste' ); ?></a>
				</p></div>
				<?php
				foreach ( array( 'invoice_title', 'receipt_title', 'inv_prefix', 'rcpt_prefix', 'bank', 'footer', 'attach_invoice', 'attach_receipt', 'customer_links' ) as $keep ) {
					echo '<input type="hidden" name="' . esc_attr( $keep ) . '" value="' . esc_attr( $c[ $keep ] ) . '">';
				}
				?>
			<?php else : ?>
			<h2><?php esc_html_e( 'Invoices and receipts', 'fleuriste' ); ?></h2>
			<table class="form-table" role="presentation">
				<?php
				$field( 'invoice_title', __( 'Invoice title', 'fleuriste' ), $c['invoice_title'], __( 'e.g. Invoice, or VAT Invoice if VAT-registered.', 'fleuriste' ) );
				$field( 'receipt_title', __( 'Receipt title', 'fleuriste' ), $c['receipt_title'] );
				?>
				<tr><th scope="row"><?php esc_html_e( 'Invoice numbers', 'fleuriste' ); ?></th><td>
					<label><?php esc_html_e( 'Prefix', 'fleuriste' ); ?> <input type="text" name="inv_prefix" value="<?php echo esc_attr( $c['inv_prefix'] ); ?>" class="small-text"></label>
					<label><?php esc_html_e( 'Next number', 'fleuriste' ); ?> <input type="number" min="1" name="next_invoice" value="<?php echo (int) get_option( 'fleuriste_next_invoice', 1 ); ?>" class="small-text"></label>
					<p class="description"><?php echo esc_html( sprintf( /* translators: %s: example */ __( 'Next invoice: %s. Change the number to continue from a paper invoice book.', 'fleuriste' ), fleuriste_doc_format( 'invoice', (int) get_option( 'fleuriste_next_invoice', 1 ) ) ) ); ?></p>
				</td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Receipt numbers', 'fleuriste' ); ?></th><td>
					<label><?php esc_html_e( 'Prefix', 'fleuriste' ); ?> <input type="text" name="rcpt_prefix" value="<?php echo esc_attr( $c['rcpt_prefix'] ); ?>" class="small-text"></label>
					<label><?php esc_html_e( 'Next number', 'fleuriste' ); ?> <input type="number" min="1" name="next_receipt" value="<?php echo (int) get_option( 'fleuriste_next_receipt', 1 ); ?>" class="small-text"></label>
				</td></tr>
				<tr><th scope="row"><label for="fl-co-bank"><?php esc_html_e( 'Bank details (optional)', 'fleuriste' ); ?></label></th><td><textarea class="large-text" rows="3" id="fl-co-bank" name="bank" placeholder="<?php esc_attr_e( "Bank: MCB\nAccount: 000 000 000\nIBAN: MU…", 'fleuriste' ); ?>"><?php echo esc_textarea( $c['bank'] ); ?></textarea><p class="description"><?php esc_html_e( 'Printed on invoices that are not paid yet.', 'fleuriste' ); ?></p></td></tr>
				<tr><th scope="row"><label for="fl-co-footer"><?php esc_html_e( 'Footer note', 'fleuriste' ); ?></label></th><td><textarea class="large-text" rows="2" id="fl-co-footer" name="footer"><?php echo esc_textarea( $c['footer'] ); ?></textarea></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Send automatically', 'fleuriste' ); ?></th><td>
					<label><input type="checkbox" name="attach_invoice" value="1" <?php checked( 'yes', $c['attach_invoice'] ); ?>> <?php esc_html_e( 'Attach the invoice to the order confirmation email', 'fleuriste' ); ?></label><br>
					<label><input type="checkbox" name="attach_receipt" value="1" <?php checked( 'yes', $c['attach_receipt'] ); ?>> <?php esc_html_e( 'Attach the receipt to the "order delivered" (completed) email', 'fleuriste' ); ?></label><br>
					<label><input type="checkbox" name="customer_links" value="1" <?php checked( 'yes', $c['customer_links'] ); ?>> <?php esc_html_e( 'Let customers download them from My Account and the order tracking page', 'fleuriste' ); ?></label>
				</td></tr>
			</table>
			<?php endif; ?>
			<p class="submit">
				<button class="button button-primary"><?php esc_html_e( 'Save', 'fleuriste' ); ?></button>
				<?php if ( $sample && fleuriste_wcdn_active() ) : ?>
					<a class="button" target="_blank" href="<?php echo esc_url( fleuriste_wcdn_url( $sample->get_id(), 'invoice' ) ); ?>"><?php esc_html_e( 'Preview an invoice', 'fleuriste' ); ?></a>
					<a class="button" target="_blank" href="<?php echo esc_url( fleuriste_wcdn_url( $sample->get_id(), 'receipt' ) ); ?>"><?php esc_html_e( 'Preview a receipt', 'fleuriste' ); ?></a>
					<span class="description"><?php echo esc_html( sprintf( /* translators: %s: order number */ __( '(order #%s – save first)', 'fleuriste' ), $sample->get_order_number() ) ); ?></span>
				<?php elseif ( $sample ) : ?>
					<a class="button" target="_blank" href="<?php echo esc_url( fleuriste_doc_admin_url( $sample, 'invoice', true ) ); ?>"><?php esc_html_e( 'Preview an invoice', 'fleuriste' ); ?></a>
					<?php if ( $sample->get_date_paid() ) : ?>
						<a class="button" target="_blank" href="<?php echo esc_url( fleuriste_doc_admin_url( $sample, 'receipt', true ) ); ?>"><?php esc_html_e( 'Preview a receipt', 'fleuriste' ); ?></a>
					<?php endif; ?>
					<span class="description"><?php echo esc_html( sprintf( /* translators: %s: order number */ __( '(using order #%s; previews don\'t use up a number – save first)', 'fleuriste' ), $sample->get_order_number() ) ); ?></span>
				<?php endif; ?>
			</p>
		</form>
	</div>
	<style>.fl-co{max-width:900px}.fl-co__where{background:#fff;border-left:4px solid #9e4f63;padding:10px 14px;margin:12px 0 6px}.fl-co__logo img{max-width:220px;max-height:90px;display:block;margin:0 0 8px;border:1px solid #dcdcde;padding:6px;background:#fff}.fl-co label{margin-right:12px}</style>
	<script>
	( function () {
		var pick = document.getElementById( 'fl-co-logo-pick' ), clear = document.getElementById( 'fl-co-logo-clear' );
		var input = document.getElementById( 'fl-co-logo' ), img = document.querySelector( '.fl-co__logo img' ), frame;
		pick.addEventListener( 'click', function () {
			if ( ! frame ) {
				frame = wp.media( { title: <?php echo wp_json_encode( __( 'Choose a logo', 'fleuriste' ) ); ?>, library: { type: 'image' }, multiple: false } );
				frame.on( 'select', function () {
					var a = frame.state().get( 'selection' ).first().toJSON();
					input.value = a.id; img.src = ( a.sizes && a.sizes.medium ? a.sizes.medium.url : a.url ); img.hidden = false; clear.hidden = false;
				} );
			}
			frame.open();
		} );
		clear.addEventListener( 'click', function () { input.value = 0; img.hidden = true; clear.hidden = true; } );
	} )();
	</script>
	<?php
}

/* -------------------------------------------------------------------------
 * Numbers
 * ---------------------------------------------------------------------- */

/**
 * Format a document number, e.g. INV-00012.
 *
 * @param string $type invoice|receipt.
 * @param int    $n    Number.
 * @return string
 */
function fleuriste_doc_format( $type, $n ) {
	$c = fleuriste_company();
	return ( 'receipt' === $type ? $c['rcpt_prefix'] : $c['inv_prefix'] ) . str_pad( (string) $n, 5, '0', STR_PAD_LEFT );
}

/**
 * Number and date of an order's invoice/receipt; given the first time.
 *
 * @param WC_Order $order   Order.
 * @param string   $type    invoice|receipt.
 * @param bool     $preview Don't give a number (show what it would be).
 * @return array number, date (timestamp).
 */
function fleuriste_doc_number( $order, $type, $preview = false ) {
	$key    = '_fleuriste_' . $type . '_no';
	$number = (string) $order->get_meta( $key );
	if ( '' !== $number ) {
		return array(
			'number' => $number,
			'date'   => (int) $order->get_meta( '_fleuriste_' . $type . '_date' ),
		);
	}
	$next = max( 1, (int) get_option( 'fleuriste_next_' . $type, 1 ) );
	if ( $preview ) {
		return array(
			'number' => fleuriste_doc_format( $type, $next ) . ' (' . __( 'preview', 'fleuriste' ) . ')',
			'date'   => time(),
		);
	}
	update_option( 'fleuriste_next_' . $type, $next + 1, false );
	$number = fleuriste_doc_format( $type, $next );
	$date   = 'receipt' === $type && $order->get_date_paid() ? $order->get_date_paid()->getTimestamp() : time();
	$order->update_meta_data( $key, $number );
	$order->update_meta_data( '_fleuriste_' . $type . '_date', $date );
	$order->save();
	/* translators: 1: document, 2: number */
	$order->add_order_note( sprintf( __( '%1$s %2$s issued.', 'fleuriste' ), 'receipt' === $type ? __( 'Receipt', 'fleuriste' ) : __( 'Invoice', 'fleuriste' ), $number ) );
	return array(
		'number' => $number,
		'date'   => $date,
	);
}

/**
 * Can this order have a receipt (paid, not fully refunded)?
 *
 * @param WC_Order $order Order.
 * @return bool
 */
function fleuriste_can_receipt( $order ) {
	return $order->get_date_paid() && ! $order->has_status( array( 'refunded', 'cancelled', 'pending', 'failed' ) );
}

/* -------------------------------------------------------------------------
 * The PDF
 * ---------------------------------------------------------------------- */

/**
 * Money as plain text.
 *
 * @param float    $n     Amount.
 * @param WC_Order $order Order (currency).
 * @return string
 */
function fleuriste_doc_money( $n, $order ) {
	return html_entity_decode( wp_strip_all_tags( wc_price( $n, array( 'currency' => $order->get_currency() ) ) ), ENT_QUOTES, 'UTF-8' );
}

/**
 * Build an invoice or receipt.
 *
 * @param WC_Order $order   Order.
 * @param string   $type    invoice|receipt.
 * @param bool     $preview Preview (no number used up).
 * @return string PDF bytes.
 */
function fleuriste_doc_pdf( $order, $type, $preview = false ) {
	$c       = fleuriste_company();
	$pdf     = new Fleuriste_PDF( 'P' );
	$doc     = fleuriste_doc_number( $order, $type, $preview );
	$rose    = '#9E4F63';
	$ink     = '#2B1D21';
	$muted   = '#6F6266';
	$left    = 42;
	$right   = $pdf->w - 42;
	$receipt = 'receipt' === $type;
	$title   = $receipt ? ( $c['receipt_title'] ? $c['receipt_title'] : __( 'Receipt', 'fleuriste' ) ) : ( $c['invoice_title'] ? $c['invoice_title'] : __( 'Invoice', 'fleuriste' ) );
	$pdf->add_page();

	// Logo and company.
	$y    = 46;
	$logo = false;
	if ( $c['logo_id'] ) {
		$file = get_attached_file( $c['logo_id'] );
		$logo = $file ? $pdf->load_image( $file ) : false;
	}
	if ( false !== $logo ) {
		list( $iw, $ih ) = $pdf->image_size( $logo );
		$scale           = min( 160 / max( 1, $iw ), 64 / max( 1, $ih ) );
		$pdf->image( $logo, $left, $y - 6, $iw * $scale, $ih * $scale );
		$y += $ih * $scale + 10;
	} else {
		$pdf->text( $left, $y + 12, $c['trading_name'], 18, true, $rose );
		$y += 26;
	}
	$company = array();
	if ( false !== $logo ) {
		$company[] = array( $c['trading_name'], true );
	}
	if ( $c['legal_name'] && strcasecmp( $c['legal_name'], $c['trading_name'] ) ) {
		$company[] = array( $c['legal_name'], false );
	}
	foreach ( array_filter( array_map( 'trim', explode( "\n", $c['address'] ) ) ) as $line ) {
		$company[] = array( $line, false );
	}
	$contact = implode( ' · ', array_filter( array( $c['phone'], $c['email'] ) ) );
	if ( $contact ) {
		$company[] = array( $contact, false );
	}
	$legal = array();
	if ( $c['brn'] ) {
		/* translators: %s: business registration number */
		$legal[] = sprintf( __( 'BRN: %s', 'fleuriste' ), $c['brn'] );
	}
	if ( $c['vat'] ) {
		/* translators: %s: VAT number */
		$legal[] = sprintf( __( 'VAT No: %s', 'fleuriste' ), $c['vat'] );
	}
	if ( $legal ) {
		$company[] = array( implode( '   ', $legal ), true );
	}
	foreach ( $company as $line ) {
		$pdf->text( $left, $y, $pdf->fit( $line[0], 280, 8.6, $line[1] ), 8.6, $line[1], $line[1] ? $ink : $muted );
		$y += 12.5;
	}

	// Title block (right).
	$pdf->text( $right, 62, function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $title ) : strtoupper( $title ), 22, true, $rose, 'R' );
	$meta = array(
		array( $receipt ? __( 'Receipt no.', 'fleuriste' ) : __( 'Invoice no.', 'fleuriste' ), $doc['number'] ),
		array( __( 'Date', 'fleuriste' ), wp_date( 'd/m/Y', $doc['date'] ) ),
		array( __( 'Order', 'fleuriste' ), '#' . $order->get_order_number() . ' · ' . wp_date( 'd/m/Y', $order->get_date_created() ? $order->get_date_created()->getTimestamp() : time() ) ),
	);
	$parent = function_exists( 'fleuriste_parent_order' ) ? fleuriste_parent_order( $order ) : false;
	if ( $parent ) {
		$meta[] = array( __( 'Extra for order', 'fleuriste' ), '#' . $parent->get_order_number() );
	}
	$my = 84;
	foreach ( $meta as $m ) {
		$pdf->text( $right - 120, $my, $m[0], 8.6, false, $muted, 'R' );
		$pdf->text( $right, $my, $m[1], 8.6, true, $ink, 'R' );
		$my += 13;
	}
	if ( $order->has_status( array( 'cancelled', 'refunded' ) ) ) {
		$pdf->text( $right, $my + 6, strtoupper( wc_get_order_status_name( $order->get_status() ) ), 12, true, '#B32D2E', 'R' );
		$my += 18;
	}

	// Bill to / deliver to.
	$y = max( $y, $my ) + 14;
	$pdf->line( $left, $y, $right, $y, '#EBDCDD', 0.8 );
	$y   += 18;
	$col  = ( $right - $left ) / 2;
	$bill = array_filter(
		array(
			trim( $order->get_formatted_billing_full_name() ),
			$order->get_billing_company(),
			trim( $order->get_billing_address_1() . ' ' . $order->get_billing_address_2() ),
			$order->get_billing_city(),
			$order->get_billing_phone(),
			$order->get_billing_email(),
		)
	);
	$data = function_exists( 'fleuriste_board_order_data' ) ? fleuriste_board_order_data( $order ) : array(
		'date' => '',
		'slot' => '',
	);
	$ship = array();
	$is_pickup = function_exists( 'fleuriste_order_is_pickup' ) && fleuriste_order_is_pickup( $order );
	if ( ! $is_pickup && ( $order->get_items( 'shipping' ) || $order->get_shipping_address_1() ) ) {
		$ship = array_filter(
			array(
				trim( $order->get_formatted_shipping_full_name() ),
				trim( $order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2() ),
				trim( implode( ', ', array_filter( array( $order->get_shipping_city(), ( function_exists( 'fleuriste_dl_region' ) && fleuriste_dl_region( $order->get_shipping_state() ) ) ? fleuriste_dl_region( $order->get_shipping_state() )['name'] : '' ) ) ) ),
			)
		);
	} else {
		$ship[] = __( 'Collected from the shop', 'fleuriste' );
		if ( $is_pickup && function_exists( 'fleuriste_dl_pickup_address' ) && fleuriste_dl_pickup_address() ) {
			$ship[] = fleuriste_dl_pickup_address();
		}
	}
	if ( $data['date'] ) {
		$ship[] = date_i18n( 'l j F Y', strtotime( $data['date'] ) ) . ( $data['slot'] ? ' · ' . $data['slot'] : '' );
	}
	$pdf->text( $left, $y, $receipt ? __( 'Received from', 'fleuriste' ) : __( 'Bill to', 'fleuriste' ), 8, true, $rose );
	$pdf->text( $left + $col, $y, __( 'Delivery', 'fleuriste' ), 8, true, $rose );
	$by = $y + 15;
	foreach ( array_values( $bill ) as $i => $line ) {
		$pdf->text( $left, $by + $i * 12.5, $pdf->fit( $line, $col - 16, 9, 0 === $i ), 9, 0 === $i, $ink );
	}
	foreach ( array_values( $ship ) as $i => $line ) {
		$pdf->text( $left + $col, $by + $i * 12.5, $pdf->fit( $line, $col - 4, 9, 0 === $i ), 9, 0 === $i, $ink );
	}
	$y = $by + max( count( $bill ), count( $ship ) ) * 12.5 + 16;

	// Items table.
	$cols = array(
		array( __( 'Description', 'fleuriste' ), 290, 'L' ),
		array( __( 'Qty', 'fleuriste' ), 50, 'R' ),
		array( __( 'Unit price', 'fleuriste' ), 85, 'R' ),
		array( __( 'Amount', 'fleuriste' ), $right - $left - 425, 'R' ),
	);
	$head = function ( $y ) use ( $pdf, $cols, $left, $right, $rose ) {
		$pdf->box( $left, $y, $right - $left, 20, $rose );
		$x = $left;
		foreach ( $cols as $col ) {
			$pdf->text( 'R' === $col[2] ? $x + $col[1] - 8 : $x + 8, $y + 13.5, $col[0], 8.4, true, '#FFFFFF', $col[2] );
			$x += $col[1];
		}
		return $y + 20;
	};
	$y    = $head( $y );
	$skip = array( __( 'Delivery date', 'fleuriste' ), __( 'Delivery time', 'fleuriste' ), __( 'Card message', 'fleuriste' ), __( 'Send anonymously', 'fleuriste' ) );
	$rows = array();
	foreach ( $order->get_items() as $item ) {
		$details = array();
		foreach ( $item->get_formatted_meta_data( '_', true ) as $meta_row ) {
			$k = wp_strip_all_tags( $meta_row->display_key );
			if ( ! in_array( $k, $skip, true ) ) {
				$details[] = $k . ': ' . trim( wp_strip_all_tags( $meta_row->display_value ) );
			}
		}
		$qty    = max( 1, (int) $item->get_quantity() );
		$amount = (float) $item->get_total() + (float) $item->get_total_tax();
		$rows[] = array( $item->get_name(), implode( ' · ', $details ), $qty, $amount / $qty, $amount );
	}
	foreach ( $order->get_fees() as $fee ) {
		$amount = (float) $fee->get_total() + (float) $fee->get_total_tax();
		$rows[] = array( $fee->get_name(), '', 1, $amount, $amount );
	}
	foreach ( $order->get_items( 'shipping' ) as $ship_item ) {
		$amount = (float) $ship_item->get_total() + (float) $ship_item->get_total_tax();
		$rows[] = array( $ship_item->get_name() ? $ship_item->get_name() : __( 'Delivery', 'fleuriste' ), '', 1, $amount, $amount );
	}
	foreach ( $rows as $i => $r ) {
		$h = $r[1] ? 30 : 20;
		if ( $y + $h > $pdf->h - 190 ) {
			$pdf->add_page();
			$y = $head( 50 );
		}
		if ( $i % 2 ) {
			$pdf->box( $left, $y, $right - $left, $h, '#FBF6F7' );
		}
		$pdf->text( $left + 8, $y + 13.5, $pdf->fit( $r[0], $cols[0][1] - 14, 9, true ), 9, true, $ink );
		if ( $r[1] ) {
			$pdf->text( $left + 8, $y + 24.5, $pdf->fit( $r[1], $cols[0][1] - 14, 7.6 ), 7.6, false, $muted );
		}
		$x = $left + $cols[0][1];
		$pdf->text( $x + $cols[1][1] - 8, $y + 13.5, (string) $r[2], 9, false, $ink, 'R' );
		$x += $cols[1][1];
		$pdf->text( $x + $cols[2][1] - 8, $y + 13.5, fleuriste_doc_money( $r[3], $order ), 9, false, $ink, 'R' );
		$x += $cols[2][1];
		$pdf->text( $x + $cols[3][1] - 8, $y + 13.5, fleuriste_doc_money( $r[4], $order ), 9, false, $ink, 'R' );
		$y += $h;
	}
	$pdf->line( $left, $y, $right, $y, '#EBDCDD', 0.8 );

	// Totals.
	$tx     = $right - 230;
	$y     += 18;
	$totals = array();
	foreach ( $order->get_tax_totals() as $tax ) {
		$totals[] = array( $tax->label, fleuriste_doc_money( $tax->amount, $order ), false );
	}
	$totals[] = array( __( 'Total', 'fleuriste' ), fleuriste_doc_money( $order->get_total(), $order ), true );
	$refunded = (float) $order->get_total_refunded();
	if ( $refunded > 0 ) {
		$totals[] = array( __( 'Refunded', 'fleuriste' ), '−' . fleuriste_doc_money( $refunded, $order ), false );
	}
	if ( $receipt ) {
		$totals[] = array( __( 'Amount received', 'fleuriste' ), fleuriste_doc_money( (float) $order->get_total() - $refunded, $order ), true );
	} elseif ( $order->get_date_paid() ) {
		$totals[] = array( __( 'Paid', 'fleuriste' ), fleuriste_doc_money( (float) $order->get_total() - $refunded, $order ), false );
		$totals[] = array( __( 'Amount due', 'fleuriste' ), fleuriste_doc_money( 0, $order ), true );
	} else {
		$totals[] = array( __( 'Amount due', 'fleuriste' ), fleuriste_doc_money( (float) $order->get_total() - $refunded, $order ), true );
	}
	foreach ( $totals as $t ) {
		if ( $t[2] ) {
			$pdf->box( $tx - 8, $y - 13, $right - $tx + 8, 20, '#F6EDEE' );
		}
		$pdf->text( $tx, $y, $t[0], $t[2] ? 10 : 9, $t[2], $ink );
		$pdf->text( $right - 8, $y, $t[1], $t[2] ? 10 : 9, $t[2], $ink, 'R' );
		$y += 22;
	}

	// Payment.
	$py      = $y - 22 * count( $totals ) - 4;
	$payment = array();
	$method  = $order->get_payment_method_title();
	if ( $receipt ) {
		/* translators: %s: payment method */
		$payment[] = sprintf( __( 'Paid by: %s', 'fleuriste' ), $method ? $method : __( 'Not set', 'fleuriste' ) );
		/* translators: %s: date */
		$payment[] = sprintf( __( 'Paid on: %s', 'fleuriste' ), wp_date( 'd/m/Y', $order->get_date_paid()->getTimestamp() ) );
		if ( $order->get_transaction_id() ) {
			/* translators: %s: transaction reference */
			$payment[] = sprintf( __( 'Reference: %s', 'fleuriste' ), $order->get_transaction_id() );
		}
		$payment[] = __( 'Received with thanks.', 'fleuriste' );
	} else {
		if ( $method ) {
			/* translators: %s: payment method */
			$payment[] = sprintf( __( 'Payment: %s', 'fleuriste' ), $method );
		}
		if ( $order->get_date_paid() ) {
			/* translators: %s: date */
			$payment[] = sprintf( __( 'Paid on %s', 'fleuriste' ), wp_date( 'd/m/Y', $order->get_date_paid()->getTimestamp() ) );
		} elseif ( $order->needs_payment() ) {
			$payment[] = __( 'Pay online with the payment link we sent you.', 'fleuriste' );
		}
	}
	foreach ( $payment as $i => $line ) {
		$pdf->text( $left, $py + $i * 13, $pdf->fit( $line, $tx - $left - 30, 8.8, 0 === $i ), 8.8, 0 === $i, $ink );
	}
	$py += count( $payment ) * 13 + 8;
	if ( ! $receipt && ! $order->get_date_paid() && $c['bank'] ) {
		$pdf->text( $left, $py + 6, __( 'Bank details', 'fleuriste' ), 8.4, true, $rose );
		foreach ( array_values( array_filter( array_map( 'trim', explode( "\n", $c['bank'] ) ) ) ) as $i => $line ) {
			$pdf->text( $left, $py + 20 + $i * 12, $pdf->fit( $line, $tx - $left - 30, 8.6 ), 8.6, false, $ink );
		}
	}

	// Footer on every page: note + legal line.
	$foot  = array();
	$parts = array();
	if ( $c['legal_name'] && strcasecmp( $c['legal_name'], $c['trading_name'] ) ) {
		/* translators: 1: trading name, 2: company name */
		$parts[] = sprintf( __( '%1$s is a trading name of %2$s', 'fleuriste' ), $c['trading_name'], $c['legal_name'] );
	} else {
		$parts[] = $c['trading_name'];
	}
	if ( $c['brn'] ) {
		/* translators: %s: BRN */
		$parts[] = sprintf( __( 'BRN %s', 'fleuriste' ), $c['brn'] );
	}
	if ( $c['vat'] ) {
		/* translators: %s: VAT number */
		$parts[] = sprintf( __( 'VAT No %s', 'fleuriste' ), $c['vat'] );
	}
	if ( $c['website'] ) {
		$parts[] = preg_replace( '#^https?://|/$#', '', $c['website'] );
	}
	$foot_note = trim( (string) $c['footer'] );
	$legal_txt = implode( ' · ', $parts );
	$count     = $pdf->page_count();
	if ( $foot_note ) {
		$pdf->text( $pdf->w / 2, $pdf->h - 58, $pdf->fit( $foot_note, $right - $left, 9.5, true ), 9.5, true, $rose, 'C' );
	}
	$pdf->line( $left, $pdf->h - 46, $right, $pdf->h - 46, '#EBDCDD', 0.6 );
	$pdf->text( $pdf->w / 2, $pdf->h - 32, $pdf->fit( $legal_txt, $right - $left, 7.6 ), 7.6, false, $muted, 'C' );
	if ( $count > 1 ) {
		$pdf->text( $right, $pdf->h - 20, sprintf( /* translators: %s: page number */ __( 'Page %s', 'fleuriste' ), $count . ' / {nb}' ), 7, false, $muted, 'R' );
	}
	return $pdf->build( $title . ' ' . $doc['number'] . ' – ' . $c['trading_name'] );
}

/* -------------------------------------------------------------------------
 * Downloads
 * ---------------------------------------------------------------------- */

/**
 * Admin link to a document (opens in the browser, ready to print or save).
 *
 * @param WC_Order $order   Order.
 * @param string   $type    invoice|receipt.
 * @param bool     $preview Preview mode.
 * @return string
 */
function fleuriste_doc_admin_url( $order, $type, $preview = false ) {
	return wp_nonce_url(
		add_query_arg(
			array(
				'action'  => 'fleuriste_doc',
				'order'   => $order->get_id(),
				'type'    => $type,
				'preview' => $preview ? 1 : 0,
			),
			admin_url( 'admin-post.php' )
		),
		'fleuriste_doc'
	);
}

/**
 * Customer link to a document (order key protected).
 *
 * @param WC_Order $order Order.
 * @param string   $type  invoice|receipt.
 * @return string
 */
function fleuriste_doc_customer_url( $order, $type ) {
	return add_query_arg(
		array(
			'fleuriste-doc' => $type,
			'order'         => $order->get_id(),
			'key'           => $order->get_order_key(),
		),
		home_url( '/' )
	);
}

/**
 * File name for a document.
 *
 * @param WC_Order $order Order.
 * @param string   $type  invoice|receipt.
 * @return string
 */
function fleuriste_doc_filename( $order, $type ) {
	$no = (string) $order->get_meta( '_fleuriste_' . $type . '_no' );
	return sanitize_file_name( ( 'receipt' === $type ? __( 'receipt', 'fleuriste' ) : __( 'invoice', 'fleuriste' ) ) . '-' . ( $no ? $no : $order->get_order_number() ) . '.pdf' );
}

/**
 * Send a document to the browser.
 *
 * @param WC_Order $order   Order.
 * @param string   $type    invoice|receipt.
 * @param bool     $preview Preview.
 */
function fleuriste_doc_send( $order, $type, $preview = false ) {
	if ( 'receipt' === $type && ! fleuriste_can_receipt( $order ) ) {
		wp_die( esc_html__( 'A receipt is available once the order is paid.', 'fleuriste' ) );
	}
	$bytes = fleuriste_doc_pdf( $order, $type, $preview );
	nocache_headers();
	header( 'Content-Type: application/pdf' );
	header( 'Content-Disposition: inline; filename="' . fleuriste_doc_filename( $order, $type ) . '"' );
	header( 'Content-Length: ' . strlen( $bytes ) );
	echo $bytes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- PDF.
	exit;
}

add_action(
	'admin_post_fleuriste_doc',
	function () {
		check_admin_referer( 'fleuriste_doc' );
		$order = wc_get_order( isset( $_GET['order'] ) ? absint( $_GET['order'] ) : 0 );
		if ( ! $order || ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'fleuriste' ) );
		}
		$type = isset( $_GET['type'] ) && 'receipt' === $_GET['type'] ? 'receipt' : 'invoice';
		fleuriste_doc_send( $order, $type, ! empty( $_GET['preview'] ) );
	}
);

add_action(
	'template_redirect',
	function () {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- protected by the order key.
		if ( empty( $_GET['fleuriste-doc'] ) || empty( $_GET['order'] ) || empty( $_GET['key'] ) ) {
			return;
		}
		$order = wc_get_order( absint( $_GET['order'] ) );
		$key   = sanitize_text_field( wp_unslash( $_GET['key'] ) );
		if ( ! $order || ! hash_equals( $order->get_order_key(), $key ) || 'yes' !== fleuriste_company()['customer_links'] ) {
			wp_die( esc_html__( 'This document is not available.', 'fleuriste' ), '', array( 'response' => 404 ) );
		}
		fleuriste_doc_send( $order, 'receipt' === $_GET['fleuriste-doc'] ? 'receipt' : 'invoice' );
		// phpcs:enable
	}
);

/* -------------------------------------------------------------------------
 * Buttons: order screen, board, My Account, tracking page
 * ---------------------------------------------------------------------- */

add_action(
	'woocommerce_admin_order_data_after_order_details',
	function ( $order ) {
		if ( fleuriste_wcdn_active() || ! $order->get_id() || 'auto-draft' === $order->get_status() ) {
			return;
		}
		echo '<p class="form-field form-field-wide" style="margin-top:12px"><strong>' . esc_html__( 'Documents', 'fleuriste' ) . '</strong><br>';
		$inv = (string) $order->get_meta( '_fleuriste_invoice_no' );
		echo '<a class="button" target="_blank" href="' . esc_url( fleuriste_doc_admin_url( $order, 'invoice' ) ) . '"><span class="dashicons dashicons-media-document" style="vertical-align:text-bottom"></span> ' . esc_html( $inv ? sprintf( /* translators: %s: number */ __( 'Invoice %s', 'fleuriste' ), $inv ) : __( 'Invoice', 'fleuriste' ) ) . '</a> ';
		if ( fleuriste_can_receipt( $order ) ) {
			$rc = (string) $order->get_meta( '_fleuriste_receipt_no' );
			echo '<a class="button" target="_blank" href="' . esc_url( fleuriste_doc_admin_url( $order, 'receipt' ) ) . '"><span class="dashicons dashicons-yes-alt" style="vertical-align:text-bottom"></span> ' . esc_html( $rc ? sprintf( /* translators: %s: number */ __( 'Receipt %s', 'fleuriste' ), $rc ) : __( 'Receipt', 'fleuriste' ) ) . '</a>';
		}
		echo '</p>';
	},
	30
);

/**
 * Board buttons for an order (used by the Deliveries board).
 *
 * @param int $order_id Order ID.
 * @return string
 */
function fleuriste_doc_board_buttons( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return '';
	}
	if ( fleuriste_wcdn_active() ) {
		$out = '<a class="button" target="_blank" href="' . esc_url( fleuriste_wcdn_url( $order_id, 'invoice' ) ) . '"><span class="dashicons dashicons-media-document" aria-hidden="true"></span> ' . esc_html__( 'Invoice', 'fleuriste' ) . '</a>';
		if ( fleuriste_can_receipt( $order ) ) {
			$out .= '<a class="button" target="_blank" href="' . esc_url( fleuriste_wcdn_url( $order_id, 'receipt' ) ) . '"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> ' . esc_html__( 'Receipt', 'fleuriste' ) . '</a>';
		}
		return $out;
	}
	$out = '<a class="button" target="_blank" href="' . esc_url( fleuriste_doc_admin_url( $order, 'invoice' ) ) . '"><span class="dashicons dashicons-media-document" aria-hidden="true"></span> ' . esc_html__( 'Invoice', 'fleuriste' ) . '</a>';
	if ( fleuriste_can_receipt( $order ) ) {
		$out .= '<a class="button" target="_blank" href="' . esc_url( fleuriste_doc_admin_url( $order, 'receipt' ) ) . '"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> ' . esc_html__( 'Receipt', 'fleuriste' ) . '</a>';
	}
	return $out;
}

add_filter(
	'woocommerce_my_account_my_orders_actions',
	function ( $actions, $order ) {
		if ( fleuriste_wcdn_active() || 'yes' !== fleuriste_company()['customer_links'] || $order->has_status( array( 'pending', 'failed', 'checkout-draft' ) ) ) {
			return $actions;
		}
		$actions['fleuriste-invoice'] = array(
			'url'  => fleuriste_doc_customer_url( $order, 'invoice' ),
			'name' => __( 'Invoice', 'fleuriste' ),
		);
		if ( fleuriste_can_receipt( $order ) ) {
			$actions['fleuriste-receipt'] = array(
				'url'  => fleuriste_doc_customer_url( $order, 'receipt' ),
				'name' => __( 'Receipt', 'fleuriste' ),
			);
		}
		return $actions;
	},
	20,
	2
);

/**
 * Links for the tracking page / thank-you page.
 *
 * @param WC_Order $order Order.
 * @return string
 */
function fleuriste_doc_customer_links( $order ) {
	if ( $order->has_status( array( 'pending', 'failed', 'checkout-draft' ) ) ) {
		return '';
	}
	if ( fleuriste_wcdn_active() ) {
		$url = function ( $type ) use ( $order ) {
			return fleuriste_wcdn_url( $order->get_id(), $type, $order->get_billing_email(), true );
		};
	} elseif ( 'yes' === fleuriste_company()['customer_links'] ) {
		$url = function ( $type ) use ( $order ) {
			return fleuriste_doc_customer_url( $order, $type );
		};
	} else {
		return '';
	}
	$links = '<a href="' . esc_url( $url( 'invoice' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Invoice', 'fleuriste' ) . '</a>';
	if ( fleuriste_can_receipt( $order ) ) {
		$links .= ' · <a href="' . esc_url( $url( 'receipt' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Receipt', 'fleuriste' ) . '</a>';
	}
	return '<p class="fl-doc-links">' . $links . '</p>';
}

add_action(
	'woocommerce_thankyou',
	function ( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order && ! fleuriste_wcdn_active() ) {
			echo fleuriste_doc_customer_links( $order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	},
	30
);

/* -------------------------------------------------------------------------
 * Email attachments
 * ---------------------------------------------------------------------- */

/**
 * Write a document to a private temporary file (old files are cleaned up).
 *
 * @param WC_Order $order Order.
 * @param string   $type  invoice|receipt.
 * @return string Path.
 */
function fleuriste_doc_temp_file( $order, $type ) {
	$up  = wp_upload_dir();
	$dir = trailingslashit( $up['basedir'] ) . 'fleuriste-docs';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
		file_put_contents( $dir . '/index.php', "<?php\n// Silence.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $dir . '/.htaccess', "Deny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}
	foreach ( (array) glob( $dir . '/*/*.pdf' ) as $old ) {
		if ( $old && filemtime( $old ) < time() - HOUR_IN_SECONDS ) {
			wp_delete_file( $old );
			@rmdir( dirname( $old ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		}
	}
	$sub = $dir . '/' . wp_generate_password( 20, false );
	wp_mkdir_p( $sub );
	$file = $sub . '/' . fleuriste_doc_filename( $order, $type );
	file_put_contents( $file, fleuriste_doc_pdf( $order, $type ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	// The number may have just been given: rename to include it.
	$named = $sub . '/' . fleuriste_doc_filename( wc_get_order( $order->get_id() ), $type );
	if ( $named !== $file && @rename( $file, $named ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename
		$file = $named;
	}
	return $file;
}

add_filter(
	'woocommerce_email_attachments',
	function ( $attachments, $email_id, $order ) {
		if ( ! $order instanceof WC_Order || fleuriste_wcdn_active() ) {
			return $attachments;
		}
		$c = fleuriste_company();
		try {
			if ( 'yes' === $c['attach_invoice'] && in_array( $email_id, array( 'customer_processing_order', 'customer_on_hold_order', 'customer_invoice' ), true ) ) {
				$attachments[] = fleuriste_doc_temp_file( $order, 'invoice' );
			}
			if ( 'yes' === $c['attach_receipt'] && 'customer_completed_order' === $email_id && fleuriste_can_receipt( $order ) ) {
				$attachments[] = fleuriste_doc_temp_file( $order, 'receipt' );
			}
		} catch ( Throwable $e ) {
			// Never block an email because of a document.
			unset( $e );
		}
		return $attachments;
	},
	10,
	3
);

/* -------------------------------------------------------------------------
 * "Print Invoice & Delivery Notes" plugin: add the company's legal details
 * ---------------------------------------------------------------------- */

/**
 * Lines of legal details shown under the shop details.
 *
 * @return string[]
 */
function fleuriste_legal_lines() {
	$c     = fleuriste_company();
	$lines = array();
	if ( $c['legal_name'] && strcasecmp( $c['legal_name'], $c['trading_name'] ) ) {
		$lines[] = $c['legal_name'];
	}
	$ids = array();
	if ( $c['brn'] ) {
		/* translators: %s: business registration number */
		$ids[] = sprintf( __( 'BRN: %s', 'fleuriste' ), $c['brn'] );
	}
	if ( $c['vat'] ) {
		/* translators: %s: VAT number */
		$ids[] = sprintf( __( 'VAT No: %s', 'fleuriste' ), $c['vat'] );
	}
	if ( $ids ) {
		$lines[] = implode( ' · ', $ids );
	}
	return $lines;
}

// Use the Company page's name, address, phone, email and logo on the plugin's documents.
add_filter(
	'wcdn_template_data',
	function ( $data ) {
		$c = fleuriste_company();
		if ( ! isset( $data['shop'] ) || ! is_array( $data['shop'] ) ) {
			return $data;
		}
		if ( $c['trading_name'] ) {
			$data['shop']['name'] = $c['trading_name'];
		}
		if ( $c['address'] ) {
			$data['shop']['address'] = $c['address'];
		}
		if ( $c['phone'] ) {
			$data['shop']['phone'] = $c['phone'];
		}
		if ( $c['email'] ) {
			$data['shop']['email'] = $c['email'];
		}
		if ( $c['logo_id'] && wp_get_attachment_url( $c['logo_id'] ) ) {
			$data['shop']['logo']      = wp_get_attachment_url( $c['logo_id'] );
			$data['shop']['logo_path'] = get_attached_file( $c['logo_id'] );
		}
		return $data;
	},
	20
);

/**
 * Print the legal block once per document (after the shop details, or before
 * the addresses when the plugin's shop details are switched off).
 *
 * @param array  $order    Order data from the plugin.
 * @param string $template Template key.
 */
function fleuriste_wcdn_legal_block( $order, $template ) {
	static $done = array();
	$key = ( is_array( $order ) && isset( $order['id'] ) ? $order['id'] : '' ) . '|' . $template;
	if ( isset( $done[ $key ] ) ) {
		return;
	}
	$done[ $key ] = true;
	$lines        = fleuriste_legal_lines();
	if ( $lines ) {
		echo '<div class="wcdn-shop-legal" style="margin-top:4px;font-size:0.92em">' . implode( '<br>', array_map( 'esc_html', $lines ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped.
	}
}
add_action( 'wcdn_after_branding', 'fleuriste_wcdn_legal_block', 10, 2 );
add_action( 'wcdn_before_addresses', 'fleuriste_wcdn_legal_block', 10, 2 );

// Legal line at the very bottom: "SunlightFlowers is a trading name of … · BRN … · VAT No …".
add_action(
	'wcdn_after_footer',
	function () {
		$c     = fleuriste_company();
		$parts = array();
		if ( $c['legal_name'] && strcasecmp( $c['legal_name'], $c['trading_name'] ) ) {
			/* translators: 1: trading name, 2: company name */
			$parts[] = sprintf( __( '%1$s is a trading name of %2$s', 'fleuriste' ), $c['trading_name'], $c['legal_name'] );
		} elseif ( $c['brn'] || $c['vat'] ) {
			$parts[] = $c['trading_name'];
		}
		if ( $c['brn'] ) {
			/* translators: %s: BRN */
			$parts[] = sprintf( __( 'BRN %s', 'fleuriste' ), $c['brn'] );
		}
		if ( $c['vat'] ) {
			/* translators: %s: VAT number */
			$parts[] = sprintf( __( 'VAT No %s', 'fleuriste' ), $c['vat'] );
		}
		if ( $parts ) {
			echo '<div class="wcdn-legal-footer" style="margin-top:10px;font-size:8.5pt;color:#666;text-align:center">' . esc_html( implode( ' · ', $parts ) ) . '</div>';
		}
	}
);
