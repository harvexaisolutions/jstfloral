<?php
/**
 * Title: Footer (settings-driven)
 * Slug: fleuriste/footer
 * Categories: fleuriste
 * Inserter: no
 * Description: Texts and links from Shop settings → Website → Footer; address, hours, phone and WhatsApp from Shop settings → Shop details.
 */

$fl_company = function_exists( 'fleuriste_company' ) ? fleuriste_company() : array();
$fl_address = isset( $fl_company['address'] ) ? $fl_company['address'] : '';
$fl_phone   = isset( $fl_company['phone'] ) ? $fl_company['phone'] : '';
$fl_hours   = isset( $fl_company['hours'] ) ? $fl_company['hours'] : '';
$fl_email   = fleuriste_s( 'footer', 'show_email' ) && ! empty( $fl_company['email'] ) ? $fl_company['email'] : '';
$fl_social  = array();
foreach ( array( 'instagram', 'facebook', 'tiktok' ) as $fl_service ) {
	if ( fleuriste_s_has( 'footer', $fl_service ) ) {
		$fl_social[ $fl_service ] = fleuriste_resolve_link( fleuriste_s( 'footer', $fl_service ) );
	}
}
if ( function_exists( 'fleuriste_company_whatsapp_url' ) && fleuriste_company_whatsapp_url() ) {
	$fl_social['whatsapp'] = fleuriste_company_whatsapp_url();
}
$fl_cats = function_exists( 'fleuriste_menu_categories' ) ? array_slice( fleuriste_menu_categories(), 0, 6 ) : array();
$fl_help = array();
foreach ( preg_split( '/\r?\n/', (string) fleuriste_s( 'footer', 'help' ) ) as $fl_line ) {
	$fl_parts = array_map( 'trim', explode( '|', $fl_line, 2 ) );
	if ( '' !== $fl_parts[0] ) {
		$fl_help[] = array( $fl_parts[0], isset( $fl_parts[1] ) ? fleuriste_resolve_link( $fl_parts[1] ) : '#' );
	}
}
$fl_copy = str_replace( array( '{year}', '{name}' ), array( wp_date( 'Y' ), get_bloginfo( 'name' ) ), (string) fleuriste_s( 'footer', 'copyright' ) );
$fl_br   = function ( $text ) {
	return implode( '<br>', array_map( 'esc_html', array_filter( array_map( 'trim', preg_split( '/\r?\n/', (string) $text ) ) ) ) );
};
?>
<!-- wp:group {"className":"fl-footer","layout":{"type":"constrained"}} -->
<div class="wp-block-group fl-footer"><!-- wp:columns {"align":"wide","className":"fl-footer__cols"} -->
<div class="wp-block-columns alignwide fl-footer__cols"><!-- wp:column {"width":"34%","className":"fl-footer__brand"} -->
<div class="wp-block-column fl-footer__brand" style="flex-basis:34%"><!-- wp:site-title {"level":0,"className":"fl-logo"} /-->

<?php if ( fleuriste_s_has( 'footer', 'about' ) ) : ?><!-- wp:paragraph -->
<p><?php fleuriste_s_e( 'footer', 'about' ); ?></p>
<!-- /wp:paragraph --><?php endif; ?>

<?php if ( $fl_social ) : ?><!-- wp:social-links {"iconColor":"surface","iconColorValue":"#FFFFFF","className":"is-style-logos-only fl-social"} -->
<ul class="wp-block-social-links has-icon-color is-style-logos-only fl-social"><?php foreach ( $fl_social as $fl_service => $fl_url ) : ?><!-- wp:social-link {"url":"<?php echo esc_url( $fl_url ); ?>","service":"<?php echo esc_attr( $fl_service ); ?>"} /-->
<?php endforeach; ?></ul>
<!-- /wp:social-links --><?php endif; ?></div>
<!-- /wp:column -->

<!-- wp:column {"className":"fl-footer__links"} -->
<div class="wp-block-column fl-footer__links"><!-- wp:heading {"level":6} -->
<h6 class="wp-block-heading"><?php fleuriste_s_e( 'footer', 'shop_head' ); ?></h6>
<!-- /wp:heading -->

<!-- wp:list {"className":"fl-list-plain"} -->
<ul class="wp-block-list fl-list-plain"><!-- wp:list-item -->
<li><a href="<?php echo esc_url( fleuriste_shop_url() ); ?>"><?php esc_html_e( 'All Products', 'fleuriste' ); ?></a></li>
<!-- /wp:list-item --><?php foreach ( $fl_cats as $fl_cat ) : ?>

<!-- wp:list-item -->
<li><a href="<?php echo esc_url( get_term_link( $fl_cat ) ); ?>"><?php echo esc_html( $fl_cat->name ); ?></a></li>
<!-- /wp:list-item --><?php endforeach; ?></ul>
<!-- /wp:list --></div>
<!-- /wp:column -->

<!-- wp:column {"className":"fl-footer__links"} -->
<div class="wp-block-column fl-footer__links"><!-- wp:heading {"level":6} -->
<h6 class="wp-block-heading"><?php fleuriste_s_e( 'footer', 'help_head' ); ?></h6>
<!-- /wp:heading -->

<!-- wp:list {"className":"fl-list-plain"} -->
<ul class="wp-block-list fl-list-plain"><?php foreach ( $fl_help as $fl_link ) : ?><!-- wp:list-item -->
<li><a href="<?php echo esc_url( $fl_link[1] ); ?>"><?php echo esc_html( $fl_link[0] ); ?></a></li>
<!-- /wp:list-item -->
<?php endforeach; ?></ul>
<!-- /wp:list --></div>
<!-- /wp:column -->

<!-- wp:column {"className":"fl-footer__links"} -->
<div class="wp-block-column fl-footer__links"><!-- wp:heading {"level":6} -->
<h6 class="wp-block-heading"><?php fleuriste_s_e( 'footer', 'visit_head' ); ?></h6>
<!-- /wp:heading -->

<?php if ( '' !== trim( (string) $fl_address ) ) : ?><!-- wp:paragraph -->
<p><?php echo $fl_br( $fl_address ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped line by line. ?></p>
<!-- /wp:paragraph --><?php endif; ?>

<?php if ( '' !== trim( (string) $fl_hours ) ) : ?><!-- wp:paragraph -->
<p><?php echo $fl_br( $fl_hours ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped line by line. ?></p>
<!-- /wp:paragraph --><?php endif; ?>

<?php if ( $fl_phone || $fl_email ) : ?><!-- wp:paragraph -->
<p><?php if ( $fl_phone ) : ?><a href="tel:<?php echo esc_attr( preg_replace( '/[^\d+]/', '', $fl_phone ) ); ?>"><?php echo esc_html( $fl_phone ); ?></a><?php endif; ?><?php echo ( $fl_phone && $fl_email ) ? '<br>' : ''; ?><?php if ( $fl_email ) : ?><a href="mailto:<?php echo esc_attr( $fl_email ); ?>"><?php echo esc_html( $fl_email ); ?></a><?php endif; ?></p>
<!-- /wp:paragraph --><?php endif; ?></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:group {"align":"wide","className":"fl-footer__bottom","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between"}} -->
<div class="wp-block-group alignwide fl-footer__bottom"><!-- wp:paragraph -->
<p><?php echo esc_html( $fl_copy ); ?></p>
<!-- /wp:paragraph -->

<?php if ( fleuriste_s_has( 'footer', 'tagline' ) ) : ?><!-- wp:paragraph -->
<p><?php fleuriste_s_e( 'footer', 'tagline' ); ?></p>
<!-- /wp:paragraph --><?php endif; ?></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
