<?php
/**
 * Title: Shop by occasion – photo grid
 * Slug: fleuriste/occasions
 * Categories: fleuriste, gallery
 * Viewport width: 1400
 */
?>
<!-- wp:group {"tagName":"section","align":"full","className":"fl-section fl-occasions","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull fl-section fl-occasions"><!-- wp:group {"align":"wide","className":"fl-section__head fl-section__head--center","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide fl-section__head fl-section__head--center"><?php if ( fleuriste_s_has( 'occasions', 'eyebrow' ) ) : ?><!-- wp:paragraph {"align":"center","className":"fl-eyebrow"} -->
<p class="has-text-align-center fl-eyebrow"><?php fleuriste_s_e( 'occasions', 'eyebrow' ); ?></p>
<!-- /wp:paragraph --><?php endif; ?>

<!-- wp:heading {"textAlign":"center"} -->
<h2 class="wp-block-heading has-text-align-center"><?php fleuriste_s_e( 'occasions', 'title' ); ?></h2>
<!-- /wp:heading -->

<?php if ( fleuriste_s_has( 'occasions', 'text' ) ) : ?><!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center"><?php fleuriste_s_e( 'occasions', 'text' ); ?></p>
<!-- /wp:paragraph --><?php endif; ?></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","className":"fl-occasions__grid","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide fl-occasions__grid"><?php if ( fleuriste_s_has( 'occasions', 'o1_title' ) ) : ?><!-- wp:cover {"url":"<?php echo esc_url( fleuriste_s_img( 'occasions', 'o1_image' ) ); ?>","alt":"","dimRatio":0,"isDark":false,"className":"fl-tile fl-tile--tall","layout":{"type":"constrained"}} -->
<div class="wp-block-cover is-light fl-tile fl-tile--tall"><img class="wp-block-cover__image-background" alt="" src="<?php echo esc_url( fleuriste_s_img( 'occasions', 'o1_image' ) ); ?>" data-object-fit="cover"/><span aria-hidden="true" class="wp-block-cover__background has-background-dim-0 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:heading {"level":3,"className":"fl-tile__title"} -->
<h3 class="wp-block-heading fl-tile__title"><a href="<?php echo esc_url( fleuriste_s_link( 'occasions', 'o1_link' ) ); ?>"><?php fleuriste_s_e( 'occasions', 'o1_title' ); ?></a></h3>
<!-- /wp:heading -->

<?php if ( fleuriste_s_has( 'occasions', 'more' ) ) : ?><!-- wp:paragraph {"className":"fl-tile__more"} -->
<p class="fl-tile__more"><?php fleuriste_s_e( 'occasions', 'more' ); ?></p>
<!-- /wp:paragraph --><?php endif; ?></div></div>
<!-- /wp:cover --><?php endif; ?>

<?php if ( fleuriste_s_has( 'occasions', 'o2_title' ) ) : ?><!-- wp:cover {"url":"<?php echo esc_url( fleuriste_s_img( 'occasions', 'o2_image' ) ); ?>","alt":"","dimRatio":0,"isDark":false,"className":"fl-tile fl-tile--wide","layout":{"type":"constrained"}} -->
<div class="wp-block-cover is-light fl-tile fl-tile--wide"><img class="wp-block-cover__image-background" alt="" src="<?php echo esc_url( fleuriste_s_img( 'occasions', 'o2_image' ) ); ?>" data-object-fit="cover"/><span aria-hidden="true" class="wp-block-cover__background has-background-dim-0 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:heading {"level":3,"className":"fl-tile__title"} -->
<h3 class="wp-block-heading fl-tile__title"><a href="<?php echo esc_url( fleuriste_s_link( 'occasions', 'o2_link' ) ); ?>"><?php fleuriste_s_e( 'occasions', 'o2_title' ); ?></a></h3>
<!-- /wp:heading -->

<?php if ( fleuriste_s_has( 'occasions', 'more' ) ) : ?><!-- wp:paragraph {"className":"fl-tile__more"} -->
<p class="fl-tile__more"><?php fleuriste_s_e( 'occasions', 'more' ); ?></p>
<!-- /wp:paragraph --><?php endif; ?></div></div>
<!-- /wp:cover --><?php endif; ?>

<?php if ( fleuriste_s_has( 'occasions', 'o3_title' ) ) : ?><!-- wp:cover {"url":"<?php echo esc_url( fleuriste_s_img( 'occasions', 'o3_image' ) ); ?>","alt":"","dimRatio":0,"isDark":false,"className":"fl-tile ","layout":{"type":"constrained"}} -->
<div class="wp-block-cover is-light fl-tile "><img class="wp-block-cover__image-background" alt="" src="<?php echo esc_url( fleuriste_s_img( 'occasions', 'o3_image' ) ); ?>" data-object-fit="cover"/><span aria-hidden="true" class="wp-block-cover__background has-background-dim-0 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:heading {"level":3,"className":"fl-tile__title"} -->
<h3 class="wp-block-heading fl-tile__title"><a href="<?php echo esc_url( fleuriste_s_link( 'occasions', 'o3_link' ) ); ?>"><?php fleuriste_s_e( 'occasions', 'o3_title' ); ?></a></h3>
<!-- /wp:heading -->

<?php if ( fleuriste_s_has( 'occasions', 'more' ) ) : ?><!-- wp:paragraph {"className":"fl-tile__more"} -->
<p class="fl-tile__more"><?php fleuriste_s_e( 'occasions', 'more' ); ?></p>
<!-- /wp:paragraph --><?php endif; ?></div></div>
<!-- /wp:cover --><?php endif; ?>

<?php if ( fleuriste_s_has( 'occasions', 'o4_title' ) ) : ?><!-- wp:cover {"url":"<?php echo esc_url( fleuriste_s_img( 'occasions', 'o4_image' ) ); ?>","alt":"","dimRatio":0,"isDark":false,"className":"fl-tile ","layout":{"type":"constrained"}} -->
<div class="wp-block-cover is-light fl-tile "><img class="wp-block-cover__image-background" alt="" src="<?php echo esc_url( fleuriste_s_img( 'occasions', 'o4_image' ) ); ?>" data-object-fit="cover"/><span aria-hidden="true" class="wp-block-cover__background has-background-dim-0 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:heading {"level":3,"className":"fl-tile__title"} -->
<h3 class="wp-block-heading fl-tile__title"><a href="<?php echo esc_url( fleuriste_s_link( 'occasions', 'o4_link' ) ); ?>"><?php fleuriste_s_e( 'occasions', 'o4_title' ); ?></a></h3>
<!-- /wp:heading -->

<?php if ( fleuriste_s_has( 'occasions', 'more' ) ) : ?><!-- wp:paragraph {"className":"fl-tile__more"} -->
<p class="fl-tile__more"><?php fleuriste_s_e( 'occasions', 'more' ); ?></p>
<!-- /wp:paragraph --><?php endif; ?></div></div>
<!-- /wp:cover --><?php endif; ?></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
