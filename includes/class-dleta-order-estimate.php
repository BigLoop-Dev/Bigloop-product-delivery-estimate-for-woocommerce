<?php
/**
 * One combined estimate for the whole cart, shown after the order total
 * on the cart and checkout pages (classic templates and blocks).
 *
 * @package UltimateEDD
 */

defined( 'ABSPATH' ) || exit;

/**
 * Order-wide estimate.
 */
class Dleta_Order_Estimate {

	/**
	 * Store API extension namespace.
	 */
	const EXT_NAMESPACE = 'dleta';

	/**
	 * Register hooks.
	 */
	public static function init() {
		// Classic cart and checkout: a row right after the order total.
		add_action( 'woocommerce_cart_totals_after_order_total', array( __CLASS__, 'render_cart_row' ) );
		add_action( 'woocommerce_review_order_after_order_total', array( __CLASS__, 'render_checkout_row' ) );

		// Cart and Checkout blocks: data through the Store API, rendered by a small script.
		if ( did_action( 'woocommerce_blocks_loaded' ) ) {
			self::register_store_api_data();
		} else {
			add_action( 'woocommerce_blocks_loaded', array( __CLASS__, 'register_store_api_data' ) );
		}
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_blocks_script' ) );
	}

	/**
	 * Calculation modes for the combined estimate.
	 *
	 * @return array<string,string>
	 */
	public static function mode_labels() {
		return array(
			'span'     => __( 'Earliest to latest (earliest item minimum – latest item maximum)', 'ultimate-estimated-delivery-date-by-bigloop' ),
			'complete' => __( 'Complete order (when every item should have arrived)', 'ultimate-estimated-delivery-date-by-bigloop' ),
		);
	}

	/**
	 * Whether the combined line is switched on for a context.
	 *
	 * @param string $context 'cart' or 'checkout'.
	 * @return bool
	 */
	public static function is_enabled( $context ) {
		$option = 'checkout' === $context ? 'dleta_checkout_total_enabled' : 'dleta_cart_total_enabled';
		return 'yes' === Dleta_Rules::option( $option );
	}

	/**
	 * Combined earliest and latest dates for the products in the cart.
	 *
	 * Items that show no estimate (virtual, hidden, out of stock when hidden)
	 * are left out.
	 *
	 * @return array{min:DateTimeImmutable,max:DateTimeImmutable}|null
	 */
	public static function cart_dates() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
			return null;
		}

		$mins = array();
		$maxs = array();

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( empty( $cart_item['data'] ) || ! $cart_item['data'] instanceof WC_Product ) {
				continue;
			}

			$product = $cart_item['data'];
			if ( ! Dleta_Frontend::should_show_for( $product ) ) {
				continue;
			}

			$resolved = Dleta_Rules::resolve( $product );
			$dates    = Dleta_Calculator::calculate( $resolved['rule'] );
			if ( null === $dates ) {
				continue;
			}

			$mins[] = $dates['min'];
			$maxs[] = $dates['max'];
		}

		if ( empty( $mins ) ) {
			return null;
		}

		$mode   = Dleta_Rules::option( 'dleta_total_mode' );
		$result = array(
			// "Complete order" starts at the latest minimum; "span" at the earliest.
			'min' => 'complete' === $mode ? max( $mins ) : min( $mins ),
			'max' => max( $maxs ),
		);

		/**
		 * Filter the combined cart delivery dates.
		 *
		 * @param array  $result Array with 'min' and 'max' DateTimeImmutable objects.
		 * @param string $mode   'span' or 'complete'.
		 */
		return apply_filters( 'dleta_cart_dates', $result, $mode );
	}

	/**
	 * Combined estimate text for the cart, or empty string.
	 *
	 * @return string Plain text (not escaped).
	 */
	public static function cart_text() {
		$dates = self::cart_dates();
		return null === $dates ? '' : Dleta_Calculator::format( $dates );
	}

	/**
	 * Label for the combined line.
	 *
	 * @return string Plain text (not escaped).
	 */
	public static function label() {
		$label = trim( Dleta_Rules::option( 'dleta_total_label' ) );
		return '' === $label ? __( 'Estimated delivery for your order', 'ultimate-estimated-delivery-date-by-bigloop' ) : $label;
	}

	/**
	 * Classic cart totals row.
	 */
	public static function render_cart_row() {
		self::render_row( 'cart' );
	}

	/**
	 * Classic checkout order review row.
	 */
	public static function render_checkout_row() {
		self::render_row( 'checkout' );
	}

	/**
	 * Print a table row with the combined estimate.
	 *
	 * @param string $context 'cart' or 'checkout'.
	 */
	private static function render_row( $context ) {
		if ( ! self::is_enabled( $context ) ) {
			return;
		}

		$text = self::cart_text();
		if ( '' === $text ) {
			return;
		}

		$label = self::label();
		?>
		<tr class="dleta-order-estimate">
			<th><?php echo esc_html( $label ); ?></th>
			<td data-title="<?php echo esc_attr( $label ); ?>"><?php echo esc_html( $text ); ?></td>
		</tr>
		<?php
	}

	/**
	 * Expose the combined estimate on the Store API cart response
	 * (extensions.dleta) for the Cart and Checkout blocks.
	 */
	public static function register_store_api_data() {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' )
			|| ! class_exists( '\Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema' ) ) {
			return;
		}

		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema::IDENTIFIER,
				'namespace'       => self::EXT_NAMESPACE,
				'data_callback'   => array( __CLASS__, 'store_api_data' ),
				'schema_callback' => array( __CLASS__, 'store_api_schema' ),
				'schema_type'     => ARRAY_A,
			)
		);
	}

	/**
	 * Data added to the Store API cart response.
	 *
	 * @return array
	 */
	public static function store_api_data() {
		$show_cart     = self::is_enabled( 'cart' );
		$show_checkout = self::is_enabled( 'checkout' );

		return array(
			'label'         => self::label(),
			'text'          => ( $show_cart || $show_checkout ) ? self::cart_text() : '',
			'show_cart'     => $show_cart,
			'show_checkout' => $show_checkout,
		);
	}

	/**
	 * Schema for the Store API data.
	 *
	 * @return array
	 */
	public static function store_api_schema() {
		return array(
			'label'         => array(
				'description' => __( 'Label for the combined delivery estimate.', 'ultimate-estimated-delivery-date-by-bigloop' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'text'          => array(
				'description' => __( 'Combined delivery estimate for the cart.', 'ultimate-estimated-delivery-date-by-bigloop' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'show_cart'     => array(
				'description' => __( 'Whether to show the estimate on the cart page.', 'ultimate-estimated-delivery-date-by-bigloop' ),
				'type'        => 'boolean',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'show_checkout' => array(
				'description' => __( 'Whether to show the estimate on the checkout page.', 'ultimate-estimated-delivery-date-by-bigloop' ),
				'type'        => 'boolean',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
		);
	}

	/**
	 * Load the blocks integration script on cart and checkout pages that use blocks.
	 */
	public static function enqueue_blocks_script() {
		if ( ! self::is_enabled( 'cart' ) && ! self::is_enabled( 'checkout' ) ) {
			return;
		}

		if ( ! is_cart() && ! is_checkout() ) {
			return;
		}

		if ( ! has_block( 'woocommerce/cart' ) && ! has_block( 'woocommerce/checkout' ) ) {
			return;
		}

		if ( ! wp_script_is( 'wc-blocks-checkout', 'registered' ) ) {
			return;
		}

		wp_enqueue_script(
			'dleta-blocks',
			DLETA_URL . 'assets/js/blocks.js',
			array( 'wp-element', 'wp-plugins', 'wc-blocks-checkout' ),
			DLETA_VERSION,
			true
		);
	}
}
