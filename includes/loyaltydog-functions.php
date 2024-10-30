<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Script to change height of frame.
 * @since 1.0.8
 */
function enqueue_iframe_helper() {
	wc_enqueue_js( '
            var eventMethod = window.addEventListener ? "addEventListener" : "attachEvent";
            var eventer = window[eventMethod];
            var messageEvent = eventMethod == "attachEvent" ? "onmessage" : "message";

            eventer(messageEvent, function (e) {
                if (!e.data) return;
                try {
                    var data = JSON.parse(e.data);
                    if (data.id && data.action == "updateHeight") {
                        var iframe = $(data.id);
                        var minHeight = $(window).height() - iframe.offset().top - 65;
                        iframe.height(Math.max(minHeight, data.height) + "px");
                        $(data.id + "-loading").hide();
                    }
                } catch (e) {
                    console.error(e);
                }
            }, false);
            $(function () {
                $("iframe").each(function (idx, iframe) {
                    iframe.contentWindow.postMessage("getHeight", "*")
                });
            });
        ' );
}

/**
 * Check if current WooCommerce version is supported
 * @since 1.1.7
 * 
 * @param string $version The minimum required WooCommerce version.
 */
function is_min_wc_version( $version ) {
    return defined( 'WC_VERSION' ) && version_compare( WC_VERSION, $version, '>=' );
}

/**
 * Get customer id from order
 * @since 1.1.8
 * 
 * @param WC_Order $order
 */
function get_customer_id_from_order( $order ) {
    if ( is_min_wc_version( '3.0.0' ) ) {
        return $order->get_customer_id();
    } else {
        return $order->customer_user;
    }
}

/**
 * Get used coupons from order
 * @since 1.1.8
 * 
 * @param WC_Order $order
 */
function get_used_coupons_from_order( $order ) {
    if ( is_min_wc_version( '3.7.0' ) ) {
        return $order->get_coupon_codes();
    } else {
        return $order->get_used_coupons();
    }
}

/**
 * Get coupon code from coupon
 * @since 1.1.8
 */
function get_code_from_coupon( $coupon ) {
    if ( is_min_wc_version( '3.0.0' ) ) {
        return $coupon->get_code();
    } else {
        return $coupon->code;
    }
}
