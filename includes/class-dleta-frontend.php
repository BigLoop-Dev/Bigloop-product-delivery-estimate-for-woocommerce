<?php
/**
 * Front-end output: product page, cart, checkout, orders and shortcode.
 *
 * @package BigLoopPDE
 */

defined( 'ABSPATH' ) || exit;

/**
 * Front-end display.
 */
class Dleta_Frontend {

	/**
	 * Avoid printing twice on the same product page.
	 *
	 * @var array<int,bool>
	 */
	private static $printed = array();

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'wp', array( __CLASS__, 'register_product_hook' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'cart_item_data' ), 20, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'add_order_item_meta' ), 20, 4 );
		add_filter( 'woocommerce_available_variation', array( __CLASS__, 'variation_data' ), 20, 3 );
		add_shortcode( 'dleta_estimate', array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * Available positions on the single product page.
	 *
	 * Each entry: [ hook, priority ].
	 *
	 * @return array<string,array{0:string,1:int}>
	 */
	public static function positions() {
		$positions = array(
			'after_title'        => array( 'woocommerce_single_product_summary', 6 ),
			'after_price'        => array( 'woocommerce_single_product_summary', 11 ),
			'after_excerpt'      => array( 'woocommerce_single_product_summary', 21 ),
			'before_add_to_cart' => array( 'woocommerce_before_add_to_cart_form', 10 ),
			'after_add_to_cart'  => array( 'woocommerce_after_add_to_cart_form', 10 ),
			'after_meta'         => array( 'woocommerce_single_product_summary', 41 ),
			'after_summary'      => array( 'woocommerce_after_single_product_summary', 5 ),
		);

		/**
		 * Filter the available product page positions.
		 *
		 * @param array $positions Position key => [ hook name, priority ].
		 */
		return apply_filters( 'dleta_product_positions', $positions );
	}

	/**
	 * Labels for the position setting.
	 *
	 * @return array<string,string>
	 */
	public static function positions_labels() {
		return array(
			'after_title'        => __( 'After product title', 'bigloop-product-delivery-estimate' ),
			'after_price'        => __( 'After price', 'bigloop-product-delivery-estimate' ),
			'after_excerpt'      => __( 'After short description', 'bigloop-product-delivery-estimate' ),
			'before_add_to_cart' => __( 'Before add to cart button', 'bigloop-product-delivery-estimate' ),
			'after_add_to_cart'  => __( 'After add to cart button', 'bigloop-product-delivery-estimate' ),
			'after_meta'         => __( 'After product meta (SKU, categories)', 'bigloop-product-delivery-estimate' ),
			'after_summary'      => __( 'After product summary (before tabs)', 'bigloop-product-delivery-estimate' ),
			'manual'             => __( 'Shortcode only (place it yourself)', 'bigloop-product-delivery-estimate' ),
		);
	}

	/**
	 * Hook the product page output at the chosen position.
	 */
	public static function register_product_hook() {
		if ( 'yes' !== Dleta_Rules::option( 'dleta_product_enabled' ) || ! is_product() ) {
			return;
		}

		$positions = self::positions();
		$position  = Dleta_Rules::option( 'dleta_product_position' );

		if ( ! isset( $positions[ $position ] ) ) {
			return;
		}

		add_action( $positions[ $position ][0], array( __CLASS__, 'render_product_estimate' ), $positions[ $position ][1] );
	}

	/**
	 * Print the estimate for the current product.
	 */
	public static function render_product_estimate() {
		global $product;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		if ( isset( self::$printed[ $product->get_id() ] ) ) {
			return;
		}

		$html = self::product_html( $product );
		if ( '' !== $html ) {
			self::$printed[ $product->get_id() ] = true;
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in product_html().
		}
	}

	/**
	 * Build the estimate markup for a product.
	 *
	 * @param WC_Product $product Product.
	 * @return string Escaped HTML, or empty string.
	 */
	public static function product_html( $product ) {
		$variable = self::is_variable( $product );
		$text     = self::should_show_for( $product ) ? Dleta_Calculator::text_for_product( $product ) : '';

		// Variable products keep an (empty, hidden) box so the chosen variation can fill it.
		if ( '' === $text && ! $variable ) {
			return '';
		}

		$attrs = '';
		if ( $variable ) {
			self::enqueue_variation_script();
			$attrs = sprintf(
				' data-dleta-product="%1$d" data-dleta-default="%2$s"%3$s',
				absint( $product->get_id() ),
				esc_attr( $text ),
				'' === $text ? ' hidden' : ''
			);
		}

		$html = sprintf(
			'<div class="dleta-estimate"%1$s>%2$s<span class="dleta-estimate__label">%3$s</span> <span class="dleta-estimate__date">%4$s</span></div>',
			$attrs,
			self::icon_html(),
			esc_html( Dleta_Rules::option( 'dleta_label' ) ),
			esc_html( $text )
		);

		/**
		 * Filter the product page estimate HTML.
		 *
		 * @param string     $html    Escaped HTML.
		 * @param WC_Product $product Product.
		 * @param string     $text    Formatted date text.
		 */
		return (string) apply_filters( 'dleta_product_html', $html, $product, $text );
	}

	/**
	 * Whether a product has variations a shopper can pick (including variable subscriptions).
	 *
	 * @param WC_Product $product Product.
	 * @return bool
	 */
	public static function is_variable( $product ) {
		return $product instanceof WC_Product && $product->is_type( array( 'variable', 'variable-subscription' ) );
	}

	/**
	 * Add each variation's estimate to the data WooCommerce sends to the
	 * product page, so the estimate can switch when a variation is chosen.
	 *
	 * @param array                $data      Variation data.
	 * @param WC_Product           $parent_product Parent product (unused).
	 * @param WC_Product_Variation $variation Variation.
	 * @return array
	 */
	public static function variation_data( $data, $parent_product, $variation ) {
		unset( $parent_product );

		$data['dleta_text'] = ( $variation instanceof WC_Product && self::should_show_for( $variation ) )
			? Dleta_Calculator::text_for_product( $variation )
			: '';

		return $data;
	}

	/**
	 * Script that swaps the estimate when a variation is chosen.
	 */
	private static function enqueue_variation_script() {
		wp_enqueue_script( 'dleta-frontend', DLETA_URL . 'assets/js/frontend.js', array( 'jquery' ), DLETA_VERSION, true );
	}

	/**
	 * Built-in icons plus "custom" and "none". Keys match CSS classes dleta-icon--{key}.
	 *
	 * @return array<string,string>
	 */
	public static function icon_labels() {
		return array(
			'truck'    => __( 'Delivery truck', 'bigloop-product-delivery-estimate' ),
			'fast'     => __( 'Fast truck', 'bigloop-product-delivery-estimate' ),
			'box'      => __( 'Package', 'bigloop-product-delivery-estimate' ),
			'calendar' => __( 'Calendar', 'bigloop-product-delivery-estimate' ),
			'clock'    => __( 'Clock', 'bigloop-product-delivery-estimate' ),
			'pin'      => __( 'Location pin', 'bigloop-product-delivery-estimate' ),
			'custom'   => __( 'Custom image (Media Library)', 'bigloop-product-delivery-estimate' ),
			'none'     => __( 'No icon', 'bigloop-product-delivery-estimate' ),
		);
	}

	/**
	 * Icon markup for the chosen icon setting.
	 *
	 * @return string Escaped HTML, or empty string.
	 */
	private static function icon_html() {
		$icon = Dleta_Rules::option( 'dleta_icon' );
		$html = '';

		if ( 'custom' === $icon ) {
			$attachment_id = absint( Dleta_Rules::option( 'dleta_icon_custom' ) );
			$image         = $attachment_id ? wp_get_attachment_image(
				$attachment_id,
				'thumbnail',
				false,
				array(
					'class'   => 'dleta-estimate__icon dleta-estimate__icon--image',
					'alt'     => '',
					'loading' => false,
				)
			) : '';

			if ( $image ) {
				$html = $image;
			}
		} elseif ( 'none' !== $icon && array_key_exists( $icon, self::icon_labels() ) ) {
			$html = sprintf(
				'<span class="dleta-estimate__icon dleta-icon--%s" aria-hidden="true"></span>',
				esc_attr( $icon )
			);
		}

		/**
		 * Filter the icon HTML shown before the estimate.
		 *
		 * @param string $html Escaped HTML.
		 * @param string $icon Chosen icon key.
		 */
		return (string) apply_filters( 'dleta_icon_html', $html, $icon );
	}

	/**
	 * Whether a product may show an estimate (stock and purchasability checks).
	 *
	 * @param WC_Product $product Product.
	 * @return bool
	 */
	public static function should_show_for( $product ) {
		$show = true;

		if ( 'yes' === Dleta_Rules::option( 'dleta_hide_out_of_stock' ) && ! $product->is_in_stock() ) {
			$show = false;
		}

		// Virtual products are not delivered.
		if ( $product->is_virtual() ) {
			$show = false;
		}

		/**
		 * Filter whether the estimate is shown for a product.
		 *
		 * @param bool       $show    Whether to show it.
		 * @param WC_Product $product Product.
		 */
		return (bool) apply_filters( 'dleta_show_for_product', $show, $product );
	}

	/**
	 * Shortcode [dleta_estimate id="123"].
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id' => 0,
			),
			$atts,
			'dleta_estimate'
		);

		$product_id = absint( $atts['id'] );
		if ( ! $product_id ) {
			$current    = isset( $GLOBALS['product'] ) ? $GLOBALS['product'] : null;
			$product_id = $current instanceof WC_Product ? $current->get_id() : get_the_ID();
		}

		$item = $product_id ? wc_get_product( $product_id ) : null;
		if ( ! $item ) {
			return '';
		}

		self::enqueue_style();
		return self::product_html( $item );
	}

	/**
	 * Figure out whether we are rendering the cart or the checkout.
	 *
	 * Classic pages use is_cart()/is_checkout(). Cart and Checkout blocks load
	 * their data from the Store API, where we compare the page that made the
	 * request (the referer) with the cart and checkout pages.
	 *
	 * @return string 'cart', 'checkout' or ''.
	 */
	private static function context() {
		$context = '';

		if ( is_checkout() && ! is_wc_endpoint_url( 'order-received' ) ) {
			$context = 'checkout';
		} elseif ( is_cart() ) {
			$context = 'cart';
		} elseif ( self::is_store_api_request() ) {
			$referer = wp_get_referer();
			$page_id = $referer ? url_to_postid( $referer ) : 0;

			if ( $page_id ) {
				if ( wc_get_page_id( 'checkout' ) === $page_id || has_block( 'woocommerce/checkout', $page_id ) ) {
					$context = 'checkout';
				} elseif ( wc_get_page_id( 'cart' ) === $page_id || has_block( 'woocommerce/cart', $page_id ) ) {
					$context = 'cart';
				}
			}
		}

		/**
		 * Filter the detected context for cart item output.
		 *
		 * @param string $context 'cart', 'checkout' or ''.
		 */
		return (string) apply_filters( 'dleta_cart_context', $context );
	}

	/**
	 * Whether the current request is a WooCommerce Store API request.
	 *
	 * @return bool
	 */
	private static function is_store_api_request() {
		if ( method_exists( WC(), 'is_store_api_request' ) ) {
			return WC()->is_store_api_request();
		}

		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		$uri = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		return false !== strpos( $uri, 'wc/store' );
	}

	/**
	 * Add the estimate under each cart / checkout item.
	 *
	 * @param array $item_data Item data.
	 * @param array $cart_item Cart item.
	 * @return array
	 */
	public static function cart_item_data( $item_data, $cart_item ) {
		if ( empty( $cart_item['data'] ) || ! $cart_item['data'] instanceof WC_Product ) {
			return $item_data;
		}

		$context = self::context();
		$enabled = ( 'cart' === $context && 'yes' === Dleta_Rules::option( 'dleta_cart_enabled' ) )
			|| ( 'checkout' === $context && 'yes' === Dleta_Rules::option( 'dleta_checkout_enabled' ) );

		if ( ! $enabled || ! self::should_show_for( $cart_item['data'] ) ) {
			return $item_data;
		}

		$text = Dleta_Calculator::text_for_product( $cart_item['data'] );
		if ( '' === $text ) {
			return $item_data;
		}

		$item_data[] = array(
			'key'     => self::item_label(),
			'value'   => $text,
			'display' => esc_html( $text ),
		);

		return $item_data;
	}

	/**
	 * Save the estimate on order items.
	 *
	 * @param WC_Order_Item_Product $item          Order item.
	 * @param string                $cart_item_key Cart item key.
	 * @param array                 $values        Cart item values.
	 * @param WC_Order              $order         Order (or subscription).
	 */
	public static function add_order_item_meta( $item, $cart_item_key, $values, $order = null ) {
		if ( 'yes' !== Dleta_Rules::option( 'dleta_order_enabled' ) || empty( $values['data'] ) || ! $values['data'] instanceof WC_Product ) {
			return;
		}

		// WooCommerce Subscriptions: the subscription itself is not shipped, so it gets no date.
		// Renewal orders receive a fresh estimate instead (see Dleta_Subscriptions).
		if ( $order && function_exists( 'wcs_is_subscription' ) && wcs_is_subscription( $order ) ) {
			return;
		}

		self::add_estimate_to_item( $item, $values['data'] );
	}

	/**
	 * Store the current estimate for a product on an order item.
	 *
	 * @param WC_Order_Item_Product $item    Order item.
	 * @param WC_Product            $product Product (or variation).
	 * @return bool Whether an estimate was added.
	 */
	public static function add_estimate_to_item( $item, $product ) {
		if ( ! $product instanceof WC_Product || ! self::should_show_for( $product ) ) {
			return false;
		}

		$text = Dleta_Calculator::text_for_product( $product );
		if ( '' === $text ) {
			return false;
		}

		$item->update_meta_data( self::item_label(), $text );
		return true;
	}

	/**
	 * Label without trailing colon, for "Label: value" lists.
	 *
	 * @return string
	 */
	public static function item_label() {
		$label = trim( Dleta_Rules::option( 'dleta_label' ) );
		$label = rtrim( $label, ": \u{00A0}" );
		return '' === $label ? __( 'Estimated delivery', 'bigloop-product-delivery-estimate' ) : $label;
	}

	/**
	 * Font choices. Every option uses fonts already on the visitor's device or
	 * loaded by the theme; nothing is fetched from outside the site.
	 *
	 * @return array<string,array{label:string,stack:string}>
	 */
	public static function font_choices() {
		return array(
			'inherit' => array(
				'label' => __( 'Theme font (default)', 'bigloop-product-delivery-estimate' ),
				'stack' => 'inherit',
			),
			'system'  => array(
				'label' => __( 'System sans-serif', 'bigloop-product-delivery-estimate' ),
				'stack' => 'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
			),
			'serif'   => array(
				'label' => __( 'Serif', 'bigloop-product-delivery-estimate' ),
				'stack' => 'Georgia, "Times New Roman", Times, serif',
			),
			'mono'    => array(
				'label' => __( 'Monospace', 'bigloop-product-delivery-estimate' ),
				'stack' => 'ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace',
			),
			'custom'  => array(
				'label' => __( 'Custom (a font your theme already loads)', 'bigloop-product-delivery-estimate' ),
				'stack' => '',
			),
		);
	}

	/**
	 * Clean a custom font family so it is safe inside CSS.
	 *
	 * Allows letters, numbers, spaces, commas, hyphens, underscores and quotes,
	 * for example: "Open Sans", sans-serif.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_font_family( $value ) {
		$value = (string) preg_replace( '/[^\p{L}\p{N} ,\-_\'"]/u', '', (string) $value );
		return trim( mb_substr( $value, 0, 200 ) );
	}

	/**
	 * Clean a pixel size. Empty string means "use the default".
	 *
	 * @param mixed $value Raw value.
	 * @param int   $min   Smallest allowed size.
	 * @param int   $max   Largest allowed size.
	 * @return string
	 */
	public static function sanitize_px( $value, $min = 8, $max = 96 ) {
		if ( ! is_numeric( $value ) || 0 === absint( $value ) ) {
			return '';
		}
		return (string) min( $max, max( $min, absint( $value ) ) );
	}

	/**
	 * CSS custom properties built from the Appearance settings.
	 *
	 * @return string CSS, or empty string when everything uses the defaults.
	 */
	public static function style_css() {
		$vars = array();

		$font    = Dleta_Rules::option( 'dleta_font_family' );
		$choices = self::font_choices();
		if ( 'custom' === $font ) {
			$custom = self::sanitize_font_family( Dleta_Rules::option( 'dleta_font_custom' ) );
			if ( '' !== $custom ) {
				$vars['--dleta-font'] = $custom;
			}
		} elseif ( 'inherit' !== $font && isset( $choices[ $font ] ) ) {
			$vars['--dleta-font'] = $choices[ $font ]['stack'];
		}

		$size = self::sanitize_px( Dleta_Rules::option( 'dleta_font_size' ) );
		if ( '' !== $size ) {
			$vars['--dleta-size'] = $size . 'px';
		}

		$icon_size = self::sanitize_px( Dleta_Rules::option( 'dleta_icon_size' ) );
		if ( '' !== $icon_size ) {
			$vars['--dleta-icon-size'] = $icon_size . 'px';
		}

		$colors = array(
			'--dleta-color'      => 'dleta_text_color',
			'--dleta-date-color' => 'dleta_date_color',
			'--dleta-icon-color' => 'dleta_icon_color',
		);
		foreach ( $colors as $var => $option ) {
			$color = sanitize_hex_color( Dleta_Rules::option( $option ) );
			if ( $color ) {
				$vars[ $var ] = $color;
			}
		}

		if ( empty( $vars ) ) {
			return '';
		}

		$css = '';
		foreach ( $vars as $name => $value ) {
			$css .= $name . ':' . $value . ';';
		}

		/**
		 * Filter the generated appearance CSS.
		 *
		 * @param string $css  CSS rule.
		 * @param array  $vars Custom properties (name => value).
		 */
		return (string) apply_filters( 'dleta_style_css', '.dleta-estimate{' . $css . '}', $vars );
	}

	/**
	 * Register the stylesheet with the appearance settings attached.
	 */
	private static function register_style() {
		if ( wp_style_is( 'dleta-frontend', 'registered' ) ) {
			return;
		}

		wp_register_style( 'dleta-frontend', DLETA_URL . 'assets/css/frontend.css', array(), DLETA_VERSION );

		$css = self::style_css();
		if ( '' !== $css ) {
			wp_add_inline_style( 'dleta-frontend', $css );
		}
	}

	/**
	 * Register and enqueue the stylesheet on product pages.
	 */
	public static function enqueue() {
		self::register_style();

		if ( is_product() && 'yes' === Dleta_Rules::option( 'dleta_product_enabled' ) ) {
			wp_enqueue_style( 'dleta-frontend' );
		}
	}

	/**
	 * Enqueue the stylesheet when the shortcode is used elsewhere.
	 */
	private static function enqueue_style() {
		self::register_style();
		wp_enqueue_style( 'dleta-frontend' );
	}
}
