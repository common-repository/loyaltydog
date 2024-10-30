<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * LoyaltyDog welcome screen.
 *
 * @class LoyaltyDog_Welcome
 * @author kodeplusdev <kodeplusdev@gmail.com>
 * @version 1.0.8
 */
class LoyaltyDog_Welcome {

	/**
	 * LoyaltyDog_Welcome constructor.
	 * @since 1.0.8
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'welcome_screen_do_activation_redirect' ) );
		add_action( 'admin_menu', array( $this, 'welcome_screen_pages' ) );
		add_action( 'admin_head', array( $this, 'welcome_screen_remove_menus' ) );
	}

	/**
	 * Check and display welcome screen
	 * @since 1.0.8
	 */
	public function welcome_screen_do_activation_redirect() {
		if ( ! get_transient( 'loyalty_dog_welcome_screen_activation_redirect' ) ) {
			return;
		}

		delete_transient( 'loyalty_dog_welcome_screen_activation_redirect' );

		if ( is_network_admin() || isset( $_GET['activate-multi'] ) ) {
			return;
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'loyaltydog-welcome' ), admin_url( 'index.php' ) ) );

	}

	/**
	 * Add welcome page.
	 * @since 1.0.8
	 */
	public function welcome_screen_pages() {
		add_dashboard_page(
			sprintf( __( 'Welcome to %1$s %2$s', 'loyaltydog' ), __( 'LoyaltyDog', 'loyaltydog' ), LOYALTY_DOG_VERSION ),
			sprintf( __( 'Welcome to %1$s %2$s', 'loyaltydog' ), __( 'LoyaltyDog', 'loyaltydog' ), LOYALTY_DOG_VERSION ),
			'read',
			'loyaltydog-welcome',
			array( $this, 'welcome_screen_content' )
		);
	}

	/**
	 * Remove welcome menu out of admin menu
	 * @since 1.0.8
	 */
	function welcome_screen_remove_menus() {
		remove_submenu_page( 'index.php', 'loyaltydog-welcome' );
	}

	/**
	 * Render welcome screen
	 * @since 1.0.8
	 */
	public function welcome_screen_content() {
		?>
        <div class="wrap woocommerce">
            <h2></h2>
            <span id="loyalty-dog-welcome-iframe-loading"><?php echo __( 'Loading...', 'loyaltydog' ); ?></span>
            <iframe id="loyalty-dog-welcome-iframe" frameborder="0" width="100%" height="0" style="overflow-y: hidden"
                    src="<?php echo LOYALTY_DOG_PLUGIN_URL . '/assets/welcome/index.html#loyalty-dog-welcome-iframe'; ?>"></iframe>
        </div>
		<?php
		enqueue_iframe_helper();
	}
}