<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

require_once APPPATH.'/libraries/extensions/FHC-Core-DVUH/ClientLib.php';

/**
 * Manages UHSTAT API calls
 */
class UHSTATClientLib extends ClientLib
{
	// Configs parameters names
	const UHSTAT_API_VERSION = 'fhc_dvuh_uhstat_api_version';
	const UHSTAT_PATH = 'fhc_dvuh_uhstat_path';

	const URI_TEMPLATE = '%s/%s/%s/%s/%s'; // URI format
	const AUTHORIZATION_HEADER_NAME = 'Authorization'; // accept header name
	const AUTHORIZATION_HEADER_PREFIX = 'Bearer'; // accept header name
	const ACCEPT_HEADER_VALUE = 'application/json'; // accept header value

	private $_wsFunction;		// path to the webservice

	private $_httpMethod;		// http method used to call this server
	private $_authToken;			// token for authentication
	private $_uriParametersArray;	// contains the parameters to give to the remote web service which are part of the url
	private $_callParametersArray;	// contains the parameters to give to the remote web service

	private $_hasData;		// indicates if there are data in the response or not
	private $_emptyResponse;	// indicates if the response is empty or not
	private $_hasBadRequestError;	// indicates if a "bad request" error was returned
	private $_hasNotFoundError;	// indicates if a "not found" error was returned

	/**
	 * Object initialization
	 */
	public function __construct()
	{
		parent::__construct();

		// load libraries
		$this->_ci->load->library('extensions/FHC-Core-DVUH/DVUHAuthLib');

		$this->_setPropertiesDefault(); // properties initialization

		$this->_setConnection(); // loads the configurations
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Performs a call to a remote web service
	 */
	public function call($url, $httpMethod = self::HTTP_GET_METHOD, $getParametersArray = array(), $postParametersArray = array())
	{
		// Checks if the webservice name is provided and it is valid
		// Checks if the url is valid
		if ($url == null || trim($url) == '')
			$this->_error(self::MISSING_REQUIRED_PARAMETERS, 'URL missing');

		// Checks that the HTTP method required is valid
		if ($httpMethod != null
		&& ($httpMethod == self::HTTP_HEAD_METHOD || $httpMethod == self::HTTP_GET_METHOD || $httpMethod == self::HTTP_PUT_METHOD))
		{
			$this->_httpMethod = $httpMethod;
		}
		else
		{
			$this->_error(self::WRONG_WS_PARAMETERS, 'Have you ever heard about HTTP methods?');
		}

		// Checks that the webservice uri parameters are present in an array
		if (is_array($getParametersArray))
		{
			$this->_uriParametersArray = $getParametersArray;
		}
		else
		{
			$this->_error(self::WRONG_WS_PARAMETERS, 'Are those uri parameters?');
		}

		// Checks that the webservice parameters are present in an array
		if (is_array($postParametersArray))
		{
			$this->_callParametersArray = $postParametersArray;
		}
		else
		{
			$this->_error(self::WRONG_WS_PARAMETERS, 'Are those parameters?');
		}

		// perform OAUTH2 Authentication
		$authToken = $this->_ci->dvuhauthlib->getToken();

		if (isset($authToken))
		{
			$this->_authToken = $authToken;
		}
		else
		{
			$this->_error(self::INVALID_AUTHENTICATION_TOKEN, getError($authToken));
		}

		if ($this->isError()) return null; // If an error was raised then return a null value


		return $this->_callRemoteWS($this->_generateURI($url)); // perform a remote ws call with the given uri
	}

	/**
	 * Returns true if the response contains data, otherwise false
	 */
	public function hasData()
	{
		return $this->_hasData;
	}

	/**
	 * Returns true if the response was empty, otherwise false
	 */
	public function hasEmptyResponse()
	{
		return $this->_emptyResponse;
	}

	/**
	 * Returns true if the response has a bad request error, otherwise false
	 */
	public function hasBadRequestError()
	{
		return $this->_hasBadRequestError;
	}

	/**
	 * Returns true if the response has a not found error, otherwise false
	 */
	public function hasNotFoundError()
	{
		return $this->_hasNotFoundError;
	}

	/**
	 * Reset the library properties to default values
	 */
	public function resetToDefault()
	{
		$this->_wsFunction = null;
		$this->_httpMethod = null;
		$this->_authToken = '';
		$this->_uriParametersArray = array();
		$this->_callParametersArray = array();
		$this->_error = false;
		$this->_errorMessage = '';
		$this->_hasData = false;
		$this->_emptyResponse = false;
		$this->_hasBadRequestError = false;
		$this->_hasNotFoundError = false;
	}

	// --------------------------------------------------------------------------------------------
	// Protected methods

	/**
	 * Initialization of the properties of this object
	 */
	protected function _setPropertiesDefault()
	{
		$this->_connectionsArray = null;
		$this->_wsFunction = null;
		$this->_httpMethod = null;
		$this->_authToken = '';
		$this->_uriParametersArray = array();
		$this->_callParametersArray = array();
		$this->_error = false;
		$this->_errorMessage = '';
		$this->_hasData = false;
		$this->_emptyResponse = false;
		$this->_hasBadRequestError = false;
		$this->_hasNotFoundError = false;
	}

	/**
	 * Sets the connection
	 */
	protected function _setConnection()
	{
		$activeConnectionName = $this->_ci->config->item(self::ACTIVE_CONNECTION);
		$connectionsArray = $this->_ci->config->item(self::CONNECTIONS);

		$this->_connectionsArray = $connectionsArray[$activeConnectionName];
	}

	// --------------------------------------------------------------------------------------------
	// Private methods

	/**
	 * Returns true if the HTTP method used to call this server is GET
	 */
	private function _isGET()
	{
		return $this->_httpMethod == self::HTTP_GET_METHOD;
	}

	/**
	 * Returns true if the HTTP method used to call this server is POST
	 */
	private function _isHEAD()
	{
		return $this->_httpMethod == self::HTTP_HEAD_METHOD;
	}

	/**
	 * Returns true if the HTTP method used to call this server is MERGE
	 */
	private function _isPUT()
	{
		return $this->_httpMethod == self::HTTP_PUT_METHOD;
	}

	/**
	 * Generate the URI to call the remote web service
	 */
	private function _generateURI($url)
	{
		$uri = sprintf(
			self::URI_TEMPLATE,
			$this->_connectionsArray['portal'],
			$this->_ci->config->item(self::URL_PATH),
			$this->_ci->config->item(self::UHSTAT_PATH),
			$this->_ci->config->item(self::UHSTAT_API_VERSION),
			$url
		);

		// If the call was performed using a HTTP GET then append the query string to the URI
		$queryString = '';

		// Create the query string
		foreach ($this->_uriParametersArray as $value)
		{
				$queryString .= '/'.urlencode($value);
		}

		$uri .= $queryString;

		return $uri;
	}

	/**
	 * Performs a remote web service call with the given uri and returns the result after having checked it
	 */
	private function _callRemoteWS($uri)
	{
		$response = null;

		try
		{
			if ($this->_isGET()) // if the call was performed using a HTTP GET...
			{
				$response = $this->_callGET($uri); // ...calls the remote web service with the HTTP GET method
			}
			elseif ($this->_isHEAD()) // else if the call was performed using a HTTP HEAD...
			{
				$response = $this->_callHEAD($uri); // ...calls the remote web service with the HTTP HEAD method
			}
			elseif ($this->_isPUT()) // else if the call was performed using a HTTP MERGE...
			{
				$response = $this->_callPUT($uri); // ...calls the remote web service with the HTTP MERGE method
			}

			// Checks the response of the remote web service and handles possible errors
			$response = $this->_checkResponse($response);
		}
		catch (\Httpful\Exception\ConnectionErrorException $cee) // connection error
		{
			$this->_error(self::CONNECTION_ERROR, 'A connection error occurred while calling the remote server');
		}
		// Otherwise another error has occurred, most likely the result of the
		// remote web service is not json so a parse error is raised
		catch (Exception $e)
		{
			$this->_error(self::JSON_PARSE_ERROR, 'The remote server answered with a not valid json');
		}

		if ($this->isError()) return null; // If an error was raised then return a null value

		return $response;
	}

	/**
	 * Performs a remote call using the HEAD HTTP method
	 */
	private function _callHEAD($uri)
	{
		return \Httpful\Request::head($uri)
			->expectsJson() // dangerous expectations
			->addHeader(self::AUTHORIZATION_HEADER_NAME, self::AUTHORIZATION_HEADER_PREFIX.' '.$this->_authToken)
			//->sendsJson() // content type json
			->send();
	}

	/**
	 * Performs a remote call using the GET HTTP method
	 * NOTE: parameters in a HTTP GET call are appended to the URI by _generateURI
	 */
	private function _callGET($uri)
	{
		return \Httpful\Request::get($uri)
			->expectsJson() // dangerous expectations
			->addHeader(self::AUTHORIZATION_HEADER_NAME, self::AUTHORIZATION_HEADER_PREFIX.' '.$this->_authToken)
			->send();
	}


	/**
	 * Performs a remote call using the PUT HTTP method
	 */
	private function _callPUT($uri)
	{
		return \Httpful\Request::put($uri)
			//->expectsJson() // dangerous expectations
			->addHeader(self::AUTHORIZATION_HEADER_NAME, self::AUTHORIZATION_HEADER_PREFIX.' '.$this->_authToken)
			->body($this->_callParametersArray) // parameters in body
			->sendsJson() // content type json
			->send();
	}

	/**
	 * Check HTTP response for errors.
	 */
	private function _checkResponse($response)
	{
		$checkResponse = null;

		// If NOT an empty response
		if (is_object($response) && isset($response->code))
		{
			// Checks the HTTP response code
			// If it is a success
			if ($response->code == self::HTTP_OK || $response->code == self::HTTP_CREATED || $response->code == self::HTTP_NO_CONTENT)
			{
				// If body is not empty
				if (isset($response->body))
				{
					// otherwise everything is fine
					// If data are present in the body of the response
					$checkResponse = $response->body; // returns a success

					// Set property _hasData
					$this->_hasData = !isEmptyArray($response->body);
				}
				else // ...if body empty
				{
					$this->_hasData = false;

					// If the response body is empty and an update was previously performed then return the request payload
					// alias: data sent within the request
					if (isset($response->request) && isset($response->request->payload))
					{
						$checkResponse = $response->request->payload;
					}
				}
			}
			else // otherwise checks what error occurred
			{
				// set error flags
				if ($response->code == self::HTTP_BAD_REQUEST) $this->_hasBadRequestError = true;
				if ($response->code == self::HTTP_NOT_FOUND) $this->_hasNotFoundError = true;

				$errorCode = self::ERROR; // generic error code by default
				$errorMessage = 'A fatal error occurred on the remote server'; // default error message

				// Checks if the body is present and the needed data are present
				if (isset($response->body) && is_object($response->body))
				{
					// Try to retrieve the error message from body
					if (isset($response->body->errors) && !isEmptyArray($response->body->errors))
					{
						$errors = array();

						foreach ($response->body->errors as $error)
						{
							if (is_string($error))
								$errors[] = $error;
							elseif (isset($error->title) && is_string($error->title))
								$errors[] = $error->title;
						}

						$errorMessage = implode(', ', $errors);
					}
				}
				// If some info is present
				elseif (isset($response->raw_body))
				{
					$errorMessage .= $response->raw_body;
				}
				else // Otherwise return the entire JSON encoded response
				{
					$errorMessage .= json_encode($response);
				}

				// Finally set the error!
				$this->_error($errorCode, $errorMessage.'; HTTP code: '.$response->code);
			}
		}
		else // if the response has no body
		{
			$this->_emptyResponse = true; // set property _hasData to false
		}

		return $checkResponse;
	}
}
