<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * LoyaltyDog core actions.
 *
 * @class LoyaltyDog_Actions
 * @author kodeplusdev <kodeplusdev@gmail.com>
 * @version 1.0.0
 */
class LoyaltyDog_Actions {
	/**
	 * LoyaltyDog_Admin constructor.
	 * @since 1.0.0
	 */
	public function __construct() {
		// add the WooCommerce core action settings
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			add_filter( 'loyalty_dog_action_settings', array( $this, 'earn_points_action_settings' ), 1 );
		}

		// update points when order completed
		$status = get_option( 'loyalty_dog_earn_points_when', 'processing' );
		add_action( 'woocommerce_order_status_' . $status, array( $this, 'order_status_completed' ) );

		// add points for user signup & writing a review
		add_action( 'comment_post', array( $this, 'product_review_action' ), 10, 2 );
		add_action( 'comment_unapproved_to_approved', array( $this, 'product_review_approve_action' ) );
		add_action( 'user_register', array( $this, 'create_account_action' ) );
	}

	/**
	 * Update Loyalty customer details when order complete.
	 *
	 * @since 1.0.0
	 *
	 * @param string $order_id The order ID.
	 */
	public function order_status_completed( $order_id ) {
		LoyaltyDog_Logger::v( "Order received: " . $order_id );

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			LoyaltyDog_Logger::v( "Failure to get order details. Order ID: " . $order_id );
			LoyaltyDog_Logger::e( "Failure to get order details. Order ID: " . $order_id );

			return;
		}

		$order_customer_id = get_customer_id_from_order( $order );

		if ( $order_customer_id === 0 ) {
			LoyaltyDog_Logger::v( "Ordered by guest. Rejected." );

			return;
		}

		$customer = LoyaltyDog_Manager::get_loyalty_customer( $order_customer_id );

		if ( ! $customer ) {
			return;
		}

		$total  = $order->get_total();
		$points = LoyaltyDog_Manager::calculate_points( $total );

		LoyaltyDog_Manager::update_loyalty_points( $points, 0, $customer );

		if ( ! is_null( $customer->currentOfferId ) && $customer->currentOfferId !== 'null' ) {
			$coupon_codes = get_used_coupons_from_order( $order );

			foreach ( $coupon_codes as $code ) {
				if ( strstr( $code, 'loyalty_dog_redemption_' ) && strstr( $code, "$customer->currentOfferId" ) ) {
					LoyaltyDog_Manager::redeem_offer( 0, $customer );
					break;
				}
			}
		}
	}

	/**
	 * Adds the WooCommerce core actions integration settings
	 *
	 * @since 1.0.0
	 *
	 * @param array $settings the settings array
	 *
	 * @return array the settings array
	 */
	public function earn_points_action_settings( $settings ) {

		$settings = array_merge(
			$settings,
			array(
				array(
					'title'    => __( 'Points earned for account signup', 'loyaltydog' ),
					'desc_tip' => __( 'Enter the amount of points earned when a customer signs up for an account.', 'loyaltydog' ),
					'id'       => 'loyalty_dog_account_signup_points',
				),

				array(
					'title'    => __( 'Points earned for writing a review', 'loyaltydog' ),
					'desc_tip' => __( 'Enter the amount of points earned when a customer first reviews a product.', 'loyaltydog' ),
					'id'       => 'loyalty_dog_write_review_points',
				),
			)
		);

		return $settings;
	}

	/**
	 * Add points to customer for posting a product review
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Comment|string|int $comment_id
	 * @param int $approved
	 */
	public function product_review_action( $comment_id, $approved = 0 ) {
		if ( ! is_user_logged_in() || ! $approved ) {
			return;
		}

		$comment   = get_comment( $comment_id );
		$post_type = get_post_type( $comment->comment_post_ID );

		if ( 'product' === $post_type ) {
			$points = get_option( 'loyalty_dog_write_review_points' );
		}

		if ( ! empty( $points ) ) {

			// filter the parameters for get_comments called on posting a review
			$params = apply_filters( 'loyalty_dog_review_post_comments_args', array(
				'user_id' => get_current_user_id(),
				'post_id' => $comment->comment_post_ID
			) );

			// only award points for the first comment placed on a particular product by a user
			$comments = get_comments( $params );

			// filter if points should be added for this comment id on posting a review
			if ( count( $comments ) <= 1 && apply_filters( 'loyalty_dog_post_add_product_review_points', true, $comment_id ) ) {
				LoyaltyDog_Logger::v( "Add $points points to {$comment->user_id} for product review" );
				LoyaltyDog_Manager::update_loyalty_points( get_current_user_id(), $points );
			}
		}
	}

	/**
	 * Triggered when a comment is approved
	 *
	 * @param WP_Comment $comment
	 */
	public function product_review_approve_action( $comment ) {
		$post_type = get_post_type( $comment->comment_post_ID );

		if ( 'product' === $post_type ) {
			$points = get_option( 'loyalty_dog_write_review_points' );
		}

		if ( ! empty( $points ) && $comment->user_id ) {

			// filter the parameters for get_comments called when reviews are approved
			$params = apply_filters( 'loyalty_dog_review_approve_comments_args', array(
				'user_id' => $comment->user_id,
				'post_id' => $comment->comment_post_ID
			) );

			// only award points for the first comment placed on a particular product by a user
			$comments = get_comments( $params );

			// filter if points should be added for this comment id when reviews are approved
			if ( count( $comments ) <= 1 && apply_filters( 'loyalty_dog_approve_add_product_review_points', true, $comment->comment_ID ) ) {
				LoyaltyDog_Logger::v( "Add $points points to {$comment->user_id} for product review" );
				LoyaltyDog_Manager::update_loyalty_points( $comment->user_id, $points );
			}
		}
	}

	/**
	 * Add points to customer for creating an account
	 *
	 * @since 1.0
	 *
	 * @param int $user_id The WP user
	 */
	public function create_account_action( $user_id ) {
		$points = get_option( 'loyalty_dog_account_signup_points' );

		if ( ! empty( $points ) ) {
			LoyaltyDog_Logger::v( "Add $points points to $user_id for new signup" );
			LoyaltyDog_Manager::update_loyalty_points( $user_id, $points );
		}
	}
}
