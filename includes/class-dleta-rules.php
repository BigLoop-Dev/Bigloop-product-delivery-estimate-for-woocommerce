<?php
/**
 * Delivery rules: storage, sanitizing and resolving which rule applies.
 *
 * Priority: product > category > global.
 *
 * @package BigLoopPDE
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads and resolves delivery rules.
 */
class Dleta_Rules {

	/**
	 * Highest number of days accepted anywhere.
	 */
	const MAX_DAYS = 365;

	/**
	 * Meta keys used for products (post meta) and categories (term meta).
	 */
	const META_MODE    = '_dleta_mode';
	const META_MIN     = '_dleta_min_days';
	const META_MAX     = '_dleta_max_days';
	const META_FIXED   = '_dleta_fixed_days';
	const META_WEEKEND = '_dleta_exclude_weekends';

	/**
	 * Default values for all global options.
	 *
	 * @return array<string, string>
	 */
	public static function default_options() {
		return array(
			'dleta_mode'                   => 'range',
			'dleta_min_days'               => '3',
			'dleta_max_days'               => '5',
			'dleta_fixed_days'             => '3',
			'dleta_exclude_weekends'       => 'yes',
			'dleta_product_enabled'        => 'yes',
			'dleta_product_position'       => 'after_price',
			'dleta_cart_enabled'           => 'yes',
			'dleta_checkout_enabled'       => 'yes',
			'dleta_subs_renewal'           => 'yes',
			'dleta_order_enabled'          => 'yes',
			'dleta_cart_total_enabled'     => 'no',
			'dleta_checkout_total_enabled' => 'no',
			'dleta_total_mode'             => 'span',
			'dleta_total_label'            => __( 'Estimated delivery for your order', 'bigloop-product-delivery-estimate' ),
			'dleta_hide_out_of_stock'      => 'yes',
			'dleta_icon'                   => 'truck',
			'dleta_icon_custom'            => '0',
			'dleta_icon_color'             => '',
			'dleta_icon_size'              => '',
			'dleta_font_family'            => 'inherit',
			'dleta_font_custom'            => '',
			'dleta_font_size'              => '',
			'dleta_text_color'             => '',
			'dleta_date_color'             => '',
			'dleta_label'                  => __( 'Estimated delivery:', 'bigloop-product-delivery-estimate' ),
			'dleta_date_format'            => 'D, M j',
			'dleta_delete_data'            => 'no',
		);
	}

	/**
	 * Get a global option with its default as fallback.
	 *
	 * @param string $key Option name.
	 * @return string
	 */
	public static function option( $key ) {
		$defaults = self::default_options();
		$default  = isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
		return (string) get_option( $key, $default );
	}

	/**
	 * Allowed modes for a rule.
	 *
	 * @param bool $with_inherit Include the "inherit" (empty) and "none" modes.
	 * @return string[]
	 */
	public static function modes( $with_inherit = false ) {
		$modes = array( 'range', 'fixed' );
		if ( $with_inherit ) {
			$modes[] = '';
			$modes[] = 'none';
		}
		return $modes;
	}

	/**
	 * Normalize a rule array so values are always valid.
	 *
	 * @param array $rule Raw rule.
	 * @return array{mode:string,min:int,max:int,fixed:int,exclude_weekends:bool}
	 */
	public static function normalize( array $rule ) {
		$mode = isset( $rule['mode'] ) && in_array( $rule['mode'], self::modes( true ), true ) ? $rule['mode'] : 'range';
		$min  = self::clamp_days( isset( $rule['min'] ) ? $rule['min'] : 0 );
		$max  = self::clamp_days( isset( $rule['max'] ) ? $rule['max'] : 0 );

		if ( $max < $min ) {
			$max = $min;
		}

		return array(
			'mode'             => $mode,
			'min'              => $min,
			'max'              => $max,
			'fixed'            => self::clamp_days( isset( $rule['fixed'] ) ? $rule['fixed'] : 0 ),
			'exclude_weekends' => ! empty( $rule['exclude_weekends'] ),
		);
	}

	/**
	 * Clamp a day count to 0..MAX_DAYS.
	 *
	 * @param mixed $days Value to clamp.
	 * @return int
	 */
	public static function clamp_days( $days ) {
		return min( self::MAX_DAYS, absint( $days ) );
	}

	/**
	 * The global rule from the WooCommerce settings tab.
	 *
	 * @return array
	 */
	public static function global_rule() {
		return self::normalize(
			array(
				'mode'             => self::option( 'dleta_mode' ),
				'min'              => self::option( 'dleta_min_days' ),
				'max'              => self::option( 'dleta_max_days' ),
				'fixed'            => self::option( 'dleta_fixed_days' ),
				'exclude_weekends' => 'yes' === self::option( 'dleta_exclude_weekends' ),
			)
		);
	}

	/**
	 * Rule stored on a product, or null when it inherits.
	 *
	 * @param int $product_id Product ID (parent ID for variations).
	 * @return array|null
	 */
	public static function product_rule( $product_id ) {
		$mode = (string) get_post_meta( $product_id, self::META_MODE, true );

		// Translations without their own setting use the original product's (WPML / Polylang).
		if ( '' === $mode ) {
			$original = self::original_id( $product_id, get_post_type( $product_id ) );
			if ( $original && $original !== (int) $product_id ) {
				return self::product_rule( $original );
			}
		}

		if ( '' === $mode || ! in_array( $mode, self::modes( true ), true ) ) {
			return null;
		}

		return self::normalize(
			array(
				'mode'             => $mode,
				'min'              => get_post_meta( $product_id, self::META_MIN, true ),
				'max'              => get_post_meta( $product_id, self::META_MAX, true ),
				'fixed'            => get_post_meta( $product_id, self::META_FIXED, true ),
				'exclude_weekends' => 'yes' === get_post_meta( $product_id, self::META_WEEKEND, true ),
			)
		);
	}

	/**
	 * ID of the item in the default language when a multilingual plugin is
	 * active (WPML or Polylang), or 0 when there is none.
	 *
	 * @param int    $id   Post or term ID.
	 * @param string $type Post type or taxonomy (product, product_variation, product_cat).
	 * @return int
	 */
	public static function original_id( $id, $type ) {
		$id   = (int) $id;
		$type = (string) $type;

		if ( ! $id || '' === $type ) {
			return 0;
		}

		if ( has_filter( 'wpml_object_id' ) ) {
			$default  = apply_filters( 'wpml_default_language', null ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's own hook.
			$original = $default ? apply_filters( 'wpml_object_id', $id, $type, false, $default ) : 0; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's own hook.
			return (int) $original;
		}

		if ( function_exists( 'pll_default_language' ) ) {
			$default = pll_default_language();
			if ( 'product_cat' === $type && function_exists( 'pll_get_term' ) ) {
				return (int) pll_get_term( $id, $default );
			}
			if ( function_exists( 'pll_get_post' ) ) {
				return (int) pll_get_post( $id, $default );
			}
		}

		return 0;
	}

	/**
	 * Rule stored on a product category, or null when it inherits.
	 *
	 * @param int $term_id Term ID.
	 * @return array|null
	 */
	public static function term_rule( $term_id ) {
		$mode = (string) get_term_meta( $term_id, self::META_MODE, true );

		// Translated categories without their own setting use the original category's.
		if ( '' === $mode ) {
			$original = self::original_id( $term_id, 'product_cat' );
			if ( $original && $original !== (int) $term_id ) {
				return self::term_rule( $original );
			}
		}

		if ( '' === $mode || ! in_array( $mode, self::modes( true ), true ) ) {
			return null;
		}

		return self::normalize(
			array(
				'mode'             => $mode,
				'min'              => get_term_meta( $term_id, self::META_MIN, true ),
				'max'              => get_term_meta( $term_id, self::META_MAX, true ),
				'fixed'            => get_term_meta( $term_id, self::META_FIXED, true ),
				'exclude_weekends' => 'yes' === get_term_meta( $term_id, self::META_WEEKEND, true ),
			)
		);
	}

	/**
	 * Find the category rule for a term, walking up to parent categories.
	 *
	 * @param int $term_id Term ID.
	 * @return array|null Array with 'rule' and 'term_id', or null.
	 */
	public static function term_rule_with_ancestors( $term_id ) {
		$chain = array_merge( array( (int) $term_id ), array_map( 'intval', get_ancestors( $term_id, 'product_cat', 'taxonomy' ) ) );

		foreach ( $chain as $id ) {
			$rule = self::term_rule( $id );
			if ( null !== $rule ) {
				return array(
					'rule'    => $rule,
					'term_id' => $id,
				);
			}
		}

		return null;
	}

	/**
	 * Resolve which rule applies to a product.
	 *
	 * Priority: variation, then product, then category, then the default.
	 *
	 * When a product is in several categories with their own rules, the
	 * category with the latest delivery wins, so customers are never promised
	 * a date that is too early.
	 *
	 * @param WC_Product|int $product Product object or ID.
	 * @return array{rule:array|null,source:string,term_id:int}
	 */
	public static function resolve( $product ) {
		$product = $product instanceof WC_Product ? $product : wc_get_product( $product );

		$result = array(
			'rule'    => self::global_rule(),
			'source'  => 'global',
			'term_id' => 0,
		);

		if ( ! $product ) {
			return $result;
		}

		$product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();

		// A variation's own setting beats everything (also subscription variations).
		$variation_rule = $product->is_type( 'variation' ) || $product instanceof WC_Product_Variation ? self::product_rule( $product->get_id() ) : null;

		$product_rule = null === $variation_rule ? self::product_rule( $product_id ) : null;
		if ( null !== $variation_rule ) {
			$result = array(
				'rule'    => $variation_rule,
				'source'  => 'variation',
				'term_id' => 0,
			);
		} elseif ( null !== $product_rule ) {
			$result = array(
				'rule'    => $product_rule,
				'source'  => 'product',
				'term_id' => 0,
			);
		} else {
			$category = self::category_rule_for_product( $product_id );
			if ( null !== $category ) {
				$result = array(
					'rule'    => $category['rule'],
					'source'  => 'category',
					'term_id' => $category['term_id'],
				);
			}
		}

		if ( 'none' === $result['rule']['mode'] ) {
			$result['rule'] = null;
		}

		/**
		 * Filter the resolved delivery rule for a product.
		 *
		 * @param array      $result  Array with 'rule' (array|null), 'source' and 'term_id'.
		 * @param WC_Product $product Product.
		 */
		return apply_filters( 'dleta_resolved_rule', $result, $product );
	}

	/**
	 * Pick the category rule that applies to a product.
	 *
	 * @param int $product_id Product ID.
	 * @return array|null Array with 'rule' and 'term_id', or null.
	 */
	public static function category_rule_for_product( $product_id ) {
		$term_ids = wc_get_product_term_ids( $product_id, 'product_cat' );
		if ( empty( $term_ids ) ) {
			return null;
		}

		$found = array();
		foreach ( $term_ids as $term_id ) {
			$match = self::term_rule_with_ancestors( $term_id );
			if ( null !== $match ) {
				$found[] = $match;
			}
		}

		if ( empty( $found ) ) {
			return null;
		}

		// Prefer categories that show a date; among those, the latest delivery wins.
		$showing = array_filter(
			$found,
			function ( $item ) {
				return 'none' !== $item['rule']['mode'];
			}
		);

		if ( empty( $showing ) ) {
			return reset( $found );
		}

		usort(
			$showing,
			function ( $a, $b ) {
				return self::longest_days( $b['rule'] ) <=> self::longest_days( $a['rule'] );
			}
		);

		return reset( $showing );
	}

	/**
	 * The upper day count for a rule.
	 *
	 * @param array $rule Normalized rule.
	 * @return int
	 */
	public static function longest_days( array $rule ) {
		return 'fixed' === $rule['mode'] ? $rule['fixed'] : $rule['max'];
	}

	/**
	 * Human readable summary of a rule, used in admin screens.
	 *
	 * @param array|null $rule Normalized rule or null.
	 * @return string
	 */
	public static function describe( $rule ) {
		if ( null === $rule || 'none' === $rule['mode'] ) {
			return __( 'Not shown', 'bigloop-product-delivery-estimate' );
		}

		if ( 'fixed' === $rule['mode'] ) {
			/* translators: %d: number of days. */
			$text = sprintf( _n( '%d day', '%d days', $rule['fixed'], 'bigloop-product-delivery-estimate' ), $rule['fixed'] );
		} else {
			/* translators: 1: minimum days, 2: maximum days. */
			$text = sprintf( __( '%1$d–%2$d days', 'bigloop-product-delivery-estimate' ), $rule['min'], $rule['max'] );
		}

		if ( $rule['exclude_weekends'] ) {
			$text .= ' ' . __( '(weekends excluded)', 'bigloop-product-delivery-estimate' );
		}

		return $text;
	}

	/**
	 * Read a rule from submitted form data (product or category screens).
	 *
	 * The caller must verify the nonce and capabilities before calling this.
	 *
	 * @param array $data Unslashed request data.
	 * @return array Sanitized rule with 'mode' possibly '' (inherit).
	 */
	public static function from_request( array $data ) {
		$mode = isset( $data['dleta_mode'] ) ? sanitize_key( $data['dleta_mode'] ) : '';
		if ( ! in_array( $mode, self::modes( true ), true ) ) {
			$mode = '';
		}

		return array(
			'mode'             => $mode,
			'min'              => isset( $data['dleta_min_days'] ) ? self::clamp_days( $data['dleta_min_days'] ) : 0,
			'max'              => isset( $data['dleta_max_days'] ) ? self::clamp_days( $data['dleta_max_days'] ) : 0,
			'fixed'            => isset( $data['dleta_fixed_days'] ) ? self::clamp_days( $data['dleta_fixed_days'] ) : 0,
			'exclude_weekends' => ! empty( $data['dleta_exclude_weekends'] ),
		);
	}
}
