<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Library to Connect to Services
 */
abstract class ClientLib
{
	// Configs parameters names
	const ACTIVE_CONNECTION = 'fhc_dvuh_active_connection';
	const URL_PATH = 'fhc_dvuh_path';
	const CONNECTIONS = 'fhc_dvuh_connections';

	const ERROR =			'ERR001';
	const CONNECTION_ERROR =	'ERR002';
	const JSON_PARSE_ERROR =	'ERR003';
	const MISSING_REQUIRED_PARAMETERS = 'ERR004';
	const WRONG_WS_PARAMETERS = 'ERR005';
	const WS_REQUEST_FAILED = 'ERR006';
	const REQUEST_FAILED = 'ERR007';
	const INVALID_AUTHENTICATION_TOKEN =	'ERR008';

	const AUTHORIZATION_HEADER_NAME = 'Authorization'; // authorization header name
	const AUTHORIZATION_HEADER_PREFIX = 'Bearer'; // authorization header prefix
	const USER_AGENT_HEADER_NAME = 'User-Agent'; // user agent header name
	const USER_AGENT_HEADER_VALUE = 'FHComplete'; // usrer agent header value
	const CONNECTION_HEADER_NAME = 'Connection'; // user agent header name
	const CONNECTION_HEADER_VALUE = 'Keep-Alive'; // usrer agent header value

	const HTTP_HEAD_METHOD = 'HEAD';
	const HTTP_GET_METHOD = 'GET';
	const HTTP_PUT_METHOD = 'PUT';
	const HTTP_POST_METHOD = 'POST';

	// HTTP codes
	const HTTP_OK = 200; // HTTP success code
	const HTTP_CREATED = 201; // HTTP success code created
	const HTTP_NO_CONTENT = 204; // HTTP success code no content (aka successfully updated)

	// HTTP error codes
	const HTTP_NOT_FOUND = 404;
	const HTTP_BAD_REQUEST = 400;

	protected $_connectionsArray;		// connections array
	protected $_httpMethod;		// http method used to call this server
	protected $_authToken;		// authentification token

	protected $_uriParametersArray;	// contains the parameters to give to the remote web service which are part of the url
	protected $_callParametersArray;	// contains the parameters to give to the remote web service

	protected $_error;				// true if an error occurred
	protected $_errorMessage;			// contains the error message
	protected $_errorCode;		// contains the error code

	protected $_ci; // Code igniter instance

	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

		$this->_ci->config->load('extensions/FHC-Core-DVUH/DVUHClient'); // load config

		$this->_ci->load->library('extensions/FHC-Core-DVUH/DVUHAuthLib');
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Calls web service
	 */
	abstract public function call($url, $httpMethod, $getParametersArray = null, $postData = null);

	/**
	 * Returns the error code stored in property _errorCode
	 */
	public function getErrorCode()
	{
		return $this->_errorCode;
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
		$this->_httpMethod = null;
		$this->_authToken = '';
		$this->_uriParametersArray = array();
		$this->_callParametersArray = array();
		$this->_error = false;
		$this->_errorMessage = '';
	}

	// --------------------------------------------------------------------------------------------
	// Protected methods

	/**
	 * Initialization of the properties of this object
	 */
	protected function _setPropertiesDefault()
	{
		$this->_connectionsArray = null;
		$this->_httpMethod = null;
		$this->_authToken = '';
		$this->_uriParametersArray = array();
		$this->_callParametersArray = array();
		$this->_error = false;
		$this->_errorCode = '';
		$this->_errorMessage = '';
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

	/**
	 * Sets the connection
	 */
	abstract protected function _generateURI($url);

	/**
	 * Returns true if the HTTP method used to call this server is HEAD
	 */
	protected function _isHEAD()
	{
		return $this->_httpMethod == self::HTTP_HEAD_METHOD;
	}

	/**
	 * Returns true if the HTTP method used to call this server is GET
	 */
	protected function _isGET()
	{
		return $this->_httpMethod == self::HTTP_GET_METHOD;
	}


	/**
	 * Returns true if the HTTP method used to call this server is PUT
	 */
	protected function _isPUT()
	{
		return $this->_httpMethod == self::HTTP_PUT_METHOD;
	}

	/**
	 * Returns true if the HTTP method used to call this server is POST
	 */
	protected function _isPOST()
	{
		return $this->_httpMethod == self::HTTP_POST_METHOD;
	}

	 /**
	 * Performs a remote web service call with the given name and parameters
	 */
	abstract protected function _callRemoteWebservice($url);

	/**
	 * Sets property _error to true and stores an error message in property _errorMessage
	 */
	protected function _error($code, $message = 'Generic error')
	{
		$this->_error = true;
		$this->_errorCode = $code;
		$this->_errorMessage = $message;
	}
}
