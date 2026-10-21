<?php
/**
 * WooCommerce Subscriptions integration.
 *
 * Simple and variable subscription products use the same product, variation,
 * category and default settings as any other product. This class handles what
 * is specific to subscriptions:
 *
 * - The subscription itself is not shipped, so it never stores an estimate
 *   (see Dleta_Frontend::add_order_item_meta()).
 * - Each renewal order gets a fresh estimate based on the renewal date.
 *
 * @package UltimateEDD
 */

defined( 'ABSPATH' ) || exit;

/**
 * Subscriptions support.
 */
class Dleta_Subscriptions {

	/**
	 * Register hooks. They are harmless when Subscriptions is not installed.
	 */
	public static function init() {
		add_filter( 'wcs_renewal_order_created', array( __CLASS__, 'renewal_order_created' ), 20, 2 );
	}

	/**
	 * Whether WooCommerce Subscriptions is active.
	 *
	 * @return bool
	 */
	public static function is_active() {
		return class_exists( 'WC_Subscriptions' ) || function_exists( 'wcs_is_subscription' );
	}

	/**
	 * Add a fresh estimate to every shippable item of a new renewal order.
	 *
	 * @param WC_Order $renewal_order Renewal order.
	 * @param mixed    $subscription  Subscription (unused).
	 * @return WC_Order
	 */
	public static function renewal_order_created( $renewal_order, $subscription = null ) {
		unset( $subscription );

		if ( ! $renewal_order instanceof WC_Order ) {
			return $renewal_order;
		}

		if ( 'yes' !== Dleta_Rules::option( 'dleta_order_enabled' ) || 'yes' !== Dleta_Rules::option( 'dleta_subs_renewal' ) ) {
			return $renewal_order;
		}

		foreach ( $renewal_order->get_items( 'line_item' ) as $item ) {
			if ( $item instanceof WC_Order_Item_Product && Dleta_Frontend::add_estimate_to_item( $item, $item->get_product() ) ) {
				$item->save();
			}
		}

		return $renewal_order;
	}
}
