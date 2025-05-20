<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

require_once APPPATH.'/libraries/extensions/FHC-Core-DVUH/ClientLib.php';

/**
 * Library to Connect to DVUH Services
 */
class DVUHClientLib extends ClientLib
{
	// Configs parameters names
	const API_VERSION = 'fhc_dvuh_api_version';

	const URI_TEMPLATE = '%s/%s/%s/%s'; // URI format

	/**
	 * Object initialization
	 */
	public function __construct()
	{
		parent::__construct();

		$this->_ci->load->library('extensions/FHC-Core-DVUH/XMLReaderLib');

		$this->_setPropertiesDefault(); // properties initialization
		$this->_setConnection(); // sets the connection parameters
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Performs a call to a remote web service
	 */
	public function call($url, $httpMethod, $getParametersArray = null, $postData = null)
	{
		// Checks if the url is valid
		if ($url == null || trim($url) == '')
			$this->_error(self::MISSING_REQUIRED_PARAMETERS, 'URL missing');

		// Checks if the method name is valid
		if ($httpMethod == null || trim($httpMethod) == '')
			$this->_error(self::MISSING_REQUIRED_PARAMETERS, 'Method is invalid');

		// Checks that the webservice parameters are present in an array
		if (!is_null($getParametersArray) && !is_array($getParametersArray))
			$this->_error(self::WRONG_WS_PARAMETERS, 'GET Parameters are missing or wrong');

		// Checks that the webservice parameters are present in an array
		if (!is_null($postData) && !is_string($postData))
			$this->_error(self::WRONG_WS_PARAMETERS, 'POST Parameters are missing or wrong');

		// perform OAUTH2 Authentication
		$authToken = $this->_ci->dvuhauthlib->getToken();

		// Checks that the webservice parameters are present in an array
		if (!isset($authToken) || isEmptyString($authToken))
			$this->_error(self::INVALID_AUTHENTICATION_TOKEN, 'Invalid authentication token');

		if ($this->isError())
			return null; // If an error was raised then return a null value

		// set properties
		$this->_httpMethod = $httpMethod;
		$this->_authToken = $authToken;
		$this->_uriParametersArray = $getParametersArray;
		$this->_callParametersArray = $postData;

		return $this->_callRemoteWebservice($this->_generateURI($url));
	}

	// --------------------------------------------------------------------------------------------
	// Protected methods

	/**
	 * Generate the URI to call the remote web service
	 */
	protected function _generateURI($url)
	{
		$uri = sprintf(
			self::URI_TEMPLATE,
			$this->_connectionsArray['portal'],
			$this->_ci->config->item(self::URL_PATH),
			$this->_ci->config->item(self::API_VERSION),
			$url
		);

		// append the query string to the URI, if any get parameters are passed

		if (isset($this->_uriParametersArray) && !isEmptyArray($this->_uriParametersArray))
		{
			$params = array();
			foreach($this->_uriParametersArray as $key => $val)
			{
				// replace single quotes by spaces, as server cannot handle single quotes for GET requests
				if ($this->_httpMethod == self::HTTP_GET_METHOD) $val = str_replace("'", " ", $val);
				$params[] = $key.'='.urlencode($val);
			}
			$uri .= '?'.implode('&', $params);
		}

		return $uri;
	}

	/**
	 * Performs a remote web service call with the given name and parameters
	 */
	protected function _callRemoteWebservice($uri)
	{
		$response = null;

		// Call Service
		try
		{
			if ($this->_isHEAD()) // else if the call was performed using a HTTP HEAD...
			{
				$response = $this->_callHEAD($uri); // ...calls the remote web service with the HTTP HEAD method
			}
			elseif ($this->_isGET()) // if the call was performed using a HTTP GET...
			{
				$response = $this->_callGET($uri); // ...calls the remote web service with the HTTP GET method
			}
			elseif ($this->_isPUT()) // else if the call was performed using a HTTP PUT...
			{
				$response = $this->_callPUT($uri); // ...calls the remote web service with the HTTP PUT method
			}
			elseif ($this->_isPOST()) // else if the call was performed using a HTTP POST...
			{
				$response = $this->_callPOST($uri); // ...calls the remote web service with the HTTP POST method
			}

			// Checks the response of the remote web service and handles possible errors
			//$response = $this->_checkResponse($response);
		}
		catch (\Httpful\Exception\ConnectionErrorException $cee) // connection error
		{
			$this->_error(self::CONNECTION_ERROR, 'A connection error occurred while calling the remote server');
		}
		// Otherwise another error has occurred, most likely the result of the
		// remote web service is not valid so a parse error is raised
		catch (Exception $e)
		{
			$this->_error(self::REQUEST_FAILED, 'Request failed');
		}

		if (!is_object($response) || !isset($response->raw_body) || !is_string($response->raw_body))
		{
			$this->_error(
				self::REQUEST_FAILED,
				'Body missing'
			);
			return null;
		}

		if (!isset($response->code) || substr($response->code, 0, 1) != '2')
		{
			$this->_error(
				self::REQUEST_FAILED,
				'HTTP Code not starting with 2 - Value:'.($response->code ?? '').' '.$this->_getErrorInfoFromResponse($response->raw_body)
			);
			return null;
		}

		return $response->raw_body;
	}
	// --------------------------------------------------------------------------------------------
	// Private methods


	/**
	 * Performs a remote call using the HEAD HTTP method
	 */
	private function _callHEAD($uri)
	{
		return \Httpful\Request::head($uri)
			->expectsXml() // dangerous expectations
			->addHeader(self::AUTHORIZATION_HEADER_NAME, self::AUTHORIZATION_HEADER_PREFIX.' '.$this->_authToken)
			->addHeader(self::USER_AGENT_HEADER_NAME, self::USER_AGENT_HEADER_VALUE)
			->addHeader(self::CONNECTION_HEADER_NAME, self::CONNECTION_HEADER_VALUE)
			->sendsXml() // content type xml
			->send();
	}

	/**
	 * Performs a remote call using the GET HTTP method
	 * NOTE: parameters in a HTTP GET call are appended to the URI by _generateURI
	 */
	private function _callGET($uri)
	{
		/**
		 * 		$headers = array(
			'Accept: application/xml',
			'Content-Type: application/xml',
			'Authorization: Bearer '.$access_token,
			'User-Agent: FHComplete',
			'Connection: Keep-Alive',
			'Expect:'
		);
		 */
		return \Httpful\Request::get($uri)
			->expectsXml() // dangerous expectations
			->addHeader(self::AUTHORIZATION_HEADER_NAME, self::AUTHORIZATION_HEADER_PREFIX.' '.$this->_authToken)
			->addHeader(self::USER_AGENT_HEADER_NAME, self::USER_AGENT_HEADER_VALUE)
			->addHeader(self::CONNECTION_HEADER_NAME, self::CONNECTION_HEADER_VALUE)
			->sendsXml() // content type xml
			->send();
	}

	/**
	 * Performs a remote call using the PUT HTTP method
	 */
	private function _callPUT($uri)
	{
		return \Httpful\Request::put($uri)
			->expectsXml() // dangerous expectations
			->addHeader(self::AUTHORIZATION_HEADER_NAME, self::AUTHORIZATION_HEADER_PREFIX.' '.$this->_authToken)
			->addHeader(self::USER_AGENT_HEADER_NAME, self::USER_AGENT_HEADER_VALUE)
			->addHeader(self::CONNECTION_HEADER_NAME, self::CONNECTION_HEADER_VALUE)
			->sendsXml() // content type xml
			->body($this->_callParametersArray) // parameters in body
			->send();
	}

	/**
	 * Performs a remote call using the GET HTTP method
	 * NOTE: parameters in a HTTP GET call are appended to the URI by _generateURI
	 */
	private function _callPOST($uri)
	{
		return \Httpful\Request::post($uri)
			->expectsXml() // dangerous expectations
			->addHeader(self::AUTHORIZATION_HEADER_NAME, self::AUTHORIZATION_HEADER_PREFIX.' '.$this->_authToken)
			->addHeader(self::USER_AGENT_HEADER_NAME, self::USER_AGENT_HEADER_VALUE)
			->addHeader(self::CONNECTION_HEADER_NAME, self::CONNECTION_HEADER_VALUE)
			->sendsXml() // content type xml
			->body($this->_callParametersArray) // parameters in body
			->send();
	}

	/**
	 * Gets error information from returned response object.
	 */
	private function _getErrorInfoFromResponse($response)
	{
		// by default, error info is whole printed response
		$errorInfo = print_r($response, true);
		$errorObj = $this->_ci->xmlreaderlib->parseXml($response, array('fehler'));

		if (hasData($errorObj))
		{
			$errorObj = getData($errorObj);
			if (isset($errorObj->fehler) && is_array($errorObj->fehler))
			{
				// if error data present, create string with the data
				foreach ($errorObj->fehler as $err)
				{
					$fehlernummer = isset($err->fehlernummer) ? $err->fehlernummer.': ' : '';
					$fehlertext = isset($err->fehlertext) ? ' '.$err->fehlertext : '';
					$massnahme = isset($err->massnahme) ? '; '.$err->massnahme : '';
					$datenfeld = isset($err->fehlerquelle->datenfeld)
						? $err->fehlerquelle->datenfeld
						: (isset($err->datenfeld) && is_string($err->datenfeld) ? $err->datenfeld : '');

					$errorInfo = $fehlernummer.$datenfeld.$fehlertext.$massnahme;
				}
			}
		}

		return $errorInfo;
	}
}
