<?php
/**
 * Settings tab under WooCommerce > Settings > Estimated Delivery, plus admin assets.
 *
 * The tab is split into sections (Delivery time, Placement, Appearance,
 * Advanced). Layout pieces such as cards, wireframes and previews are custom
 * WooCommerce field types rendered by Dleta_Admin_UI.
 *
 * @package UltimateEDD
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin settings.
 */
class Dleta_Admin {

	/**
	 * Settings tab ID.
	 */
	const TAB = 'dleta';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'woocommerce_settings_tabs_array', array( __CLASS__, 'add_tab' ), 50 );
		add_action( 'woocommerce_sections_' . self::TAB, array( 'Dleta_Admin_UI', 'header_and_nav' ) );
		add_action( 'woocommerce_settings_tabs_' . self::TAB, array( __CLASS__, 'output' ) );
		add_action( 'woocommerce_update_options_' . self::TAB, array( __CLASS__, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( DLETA_FILE ), array( __CLASS__, 'action_links' ) );

		Dleta_Admin_UI::init();

		add_action( 'wp_ajax_dleta_format_preview', array( __CLASS__, 'ajax_format_preview' ) );

		$sanitizers = array(
			'dleta_icon'             => array( __CLASS__, 'sanitize_icon' ),
			'dleta_icon_custom'      => 'absint',
			'dleta_icon_color'       => array( __CLASS__, 'sanitize_color' ),
			'dleta_text_color'       => array( __CLASS__, 'sanitize_color' ),
			'dleta_date_color'       => array( __CLASS__, 'sanitize_color' ),
			'dleta_total_mode'       => array( __CLASS__, 'sanitize_total_mode' ),
			'dleta_font_family'      => array( __CLASS__, 'sanitize_font_choice' ),
			'dleta_date_format'      => array( __CLASS__, 'sanitize_date_format' ),
			'dleta_font_custom'      => array( 'Dleta_Frontend', 'sanitize_font_family' ),
			'dleta_font_size'        => array( __CLASS__, 'sanitize_size' ),
			'dleta_icon_size'        => array( __CLASS__, 'sanitize_size' ),
			'dleta_product_position' => array( __CLASS__, 'sanitize_position' ),
			'dleta_min_days'         => array( 'Dleta_Rules', 'clamp_days' ),
			'dleta_max_days'         => array( 'Dleta_Rules', 'clamp_days' ),
			'dleta_fixed_days'       => array( 'Dleta_Rules', 'clamp_days' ),
		);
		foreach ( $sanitizers as $option => $callback ) {
			add_filter( 'woocommerce_admin_settings_sanitize_option_' . $option, $callback );
		}
	}

	/**
	 * Section IDs and labels. The empty key is the first (default) section.
	 *
	 * @return array<string,string>
	 */
	public static function sections() {
		return array(
			''           => __( 'Delivery time', 'ultimate-estimated-delivery-date-by-bigloop' ),
			'placement'  => __( 'Placement', 'ultimate-estimated-delivery-date-by-bigloop' ),
			'appearance' => __( 'Appearance', 'ultimate-estimated-delivery-date-by-bigloop' ),
			'advanced'   => __( 'Advanced', 'ultimate-estimated-delivery-date-by-bigloop' ),
		);
	}

	/**
	 * The section being viewed or saved.
	 *
	 * @return string
	 */
	public static function current_section() {
		global $current_section;

		$section = is_string( $current_section ) ? $current_section : '';
		if ( '' === $section && isset( $_GET['section'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only reading which section to display.
			$section = sanitize_key( wp_unslash( $_GET['section'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		return array_key_exists( $section, self::sections() ) ? $section : '';
	}

	/**
	 * URL of the settings tab, optionally a section.
	 *
	 * @param string $section Section ID.
	 * @return string
	 */
	public static function settings_url( $section = '' ) {
		$url = admin_url( 'admin.php?page=wc-settings&tab=' . self::TAB );
		return '' === $section ? $url : add_query_arg( 'section', $section, $url );
	}

	/**
	 * Whether the current admin request is our settings tab.
	 *
	 * @return bool
	 */
	public static function is_settings_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		return $screen
			&& 'woocommerce_page_wc-settings' === $screen->id
			&& isset( $_GET['tab'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only reading the current tab.
			&& self::TAB === sanitize_key( wp_unslash( $_GET['tab'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Add the settings tab.
	 *
	 * @param array $tabs Tabs.
	 * @return array
	 */
	public static function add_tab( $tabs ) {
		$tabs[ self::TAB ] = __( 'Estimated Delivery', 'ultimate-estimated-delivery-date-by-bigloop' );
		return $tabs;
	}

	/**
	 * Body class used to scope the admin styles to our tab.
	 *
	 * @param string $classes Space-separated classes.
	 * @return string
	 */
	public static function body_class( $classes ) {
		if ( self::is_settings_screen() ) {
			$classes .= ' dleta-settings-page';
		}
		return $classes;
	}

	/**
	 * Output the fields of the current section.
	 */
	public static function output() {
		echo '<div class="dleta-settings">';
		WC_Admin_Settings::output_fields( self::fields( self::current_section() ) );
		echo '</div>';
	}

	/**
	 * Save the fields of the current section. WooCommerce verifies the nonce
	 * and the manage_woocommerce capability before this action runs.
	 */
	public static function save() {
		$section = self::current_section();
		WC_Admin_Settings::save_fields( self::fields( $section ) );

		if ( '' === $section ) {
			// Keep maximum days at or above minimum days.
			$min = Dleta_Rules::clamp_days( get_option( 'dleta_min_days' ) );
			$max = Dleta_Rules::clamp_days( get_option( 'dleta_max_days' ) );
			if ( $max < $min ) {
				update_option( 'dleta_max_days', (string) $min );
			}
		}
	}

	/**
	 * Sanitize the icon choice.
	 *
	 * @param mixed $value Submitted value.
	 * @return string
	 */
	public static function sanitize_icon( $value ) {
		$value = sanitize_key( (string) $value );
		return array_key_exists( $value, Dleta_Frontend::icon_labels() ) ? $value : 'truck';
	}

	/**
	 * Sanitize a PHP date format. Empty means "site date format".
	 *
	 * @param mixed $value Submitted value.
	 * @return string
	 */
	public static function sanitize_date_format( $value ) {
		$value = trim( sanitize_text_field( (string) $value ) );
		return mb_substr( $value, 0, 60 );
	}

	/**
	 * AJAX: preview a custom date format with the sample delivery dates.
	 */
	public static function ajax_format_preview() {
		check_ajax_referer( 'dleta_format_preview', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( null, 403 );
		}

		$format = isset( $_POST['format'] ) ? self::sanitize_date_format( wp_unslash( $_POST['format'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by sanitize_date_format().
		$dates  = Dleta_Calculator::calculate( Dleta_Rules::global_rule() );

		wp_send_json_success(
			array(
				'text' => null === $dates ? '' : Dleta_Calculator::format( $dates, $format ),
			)
		);
	}

	/**
	 * Sanitize the product page position.
	 *
	 * @param mixed $value Submitted value.
	 * @return string
	 */
	public static function sanitize_position( $value ) {
		$value = sanitize_key( (string) $value );
		return array_key_exists( $value, Dleta_Frontend::positions_labels() ) ? $value : 'after_price';
	}

	/**
	 * Sanitize the order estimate calculation mode.
	 *
	 * @param mixed $value Submitted value.
	 * @return string
	 */
	public static function sanitize_total_mode( $value ) {
		$value = sanitize_key( (string) $value );
		return array_key_exists( $value, Dleta_Order_Estimate::mode_labels() ) ? $value : 'span';
	}

	/**
	 * Sanitize the font choice.
	 *
	 * @param mixed $value Submitted value.
	 * @return string
	 */
	public static function sanitize_font_choice( $value ) {
		$value = sanitize_key( (string) $value );
		return array_key_exists( $value, Dleta_Frontend::font_choices() ) ? $value : 'inherit';
	}

	/**
	 * Sanitize a pixel size; empty means "default".
	 *
	 * @param mixed $value Submitted value.
	 * @return string
	 */
	public static function sanitize_size( $value ) {
		return Dleta_Frontend::sanitize_px( $value );
	}

	/**
	 * Sanitize a color. Empty means "inherit".
	 *
	 * @param mixed $value Submitted value.
	 * @return string
	 */
	public static function sanitize_color( $value ) {
		$color = sanitize_hex_color( (string) $value );
		return $color ? $color : '';
	}

	/**
	 * Fields for a section.
	 *
	 * @param string $section Section ID.
	 * @return array
	 */
	public static function fields( $section ) {
		switch ( $section ) {
			case 'placement':
				$fields = self::placement_fields();
				break;
			case 'appearance':
				$fields = self::appearance_fields();
				break;
			case 'advanced':
				$fields = self::advanced_fields();
				break;
			default:
				$fields = self::delivery_fields();
		}

		$fields = array_values( array_filter( $fields ) );

		/**
		 * Filter the settings fields of a section.
		 *
		 * @param array  $fields  Fields.
		 * @param string $section Section ID.
		 */
		return apply_filters( 'dleta_settings_fields', $fields, $section );
	}

	/**
	 * A layout-only field (never saved as an option).
	 *
	 * @param string $type Field type.
	 * @param array  $args Extra settings.
	 * @return array
	 */
	private static function ui( $type, array $args = array() ) {
		return array_merge(
			array(
				'type'      => 'dleta_' . $type,
				'is_option' => false,
			),
			$args
		);
	}

	/**
	 * Number field for a day count.
	 *
	 * @param string $id    Option name.
	 * @param string $title Label.
	 * @param string $css_class Visibility class.
	 * @param string $tip   Help tip.
	 * @return array
	 */
	private static function days_field( $id, $title, $css_class, $tip ) {
		$defaults = Dleta_Rules::default_options();

		return array(
			'title'             => $title,
			'id'                => $id,
			'type'              => 'number',
			'default'           => $defaults[ $id ],
			'class'             => $css_class . ' dleta-input-days',
			'custom_attributes' => array(
				'min'  => '0',
				'max'  => (string) Dleta_Rules::MAX_DAYS,
				'step' => '1',
			),
			'desc'              => __( 'days', 'ultimate-estimated-delivery-date-by-bigloop' ),
			'desc_tip'          => $tip,
		);
	}

	/**
	 * Section: Delivery time.
	 *
	 * @return array
	 */
	private static function delivery_fields() {
		$defaults = Dleta_Rules::default_options();

		return array(
			self::ui( 'hierarchy' ),

			self::ui(
				'card_start',
				array(
					'title' => __( 'Default delivery time', 'ultimate-estimated-delivery-date-by-bigloop' ),
					'desc'  => __( 'Used for every product that has no category or product estimate of its own.', 'ultimate-estimated-delivery-date-by-bigloop' ),
					'icon'  => 'clock',
					'split' => true,
				)
			),
			array(
				'title'   => __( 'Show as', 'ultimate-estimated-delivery-date-by-bigloop' ),
				'id'      => 'dleta_mode',
				'type'    => 'radio',
				'class'   => 'dleta-mode-radio',
				'default' => $defaults['dleta_mode'],
				'options' => array(
					'range' => __( 'Date range — e.g. "Thu, Oct 22 – Mon, Oct 26"', 'ultimate-estimated-delivery-date-by-bigloop' ),
					'fixed' => __( 'Single date — e.g. "Thu, Oct 22"', 'ultimate-estimated-delivery-date-by-bigloop' ),
				),
			),
			self::days_field( 'dleta_min_days', __( 'Earliest delivery', 'ultimate-estimated-delivery-date-by-bigloop' ), 'dleta-show-range', __( 'Days from today until the earliest delivery.', 'ultimate-estimated-delivery-date-by-bigloop' ) ),
			self::days_field( 'dleta_max_days', __( 'Latest delivery', 'ultimate-estimated-delivery-date-by-bigloop' ), 'dleta-show-range', __( 'Days from today until the latest delivery.', 'ultimate-estimated-delivery-date-by-bigloop' ) ),
			self::days_field( 'dleta_fixed_days', __( 'Delivery in', 'ultimate-estimated-delivery-date-by-bigloop' ), 'dleta-show-fixed', __( 'Days from today until delivery.', 'ultimate-estimated-delivery-date-by-bigloop' ) ),
			array(
				'title'   => __( 'Skip weekends', 'ultimate-estimated-delivery-date-by-bigloop' ),
				'desc'    => __( 'Don\'t count Saturdays and Sundays', 'ultimate-estimated-delivery-date-by-bigloop' ),
				'id'      => 'dleta_exclude_weekends',
				'type'    => 'checkbox',
				'default' => $defaults['dleta_exclude_weekends'],
			),
			self::ui( 'card_end', array( 'aside' => 'example' ) ),
		);
	}

	/**
	 * Section: Placement.
	 *
	 * @return array
	 */
	private static function placement_fields() {
		$defaults = Dleta_Rules::default_options();

		return array(
			self::ui(
				'card_start',
				array(
					'title' => __( 'Product page', 'ultimate-estimated-delivery-date-by-bigloop' ),
					'desc'  => __( 'Pick a spot from the list, or click a dashed line in the wireframe.', 'ultimate-estimated-delivery-date-by-bigloop' ),
					'icon'  => 'box',
					'split' => true,
				)
			),
			array(
				'title'   => __( 'Show on product pages', 'ultimate-estimated-delivery-date-by-bigloop' ),
				'desc'    => __( 'Display the estimate on single product pages', 'ultimate-estimated-delivery-date-by-bigloop' ),
				'id'      => 'dleta_product_enabled',
				'type'    => 'checkbox',
				'default' => $defaults['dleta_product_enabled'],
			),
			array(
				'title'   => __( 'Position', 'ultimate-estimated-delivery-date-by-bigloop' ),
				'id'      => 'dleta_product_position',
				'type'    => 'select',
				'class'   => 'dleta-position-select',
				'default' => $defaults['dleta_product_position'],
				'options' => Dleta_Frontend::positions_labels(),
			),
			self::ui(
				'info',
				array(
					'html' => sprintf(
						/* translators: 1: shortcode, 2: shortcode with product ID. */
						esc_html__( 'Need it somewhere else? Use %1$s on a product page or %2$s anywhere.', 'ultimate-estimated-delivery-date-by-bigloop' ),
						'<code>[dleta_estimate]</code>',
						'<code>[dleta_estimate id="123"]</code>'
					),
				)
			),
			self::ui( 'card_end', array( 'aside' => 'wireframe_product' ) ),

			self::ui(
				'card_start',
				array(
					'title' => __( 'Cart page', 'ultimate-estimated-delivery-date-by-bigloop' ),
					'desc'  => __( 'Works with the classic cart and the Cart block.', 'ultimate-estimated-delivery-date-by-bigloop' ),
					'icon'  => 'cart',
					'split' => true,
				)
			),
			array(
				'title'   => __( 'Under each product', 'ultimate-estimated-delivery-date-by-bigloop' ),
				'desc'    => __( 'Show the estimate below every item', 'ultimate-estimated-delivery-date-by-bigloop' ),
				'id'      => 'dleta_cart_enabled',
				'type'    => 'checkbox',
				'default' => $defaults['dleta_cart_enabled'],
			),
			array(
				'title'   => __( 'After the total', 'ultimate-estimated-delivery-date-by-bigloop' ),
				'desc'    => __( 'Show one combined estimate for the whole order', 'ultimate-estimated-delivery-date-by-bigloop' ),
				'id'      => 'dleta_cart_total_enabled',
				'type'    => 'checkbox',
				'default' => $defaults['dleta_cart_total_enabled'],
			),
			self::ui( 'card_end', array( 'aside' => 'wireframe_cart' ) ),

			self::ui(
				'card_start',
				array(
					'title' => __( 'Checkout page', 'ultimate-estimated-delivery-date-by-bigloop' ),
					'desc'  => __( 'Works with the classic checkout and the Checkout block.', 'ultimate-estimated-delivery-date-by-bigloop' ),
					'icon'  => 'card',
					'split' => true,
				)
			),
			array(
				'title'   => __( 'Under each product', 'ultimate-estimated-delivery-date-by-bigloop' ),
				'desc'    => __( 'Show the estimate below every item in the order summary', 'ultimate-estimated-delivery-date-by-bigloop' ),
				'id'      => 'dleta_checkout_enabled',
				'type'    => 'checkbox',
				'default' => $defaults['dleta_checkout_enabled'],
			),
			array(
				'title'   => __( 'After the total', 'ultimate-estimated-delivery-date-by-bigloop' ),
				'desc'    => __( 'Show one combined estimate for the whole order', 'ultimate-estimated-delivery-date-by-bigloop' ),
				'id'      => 'dleta_checkout_total_enabled',
				'type'    => 'checkbox',
				'default' => $defaults['dleta_checkout_total_enabled'],
			),
			self::ui( 'card_end', array( 'aside' => 'wireframe_checkout' ) ),

			self::ui(
				'card_start',
				array(
					'title' => __( 'Combined order estimate', 'ultimate-estimated-delivery-date-by-bigloop' ),
					'desc'  => __( 'The line after the total on the cart and checkout pages. Example: item A takes 2–4 days and item B 5–7 days. "Earliest to latest" shows days 2–7; "Complete order" shows days 5–7.', 'ultimate-estimated-delivery-date-by-bigloop' ),
					'icon'  => 'calendar',
					'class' => 'dleta-card--order-total',
				)
			),
			array(
				'title'   => __( 'Calculation', 'ultimate-estimated-delivery-date-by-bigloop' ),
				'id'      => 'dleta_total_mode',
				'type'    => 'radio',
				'default' => $defaults['dleta_total_mode'],
				'options' => Dleta_Order_Estimate::mode_labels(),
			),
			array(
				'title'   => __( 'Label', 'ultimate-estimated-delivery-date-by-bigloop' ),
				'id'      => 'dleta_total_label',
				'type'    => 'text',
				'default' => $defaults['dleta_total_label'],
				'class'   => 'regular-text',
			),
			self::ui( 'card_end' ),

			self::ui(
				'card_start',
				array(
					'title' => __( 'Orders and stock', 'ultimate-estimated-delivery-date-by-bigloop' ),
					'icon'  => 'pin',
				)
			),
			array(
				'title'   => __( 'Save on orders', 'ultimate-estimated-delivery-date-by-bigloop' ),
				'desc'    => __( 'Store the estimate on each order item, so it appears in the order admin and order emails', 'ultimate-estimated-delivery-date-by-bigloop' ),
				'id'      => 'dleta_order_enabled',
				'type'    => 'checkbox',
				'default' => $defaults['dleta_order_enabled'],
			),
			Dleta_Subscriptions::is_active() ? array(
				'title'   => __( 'Subscription renewals', 'ultimate-estimated-delivery-date-by-bigloop' ),
				'desc'    => __( 'Add a fresh estimate to each renewal order (WooCommerce Subscriptions)', 'ultimate-estimated-delivery-date-by-bigloop' ),
				'id'      => 'dleta_subs_renewal',
				'type'    => 'checkbox',
				'default' => $defaults['dleta_subs_renewal'],
			) : null,
			array(
				'title'   => __( 'Out of stock', 'ultimate-estimated-delivery-date-by-bigloop' ),
				'desc'    => __( 'Hide the estimate for out-of-stock products', 'ultimate-estimated-delivery-date-by-bigloop' ),
				'id'      => 'dleta_hide_out_of_stock',
				'type'    => 'checkbox',
				'default' => $defaults['dleta_hide_out_of_stock'],
			),
			self::ui( 'card_end' ),
		);
	}

	/**
	 * Section: Appearance.
	 *
	 * @return array
	 */
	private static function appearance_fields() {
		$defaults = Dleta_Rules::default_options();

		return array(
			self::ui(
				'card_start',
				array(
					'title' => __( 'Look and feel', 'ultimate-estimated-delivery-date-by-bigloop' ),
					'desc'  => __( 'Applies to the product page and the shortcode. Empty fields follow your theme.', 'ultimate-estimated-delivery-date-by-bigloop' ),
					'icon'  => 'brush',
					'split' => true,
				)
			),
			self::ui( 'subhead', array( 'title' => __( 'Text', 'ultimate-estimated-delivery-date-by-bigloop' ) ) ),
			array(
				'title'   => __( 'Label', 'ultimate-estimated-delivery-date-by-bigloop' ),
				'id'      => 'dleta_label',
				'type'    => 'text',
				'default' => $defaults['dleta_label'],
				'class'   => 'regular-text',
			),
			array(
				'title'   => __( 'Date format', 'ultimate-estimated-delivery-date-by-bigloop' ),
				'id'      => 'dleta_date_format',
				'type'    => 'dleta_date_format',
				'default' => $defaults['dleta_date_format'],
			),
			array(
				'title'   => __( 'Font', 'ultimate-estimated-delivery-date-by-bigloop' ),
				'id'      => 'dleta_font_family',
				'type'    => 'select',
				'default' => $defaults['dleta_font_family'],
				'options' => wp_list_pluck( Dleta_Frontend::font_choices(), 'label' ),
			),
			array(
				'title'       => __( 'Font name', 'ultimate-estimated-delivery-date-by-bigloop' ),
				'id'          => 'dleta_font_custom',
				'type'        => 'text',
				'default'     => $defaults['dleta_font_custom'],
				'class'       => 'regular-text',
				'placeholder' => '"Open Sans", sans-serif',
				'desc'        => __( 'A font your theme or another plugin already loads. Nothing is loaded from outside your site.', 'ultimate-estimated-delivery-date-by-bigloop' ),
			),
			array(
				'title'             => __( 'Size', 'ultimate-estimated-delivery-date-by-bigloop' ),
				'id'                => 'dleta_font_size',
				'type'              => 'number',
				'default'           => $defaults['dleta_font_size'],
				'class'             => 'dleta-input-px',
				'placeholder'       => __( 'Auto', 'ultimate-estimated-delivery-date-by-bigloop' ),
				'custom_attributes' => array(
					'min'  => '8',
					'max'  => '96',
					'step' => '1',
				),
				'desc'              => 'px',
			),
			array(
				'title'   => __( 'Text color', 'ultimate-estimated-delivery-date-by-bigloop' ),
				'id'      => 'dleta_text_color',
				'type'    => 'color',
				'default' => $defaults['dleta_text_color'],
				'css'     => 'width:7em;',
			),
			array(
				'title'   => __( 'Date color', 'ultimate-estimated-delivery-date-by-bigloop' ),
				'id'      => 'dleta_date_color',
				'type'    => 'color',
				'default' => $defaults['dleta_date_color'],
				'css'     => 'width:7em;',
			),
			self::ui( 'subhead', array( 'title' => __( 'Icon', 'ultimate-estimated-delivery-date-by-bigloop' ) ) ),
			array(
				'title'   => __( 'Icon', 'ultimate-estimated-delivery-date-by-bigloop' ),
				'id'      => 'dleta_icon',
				'type'    => 'dleta_icon_picker',
				'default' => $defaults['dleta_icon'],
			),
			array(
				'title'   => __( 'Your image', 'ultimate-estimated-delivery-date-by-bigloop' ),
				'id'      => 'dleta_icon_custom',
				'type'    => 'dleta_media',
				'default' => $defaults['dleta_icon_custom'],
				'desc'    => __( 'A small square SVG or PNG works best.', 'ultimate-estimated-delivery-date-by-bigloop' ),
			),
			array(
				'title'             => __( 'Size', 'ultimate-estimated-delivery-date-by-bigloop' ),
				'id'                => 'dleta_icon_size',
				'type'              => 'number',
				'default'           => $defaults['dleta_icon_size'],
				'class'             => 'dleta-input-px',
				'placeholder'       => __( 'Auto', 'ultimate-estimated-delivery-date-by-bigloop' ),
				'custom_attributes' => array(
					'min'  => '8',
					'max'  => '96',
					'step' => '1',
				),
				'desc'              => 'px',
			),
			array(
				'title'   => __( 'Icon color', 'ultimate-estimated-delivery-date-by-bigloop' ),
				'id'      => 'dleta_icon_color',
				'type'    => 'color',
				'default' => $defaults['dleta_icon_color'],
				'css'     => 'width:7em;',
			),
			self::ui( 'card_end', array( 'aside' => 'preview' ) ),
		);
	}

	/**
	 * Section: Advanced.
	 *
	 * @return array
	 */
	private static function advanced_fields() {
		$defaults = Dleta_Rules::default_options();

		return array(
			self::ui(
				'card_start',
				array(
					'title' => __( 'Shortcode', 'ultimate-estimated-delivery-date-by-bigloop' ),
					'desc'  => __( 'Place the estimate anywhere: page builders, product descriptions, widgets or block themes.', 'ultimate-estimated-delivery-date-by-bigloop' ),
					'icon'  => 'code',
				)
			),
			self::ui( 'shortcodes' ),
			self::ui( 'card_end' ),

			self::ui(
				'card_start',
				array(
					'title' => __( 'Data', 'ultimate-estimated-delivery-date-by-bigloop' ),
					'icon'  => 'database',
				)
			),
			array(
				'title'   => __( 'When deleting the plugin', 'ultimate-estimated-delivery-date-by-bigloop' ),
				'desc'    => __( 'Also delete all settings and the category and product estimates', 'ultimate-estimated-delivery-date-by-bigloop' ),
				'id'      => 'dleta_delete_data',
				'type'    => 'checkbox',
				'default' => $defaults['dleta_delete_data'],
			),
			self::ui(
				'info',
				array(
					'html' => esc_html__( 'Estimates already saved on orders are always kept, because they are part of the order history.', 'ultimate-estimated-delivery-date-by-bigloop' ),
				)
			),
			self::ui( 'card_end' ),

			self::ui(
				'card_start',
				array(
					'title' => __( 'Privacy', 'ultimate-estimated-delivery-date-by-bigloop' ),
					'icon'  => 'shield',
				)
			),
			self::ui(
				'info',
				array(
					'html' => esc_html__( 'This plugin works entirely inside your site. It makes no external requests, loads no remote files, and collects no data.', 'ultimate-estimated-delivery-date-by-bigloop' ),
				)
			),
			self::ui( 'card_end' ),
		);
	}

	/**
	 * Load admin CSS/JS only on the screens that need it.
	 */
	public static function enqueue() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$is_settings = self::is_settings_screen();
		$is_product  = 'product' === $screen->id;
		$is_category = 'edit-product_cat' === $screen->id;

		if ( ! $is_settings && ! $is_product && ! $is_category ) {
			return;
		}

		// The front-end stylesheet provides the icons used in admin previews.
		wp_enqueue_style( 'dleta-frontend', DLETA_URL . 'assets/css/frontend.css', array(), DLETA_VERSION );
		wp_enqueue_style( 'dleta-admin', DLETA_URL . 'assets/css/admin.css', array( 'dleta-frontend' ), DLETA_VERSION );
		wp_enqueue_script( 'dleta-admin', DLETA_URL . 'assets/js/admin.js', array( 'jquery' ), DLETA_VERSION, true );

		if ( ! $is_settings ) {
			return;
		}

		$data = array(
			'fontStacks' => wp_list_pluck( Dleta_Frontend::font_choices(), 'stack' ),
		);

		switch ( self::current_section() ) {
			case 'appearance':
				wp_enqueue_media();
				$data['previewNonce'] = wp_create_nonce( 'dleta_format_preview' );
				$data['ajaxUrl']      = admin_url( 'admin-ajax.php' );
				$data['mediaTitle']   = __( 'Choose a delivery icon', 'ultimate-estimated-delivery-date-by-bigloop' );
				$data['mediaButton']  = __( 'Use this icon', 'ultimate-estimated-delivery-date-by-bigloop' );
				break;
			case '':
				$data['calendar'] = Dleta_Admin_UI::calendar_data();
				break;
		}

		wp_localize_script( 'dleta-admin', 'dletaAdmin', $data );
	}

	/**
	 * Add a Settings link on the Plugins screen.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function action_links( $links ) {
		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( self::settings_url() ),
				esc_html__( 'Settings', 'ultimate-estimated-delivery-date-by-bigloop' )
			)
		);
		return $links;
	}
}
