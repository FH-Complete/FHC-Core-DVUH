<?php

/**
 * Functionality for writing warnings.
 * Any library extending this library is capable of producing warnings.
 */
class DVUHWarningLib
{
	private $_warnings = array();

	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

		// load helpers
		$this->_ci->load->helper('extensions/FHC-Core-DVUH/hlp_sync_helper');
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
			$this->_warnings[] = createError($warningtext, $issue_fehler_kurzbz, $issue_fehlertext_params, $issue_resolution_prams);
		}
	}

	/**
	 * Gets occured warnings and resets them.
	 * @return array
	 */
	public function readWarnings()
	{
		$warnings = $this->_warnings;
		$this->_warnings = array();
		return $warnings;
	}
}
