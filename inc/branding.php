<?php
/**
 * Logo: when a logo is set (Appearance → Editor → Site logo, or Customizer → Site
 * Identity), it replaces the shop name in the header and footer.
 *
 * A logo can have a bottom line that is only shown in the footer (e.g. "Since 2014"):
 * the image's custom field _fleuriste_logo_crop is the share of the height kept in
 * the header (e.g. 0.934).
 *
 * @package Fleuriste
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'render_block_core/site-title',
	function ( $html, $block ) {
		$logo_id = (int) get_theme_mod( 'custom_logo' );
		$class   = isset( $block['attrs']['className'] ) ? (string) $block['attrs']['className'] : '';
		if ( ! $logo_id || false === strpos( $class, 'fl-logo' ) || ! wp_attachment_is_image( $logo_id ) ) {
			return $html;
		}
		$name = get_bloginfo( 'name' );
		$img  = wp_get_attachment_image(
			$logo_id,
			'medium',
			false,
			array(
				'class'    => 'fl-logo__img',
				'alt'      => $name,
				'loading'  => 'eager',
				'decoding' => 'async',
			)
		);
		$crop = (float) get_post_meta( $logo_id, '_fleuriste_logo_crop', true );
		$crop = ( $crop > 0.5 && $crop < 1 ) ? $crop : 1;
		return sprintf(
			'<div class="wp-block-site-title fl-logo fl-logo--image" style="--fl-logo-crop:%4$s"><a href="%1$s" rel="home" aria-label="%2$s">%3$s</a></div>',
			esc_url( home_url( '/' ) ),
			esc_attr( $name ),
			$img, // Built by WordPress.
			esc_attr( (string) $crop )
		);
	},
	10,
	2
);

// The JST Flowers logo: hide its "Since 2014" line in the header (kept in the footer).
add_action(
	'admin_init',
	function () {
		$logo_id = (int) get_theme_mod( 'custom_logo' );
		if ( $logo_id && '' === get_post_meta( $logo_id, '_fleuriste_logo_crop', true ) && 0 === strpos( basename( (string) get_attached_file( $logo_id ) ), 'jst-flowers-logo' ) ) {
			update_post_meta( $logo_id, '_fleuriste_logo_crop', '0.934' );
		}
	}
);
