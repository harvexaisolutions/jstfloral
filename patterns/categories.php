<?php
/**
 * Title: Shop by category – round tiles
 * Slug: fleuriste/categories
 * Categories: fleuriste, woo-commerce
 * Viewport width: 1400
 * Description: Your main product categories with their pictures (Products → Categories). Built automatically.
 */
?>
<!-- wp:group {"tagName":"section","align":"full","className":"fl-section fl-cats","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull fl-section fl-cats"><!-- wp:group {"align":"wide","className":"fl-section__head fl-section__head--center","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide fl-section__head fl-section__head--center"><?php if ( fleuriste_s_has( 'categories', 'eyebrow' ) ) : ?><!-- wp:paragraph {"align":"center","className":"fl-eyebrow"} -->
<p class="has-text-align-center fl-eyebrow"><?php fleuriste_s_e( 'categories', 'eyebrow' ); ?></p>
<!-- /wp:paragraph --><?php endif; ?>

<!-- wp:heading {"textAlign":"center"} -->
<h2 class="wp-block-heading has-text-align-center"><?php fleuriste_s_e( 'categories', 'title' ); ?></h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:shortcode -->
[fleuriste_categories limit="<?php echo (int) fleuriste_s( 'categories', 'limit' ); ?>"]
<!-- /wp:shortcode -->
<!-- /wp:group --></section>
<!-- /wp:group -->
