/* Fleuriste – product page: live total, card message counter, quantity buttons, sticky add-to-cart bar. */
( function ( $ ) {
	var cfg = window.fleuristeProduct || {};

	function money( n ) {
		var d = parseInt( cfg.decimals, 10 ) || 0;
		var parts = Math.abs( n ).toFixed( d ).split( '.' );
		parts[ 0 ] = parts[ 0 ].replace( /\B(?=(\d{3})+(?!\d))/g, cfg.thousand || ',' );
		var num = parts.join( cfg.decimal || '.' );
		return ( cfg.format || '%1$s%2$s' ).replace( '%1$s', cfg.symbol || '' ).replace( '%2$s', num );
	}

	/* ---------- Quantity +/- buttons ---------- */
	function addSteppers( scope ) {
		$( scope ).find( '.quantity' ).each( function () {
			var $q = $( this );
			var $input = $q.find( 'input.qty' );
			if ( ! $input.length || $q.hasClass( 'fl-qty' ) || $input.attr( 'type' ) === 'hidden' ) {
				return;
			}
			$q.addClass( 'fl-qty' );
			$( '<button type="button" class="fl-qty__btn fl-qty__btn--minus" aria-label="Decrease quantity">−</button>' ).insertBefore( $input );
			$( '<button type="button" class="fl-qty__btn" aria-label="Increase quantity">+</button>' ).insertAfter( $input );
		} );
	}
	$( document ).on( 'click', '.fl-qty__btn', function () {
		var $input = $( this ).siblings( 'input.qty' );
		var step = parseFloat( $input.attr( 'step' ) ) || 1;
		var min = parseFloat( $input.attr( 'min' ) ) || 1;
		var max = parseFloat( $input.attr( 'max' ) ) || Infinity;
		var val = parseFloat( $input.val() ) || min;
		val = $( this ).hasClass( 'fl-qty__btn--minus' ) ? val - step : val + step;
		$input.val( Math.min( max, Math.max( min, val ) ) ).trigger( 'change' );
	} );

	/* ---------- Florist options ---------- */
	$( '.fl-options' ).each( function () {
		var box = this;
		var $box = $( box );
		var $form = $box.closest( 'form' );
		var num = function ( v ) {
			return v === '' || v === undefined || v === null || isNaN( parseFloat( v ) ) ? null : parseFloat( v );
		};
		var base = num( $box.attr( 'data-base' ) );
		var regular = num( $box.attr( 'data-regular' ) );
		var $mainPrice = $form.closest( '.summary' ).children( 'p.price' ).first();
		var $stickyPrice = $( '.fl-sticky-cart__price' );
		var originalMain = $mainPrice.html();
		var originalSticky = $stickyPrice.html();

		// Price HTML in WooCommerce's own markup, with the sale price struck through when there is one.
		function priceHtml( unit, unitRegular ) {
			var amount = function ( n ) {
				return '<span class="woocommerce-Price-amount amount"><bdi>' + $( '<span>' ).text( money( n ) ).html() + '</bdi></span>';
			};
			if ( unitRegular !== null && unitRegular > unit ) {
				return '<del aria-hidden="true">' + amount( unitRegular ) + '</del> <ins>' + amount( unit ) + '</ins>';
			}
			return amount( unit );
		}

		function extras() {
			var sum = 0;
			$box.find( 'input[data-price]:checked' ).each( function () {
				sum += parseFloat( this.getAttribute( 'data-price' ) ) || 0;
			} );
			var opt = $box.find( 'select[name="fleuriste_slot"] option:selected' )[ 0 ];
			if ( opt ) {
				sum += parseFloat( opt.getAttribute( 'data-price' ) ) || 0;
			}
			return sum;
		}

		// Add-on products are separate cart lines (one of each), so they aren't multiplied by the quantity.
		function addons() {
			var sum = 0;
			$box.find( 'input[data-addon-price]:checked' ).each( function () {
				sum += parseFloat( this.getAttribute( 'data-addon-price' ) ) || 0;
			} );
			return sum;
		}

		function update() {
			var qty = parseFloat( $form.find( 'input.qty' ).val() ) || 1;
			var $out = $box.find( '.fl-total__value' );
			var text;
			if ( base === null ) {
				text = cfg.chooseMsg || '–';
				$box.addClass( 'is-waiting' );
			} else {
				text = money( ( base + extras() ) * qty + addons() );
				$box.removeClass( 'is-waiting' );
			}
			$out.text( text );

			// Keep the price under the title and in the sticky bar in step with the choices.
			if ( base === null ) {
				$mainPrice.html( originalMain );
				$stickyPrice.html( originalSticky );
			} else {
				var add = extras();
				var html = priceHtml( base + add, regular === null ? null : regular + add );
				$mainPrice.html( html );
				$stickyPrice.html( html );
				$form.find( '.woocommerce-variation-price' ).hide();
			}

			var $msg = $box.find( 'textarea[name="fleuriste_message"]' );
			if ( $msg.length ) {
				$box.find( '.fl-count' ).text( $msg.val().length + ' / ' + $msg.attr( 'maxlength' ) );
			}
		}

		// Variable products: the base price comes from the chosen variation.
		$form.on( 'found_variation', function ( e, variation ) {
			base = num( variation.display_price );
			regular = num( variation.display_regular_price );
			if ( regular !== null && base !== null && regular <= base ) {
				regular = null;
			}
			update();
		} );
		$form.on( 'reset_data hide_variation', function () {
			base = null;
			regular = null;
			update();
		} );

		$form.on( 'change input', update );
		update();
	} );

	/* ---------- Sticky add-to-cart bar ---------- */
	var $bar = $( '.fl-sticky-cart' );
	var $form = $( 'form.cart' ).first();
	var target = $form.find( '.single_add_to_cart_button' )[ 0 ] || $form[ 0 ];
	if ( $bar.length && target && 'IntersectionObserver' in window ) {
		var seen = false;
		new IntersectionObserver( function ( entries ) {
			var visible = entries[ 0 ].isIntersecting;
			var past = entries[ 0 ].boundingClientRect.top < 0;
			if ( visible ) {
				seen = true;
			}
			var show = ! visible && past && seen;
			$bar.toggleClass( 'is-visible', show ).attr( 'aria-hidden', show ? 'false' : 'true' );
			$bar.find( 'button' ).attr( 'tabindex', show ? '0' : '-1' );
		} ).observe( target );

		$bar.on( 'click', '.fl-sticky-cart__btn', function () {
			var form = $form[ 0 ];
			var button = $form.find( '.single_add_to_cart_button' )[ 0 ];
			var ready = form.checkValidity() && button && ! button.classList.contains( 'disabled' );
			if ( ready ) {
				button.click();
			} else {
				form.scrollIntoView( { behavior: 'smooth', block: 'center' } );
				if ( form.reportValidity ) {
					setTimeout( function () { form.reportValidity(); }, 400 );
				}
			}
		} );
	}

	addSteppers( document );
	$( document.body ).on( 'updated_cart_totals updated_wc_div', function () {
		addSteppers( document );
	} );
} )( jQuery );
