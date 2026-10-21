/**
 * Ultimate Estimated Delivery Date by BigLoop - variable products.
 *
 * When a shopper picks a variation, show that variation's estimate; when the
 * choice is cleared, go back to the product's estimate. Works for variable
 * products and variable subscriptions. Plain jQuery, no build step.
 */
( function ( $ ) {
	'use strict';

	function boxesFor( $form ) {
		var id = parseInt( $form.data( 'product_id' ), 10 );
		return $( '.dleta-estimate[data-dleta-product="' + id + '"]' );
	}

	function show( $boxes, text ) {
		$boxes.each( function () {
			var $box = $( this );
			$box.find( '.dleta-estimate__date' ).text( text || '' );
			$box.prop( 'hidden', ! text );
		} );
	}

	$( document.body ).on( 'found_variation', 'form.variations_form', function ( event, variation ) {
		var $form = $( this );
		var text = variation && 'string' === typeof variation.dleta_text ? variation.dleta_text : null;
		var $boxes = boxesFor( $form );

		// No data for this variation (e.g. cached markup): keep the product estimate.
		show( $boxes, null === text ? $boxes.first().attr( 'data-dleta-default' ) : text );
	} );

	$( document.body ).on( 'reset_data hide_variation', 'form.variations_form', function () {
		var $boxes = boxesFor( $( this ) );
		show( $boxes, $boxes.first().attr( 'data-dleta-default' ) );
	} );
}( jQuery ) );
