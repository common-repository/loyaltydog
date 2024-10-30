<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * LoyaltyDog Manager
 *
 * @class LoyaltyDog_Manager
 * @author kodeplusdev <kodeplusdev@gmail.com>
 * @version 1.0.0
 */
class LoyaltyDog_Manager {

	/**
	 * Calculate the points earned for a purchase based on the given amount.
	 *
	 * @since 1.0.0
	 *
	 * @param string|float the amount to calculate the points earned for
	 *
	 * @return int the points earned
	 */
	public static function calculate_points( $amount ) {
		list( $points, $monetary_value ) = explode( ':', get_option( 'loyalty_dog_earn_points_ratio', '' ) );

		if ( ! $points ) {
			return 0;
		}

		switch ( get_option( 'loyalty_dog_earn_points_rounding' ) ) {
			case 'ceil':
				$points = ceil( $amount * ( $points / $monetary_value ) );
				break;
			case 'floor':
				$points = floor( $amount * ( $points / $monetary_value ) );
				break;
			default:
				$points = round( $amount * ( $points / $monetary_value ) );
				break;
		}

		return apply_filters( 'loyalty_dog_calculate_point', $points, $amount );
	}

	/**
	 * Update Loyalty points for a user or Loyalty customer.
	 *
	 * @since 1.0.0
	 *
	 * @param int $points Amount points to add
	 * @param int $user_id The WP user
	 * @param int|bool|object $customer The Loyalty customer
	 *
	 * @return bool The success or failure code
	 */
	public static function update_loyalty_points( $points, $user_id = 0, $customer = 0 ) {
		// only update if valid points
		if ( ! $points ) {
			return false;
		}

		if ( ! $customer ) {
			$customer = self::get_loyalty_customer( $user_id );

			if ( ! $customer ) {
				return false;
			}
		}

		$api = new LoyaltyDog_API();

		return $api->update_points( $customer->id, $points );
	}

	/**
	 * Redeem active offer for a WP user or Loyalty customer.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id The WP user
	 * @param int|bool|object $customer The Loyalty customer
	 *
	 * @return bool The success or failure code
	 */
	public static function redeem_offer( $user_id = 0, $customer = 0 ) {
		if ( ! $customer ) {
			$customer = self::get_loyalty_customer( $user_id );

			if ( ! $customer ) {
				return false;
			}
		}

		$api = new LoyaltyDog_API();

		return $api->redeem_offer( $customer->id );
	}

	/**
	 * Get Loyalty customer for a WP user.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id The WP user
	 *
	 * @return bool|stdClass The Loyalty customer or false
	 */
	public static function get_loyalty_customer( $user_id = 0 ) {
		if ( ! $user_id ) {
			$user = wp_get_current_user();
		} else {
			$user = get_userdata( $user_id );
		}

		if ( ! $user ) {
			return false;
		}

		$api = new LoyaltyDog_API();

		return $api->get_customer_details( $user->user_email );
	}

	/**
	 * Check current active offer.
	 *
	 * @since 1.0.0
	 * @return stdClass|bool The offer detail or false.
	 */
	public static function check_offer_available() {
		$customer = self::get_loyalty_customer();

		if ( ! $customer ) {
			return false;
		}

		if ( is_null( $customer->currentOfferId ) || $customer->currentOfferId === 'null' ) {
			return false;
		}

		$api = new LoyaltyDog_API();

		return $api->get_offer_details( $customer->currentOfferId );
	}

	/**
	 * Return the message based on template from offer.
	 *
	 * @since 1.0.0
	 *
	 * @param string $template The template to use
	 * @param object $offer The offer to use
	 *
	 * @return string The final messages
	 */
	public static function get_offer_message( $template, $offer ) {
		if ( empty( $template ) || ! $offer ) {
			return $template;
		}

		if ( isset( $offer->friendlyName ) ) {
			$template = str_replace( '{friendlyName}', $offer->friendlyName, $template );
		}
		if ( isset( $offer->friendlyNameToBeAdded ) ) {
			$template = str_replace( '{friendlyNameToBeAdded}', $offer->friendlyNameToBeAdded, $template );
		}
		if ( isset( $offer->name ) ) {
			$template = str_replace( '{name}', $offer->name, $template );
		}

		switch ( $offer->type ) {
			case 'percent':
				if ( isset( $offer->value ) ) {
					$template = str_replace( '{value}', $offer->value * 100, $template );
				}
				$template = str_replace( '{currency}', '%', $template );
				break;
			case 'fixed_cart':
			default:
				if ( isset( $offer->value ) ) {
					$template = str_replace( '{value}', $offer->value, $template );
				}
				if ( isset( $offer->currency ) ) {
					$template = str_replace( '{currency}', $offer->currency, $template );
				}
				break;
		}

		return $template;
	}
}
