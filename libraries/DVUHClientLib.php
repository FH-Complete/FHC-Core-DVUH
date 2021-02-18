<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Library to Connect to DVUH Services
 */
class DVUHClientLib
{
	// Configs parameters names
	const ACTIVE_CONNECTION = 'fhc_dvuh_active_connection';
	const CONNECTIONS = 'fhc_dvuh_connections';

	const MISSING_REQUIRED_PARAMETERS = 'ERR001';
	const WRONG_WS_PARAMETERS = 'ERR002';
	const WS_REQUEST_FAILED = 'ERR003';
	const REQUEST_FAILED = 'ERR004';

	private $_connectionsArray;		// connections array

	private $_error;				// true if an error occurred
	private $_errorMessage;			// contains the error message

	private $_ci; // Code igniter instance

	/**
	 * Object initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

		$this->_ci->config->load('extensions/FHC-Core-DVUH/DVUHClient');
		$this->_ci->load->library('extensions/FHC-Core-DVUH/DVUHAuthLib');

		$this->_setPropertiesDefault(); // properties initialization
		$this->_setConnection(); // sets the connection parameters
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Performs a call to a remote web service
	 */
	public function call($url, $method, $getParametersArray = null, $postData = null)
	{
		// Checks if the api set name is valid
		if ($url == null || trim($url) == '')
			$this->_error(self::MISSING_REQUIRED_PARAMETERS, 'URL missing');

		// Checks if the method name is valid
		if ($method == null || trim($method) == '')
			$this->_error(self::MISSING_REQUIRED_PARAMETERS, 'Method is invalid');

		// Checks that the webservice parameters are present in an array
		if (!is_null($getParametersArray) && !is_array($getParametersArray))
			$this->_error(self::WRONG_WS_PARAMETERS, 'Parameters are missing or wrong');

		if ($this->isError())
			return null; // If an error was raised then return a null value

		return $this->_callRemoteService($url, $method, $getParametersArray, $postData);
	}

	/**
	 * Returns the error message stored in property _errorMessage
	 */
	public function getError()
	{
		return $this->_errorMessage;
	}

	/**
	 * Returns true if an error occurred, otherwise false
	 */
	public function isError()
	{
		return $this->_error;
	}

	/**
	 * Reset the library properties to default values
	 */
	public function resetToDefault()
	{
		$this->_error = false;
		$this->_errorMessage = '';
	}

	// --------------------------------------------------------------------------------------------
	// Private methods

	/**
	 * Initialization of the properties of this object
	 */
	private function _setPropertiesDefault()
	{
		$this->_connectionsArray = null;
		$this->_error = false;
		$this->_errorMessage = '';
	}

	/**
	 * Sets the connection
	 */
	private function _setConnection()
	{
		$activeConnectionName = $this->_ci->config->item(self::ACTIVE_CONNECTION);
		$connectionsArray = $this->_ci->config->item(self::CONNECTIONS);

		$this->_connectionsArray = $connectionsArray[$activeConnectionName];
	}

	/**
	 * Performs a remote web service call with the given name and parameters
	 */
	private function _callRemoteService($url, $method, $getParametersArray, $postData = null)
	{
		$response = null;

		// perform OAUTH2 Authentication
		$access_token = $this->_ci->dvuhauthlib->getToken();

		// Call Service
		$curl = curl_init();
		if ($method == 'GET')
		{
			$params = array();
			foreach($getParametersArray as $key=>$val)
			{
				$params[] = $key.'='.curl_escape($curl, $val);
			}
			$url .= '?'.implode('&', $params);
		}

		curl_setopt($curl, CURLOPT_URL, $this->_connectionsArray['portal'].$url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

		$headers = array(
			'Accept: application/xml',
			'Content-Type: application/xml',
			'Authorization: Bearer '.$access_token,
			'User-Agent: FHComplete',
			'Connection: Keep-Alive',
			'Expect:'
		);

		switch ($method)
		{
			case 'POST':
				curl_setopt($curl, CURLOPT_POST, true);
				if (!is_null($postData))
					curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
				break;

			case 'PUT':
				curl_setopt($curl, CURLOPT_PUT, true);
				break;

			case 'HEAD':
				curl_setopt($curl, CURLOPT_NOBODY, true);
				break;

			case 'GET':
			default:
				$headers[] = 'Content-Length: 0';
				break;
		}

		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

		$response = curl_exec($curl);
		$curl_info = curl_getinfo($curl);
		curl_close($curl);

		if (substr($curl_info['http_code'], 0,1) == '2')
		{
			return $response;
		}
		else
		{
			$this->_error(self::REQUEST_FAILED, 'HTTP Code not starting with 2 - Value:'.$curl_info['http_code'].$url.print_r($response,true));
			return null;
		}
	}

	/**
	 * Sets property _error to true and stores an error message in property _errorMessage
	 */
	private function _error($code, $message = 'Generic error')
	{
		$this->_error = true;
		$this->_errorMessage = $code.': '.$message;
	}
}
