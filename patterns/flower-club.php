<?php
/**
 * Title: Flower club – sign-up call to action
 * Slug: fleuriste/flower-club
 * Categories: fleuriste, call-to-action
 * Viewport width: 1400
 */
?>
<!-- wp:group {"tagName":"section","align":"full","className":"fl-section fl-club-wrap","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull fl-section fl-club-wrap"><!-- wp:group {"align":"wide","className":"fl-club","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide fl-club"><!-- wp:group {"className":"fl-club__copy","layout":{"type":"default"}} -->
<div class="wp-block-group fl-club__copy"><?php if ( fleuriste_s_has( 'flower-club', 'eyebrow' ) ) : ?><!-- wp:paragraph {"className":"fl-eyebrow"} -->
<p class="fl-eyebrow"><?php fleuriste_s_e( 'flower-club', 'eyebrow' ); ?></p>
<!-- /wp:paragraph --><?php endif; ?>

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php fleuriste_s_e( 'flower-club', 'title' ); ?></h2>
<!-- /wp:heading -->

<?php if ( fleuriste_s_has( 'flower-club', 'text' ) ) : ?><!-- wp:paragraph -->
<p><?php fleuriste_s_e( 'flower-club', 'text' ); ?></p>
<!-- /wp:paragraph --><?php endif; ?>

<?php if ( fleuriste_s_has( 'flower-club', 'button_label' ) ) : ?><!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( fleuriste_s_link( 'flower-club', 'button_link' ) ); ?>"><?php fleuriste_s_e( 'flower-club', 'button_label' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --><?php endif; ?></div>
<!-- /wp:group -->

<!-- wp:image {"sizeSlug":"full","linkDestination":"none","className":"fl-club__art"} -->
<figure class="wp-block-image size-full fl-club__art"><img src="<?php echo esc_url( fleuriste_s_img( 'flower-club', 'image' ) ); ?>" alt=""/></figure>
<!-- /wp:image --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
