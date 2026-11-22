<?php
/**
 * Title: Fresh arrivals – product grid
 * Slug: fleuriste/new-arrivals
 * Categories: fleuriste, woo-commerce
 * Viewport width: 1400
 */
?>
<!-- wp:group {"tagName":"section","align":"full","className":"fl-section fl-products fl-products--tint","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull fl-section fl-products fl-products--tint"><!-- wp:group {"align":"wide","className":"fl-section__head","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"bottom"}} -->
<div class="wp-block-group alignwide fl-section__head"><!-- wp:group {"className":"fl-section__intro","layout":{"type":"default"}} -->
<div class="wp-block-group fl-section__intro"><?php if ( fleuriste_s_has( 'new-arrivals', 'eyebrow' ) ) : ?><!-- wp:paragraph {"className":"fl-eyebrow"} -->
<p class="fl-eyebrow"><?php fleuriste_s_e( 'new-arrivals', 'eyebrow' ); ?></p>
<!-- /wp:paragraph --><?php endif; ?>

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php fleuriste_s_e( 'new-arrivals', 'title' ); ?></h2>
<!-- /wp:heading -->

<?php if ( fleuriste_s_has( 'new-arrivals', 'text' ) ) : ?><!-- wp:paragraph -->
<p><?php fleuriste_s_e( 'new-arrivals', 'text' ); ?></p>
<!-- /wp:paragraph --><?php endif; ?></div>
<!-- /wp:group -->

<?php if ( fleuriste_s_has( 'new-arrivals', 'link_label' ) ) : ?><!-- wp:paragraph {"className":"fl-link-arrow"} -->
<p class="fl-link-arrow"><a href="<?php echo esc_url( fleuriste_s_link( 'new-arrivals', 'link' ) ); ?>"><?php fleuriste_s_e( 'new-arrivals', 'link_label' ); ?></a></p>
<!-- /wp:paragraph --><?php endif; ?></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","className":"fl-products__grid","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide fl-products__grid"><!-- wp:shortcode -->
<?php echo fleuriste_s_products( 'new-arrivals' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode built from numbers and fixed words. ?>
<!-- /wp:shortcode --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
