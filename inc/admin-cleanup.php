<?php
/**
 * Hide the blog (Posts) from the admin for a shop that doesn't use it.
 * Settings → Reading → "Blog posts" brings it back.
 *
 * @package Fleuriste
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Is the blog switched on?
 *
 * @return bool
 */
function fleuriste_blog_enabled() {
	return 'yes' === get_option( 'fleuriste_use_blog', 'no' );
}

// The switch, in Settings → Reading.
add_action(
	'admin_init',
	function () {
		register_setting(
			'reading',
			'fleuriste_use_blog',
			array(
				'type'              => 'string',
				'default'           => 'no',
				'sanitize_callback' => function ( $v ) {
					return 'yes' === $v ? 'yes' : 'no';
				},
			)
		);
		add_settings_field(
			'fleuriste_use_blog',
			__( 'Blog posts', 'fleuriste' ),
			function () {
				printf(
					'<label><input type="checkbox" name="fleuriste_use_blog" value="yes" %s> %s</label><p class="description">%s</p>',
					checked( fleuriste_blog_enabled(), true, false ),
					esc_html__( 'Show "Posts" in the admin menu', 'fleuriste' ),
					esc_html__( 'Off by default: the shop doesn\'t use a blog. Existing posts are kept.', 'fleuriste' )
				);
			},
			'reading'
		);
	}
);

if ( ! fleuriste_blog_enabled() ) {
	// Menu.
	add_action(
		'admin_menu',
		function () {
			remove_menu_page( 'edit.php' );
		},
		999
	);
	// "+ New → Post" in the admin bar.
	add_action(
		'admin_bar_menu',
		function ( $bar ) {
			$bar->remove_node( 'new-post' );
		},
		999
	);
	// "Quick Draft" on the Dashboard.
	add_action(
		'wp_dashboard_setup',
		function () {
			remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );
		},
		999
	);
	// Typing the address of the Posts screens leads to the Dashboard.
	add_action(
		'load-edit.php',
		function () {
			$type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : 'post'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( 'post' === $type ) {
				wp_safe_redirect( admin_url() );
				exit;
			}
		}
	);
	add_action(
		'load-post-new.php',
		function () {
			$type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : 'post'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( 'post' === $type ) {
				wp_safe_redirect( admin_url() );
				exit;
			}
		}
	);
}
