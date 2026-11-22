<?php
/**
 * Title: Bestsellers – product grid
 * Slug: fleuriste/bestsellers
 * Categories: fleuriste, woo-commerce
 * Viewport width: 1400
 */
?>
<!-- wp:group {"tagName":"section","align":"full","className":"fl-section fl-products ","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull fl-section fl-products "><!-- wp:group {"align":"wide","className":"fl-section__head","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"bottom"}} -->
<div class="wp-block-group alignwide fl-section__head"><!-- wp:group {"className":"fl-section__intro","layout":{"type":"default"}} -->
<div class="wp-block-group fl-section__intro"><?php if ( fleuriste_s_has( 'bestsellers', 'eyebrow' ) ) : ?><!-- wp:paragraph {"className":"fl-eyebrow"} -->
<p class="fl-eyebrow"><?php fleuriste_s_e( 'bestsellers', 'eyebrow' ); ?></p>
<!-- /wp:paragraph --><?php endif; ?>

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php fleuriste_s_e( 'bestsellers', 'title' ); ?></h2>
<!-- /wp:heading -->

<?php if ( fleuriste_s_has( 'bestsellers', 'text' ) ) : ?><!-- wp:paragraph -->
<p><?php fleuriste_s_e( 'bestsellers', 'text' ); ?></p>
<!-- /wp:paragraph --><?php endif; ?></div>
<!-- /wp:group -->

<?php if ( fleuriste_s_has( 'bestsellers', 'link_label' ) ) : ?><!-- wp:paragraph {"className":"fl-link-arrow"} -->
<p class="fl-link-arrow"><a href="<?php echo esc_url( fleuriste_s_link( 'bestsellers', 'link' ) ); ?>"><?php fleuriste_s_e( 'bestsellers', 'link_label' ); ?></a></p>
<!-- /wp:paragraph --><?php endif; ?></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","className":"fl-products__grid","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide fl-products__grid"><!-- wp:shortcode -->
<?php echo fleuriste_s_products( 'bestsellers' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode built from numbers and fixed words. ?>
<!-- /wp:shortcode --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
