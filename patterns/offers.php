<?php
/**
 * Title: Offers – three promo cards
 * Slug: fleuriste/offers
 * Categories: fleuriste, call-to-action
 * Viewport width: 1400
 */
?>
<!-- wp:group {"tagName":"section","align":"full","className":"fl-section fl-offers","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull fl-section fl-offers"><!-- wp:group {"align":"wide","className":"fl-section__head fl-section__head--center","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide fl-section__head fl-section__head--center"><?php if ( fleuriste_s_has( 'offers', 'eyebrow' ) ) : ?><!-- wp:paragraph {"align":"center","className":"fl-eyebrow"} -->
<p class="has-text-align-center fl-eyebrow"><?php fleuriste_s_e( 'offers', 'eyebrow' ); ?></p>
<!-- /wp:paragraph --><?php endif; ?>

<!-- wp:heading {"textAlign":"center"} -->
<h2 class="wp-block-heading has-text-align-center"><?php fleuriste_s_e( 'offers', 'title' ); ?></h2>
<!-- /wp:heading -->

<?php if ( fleuriste_s_has( 'offers', 'text' ) ) : ?><!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center"><?php fleuriste_s_e( 'offers', 'text' ); ?></p>
<!-- /wp:paragraph --><?php endif; ?></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","className":"fl-offers__grid","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide fl-offers__grid"><!-- wp:cover {"url":"<?php echo esc_url( fleuriste_s_img( 'offers', 'c1_image' ) ); ?>","alt":"","dimRatio":0,"isDark":false,"className":"fl-offer","layout":{"type":"constrained"}} -->
<div class="wp-block-cover is-light fl-offer"><img class="wp-block-cover__image-background" alt="" src="<?php echo esc_url( fleuriste_s_img( 'offers', 'c1_image' ) ); ?>" data-object-fit="cover"/><span aria-hidden="true" class="wp-block-cover__background has-background-dim-0 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:paragraph {"className":"fl-offer__eyebrow"} -->
<p class="fl-offer__eyebrow"><?php fleuriste_s_e( 'offers', 'c1_eyebrow' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"fl-offer__big"} -->
<p class="fl-offer__big"><?php fleuriste_s_e( 'offers', 'c1_big' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"fl-offer__text"} -->
<p class="fl-offer__text"><?php fleuriste_s_e( 'offers', 'c1_text' ); ?></p>
<!-- /wp:paragraph -->

<?php if ( fleuriste_s_has( 'offers', 'c1_button' ) ) : ?><!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"fl-btn-light"} -->
<div class="wp-block-button fl-btn-light"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( fleuriste_s_link( 'offers', 'c1_link' ) ); ?>"><?php fleuriste_s_e( 'offers', 'c1_button' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --><?php endif; ?></div></div>
<!-- /wp:cover -->

<!-- wp:cover {"url":"<?php echo esc_url( fleuriste_s_img( 'offers', 'c2_image' ) ); ?>","alt":"","dimRatio":0,"isDark":false,"className":"fl-offer","layout":{"type":"constrained"}} -->
<div class="wp-block-cover is-light fl-offer"><img class="wp-block-cover__image-background" alt="" src="<?php echo esc_url( fleuriste_s_img( 'offers', 'c2_image' ) ); ?>" data-object-fit="cover"/><span aria-hidden="true" class="wp-block-cover__background has-background-dim-0 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:paragraph {"className":"fl-offer__eyebrow"} -->
<p class="fl-offer__eyebrow"><?php fleuriste_s_e( 'offers', 'c2_eyebrow' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"fl-offer__big"} -->
<p class="fl-offer__big"><?php fleuriste_s_e( 'offers', 'c2_big' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"fl-offer__text"} -->
<p class="fl-offer__text"><?php fleuriste_s_e( 'offers', 'c2_text' ); ?></p>
<!-- /wp:paragraph -->

<?php if ( fleuriste_s_has( 'offers', 'c2_button' ) ) : ?><!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"fl-btn-light"} -->
<div class="wp-block-button fl-btn-light"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( fleuriste_s_link( 'offers', 'c2_link' ) ); ?>"><?php fleuriste_s_e( 'offers', 'c2_button' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --><?php endif; ?></div></div>
<!-- /wp:cover -->

<!-- wp:cover {"url":"<?php echo esc_url( fleuriste_s_img( 'offers', 'c3_image' ) ); ?>","alt":"","dimRatio":0,"isDark":false,"className":"fl-offer","layout":{"type":"constrained"}} -->
<div class="wp-block-cover is-light fl-offer"><img class="wp-block-cover__image-background" alt="" src="<?php echo esc_url( fleuriste_s_img( 'offers', 'c3_image' ) ); ?>" data-object-fit="cover"/><span aria-hidden="true" class="wp-block-cover__background has-background-dim-0 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:paragraph {"className":"fl-offer__eyebrow"} -->
<p class="fl-offer__eyebrow"><?php fleuriste_s_e( 'offers', 'c3_eyebrow' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"fl-offer__big"} -->
<p class="fl-offer__big"><?php fleuriste_s_e( 'offers', 'c3_big' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"fl-offer__text"} -->
<p class="fl-offer__text"><?php fleuriste_s_e( 'offers', 'c3_text' ); ?></p>
<!-- /wp:paragraph -->

<?php if ( fleuriste_s_has( 'offers', 'c3_button' ) ) : ?><!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"fl-btn-light"} -->
<div class="wp-block-button fl-btn-light"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( fleuriste_s_link( 'offers', 'c3_link' ) ); ?>"><?php fleuriste_s_e( 'offers', 'c3_button' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --><?php endif; ?></div></div>
<!-- /wp:cover --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
