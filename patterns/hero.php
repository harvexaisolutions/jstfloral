<?php
/**
 * Title: Hero – fresh flowers
 * Slug: fleuriste/hero
 * Categories: fleuriste, banner
 * Keywords: hero, banner, intro
 * Viewport width: 1400
 */
?>
<!-- wp:group {"tagName":"section","align":"full","className":"fl-hero","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull fl-hero"><!-- wp:columns {"verticalAlignment":"center","align":"wide","className":"fl-hero__cols"} -->
<div class="wp-block-columns alignwide are-vertically-aligned-center fl-hero__cols"><!-- wp:column {"verticalAlignment":"center","width":"50%","className":"fl-hero__copy"} -->
<div class="wp-block-column is-vertically-aligned-center fl-hero__copy" style="flex-basis:50%"><?php if ( fleuriste_s_has( 'hero', 'eyebrow' ) ) : ?><!-- wp:paragraph {"className":"fl-eyebrow"} -->
<p class="fl-eyebrow"><?php fleuriste_s_e( 'hero', 'eyebrow' ); ?></p>
<!-- /wp:paragraph --><?php endif; ?>

<!-- wp:heading {"level":1,"className":"fl-hero__title"} -->
<h1 class="wp-block-heading fl-hero__title"><?php fleuriste_s_e( 'hero', 'title' ); ?><?php if ( fleuriste_s_has( 'hero', 'title_em' ) ) : ?> <em><?php fleuriste_s_e( 'hero', 'title_em' ); ?></em><?php endif; ?></h1>
<!-- /wp:heading -->

<?php if ( fleuriste_s_has( 'hero', 'text' ) ) : ?><!-- wp:paragraph {"className":"fl-hero__lead"} -->
<p class="fl-hero__lead"><?php fleuriste_s_e( 'hero', 'text' ); ?></p>
<!-- /wp:paragraph --><?php endif; ?>

<!-- wp:buttons {"className":"fl-hero__buttons"} -->
<div class="wp-block-buttons fl-hero__buttons"><?php if ( fleuriste_s_has( 'hero', 'button1_label' ) ) : ?><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( fleuriste_s_link( 'hero', 'button1_link' ) ); ?>"><?php fleuriste_s_e( 'hero', 'button1_label' ); ?></a></div>
<!-- /wp:button --><?php endif; ?>

<?php if ( fleuriste_s_has( 'hero', 'button2_label' ) ) : ?><!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( fleuriste_s_link( 'hero', 'button2_link' ) ); ?>"><?php fleuriste_s_e( 'hero', 'button2_label' ); ?></a></div>
<!-- /wp:button --><?php endif; ?></div>
<!-- /wp:buttons -->

<!-- wp:list {"className":"fl-hero__points"} -->
<ul class="wp-block-list fl-hero__points"><?php if ( fleuriste_s_has( 'hero', 'point1' ) ) : ?><!-- wp:list-item -->
<li><?php fleuriste_s_e( 'hero', 'point1' ); ?></li>
<!-- /wp:list-item --><?php endif; ?>

<?php if ( fleuriste_s_has( 'hero', 'point2' ) ) : ?><!-- wp:list-item -->
<li><?php fleuriste_s_e( 'hero', 'point2' ); ?></li>
<!-- /wp:list-item --><?php endif; ?>

<?php if ( fleuriste_s_has( 'hero', 'point3' ) ) : ?><!-- wp:list-item -->
<li><?php fleuriste_s_e( 'hero', 'point3' ); ?></li>
<!-- /wp:list-item --><?php endif; ?></ul>
<!-- /wp:list --></div>
<!-- /wp:column -->

<!-- wp:column {"width":"50%","className":"fl-hero__media"} -->
<div class="wp-block-column fl-hero__media" style="flex-basis:50%"><!-- wp:image {"sizeSlug":"full","linkDestination":"none","className":"fl-hero__main"} -->
<figure class="wp-block-image size-full fl-hero__main"><img src="<?php echo esc_url( fleuriste_s_img( 'hero', 'image' ) ); ?>" alt="Colourful fresh flowers"/></figure>
<!-- /wp:image -->

<?php if ( fleuriste_s_has( 'hero', 'card_title' ) ) : ?><!-- wp:group {"className":"fl-hero__card","layout":{"type":"default"}} -->
<div class="wp-block-group fl-hero__card"><!-- wp:image {"sizeSlug":"full","linkDestination":"none"} -->
<figure class="wp-block-image size-full"><img src="<?php echo esc_url( fleuriste_s_img( 'hero', 'card_image' ) ); ?>" alt=""/></figure>
<!-- /wp:image -->

<!-- wp:paragraph {"className":"fl-hero__card-title"} -->
<p class="fl-hero__card-title"><?php fleuriste_s_e( 'hero', 'card_title' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"fl-hero__card-text"} -->
<p class="fl-hero__card-text"><?php fleuriste_s_e( 'hero', 'card_text' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --><?php endif; ?>

<?php if ( fleuriste_s_has( 'hero', 'badge_big' ) ) : ?><!-- wp:group {"className":"fl-hero__badge","layout":{"type":"default"}} -->
<div class="wp-block-group fl-hero__badge"><!-- wp:paragraph {"className":"fl-hero__badge-big"} -->
<p class="fl-hero__badge-big"><?php fleuriste_s_e( 'hero', 'badge_big' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"fl-hero__badge-text"} -->
<p class="fl-hero__badge-text"><?php fleuriste_s_e( 'hero', 'badge_text' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --><?php endif; ?></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></section>
<!-- /wp:group -->
