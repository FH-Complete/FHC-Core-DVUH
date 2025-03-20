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
	protected $_urlPath;				// url path

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
	public abstract function resetToDefault();

	// --------------------------------------------------------------------------------------------
	// Protected methods

	/**
	 * Initialization of the properties of this object
	 */
	abstract protected function _setPropertiesDefault();

	/**
	 * Sets the connection
	 */
	abstract protected function _setConnection();

	 /**
	 * Performs a remote web service call with the given name and parameters
	 */
	//abstract protected function _callRemoteService($url, $method, $getParametersArray, $postData = null);

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
