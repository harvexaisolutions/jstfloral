<?php
/**
 * Announcement bar: messages managed in Shop settings → Announcement bar, each with
 * optional dates, days of the week and a daily time window. Several messages rotate.
 *
 * Scheduling is checked in the visitor's browser against the shop's time zone, so it
 * stays correct even when pages are cached.
 *
 * @package Fleuriste
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FLEURISTE_ANN_OPTION = 'fleuriste_announcement';

/**
 * Default settings (used until the owner saves the page).
 *
 * @return array
 */
function fleuriste_ann_defaults() {
	return array(
		'enabled'  => 1,
		'interval' => 5,
		'bg'       => '#2B1D21',
		'color'    => '#F6EDEE',
		'messages' => array(
			array(
				'text'      => 'Order by 2pm for same-day delivery',
				'link'      => '',
				'start'     => '',
				'end'       => '',
				'days'      => array(),
				'time_from' => '',
				'time_to'   => '14:00',
			),
			array(
				'text'      => 'Order now for delivery tomorrow',
				'link'      => '',
				'start'     => '',
				'end'       => '',
				'days'      => array(),
				'time_from' => '14:00',
				'time_to'   => '',
			),
			array(
				'text'      => '7-day freshness guarantee on every bouquet',
				'link'      => '',
				'start'     => '',
				'end'       => '',
				'days'      => array(),
				'time_from' => '',
				'time_to'   => '',
			),
			array(
				'text'      => '15% off your first order with code <strong>BLOOM15</strong>',
				'link'      => '',
				'start'     => '',
				'end'       => '',
				'days'      => array(),
				'time_from' => '',
				'time_to'   => '',
			),
		),
	);
}

/**
 * Saved settings merged with defaults.
 *
 * @return array
 */
function fleuriste_ann_get() {
	$saved = get_option( FLEURISTE_ANN_OPTION, null );
	if ( ! is_array( $saved ) ) {
		return fleuriste_ann_defaults();
	}
	return wp_parse_args( $saved, fleuriste_ann_defaults() );
}

/**
 * HTML allowed in a message.
 *
 * @return array
 */
function fleuriste_ann_allowed_html() {
	return array(
		'strong' => array(),
		'b'      => array(),
		'em'     => array(),
		'i'      => array(),
		'br'     => array(),
	);
}

/**
 * Day names, ISO numbering (1 = Monday).
 *
 * @return array
 */
function fleuriste_ann_days() {
	return array(
		1 => __( 'Mon', 'fleuriste' ),
		2 => __( 'Tue', 'fleuriste' ),
		3 => __( 'Wed', 'fleuriste' ),
		4 => __( 'Thu', 'fleuriste' ),
		5 => __( 'Fri', 'fleuriste' ),
		6 => __( 'Sat', 'fleuriste' ),
		7 => __( 'Sun', 'fleuriste' ),
	);
}

/* -------------------------------------------------------------------------
 * Admin page
 * ---------------------------------------------------------------------- */

add_action(
	'admin_menu',
	function () {
		add_theme_page(
			__( 'Announcement bar', 'fleuriste' ),
			__( 'Announcement bar', 'fleuriste' ),
			'edit_theme_options',
			'fleuriste-announcement',
			'fleuriste_ann_page'
		);
	}
);

add_action(
	'admin_init',
	function () {
		register_setting(
			'fleuriste_announcement',
			FLEURISTE_ANN_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => 'fleuriste_ann_sanitize',
			)
		);
	}
);

// Shop managers can edit it too.
add_filter(
	'option_page_capability_fleuriste_announcement',
	function () {
		return 'edit_theme_options';
	}
);

/**
 * Clean the submitted settings.
 *
 * @param mixed $input Raw input.
 * @return array
 */
function fleuriste_ann_sanitize( $input ) {
	$input = is_array( $input ) ? $input : array();
	$out   = array(
		'enabled'  => empty( $input['enabled'] ) ? 0 : 1,
		'interval' => max( 2, min( 30, absint( isset( $input['interval'] ) ? $input['interval'] : 5 ) ) ),
		'bg'       => sanitize_hex_color( isset( $input['bg'] ) ? $input['bg'] : '' ) ? sanitize_hex_color( $input['bg'] ) : '#2B1D21',
		'color'    => sanitize_hex_color( isset( $input['color'] ) ? $input['color'] : '' ) ? sanitize_hex_color( $input['color'] ) : '#F6EDEE',
		'messages' => array(),
	);
	$time = function ( $v ) {
		$v = sanitize_text_field( (string) $v );
		return preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $v ) ? $v : '';
	};
	$datetime = function ( $v ) {
		$v = sanitize_text_field( (string) $v );
		return preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $v ) ? $v : '';
	};
	if ( ! empty( $input['messages'] ) && is_array( $input['messages'] ) ) {
		foreach ( $input['messages'] as $m ) {
			$text = isset( $m['text'] ) ? trim( wp_kses( wp_unslash( $m['text'] ), fleuriste_ann_allowed_html() ) ) : '';
			if ( '' === $text ) {
				continue;
			}
			$days = array();
			if ( ! empty( $m['days'] ) && is_array( $m['days'] ) ) {
				$days = array_values( array_intersect( array_map( 'absint', $m['days'] ), array( 1, 2, 3, 4, 5, 6, 7 ) ) );
			}
			$out['messages'][] = array(
				'text'      => $text,
				'link'      => isset( $m['link'] ) ? esc_url_raw( trim( $m['link'] ) ) : '',
				'start'     => $datetime( isset( $m['start'] ) ? $m['start'] : '' ),
				'end'       => $datetime( isset( $m['end'] ) ? $m['end'] : '' ),
				'days'      => count( $days ) === 7 ? array() : $days,
				'time_from' => $time( isset( $m['time_from'] ) ? $m['time_from'] : '' ),
				'time_to'   => $time( isset( $m['time_to'] ) ? $m['time_to'] : '' ),
			);
		}
	}
	return $out;
}

/**
 * Print one message row in the admin form.
 *
 * @param int|string $i Index (or "__i__" for the template).
 * @param array      $m Message.
 */
function fleuriste_ann_row( $i, $m ) {
	$name = FLEURISTE_ANN_OPTION . '[messages][' . $i . ']';
	$m    = wp_parse_args(
		$m,
		array(
			'text'      => '',
			'link'      => '',
			'start'     => '',
			'end'       => '',
			'days'      => array(),
			'time_from' => '',
			'time_to'   => '',
		)
	);
	?>
	<div class="fl-ann-row">
		<div class="fl-ann-row__head">
			<strong class="fl-ann-row__num"></strong>
			<button type="button" class="button-link fl-ann-up" aria-label="<?php esc_attr_e( 'Move up', 'fleuriste' ); ?>">↑</button>
			<button type="button" class="button-link fl-ann-down" aria-label="<?php esc_attr_e( 'Move down', 'fleuriste' ); ?>">↓</button>
			<button type="button" class="button-link button-link-delete fl-ann-remove"><?php esc_html_e( 'Remove', 'fleuriste' ); ?></button>
		</div>
		<p>
			<label><?php esc_html_e( 'Message', 'fleuriste' ); ?><br>
			<input type="text" class="large-text" name="<?php echo esc_attr( $name ); ?>[text]" value="<?php echo esc_attr( $m['text'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Free delivery this weekend', 'fleuriste' ); ?>"></label>
		</p>
		<p>
			<label><?php esc_html_e( 'Link (optional)', 'fleuriste' ); ?><br>
			<input type="url" class="large-text" name="<?php echo esc_attr( $name ); ?>[link]" value="<?php echo esc_attr( $m['link'] ); ?>" placeholder="https://"></label>
		</p>
		<div class="fl-ann-grid">
			<label><?php esc_html_e( 'Show from', 'fleuriste' ); ?><br>
				<input type="datetime-local" name="<?php echo esc_attr( $name ); ?>[start]" value="<?php echo esc_attr( $m['start'] ); ?>"></label>
			<label><?php esc_html_e( 'Show until', 'fleuriste' ); ?><br>
				<input type="datetime-local" name="<?php echo esc_attr( $name ); ?>[end]" value="<?php echo esc_attr( $m['end'] ); ?>"></label>
			<label><?php esc_html_e( 'Each day from', 'fleuriste' ); ?><br>
				<input type="time" name="<?php echo esc_attr( $name ); ?>[time_from]" value="<?php echo esc_attr( $m['time_from'] ); ?>"></label>
			<label><?php esc_html_e( 'Each day until', 'fleuriste' ); ?><br>
				<input type="time" name="<?php echo esc_attr( $name ); ?>[time_to]" value="<?php echo esc_attr( $m['time_to'] ); ?>"></label>
		</div>
		<fieldset class="fl-ann-days">
			<legend><?php esc_html_e( 'Days (leave all unticked for every day)', 'fleuriste' ); ?></legend>
			<?php foreach ( fleuriste_ann_days() as $n => $label ) : ?>
				<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[days][]" value="<?php echo (int) $n; ?>" <?php checked( in_array( $n, (array) $m['days'], true ) ); ?>> <?php echo esc_html( $label ); ?></label>
			<?php endforeach; ?>
		</fieldset>
	</div>
	<?php
}

/**
 * Settings page.
 */
function fleuriste_ann_page() {
	$s = fleuriste_ann_get();
	?>
	<div class="wrap fl-ann">
		<h1><?php esc_html_e( 'Announcement bar', 'fleuriste' ); ?></h1>
		<p><?php esc_html_e( 'The thin bar at the very top of every page. Add as many messages as you like; when more than one is showing they take turns. Each message can be limited to certain dates, days of the week or times of day (shop time zone:', 'fleuriste' ); ?> <strong><?php echo esc_html( wp_timezone_string() ); ?></strong>).</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'fleuriste_announcement' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Show the bar', 'fleuriste' ); ?></th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( FLEURISTE_ANN_OPTION ); ?>[enabled]" value="1" <?php checked( $s['enabled'] ); ?>> <?php esc_html_e( 'Show the announcement bar', 'fleuriste' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><label for="fl-ann-interval"><?php esc_html_e( 'Change message every', 'fleuriste' ); ?></label></th>
					<td><input id="fl-ann-interval" type="number" min="2" max="30" class="small-text" name="<?php echo esc_attr( FLEURISTE_ANN_OPTION ); ?>[interval]" value="<?php echo (int) $s['interval']; ?>"> <?php esc_html_e( 'seconds', 'fleuriste' ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Colours', 'fleuriste' ); ?></th>
					<td>
						<label><?php esc_html_e( 'Background', 'fleuriste' ); ?> <input type="color" name="<?php echo esc_attr( FLEURISTE_ANN_OPTION ); ?>[bg]" value="<?php echo esc_attr( $s['bg'] ); ?>"></label>
						&nbsp;&nbsp;
						<label><?php esc_html_e( 'Text', 'fleuriste' ); ?> <input type="color" name="<?php echo esc_attr( FLEURISTE_ANN_OPTION ); ?>[color]" value="<?php echo esc_attr( $s['color'] ); ?>"></label>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Messages', 'fleuriste' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Tip: use <strong>…</strong> to make words bold, e.g. code <strong>BLOOM15</strong>. A message with "Each day until 14:00" disappears at 2pm.', 'fleuriste' ); ?></p>
			<div id="fl-ann-rows">
				<?php
				foreach ( array_values( $s['messages'] ) as $i => $m ) {
					fleuriste_ann_row( $i, $m );
				}
				?>
			</div>
			<p><button type="button" class="button" id="fl-ann-add">+ <?php esc_html_e( 'Add a message', 'fleuriste' ); ?></button></p>
			<script type="text/html" id="fl-ann-template"><?php fleuriste_ann_row( '__i__', array() ); ?></script>
			<?php submit_button(); ?>
		</form>
	</div>
	<style>
		.fl-ann-row { background: #fff; border: 1px solid #dcdcde; border-radius: 6px; padding: 12px 16px; margin: 0 0 12px; max-width: 860px; }
		.fl-ann-row__head { display: flex; align-items: center; gap: 10px; }
		.fl-ann-row__head .fl-ann-remove { margin-left: auto; }
		.fl-ann-row__num::before { content: "<?php echo esc_js( __( 'Message', 'fleuriste' ) ); ?> " counter(flann); }
		#fl-ann-rows { counter-reset: flann; }
		.fl-ann-row { counter-increment: flann; }
		.fl-ann-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin: 0 0 10px; }
		.fl-ann-grid input { width: 100%; }
		.fl-ann-days { display: flex; flex-wrap: wrap; gap: 14px; align-items: center; }
		.fl-ann-days legend { width: 100%; margin-bottom: 4px; }
		.fl-ann-handle { color: #a7aaad; }
		@media (max-width: 782px) { .fl-ann-grid { grid-template-columns: 1fr 1fr; } }
	</style>
	<script>
	( function () {
		var rows = document.getElementById( 'fl-ann-rows' );
		var tpl = document.getElementById( 'fl-ann-template' ).innerHTML;
		var next = rows.children.length + 1000;
		document.getElementById( 'fl-ann-add' ).addEventListener( 'click', function () {
			var div = document.createElement( 'div' );
			div.innerHTML = tpl.replace( /__i__/g, String( next++ ) );
			rows.appendChild( div.firstElementChild );
			rows.lastElementChild.querySelector( 'input[type=text]' ).focus();
		} );
		rows.addEventListener( 'click', function ( e ) {
			var row = e.target.closest( '.fl-ann-row' );
			if ( ! row ) { return; }
			if ( e.target.classList.contains( 'fl-ann-remove' ) ) { row.remove(); }
			if ( e.target.classList.contains( 'fl-ann-up' ) && row.previousElementSibling ) { rows.insertBefore( row, row.previousElementSibling ); }
			if ( e.target.classList.contains( 'fl-ann-down' ) && row.nextElementSibling ) { rows.insertBefore( row.nextElementSibling, row ); }
		} );
	} )();
	</script>
	<?php
}

/* -------------------------------------------------------------------------
 * Front end: "fleuriste/announcement" block used in the header
 * ---------------------------------------------------------------------- */

/**
 * Convert "2026-12-24T09:00" in the shop time zone to a UTC timestamp (ms), or 0.
 *
 * @param string $value Local date-time.
 * @return int
 */
function fleuriste_ann_ts( $value ) {
	if ( ! $value ) {
		return 0;
	}
	$dt = date_create_immutable_from_format( 'Y-m-d\TH:i', $value, wp_timezone() );
	return $dt ? $dt->getTimestamp() * 1000 : 0;
}

/**
 * Render the bar.
 *
 * @return string
 */
function fleuriste_ann_render() {
	$s = fleuriste_ann_get();
	if ( empty( $s['enabled'] ) || empty( $s['messages'] ) ) {
		return '';
	}
	$now   = time() * 1000;
	$items = '';
	foreach ( $s['messages'] as $m ) {
		$end = fleuriste_ann_ts( $m['end'] );
		if ( $end && $end < $now ) {
			continue; // Finished – no need to send it.
		}
		$text  = wp_kses( $m['text'], fleuriste_ann_allowed_html() );
		$inner = $m['link'] ? '<a href="' . esc_url( $m['link'] ) . '">' . $text . '</a>' : $text;
		$items .= sprintf(
			'<p class="fl-announce__msg" data-start="%1$d" data-end="%2$d" data-days="%3$s" data-from="%4$s" data-to="%5$s" hidden>%6$s</p>',
			fleuriste_ann_ts( $m['start'] ),
			$end,
			esc_attr( implode( ',', (array) $m['days'] ) ),
			esc_attr( $m['time_from'] ),
			esc_attr( $m['time_to'] ),
			$inner
		);
	}
	if ( '' === $items ) {
		return '';
	}
	// In the Site Editor preview, show the messages without the scheduling script.
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		$items = str_replace( ' hidden>', '>', $items );
	}
	return sprintf(
		'<div class="wp-block-fleuriste-announcement fl-announce" style="--fl-ann-bg:%1$s;--fl-ann-color:%2$s" data-interval="%3$d" data-tz="%4$s" role="region" aria-label="%5$s"><div class="fl-announce__track" aria-live="polite">%6$s</div></div>',
		esc_attr( $s['bg'] ),
		esc_attr( $s['color'] ),
		(int) $s['interval'],
		esc_attr( fleuriste_ann_timezone() ),
		esc_attr__( 'Announcements', 'fleuriste' ),
		$items
	);
}

/**
 * Time zone for the browser: an IANA name, or a fixed offset like "+04:00".
 *
 * @return string
 */
function fleuriste_ann_timezone() {
	$tz = wp_timezone_string();
	return $tz ? $tz : '+00:00';
}

add_action(
	'init',
	function () {
		wp_register_script(
			'fleuriste-announcement-editor',
			false,
			array( 'wp-blocks', 'wp-element', 'wp-server-side-render', 'wp-block-editor' ),
			FLEURISTE_VERSION,
			true
		);
		wp_add_inline_script(
			'fleuriste-announcement-editor',
			'( function ( wp ) {
				var el = wp.element.createElement;
				wp.blocks.registerBlockType( "fleuriste/announcement", {
					apiVersion: 3,
					title: "Announcement bar",
					description: "Messages from Shop settings → Announcement bar.",
					category: "theme",
					icon: "megaphone",
					supports: { html: false, reusable: false },
					edit: function () {
						var props = wp.blockEditor.useBlockProps();
						return el( "div", props,
							el( wp.serverSideRender, { block: "fleuriste/announcement" } ),
							el( "p", { style: { fontSize: "12px", margin: "4px 0 0", opacity: 0.7 } }, "Edit these messages in Shop settings → Announcement bar." )
						);
					},
					save: function () { return null; }
				} );
			} )( window.wp );'
		);
		register_block_type(
			'fleuriste/announcement',
			array(
				'api_version'     => 3,
				'editor_script'   => 'fleuriste-announcement-editor',
				'render_callback' => 'fleuriste_ann_render',
				'supports'        => array( 'html' => false ),
			)
		);
	}
);
