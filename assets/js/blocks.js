/**
 * Ultimate Estimated Delivery Date by BigLoop - combined order estimate for the Cart and Checkout blocks.
 *
 * Reads extensions.dleta from the Store API cart response and shows one line
 * after the order total (the "order meta" area under the totals).
 * Plain JavaScript using WordPress' bundled React (wp.element); no build step.
 */
( function ( wp, wc ) {
	'use strict';

	if ( ! wp || ! wp.plugins || ! wp.element || ! wc || ! wc.blocksCheckout || ! wc.blocksCheckout.ExperimentalOrderMeta ) {
		return;
	}

	var el = wp.element.createElement;
	var OrderMeta = wc.blocksCheckout.ExperimentalOrderMeta;

	function OrderEstimate( props ) {
		var data = props.extensions && props.extensions.dleta;
		if ( ! data || ! data.text ) {
			return null;
		}

		var isCheckout = 'woocommerce/checkout' === props.context;
		if ( isCheckout ? ! data.show_checkout : ! data.show_cart ) {
			return null;
		}

		return el(
			'div',
			{ className: 'wc-block-components-totals-item dleta-order-estimate' },
			el( 'span', { className: 'wc-block-components-totals-item__label' }, data.label ),
			el( 'span', { className: 'wc-block-components-totals-item__value' }, data.text )
		);
	}

	wp.plugins.registerPlugin( 'dleta-order-estimate', {
		scope: 'woocommerce-checkout',
		render: function () {
			return el( OrderMeta, null, el( OrderEstimate, null ) );
		}
	} );
}( window.wp, window.wc ) );
