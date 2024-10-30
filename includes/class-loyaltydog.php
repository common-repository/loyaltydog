<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'LoyaltyDog' ) ) :

	/**
	 * Main LoyaltyDog WooCommerce
	 *
	 * @class LoyaltyDog
	 * @author kodeplusdev <kodeplusdev@gmail.com>
	 * @version 1.0.0
	 */
	class LoyaltyDog {
		/**
		 * @since 1.0.0
		 * @var object The single instance of LoyaltyDog.
		 */
		protected static $_instance = null;

		/**
		 * @since 1.0.0
		 * @var string The version number.
		 */
		public $version;

		/**
		 * @since 1.0.0
		 * @var string The token.
		 */
		public $id;

		/**
		 * Constructor function.
		 *
		 * @since 1.0.0
		 *
		 * @param string $file
		 * @param string $version
		 */
		public function __construct( $file = '', $version = '1.0.0' ) {
			$this->version = $version;
			$this->id      = 'loyaltydog';

			// Load plugin environment variables
			$this->define_constants( $file );

			register_activation_hook( LOYALTY_DOG_PLUGIN_FILE, array( $this, 'install' ) );

			// Handle localisation
			add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ), 0 );

			// Load dependencies
			add_action( 'plugins_loaded', array( $this, 'init' ) );
		}

		/**
		 * Initialize the plugin.
		 * @since 1.0.0
		 */
		public function init() {
			// Checks if WooCommerce is installed and activated.
			if ( class_exists( 'WooCommerce' ) ) {
				include_once 'loyaltydog-functions.php';
				include_once 'lib/class-loyaltydog-logger.php';
				include_once 'lib/class-loyaltydog-rest-client.php';
				include_once 'lib/class-loyaltydog-api.php';
				include_once 'class-loyaltydog-notices.php';

				if ( empty( $this->manager ) ) {
					include_once 'class-loyaltydog-manager.php';
					$this->manager = new LoyaltyDog_Manager();
				}

				if ( empty( $this->actions ) ) {
					include_once 'class-loyaltydog-actions.php';
					$this->actions = new LoyaltyDog_Actions();
				}

				if ( empty( $this->cart ) ) {
					include_once 'class-loyaltydog-cart-checkout.php';
					$this->cart = new LoyaltyDog_Cart_Checkout();
				}

				if ( empty( $this->discount ) ) {
					include_once 'class-loyaltydog-discount.php';
					$this->discount = new LoyaltyDog_Discount();
				}

				if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
					if ( empty( $this->welcome ) ) {
						include_once 'class-loyaltydog-welcome.php';
						$this->welcome = new LoyaltyDog_Welcome();
					}

					if ( empty( $this->admin ) ) {
						include_once 'class-loyaltydog-admin.php';
						$this->admin = new LoyaltyDog_Admin();
					}

					// Add settings link to plugins page
					add_filter( 'plugin_action_links_' . LOYALTY_DOG_PLUGIN_BASENAME, array(
						$this,
						'add_plugin_configure_link'
					) );
				}
			} else {
				add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			}
		}

		/**
		 * Return the plugin action links.  This will only be called if the plugin is active.
		 *
		 * @since 1.0
		 *
		 * @param array $actions associative array of action names to anchor tags
		 *
		 * @return array associative array of plugin action links
		 */
		public function add_plugin_configure_link( $actions ) {
			// add the link to the front of the actions list
			return ( array_merge( array( 'configure' => sprintf( '<a href="%s">%s</a>', admin_url( 'admin.php?page=loyaltydog&tab=settings' ), __( 'Settings', 'loyaltydog' ) ) ),
				$actions )
			);
		}

		/**
		 * WooCommerce fallback notice.
		 * @since 1.0.0
		 */
		public function woocommerce_missing_notice() {
			include_once 'views/html-missing-dependencies.php';
		}

		/**
		 * Load plugin textdomain
		 * @since 1.0.0
		 */
		public function load_plugin_textdomain() {
			load_plugin_textdomain( 'loyaltydog', false, LOYALTY_DOG_PLUGIN_DIR . '/languages/' );
		}

		/**
		 * Define Constants.
		 *
		 * @since 1.0.0
		 *
		 * @param string $file The main plugin file
		 */
		private function define_constants( $file = '' ) {
			$this->define( 'LOYALTY_DOG_PLUGIN_FILE', $file );
			$this->define( 'LOYALTY_DOG_PLUGIN_BASENAME', plugin_basename( $file ) );
			$this->define( 'LOYALTY_DOG_PLUGIN_DIR', dirname( LOYALTY_DOG_PLUGIN_BASENAME ) );
			$this->define( 'LOYALTY_DOG_PLUGIN_URL', untrailingslashit( plugins_url( '/', $file ) ) );
			$this->define( 'LOYALTY_DOG_VERSION', $this->version );
			$this->define( 'LOYALTY_DOG_CUSTOMER_DETAILS_CACHE', 30 ); // 30 seconds
			$this->define( 'LOYALTY_DOG_OFFER_DETAILS_CACHE', 3600 ); // 1 hour
		}

		/**
		 * Define constant if not already set.
		 *
		 * @since 1.0.0
		 *
		 * @param string $name The constant name
		 * @param string|bool $value The constant value
		 */
		private function define( $name, $value ) {
			if ( ! defined( $name ) ) {
				define( $name, $value );
			}
		}

		/**
		 * Main LoyaltyDog Instance
		 * Ensures only one instance of LoyaltyDog is loaded or can be loaded.
		 *
		 * @since 1.0.0
		 *
		 * @param string $file The main plugin file
		 * @param string $version The LoyaltyDog version
		 *
		 * @return LoyaltyDog instance The LoyaltyDog instance
		 */
		public static function instance( $file = '', $version = '1.0.0' ) {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self( $file, $version );
			}

			return self::$_instance;
		}

		/**
		 * Cloning is forbidden.
		 * @since 1.0.0
		 */
		public function __clone() {
			_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ), $this->version );
		}

		/**
		 * Unserializing instances of this class is forbidden.
		 * @since 1.0.0
		 */
		public function __wakeup() {
			_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ), $this->version );
		}

		/**
		 * Installation. Runs on activation.
		 * @since 1.0.0
		 */
		public function install() {
			$this->_log_version_number();
			set_transient( 'loyalty_dog_welcome_screen_activation_redirect', true, 30 );
		}

		/**
		 * Log the plugin version number.
		 * @since 1.0.0
		 */
		private function _log_version_number() {
			update_option( 'loyalty_dog_version', $this->version );
		}
	}

endif;