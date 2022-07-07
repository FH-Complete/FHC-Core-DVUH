<?php

/**
 * Contains logic for interaction of FHC with DVUH.
 * This includes initializing webservice calls for modifiying data in DVUH, and updating data in FHC accordingly.
 */
class DVUHManagementLib
{
	protected $_ci; // code igniter instance
	protected $_dbModel; // database
	protected $_be; // Bildungseinrichtung code

	/**
	 * Library initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

		// load libraries
		$this->_ci->load->library('extensions/FHC-Core-DVUH/XMLReaderLib');

		// load helpers
		$this->_ci->load->helper('extensions/FHC-Core-DVUH/hlp_sync_helper');

		// load configs
		$this->_ci->config->load('extensions/FHC-Core-DVUH/DVUHClient');
		$this->_ci->config->load('extensions/FHC-Core-DVUH/DVUHSync');
		$this->_be = $this->_ci->config->item('fhc_dvuh_be_code');

		$this->_ci->load->model('person/Person_model', 'PersonModel');
		$this->_dbModel = new DB_Model(); // get db
	}

	// --------------------------------------------------------------------------------------------
	// Protected methods

	/**
	 * Constructs response array consisting of information and the result itself.
	 * Info is passed for logging/displaying.
	 * @param object $result main response data
	 * @param array $infos array with info strings
	 * @param array $warnings array with warning strings
	 * @param bool $getWarningsFromResult if true, parse the result for warnings and include them in response
	 * @param array $warningCodesToExcludeFromIssues array with fehlercodes which are not considered issues
	 * @return object response object with result, infos and warnings
	 */
	protected function getResponseArr(
		$result,
		$infos = null, $warnings = null, $getWarningsFromResult = false, $warningCodesToExcludeFromIssues = array()
	)
	{
		$responseArr = array();
		$responseArr['infos'] = isset($infos) ? $infos : array();
		$responseArr['result'] = $result;
		$responseArr['warnings'] = isset($warnings) ? $warnings : array();

		if ($getWarningsFromResult === true && !isEmptyString($result))
		{
			if (!is_array($result))
				$result = array(success($result));

			foreach ($result as $xmlstr)
			{
				if (hasData($xmlstr))
				{
					$xmlstr = getData($xmlstr);
					$warningsRes = $this->_ci->xmlreaderlib->parseXmlDvuhWarnings($xmlstr);

					if (isError($warningsRes))
						return error('Fehler beim Auslesen der Warnungen');

					if (hasData($warningsRes))
					{
						$warningtext = '';
						$parsedWarnings = array();

						foreach (getData($warningsRes) as $warning)
						{
							if (!isEmptyString($warningtext))
								$warningtext .= ', ';
							$warningtext .= $warning->fehlertextKomplett;
							if (!isEmptyArray($warningCodesToExcludeFromIssues)
								&& in_array($warning->fehlernummer, $warningCodesToExcludeFromIssues))
							{
								unset($warning->fehlernummer); // unset fehlernummer if it doesn't need to be written as issue
							}

							$parsedWarnings[] = $warning;
						}
						$responseArr['warnings'][] = error($warningtext, $parsedWarnings);
					}
				}
			}
		}

		return success($responseArr);
	}
}
