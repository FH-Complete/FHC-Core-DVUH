<?php

/**
 * Implements the DVUH client basic funcitonalities
 */
abstract class ClientModel extends CI_Model
{
	protected $_url; // service url
	protected $_clientLib;

	public function __construct()
	{
		parent::__construct();
		$this->load->helper('extensions/FHC-Core-DVUH/hlp_uuid_helper');
	}

	// --------------------------------------------------------------------------------------------
	// Protected methods

	/**
	 * Generic DVUH call. It checks also for specific blocking and non-blocking errors.
	 * @param string $method POST, GET, PUT, ...
	 * @param array $getParametersArray
	 * @param array $postData
	 * @return object success or error
	 */
	protected function _call($method, $getParametersArray, $postData = null)
	{
		// Checks if the url is valid
		if ($this->_url == null || trim($this->_url) == '')
		{
			$this->_clientLib->resetToDefault();
			return error('URL ungültig');
		}

		// Call the webservice with the given parameters
		$wsResult = success(
			$this->_clientLib->call(
				$this->_url,
				$method,
				$getParametersArray,
				$postData
			)
		);

		// If an error occurred
		if ($this->_clientLib->isError())
		{
			$wsResult = error($this->_clientLib->getError());
		}

		$this->_clientLib->resetToDefault(); // reset to the default values

		return $wsResult;
	}
}
