<?php
/**
 * Estimated delivery fields on Products > Categories.
 *
 * @package BigLoopPDE
 */

defined( 'ABSPATH' ) || exit;

/**
 * Category fields.
 */
class Dleta_Category_Fields {

	/**
	 * Nonce action and field name.
	 */
	const NONCE = 'dleta_category_nonce';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'product_cat_add_form_fields', array( __CLASS__, 'add_fields' ), 20 );
		add_action( 'product_cat_edit_form_fields', array( __CLASS__, 'edit_fields' ), 20 );
		add_action( 'created_product_cat', array( __CLASS__, 'save' ) );
		add_action( 'edited_product_cat', array( __CLASS__, 'save' ) );
		add_filter( 'manage_edit-product_cat_columns', array( __CLASS__, 'add_column' ) );
		add_filter( 'manage_product_cat_custom_column', array( __CLASS__, 'column_content' ), 10, 3 );
	}

	/**
	 * Mode choices for categories.
	 *
	 * @return array<string,string>
	 */
	private static function mode_options() {
		return array(
			''      => __( 'Use global setting', 'bigloop-product-delivery-estimate' ),
			'range' => __( 'Range (minimum and maximum days)', 'bigloop-product-delivery-estimate' ),
			'fixed' => __( 'Fixed number of days', 'bigloop-product-delivery-estimate' ),
			'none'  => __( 'Do not show an estimate', 'bigloop-product-delivery-estimate' ),
		);
	}

	/**
	 * Explanation shown with the fields.
	 *
	 * @return string Escaped HTML.
	 */
	private static function help_text() {
		$levels = '<ul class="dleta-levels">'
			. '<li>' . esc_html__( 'Default', 'bigloop-product-delivery-estimate' ) . '</li>'
			. '<li class="dleta-levels__sep" aria-hidden="true">&rarr;</li>'
			. '<li class="is-active">' . esc_html__( 'This category', 'bigloop-product-delivery-estimate' ) . '</li>'
			. '<li class="dleta-levels__sep" aria-hidden="true">&rarr;</li>'
			. '<li>' . esc_html__( 'Product', 'bigloop-product-delivery-estimate' ) . '</li>'
			. '</ul>';

		$text = sprintf(
			/* translators: 1: default rule summary, 2: settings URL. */
			wp_kses_post( __( 'Products in this category and its subcategories use this instead of the <a href="%2$s">default estimate</a> (currently %1$s). A product\'s own setting still wins.', 'bigloop-product-delivery-estimate' ) ),
			esc_html( Dleta_Rules::describe( Dleta_Rules::global_rule() ) ),
			esc_url( Dleta_Admin::settings_url() )
		);

		return '<div class="dleta-box">' . $levels . '<p>' . $text . '</p></div>';
	}

	/**
	 * Fields on the "Add new category" form.
	 */
	public static function add_fields() {
		wp_nonce_field( self::NONCE, self::NONCE );
		?>
		<div class="form-field dleta-term-fields">
			<label for="dleta_mode"><?php esc_html_e( 'Estimated delivery', 'bigloop-product-delivery-estimate' ); ?></label>
			<?php self::mode_select( '' ); ?>
			<?php echo self::help_text(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in help_text(). ?>
		</div>
		<div class="form-field dleta-show-range">
			<label for="dleta_min_days"><?php esc_html_e( 'Minimum days', 'bigloop-product-delivery-estimate' ); ?></label>
			<?php self::number_input( 'dleta_min_days', '' ); ?>
		</div>
		<div class="form-field dleta-show-range">
			<label for="dleta_max_days"><?php esc_html_e( 'Maximum days', 'bigloop-product-delivery-estimate' ); ?></label>
			<?php self::number_input( 'dleta_max_days', '' ); ?>
		</div>
		<div class="form-field dleta-show-fixed">
			<label for="dleta_fixed_days"><?php esc_html_e( 'Fixed days', 'bigloop-product-delivery-estimate' ); ?></label>
			<?php self::number_input( 'dleta_fixed_days', '' ); ?>
		</div>
		<div class="form-field dleta-show-rule">
			<label for="dleta_exclude_weekends">
				<input type="checkbox" id="dleta_exclude_weekends" name="dleta_exclude_weekends" value="yes" />
				<?php esc_html_e( 'Exclude Saturdays and Sundays when counting days', 'bigloop-product-delivery-estimate' ); ?>
			</label>
		</div>
		<?php
	}

	/**
	 * Fields on the "Edit category" form.
	 *
	 * @param WP_Term $term Term being edited.
	 */
	public static function edit_fields( $term ) {
		$mode    = (string) get_term_meta( $term->term_id, Dleta_Rules::META_MODE, true );
		$min     = get_term_meta( $term->term_id, Dleta_Rules::META_MIN, true );
		$max     = get_term_meta( $term->term_id, Dleta_Rules::META_MAX, true );
		$fixed   = get_term_meta( $term->term_id, Dleta_Rules::META_FIXED, true );
		$weekend = 'yes' === get_term_meta( $term->term_id, Dleta_Rules::META_WEEKEND, true );
		?>
		<tr class="form-field dleta-term-fields">
			<th scope="row"><label for="dleta_mode"><?php esc_html_e( 'Estimated delivery', 'bigloop-product-delivery-estimate' ); ?></label></th>
			<td>
				<?php wp_nonce_field( self::NONCE, self::NONCE ); ?>
				<?php self::mode_select( $mode ); ?>
				<?php echo self::help_text(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in help_text(). ?>
			</td>
		</tr>
		<tr class="form-field dleta-show-range">
			<th scope="row"><label for="dleta_min_days"><?php esc_html_e( 'Minimum days', 'bigloop-product-delivery-estimate' ); ?></label></th>
			<td><?php self::number_input( 'dleta_min_days', $min ); ?></td>
		</tr>
		<tr class="form-field dleta-show-range">
			<th scope="row"><label for="dleta_max_days"><?php esc_html_e( 'Maximum days', 'bigloop-product-delivery-estimate' ); ?></label></th>
			<td><?php self::number_input( 'dleta_max_days', $max ); ?></td>
		</tr>
		<tr class="form-field dleta-show-fixed">
			<th scope="row"><label for="dleta_fixed_days"><?php esc_html_e( 'Fixed days', 'bigloop-product-delivery-estimate' ); ?></label></th>
			<td><?php self::number_input( 'dleta_fixed_days', $fixed ); ?></td>
		</tr>
		<tr class="form-field dleta-show-rule">
			<th scope="row"><?php esc_html_e( 'Weekends', 'bigloop-product-delivery-estimate' ); ?></th>
			<td>
				<label for="dleta_exclude_weekends">
					<input type="checkbox" id="dleta_exclude_weekends" name="dleta_exclude_weekends" value="yes" <?php checked( $weekend ); ?> />
					<?php esc_html_e( 'Exclude Saturdays and Sundays when counting days', 'bigloop-product-delivery-estimate' ); ?>
				</label>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render the mode select.
	 *
	 * @param string $current Current value.
	 */
	private static function mode_select( $current ) {
		echo '<select id="dleta_mode" name="dleta_mode" class="dleta-mode-select">';
		foreach ( self::mode_options() as $value => $label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $value ),
				selected( $current, $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}

	/**
	 * Render a day count input.
	 *
	 * @param string $name  Field name.
	 * @param mixed  $value Current value.
	 */
	private static function number_input( $name, $value ) {
		printf(
			'<input type="number" id="%1$s" name="%1$s" value="%2$s" min="0" max="%3$d" step="1" style="width:80px;" />',
			esc_attr( $name ),
			esc_attr( '' === $value ? '' : (string) absint( $value ) ),
			absint( Dleta_Rules::MAX_DAYS )
		);
	}

	/**
	 * Save term meta.
	 *
	 * @param int $term_id Term ID.
	 */
	public static function save( $term_id ) {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_product_terms' ) ) {
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

		self::store( $term_id, $rule );
	}

	/**
	 * Write a sanitized rule to term meta.
	 *
	 * @param int   $term_id Term ID.
	 * @param array $rule    Rule from Dleta_Rules::from_request().
	 */
	private static function store( $term_id, array $rule ) {
		if ( '' === $rule['mode'] ) {
			foreach ( array( Dleta_Rules::META_MODE, Dleta_Rules::META_MIN, Dleta_Rules::META_MAX, Dleta_Rules::META_FIXED, Dleta_Rules::META_WEEKEND ) as $key ) {
				delete_term_meta( $term_id, $key );
			}
			return;
		}

		$rule = Dleta_Rules::normalize( $rule );

		update_term_meta( $term_id, Dleta_Rules::META_MODE, $rule['mode'] );
		update_term_meta( $term_id, Dleta_Rules::META_MIN, $rule['min'] );
		update_term_meta( $term_id, Dleta_Rules::META_MAX, $rule['max'] );
		update_term_meta( $term_id, Dleta_Rules::META_FIXED, $rule['fixed'] );
		update_term_meta( $term_id, Dleta_Rules::META_WEEKEND, $rule['exclude_weekends'] ? 'yes' : 'no' );
	}

	/**
	 * Add a column to the category list.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function add_column( $columns ) {
		$columns['dleta'] = __( 'Estimated Delivery', 'bigloop-product-delivery-estimate' );
		return $columns;
	}

	/**
	 * Column content.
	 *
	 * @param string $content Existing content.
	 * @param string $column  Column name.
	 * @param int    $term_id Term ID.
	 * @return string
	 */
	public static function column_content( $content, $column, $term_id ) {
		if ( 'dleta' !== $column ) {
			return $content;
		}

		$rule = Dleta_Rules::term_rule( $term_id );
		if ( null === $rule ) {
			return '<span class="dleta-muted">' . esc_html__( 'Global', 'bigloop-product-delivery-estimate' ) . '</span>';
		}

		return esc_html( Dleta_Rules::describe( $rule ) );
	}
}
