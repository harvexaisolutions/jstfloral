<?php
/**
 * Customer accounts on/off.
 *
 * Off (default): customers check out as guests, no account is created, the
 * account icon and "My Account" link are hidden, and the My Account page sends
 * visitors to the shop. Order tracking links, invoices and receipts keep working.
 * On: WooCommerce's own account settings apply again.
 *
 * Shop settings → Customer accounts.
 *
 * @package Fleuriste
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Are customer accounts switched on?
 *
 * @return bool
 */
function fleuriste_accounts_enabled() {
	return 'yes' === get_option( 'fleuriste_customer_accounts', 'no' );
}

/* -------------------------------------------------------------------------
 * Settings page (Shop settings → Customer accounts)
 * ---------------------------------------------------------------------- */

add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'fleuriste-company', __( 'Customer accounts', 'fleuriste' ), __( 'Customer accounts', 'fleuriste' ), 'manage_woocommerce', 'fleuriste-accounts', 'fleuriste_accounts_page', 6 );
	},
	100
);

add_action(
	'admin_post_fleuriste_accounts',
	function () {
		check_admin_referer( 'fleuriste_accounts' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'fleuriste' ) );
		}
		$on = ! empty( $_POST['accounts'] );
		update_option( 'fleuriste_customer_accounts', $on ? 'yes' : 'no' );
		if ( $on ) {
			// Sensible defaults when switching on: guests can still order, and can create an account at checkout.
			update_option( 'woocommerce_enable_guest_checkout', 'yes' );
			update_option( 'woocommerce_enable_signup_and_login_from_checkout', 'yes' );
			update_option( 'woocommerce_enable_myaccount_registration', 'yes' );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=fleuriste-accounts&saved=1' ) );
		exit;
	}
);

/**
 * The settings page.
 */
function fleuriste_accounts_page() {
	$on = fleuriste_accounts_enabled();
	?>
	<div class="wrap" style="max-width:760px">
		<h1><?php esc_html_e( 'Customer accounts', 'fleuriste' ); ?></h1>
		<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Saved.', 'fleuriste' ); ?></p></div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="fleuriste_accounts">
			<?php wp_nonce_field( 'fleuriste_accounts' ); ?>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><?php esc_html_e( 'Customer accounts', 'fleuriste' ); ?></th><td>
					<label><input type="checkbox" name="accounts" value="1" <?php checked( $on ); ?>> <?php esc_html_e( 'Let customers create an account and log in', 'fleuriste' ); ?></label>
					<p class="description"><?php esc_html_e( 'Off: customers order as guests. No account is created, the account icon and the "My Account" link are hidden, and the My Account page goes to the shop. Order tracking links, invoices and receipts still work.', 'fleuriste' ); ?></p>
					<p class="description"><?php esc_html_e( 'On: the account icon comes back and customers can create an account at checkout to see their orders later. More options (e.g. registration on the My Account page) are in WooCommerce → Settings → Accounts & Privacy.', 'fleuriste' ); ?></p>
				</td></tr>
			</table>
			<p class="submit"><button class="button button-primary"><?php esc_html_e( 'Save', 'fleuriste' ); ?></button>
			<?php if ( $on ) : ?>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=account' ) ); ?>"><?php esc_html_e( 'Accounts & Privacy settings', 'fleuriste' ); ?></a>
			<?php endif; ?></p>
		</form>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * When accounts are off
 * ---------------------------------------------------------------------- */

if ( ! fleuriste_accounts_enabled() ) {

	// Guest checkout only: no account creation, no login prompt.
	$fleuriste_force = array(
		'woocommerce_enable_guest_checkout'                 => 'yes',
		'woocommerce_enable_checkout_login_reminder'        => 'no',
		'woocommerce_enable_signup_and_login_from_checkout' => 'no',
		'woocommerce_enable_myaccount_registration'         => 'no',
		'woocommerce_enable_signup_from_checkout_for_subscriptions' => 'no',
		'woocommerce_enable_delayed_account_creation'       => 'no',
	);
	foreach ( $fleuriste_force as $fleuriste_opt => $fleuriste_val ) {
		add_filter(
			'pre_option_' . $fleuriste_opt,
			function () use ( $fleuriste_val ) {
				return $fleuriste_val;
			}
		);
	}
	unset( $fleuriste_force, $fleuriste_opt, $fleuriste_val );

	// Hide the account icon in the header.
	add_filter( 'render_block_woocommerce/customer-account', '__return_empty_string' );

	// Remove the "My Account" link from the footer.
	add_filter(
		'render_block',
		function ( $html, $block ) {
			if ( false === strpos( $html, 'my-account' ) || is_admin() ) {
				return $html;
			}
			return preg_replace( '#<li[^>]*>\s*<a[^>]+href="[^"]*/my-account/?"[^>]*>[^<]*</a>\s*</li>#i', '', $html );
		},
		10,
		2
	);

	// The My Account page goes to the shop for visitors (staff and existing logged-in
	// customers can still use it; printed invoices and receipts keep working).
	add_action(
		'template_redirect',
		function () {
			if ( is_user_logged_in() || ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
				return;
			}
			if ( get_query_var( 'print-order' ) || is_wc_endpoint_url( 'lost-password' ) || is_wc_endpoint_url( 'customer-logout' ) ) {
				return;
			}
			wp_safe_redirect( function_exists( 'fleuriste_shop_url' ) ? fleuriste_shop_url() : home_url( '/' ) );
			exit;
		}
	);

}
