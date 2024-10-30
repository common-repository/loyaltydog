<?php

/**
 * LoyaltyDog Uninstall
 * @since 1.0.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Clear all options
delete_option( 'loyalty_dog_version' );
delete_option( 'loyalty_dog_api_url' );
delete_option( 'loyalty_dog_program_id' );
delete_option( 'loyalty_dog_api_key' );
delete_option( 'loyalty_dog_debug' );
delete_option( 'loyalty_dog_earn_points_ratio' );
delete_option( 'loyalty_dog_earn_points_rounding' );
delete_option( 'loyalty_dog_available_offer_message' );
delete_option( 'loyalty_dog_redeem_offer_message' );
delete_option( 'loyalty_dog_account_signup_points' );
delete_option( 'loyalty_dog_write_review_points' );