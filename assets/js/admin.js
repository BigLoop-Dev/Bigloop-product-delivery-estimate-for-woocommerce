/**
 * Ultimate Estimated Delivery Date by BigLoop - admin behaviour.
 *
 * - Shows only the fields that apply (range vs single date, custom font, icon options).
 * - Keeps the wireframes, live example and preview in sync with the form.
 * - Media Library picker for the custom icon, copy buttons for shortcodes.
 *
 * Plain jQuery (bundled with WordPress), no build step.
 */
( function ( $ ) {
	'use strict';

	var l10n = window.dletaAdmin || {};

	/* ------------------------------------------------------------------ *
	 * Helpers
	 * ------------------------------------------------------------------ */

	function row( $el ) {
		return $el.closest( 'tr' );
	}

	function isChecked( id ) {
		return $( '#' + id ).is( ':checked' );
	}

	function radioValue( name ) {
		return $( 'input[name="' + name + '"]:checked' ).val();
	}

	function isHex( value ) {
		return /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test( value || '' );
	}

	function px( value ) {
		var n = parseInt( value, 10 );
		return n > 0 ? Math.min( 96, Math.max( 8, n ) ) + 'px' : '';
	}

	/* ------------------------------------------------------------------ *
	 * Range / single date fields (settings, category and product screens)
	 * ------------------------------------------------------------------ */

	function toggleModeFields( $scope, mode ) {
		var rows = function ( selector ) {
			return $scope.find( selector ).map( function () {
				var $el = $( this );
				return $el.is( 'input, select' ) ? $el.closest( 'tr' ).get( 0 ) : this;
			} );
		};

		rows( '.dleta-show-range' ).toggle( 'range' === mode );
		rows( '.dleta-show-fixed' ).toggle( 'fixed' === mode );
		rows( '.dleta-show-rule' ).toggle( 'range' === mode || 'fixed' === mode );
	}

	function initModeFields() {
		// Category and product screens use a select.
		$( '.dleta-mode-select' ).each( function () {
			var $select = $( this );
			var $scope = $select.closest( 'form' );
			toggleModeFields( $scope, $select.val() );
			$select.on( 'change', function () {
				toggleModeFields( $scope, $select.val() );
			} );
		} );

		// The settings tab uses radio buttons.
		var $radios = $( 'input[name="dleta_mode"]' );
		if ( $radios.length ) {
			var $form = $radios.closest( 'form' );
			toggleModeFields( $form, radioValue( 'dleta_mode' ) );
			$radios.on( 'change', function () {
				toggleModeFields( $form, radioValue( 'dleta_mode' ) );
			} );
		}
	}

	/* ------------------------------------------------------------------ *
	 * Variation fields (loaded by WooCommerce over AJAX)
	 * ------------------------------------------------------------------ */

	function toggleVariation( $wrap ) {
		var mode = $wrap.find( '.dleta-var-mode' ).val();
		$wrap.find( '.dleta-var-range' ).toggle( 'range' === mode );
		$wrap.find( '.dleta-var-fixed' ).toggle( 'fixed' === mode );
		$wrap.find( '.dleta-var-rule' ).toggle( 'range' === mode || 'fixed' === mode );
	}

	function initVariations() {
		var $box = $( '#woocommerce-product-data' );
		if ( ! $box.length ) {
			return;
		}

		var refreshAll = function () {
			$( '[data-dleta-variation]' ).each( function () {
				toggleVariation( $( this ) );
			} );
		};

		$box.on( 'change', '.dleta-var-mode', function () {
			toggleVariation( $( this ).closest( '[data-dleta-variation]' ) );
		} );
		$box.on( 'woocommerce_variations_loaded woocommerce_variations_added', refreshAll );
		refreshAll();
	}

	/* ------------------------------------------------------------------ *
	 * Placement: wireframes follow the switches and position
	 * ------------------------------------------------------------------ */

	function refreshPlacement() {
		// Anything tied to a switch.
		$( '[data-dleta-when]' ).each( function () {
			var $el = $( this );
			$el.toggleClass( 'is-off', ! isChecked( $el.data( 'dleta-when' ) ) );
		} );

		// Cart / checkout panels: say so when nothing is shown.
		$( '.dleta-wf--cart, .dleta-wf--checkout' ).each( function () {
			var $panel = $( this );
			var anyOn = $panel.find( '[data-dleta-when]' ).filter( ':not(.is-off)' ).length > 0;
			$panel.toggleClass( 'is-empty', ! anyOn );
		} );

		// Product page position.
		var $position = $( '.dleta-position-select' );
		if ( $position.length ) {
			var position = $position.val();

			$( '.dleta-wf__slot' ).each( function () {
				var $slot = $( this );
				var active = $slot.data( 'position' ) === position;
				$slot.toggleClass( 'is-active', active ).attr( 'aria-pressed', active ? 'true' : 'false' );
			} );
			$( '[data-dleta-manual]' ).prop( 'hidden', 'manual' !== position );
			row( $position ).toggleClass( 'is-muted', ! isChecked( 'dleta_product_enabled' ) );
		}

		// The combined estimate settings only matter when one of its switches is on.
		if ( $( '#dleta_cart_total_enabled' ).length ) {
			$( '.dleta-card--order-total' ).toggleClass( 'is-muted', ! isChecked( 'dleta_cart_total_enabled' ) && ! isChecked( 'dleta_checkout_total_enabled' ) );
		}

		// Live text (for example the combined estimate label).
		$( '[data-dleta-text]' ).each( function () {
			var $el = $( this );
			var $input = $( '#' + $el.data( 'dleta-text' ) );
			if ( $input.length ) {
				$el.text( $input.val() );
			}
		} );
	}

	function initPlacement() {
		if ( ! $( '.dleta-wf' ).length ) {
			return;
		}

		$( '.dleta-wf__slot' ).on( 'click', function () {
			if ( ! isChecked( 'dleta_product_enabled' ) ) {
				$( '#dleta_product_enabled' ).prop( 'checked', true ).trigger( 'change' );
			}
			$( '.dleta-position-select' ).val( $( this ).data( 'position' ) ).trigger( 'change' );
		} );

		$( '#mainform' ).on( 'change input', 'input, select', refreshPlacement );
		refreshPlacement();
	}

	/* ------------------------------------------------------------------ *
	 * Delivery time: live example and mini calendar
	 * ------------------------------------------------------------------ */

	function initExample() {
		var cal = l10n.calendar;
		var $out = $( '[data-dleta-example]' );
		var $grid = $( '[data-dleta-calendar]' );
		if ( ! cal || ! cal.days || ! $out.length ) {
			return;
		}

		var days = cal.days;
		var last = days.length - 1;

		function skipped( i, weekends ) {
			return weekends ? days[ i ].sw : days[ i ].sn;
		}

		// Same counting as Dleta_Calculator::add_days().
		function addDays( count, weekends ) {
			var i = 0;
			var added = 0;
			while ( added < count && i < last ) {
				i++;
				if ( ! skipped( i, weekends ) ) {
					added++;
				}
			}
			while ( skipped( i, weekends ) && i < last ) {
				i++;
			}
			return i;
		}

		function dayCount( id ) {
			var n = parseInt( $( '#' + id ).val(), 10 );
			return isNaN( n ) || n < 0 ? 0 : Math.min( n, 365 );
		}

		function render() {
			var mode = radioValue( 'dleta_mode' ) || 'range';
			var weekends = isChecked( 'dleta_exclude_weekends' );
			var minDays = 'fixed' === mode ? dayCount( 'dleta_fixed_days' ) : dayCount( 'dleta_min_days' );
			var maxDays = 'fixed' === mode ? minDays : Math.max( minDays, dayCount( 'dleta_max_days' ) );
			var from = addDays( minDays, weekends );
			var to = addDays( maxDays, weekends );

			$out.text( from === to ? days[ from ].f : cal.range.replace( '%1$s', days[ from ].f ).replace( '%2$s', days[ to ].f ) );

			// Three weeks, starting on the Monday of the current week.
			var pad = days[ 0 ].w - 1;
			var cells = 21;
			var html = '';
			var c;

			for ( c = 0; c < 7; c++ ) {
				html += '<span class="dleta-cal__head">' + $( '<i>' ).text( cal.weekdays[ c ] ).html() + '</span>';
			}

			for ( c = 0; c < cells; c++ ) {
				var i = c - pad;
				if ( i < 0 ) {
					html += '<span class="dleta-cal__day is-past"></span>';
					continue;
				}

				var cls = 'dleta-cal__day';
				if ( 0 === i ) {
					cls += ' is-today';
				}
				if ( i > 0 && skipped( i, weekends ) ) {
					cls += ' is-skipped';
				}
				if ( i >= from && i <= to ) {
					cls += ' is-window';
					cls += i === from ? ' is-start' : '';
					cls += i === to ? ' is-end' : '';
				}
				html += '<span class="' + cls + '">' + days[ i ].d + '</span>';
			}

			$grid.html( html );
			$( '[data-dleta-beyond]' ).text( cal.beyond ).prop( 'hidden', to <= cells - pad - 1 );
		}

		$( '#mainform' ).on( 'change input', 'input', render );
		render();
	}

	/* ------------------------------------------------------------------ *
	 * Appearance: live preview, rows that apply, media picker
	 * ------------------------------------------------------------------ */

	function initAppearance() {
		var $box = $( '.dleta-preview' );
		if ( ! $box.length ) {
			return;
		}

		var stacks = l10n.fontStacks || {};
		var box = $box.get( 0 );
		var $customRow = $( '.dleta-row-custom-icon' );
		var $input = $( '#dleta_icon_custom' );
		var $thumb = $customRow.find( '.dleta-media-preview' );
		var $remove = $customRow.find( '.dleta-media-remove' );
		var fields = [ 'dleta_font_family', 'dleta_font_custom', 'dleta_font_size', 'dleta_text_color', 'dleta_date_color', 'dleta_icon_size', 'dleta_icon_color', 'dleta_icon_custom', 'dleta_label' ];
		var lastState = '';
		var frame;

		function val( id ) {
			return $( '#' + id ).val() || '';
		}

		function setVar( name, value ) {
			if ( value ) {
				box.style.setProperty( name, value );
			} else {
				box.style.removeProperty( name );
			}
		}

		function state() {
			return fields.map( val ).join( '|' ) + '|' + radioValue( 'dleta_icon' );
		}

		function refresh() {
			var icon = radioValue( 'dleta_icon' ) || 'truck';
			var font = val( 'dleta_font_family' );

			row( $( '#dleta_font_custom' ) ).toggle( 'custom' === font );
			$customRow.toggle( 'custom' === icon );
			row( $( '#dleta_icon_color' ) ).toggle( 'custom' !== icon && 'none' !== icon );
			row( $( '#dleta_icon_size' ) ).toggle( 'none' !== icon );

			setVar( '--dleta-font', 'custom' === font ? val( 'dleta_font_custom' ).replace( /[^\w\s,'"\-]/g, '' ) : ( 'inherit' === font ? '' : stacks[ font ] ) );
			setVar( '--dleta-size', px( val( 'dleta_font_size' ) ) );
			setVar( '--dleta-color', isHex( val( 'dleta_text_color' ) ) ? val( 'dleta_text_color' ) : '' );
			setVar( '--dleta-date-color', isHex( val( 'dleta_date_color' ) ) ? val( 'dleta_date_color' ) : '' );
			setVar( '--dleta-icon-size', px( val( 'dleta_icon_size' ) ) );
			setVar( '--dleta-icon-color', isHex( val( 'dleta_icon_color' ) ) ? val( 'dleta_icon_color' ) : '' );
			$box.find( '.dleta-estimate__label' ).text( val( 'dleta_label' ) );

			$box.find( '.dleta-estimate__icon' ).remove();
			if ( 'custom' === icon ) {
				var src = $thumb.find( 'img' ).attr( 'src' );
				if ( src ) {
					$box.prepend( $( '<img />', { src: src, alt: '', 'class': 'dleta-estimate__icon dleta-estimate__icon--image' } ) );
				}
			} else if ( 'none' !== icon ) {
				$box.prepend( $( '<span />', { 'class': 'dleta-estimate__icon dleta-icon--' + icon, 'aria-hidden': 'true' } ) );
			}

			lastState = state();
		}

		$( '#mainform' ).on( 'change input', 'input, select', refresh );

		// The WooCommerce color picker changes its input without firing events.
		window.setInterval( function () {
			if ( state() !== lastState ) {
				refresh();
			}
		}, 300 );

		$customRow.on( 'click', '.dleta-media-select', function ( event ) {
			event.preventDefault();
			if ( ! window.wp || ! window.wp.media ) {
				return;
			}

			if ( ! frame ) {
				frame = window.wp.media( {
					title: l10n.mediaTitle || '',
					button: { text: l10n.mediaButton || '' },
					library: { type: 'image' },
					multiple: false
				} );

				frame.on( 'select', function () {
					var attachment = frame.state().get( 'selection' ).first().toJSON();
					var sizes = attachment.sizes || {};
					var url = sizes.thumbnail ? sizes.thumbnail.url : attachment.url;

					$thumb.empty().append( $( '<img />', { src: url, alt: '', 'class': 'dleta-media-preview-img' } ) );
					$input.val( attachment.id ).trigger( 'change' );
					$remove.prop( 'hidden', false );
				} );
			}

			frame.open();
		} );

		$remove.on( 'click', function ( event ) {
			event.preventDefault();
			$thumb.empty();
			$input.val( '0' ).trigger( 'change' );
			$remove.prop( 'hidden', true );
		} );

		refresh();
	}

	/* ------------------------------------------------------------------ *
	 * Date format picker
	 * ------------------------------------------------------------------ */

	function initDateFormat() {
		var $radios = $( 'input[name="dleta_date_format"]' );
		if ( ! $radios.length ) {
			return;
		}

		var $custom = $radios.filter( '[data-custom]' );
		var $text = $( '.dleta-format__input' );
		var $customSample = $( '[data-dleta-custom-sample]' );
		var timer = null;
		var request = null;

		function showInPreview( text ) {
			$( '.dleta-preview .dleta-estimate__date' ).text( text );
		}

		function previewCustom() {
			var format = $.trim( $text.val() );
			$custom.val( format );

			if ( request ) {
				request.abort();
			}
			if ( ! format || ! l10n.ajaxUrl ) {
				$customSample.text( '' );
				return;
			}

			request = $.post( l10n.ajaxUrl, {
				action: 'dleta_format_preview',
				nonce: l10n.previewNonce,
				format: format
			} ).done( function ( response ) {
				if ( response && response.success ) {
					$custom.attr( 'data-sample', response.data.text );
					$customSample.text( response.data.text );
					if ( $custom.is( ':checked' ) ) {
						showInPreview( response.data.text );
					}
				}
			} );
		}

		$radios.on( 'change', function () {
			var $checked = $radios.filter( ':checked' );
			if ( $checked.is( $custom ) ) {
				$text.trigger( 'focus' );
			}
			showInPreview( $checked.attr( 'data-sample' ) || '' );
		} );

		$text.on( 'focus', function () {
			if ( ! $custom.is( ':checked' ) ) {
				$custom.prop( 'checked', true ).trigger( 'change' );
			}
		} );

		$text.on( 'input', function () {
			window.clearTimeout( timer );
			timer = window.setTimeout( previewCustom, 350 );
		} );
	}

	/* ------------------------------------------------------------------ *
	 * Copy buttons
	 * ------------------------------------------------------------------ */

	function initCopy() {
		$( '.dleta-copy' ).on( 'click', function () {
			var $btn = $( this );
			var text = String( $btn.data( 'copy' ) );
			var original = $btn.text();
			var done = function () {
				$btn.text( $btn.data( 'done' ) );
				window.setTimeout( function () {
					$btn.text( original );
				}, 1500 );
			};

			if ( window.navigator.clipboard && window.isSecureContext ) {
				window.navigator.clipboard.writeText( text ).then( done );
			} else {
				var $tmp = $( '<textarea readonly>' ).val( text ).css( { position: 'absolute', left: '-9999px' } ).appendTo( 'body' );
				$tmp.get( 0 ).select();
				document.execCommand( 'copy' );
				$tmp.remove();
				done();
			}
		} );
	}

	/* ------------------------------------------------------------------ */

	$( function () {
		initModeFields();
		initVariations();
		initPlacement();
		initExample();
		initAppearance();
		initDateFormat();
		initCopy();

		// Reset the category "add" form after a term was added via AJAX.
		$( document ).ajaxComplete( function ( event, xhr, settings ) {
			if ( settings && 'string' === typeof settings.data && -1 !== settings.data.indexOf( 'action=add-tag' ) ) {
				var $select = $( '#addtag .dleta-mode-select' );
				if ( $select.length ) {
					$select.val( '' );
					$( '#addtag #dleta_exclude_weekends' ).prop( 'checked', false );
					toggleModeFields( $select.closest( 'form' ), '' );
				}
			}
		} );
	} );
}( jQuery ) );
