<?php
/**
 * "Estimated Delivery" tab in the Product data box.
 *
 * @package UltimateEDD
 */

defined( 'ABSPATH' ) || exit;

/**
 * Product tab.
 */
class Dleta_Product_Tab {

	/**
	 * Nonce action and field name.
	 */
	const NONCE = 'dleta_product_nonce';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'add_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'panel' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save' ) );
	}

	/**
	 * Add the tab.
	 *
	 * @param array $tabs Tabs.
	 * @return array
	 */
	public static function add_tab( $tabs ) {
		$tabs['dleta'] = array(
			'label'    => __( 'Estimated Delivery', 'ultimate-estimated-delivery-date-by-bigloop' ),
			'target'   => 'dleta_product_data',
			'class'    => array(),
			'priority' => 75,
		);
		return $tabs;
	}

	/**
	 * Explain what the product would use without its own setting.
	 *
	 * @param int $product_id Product ID.
	 * @return string Plain text.
	 */
	private static function inherited_summary( $product_id ) {
		$category = Dleta_Rules::category_rule_for_product( $product_id );

		if ( null !== $category ) {
			$term = get_term( $category['term_id'], 'product_cat' );
			$name = ( $term && ! is_wp_error( $term ) ) ? $term->name : '';
			$rule = 'none' === $category['rule']['mode'] ? null : $category['rule'];

			/* translators: 1: category name, 2: rule summary. */
			return sprintf( __( 'Without a product setting, this product uses its category "%1$s": %2$s.', 'ultimate-estimated-delivery-date-by-bigloop' ), $name, Dleta_Rules::describe( $rule ) );
		}

		/* translators: %s: rule summary. */
		return sprintf( __( 'Without a product setting, this product uses the global setting: %s.', 'ultimate-estimated-delivery-date-by-bigloop' ), Dleta_Rules::describe( Dleta_Rules::global_rule() ) );
	}

	/**
	 * Box showing which level currently decides this product's estimate.
	 *
	 * @param int $product_id Product ID.
	 */
	private static function levels_box( $product_id ) {
		$own      = Dleta_Rules::product_rule( $product_id );
		$category = Dleta_Rules::category_rule_for_product( $product_id );
		$cat_name = __( 'Category', 'ultimate-estimated-delivery-date-by-bigloop' );

		if ( null !== $category ) {
			$term = get_term( $category['term_id'], 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				/* translators: %s: category name. */
				$cat_name = sprintf( __( 'Category: %s', 'ultimate-estimated-delivery-date-by-bigloop' ), $term->name );
			}
		}

		if ( null !== $own ) {
			$active = 'product';
		} elseif ( null !== $category ) {
			$active = 'category';
		} else {
			$active = 'default';
		}

		$levels = array(
			'default'  => __( 'Default', 'ultimate-estimated-delivery-date-by-bigloop' ),
			'category' => $cat_name,
			'product'  => __( 'This product', 'ultimate-estimated-delivery-date-by-bigloop' ),
		);
		?>
		<div class="dleta-box">
			<ul class="dleta-levels" aria-label="<?php esc_attr_e( 'Which setting applies', 'ultimate-estimated-delivery-date-by-bigloop' ); ?>">
				<?php
				$first = true;
				foreach ( $levels as $key => $label ) {
					if ( ! $first ) {
						echo '<li class="dleta-levels__sep" aria-hidden="true">&rarr;</li>';
					}
					$first = false;
					printf(
						'<li class="%1$s"%2$s>%3$s</li>',
						$key === $active ? 'is-active' : '',
						$key === $active ? ' aria-current="true"' : '',
						esc_html( $label )
					);
				}
				?>
			</ul>
			<p><?php esc_html_e( 'A setting here beats the category and the default for this product. For variable products, each variation can also override it under Variations.', 'ultimate-estimated-delivery-date-by-bigloop' ); ?></p>
			<p class="dleta-box__current"><?php echo esc_html( self::inherited_summary( $product_id ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Output the panel.
	 */
	public static function panel() {
		global $post;

		$product_id = $post ? (int) $post->ID : 0;
		$mode       = (string) get_post_meta( $product_id, Dleta_Rules::META_MODE, true );
		$days_attrs = array(
			'min'  => '0',
			'max'  => (string) Dleta_Rules::MAX_DAYS,
			'step' => '1',
		);
		?>
		<div id="dleta_product_data" class="panel woocommerce_options_panel hidden">
			<div class="options_group">
				<?php self::levels_box( $product_id ); ?>
				<?php
				wp_nonce_field( self::NONCE, self::NONCE );

				woocommerce_wp_select(
					array(
						'id'      => 'dleta_mode',
						'label'   => __( 'Estimated delivery', 'ultimate-estimated-delivery-date-by-bigloop' ),
						'class'   => 'select short dleta-mode-select',
						'value'   => $mode,
						'options' => array(
							''      => __( 'Use category / global setting', 'ultimate-estimated-delivery-date-by-bigloop' ),
							'range' => __( 'Range (minimum and maximum days)', 'ultimate-estimated-delivery-date-by-bigloop' ),
							'fixed' => __( 'Fixed number of days', 'ultimate-estimated-delivery-date-by-bigloop' ),
							'none'  => __( 'Do not show an estimate', 'ultimate-estimated-delivery-date-by-bigloop' ),
						),
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'                => 'dleta_min_days',
						'label'             => __( 'Minimum days', 'ultimate-estimated-delivery-date-by-bigloop' ),
						'type'              => 'number',
						'value'             => get_post_meta( $product_id, Dleta_Rules::META_MIN, true ),
						'wrapper_class'     => 'dleta-show-range',
						'custom_attributes' => $days_attrs,
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'                => 'dleta_max_days',
						'label'             => __( 'Maximum days', 'ultimate-estimated-delivery-date-by-bigloop' ),
						'type'              => 'number',
						'value'             => get_post_meta( $product_id, Dleta_Rules::META_MAX, true ),
						'wrapper_class'     => 'dleta-show-range',
						'custom_attributes' => $days_attrs,
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'                => 'dleta_fixed_days',
						'label'             => __( 'Fixed days', 'ultimate-estimated-delivery-date-by-bigloop' ),
						'type'              => 'number',
						'value'             => get_post_meta( $product_id, Dleta_Rules::META_FIXED, true ),
						'wrapper_class'     => 'dleta-show-fixed',
						'custom_attributes' => $days_attrs,
					)
				);

				woocommerce_wp_checkbox(
					array(
						'id'            => 'dleta_exclude_weekends',
						'label'         => __( 'Weekends', 'ultimate-estimated-delivery-date-by-bigloop' ),
						'description'   => __( 'Exclude Saturdays and Sundays when counting days', 'ultimate-estimated-delivery-date-by-bigloop' ),
						'value'         => get_post_meta( $product_id, Dleta_Rules::META_WEEKEND, true ),
						'cbvalue'       => 'yes',
						'wrapper_class' => 'dleta-show-rule',
					)
				);
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Save product meta.
	 *
	 * @param WC_Product $product Product being saved.
	 */
	public static function save( $product ) {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_product', $product->get_id() ) ) {
			return;
		}

		$rule = Dleta_Rules::from_request(
			array(
				'dleta_mode'             => isset( $_POST['dleta_mode'] ) ? sanitize_key( wp_unslash( $_POST['dleta_mode'] ) ) : '',
				'dleta_min_days'         => isset( $_POST['dleta_min_days'] ) ? absint( wp_unslash( $_POST['dleta_min_days'] ) ) : 0,
				'dleta_max_days'         => isset( $_POST['dleta_max_days'] ) ? absint( wp_unslash( $_POST['dleta_max_days'] ) ) : 0,
				'dleta_fixed_days'       => isset( $_POST['dleta_fixed_days'] ) ? absint( wp_unslash( $_POST['dleta_fixed_days'] ) ) : 0,
				'dleta_exclude_weekends' => ! empty( $_POST['dleta_exclude_weekends'] ),
			)
		);

		$keys = array( Dleta_Rules::META_MODE, Dleta_Rules::META_MIN, Dleta_Rules::META_MAX, Dleta_Rules::META_FIXED, Dleta_Rules::META_WEEKEND );

		if ( '' === $rule['mode'] ) {
			foreach ( $keys as $key ) {
				$product->delete_meta_data( $key );
			}
			return;
		}

		$rule = Dleta_Rules::normalize( $rule );

		$product->update_meta_data( Dleta_Rules::META_MODE, $rule['mode'] );
		$product->update_meta_data( Dleta_Rules::META_MIN, $rule['min'] );
		$product->update_meta_data( Dleta_Rules::META_MAX, $rule['max'] );
		$product->update_meta_data( Dleta_Rules::META_FIXED, $rule['fixed'] );
		$product->update_meta_data( Dleta_Rules::META_WEEKEND, $rule['exclude_weekends'] ? 'yes' : 'no' );
	}
}
