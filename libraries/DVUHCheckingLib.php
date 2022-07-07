<?php

/**
 * Library for checking validity of data before reporting to DVUH.
 */
class DVUHCheckingLib
{
	private $_ci;

	const DVUH_STGKZ_LENGTH = 4;
	const DVUH_ERHALTER_LENGTH = 3;

	/**
	 * Library initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

		// load helpers
		$this->_ci->load->helper('extensions/FHC-Core-DVUH/hlp_sync_helper');

		// load configs
		$this->_ci->config->load('extensions/FHC-Core-DVUH/DVUHSync');
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Checks an adress for validity.
	 * @param object $addr
	 * @return error or success with true/false (valid or not)
	 */
	public function checkAdresse($addr)
	{
		$result = success(true);

		$errorText = '';

		if (!isset($addr['ort']) || isEmptyString($addr['ort']) || !validateXmlTextValue($addr['ort']))
			$errorText .= (!isEmptyString($errorText) ? ', ' : '') . 'Ort (Feld Gemeinde) fehlt oder enthält Sonderzeichen';

		if (!isset($addr['plz']) || isEmptyString($addr['plz']) || !validateXmlTextValue($addr['plz']))
			$errorText .= (!isEmptyString($errorText) ? ', ' : '') . 'Plz fehlt oder enthält Sonderzeichen';

		if (!isset($addr['strasse']) || isEmptyString($addr['strasse']) || !validateXmlTextValue($addr['strasse']))
			$errorText .= (!isEmptyString($errorText) ? ', ' : '') . 'Strasse fehlt oder enthält Sonderzeichen';

		if (!isset($addr['staat']) || isEmptyString($addr['staat']))
			$errorText .= (!isEmptyString($errorText) ? ', ' : '') . 'Nation fehlt';

		if (!isEmptyString($errorText))
			$result = error($errorText);

		return $result;
	}

	/**
	 * Checks Matrikelnummer for validity.
	 * @param string $svnr
	 * @return bool valid or not
	 */
	public function checkMatrikelnummer($svnr)
	{
		return preg_match("/^\d{8}$/", $svnr) === 1;
	}

	/**
	 * Checks Ersatzkennzeichen for validity.
	 * @param string $ekz
	 * @return bool valid or not
	 */
	public function checkEkz($ekz)
	{
		return preg_match('/^[A-Z]{4}[0-9]{6}$/', $ekz) === 1;
	}

	/**
	 * Checks Bpk for validity.
	 * @param string $bpk
	 * @return bool valid or not
	 */
	public function checkBpk($bpk)
	{
		return preg_match("/^([A-Za-z0-9+\/]{27})=$/", $bpk) === 1;
	}

	/**
	 * Checks Bpk for validity.
	 * @param string $bpk
	 * @return bool valid or not
	 */
	public function checkPersonenkennzeichen($perskz)
	{
		return preg_match("/^\d{10}$/", $perskz);
	}

	/**
	 * Checks if a student is ausserordentlich.
	 * @param string $personenkennzeichen
	 * @return bool true if ausserordentlich, false otherwise
	 */
	public function checkIfAusserordentlich($personenkennzeichen)
	{
		$ausserordentlich_prefix = $this->_ci->config->item('fhc_dvuh_sync_ausserordentlich_prefix');
		return mb_substr($personenkennzeichen, 3, 1) == $ausserordentlich_prefix;
	}

	/**
	 * Checks if studiengang kz is valid to report.
	 * @param int $melde_studiengang_kz
	 * @return bool true if valid, false otherwise
	 */
	public function checkStudiengangkz($melde_studiengang_kz)
	{
		$stgkzValid = is_numeric($melde_studiengang_kz);

		// length must be erhalter kz length + studiengang kz length
		if (strlen((string)$melde_studiengang_kz) !== self::DVUH_ERHALTER_LENGTH + self::DVUH_STGKZ_LENGTH)
		{
			$stgkzValid = false;
		}

		return $stgkzValid;
	}
}
