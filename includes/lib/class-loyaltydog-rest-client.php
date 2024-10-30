<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helpful Rest client.
 *
 * @class RestClient
 * @author kodeplusdev <kodeplusdev@gmail.com>
 * @version 1.0.0
 */
class LoyaltyDog_Rest_Client {
	/**
	 * @since 1.0.0
	 * @var bool Submitted state.
	 */
	public $_submitted = false;

	/**
	 * @since 1.0.0
	 * @var array Header parameters.
	 */
	public $_headers = array();

	/**
	 * Error state.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	public $_error = false;

	/**
	 * @since 1.0.0
	 * @var string Raw body response.
	 */
	public $_body = '';

	/**
	 * Http Request to server use method GET.
	 *
	 * @since 1.0.0
	 *
	 * @param string $uri
	 * @param array $headers
	 * @param int $timeout
	 *
	 * @return $this
	 */
	public function get( $uri, $headers = array(), $timeout = 30 ) {
		if ( ! function_exists( 'curl_init' ) ) {
			$this->_error = true;
			$this->_body  = 'curl_init functions are not available.';
			LoyaltyDog_Logger::v( "curl_init functions are not available." );
		}

		LoyaltyDog_Logger::v( "GET $uri" );
		LoyaltyDog_Logger::v( "headers: " . print_r( $headers, true ) );

		$ch = curl_init( $uri );

		curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "GET" );
		curl_setopt( $ch, CURLOPT_TIMEOUT, $timeout );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );

		if ( is_array( $headers ) && count( $headers ) > 0 ) {
			curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
		}

		if ( curl_errno( $ch ) ) {
			$this->_error = true;
			$this->_body  = 'Error: "' . curl_error( $ch ) . '" - Code: ' . curl_errno( $ch );
			LoyaltyDog_Logger::v( $this->_body );
		}

		$this->_submitted = true;
		if ( ! $this->_body = curl_exec( $ch ) ) {
			$this->_error = true;
			$this->_body  = 'Error: "' . curl_error( $ch ) . '" - Code: ' . curl_errno( $ch );
			LoyaltyDog_Logger::v( $this->_body );
		}

		$this->_headers = curl_getinfo( $ch );
		curl_close( $ch );

		return $this;
	}

	/**
	 * Http Request to server use method POST.
	 *
	 * @since 1.0.0
	 *
	 * @param string $uri
	 * @param array $payload
	 * @param array $headers
	 * @param int $timeout
	 * @param string $method POST|PUT
	 *
	 * @return LoyaltyDog_Rest_Client
	 */
	public function post( $uri, $payload, $headers = array(), $timeout = 30, $method = "POST" ) {
		if ( ! function_exists( 'curl_init' ) ) {
			$this->_error = true;
			$this->_body  = 'curl_init functions are not available.';
			LoyaltyDog_Logger::v( "curl_init functions are not available." );
		}

		LoyaltyDog_Logger::v( "$method $uri" );
		LoyaltyDog_Logger::v( "headers: " . print_r( $headers, true ) );
		LoyaltyDog_Logger::v( "payload: " . print_r( $payload, true ) );

		$ch = curl_init( $uri );

		curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $method );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $payload ) );
		curl_setopt( $ch, CURLOPT_TIMEOUT, $timeout );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );

		if ( is_array( $headers ) && count( $headers ) > 0 ) {
			curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
		}

		if ( curl_errno( $ch ) ) {
			$this->_error = true;
			$this->_body  = 'Error: "' . curl_error( $ch ) . '" - Code: ' . curl_errno( $ch );
			LoyaltyDog_Logger::v( $this->_body );
		}

		$this->_submitted = true;
		if ( ! $this->_body = curl_exec( $ch ) ) {
			$this->_error = true;
			$this->_body  = 'Error: "' . curl_error( $ch ) . '" - Code: ' . curl_errno( $ch );
			LoyaltyDog_Logger::v( $this->_body );
		}
		$this->_headers = curl_getinfo( $ch );
		curl_close( $ch );

		return $this;
	}
	
	/**
	 * Http Request to server use method PUT.
	 *
	 * @since 1.1.4
	 *
	 * @param string $uri
	 * @param array $payload
	 * @param array $headers
	 * @param int $timeout
	 *
	 * @return LoyaltyDog_Rest_Client
	 */
	public function put( $uri, $payload, $headers = array(), $timeout = 30 ) {
		return $this->post($uri, $payload, $headers, $timeout, "PUT");
	}

	/**
	 * Get status text from response.
	 *
	 * @since 1.0.0
	 * @return int|string
	 */
	public function getStatusText() {
		if ( $this->_submitted ) {
			return $this->getStatusCode();
		}

		return 'N/A';
	}

	/**
	 * Get status code from response.
	 *
	 * @since 1.0.0
	 * @return int|string
	 */
	public function getStatusCode() {
		if ( $this->_submitted ) {
			return $this->getHeader( 'http_code' );
		}

		return 0;
	}

	/**
	 * Get Header from Response.
	 *
	 * @since 1.0.0
	 *
	 * @param $index
	 *
	 * @return string
	 */
	public function getHeader( $index ) {
		if ( isset( $this->_headers[ $index ] ) ) {
			return $this->_headers[ $index ];
		}

		return 'N/A';
	}

	/**
	 * Get content from response.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function getContent() {
		return $this->_body;
	}

	/**
	 * Get Header from response.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function getHeaders() {
		return $this->_headers;
	}

	/**
	 * Get time from response.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function getTime() {
		return $this->getHeader( 'total_time' );
	}
}