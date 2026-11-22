<?php
/**
 * New-order alerts on the Orders & Deliveries board (free): while the board is open,
 * a chime and a notification for every new order. Turned on per device with the
 * "New-order alerts" button (browsers only allow sound after a click).
 *
 * @package Fleuriste
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Newest order that needs attention: [id, number, summary].
 *
 * @return array
 */
function fleuriste_alert_latest() {
	$ids = wc_get_orders(
		array(
			'limit'   => 1,
			'orderby' => 'ID',
			'order'   => 'DESC',
			'type'    => 'shop_order',
			'status'  => array( 'wc-processing', 'wc-on-hold', 'wc-pending' ),
			'return'  => 'ids',
		)
	);
	$id = $ids ? (int) $ids[0] : 0;
	if ( ! $id ) {
		return array( 0, '', '' );
	}
	$order = wc_get_order( $id );
	$items = array();
	foreach ( $order->get_items() as $item ) {
		$items[] = $item->get_quantity() . ' × ' . $item->get_name();
	}
	$data = function_exists( 'fleuriste_board_order_data' ) ? fleuriste_board_order_data( $order ) : array();
	$when = ! empty( $data['date'] ) ? date_i18n( 'D j M', strtotime( $data['date'] ) ) . ( $data['slot'] ? ', ' . $data['slot'] : '' ) : '';
	return array( $id, $order->get_order_number(), trim( implode( ', ', $items ) . ( $when ? ' – ' . $when : '' ) ) );
}

add_action(
	'wp_ajax_fleuriste_alert_latest',
	function () {
		check_ajax_referer( 'fleuriste_board', 'nonce' );
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_send_json_error( null, 403 );
		}
		list( $id, $number, $summary ) = fleuriste_alert_latest();
		wp_send_json_success(
			array(
				'id'      => $id,
				'number'  => $number,
				'summary' => $summary,
			)
		);
	}
);

add_action(
	'admin_enqueue_scripts',
	function () {
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( (string) $screen->id, 'fleuriste-deliveries' ) ) {
			return;
		}
		$cfg = array(
			'nonce'  => wp_create_nonce( 'fleuriste_board' ),
			'latest' => fleuriste_alert_latest()[0],
			'on'     => __( 'New-order alerts: on', 'fleuriste' ),
			'off'    => __( 'Turn on new-order alerts', 'fleuriste' ),
			/* translators: %s: order number */
			'title'  => __( 'New order #%s', 'fleuriste' ),
			'shop'   => get_bloginfo( 'name' ),
			'help'   => __( 'Keep this page open: you\'ll hear a chime and see a notification for every new order.', 'fleuriste' ),
		);
		wp_register_script( 'fleuriste-board-alerts', '', array(), FLEURISTE_VERSION, true );
		wp_enqueue_script( 'fleuriste-board-alerts' );
		wp_add_inline_script( 'fleuriste-board-alerts', 'window.flAlerts=' . wp_json_encode( $cfg ) . ';' . <<<'JS'
( function ( c ) {
	var KEY = 'flAlertsOn', LAST = 'flAlertsLast';
	var store = { get: function ( k ) { try { return localStorage.getItem( k ); } catch ( e ) { return null; } }, set: function ( k, v ) { try { localStorage.setItem( k, v ); } catch ( e ) {} } };
	var last = Math.max( c.latest || 0, parseInt( store.get( LAST ) || '0', 10 ) );
	var on = store.get( KEY ) === '1';
	var ctx = null, unseen = 0, baseTitle = document.title;
	var head = document.querySelector( '.fl-board .page-title-action' );
	if ( ! head ) { return; }
	var btn = document.createElement( 'button' );
	btn.type = 'button';
	btn.className = 'page-title-action fl-alert-btn';
	head.insertAdjacentElement( 'afterend', btn );
	function label() { btn.innerHTML = '<span class="dashicons ' + ( on ? 'dashicons-bell' : 'dashicons-controls-volumeoff' ) + '" aria-hidden="true"></span> ' + ( on ? c.on : c.off ); btn.title = c.help; btn.classList.toggle( 'is-on', on ); }
	function chime() {
		try {
			ctx = ctx || new ( window.AudioContext || window.webkitAudioContext )();
			if ( ctx.state === 'suspended' ) { ctx.resume(); }
			[ [ 880, 0 ], [ 1175, 0.18 ], [ 1568, 0.36 ] ].forEach( function ( n ) {
				var o = ctx.createOscillator(), g = ctx.createGain(), t = ctx.currentTime + n[ 1 ];
				o.type = 'sine'; o.frequency.value = n[ 0 ];
				g.gain.setValueAtTime( 0.0001, t ); g.gain.exponentialRampToValueAtTime( 0.35, t + 0.02 ); g.gain.exponentialRampToValueAtTime( 0.0001, t + 0.5 );
				o.connect( g ); g.connect( ctx.destination ); o.start( t ); o.stop( t + 0.55 );
			} );
		} catch ( e ) {}
	}
	function notify( d ) {
		var title = c.title.replace( '%s', d.number );
		if ( 'Notification' in window && Notification.permission === 'granted' ) {
			try { var n = new Notification( title, { body: d.summary, tag: 'fl-order-' + d.id } ); n.onclick = function () { window.focus(); n.close(); }; } catch ( e ) {}
		}
		unseen++;
		document.title = '(' + unseen + ') ' + baseTitle;
	}
	function check() {
		if ( ! on ) { return; }
		var body = new URLSearchParams( { action: 'fleuriste_alert_latest', nonce: c.nonce } );
		fetch( ajaxurl, { method: 'POST', credentials: 'same-origin', body: body } ).then( function ( r ) { return r.json(); } ).then( function ( res ) {
			if ( ! res || ! res.success || ! res.data.id ) { return; }
			if ( res.data.id > last ) {
				last = res.data.id; store.set( LAST, String( last ) );
				chime(); notify( res.data );
				if ( typeof window.flBoardReload === 'function' ) { window.flBoardReload(); }
			}
		} ).catch( function () {} );
	}
	btn.addEventListener( 'click', function () {
		on = ! on; store.set( KEY, on ? '1' : '0' ); label();
		if ( on ) {
			chime(); // Plays a short test chime (and unlocks sound in the browser).
			if ( 'Notification' in window && Notification.permission === 'default' ) { Notification.requestPermission(); }
		}
	} );
	document.addEventListener( 'visibilitychange', function () { if ( ! document.hidden ) { unseen = 0; document.title = baseTitle; } } );
	// Browsers block sound until the page is clicked once: any click unlocks it.
	document.addEventListener( 'click', function () { if ( on && ( ! ctx || ctx.state === 'suspended' ) ) { try { ctx = ctx || new ( window.AudioContext || window.webkitAudioContext )(); ctx.resume(); } catch ( e ) {} } }, { once: true } );
	label();
	setInterval( check, 20000 );
} )( window.flAlerts );
JS
		);
		wp_add_inline_style( 'common', '.fl-alert-btn .dashicons{font-size:16px;width:16px;height:16px;vertical-align:-3px}.fl-alert-btn.is-on{background:#e6f4ea!important;border-color:#1e6b34!important;color:#1e6b34!important}' );
	}
);
