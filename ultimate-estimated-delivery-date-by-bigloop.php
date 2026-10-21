<?php
/**
 * Plugin Name:          Ultimate Estimated Delivery Date by BigLoop
 * Plugin URI:           https://wordpress.org/plugins/ultimate-estimated-delivery-date-by-bigloop/
 * Description:          Show an estimated delivery date on product, cart and checkout pages. Set it globally, per category, per product or per variation, with weekend skipping. Supports WooCommerce Subscriptions, WPML and RTL.
 * Version:              1.0.0
 * Requires at least:    6.5
 * Requires PHP:         7.4
 * Requires Plugins:     woocommerce
 * Author:               BigLoop
 * Author URI:           https://bigloop.dev/
 * License:              GPLv2 or later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          ultimate-estimated-delivery-date-by-bigloop
 * WC requires at least: 8.0
 * WC tested up to:      11.1
 *
 * @package UltimateEDD
 */

defined( 'ABSPATH' ) || exit;

define( 'DLETA_VERSION', '1.0.0' );
define( 'DLETA_FILE', __FILE__ );
define( 'DLETA_PATH', plugin_dir_path( __FILE__ ) );
define( 'DLETA_URL', plugin_dir_url( __FILE__ ) );

/**
 * Declare compatibility with WooCommerce features (HPOS and Cart/Checkout blocks).
 */
function dleta_declare_wc_compatibility() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}
}
add_action( 'before_woocommerce_init', 'dleta_declare_wc_compatibility' );

/**
 * Boot the plugin once all plugins are loaded and WooCommerce is available.
 */
function dleta_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	require_once DLETA_PATH . 'includes/class-dleta-rules.php';
	require_once DLETA_PATH . 'includes/class-dleta-calculator.php';
	require_once DLETA_PATH . 'includes/class-dleta-frontend.php';
	require_once DLETA_PATH . 'includes/class-dleta-order-estimate.php';
	require_once DLETA_PATH . 'includes/class-dleta-subscriptions.php';

	Dleta_Frontend::init();
	Dleta_Order_Estimate::init();
	Dleta_Subscriptions::init();

	if ( is_admin() ) {
		require_once DLETA_PATH . 'includes/admin/class-dleta-admin.php';
		require_once DLETA_PATH . 'includes/admin/class-dleta-admin-ui.php';
		require_once DLETA_PATH . 'includes/admin/class-dleta-category-fields.php';
		require_once DLETA_PATH . 'includes/admin/class-dleta-product-tab.php';
		require_once DLETA_PATH . 'includes/admin/class-dleta-variation-fields.php';

		Dleta_Admin::init();
		Dleta_Category_Fields::init();
		Dleta_Product_Tab::init();
		Dleta_Variation_Fields::init();
	}
}
add_action( 'plugins_loaded', 'dleta_init' );

/**
 * Store default settings on activation without overwriting existing values.
 */
function dleta_activate() {
	require_once DLETA_PATH . 'includes/class-dleta-rules.php';

	foreach ( Dleta_Rules::default_options() as $option => $value ) {
		if ( false === get_option( $option ) ) {
			add_option( $option, $value );
		}
	}
}
register_activation_hook( __FILE__, 'dleta_activate' );
