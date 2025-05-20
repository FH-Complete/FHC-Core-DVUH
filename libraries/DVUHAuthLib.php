<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');


/**
 * Library to Connect to DVUH Services
 */
class DVUHAuthLib
{
	private $_ci; // Code igniter instance
	private $authentication; // object containing access token

	const OAUTH_TOKEN_URL = '/dvb/oauth/token';
	const AUTHORIZATION_HEADER_PREFIX = 'Basic';
	const TOKEN_EXPIRATION_OFFSET = 3; // offset to make token expire earlier to avoid errors

	/**
	 * Object initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

		$this->_ci->config->load('extensions/FHC-Core-DVUH/DVUHClient');
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * get OAuth Token
	 */
	public function getToken()
	{
		if ($this->_tokenIsExpired())
		{
			$this->_authenticate();
		}

		if (isset($this->authentication->access_token))
			return $this->authentication->access_token;

		return null;
	}

	/**
	 * Checks if the Token is Expired
	 * @return boolean true if expired, false if valid.
	 */
	private function _tokenIsExpired()
	{
		if (!isset($this->authentication))
			return true;

		$dtnow = new DateTime();
		if ($this->authentication->DateTimeExpires < $dtnow)
			return true;
		else
			return false;
	}

	/**
	 * Retrieves active connection from config.
	 * @return object the connection
	 */
	private function _getConnection()
	{
		$activeConnectionName = $this->_ci->config->item(ClientLib::ACTIVE_CONNECTION);
		$connectionsArray = $this->_ci->config->item(ClientLib::CONNECTIONS);
		return $connectionsArray[$activeConnectionName];
	}

	/**
	 * Retrieves token service url needed for authentication.
	 * @return string
	 */
	private function _getTokenServiceURL()
	{
		$conn = $this->_getConnection();
		return $conn['portal'].self::OAUTH_TOKEN_URL.'?grant_type=client_credentials';
	}

	/**
	 * Performs a remote web service authentication.
	 */
	private function _authenticate()
	{
		$curl = curl_init();

		$url = $this->_getTokenServiceURL();
		$conn = $this->_getConnection();

		$response = \Httpful\Request::post($url)
			->expectsJson() // dangerous expectations
			->addHeader(
				ClientLib::AUTHORIZATION_HEADER_NAME,
				self::AUTHORIZATION_HEADER_PREFIX.' '.base64_encode($conn['username'].":".$conn['password'])
			)
			->addHeader(ClientLib::USER_AGENT_HEADER_NAME, ClientLib::USER_AGENT_HEADER_VALUE)
			->addHeader(ClientLib::CONNECTION_HEADER_NAME, ClientLib::CONNECTION_HEADER_VALUE)
			->sendsForm() // content type form
			->send();

		if (!is_object($response) || !isset($response->code) || $response->code != '200') return error('Authentication failed');
		if (!isset($response->body->access_token)) return error('No access token returned');

		/* Example Response:
			{
			"access_token": "d9c60404-1530-4b05-bb8e-0a0b0f321726",
			"token_type": "bearer",
			"expires_in": 41087,
			"scope": "read write ROLE_bildungseinrichtung
			ROLE_bildungseinrichtung_A"
			}
		*/
		$this->authentication = $response->body;

		// Calculate Expire Date
		$ttl = new DateTime();
		// make the date expire a bit earlier to avoid "invalid token" error
		$expires_in_seconds = $this->authentication->expires_in > self::TOKEN_EXPIRATION_OFFSET
			? $this->authentication->expires_in - self::TOKEN_EXPIRATION_OFFSET
			: $this->authentication->expires_in;
		$ttl->add(new DateInterval('PT'.($expires_in_seconds).'S'));
		$this->authentication->DateTimeExpires = $ttl;

		return success();
	}
}
