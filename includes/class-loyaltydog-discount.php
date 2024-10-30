<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds messages and calculates the discounts available
 *
 * @class LoyaltyDog_Discount
 * @author kodeplusdev <kodeplusdev@gmail.com>
 * @version 1.0.0
 */
class LoyaltyDog_Discount {
	/**
	 * Add coupon-related filters to help generate the custom coupon
	 * @since 1.0.0
	 */
	public function __construct() {
		// set our custom coupon data
		add_filter( 'woocommerce_get_shop_coupon_data', array( $this, 'get_discount_data' ), 10, 2 );

		// filter the "coupon applied successfully" message
		add_filter( 'woocommerce_coupon_message', array( $this, 'get_discount_applied_message' ), 10, 3 );
	}

	/**
	 * Generate the coupon data required for the discount
	 *
	 * @since 1.0.0
	 *
	 * @param array $data the coupon data
	 * @param string $code the coupon code
	 *
	 * @return array the custom coupon data
	 */
	public function get_discount_data( $data, $code ) {
		if ( WC()->session === null || strtolower( $code ) != $this->get_discount_code() ) {
			return $data;
		}

		$offer = WC()->session->get( 'loyalty_dog_offer_redeemed' );

		if ( is_min_wc_version( '3.0.0' ) ) {
			$data = array(
				'id'                         => true,
				'type'                       => $offer->type,
				'amount'                     => self::get_discount_amount_for_redeeming_offer( $offer ),
				'individual_use'             => false,
				'product_ids'                => array(),
				'exclude_product_ids'        => array(),
				'usage_limit'                => 1,
				'usage_count'                => 0,
				'expiry_date'                => '',
				'apply_before_tax'           => true,
				'free_shipping'              => false,
				'product_categories'         => array(),
				'exclude_product_categories' => array(),
				'exclude_sale_items'         => false,
				'minimum_amount'             => '',
				'maximum_amount'             => '',
				'customer_email'             => ''
			);
		} else {
			$data = array(
				'id'                         => true,
				'type'                       => $offer->type,
				'amount'                     => self::get_discount_amount_for_redeeming_offer( $offer ),
				'individual_use'             => 'no',
				'product_ids'                => '',
				'exclude_product_ids'        => '',
				'usage_limit'                => 1,
				'usage_count'                => 0,
				'expiry_date'                => '',
				'apply_before_tax'           => 'yes',
				'free_shipping'              => 'no',
				'product_categories'         => array(),
				'exclude_product_categories' => array(),
				'exclude_sale_items'         => 'no',
				'minimum_amount'             => '',
				'maximum_amount'             => '',
				'customer_email'             => ''
			);
		}

		return $data;
	}

	/**
	 * Get discount amount of current redeeming offer.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $offer The redeeming offer to get. Optional
	 *
	 * @return float|int The discount amount
	 */
	public static function get_discount_amount_for_redeeming_offer( $offer = null ) {
		if ( empty( $offer ) ) {
			$offer = WC()->session->get( 'loyalty_dog_offer_redeemed' );
		}

		if ( $offer && ! empty( $offer->value ) ) {
			switch ( $offer->type ) {
				case 'percent':
					return $offer->value * 100;
					break;
				case 'fixed_cart':
				default:
					return $offer->value;
					break;
			}
		}

		return 0;
	}

	/**
	 * Change the "Coupon applied successfully" message to "Discount Applied Successfully"
	 *
	 * @since 1.0
	 *
	 * @param string $message the message text
	 * @param string $message_code the message code
	 * @param object $coupon the WC_Coupon instance
	 *
	 * @return string the modified messages
	 */
	public function get_discount_applied_message( $message, $message_code, $coupon ) {
		if ( $message_code === WC_Coupon::WC_COUPON_SUCCESS && $coupon->get_code() === $this->get_discount_code() ) {
			return __( 'Redeemed Successfully', 'loyaltydog' );
		} else {
			return $message;
		}
	}

	/**
	 * Generates a unique discount code tied to the current user ID and timestamp
	 *
	 * @since 1.0.0
	 */
	public static function generate_discount_code() {
		$offer         = WC()->session->get( 'loyalty_dog_offer_redeemed' );
		$discount_code = sprintf( 'loyalty_dog_redemption_%s_%s_%s', get_current_user_id(), date( 'Y_m_d_h_i', current_time( 'timestamp' ) ), $offer->id );

		WC()->session->set( 'loyalty_dog_discount_code', $discount_code );

		return $discount_code;
	}

	/**
	 * Returns the unique discount code generated for the applied discount if set
	 *
	 * @since 1.0.0
	 */
	public static function get_discount_code() {
		if ( WC()->session !== null ) {
			return WC()->session->get( 'loyalty_dog_discount_code' );
		}
	}
}
