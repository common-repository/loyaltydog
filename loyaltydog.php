<?php // @codingStandardsIgnoreLine
/**
 * LoyaltyDog Plugin.
 *
 * @package      LOYALTYDOG
 * @copyright    Copyright (C) 2018-2023, LoyaltyDog - support@loyalty.dog
 * @link         https://loyalty.dog
 * @since        0.9.0
 *
 * @wordpress-plugin
 * Plugin Name:       LoyaltyDog
 * Version:           1.2.1
 * Plugin URI:        https://loyalty.dog/ecommerce-support-wordpress-plugin/
 * Description:       LoyaltyDog - The Premeir Digital Mobile Loyalty Program for On-Premise and Online Customers
 * Author:            LoyaltyDog
 * Author URI:        https://loyalty.dog
 * License:           GPL-3.0+
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain:       loyaltydog
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

defined( 'LOYALTY_DOG_DEFAULT_API_URL' ) || define( 'LOYALTY_DOG_DEFAULT_API_URL', 'https://app.loyalty.dog/api/v1' );
defined( 'LOYALTY_DOG_PORTAL_URL' ) || define( 'LOYALTY_DOG_PORTAL_URL', 'https://scan.loyalty.dog' );
defined( 'LOYALTY_DOG_SUPPORT_URL' ) || define( 'LOYALTY_DOG_SUPPORT_URL', 'https://support.loyalty.dog' );
defined( 'LOYALTY_DOG_VERSION' ) || define( 'LOYALTY_DOG_VERSION', '1.2.1' );

// Load plugin class files
require_once( 'includes/class-loyaltydog.php' );

/**
 * The LoyaltyDog global object
 * @name $loyaltydog
 * @global LoyaltyDog $GLOBALS ['loyaltydog']
 */
$GLOBALS['loyaltydog'] = LoyaltyDog::instance( __FILE__, LOYALTY_DOG_VERSION );
