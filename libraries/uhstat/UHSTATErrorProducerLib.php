<?php

require_once APPPATH.'/libraries/extensions/FHC-Core-DVUH/syncdata/ErrorProducerLib.php';

/**
 * Functionality for writing errors and warnings.
 * Any library extending this library is capable of producing errors and warnings.
 */
class UHSTATErrorProducerLib extends ErrorProducerLib
{
	public function __construct()
	{
		parent::__construct();
		$this->_ci->load->library('extensions/FHC-Core-DVUH/DVUHIssueLib');
	}

	/**
	 * Adds error to error list, and optionally write an issue.
	 * @param $error
	 * @param $issue
	 */
	protected function addError($error, $issue = null)
	{
		$errorObj = new stdClass();
		$errorObj->error = $error;
		$this->_addIssue($issue);

		$this->_errors[] = $errorObj;
	}

	/**
	 * Adds warning to warning list, and optionally write an issue.
	 * @param $warning
	 */
	protected function addWarning($warning, $issue = null)
	{
		$errorObj = new stdClass();
		$errorObj->error = $warning;
		$this->_addIssue($issue);

		$this->_warnings[] = $errorObj;
	}


	/**
	 * Adds issue (saves issue in db).
	 * @param object $issue
	 */
	private function _addIssue($issue)
	{
		// if issue is really an issue
		if (isset($issue->issue_fehler_kurzbz))
		{
			// add issue with its params
			$addIssueRes = $this->_ci->issueslib->addFhcIssue(
				$issue->issue_fehler_kurzbz,
				isset($issue->person_id) ? $issue->person_id : null,
				isset($issue->oe_kurzbz) ? $issue->oe_kurzbz : null,
				isset($issue->issue_fehlertext_params) ? $issue->issue_fehlertext_params : null,
				isset($issue->issue_resolution_params) ? $issue->issue_resolution_params : null
			);

			if (isError($addIssueRes))
				$this->addError(error("Fehler beim Hinzufügen des BIS issue".(isset($issue->person_id) ? " für Person mit ID ".$issue->person_id : "")));
		}

		// do nothing if not issue
		return success();
	}
}
