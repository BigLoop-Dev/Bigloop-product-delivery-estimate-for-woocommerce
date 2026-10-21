<?php
/**
 * Layout pieces for the settings tab: header, section navigation, cards,
 * wireframes, previews and pickers. Each is a custom WooCommerce settings
 * field type ("dleta_*") so it can sit between normal fields.
 *
 * @package UltimateEDD
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings UI renderers.
 */
class Dleta_Admin_UI {

	/**
	 * Register custom field types.
	 */
	public static function init() {
		$types = array( 'card_start', 'card_end', 'subhead', 'info', 'hierarchy', 'icon_picker', 'media', 'shortcodes', 'date_format' );
		foreach ( $types as $type ) {
			add_action( 'woocommerce_admin_field_dleta_' . $type, array( __CLASS__, $type ) );
		}
	}

	/**
	 * Dashicon class for a card icon key.
	 *
	 * @param string $key Icon key.
	 * @return string
	 */
	private static function dashicon( $key ) {
		$map = array(
			'clock'    => 'dashicons-clock',
			'box'      => 'dashicons-products',
			'cart'     => 'dashicons-cart',
			'card'     => 'dashicons-money-alt',
			'calendar' => 'dashicons-calendar-alt',
			'pin'      => 'dashicons-clipboard',
			'brush'    => 'dashicons-art',
			'code'     => 'dashicons-shortcode',
			'database' => 'dashicons-database',
			'shield'   => 'dashicons-shield',
		);
		return isset( $map[ $key ] ) ? $map[ $key ] : 'dashicons-admin-generic';
	}

	/**
	 * Sample estimate text from the saved global settings.
	 *
	 * @return string Plain text.
	 */
	private static function sample_text() {
		$dates = Dleta_Calculator::calculate( Dleta_Rules::global_rule() );
		return null === $dates ? '' : Dleta_Calculator::format( $dates );
	}

	/**
	 * Icon markup for previews, matching the saved icon setting.
	 *
	 * @return string Escaped HTML.
	 */
	private static function sample_icon() {
		$icon = Dleta_Rules::option( 'dleta_icon' );

		if ( 'none' === $icon ) {
			return '';
		}

		if ( 'custom' === $icon ) {
			$attachment_id = absint( Dleta_Rules::option( 'dleta_icon_custom' ) );
			$src           = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'thumbnail' ) : '';
			return $src ? '<img class="dleta-estimate__icon dleta-estimate__icon--image" src="' . esc_url( $src ) . '" alt="" />' : '';
		}

		return '<span class="dleta-estimate__icon dleta-icon--' . esc_attr( $icon ) . '" aria-hidden="true"></span>';
	}

	/**
	 * A small estimate "pill" used inside the wireframes.
	 *
	 * @param string $label Label text.
	 * @param string $extra Extra CSS classes.
	 * @param string $attrs Extra escaped HTML attributes.
	 * @return string Escaped HTML.
	 */
	private static function pill( $label, $extra = '', $attrs = '' ) {
		return sprintf(
			'<span class="dleta-wf__eta %1$s"%2$s>%3$s<span><span class="dleta-wf__eta-label">%4$s</span> <strong>%5$s</strong></span></span>',
			esc_attr( $extra ),
			$attrs,
			self::sample_icon(),
			esc_html( $label ),
			esc_html( self::sample_text() )
		);
	}

	/**
	 * Header and section navigation (printed above the fields).
	 */
	public static function header_and_nav() {
		$current = Dleta_Admin::current_section();
		?>
		<div class="dleta-header">
			<div class="dleta-header__brand">
				<span class="dleta-header__logo" aria-hidden="true"><span class="dleta-estimate__icon dleta-icon--fast"></span></span>
				<div>
					<h2 class="dleta-header__title">
						<?php esc_html_e( 'Ultimate Estimated Delivery Date', 'ultimate-estimated-delivery-date-by-bigloop' ); ?>
						<span class="dleta-header__version"><?php echo esc_html( DLETA_VERSION ); ?></span>
					</h2>
					<p class="dleta-header__tagline"><?php esc_html_e( 'Show customers when their order will arrive.', 'ultimate-estimated-delivery-date-by-bigloop' ); ?></p>
				</div>
			</div>
			<div class="dleta-header__meta">
				<span class="dleta-badge"><?php esc_html_e( '100% free', 'ultimate-estimated-delivery-date-by-bigloop' ); ?></span>
				<span class="dleta-badge"><?php esc_html_e( 'No external connections', 'ultimate-estimated-delivery-date-by-bigloop' ); ?></span>
				<a class="dleta-header__by" href="<?php echo esc_url( 'https://bigloop.dev/' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'by BigLoop', 'ultimate-estimated-delivery-date-by-bigloop' ); ?></a>
			</div>
		</div>
		<nav class="dleta-nav" aria-label="<?php esc_attr_e( 'Estimated delivery settings sections', 'ultimate-estimated-delivery-date-by-bigloop' ); ?>">
			<?php
			$step = 0;
			foreach ( Dleta_Admin::sections() as $id => $label ) {
				++$step;
				printf(
					'<a class="dleta-nav__item%1$s" href="%2$s"%3$s><span class="dleta-nav__num">%4$d</span>%5$s</a>',
					$id === $current ? ' is-active' : '',
					esc_url( Dleta_Admin::settings_url( $id ) ),
					$id === $current ? ' aria-current="page"' : '',
					absint( $step ),
					esc_html( $label )
				);
			}
			?>
		</nav>
		<?php
	}

	/**
	 * Open a card.
	 *
	 * @param array $field Field settings.
	 */
	public static function card_start( $field ) {
		$classes = array( 'dleta-card' );
		if ( ! empty( $field['split'] ) ) {
			$classes[] = 'dleta-card--split';
		}
		if ( ! empty( $field['class'] ) ) {
			$classes[] = $field['class'];
		}
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
			<div class="dleta-card__head">
				<span class="dleta-card__icon dashicons <?php echo esc_attr( self::dashicon( isset( $field['icon'] ) ? $field['icon'] : '' ) ); ?>" aria-hidden="true"></span>
				<div>
					<h2 class="dleta-card__title"><?php echo esc_html( $field['title'] ); ?></h2>
					<?php if ( ! empty( $field['desc'] ) ) : ?>
						<p class="dleta-card__desc"><?php echo esc_html( $field['desc'] ); ?></p>
					<?php endif; ?>
				</div>
			</div>
			<div class="dleta-card__body">
				<div class="dleta-card__main">
					<table class="form-table" role="presentation"><tbody>
		<?php
	}

	/**
	 * Close a card, printing its side panel if it has one.
	 *
	 * @param array $field Field settings.
	 */
	public static function card_end( $field ) {
		?>
					</tbody></table>
				</div>
				<?php
				$aside = isset( $field['aside'] ) ? (string) $field['aside'] : '';
				if ( '' !== $aside && method_exists( __CLASS__, 'aside_' . $aside ) ) {
					echo '<aside class="dleta-card__aside">';
					call_user_func( array( __CLASS__, 'aside_' . $aside ) );
					echo '</aside>';
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Small heading inside a card.
	 *
	 * @param array $field Field settings.
	 */
	public static function subhead( $field ) {
		?>
		</tbody></table>
		<h3 class="dleta-subhead"><?php echo esc_html( $field['title'] ); ?></h3>
		<table class="form-table" role="presentation"><tbody>
		<?php
	}

	/**
	 * Info box row.
	 *
	 * @param array $field Field settings; 'html' must already be escaped.
	 */
	public static function info( $field ) {
		?>
		<tr class="dleta-row-full">
			<td colspan="2">
				<div class="dleta-info">
					<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
					<div><?php echo wp_kses_post( isset( $field['html'] ) ? $field['html'] : '' ); ?></div>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * "How the estimate is chosen" box at the top of the Delivery time section.
	 */
	public static function hierarchy() {
		$categories = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'fields'     => 'count',
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin settings screen only, one count query.
					array(
						'key'     => Dleta_Rules::META_MODE,
						'compare' => 'EXISTS',
					),
				),
			)
		);
		$categories = is_wp_error( $categories ) ? 0 : absint( $categories );

		$products = new WP_Query(
			array(
				'post_type'              => 'product',
				'post_status'            => 'any',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'meta_key'               => Dleta_Rules::META_MODE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Admin settings screen only, one count query.
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		$products = absint( $products->found_posts );

		$variations = new WP_Query(
			array(
				'post_type'              => 'product_variation',
				'post_status'            => 'any',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'meta_key'               => Dleta_Rules::META_MODE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Admin settings screen only, one count query.
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		$variations = absint( $variations->found_posts );

		$steps = array(
			array(
				'title' => __( 'Variation', 'ultimate-estimated-delivery-date-by-bigloop' ),
				'where' => __( 'Edit product → Variations → a variation', 'ultimate-estimated-delivery-date-by-bigloop' ),
				/* translators: %d: number of variations. */
				'count' => sprintf( _n( '%d variation has its own', '%d variations have their own', $variations, 'ultimate-estimated-delivery-date-by-bigloop' ), $variations ),
				'link'  => '',
				'class' => '',
			),
			array(
				'title' => __( 'Product', 'ultimate-estimated-delivery-date-by-bigloop' ),
				'where' => __( 'Edit product → Product data → Estimated Delivery', 'ultimate-estimated-delivery-date-by-bigloop' ),
				/* translators: %d: number of products. */
				'count' => sprintf( _n( '%d product has its own', '%d products have their own', $products, 'ultimate-estimated-delivery-date-by-bigloop' ), $products ),
				'link'  => admin_url( 'edit.php?post_type=product' ),
				'class' => '',
			),
			array(
				'title' => __( 'Category', 'ultimate-estimated-delivery-date-by-bigloop' ),
				'where' => __( 'Products → Categories → edit a category', 'ultimate-estimated-delivery-date-by-bigloop' ),
				/* translators: %d: number of categories. */
				'count' => sprintf( _n( '%d category has its own', '%d categories have their own', $categories, 'ultimate-estimated-delivery-date-by-bigloop' ), $categories ),
				'link'  => admin_url( 'edit-tags.php?taxonomy=product_cat&post_type=product' ),
				'class' => '',
			),
			array(
				'title' => __( 'Default', 'ultimate-estimated-delivery-date-by-bigloop' ),
				'where' => __( 'This page', 'ultimate-estimated-delivery-date-by-bigloop' ),
				'count' => __( 'Used for everything else', 'ultimate-estimated-delivery-date-by-bigloop' ),
				'link'  => '',
				'class' => ' is-current',
			),
		);
		?>
		<div class="dleta-hierarchy">
			<div class="dleta-hierarchy__intro">
				<h2><?php esc_html_e( 'How the estimate is chosen', 'ultimate-estimated-delivery-date-by-bigloop' ); ?></h2>
				<p><?php esc_html_e( 'The most specific setting wins: a variation beats its product, a product beats its category, and a category beats the default.', 'ultimate-estimated-delivery-date-by-bigloop' ); ?></p>
			</div>
			<ol class="dleta-hierarchy__steps">
				<?php foreach ( $steps as $index => $step ) : ?>
					<li class="dleta-hierarchy__step<?php echo esc_attr( $step['class'] ); ?>">
						<span class="dleta-hierarchy__rank"><?php echo absint( $index + 1 ); ?></span>
						<strong class="dleta-hierarchy__title"><?php echo esc_html( $step['title'] ); ?></strong>
						<span class="dleta-hierarchy__where"><?php echo esc_html( $step['where'] ); ?></span>
						<?php if ( $step['link'] ) : ?>
							<a class="dleta-hierarchy__count" href="<?php echo esc_url( $step['link'] ); ?>"><?php echo esc_html( $step['count'] ); ?></a>
						<?php else : ?>
							<span class="dleta-hierarchy__count"><?php echo esc_html( $step['count'] ); ?></span>
						<?php endif; ?>
					</li>
					<?php if ( $index < count( $steps ) - 1 ) : ?>
						<li class="dleta-hierarchy__arrow" aria-hidden="true"><span><?php esc_html_e( 'overrides', 'ultimate-estimated-delivery-date-by-bigloop' ); ?></span></li>
					<?php endif; ?>
				<?php endforeach; ?>
			</ol>
		</div>
		<?php
	}

	/**
	 * Formatted dates and skip flags for the next days, so the live example
	 * in the Delivery time section uses the same format, start date and
	 * holiday filters as the store.
	 *
	 * @return array
	 */
	public static function calendar_data() {
		/** This filter is documented in includes/class-dleta-calculator.php */
		$start = apply_filters( 'dleta_start_date', current_datetime()->setTime( 0, 0 ), Dleta_Rules::global_rule() );
		$days  = array();
		$count = (int) ceil( Dleta_Rules::MAX_DAYS * 7 / 5 ) + 30;

		$format = Dleta_Rules::option( 'dleta_date_format' );
		if ( '' === trim( $format ) ) {
			$format = get_option( 'date_format' );
		}

		$date = $start;
		for ( $i = 0; $i < $count; $i++ ) {
			$days[] = array(
				'f'  => wp_date( $format, $date->getTimestamp(), $date->getTimezone() ),
				'd'  => (int) $date->format( 'j' ),
				'w'  => (int) $date->format( 'N' ),
				'sw' => Dleta_Calculator::is_skipped_day( $date, true ),
				'sn' => Dleta_Calculator::is_skipped_day( $date, false ),
			);
			$date   = $date->modify( '+1 day' );
		}

		return array(
			'days'     => $days,
			/* translators: 1: earliest delivery date, 2: latest delivery date. */
			'range'    => __( '%1$s – %2$s', 'ultimate-estimated-delivery-date-by-bigloop' ),
			'weekdays' => array(
				__( 'Mon', 'ultimate-estimated-delivery-date-by-bigloop' ),
				__( 'Tue', 'ultimate-estimated-delivery-date-by-bigloop' ),
				__( 'Wed', 'ultimate-estimated-delivery-date-by-bigloop' ),
				__( 'Thu', 'ultimate-estimated-delivery-date-by-bigloop' ),
				__( 'Fri', 'ultimate-estimated-delivery-date-by-bigloop' ),
				__( 'Sat', 'ultimate-estimated-delivery-date-by-bigloop' ),
				__( 'Sun', 'ultimate-estimated-delivery-date-by-bigloop' ),
			),
			'beyond'   => __( 'The delivery window is further out than the calendar shows.', 'ultimate-estimated-delivery-date-by-bigloop' ),
		);
	}

	/**
	 * Side panel: live example with a mini calendar.
	 */
	public static function aside_example() {
		?>
		<div class="dleta-panel dleta-example">
			<div class="dleta-panel__label"><?php esc_html_e( 'If a customer orders today', 'ultimate-estimated-delivery-date-by-bigloop' ); ?></div>
			<div class="dleta-example__result">
				<?php echo self::sample_icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in sample_icon(). ?>
				<strong data-dleta-example><?php echo esc_html( self::sample_text() ); ?></strong>
			</div>
			<div class="dleta-cal" data-dleta-calendar aria-hidden="true"></div>
			<ul class="dleta-cal__legend" aria-hidden="true">
				<li><span class="dleta-cal__key is-today"></span><?php esc_html_e( 'Today', 'ultimate-estimated-delivery-date-by-bigloop' ); ?></li>
				<li><span class="dleta-cal__key is-window"></span><?php esc_html_e( 'Delivery', 'ultimate-estimated-delivery-date-by-bigloop' ); ?></li>
				<li><span class="dleta-cal__key is-skipped"></span><?php esc_html_e( 'Not counted', 'ultimate-estimated-delivery-date-by-bigloop' ); ?></li>
			</ul>
			<p class="dleta-panel__note" data-dleta-beyond hidden></p>
		</div>
		<?php
	}

	/**
	 * One clickable position in the product page wireframe.
	 *
	 * @param string $position Position key.
	 * @param array  $labels   Position labels.
	 */
	private static function wf_slot( $position, $labels ) {
		printf(
			'<button type="button" class="dleta-wf__slot" data-position="%1$s" title="%2$s"><span class="dleta-wf__slot-hint">%2$s</span>%3$s</button>',
			esc_attr( $position ),
			esc_attr( isset( $labels[ $position ] ) ? $labels[ $position ] : $position ),
			self::pill( Dleta_Rules::option( 'dleta_label' ) ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in pill().
		);
	}

	/**
	 * Side panel: product page wireframe.
	 */
	public static function aside_wireframe_product() {
		$labels = Dleta_Frontend::positions_labels();
		?>
		<div class="dleta-panel dleta-wf dleta-wf--product" data-dleta-when="dleta_product_enabled">
			<div class="dleta-panel__label"><?php esc_html_e( 'Where it appears', 'ultimate-estimated-delivery-date-by-bigloop' ); ?></div>
			<div class="dleta-wf__page">
				<div class="dleta-wf__cols">
					<div class="dleta-wf__gallery"><span class="dashicons dashicons-format-image" aria-hidden="true"></span></div>
					<div class="dleta-wf__summary">
						<span class="dleta-wf__bar dleta-wf__bar--title"></span>
						<?php self::wf_slot( 'after_title', $labels ); ?>
						<span class="dleta-wf__price">$49.00</span>
						<?php self::wf_slot( 'after_price', $labels ); ?>
						<span class="dleta-wf__bar"></span>
						<span class="dleta-wf__bar dleta-wf__bar--short"></span>
						<?php self::wf_slot( 'after_excerpt', $labels ); ?>
						<?php self::wf_slot( 'before_add_to_cart', $labels ); ?>
						<span class="dleta-wf__buy"><span class="dleta-wf__qty">1</span><span class="dleta-wf__btn"><?php esc_html_e( 'Add to cart', 'ultimate-estimated-delivery-date-by-bigloop' ); ?></span></span>
						<?php self::wf_slot( 'after_add_to_cart', $labels ); ?>
						<span class="dleta-wf__bar dleta-wf__bar--meta"></span>
						<?php self::wf_slot( 'after_meta', $labels ); ?>
					</div>
				</div>
				<?php self::wf_slot( 'after_summary', $labels ); ?>
				<div class="dleta-wf__tabs"><span></span><span></span><span></span></div>
			</div>
			<p class="dleta-panel__note" data-dleta-manual hidden>
				<?php
				printf(
					/* translators: %s: shortcode. */
					esc_html__( 'Shortcode only: the estimate appears wherever you place %s.', 'ultimate-estimated-delivery-date-by-bigloop' ),
					'<code>[dleta_estimate]</code>'
				);
				?>
			</p>
			<p class="dleta-panel__note dleta-panel__note--off"><?php esc_html_e( 'Product pages are switched off.', 'ultimate-estimated-delivery-date-by-bigloop' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Cart line items used in the cart and checkout wireframes.
	 *
	 * @param string $toggle Option that controls the per-item line.
	 */
	private static function wireframe_items( $toggle ) {
		$label = Dleta_Rules::option( 'dleta_label' );
		for ( $i = 0; $i < 2; $i++ ) {
			?>
			<div class="dleta-wf__item">
				<span class="dleta-wf__thumb"></span>
				<span class="dleta-wf__item-main">
					<span class="dleta-wf__bar<?php echo 1 === $i ? ' dleta-wf__bar--short' : ''; ?>"></span>
					<?php echo self::pill( $label, 'dleta-wf__eta--small', ' data-dleta-when="' . esc_attr( $toggle ) . '"' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in pill(). ?>
				</span>
				<span class="dleta-wf__amount"><?php echo 0 === $i ? '$49.00' : '$18.00'; ?></span>
			</div>
			<?php
		}
	}

	/**
	 * Totals block used in the cart and checkout wireframes.
	 *
	 * @param string $toggle Option that controls the combined line.
	 */
	private static function wireframe_totals( $toggle ) {
		?>
		<div class="dleta-wf__totals">
			<div class="dleta-wf__line"><span><?php esc_html_e( 'Subtotal', 'ultimate-estimated-delivery-date-by-bigloop' ); ?></span><span>$67.00</span></div>
			<div class="dleta-wf__line"><span><?php esc_html_e( 'Shipping', 'ultimate-estimated-delivery-date-by-bigloop' ); ?></span><span>$5.00</span></div>
			<div class="dleta-wf__line dleta-wf__line--total"><span><?php esc_html_e( 'Total', 'ultimate-estimated-delivery-date-by-bigloop' ); ?></span><span>$72.00</span></div>
			<div class="dleta-wf__line dleta-wf__line--eta" data-dleta-when="<?php echo esc_attr( $toggle ); ?>">
				<span data-dleta-text="dleta_total_label"><?php echo esc_html( Dleta_Order_Estimate::label() ); ?></span>
				<strong><?php echo esc_html( self::sample_text() ); ?></strong>
			</div>
		</div>
		<?php
	}

	/**
	 * Side panel: cart wireframe.
	 */
	public static function aside_wireframe_cart() {
		?>
		<div class="dleta-panel dleta-wf dleta-wf--cart">
			<div class="dleta-panel__label"><?php esc_html_e( 'Where it appears', 'ultimate-estimated-delivery-date-by-bigloop' ); ?></div>
			<div class="dleta-wf__page">
				<span class="dleta-wf__heading"><?php esc_html_e( 'Cart', 'ultimate-estimated-delivery-date-by-bigloop' ); ?></span>
				<?php self::wireframe_items( 'dleta_cart_enabled' ); ?>
				<?php self::wireframe_totals( 'dleta_cart_total_enabled' ); ?>
				<span class="dleta-wf__btn dleta-wf__btn--wide"><?php esc_html_e( 'Proceed to checkout', 'ultimate-estimated-delivery-date-by-bigloop' ); ?></span>
			</div>
			<p class="dleta-panel__note dleta-panel__note--none"><?php esc_html_e( 'Nothing is shown on the cart page.', 'ultimate-estimated-delivery-date-by-bigloop' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Side panel: checkout wireframe.
	 */
	public static function aside_wireframe_checkout() {
		?>
		<div class="dleta-panel dleta-wf dleta-wf--checkout">
			<div class="dleta-panel__label"><?php esc_html_e( 'Where it appears', 'ultimate-estimated-delivery-date-by-bigloop' ); ?></div>
			<div class="dleta-wf__page dleta-wf__page--checkout">
				<div class="dleta-wf__form">
					<span class="dleta-wf__heading"><?php esc_html_e( 'Checkout', 'ultimate-estimated-delivery-date-by-bigloop' ); ?></span>
					<span class="dleta-wf__field"></span>
					<span class="dleta-wf__field"></span>
					<span class="dleta-wf__field dleta-wf__field--half"></span>
					<span class="dleta-wf__field"></span>
				</div>
				<div class="dleta-wf__review">
					<span class="dleta-wf__subheading"><?php esc_html_e( 'Your order', 'ultimate-estimated-delivery-date-by-bigloop' ); ?></span>
					<?php self::wireframe_items( 'dleta_checkout_enabled' ); ?>
					<?php self::wireframe_totals( 'dleta_checkout_total_enabled' ); ?>
					<span class="dleta-wf__btn dleta-wf__btn--wide"><?php esc_html_e( 'Place order', 'ultimate-estimated-delivery-date-by-bigloop' ); ?></span>
				</div>
			</div>
			<p class="dleta-panel__note dleta-panel__note--none"><?php esc_html_e( 'Nothing is shown on the checkout page.', 'ultimate-estimated-delivery-date-by-bigloop' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Side panel: live appearance preview.
	 */
	public static function aside_preview() {
		?>
		<div class="dleta-panel dleta-preview-panel">
			<div class="dleta-panel__label"><?php esc_html_e( 'Live preview', 'ultimate-estimated-delivery-date-by-bigloop' ); ?></div>
			<div class="dleta-mock">
				<span class="dleta-wf__bar dleta-wf__bar--title"></span>
				<span class="dleta-mock__price">$49.00</span>
				<div class="dleta-estimate dleta-preview">
					<?php echo self::sample_icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in sample_icon(). ?>
					<span class="dleta-estimate__label"><?php echo esc_html( Dleta_Rules::option( 'dleta_label' ) ); ?></span>
					<span class="dleta-estimate__date"><?php echo esc_html( self::sample_text() ); ?></span>
				</div>
				<span class="dleta-mock__btn"><?php esc_html_e( 'Add to cart', 'ultimate-estimated-delivery-date-by-bigloop' ); ?></span>
			</div>
			<p class="dleta-panel__note"><?php esc_html_e( 'Your theme can change the final look slightly.', 'ultimate-estimated-delivery-date-by-bigloop' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Icon picker: visual tiles backed by radio buttons.
	 *
	 * @param array $field Field settings.
	 */
	public static function icon_picker( $field ) {
		$current = Dleta_Rules::option( $field['id'] );
		?>
		<tr valign="top">
			<th scope="row" class="titledesc"><?php echo esc_html( $field['title'] ); ?></th>
			<td class="forminp">
				<fieldset class="dleta-tiles">
					<legend class="screen-reader-text"><?php echo esc_html( $field['title'] ); ?></legend>
					<?php foreach ( Dleta_Frontend::icon_labels() as $key => $label ) : ?>
						<label class="dleta-tile">
							<input type="radio" name="<?php echo esc_attr( $field['id'] ); ?>" value="<?php echo esc_attr( $key ); ?>" <?php checked( $current, $key ); ?> />
							<span class="dleta-tile__face">
								<?php if ( 'custom' === $key ) : ?>
									<span class="dleta-tile__glyph dashicons dashicons-upload" aria-hidden="true"></span>
								<?php elseif ( 'none' === $key ) : ?>
									<span class="dleta-tile__glyph dashicons dashicons-hidden" aria-hidden="true"></span>
								<?php else : ?>
									<span class="dleta-tile__glyph dleta-estimate__icon dleta-icon--<?php echo esc_attr( $key ); ?>" aria-hidden="true"></span>
								<?php endif; ?>
								<span class="dleta-tile__label"><?php echo esc_html( $label ); ?></span>
							</span>
						</label>
					<?php endforeach; ?>
				</fieldset>
			</td>
		</tr>
		<?php
	}

	/**
	 * Ready-made date formats offered in the picker.
	 *
	 * @return string[] PHP date formats.
	 */
	public static function date_presets() {
		$presets = array( 'D, M j', 'l, F j', 'M j', 'F j, Y', 'j M', 'l j F', 'd/m/Y', 'm/d/Y', 'Y-m-d' );

		/**
		 * Filter the ready-made date formats in the settings.
		 *
		 * @param string[] $presets PHP date formats.
		 */
		return array_values( array_unique( array_filter( array_map( 'strval', (array) apply_filters( 'dleta_date_format_presets', $presets ) ) ) ) );
	}

	/**
	 * Date format picker: each choice shows the real sample delivery dates.
	 *
	 * @param array $field Field settings.
	 */
	public static function date_format( $field ) {
		$current   = (string) get_option( $field['id'], $field['default'] );
		$presets   = self::date_presets();
		$dates     = Dleta_Calculator::calculate( Dleta_Rules::global_rule() );
		$is_custom = '' !== $current && ! in_array( $current, $presets, true );
		$sample    = function ( $format ) use ( $dates ) {
			return null === $dates ? '' : Dleta_Calculator::format( $dates, $format );
		};
		?>
		<tr valign="top">
			<th scope="row" class="titledesc"><?php echo esc_html( $field['title'] ); ?></th>
			<td class="forminp">
				<fieldset class="dleta-formats">
					<legend class="screen-reader-text"><?php echo esc_html( $field['title'] ); ?></legend>
					<?php foreach ( $presets as $format ) : ?>
						<label class="dleta-format">
							<input type="radio" name="<?php echo esc_attr( $field['id'] ); ?>" value="<?php echo esc_attr( $format ); ?>" data-sample="<?php echo esc_attr( $sample( $format ) ); ?>" <?php checked( $current, $format ); ?> />
							<span class="dleta-format__face">
								<span class="dleta-format__sample"><?php echo esc_html( $sample( $format ) ); ?></span>
								<code class="dleta-format__code"><?php echo esc_html( $format ); ?></code>
							</span>
						</label>
					<?php endforeach; ?>

					<label class="dleta-format">
						<input type="radio" name="<?php echo esc_attr( $field['id'] ); ?>" value="" data-sample="<?php echo esc_attr( $sample( '' ) ); ?>" <?php checked( $current, '' ); ?> />
						<span class="dleta-format__face">
							<span class="dleta-format__sample"><?php echo esc_html( $sample( '' ) ); ?></span>
							<span class="dleta-format__code"><?php esc_html_e( 'Site default (Settings → General)', 'ultimate-estimated-delivery-date-by-bigloop' ); ?></span>
						</span>
					</label>

					<label class="dleta-format dleta-format--custom">
						<input type="radio" name="<?php echo esc_attr( $field['id'] ); ?>" value="<?php echo esc_attr( $is_custom ? $current : '' ); ?>" data-custom="1" data-sample="<?php echo esc_attr( $is_custom ? $sample( $current ) : '' ); ?>" <?php checked( $is_custom ); ?> />
						<span class="dleta-format__face">
							<span class="dleta-format__title"><?php esc_html_e( 'Custom', 'ultimate-estimated-delivery-date-by-bigloop' ); ?></span>
							<input type="text" class="dleta-format__input" value="<?php echo esc_attr( $is_custom ? $current : '' ); ?>" placeholder="<?php esc_attr_e( 'e.g. l, F jS', 'ultimate-estimated-delivery-date-by-bigloop' ); ?>" aria-label="<?php esc_attr_e( 'Custom date format', 'ultimate-estimated-delivery-date-by-bigloop' ); ?>" spellcheck="false" autocomplete="off" />
							<span class="dleta-format__sample" data-dleta-custom-sample><?php echo esc_html( $is_custom ? $sample( $current ) : '' ); ?></span>
						</span>
					</label>
				</fieldset>
				<p class="description">
					<?php
					printf(
						/* translators: %s: link to the date format reference. */
						esc_html__( 'Custom formats use PHP date characters, like on Settings → General. %s', 'ultimate-estimated-delivery-date-by-bigloop' ),
						'<a href="' . esc_url( __( 'https://wordpress.org/documentation/article/customize-date-and-time-format/', 'ultimate-estimated-delivery-date-by-bigloop' ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Format reference', 'ultimate-estimated-delivery-date-by-bigloop' ) . '</a>'
					);
					?>
				</p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Media Library picker used for the custom icon.
	 *
	 * @param array $field Field settings.
	 */
	public static function media( $field ) {
		$attachment_id = absint( get_option( $field['id'], $field['default'] ) );
		$preview       = $attachment_id ? wp_get_attachment_image( $attachment_id, 'thumbnail', false, array( 'class' => 'dleta-media-preview-img' ) ) : '';
		?>
		<tr valign="top" class="dleta-row-custom-icon">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field['id'] ); ?>"><?php echo esc_html( $field['title'] ); ?></label>
			</th>
			<td class="forminp">
				<div class="dleta-media-field">
					<span class="dleta-media-preview"><?php echo wp_kses_post( $preview ); ?></span>
					<input type="hidden" id="<?php echo esc_attr( $field['id'] ); ?>" name="<?php echo esc_attr( $field['id'] ); ?>" value="<?php echo esc_attr( (string) $attachment_id ); ?>" />
					<button type="button" class="button dleta-media-select"><?php esc_html_e( 'Choose image', 'ultimate-estimated-delivery-date-by-bigloop' ); ?></button>
					<button type="button" class="button-link dleta-media-remove"<?php echo $attachment_id ? '' : ' hidden'; ?>><?php esc_html_e( 'Remove', 'ultimate-estimated-delivery-date-by-bigloop' ); ?></button>
				</div>
				<?php if ( ! empty( $field['desc'] ) ) : ?>
					<p class="description"><?php echo esc_html( $field['desc'] ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Shortcode reference with copy buttons.
	 */
	public static function shortcodes() {
		$items = array(
			'[dleta_estimate]'          => __( 'On a product page or inside a product loop: shows that product\'s estimate.', 'ultimate-estimated-delivery-date-by-bigloop' ),
			'[dleta_estimate id="123"]' => __( 'Anywhere: shows the estimate for product ID 123.', 'ultimate-estimated-delivery-date-by-bigloop' ),
		);
		?>
		<tr class="dleta-row-full">
			<td colspan="2">
				<ul class="dleta-shortcodes">
					<?php foreach ( $items as $code => $text ) : ?>
						<li>
							<code><?php echo esc_html( $code ); ?></code>
							<span><?php echo esc_html( $text ); ?></span>
							<button type="button" class="button button-small dleta-copy" data-copy="<?php echo esc_attr( $code ); ?>" data-done="<?php esc_attr_e( 'Copied', 'ultimate-estimated-delivery-date-by-bigloop' ); ?>"><?php esc_html_e( 'Copy', 'ultimate-estimated-delivery-date-by-bigloop' ); ?></button>
						</li>
					<?php endforeach; ?>
				</ul>
			</td>
		</tr>
		<?php
	}
}
