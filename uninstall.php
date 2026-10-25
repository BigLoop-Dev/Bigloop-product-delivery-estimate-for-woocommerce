<?php
/**
 * Uninstall routine. Removes data only when the store owner opted in.
 *
 * Order item notes ("Estimated delivery: ...") are kept on purpose because
 * they are part of the order history.
 *
 * @package BigLoopPDE
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( 'yes' !== get_option( 'dleta_delete_data', 'no' ) ) {
	return;
}

$dleta_options = array(
	'dleta_mode',
	'dleta_min_days',
	'dleta_max_days',
	'dleta_fixed_days',
	'dleta_exclude_weekends',
	'dleta_product_enabled',
	'dleta_product_position',
	'dleta_cart_enabled',
	'dleta_checkout_enabled',
	'dleta_order_enabled',
	'dleta_subs_renewal',
	'dleta_cart_total_enabled',
	'dleta_checkout_total_enabled',
	'dleta_total_mode',
	'dleta_total_label',
	'dleta_hide_out_of_stock',
	'dleta_icon',
	'dleta_icon_custom',
	'dleta_icon_color',
	'dleta_icon_size',
	'dleta_font_family',
	'dleta_font_custom',
	'dleta_font_size',
	'dleta_text_color',
	'dleta_date_color',
	'dleta_label',
	'dleta_date_format',
	'dleta_delete_data',
);

foreach ( $dleta_options as $dleta_option ) {
	delete_option( $dleta_option );
}

$dleta_meta_keys = array(
	'_dleta_mode',
	'_dleta_min_days',
	'_dleta_max_days',
	'_dleta_fixed_days',
	'_dleta_exclude_weekends',
);

foreach ( $dleta_meta_keys as $dleta_meta_key ) {
	delete_post_meta_by_key( $dleta_meta_key );
	delete_metadata( 'term', 0, $dleta_meta_key, '', true );
}
