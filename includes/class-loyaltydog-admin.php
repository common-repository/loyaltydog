<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load/saves admin settings
 *
 * @class LoyaltyDog_Admin
 * @author kodeplusdev <kodeplusdev@gmail.com>
 * @version 1.0.0
 */
class LoyaltyDog_Admin {
	/**
	 * @since 1.0.0
	 * @var string Settings page ID
	 */
	private $page_id;

	/**
	 * @since 1.0.0
	 * @var array Management tabs
	 */
	private $tabs;

	/**
	 * LoyaltyDog_Admin constructor.
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->tabs = array(
			'news'          => __( 'News', 'loyaltydog' ),
			'manage'        => __( 'Manage', 'loyaltydog' ),
			/* 'push_notifications'	=> __( 'Push Notifications', 'loyaltydog' ), */
			'settings'      => __( 'Settings', 'loyaltydog' ),
			'documentation' => __( 'Support', 'loyaltydog' ),
		);

		// Load WC styles / scripts
		add_filter( 'woocommerce_screen_ids', array( $this, 'load_wc_scripts' ) );

		// add 'LoyaltyDog' link under WooCommerce menu
		add_action( 'admin_menu', array( $this, 'add_menu_link' ) );

		// warn that points won't be able to be redeemed if coupons are disabled
		add_action( 'admin_notices', array( $this, 'verify_coupons_enabled' ) );
		// Check for valid settings
		add_action( 'admin_notices', array( $this, 'check_settings' ) );

		// sanitize and validate settings
		add_filter( 'woocommerce_admin_settings_sanitize_option', array( $this, 'sanitize_settings' ), 10, 3 );

		// save settings
		add_action( 'admin_post_save_loyalty_dog_settings', array( $this, 'save_settings' ) );
		
		// push Notifications
		add_action( 'admin_post_push_loyalty_dog_notifications', array( $this, 'push_notifications' ) );

		// Add a custom field types
		add_action( 'woocommerce_admin_field_point_conversion_ratio', array(
			$this,
			'render_point_conversion_ratio_field'
		) );

		// save custom field types
		add_action( 'init', array( $this, 'save_custom_field_types' ) );
	}

	/**
	 * Check valid settings and display admin notices
	 * @since 1.0.2
	 */
	public function check_settings() {
		$apiUrl    = LOYALTY_DOG_DEFAULT_API_URL;
		$programId = get_option( 'loyalty_dog_program_id', '' );
		$apiKey    = get_option( 'loyalty_dog_api_key', '' );
		if ( empty( $apiUrl ) || empty( $programId ) || empty( $apiKey ) ) {
			$message = sprintf( __( '<strong>LoyaltyDog</strong> is almost ready. To get started, %sset your LoyaltyDog account keys%s.', 'loyaltydog' ),
				'<a href="' . admin_url( 'admin.php?page=loyaltydog&tab=settings' ) . '">',
				'</a>'
			);
			echo '<div class="notice notice-warning"><p>' . $message . '</p></div>';
		}
	}

	/**
	 * Add settings/export screen ID to the list of pages for WC to load its JS on
	 *
	 * @since 1.0.0
	 *
	 * @param array $screen_ids The screen ids
	 *
	 * @return array The screen ids
	 */
	public function load_wc_scripts( $screen_ids ) {
		$wc_screen_id = sanitize_title( __( 'WooCommerce', 'woocommerce' ) );

		// sub-menu page
		$screen_ids[] = $wc_screen_id . '_page_loyaltydog';

		return $screen_ids;
	}

	/**
	 * Add 'LoyaltyDog' sub-menu link under 'WooCommerce' top level menu
	 * @since 1.0.0
	 */
	public function add_menu_link() {
		$this->page_id = add_submenu_page(
			'woocommerce',
			__( 'LoyaltyDog', 'loyaltydog' ),
			__( 'LoyaltyDog', 'loyaltydog' ),
			'manage_woocommerce',
			'loyaltydog',
			array( $this, 'show_sub_menu_page' )
		);
	}

	/**
	 * Verify that coupons are enabled and render an annoying warning in the admin if they are not
	 * @since 1.0.0
	 */
	public function verify_coupons_enabled() {
		$coupons_enabled = get_option( 'woocommerce_enable_coupons' ) == 'no' ? false : true;

		if ( ! $coupons_enabled ) {
			$message = sprintf(
				__( '<strong>LoyaltyDog</strong> requires coupons to be %senabled%s in order to function properly and allow customers to redeem offer during checkout.', 'loyaltydog' ),
				'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">',
				'</a>'
			);

			echo '<div class="error"><p>' . $message . '</p></div>';
		}
	}

	/**
	 * Show LoyaltyDog page content
	 * @since 1.0.0
	 */
	public function show_sub_menu_page() {
		$current_tab = ( empty( $_GET['tab'] ) ) ? 'news' : urldecode( $_GET['tab'] ); ?>

        <div class="wrap woocommerce">
            <div id="icon-woocommerce" class="icon32-woocommerce-users icon32"><br/></div>
            <h2 class="nav-tab-wrapper woo-nav-tab-wrapper">
				<?php
				$tabs = apply_filters( 'loyalty_dog_get_sub_tabs', $this->tabs );

				// display tabs
				// Normally, this would be efficient as a foreach, but two of the tabs should just open new views to scanning and support portals
				//foreach ( $tabs as $tab_id => $tab_title ) {
				// manage, news, settings, documentation
					$tab_id = "news";
					$class = ( $tab_id == $current_tab ) ? 'nav-tab nav-tab-active' : 'nav-tab';
					$url   = add_query_arg( 'tab', $tab_id, admin_url( 'admin.php?page=loyaltydog' ) );
					printf( '<a href="%s" class="%s">%s</a>', $url, $class, "News" );

					$tab_id = "manage";
					$class = ( $tab_id == $current_tab ) ? 'nav-tab nav-tab-active' : 'nav-tab';
					$url   = add_query_arg( 'tab', $tab_id, admin_url( 'admin.php?page=loyaltydog' ) );
					printf( '<a href="%s" class="%s" target="_blank">%s</a>', LOYALTY_DOG_PORTAL_URL, $class, "Manage" );

					$tab_id = "settings";
					$class = ( $tab_id == $current_tab ) ? 'nav-tab nav-tab-active' : 'nav-tab';
					$url   = add_query_arg( 'tab', $tab_id, admin_url( 'admin.php?page=loyaltydog' ) );
					printf( '<a href="%s" class="%s">%s</a>', $url, $class, "Settings" );

					$tab_id = "documentation";
					$class = ( $tab_id == $current_tab ) ? 'nav-tab nav-tab-active' : 'nav-tab';
					$url   = add_query_arg( 'tab', $tab_id, admin_url( 'admin.php?page=loyaltydog' ) );
					printf( '<a href="%s" class="%s" target="_blank">%s</a>', LOYALTY_DOG_SUPPORT_URL, $class, "Support" );
				//}

				?> </h2> <?php

			LoyaltyDog_Notices::show_admin_notices();

			// display tab content, default to 'Manage' tab
			switch ( $current_tab ) {
				case 'settings':
					$this->show_settings_tab();
					break;
				case 'manage':
					$this->show_manage_tab();
					break;
				case 'documentation':
					$this->show_documentation_tab();
					break;
				case 'news':
					$this->show_news_tab();
					break;
				case 'push_notifications':
					$this->show_push_notifications_tab();
					break;
				default:
					do_action( 'loyalty_dog_show' . $current_tab . '_tab' );
					break;
			}
			?></div> <?php
	}

	/**
	 * Show the LoyaltyDog > Manage tab content
	 * @since 1.0.0
	 */
	private function show_manage_tab() {
		?>
        <h2><a href="https://scan.loyalty.dog" target="_blank" style="padding: 20px;">Click here to open a new tab to the Management Portal</a></h2>
		<?php
	}

	/**
	 * Show the LoyaltyDog > Settings tab content
	 * @since 1.0.0
	 */
	private function show_settings_tab() {
		?>
        <form method="post" action="admin-post.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="save_loyalty_dog_settings"/>
			<?php
			wp_nonce_field( 'loyalty-dog-save-settings-verify' );
			woocommerce_admin_fields( $this->get_settings() );
			?>
            <input type="submit" class="button-primary" value="<?php esc_attr_e( 'Save Changes', 'woocommerce' ) ?>"/>
        </form>
		<?php
	}

	/**
	 * Render the news tab
	 * @since 1.0.8
	 */
	private function show_news_tab() {
		?>
        <span id="loyalty-dog-docs-iframe-loading"><?php echo __( 'Loading...', 'loyaltydog' ); ?></span>
        <iframe id="loyalty-dog-docs-iframe" frameborder="0" width="100%" height="0" style="overflow-y: hidden"
                src="<?php echo LOYALTY_DOG_PLUGIN_URL . '/assets/docs-en/index.html#loyalty-dog-docs-iframe'; ?>"></iframe>
		<?php
		enqueue_iframe_helper();

		wp_enqueue_style( 'fancyBox', LOYALTY_DOG_PLUGIN_URL . '/assets/docs-en/assets/fancyBox/dist/jquery.fancybox.min.css', [], '3.0.0' );
		wp_add_inline_style( 'fancyBox', '
		    .fancybox-container {
		        top: 32px;
		     }
		' );
		wp_enqueue_script( 'fancyBox', LOYALTY_DOG_PLUGIN_URL . '/assets/docs-en/assets/fancyBox/dist/jquery.fancybox.min.js', [ 'jquery' ], '3.0.0' );
	}

	/**
	 * Render the news tab.
	 * @since 1.0.0
	 */
	private function show_documentation_tab() {
		// FIXME
		?>
		<?php readfile(LOYALTY_DOG_PLUGIN_URL . "/assets/misc/docs.html"); ?>
		<?php
	}
	
	/**
	* Render the push notification tab.
	*
	* @since 1.1.4
	*/
	private function show_push_notifications_tab() {
		?>
		<form method="POST" action="admin-post.php" enctype="multipart/form-data">
		   <input type="hidden" name="action" value="push_loyalty_dog_notifications" />
		   <?php
		   wp_nonce_field( 'loyalty-dog-push-notificatins-verify' );
		   woocommerce_admin_fields( array(
			   array(
	   				'title' => __( 'Send Push Notifications', 'loyaltydog' ),
	   				'type'  => 'title',
	   				'id'    => 'loyalty_dog_push_notifications_start'
	   			),

	   			array(
	   				'title'    => __( 'Message', 'loyaltydog' ),
	   				'type'     => 'text',
	   				'css'      => 'min-width: 400px;',
	   				'desc_tip' => __( 'Push notifications are compliant with EU GDPR.', 'loyaltydog' ),
	   				'id'       => 'loyalty_dog_message',
					'type'     => 'textarea',
					'custom_attributes' => array(
						'rows' => 3
					)
	   			),
				
				array( 'type' => 'sectionend', 'id' => 'loyalty_dog_push_notifications_end' ),
		   ) );
		   ?>
		   <input type="submit" class="button-primary" value="<?php esc_attr_e( 'Send', 'loyaltydog' ) ?>"/>
	   </form>
		<?php
	}

	/**
	 * Returns settings array for use by render/save/install default settings methods
	 *
	 * @since 1.0.0
	 * @return array settings Arrray of LoyaltyDog settings
	 */
	public static function get_settings() {
		$label = __( 'Enable the logging of API request.', 'loyaltydog' );

		if ( defined( 'WC_LOG_DIR' ) ) {
			$log_url = add_query_arg( 'tab', 'logs', add_query_arg( 'page', 'wc-status', admin_url( 'admin.php' ) ) );
			$log_key = 'loyaltydog-logs-' . sanitize_file_name( wp_hash( 'loyaltydog-logs' ) ) . '.log';
			$log_url = add_query_arg( 'log_file', $log_key, $log_url );

			$label .= ' | ' . sprintf( __( '%1$sView Log%2$s', 'loyaltydog' ), '<a href="' . esc_url( $log_url ) . '">', '</a>' );
		}

		$settings = array(

			array(
				'title' => __( 'LoyaltyDog API', 'loyaltydog' ),
				'type'  => 'title',
				'id'    => 'loyalty_dog_api_settings_start'
			),

			/* API URL is hidden since it shouldn't be changed by the user and is defined in main plugin file loyaltydog.php */
			/* array(
				'title'    => __( 'API URL', 'loyaltydog' ),
				'type'     => 'text',
				'css'      => 'min-width: 400px; ',
				'desc_tip' => __( 'Enter with your LoyaltyDog API url.', 'loyaltydog' ),
				'default'  => LOYALTY_DOG_DEFAULT_API_URL,
				'id'       => 'loyalty_dog_api_url'
			), */

			array(
				'title'    => __( 'Program ID', 'loyaltydog' ),
				'type'     => 'text',
				'css'      => 'min-width: 400px;',
				'desc_tip' => __( 'Enter with your program ID.', 'loyaltydog' ),
				'default'  => '',
				'id'       => 'loyalty_dog_program_id'
			),

			array(
				'title'    => __( 'API Key', 'loyaltydog' ),
				'type'     => 'text',
				'css'      => 'min-width: 400px;',
				'desc_tip' => __( 'Enter with your LoyaltyDog API key.', 'loyaltydog' ),
				'default'  => '',
				'id'       => 'loyalty_dog_api_key'
			),

			array(
				'title'    => __( 'Debug Log', 'loyaltydog' ),
				'desc'     => $label,
				'desc_tip' => __( 'This should be checked only if LoyaltyDog support suggests it to be checked.', 'loyaltydog' ),
				'type'     => 'checkbox',
				'default'  => 'no',
				'id'       => 'loyalty_dog_debug'
			),

			array( 'type' => 'sectionend', 'id' => 'loyalty_dog_api_settings_end' ),

			array(
				'title' => __( 'Points Conversion', 'loyaltydog' ),
				'type'  => 'title',
				'id'    => 'loyalty_dog_points_settings_start'
			),

			// order status which we run earning points and redeeming a reward
			array(
				'title'    => __( 'Earn Points When Order Status Is', 'loyaltydog' ),
				'desc_tip' => __( 'Behavior how points are earned and rewards are redeemed.', 'loyaltydog' ),
				'id'       => 'loyalty_dog_earn_points_when',
				'default'  => 'processing',
				'options'  => array(
					'pending'    => _x( 'Pending Payment', 'Order status', 'loyaltydog' ),
					'processing' => _x( 'Processing', 'Order status', 'loyaltydog' ),
					'on-hold'    => _x( 'On Hold', 'Order status', 'loyaltydog' ),
					'completed'  => _x( 'Completed', 'Order status', 'loyaltydog' ),
				),
				'type'     => 'select'
			),

			// earn points conversion
			array(
				'title'    => __( 'Earn Points Conversion Rate', 'loyaltydog' ),
				'desc_tip' => __( 'Set the number of points awarded based on the product price.', 'loyaltydog' ),
				'id'       => 'loyalty_dog_earn_points_ratio',
				'default'  => '1:1',
				'type'     => 'point_conversion_ratio'
			),

			// earn points rouding mode
			array(
				'title'    => __( 'Earn Points Rounding Mode', 'loyaltydog' ),
				'desc_tip' => __( 'Set how points should be rounded.', 'loyaltydog' ),
				'id'       => 'loyalty_dog_earn_points_rounding',
				'default'  => 'round',
				'options'  => array(
					'round' => __( 'Round to nearest integer', 'loyaltydog' ),
					'floor' => __( 'Always round down', 'loyaltydog' ),
					'ceil'  => __( 'Always round up', 'loyaltydog' ),
				),
				'type'     => 'select'
			),

			array( 'type' => 'sectionend', 'id' => 'loyalty_dog_points_settings_end' ),

			array(
				'title' => __( 'Offer Messages', 'loyaltydog' ),
				'desc'  => sprintf( __( 'Adjust the message by using %1$s{friendlyNameToBeAdded}%2$s, %1$s{friendlyName}%2$s, %1$s{name}%2$s, %1$s{value}%2$s, %1$s{currency}%2$s to represent the redeemed / available offer.', 'loyaltydog' ), '<code>', '</code>' ),
				'type'  => 'title',
				'id'    => 'loyalty_dog_messages_start'
			),

			// available offer message
			array(
				'title'             => __( 'Available Offer Message', 'loyaltydog' ),
				'desc_tip'          => __( 'Add an optional message when user have a active Loyalty offer. Limited HTML is allowed. Leave blank to disable.', 'loyaltydog' ),
				'id'                => 'loyalty_dog_available_offer_message',
				'css'               => 'min-width: 400px;',
				'default'           => __( '<strong>Active Loyalty Offer:</strong> {friendlyNameToBeAdded}.<br />You can add some products to redeem this offer.', 'loyaltydog' ),
				'type'              => 'textarea',
				'custom_attributes' => array(
					'rows' => 5
				)
			),

			// redeem offer message
			array(
				'title'             => __( 'Redeem Offer Message', 'loyaltydog' ),
				'desc_tip'          => __( 'Displayed on the cart and checkout page when cart is not empty and user have active offer. Limited HTML is allowed.', 'loyaltydog' ),
				'id'                => 'loyalty_dog_redeem_offer_message',
				'css'               => 'min-width: 400px;',
				'default'           => __( '<strong>Loyalty Reward:</strong> {friendlyNameToBeAdded}', 'loyaltydog' ),
				'type'              => 'textarea',
				'custom_attributes' => array(
					'rows' => 5
				)
			),

			array( 'type' => 'sectionend', 'id' => 'loyalty_dog_messages_end' ),

			array(
				'title' => __( 'Points Earned for Actions', 'loyaltydog' ),
				'desc'  => __( 'Customers can also earn points for actions like creating an account or writing a product review. You can enter the amount of points the customer will earn for each action in this section.', 'loyaltydog' ),
				'type'  => 'title',
				'id'    => 'loyalty_dog_earn_points_for_actions_settings_start'
			),

			array( 'type' => 'sectionend', 'id' => 'loyalty_dog_earn_points_for_actions_settings_end' ),
		);

		$integration_settings = apply_filters( 'loyalty_dog_action_settings', array() );

		if ( $integration_settings ) {

			// set defaults
			foreach ( array_keys( $integration_settings ) as $key ) {
				if ( ! isset( $integration_settings[ $key ]['css'] ) ) {
					$integration_settings[ $key ]['css'] = 'max-width: 50px;';
				}
				if ( ! isset( $integration_settings[ $key ]['type'] ) ) {
					$integration_settings[ $key ]['type'] = 'text';
				}
			}

			// find the start of the Points Earned for Actions settings to splice into
			$index = - 1;
			foreach ( $settings as $index => $setting ) {
				if ( isset( $setting['id'] ) && 'loyalty_dog_earn_points_for_actions_settings_start' == $setting['id'] ) {
					break;
				}
			}

			array_splice( $settings, $index + 1, 0, $integration_settings );
		}

		return apply_filters( 'loyalty_dog_settings', $settings );
	}

	/**
	 * Sanitize values
	 * @since 1.0.1
	 *
	 * @inheritdoc
	 */
	public function sanitize_settings( $value, $option, $raw_value ) {

		switch ( $option['id'] ) {
			case 'loyalty_dog_api_url':
				if ( ! empty( $value ) && filter_var( $value, FILTER_VALIDATE_URL ) === false ) {
					LoyaltyDog_Notices::add_error( sprintf( __( '%s is not a valid url.', 'loyaltydog' ), $value ) );
					break;
				}
			case 'loyalty_dog_program_id':
			case 'loyalty_dog_api_key':
				if ( empty( $value ) ) {
					LoyaltyDog_Notices::add_error( sprintf( __( '%s is required.', 'loyaltydog' ), $option['title'] ) );
				}
				break;
			default:
				break;
		}

		return $value;
	}

	/**
	 * Save the 'LoyaltyDog' settings page
	 * @since 1.0.0
	 */
	public function save_settings() {
		check_admin_referer( 'loyalty-dog-save-settings-verify' );
		woocommerce_update_options( $this->get_settings() );
		wp_redirect( admin_url( 'admin.php?page=loyaltydog&tab=settings' ) );
		exit;
	}

	/**
	 * Filters the save custom field type functions so they get sanitized correctly
	 * @since 1.0.0
	 */
	public function save_custom_field_types() {
		if ( is_min_wc_version( '2.4.0' ) ) {
			add_filter( 'woocommerce_admin_settings_sanitize_option_loyalty_dog_earn_points_ratio', array(
				$this,
				'save_point_conversion_ratio_field'
			), 10, 3 );
		} else {
			add_action( 'woocommerce_update_option_point_conversion_ratio', array(
				$this,
				'_deprecated_save_point_conversion_ratio_field'
			) );
		}
	}

	/**
	 * Render the Earn Points conversion ratio section
	 *
	 * @since 1.0.0
	 *
	 * @param array $field associative array of field parameters
	 */
	public function render_point_conversion_ratio_field( $field ) {
		if ( isset( $field['title'] ) && isset( $field['id'] ) ) :

			$ratio = get_option( $field['id'], $field['default'] );

			list( $points, $monetary_value ) = explode( ':', $ratio );

			?>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for=""><?php echo wp_kses_post( $field['title'] ); ?></label>
                    <span class="woocommerce-help-tip" data-tip="<?php echo esc_attr( $field['desc_tip'] ); ?>"></span>
                </th>
                <td class="forminp forminp-text">
                    <fieldset>
                        <input name="<?php echo esc_attr( $field['id'] . '_points' ); ?>"
                               id="<?php echo esc_attr( $field['id'] . '_points' ); ?>" type="text"
                               style="max-width: 50px;"
                               value="<?php echo esc_attr( $points ); ?>"/>&nbsp;<?php _e( 'Points', 'loyaltydog' ); ?>
                        <span>&nbsp;&#61;&nbsp;</span>&nbsp;<?php echo get_woocommerce_currency_symbol(); ?>
                        <input name="<?php echo esc_attr( $field['id'] . '_monetary_value' ); ?>"
                               id="<?php echo esc_attr( $field['id'] . '_monetary_value' ); ?>" type="text"
                               style="max-width: 50px;" value="<?php echo esc_attr( $monetary_value ); ?>"/>
                    </fieldset>
                </td>
            </tr>
			<?php

		endif;
	}

	/**
	 * Save the Earn Points Conversion Ratio field
	 *
	 * @since 1.0.0
	 *
	 * @param $value
	 * @param $option
	 * @param $raw_value
	 *
	 * @return string
	 * @internal param array $field
	 */
	public function save_point_conversion_ratio_field( $value, $option, $raw_value ) {

		if ( isset( $_POST[ $option['id'] . '_points' ] ) && ! empty( $_POST[ $option['id'] . '_monetary_value' ] ) ) {
			$points         = wc_clean( $_POST[ $option['id'] . '_points' ] );
			$monetary_value = wc_clean( $_POST[ $option['id'] . '_monetary_value' ] );

			if ( empty( $points ) || empty( $monetary_value ) || ! is_numeric( $points ) || ! is_numeric( $monetary_value ) ) {
				LoyaltyDog_Notices::add_error( sprintf( __( '%s is invalid.', 'loyaltydog' ), $option['title'] ) );
			} else {
				return $points . ':' . $monetary_value;
			}
		}
	}

	/**
	 * Backward compatible function to deal with deprecated actions in 2.4
	 *
	 * @since 1.0.0
	 *
	 * @param $option
	 */
	public function _deprecated_save_point_conversion_ratio_field( $option ) {
		$value = $this->save_point_conversion_ratio_field( null, $option, null );
		update_option( $option['id'], $value );
	}
	
	/**
	* Send Push notifications to all customers.
	* Push notifications are compliant with EU GDPR.
	*
	* @since 1.1.4
	*/
	public function push_notifications() {
		check_admin_referer( 'loyalty-dog-push-notificatins-verify' );
		
		$message = $_POST['loyalty_dog_message'];
		
		if ( empty( $message ) ) {
			LoyaltyDog_Notices::add_error( __( 'Message can not be blank.', 'loyaltydog' ) );
		} else {
			$api = new LoyaltyDog_API();
			if ( $api->push_notifications( $message ) ) {
				LoyaltyDog_Notices::add_message( __( 'Message have been sent.', 'loyaltydog' ) );
			}
		}
		
		wp_redirect( admin_url( 'admin.php?page=loyaltydog&tab=push_notifications' ) );
		exit;
	}
}
