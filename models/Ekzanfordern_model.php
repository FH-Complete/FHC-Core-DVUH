<?php

require_once APPPATH.'/models/extensions/FHC-Core-DVUH/DVUHClientModel.php';
/**
 * get Ersatzkennzeichen for Students
 */
class Ekzanfordern_model extends DVUHClientModel
{
	/**
	 * Set the properties to perform calls
	 */
	public function __construct()
	{
		parent::__construct();
		$this->_url = 'ekzanfordern.xml';

		$this->load->library('extensions/FHC-Core-DVUH/DVUHCheckingLib');
		$this->load->library('extensions/FHC-Core-DVUH/DVUHConversionLib');
	}

	/**
	 * Execute post call.
	 * @param array $ekzData
	 * @param string $forcierungskey optional, for request of new EKZ when no results fit the person for which EKZ is needed.
	 * @return object success or error
	 */
	public function post($ekzData, $forcierungskey = null)
	{
		$postData = $this->retrievePostData($ekzData, $forcierungskey);

		if (isError($postData))
			$result = $postData;
		else
			$result = $this->_call('POST', null, getData($postData));

		return $result;
	}

	/**
	 * Retrieves necessary xml person and kontakt data for performing ekzanfordern call.
	 * @param array $ekzData
	 * @param string $forcierungskey
	 * @return object success or error
	 */
	public function retrievePostData($ekzData, $forcierungskey = null)
	{
		$result = null;

		if (isEmptyArray($ekzData))
			$result = error('EKZ Daten nicht gesetzt');
		else
		{
			$params = array(
				'ekzbasisdaten' => $ekzData
			);

			if (isset($forcierungskey))
				$params['forcierungskey'] = $forcierungskey;

			$postData = $this->load->view('extensions/FHC-Core-DVUH/requests/ekzanfordern', $params, true);

			$result = success($postData);
		}

		return $result;
	}
}
