<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LoyaltyDog_Cart_Checkout
 *
 * @class LoyaltyDog_Cart_Checkout
 * @author kodeplusdev <kodeplusdev@gmail.com>
 * @version 1.0.0
 */
class LoyaltyDog_Cart_Checkout {
	/**
	 * Add cart/checkout related hooks / filters.
	 * @since 1.0.0
	 */
	public function __construct() {
		// enqueue assets
		add_action( 'woocommerce_before_cart', array( $this, 'assets' ) );
		add_action( 'woocommerce_before_checkout_form', array( $this, 'assets' ) );

		// Coupon display
		add_filter( 'woocommerce_cart_totals_coupon_label', array( $this, 'coupon_label' ), 10, 2 );
		add_filter( 'woocommerce_coupon_discount_amount_html', array( $this, 'discount_amount_html' ), 10, 2 );
		// Coupon loading
		add_action( 'woocommerce_cart_loaded_from_session', array( $this, 'points_last' ) );
		add_action( 'woocommerce_applied_coupon', array( $this, 'points_last' ) );

		// add notify/redeem active offer message
		add_action( 'woocommerce_before_shop_loop', array( $this, 'render_available_offer_message' ) );
		add_action( 'woocommerce_before_single_product', array( $this, 'render_available_offer_message' ) );
		add_action( 'woocommerce_check_cart_items', array( $this, 'render_available_offer_message' ) );
		add_action( 'woocommerce_before_cart', array( $this, 'render_redeem_offer_message' ), 16 );
		add_action( 'woocommerce_before_checkout_form', array( $this, 'render_redeem_offer_message' ), 6 );

		// handle the apply discount AJAX submit on the checkout page
		add_action( 'wp_ajax_loyalty_dog_redeem_offer', array( $this, 'ajax_maybe_redeem_offer' ) );

		// reshow messages on checkout if coupon was removed/applied?
		add_action( 'woocommerce_removed_coupon', array( $this, 'discount_removed' ) );
		add_action( 'woocommerce_applied_coupon', array( $this, 'discount_applied' ) );
	}

	/**
	 * Enqueue assets
	 */
	public function assets() {
		// add AJAX submit for applying the discount on the checkout page
		if ( is_checkout() || is_cart() ) {
			wc_enqueue_js( '
			$(document).on("submit", ".loyalty-dog-redeem-offer", function(e) {
				var $section = $("div.loyalty-dog-redeem-offer-message");

				if ($section.is(".processing")) return false;

				$section.addClass("processing").block({message: null, overlayCSS: {background: "#fff", opacity: 0.6}});

				var data = {
					action: "loyalty_dog_redeem_offer",
					security: ' . ( is_cart() ? 'wc_cart_params.apply_coupon_nonce' : 'wc_checkout_params.apply_coupon_nonce' ) . '
				};

				$.ajax({
					type:     "POST",
					url:      woocommerce_params.ajax_url,
					data:     data,
					success:  function(code) {
					
						$(".woocommerce-error, .woocommerce-message").remove();
						$section.removeClass("processing").unblock();

						if (code) {
							$section.before(code);
							$section.remove();
							$("body").trigger("' . ( is_cart() ? 'wc_update_cart' : 'update_checkout' ) . '");
						}
					},
					dataType: "html"
				});
				return false;
			});
			' );
		}
	}

	/**
	 * Make the label for the coupon look nicer.
	 *
	 * @since 1.0.0
	 *
	 * @param string $label
	 * @param $coupon
	 *
	 * @return string
	 */
	public function coupon_label( $label, $coupon ) {
		if ( strstr( get_code_from_coupon( $coupon ), 'loyalty_dog_redemption_' ) ) {
			$label = esc_html( __( 'Loyalty Reward', 'loyaltydog' ) );
		}

		return $label;
	}

	/**
	 * Make the coupon value look nicer.
	 *
	 * @since 1.0.0
	 *
	 * @param $discount_html
	 * @param $coupon
	 *
	 * @return mixed
	 */
	public function discount_amount_html( $discount_html, $coupon ) {
		if ( strstr( get_code_from_coupon( $coupon ), 'loyalty_dog_redemption_' ) ) {
			$offer         = WC()->session->get( 'loyalty_dog_offer_redeemed' );
			$discount_html = $offer->friendlyNameToBeAdded;
		}

		return $discount_html;
	}

	/**
	 * Ensure coupon are applied before tax, last.
	 * @since 1.0.0
	 */
	public function points_last() {
		$ordered_coupons = array();
		$points          = array();

		foreach ( WC()->cart->get_applied_coupons() as $code ) {
			if ( strstr( $code, 'loyalty_dog_redemption_' ) ) {
				$points[] = $code;
			} else {
				$ordered_coupons[] = $code;
			}
		}

		WC()->cart->applied_coupons = array_merge( $ordered_coupons, $points );
	}

	/**
	 * Reshow the redeem message after a discount is removed.
	 *
	 * @since 1.0.0
	 *
	 * @param string $coupon_code
	 */
	public function discount_removed( $coupon_code ) {
		if ( is_checkout() || is_cart() ) {
			$this->render_redeem_offer_message();
		}

		if ( ! strstr( $coupon_code, 'loyalty_dog_redemption_' ) ) {
			return;
		}

		// remove stored offer
		WC()->session->set( 'loyalty_dog_offer_redeemed', '' );
	}

	/**
	 * Reshow the redeem message.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Coupon|string $coupon
	 */
	public function discount_applied( $coupon ) {
		$coupon_code = is_string( $coupon ) ? $coupon : get_code_from_coupon( $coupon );

		if ( strstr( $coupon_code, 'loyalty_dog_redemption_' ) ) {
			return;
		}

		if ( is_checkout() || is_cart() ) {
			$this->render_redeem_offer_message();
		}
	}

	/**
	 * Redeem the current active offer by generating and applying a discount code via AJAX.
	 * @since 1.0.0
	 */
	public function ajax_maybe_redeem_offer() {
		check_ajax_referer( 'apply-coupon', 'security' );

		// bail if the discount has already been applied
		if ( WC()->cart->has_discount( LoyaltyDog_Discount::get_discount_code() ) ) {
			die;
		}

		// check valid offer
		$offer = LoyaltyDog_Manager::check_offer_available();
		if ( ! $offer ) {
			return false;
		}

		WC()->session->set( 'loyalty_dog_offer_redeemed', $offer );

		$discount_code = LoyaltyDog_Discount::generate_discount_code();

		WC()->cart->add_discount( $discount_code );

		wc_print_notices();
		die;
	}

	/**
	 * Renders a message to notify user a available active offer if cart is empty.
	 * @since 1.0.0
	 */
	public function render_available_offer_message() {
		// don't display a message if coupons are disabled or cart not empty
		if ( ! wc_coupons_enabled() || ! WC()->cart->is_empty() ) {
			return;
		}

		$offer = LoyaltyDog_Manager::check_offer_available();
		if ( ! $offer ) {
			return;
		}

		$message = get_option( 'loyalty_dog_available_offer_message',
			__( '<strong>Active Loyalty Offer:</strong> {friendlyNameToBeAdded}.<br />You can add some products to redeem this offer.', 'loyaltydog' ) );

		if ( empty( $message ) ) {
			return;
		}

		$message = LoyaltyDog_Manager::get_offer_message( $message, $offer );

		$message = apply_filters( 'loyalty_dog_available_offer_message', $message, $offer );

		wc_print_notice( $message, 'notice' );
	}

	/**
	 * Renders a message and button above the cart displaying the points available to redeem for a discount
	 * @since 1.0.0
	 */
	public function render_redeem_offer_message() {
		// don't display a message if coupons are disabled or offer already redeemed
		if ( ! wc_coupons_enabled() || WC()->cart->has_discount( LoyaltyDog_Discount::get_discount_code() ) ) {
			return;
		}

		$offer = LoyaltyDog_Manager::check_offer_available();
		if ( ! $offer ) {
			return;
		}

		$message = get_option( 'loyalty_dog_redeem_offer_message',
			__( '<strong>Loyalty Reward:</strong> {friendlyNameToBeAdded}', 'loyaltydog' ) );

		if ( empty( $message ) ) {
			return;
		}

		$message = LoyaltyDog_Manager::get_offer_message( $message, $offer );

		// add 'REDEEM' button
		$message .= '<form class="loyalty-dog-redeem-offer" action="' . esc_url( wc_get_cart_url() ) . '" method="post" style="display:inline">';
		$message .= '<input type="submit" class="button" name="loyalty-dog-redeem-offer" value="' . __( 'REDEEM', 'loyaltydog' ) . '" />';
		$message .= '</form>';

		// wrap with info div
		$message = '<div class="woocommerce-info loyalty-dog-redeem-offer-message">' . $message . '</div>';

		echo apply_filters( 'loyalty_dog_redeem_offer_message', $message, $offer );
	}
}
