<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Integrate with LoyaltyDog API.
 *
 * @class LoyaltyDog_API
 * @author kodeplusdev <kodeplusdev@gmail.com>
 * @version 1.1.3
 */
class LoyaltyDog_API {
	/**
	 * @since 1.0.0
	 * @var string LoyaltyDog API url.
	 */
	public $_apiUrl;

	/**
	 * @since 1.0.0
	 * @var string LoyaltyDog API key.
	 */
	public $_apiKey;

	/**
	 * @since 1.0.0
	 * @var string LoyaltyDog Program ID.
	 */
	public $_programId;

	/**
	 * LoyaltyDog_API constructor.
	 * @since 1.0.0
	 */
	function __construct() {
		// Get settings
		$this->_apiUrl    = get_option( 'loyalty_dog_api_url', '' );
		$this->_programId = get_option( 'loyalty_dog_program_id', '' );
		$this->_apiKey    = get_option( 'loyalty_dog_api_key', '' );

		LoyaltyDog_Logger::v( "API url: " . $this->_apiUrl );
		LoyaltyDog_Logger::v( "Program ID: " . $this->_programId );
		LoyaltyDog_Logger::v( "API key: " . $this->_apiKey );
	}

	/**
	 * Find a customer by email
	 *
	 * @since 1.0.0
	 *
	 * @param string $email The customer email
	 *
	 * @return stdClass|bool The simple customer details.
	 */
	public function find_customer_by_email( $email ) {
		LoyaltyDog_Logger::v( "Find customer by email ($email)" );

		if ( empty( $email ) ) {
			return false;
		}

		// Load from cache
		$customer = wp_cache_get( "loyalty_dog_find_customer_by_email_$email" );
		if ( $customer ) {
			LoyaltyDog_Logger::v( "Got customer from cache: " . print_r( $customer, true ) );

			return $customer;
		}

		$restClient = new LoyaltyDog_Rest_Client();
		$restClient->get( "{$this->_apiUrl}/loyalty/programs/{$this->_programId}/customers?email={$email}", array(
			"Authorization: $this->_apiKey"
		) );

		if ( ! $restClient->_error && $restClient->getStatusCode() === 200 ) {
			$customers = json_decode( $restClient->getContent() );
			if ( count( $customers ) ) {
				foreach ( $customers as $customer ) {
					if ( $customer->email == $email ) {
						$customer = apply_filters( 'loyalty_dog_find_customer_by_email', $customer );

						LoyaltyDog_Logger::v( "Got customer: " . print_r( $customer, true ) );

						// Save to cache
						wp_cache_set( "loyalty_dog_find_customer_by_email_{$email}", $customer, '', LOYALTY_DOG_CUSTOMER_DETAILS_CACHE );

						return $customer;
					}
				}
			}
		}

		LoyaltyDog_Logger::v( "Failure to find customer with email $email. Response: " . print_r( $restClient->getContent(), true ) );
		LoyaltyDog_Logger::e( "Failure to find customer with email $email. Response: " . print_r( $restClient->getContent(), true ) );

		return false;
	}

	/***
	 * Load Loyalty customer details.
	 *
	 * @since 1.0.0
	 *
	 * @param string $emailOrId The customer email.
	 *
	 * @return stdClass|bool The customer details.
	 */
	public function get_customer_details( $emailOrId ) {
		LoyaltyDog_Logger::v( "Start get customer detail: ($emailOrId)" );

		if ( empty( $emailOrId ) ) {
			return false;
		}
		
		$emailOrId = strtolower($emailOrId);
		
		// Load from cache
		$customer = wp_cache_get( "loyalty_dog_get_customer_details_$emailOrId" );
		if ( $customer ) {
			LoyaltyDog_Logger::v( "Got customer details from cache: " . print_r( $customer, true ) );

			return $customer;
		}

		if ( is_email( $emailOrId ) ) {
			$email = strtolower($emailOrId);

			$customer = $this->find_customer_by_email( $email );
			if ( ! $customer ) {
				LoyaltyDog_Logger::v( "Loyalty customer with email $email was not found!" );

				return false;
			}
			
			$customer_id = $customer->id;
		} else {
			$customer_id = $emailOrId;
		}

		return $this->get_customer_details_by_id( $customer_id );
	}
	
	/***
	 * Load Loyalty customer details by ID.
	 *
	 * @since 1.1.4
	 *
	 * @param integer $customer_id The customer id.
	 *
	 * @return stdClass|bool The customer details.
	 */
	public function get_customer_details_by_id( $customer_id ) {
		LoyaltyDog_Logger::v( "Start get customer detail by Id: ($customer_id)" );
		
		if ( empty( $customer_id ) ) {
			return false;
		}
		
		$restClient = new LoyaltyDog_Rest_Client();
		$restClient->get( "$this->_apiUrl/loyalty/programs/{$this->_programId}/customers/$customer_id", array(
			"Authorization: $this->_apiKey"
		) );

		if ( ! $restClient->_error && $restClient->getStatusCode() === 200 ) {
			$customer = json_decode( $restClient->getContent() );

			$customer = apply_filters( 'loyalty_dog_get_customer_details', $customer );

			LoyaltyDog_Logger::v( "Got customer: " . print_r( $customer, true ) );

			// Save to cache
			wp_cache_set( "loyalty_dog_get_customer_details_" . $customer->email, $customer, '', LOYALTY_DOG_CUSTOMER_DETAILS_CACHE );
			wp_cache_set( "loyalty_dog_get_customer_details_" . $customer->id, $customer, '', LOYALTY_DOG_CUSTOMER_DETAILS_CACHE );

			return $customer;
		} else {
			LoyaltyDog_Logger::v( "Failure to get customer detail for id $customer_id. Response: " . print_r( $restClient->getContent(), true ) );
			LoyaltyDog_Logger::e( "Failure to get customer detail for id $customer_id. Response: " . print_r( $restClient->getContent(), true ) );

			return false;
		}
	}

	/**
	 * Load offer details.
	 *
	 * @since 1.0.0
	 *
	 * @param string $offer_id The offer ID
	 *
	 * @return stdClass|bool The offer details
	 */
	public function get_offer_details( $offer_id ) {
		LoyaltyDog_Logger::v( "Start get offer details: ($this->_programId, $offer_id)" );

		// Load from cache
		$offer = wp_cache_get( "loyalty_dog_get_offer_details_{$this->_programId}_{$offer_id}" );
		if ( $offer ) {
			LoyaltyDog_Logger::v( "Got offer from cache: " . print_r( $offer, true ) );

			return $offer;
		}

		// TODO: load offer details
		$restClient = new LoyaltyDog_Rest_Client();
		$restClient->get( "$this->_apiUrl/loyalty/programs/$this->_programId/offers/$offer_id/card", array(
			"Authorization: $this->_apiKey"
		) );

		if ( ! $restClient->_error && $restClient->getStatusCode() === 200 ) {
			$card = json_decode( $restClient->getContent() );

			$offer     = new stdClass();
			$offer->id = $offer_id;

			// find product info
			foreach ( $card->coupon->primaryFields as $field ) {
				if ( $field->key === 'primary' ) {
					$offer->name = $field->value;
					break;
				}
			}

			// find offer price
			foreach ( $card->coupon->secondaryFields as $field ) {
				if ( $field->key === '$maxValue' ) {
					$offer->value    = $field->value;
					$offer->currency = $field->currencyCode;

					// get current offer type
					switch ( $field->numberStyle ) {
						case 'PKNumberStylePercent':
							$offer->type = 'percent';
							break;
						case 'PKNumberStyleDecimal':
						case 'PKNumberStyleScientific':
						default:
							$offer->type = 'fixed_cart';
							break;
					}
					break;
				}
			}

			// friendly name
			if ( empty( $offer->value ) ) {
				$offer->friendlyNameToBeAdded = $offer->friendlyName = LoyaltyDog_Manager::get_offer_message( __( '{name} (FREE)', 'loyaltydog' ), $offer );
			} else {
				$offer->friendlyName          = LoyaltyDog_Manager::get_offer_message( __( '{name} ({value}{currency})', 'loyaltydog' ), $offer );
				$offer->friendlyNameToBeAdded = LoyaltyDog_Manager::get_offer_message( __( '{name} (-{value}{currency})', 'loyaltydog' ), $offer );
			}

			$offer = apply_filters( 'loyalty_dog_get_offer_details', $offer );

			LoyaltyDog_Logger::v( "Got offer: " . print_r( $offer, true ) );

			// Save to cache
			wp_cache_set( "loyalty_dog_get_offer_details_{$this->_programId}_{$offer_id}", $offer, '', LOYALTY_DOG_OFFER_DETAILS_CACHE );

			return $offer;
		} else {
			LoyaltyDog_Logger::v( "Failure to get detail for offer #$offer_id. Response: " . print_r( $restClient->getContent(), true ) );
			LoyaltyDog_Logger::e( "Failure to get detail for offer #$offer_id. Response: " . print_r( $restClient->getContent(), true ) );

			return false;
		}
	}

	/**
	 * Update Loyalty point.
	 *
	 * @since 1.0.0
	 *
	 * @param string $customer_id The Customer ID.
	 * @param string $points The points to update.
	 *
	 * @return bool
	 */
	public function update_points( $customer_id, $points ) {
		LoyaltyDog_Logger::v( "Start update points: ($this->_programId, $customer_id, $points)" );

		$restClient = new LoyaltyDog_Rest_Client();
		$restClient->post( "$this->_apiUrl/loyalty/programs/$this->_programId/customers/$customer_id/points/add", array( 'points' => $points ), array(
			"Authorization: $this->_apiKey"
		) );

		if ( ! $restClient->_error && $restClient->getStatusCode() === 200 ) {
			LoyaltyDog_Logger::v( "Update point successful. Response: " . $restClient->getContent() );

			return true;
		} else {
			LoyaltyDog_Logger::v( "Failure to update point for customer #$customer_id. Response: " . print_r( $restClient->getContent(), true ) );
			LoyaltyDog_Logger::e( "Failure to update point for customer #$customer_id. Response: " . print_r( $restClient->getContent(), true ) );

			return false;
		}
	}

	/**
	 * Redeem offer of a customer.
	 *
	 * @since 1.0.0
	 *
	 * @param string $customer_id The Customer ID.
	 *
	 * @return bool
	 */
	public function redeem_offer( $customer_id ) {
		LoyaltyDog_Logger::v( "Start redeem offer: ($this->_programId, $customer_id)" );

		$restClient = new LoyaltyDog_Rest_Client();
		$restClient->post( "$this->_apiUrl/loyalty/programs/$this->_programId/customers/$customer_id/offers/current/redeem", array(), array(
			"Authorization: $this->_apiKey"
		) );

		if ( ! $restClient->_error && $restClient->getStatusCode() === 202 ) {
			LoyaltyDog_Logger::v( "Redeem successful. Response: " . $restClient->getContent() );

			return true;
		} else {
			LoyaltyDog_Logger::v( "Failure to redeem offer for customer #$customer_id. Response: " . print_r( $restClient->getContent(), true ) );
			LoyaltyDog_Logger::e( "Failure to redeem offer for customer #$customer_id. Response: " . print_r( $restClient->getContent(), true ) );

			return false;
		}
	}
	
	/**
	* Query all customers.
	*
	* @since 1.1.4
	*
	* @return array|bool
	*/
	public function get_all_customers() {
		LoyaltyDog_Logger::v( "Start get all customers: ($this->_programId)" );
		
		$restClient = new LoyaltyDog_Rest_Client();
		$restClient->get( "$this->_apiUrl/loyalty/programs/{$this->_programId}/customers", array(
			"Authorization: $this->_apiKey"
		) );

		if ( ! $restClient->_error && $restClient->getStatusCode() === 200 ) {
			$customers = json_decode( $restClient->getContent() );

			$customer = apply_filters( 'loyalty_dog_get_all_customers', $customers );

			LoyaltyDog_Logger::v( "Got " . count( $customers ) . " customers" );

			return $customers;
		} else {
			LoyaltyDog_Logger::v( "Failure to get all customers. Response: " . print_r( $restClient->getContent(), true ) );
			LoyaltyDog_Logger::e( "Failure to get all customers. Response: " . print_r( $restClient->getContent(), true ) );

			return false;
		}
	}
	
	/**
	* Update customer details.
	*
	* @since 1.1.4
	*
	* @param integer $customer_id The customer id.
	* @param array $details The customer details values.
	*
	* @return array|bool
	*/
	public function update_custom_details( $customer_id, $details ) {
		LoyaltyDog_Logger::v( "Start update customer details: ($this->_programId, $customer_id)" );
		
		if ( empty( $customer_id ) || empty( $details ) ) {
			return false;
		}
		
		$restClient = new LoyaltyDog_Rest_Client();
		$restClient->put( "$this->_apiUrl/loyalty/programs/{$this->_programId}/customers/{$customer_id}", $details, array(
			"Authorization: $this->_apiKey"
		) );
		
		if ( ! $restClient->_error && $restClient->getStatusCode() === 200 ) {
			$customer = json_decode( $restClient->getContent() );
			
			$customer = apply_filters( 'loyalty_dog_get_customer_details', $customer );

			LoyaltyDog_Logger::v( "Got customer: " . print_r( $customer, true ) );

			// Save to cache
			wp_cache_set( "loyalty_dog_get_customer_details_" . $customer->email, $customer, '', LOYALTY_DOG_CUSTOMER_DETAILS_CACHE );
			wp_cache_set( "loyalty_dog_get_customer_details_" . $customer->id, $customer, '', LOYALTY_DOG_CUSTOMER_DETAILS_CACHE );

			return $customer;
		} else {
			LoyaltyDog_Logger::v( "Failure to update customer details for id $customer_id. Response: " . print_r( $restClient->getContent(), true ) );
			LoyaltyDog_Logger::e( "Failure to update customer details for id $customer_id. Response: " . print_r( $restClient->getContent(), true ) );

			return false;
		}
	}
	
	/**
	* Send Push Notifications to all customers.
	*
	* @since 1.1.4
	*
	* @param string $message The message to send.
	*
	* @return bool
	*/
	public function push_notifications( $message ) {
		LoyaltyDog_Logger::v( "Start push notifications: ($this->_programId, $message)" );
		
		if ( empty( $message ) ) {
			return false;
		}
		
		$customers = $this->get_all_customers();
		
		if ( ! empty( $customers ) ) {
			foreach ($customers as $customer) {
				
				$customer = $this->get_customer_details( $customer->id );
				
				// check GDPR field
				if ( empty( $customer->customFields ) || empty( $customer->customFields->GDPR ) ||  $customer->customFields->GDPR == 'No' ) {
					$resp = $this->update_custom_details( $customer->id, array( 'currentMessage' => $message ) );
					
					if ( empty( $resp ) ) {
						LoyaltyDog_Logger::v( "Failure to send push notification to {$customer->id}" );
						LoyaltyDog_Logger::e( "Failure to send push notification to {$customer->id}" );
					}
				}
			}
			
			return true;
		}
		
		return false;
	}
}