<?php
/**
 * Title: Our story – image and text
 * Slug: fleuriste/about
 * Categories: fleuriste, about
 * Viewport width: 1400
 */
?>
<!-- wp:group {"tagName":"section","align":"full","className":"fl-section fl-about","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull fl-section fl-about"><!-- wp:columns {"verticalAlignment":"center","align":"wide","className":"fl-about__cols"} -->
<div class="wp-block-columns alignwide are-vertically-aligned-center fl-about__cols"><!-- wp:column {"verticalAlignment":"center","width":"50%","className":"fl-about__media"} -->
<div class="wp-block-column is-vertically-aligned-center fl-about__media" style="flex-basis:50%"><!-- wp:image {"sizeSlug":"full","linkDestination":"none","className":"fl-about__img"} -->
<figure class="wp-block-image size-full fl-about__img"><img src="<?php echo esc_url( fleuriste_s_img( 'about', 'image' ) ); ?>" alt="Fresh flowers being arranged"/></figure>
<!-- /wp:image -->

<?php if ( fleuriste_s_has( 'about', 'stamp_big' ) ) : ?><!-- wp:group {"className":"fl-about__stamp","layout":{"type":"default"}} -->
<div class="wp-block-group fl-about__stamp"><!-- wp:paragraph {"className":"fl-about__stamp-big"} -->
<p class="fl-about__stamp-big"><?php fleuriste_s_e( 'about', 'stamp_big' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"fl-about__stamp-text"} -->
<p class="fl-about__stamp-text"><?php fleuriste_s_e( 'about', 'stamp_text' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --><?php endif; ?></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center","width":"50%","className":"fl-about__copy"} -->
<div class="wp-block-column is-vertically-aligned-center fl-about__copy" style="flex-basis:50%"><?php if ( fleuriste_s_has( 'about', 'eyebrow' ) ) : ?><!-- wp:paragraph {"className":"fl-eyebrow"} -->
<p class="fl-eyebrow"><?php fleuriste_s_e( 'about', 'eyebrow' ); ?></p>
<!-- /wp:paragraph --><?php endif; ?>

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php fleuriste_s_e( 'about', 'title' ); ?><?php if ( fleuriste_s_has( 'about', 'title_em' ) ) : ?> <em><?php fleuriste_s_e( 'about', 'title_em' ); ?></em><?php endif; ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php fleuriste_s_e( 'about', 'text' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:list {"className":"fl-checks"} -->
<ul class="wp-block-list fl-checks"><?php if ( fleuriste_s_has( 'about', 'b1' ) ) : ?><!-- wp:list-item -->
<li><?php fleuriste_s_e( 'about', 'b1' ); ?></li>
<!-- /wp:list-item --><?php endif; ?>

<?php if ( fleuriste_s_has( 'about', 'b2' ) ) : ?><!-- wp:list-item -->
<li><?php fleuriste_s_e( 'about', 'b2' ); ?></li>
<!-- /wp:list-item --><?php endif; ?>

<?php if ( fleuriste_s_has( 'about', 'b3' ) ) : ?><!-- wp:list-item -->
<li><?php fleuriste_s_e( 'about', 'b3' ); ?></li>
<!-- /wp:list-item --><?php endif; ?></ul>
<!-- /wp:list -->

<?php if ( fleuriste_s_has( 'about', 'button_label' ) ) : ?><!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( fleuriste_s_link( 'about', 'button_link' ) ); ?>"><?php fleuriste_s_e( 'about', 'button_label' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --><?php endif; ?></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></section>
<!-- /wp:group -->
