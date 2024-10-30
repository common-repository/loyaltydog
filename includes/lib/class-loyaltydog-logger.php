<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Logger.
 *
 * @class LoyaltyDog_Logger
 * @author kodeplusdev <kodeplusdev@gmail.com>
 * @version 1.0.0
 */
class LoyaltyDog_Logger {
	/**
	 * Log verbose (must be enabled).
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $data The verbose message.
	 */
	public static function v( $data ) {
		if ( get_option( 'loyalty_dog_debug', 'no' ) === 'yes' ) {
			$logger = new WC_Logger();
			$logger->add( 'loyaltydog-logs', $data );
		}
	}

	/**
	 * Log errors.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $data The error message.
	 */
	public static function e( $data ) {
		$logger = new WC_Logger();
		$logger->add( 'loyaltydog-errors', $data );
	}
}