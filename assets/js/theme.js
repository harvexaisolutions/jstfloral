/* Fleuriste – sticky header: the announcement bar scrolls away and the header gets a
   little smaller once the page is scrolled. Separate on/off points (hysteresis) so the
   header never flickers between the two sizes. */
( function () {
	var root = document.documentElement;
	var body = document.body;
	var bar = document.querySelector( '.fl-announce' );
	var ticking = false;
	function measure() {
		var h = bar && ! bar.classList.contains( 'is-empty' ) ? bar.offsetHeight : 0;
		root.style.setProperty( '--fl-ann-h', h + 'px' );
	}
	function update() {
		var y = window.scrollY;
		var on = body.classList.contains( 'fl-scrolled' );
		if ( ! on && y > 160 ) {
			body.classList.add( 'fl-scrolled' );
		} else if ( on && y < 40 ) {
			body.classList.remove( 'fl-scrolled' );
		}
		ticking = false;
	}
	window.addEventListener( 'scroll', function () {
		if ( ! ticking ) {
			window.requestAnimationFrame( update );
			ticking = true;
		}
	}, { passive: true } );
	window.addEventListener( 'resize', measure );
	if ( bar && 'ResizeObserver' in window ) {
		new ResizeObserver( measure ).observe( bar );
	}
	measure();
	update();
} )();

/* Fleuriste – announcement bar: show messages whose schedule matches now (shop time zone), rotate them. */
( function () {
	var bar = document.querySelector( '.fl-announce' );
	if ( ! bar ) {
		return;
	}
	var msgs = Array.prototype.slice.call( bar.querySelectorAll( '.fl-announce__msg' ) );
	var tz = bar.getAttribute( 'data-tz' ) || '';
	var interval = ( parseInt( bar.getAttribute( 'data-interval' ), 10 ) || 5 ) * 1000;
	var index = 0;
	var active = [];

	// Weekday (1 = Monday) and "HH:MM" in the shop's time zone.
	function shopNow() {
		var now = new Date();
		var m = /^([+-])(\d{2}):(\d{2})$/.exec( tz );
		if ( m || ! tz ) {
			var offset = m ? ( m[ 1 ] === '-' ? -1 : 1 ) * ( parseInt( m[ 2 ], 10 ) * 60 + parseInt( m[ 3 ], 10 ) ) : 0;
			var d = new Date( now.getTime() + offset * 60000 );
			return { day: d.getUTCDay() || 7, time: ( '0' + d.getUTCHours() ).slice( -2 ) + ':' + ( '0' + d.getUTCMinutes() ).slice( -2 ) };
		}
		try {
			var parts = new Intl.DateTimeFormat( 'en-GB', { timeZone: tz, weekday: 'short', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' } ).formatToParts( now );
			var get = function ( t ) { return ( parts.filter( function ( p ) { return p.type === t; } )[ 0 ] || {} ).value; };
			var days = { Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6, Sun: 7 };
			return { day: days[ get( 'weekday' ) ] || 1, time: get( 'hour' ) + ':' + get( 'minute' ) };
		} catch ( e ) {
			return { day: now.getDay() || 7, time: ( '0' + now.getHours() ).slice( -2 ) + ':' + ( '0' + now.getMinutes() ).slice( -2 ) };
		}
	}

	function isOn( el, now, local ) {
		var start = +el.getAttribute( 'data-start' ) || 0;
		var end = +el.getAttribute( 'data-end' ) || 0;
		if ( ( start && now < start ) || ( end && now >= end ) ) {
			return false;
		}
		var days = ( el.getAttribute( 'data-days' ) || '' ).split( ',' ).filter( Boolean );
		if ( days.length && days.indexOf( String( local.day ) ) === -1 ) {
			return false;
		}
		var from = el.getAttribute( 'data-from' ) || '';
		var to = el.getAttribute( 'data-to' ) || '';
		var t = local.time;
		if ( from && to ) {
			return from <= to ? ( t >= from && t < to ) : ( t >= from || t < to );
		}
		if ( from ) {
			return t >= from;
		}
		if ( to ) {
			return t < to;
		}
		return true;
	}

	function refresh() {
		var now = Date.now();
		var local = shopNow();
		active = msgs.filter( function ( el ) { return isOn( el, now, local ); } );
		bar.classList.toggle( 'is-empty', active.length === 0 );
		if ( index >= active.length ) {
			index = 0;
		}
		show();
	}

	function show() {
		msgs.forEach( function ( el ) { el.hidden = true; } );
		if ( active[ index ] ) {
			active[ index ].hidden = false;
		}
	}

	refresh();
	setInterval( refresh, 30000 );
	setInterval( function () {
		if ( active.length > 1 && ! bar.matches( ':hover' ) ) {
			index = ( index + 1 ) % active.length;
			show();
		}
	}, interval );
} )();
