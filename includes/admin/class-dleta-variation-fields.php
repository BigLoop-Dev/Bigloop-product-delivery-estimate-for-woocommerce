<?php
/**
 * Estimated delivery fields on each variation (variable products and
 * variable subscriptions). A variation's setting overrides the product,
 * category and default settings.
 *
 * @package BigLoopPDE
 */

defined( 'ABSPATH' ) || exit;

/**
 * Variation fields.
 */
class Dleta_Variation_Fields {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'woocommerce_product_after_variable_attributes', array( __CLASS__, 'fields' ), 20, 3 );
		add_action( 'woocommerce_save_product_variation', array( __CLASS__, 'save' ), 20, 2 );
	}

	/**
	 * Output the fields inside a variation.
	 *
	 * @param int     $loop           Variation index.
	 * @param array   $variation_data Variation data (unused).
	 * @param WP_Post $variation      Variation post.
	 */
	public static function fields( $loop, $variation_data, $variation ) {
		unset( $variation_data );

		$id      = (int) $variation->ID;
		$mode    = (string) get_post_meta( $id, Dleta_Rules::META_MODE, true );
		$min     = get_post_meta( $id, Dleta_Rules::META_MIN, true );
		$max     = get_post_meta( $id, Dleta_Rules::META_MAX, true );
		$fixed   = get_post_meta( $id, Dleta_Rules::META_FIXED, true );
		$weekend = 'yes' === get_post_meta( $id, Dleta_Rules::META_WEEKEND, true );
		$loop    = absint( $loop );
		$modes   = array(
			''      => __( 'Use product setting', 'bigloop-product-delivery-estimate' ),
			'range' => __( 'Date range (earliest and latest)', 'bigloop-product-delivery-estimate' ),
			'fixed' => __( 'Single date', 'bigloop-product-delivery-estimate' ),
			'none'  => __( 'Do not show an estimate', 'bigloop-product-delivery-estimate' ),
		);
		$days    = function ( $value ) {
			return '' === $value ? '' : (string) absint( $value );
		};
		?>
		<div class="dleta-variation" data-dleta-variation>
			<p class="form-row form-row-full dleta-variation__head">
				<label for="dleta_var_mode_<?php echo esc_attr( (string) $loop ); ?>">
					<span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
					<?php esc_html_e( 'Estimated delivery', 'bigloop-product-delivery-estimate' ); ?>
					<?php echo wc_help_tip( __( 'Overrides the product, category and default estimates for this variation only.', 'bigloop-product-delivery-estimate' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_help_tip() escapes. ?>
				</label>
				<select id="dleta_var_mode_<?php echo esc_attr( (string) $loop ); ?>" name="dleta_var_mode[<?php echo esc_attr( (string) $loop ); ?>]" class="dleta-var-mode">
					<?php foreach ( $modes as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $mode, $value ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p class="form-row form-row-first dleta-var-range">
				<label for="dleta_var_min_<?php echo esc_attr( (string) $loop ); ?>"><?php esc_html_e( 'Earliest delivery (days)', 'bigloop-product-delivery-estimate' ); ?></label>
				<input type="number" min="0" max="<?php echo esc_attr( (string) Dleta_Rules::MAX_DAYS ); ?>" step="1" id="dleta_var_min_<?php echo esc_attr( (string) $loop ); ?>" name="dleta_var_min[<?php echo esc_attr( (string) $loop ); ?>]" value="<?php echo esc_attr( $days( $min ) ); ?>" />
			</p>
			<p class="form-row form-row-last dleta-var-range">
				<label for="dleta_var_max_<?php echo esc_attr( (string) $loop ); ?>"><?php esc_html_e( 'Latest delivery (days)', 'bigloop-product-delivery-estimate' ); ?></label>
				<input type="number" min="0" max="<?php echo esc_attr( (string) Dleta_Rules::MAX_DAYS ); ?>" step="1" id="dleta_var_max_<?php echo esc_attr( (string) $loop ); ?>" name="dleta_var_max[<?php echo esc_attr( (string) $loop ); ?>]" value="<?php echo esc_attr( $days( $max ) ); ?>" />
			</p>
			<p class="form-row form-row-first dleta-var-fixed">
				<label for="dleta_var_fixed_<?php echo esc_attr( (string) $loop ); ?>"><?php esc_html_e( 'Delivery in (days)', 'bigloop-product-delivery-estimate' ); ?></label>
				<input type="number" min="0" max="<?php echo esc_attr( (string) Dleta_Rules::MAX_DAYS ); ?>" step="1" id="dleta_var_fixed_<?php echo esc_attr( (string) $loop ); ?>" name="dleta_var_fixed[<?php echo esc_attr( (string) $loop ); ?>]" value="<?php echo esc_attr( $days( $fixed ) ); ?>" />
			</p>
			<p class="form-row form-row-full dleta-var-rule">
				<label>
					<input type="checkbox" name="dleta_var_weekends[<?php echo esc_attr( (string) $loop ); ?>]" value="yes" <?php checked( $weekend ); ?> />
					<?php esc_html_e( 'Don\'t count Saturdays and Sundays', 'bigloop-product-delivery-estimate' ); ?>
				</label>
			</p>
		</div>
		<?php
	}

	/**
	 * Save a variation's fields.
	 *
	 * WooCommerce verifies its own nonce before this runs, both when saving
	 * variations over AJAX (save-variations) and on the product form
	 * (woocommerce_meta_nonce).
	 *
	 * @param int $variation_id Variation ID.
	 * @param int $i            Index in the submitted arrays.
	 */
	public static function save( $variation_id, $i ) {
		$variation_id = absint( $variation_id );
		$parent_id    = (int) wp_get_post_parent_id( $variation_id );

		if ( ! current_user_can( 'edit_product', $parent_id ? $parent_id : $variation_id ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified by WooCommerce before woocommerce_save_product_variation fires.
		if ( ! isset( $_POST['dleta_var_mode'] ) || ! is_array( $_POST['dleta_var_mode'] ) ) {
			return;
		}

		$value = function ( $key ) use ( $i ) {
			return isset( $_POST[ $key ][ $i ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ][ $i ] ) ) : '';
		};

		$rule = Dleta_Rules::from_request(
			array(
				'dleta_mode'             => sanitize_key( $value( 'dleta_var_mode' ) ),
				'dleta_min_days'         => absint( $value( 'dleta_var_min' ) ),
				'dleta_max_days'         => absint( $value( 'dleta_var_max' ) ),
				'dleta_fixed_days'       => absint( $value( 'dleta_var_fixed' ) ),
				'dleta_exclude_weekends' => 'yes' === $value( 'dleta_var_weekends' ),
			)
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$keys = array( Dleta_Rules::META_MODE, Dleta_Rules::META_MIN, Dleta_Rules::META_MAX, Dleta_Rules::META_FIXED, Dleta_Rules::META_WEEKEND );

		if ( '' === $rule['mode'] ) {
			foreach ( $keys as $key ) {
				delete_post_meta( $variation_id, $key );
			}
			return;
		}

		$rule = Dleta_Rules::normalize( $rule );

		update_post_meta( $variation_id, Dleta_Rules::META_MODE, $rule['mode'] );
		update_post_meta( $variation_id, Dleta_Rules::META_MIN, $rule['min'] );
		update_post_meta( $variation_id, Dleta_Rules::META_MAX, $rule['max'] );
		update_post_meta( $variation_id, Dleta_Rules::META_FIXED, $rule['fixed'] );
		update_post_meta( $variation_id, Dleta_Rules::META_WEEKEND, $rule['exclude_weekends'] ? 'yes' : 'no' );
	}
}
