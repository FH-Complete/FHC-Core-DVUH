<?php

/**
 * Implements the DVUH client basic funcitonalities
 */
abstract class DVUHClientModel extends CI_Model
{
	protected $_url; // service url

	public function __construct()
	{
		parent::__construct();
		$this->load->helper('extensions/FHC-Core-DVUH/hlp_uuid_helper');

		$this->load->library('extensions/FHC-Core-DVUH/DVUHClientLib');
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
			$this->dvuhclientlib->resetToDefault();
			return error('URL invalid');
		}

		// Call the webservice with the given parameters
		$wsResult = success(
			$this->dvuhclientlib->call(
				$this->_url,
				$method,
				$getParametersArray,
				$postData
			)
		);

		// If an error occurred
		if ($this->dvuhclientlib->isError())
		{
			$wsResult = error($this->dvuhclientlib->getError());
		}

		$this->dvuhclientlib->resetToDefault(); // reset to the default values

		return $wsResult;
	}
}
