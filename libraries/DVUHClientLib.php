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
	public function call($url, $httpMethod, $getParametersArray = null, $postParametersArray = null)
	{
		// Checks if the url is valid
		if ($url == null || trim($url) == '')
			$this->_error(self::MISSING_REQUIRED_PARAMETERS, 'URL missing');

		// Checks if the method name is valid
		if ($httpMethod == null || trim($httpMethod) == '')
			$this->_error(self::MISSING_REQUIRED_PARAMETERS, 'Method is invalid');

		// Checks that the webservice parameters are present in an array
		if (!is_null($getParametersArray) && !is_array($getParametersArray))
			$this->_error(self::WRONG_WS_PARAMETERS, 'Parameters are missing or wrong');

		if ($this->isError())
			return null; // If an error was raised then return a null value

		return $this->_callRemoteService($url, $httpMethod, $getParametersArray, $postParametersArray);
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
	// Protected methods

	/**
	 * Initialization of the properties of this object
	 */
	protected function _setPropertiesDefault()
	{
		$this->_connectionsArray = null;
		$this->_error = false;
		$this->_errorMessage = '';
	}

	/**
	 * Sets the connection
	 */
	protected function _setConnection()
	{
		$activeConnectionName = $this->_ci->config->item(self::ACTIVE_CONNECTION);
		$connectionsArray = $this->_ci->config->item(self::CONNECTIONS);

		$this->_urlPath = $this->_ci->config->item(self::URL_PATH).'/'.$this->_ci->config->item(self::API_VERSION);
		$this->_connectionsArray = $connectionsArray[$activeConnectionName];
	}

	// --------------------------------------------------------------------------------------------
	// Private methods

	/**
	 * Performs a remote web service call with the given name and parameters
	 */
	private function _callRemoteService($url, $httpMethod, $getParametersArray, $postData = null)
	{
		$response = null;

		// perform OAUTH2 Authentication
		$access_token = $this->_ci->dvuhauthlib->getToken();

		// Call Service
		$curl = curl_init();
		if (isset($getParametersArray) && !isEmptyArray($getParametersArray))
		{
			$params = array();
			foreach($getParametersArray as $key => $val)
			{
				// replace single quotes by spaces, as server cannot handle single quotes for GET requests
				if ($httpMethod == self::HTTP_GET_METHOD) $val = str_replace("'", " ", $val);
				$params[] = $key.'='.curl_escape($curl, $val);
			}
			$url .= '?'.implode('&', $params);
		}

		curl_setopt($curl, CURLOPT_URL, $this->_connectionsArray['portal'].'/'.$this->_urlPath.'/'.$url);
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

		switch ($httpMethod)
		{
			case self::HTTP_POST_METHOD:
				curl_setopt($curl, CURLOPT_POST, true);
				if (!is_null($postData))
					curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
				break;

			case self::HTTP_PUT_METHOD:
				curl_setopt($curl, CURLOPT_CUSTOMREQUEST, self::HTTP_PUT_METHOD);
				if (!is_null($postData))
					curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
				break;

			case self::HTTP_HEAD_METHOD:
				curl_setopt($curl, CURLOPT_NOBODY, true);
				break;

			case self::HTTP_GET_METHOD:
			default:
				$headers[] = 'Content-Length: 0';
				break;
		}

		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

		$response = curl_exec($curl);
		$curl_info = curl_getinfo($curl);
		curl_close($curl);

		if (substr($curl_info['http_code'], 0, 1) == '2')
		{
			return $response;
		}
		else
		{
			$this->_error(
				self::REQUEST_FAILED,
				'HTTP Code not starting with 2 - Value:'.$curl_info['http_code'].' '.$url.' '.$this->_getErrorInfoFromResponse($response)
			);
			return null;
		}
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
						: (isset($err->datenfeld) ? $err->datenfeld : '');

					$errorInfo = $fehlernummer.$datenfeld.$fehlertext.$massnahme;
				}
			}
		}

		return $errorInfo;
	}
}
