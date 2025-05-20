<?php

require_once APPPATH.'/libraries/extensions/FHC-Core-DVUH/syncdata/ErrorProducerLib.php';

/**
 * Functionality for writing errors and warnings.
 * Any library extending this library is capable of producing errors and warnings.
 */
class DVUHErrorProducerLib extends ErrorProducerLib
{
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Adds error to error list.
	 * @param $errortext
	 * @param string $issue_fehler_kurzbz if set, issue is created and added as error
	 * @param array $issue_fehlertext_params
	 * @param array $issue_resolution_prams
	 */
	protected function addError($errortext, $issue_fehler_kurzbz = null, $issue_fehlertext_params = null, $issue_resolution_prams = null)
	{
		if (isEmptyString($issue_fehler_kurzbz))
		{
			$this->_errors[] = error($errortext);
		}
		else
		{
			$this->_errors[] = createIssueObj($errortext, $issue_fehler_kurzbz, $issue_fehlertext_params, $issue_resolution_prams);
		}
	}

	/**
	 * Adds warning to warning list.
	 * @param $warningtext
	 * @param string $issue_fehler_kurzbz if set, issue is created and added as warning
	 * @param array $issue_fehlertext_params
	 * @param array $issue_resolution_prams
	 */
	protected function addWarning($warningtext, $issue_fehler_kurzbz = null, $issue_fehlertext_params = null, $issue_resolution_prams = null)
	{
		if (isEmptyString($issue_fehler_kurzbz))
		{
			$this->_warnings[] = error($warningtext);
		}
		else
		{
			$this->_warnings[] = createIssueObj($warningtext, $issue_fehler_kurzbz, $issue_fehlertext_params, $issue_resolution_prams);
		}
	}
}
