<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load/save/display admin notices
 * Class LoyaltyDog_Notices
 * @author kodeplusdev <kodeplusdev@gmail.com>
 * @version 1.0.1
 */
class LoyaltyDog_Notices {
	/**
	 * @since 1.0.1
	 * @var array Error messages.
	 */
	private static $errors = array();

	/**
	 * @since 1.0.1
	 * @var array Update messages.
	 */
	private static $messages = array();

	/**
	 * Constructor.
	 * @since 1.0.1
	 */
	public static function init() {
		add_action( 'shutdown', array( __CLASS__, 'store_notices' ) );
	}

	/**
	 * Add a message.
	 *
	 * @since 1.0.1
	 *
	 * @param string $text
	 */
	public static function add_message( $text ) {
		self::$messages[] = $text;
	}

	/**
	 * Add an error.
	 *
	 * @since 1.0.1
	 *
	 * @param string $text
	 */
	public static function add_error( $text ) {
		self::$errors[] = $text;
	}

	/**
	 * Save notices to db, so we can display them after the page refreshed.
	 * @since 1.0.0
	 */
	public static function store_notices() {
		if ( sizeof( self::$errors ) > 0 || sizeof( self::$messages ) > 0 ) {
			$notices = apply_filters( 'loyalty_dog_save_admin_notices', array(
				'errors'   => self::$errors,
				'messages' => self::$messages
			) );
			update_option( 'loyalty_dog_notices', $notices );
		}
	}

	/**
	 * Load and display admin notices.
	 * @since 1.0.0
	 */
	public static function show_admin_notices() {
		$notices = maybe_unserialize( get_option( 'loyalty_dog_notices' ) );
		$notices = apply_filters( 'loyalty_dog_load_admin_notices', $notices );

		if ( ! empty( $notices ) ) {

			if ( sizeof( $notices['errors'] ) > 0 ) {
				foreach ( $notices['errors'] as $error ) {
					echo '<div id="message" class="error inline"><p><strong>' . esc_html( $error ) . '</strong></p></div>';
				}
			} elseif ( sizeof( $notices['messages'] ) > 0 ) {
				foreach ( $notices['messages'] as $message ) {
					echo '<div id="message" class="updated inline"><p><strong>' . esc_html( $message ) . '</strong></p></div>';
				}
			}

			// Delete option after all notice displayed
			delete_option( 'loyalty_dog_notices' );
		}
	}
}

LoyaltyDog_Notices::init();